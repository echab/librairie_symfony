<?php

declare(strict_types=1);

namespace App\Service;

class ImageService
{
    public static function resample(string $filename, string $targetFilename, int $targetSize, float $margin = 1.1)
    {
        ['dirname' => $dirname, 'filename' => $basename, 'extension' => $extension] = pathinfo($targetFilename);
        $extension = strtolower($extension);
        $isJpeg = $extension === 'jpg' || $extension === 'jpeg';

        [$src_width, $src_height] = getimagesize($filename);
        $isSmall = $src_width <= $targetSize * $margin && $src_height <= $targetSize * $margin;
        if ($isSmall && $isJpeg) {
            return false;
        }

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $src_image = imagecreatefromjpeg($filename);
                break;
            case 'png':
                $src_image = imagecreatefrompng($filename);
                break;
            case 'gif':
                $src_image = imagecreatefromgif($filename);
                break;
            default:
                return false;
        }
        if ($src_image === false) { 
            return false;
        }

        if( !$isJpeg) {
            $extension = 'jpg';
            $targetFilename = "$dirname/$basename.$extension";
        }

        if ($isSmall) {
            $new_image = $src_image;
        } else {
            [$new_width, $new_height] = self::imageResize($src_width, $src_height, $targetSize);

            // imagecreatefromjpeg() imagecreatefrompng() imagecreatefromgif()
            // imagecopyresampled()

            $new_image = imagecreatetruecolor($new_width, $new_height);
            if ($new_image === false) {
                return false;
            }

            if (!imagecopyresampled($new_image, $src_image, 0, 0, 0, 0, $new_width, $new_height, $src_width, $src_height)) {
                return false;
            }
        }

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return imagejpeg($new_image, $targetFilename, 85);
            default:
                return imagepng($new_image, $targetFilename);
        }
    }

    public static function imageResize(int $width, int $height, int $targetSize)
    {
        $ratio = $targetSize / ($width > $height ? $width : $height);

        $width = (int)round($width * $ratio);
        $height = (int)round($height * $ratio);

        return [$width, $height];
    }
}
