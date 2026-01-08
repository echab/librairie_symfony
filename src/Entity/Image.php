<?php

declare(strict_types=1);

namespace App\Entity;

class Image
{
    public \DateTime $date;

    public int $fileSize;

    public int $width;
    public int $height;

    public function __construct(
        public readonly string $slug,
    ) {}
}
