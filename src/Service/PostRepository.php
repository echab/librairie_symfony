<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Rayon;
use App\Entity\Post;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PostRepository
{
    private const POOL = 'posts';

    private const CACHE_DURATION = 'P7D'; // 7 jours

    public function __construct(
        private string $postsDir,
        private CacheInterface $pool,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array{
     *   categories: ?string[],
     *   notCategories: ?string[],
     *   limit: ?positive-int
     * } $filters */
    public function findLast(array $filters)
    {
        $categories = $filters['categories'] ?? null;
        $notCategories = $filters['notCategories'] ?? null;

        $cache = $this->cachedPosts();
        return new \LimitIterator(
            $categories !== null
                ? (count($categories) === 1
                    ? new \ArrayIterator($cache->byCategory[$categories[0]] ?? [])
                    : new \CallbackFilterIterator(
                        new \ArrayIterator($cache->posts),
                        fn($post) => in_array($post->category, $categories),
                    )
                )
                : ($notCategories !== null
                    ? new \CallbackFilterIterator(
                        new \ArrayIterator($cache->posts),
                        fn($post) => !in_array($post->category, $notCategories),
                    )
                    : new \ArrayIterator($cache->posts)
                ),
            0,
            $filters['limit'] ?? 10
        );
    }

    /**
     * Find a single post by slug (filename without extension)
     */
    public function find(string $slug, string $category = 'a-la-une'): ?Post
    {
        return $this->cachedPosts()->bySlug["$category/$slug"] ?? null;
    }

    public function findByEan(int $ean): ?Post
    {
        return $this->cachedPosts()->byEan[$ean] ?? null;
    }

    /** @param int[] $eans @return array<int,Post> */
    public function findByEans(array $eans)
    {
        $c = $this->cachedPosts()->byEan;
        /** @var array<int,Post> */$posts = [];
        foreach ($eans as $ean) {
            $post = $c[$ean] ?? null;
            if ($post) {
                $posts[$ean] = $post;
            }
        }
        return $posts;
    }

    /**
     * @param \App\Entity\Post $post
     * @return string[]
     */
    public function save(Post $post, ?Post $prevPost)
    {
        /** @var string[] */ $errors = [];

        // trim all values
        foreach ($post as &$val) {
            if (is_string($val)) {
                $val = trim($val, " \n\r\t\v\0\"'");
            }
        }

        $datePrefix = $post->date->format('Y-m-d') . '-';

        // Generate slug if new post
        if (empty($post->slug)) {
            $post->slug = $datePrefix . Util::slugify($post->titre);
        }

        // Compose markdown with YAML front matter
        $yaml = [
            'titre' => $post->titre,
        ];
        if (isset($post->auteur)) {
            $yaml['auteur'] = $post->auteur;
        }
        if (isset($post->editeur)) {
            $yaml['editeur'] = $post->editeur;
        }
        if (isset($post->parution)) {
            $yaml['parution'] = $post->parution->format('Y-m-d');
        }
        if (isset($post->prix)) {
            $yaml['prix'] = $post->prix;
        }
        if (isset($post->ean)) {
            $yaml['ean'] = $post->ean;
        }
        if (isset($post->rayonCode)) {
            $yaml['rayonCode'] = $post->rayonCode;
        }
        $yaml['libraire'] = $post->libraire;
        $yaml['date'] = $post->date->format('Y-m-d');
        if (isset($post->expire)) {
            $yaml['expire'] = $post->expire->format('Y-m-d');
        }
        $yaml = YAML::dump($yaml);
        $markdownContent = "---\n$yaml---\n$post->markdown";

        $folders = [$this->postsDir];
        if (isset($post->rayonCode)) {
            $rayon = Rayon::byCode($post->rayonCode) ?? Rayon::$defaut;
            if (isset($rayon->parentCode)) {
                $folders[] = Rayon::byCode($rayon->parentCode)->slug;
            }
            $folders[] = $rayon->slug;
        } else {
            $folders[] = $post->category;
        }

        $folder = join('/', $folders);
        $file = "$folder/$post->slug.md";

        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        if (false === file_put_contents($file, $markdownContent)) {
            $errors[] = "Error writing post file $file";
        } else {
            // delete the previous post if it was into another folder
            if ($prevPost && $prevPost->category !== $post->category) {
                $prevFile = "$this->postsDir/$prevPost->category/$prevPost->slug.md";
                if (file_exists($prevFile)) {
                    unlink($prevFile);
                }
            }
        }

        $this->pool->delete(self::POOL); //clear cache

        return $errors;
    }

    public function cachedPosts()
    {
        return $this->pool->get(self::POOL, function (ItemInterface $item) {
            $item->expiresAfter(new \DateInterval(self::CACHE_DURATION));

            return PostCache::fromPosts($this->loadAll());
        });
    }

    public function clearCache()
    {
        $this->pool->delete(self::POOL);
    }

    /**
     * Returns all non-expired posts with metadata
     * @return \Generator<mixed, Post, mixed, void>
     */
    public function loadAll()
    {
        $now = date_create();
        $files = Util::recursiveFileIterator($this->postsDir, '/.*\.md$/');
        foreach ($files as $file) {
            $file = $file[0];
            $category = basename(dirname($file));
            $post = $this->fromMarkdownFile($file, $category);
            if (!isset($post->expire) || $post->expire > $now) {
                yield $post;
            }
        }
    }

    public function fromMarkdownFile(
        string $file,
        string $category,
    ): Post {
        $post = new Post($category);

        // $post->slug = basename($file, '.md');
        $post->slug = basename($file, '.md');

        $contents = file_get_contents($file);
        $post->date = Util::from_timestamp(filemtime($file));

        if (preg_match('/^---\s*(.*?)\s*---\s*(.*)$/s', $contents, $matches)) {
            $yaml = $matches[1];
            $post->markdown = $matches[2];

            try {
                $data = Yaml::parse($yaml);
            } catch (ParseException $ex) {
                $this->logger->error("Parsing yaml in $file: $ex");
            }
            $post->titre = $data['titre'] ?? $post->slug;

            if (isset($data['libraire'])) {
                $post->libraire = $data['libraire'];
            }
            if (isset($data['date'])) {
                $post->date = toDate($data['date']) ?? $post->date;
            }
            if (isset($data['expire'])) {
                $post->expire = toDate($data['expire']);
            }
            if (isset($data['ean'])) {
                $post->ean = (int)$data['ean'];
            }
            if (isset($data['auteur'])) {
                $post->auteur = $data['auteur'];
            }
            if (isset($data['editeur'])) {
                $post->editeur = $data['editeur'];
            }
            if (isset($data['parution'])) {
                $post->parution = toDate($data['parution']);
            }
            if (isset($data['rayonCode'])) {
                $post->rayonCode = $data['rayonCode'];
            } else if (isset($data['rayon'])) {
                $post->rayonCode = Rayon::bySlug($data['rayon'])->code;
            }
            if (isset($data['prix'])) {
                $post->prix = $data['prix'];
            }
        } else {
            $post->markdown = $contents;
            $post->titre = $post->slug;
        }

        return $post;
    }
}

class PostCache
{
    public function __construct(
        /** @param Post[] */
        public readonly array $posts,

        /** @param array<string, Post> */
        public readonly array $bySlug,

        /** @param array<int, Post> */
        public readonly array $byEan,

        /** @param array<string, Post[]> */
        public readonly array $byCategory,
    ) {}

    /**
     * @param \Iterator<Post> $posts
     * @return PostCache
     */
    public static function fromPosts(\Iterator $posts)
    {
        $posts = iterator_to_array($posts);
        usort(
            $posts,
            fn($a, $b) => $b->date->getTimestamp() - $a->date->getTimestamp()
        );

        /** @var array<string, Post> */ $bySlug = [];
        /** @var array<string, Post> */ $byEan = [];
        /** @var array<string, Post[]> */ $byCategory = [];

        foreach ($posts as $post) {
            $bySlug["$post->category/$post->slug"] = $post;
            if (isset($post->ean)) {
                $byEan[$post->ean] = $post;
            }
            if (!isset($byCategory[$post->category])) {
                $byCategory[$post->category] = [];
            }
            $byCategory[$post->category][] = $post;
        }

        return new PostCache(
            $posts,
            $bySlug,
            $byEan,
            $byCategory,
        );
    }
}

function toDate(string|int $d)
{
    try {
        return is_string($d)
            ? new \DateTime($d)
            : Util::from_timestamp($d);
    } catch (\Exception $ex) {
        return null;
    }
}
