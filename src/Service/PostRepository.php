<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Rayon;
use App\Entity\Post;
use App\Service\MarkdownService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PostRepository
{
    private const string POOL = 'posts';

    private const string CACHE_DURATION = 'P7D'; // 7 jours

    public function __construct(
        private string $postsDir,
        private MarkdownService $markdownService,
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
                ? (\count($categories) === 1
                    ? new \ArrayIterator($cache->byCategory[$categories[0]] ?? [])
                    : new \CallbackFilterIterator(
                        new \ArrayIterator($cache->posts),
                        fn($post) => \in_array($post->category, $categories),
                    )
                )
                : ($notCategories !== null
                    ? new \CallbackFilterIterator(
                        new \ArrayIterator($cache->posts),
                        fn($post) => !\in_array($post->category, $notCategories),
                    )
                    : new \ArrayIterator($cache->posts)
                ),
            0,
            $filters['limit'] ?? 10
        );
    }

    /**
     * @param array{
     *   mot: string,
     *   notCategories: ?string[],
     *   limit: ?positive-int
     * } $filters */
    public function find(array $filters)
    {
        $mot = $filters['mot'];
        $notCategories = $filters['notCategories'] ?? null;
        $predicate = Util::searchPredicate($mot);

        // TODO sort found result by pertinence (searchPredicate() could return the average position in the text)

        $cache = $this->cachedPosts();
        return new \LimitIterator(
            new \CallbackFilterIterator(
                new \ArrayIterator($cache->posts),
                fn($post) =>
                    ($notCategories === null || !\in_array($post->category, $notCategories))
                    && $predicate("$post->titre ". ($post->auteur ?? '') .' '. ($post->ean ?? '') .' '. ($post->markdown ?? ''))
            ),
            0,
            $filters['limit'] ?? 10
        );
    }

    /**
     * Find a single post by slug (filename without extension)
     */
    public function findOne(string $slug, string $category = 'a-la-une'): ?Post
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
            if (\is_string($val)) {
                $val = trim($val, " \n\r\t\v\0\"'");
            }
        }

        $datePrefix = $post->date->format('Y-m-d') . '-';

        // Generate slug if new post
        if (empty($post->slug)) {
            $post->slug = $datePrefix . Util::slugify($post->titre);
        }

        $markdownContent = $this->markdownService->buildMarkdown($post);

        $folder = $this->getFolder($post);
        $file = "$folder/$post->slug.md";

        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        if (false === file_put_contents($file, $markdownContent)) {
            $errors[] = "Error writing post file $file";
        } else {
            // delete the previous post if it was into another folder
            if ($prevPost && isset($prevPost->slug)) {
                $prevFile = "{$this->getFolder($prevPost)}/$prevPost->slug.md";
                if ($prevFile != $file && file_exists($prevFile)) {
                    unlink($prevFile);
                }
            }
        }

        $this->pool->delete(self::POOL); //clear cache

        return $errors;
    }

    public function getFolder(Post $post) {
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

        return join('/', $folders);
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
            $slug = pathinfo($file, PATHINFO_FILENAME);
            if (Util::isSlug($slug)) {
                $category = basename(dirname($file));
                $post = $this->markdownService->fromMarkdownFile($file, $category);
                if (!isset($post->expire) || $post->expire > $now) {
                    yield $post;
                }
            }
        }
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
        return \is_string($d)
            ? new \DateTime($d)
            : Util::from_timestamp($d);
    } catch (\Exception $ex) {
        return null;
    }
}
