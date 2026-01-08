<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ImageRepository
{
    private const POOL_IMAGES = 'images';

    private const CACHE_DURATION = 'P7D'; // 7 jours

    public function __construct(
        private string $imageUploadDir,
        private CacheInterface $pool,
    ) {}

    public function findLast(int $limit = 10)
    {
        $cache = $this->cachedImages();
        return new \LimitIterator(
            new \ArrayIterator($cache->images ?? []),
            0,
            $limit
        );
    }

    /**
     * Find a single image by slug (filename without extension)
     */
    public function find(string $slug): ?Image
    {
        return $this->cachedImages()->bySlug[$slug] ?? null;
    }

    /** @return string[] */
    public function save(UploadedFile $imageFile)
    {
        /** @var string[] */ $errors = [];

        $mtime = $imageFile->getMTime();
        if ($mtime === false) {
            $mtime = time();
        }
        $datePrefix = date('Y-m-d', $mtime);

        $folder = $this->imageUploadDir . DIRECTORY_SEPARATOR . date('Y', $mtime);
        if (!file_exists($folder)) {
            mkdir($folder);
        }

        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = Util::slugify($originalFilename);
        // $safeFilename = $slugger->slug($originalFilename); // SluggerInterface $slugger
        $newFilename = "$datePrefix-$safeFilename." . $imageFile->guessExtension();
        try {
            if (ImageService::resample(
                $imageFile->getPathname(),
                $folder . DIRECTORY_SEPARATOR . $newFilename,
                800
            )) {
                unlink($imageFile->getPathname());
            } else {
                $imageFile->move(
                    $folder,
                    $newFilename
                );
            }
            self::clearCache();
        } catch (FileException $e) {
            $errors[] = "Failed to upload image $originalFilename.";
        }

        return $errors;
    }

    public function cachedImages()
    {
        return $this->pool->get(self::POOL_IMAGES, function (ItemInterface $item) {
            $item->expiresAfter(new \DateInterval(self::CACHE_DURATION));

            return ImageCache::fromImages($this->loadAll());
        });
    }

    public function clearCache()
    {
        $this->pool->delete(self::POOL_IMAGES);
    }

    /**
     * Returns all non-expired images with metadata
     * @return \Generator<mixed, Image, mixed, void>
     */
    public function loadAll()
    {
        $pos = strlen($this->imageUploadDir) + 1;
        // $files = glob("$this->imageUploadDir/**/*.{png,jpg,jpeg,gif}", GLOB_BRACE);
        $files = Util::recursiveFileIterator($this->imageUploadDir, '/.*\.(?:png|jpg|jpeg|gif)/i');
        foreach ($files as $file) {
            yield self::fromFile($this->imageUploadDir, substr($file[0], $pos));
        }
    }

    public static function fromFile(string $folder, string $file): Image
    {
        $fullPath = $folder . DIRECTORY_SEPARATOR . $file;

        $mtime = filemtime($fullPath);
        if ($mtime === false) {
            $mtime = time();
        }

        $image = new Image(str_replace('\\', '/', $file));
        $image->date = Util::from_timestamp($mtime);
        $image->fileSize = filesize($fullPath);
        [$image->width, $image->height] = getimagesize($fullPath);

        return $image;
    }
}

class ImageCache
{
    public function __construct(
        /** @param Image[] */
        public readonly array $images,

        /** @param array<string, Image> */
        public readonly array $bySlug,
    ) {}

    /**
     * @param \Iterator<Image> $images
     * @return ImageCache
     */
    public static function fromImages(\Iterator $images)
    {
        $images = iterator_to_array($images);
        usort(
            $images,
            fn($a, $b) => $b->date->getTimestamp() - $a->date->getTimestamp()
        );

        /** @var array<string, Image> */ $bySlug = [];

        foreach ($images as $image) {
            $bySlug[$image->slug] = $image;
        }

        return new ImageCache(
            $images,
            $bySlug,
        );
    }
}
