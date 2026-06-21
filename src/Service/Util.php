<?php

declare(strict_types=1);

namespace App\Service;

use League\CommonMark\CommonMarkConverter;

class Util
{
    public const string IS_SLUG = '^[a-z0-9-]+$';

    public static function isSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $slug) === 1;
    }

    public static function slugify(string $text): string
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

    /**
     * Convert string to EAN or null
     * example: çè_éé("ç"-_é' --> 9782253936824 
     */
    public static function toEan(string $mot)
    {
        if (!mb_ereg_match("^[0-9à&é\"'(\-è_ç]{13}$", $mot)) {
            return null;
        }
        return \intval(mb_ereg_replace_callback(
            "[^\d]",
            fn($c) => mb_strpos("à&é\"'(-è_ç", $c[0]),
            $mot
        ));
    }

    /**
     * Return an url replacing placeholders like "{ean}", "{ean,3}", "{ean,-3}"
     */
    public static function formatUrl(string $urlTempl, string $ean)
    {
        return mb_ereg_replace_callback(
            "\{ean(?:,(-?\d+))?\}",
            function($c) use($ean) {
                $p = \strlen($c[1]) > 0 ? \intval($c[1]) : 0;
                return $p
                    ? $p > 0 ? substr($ean, 0, $p) : substr($ean, $p)
                    : $ean;
            },
            $urlTempl
        );
    }

    /**
     * @return (callable(string):bool)
     */
    public static function searchPredicate(string $mot)
    {
        //keep only words (at least 2 letters)
        $mots = mb_split(
            "[^a-zA-Z0-9]+",
            Util::replace_accents(trim($mot))
        );
        $mots = array_unique(array_filter($mots, fn($m) => mb_strlen($m) >= 2));
        usort($mots, fn($a, $b) => \strlen($b) - \strlen($a));

        //remove empty words and keep only the 4 first
        if (\count($mots) > 2) {
            $mots = \array_slice(
                array_filter(
                    $mots,
                    fn($m) => !mb_eregi(
                        "^(de|la|le|et|du|un|en|au|je|ne|ou|ma|on|ce|tu)$",
                        $m
                    )
                ),
                0,
                4
            );
        }

        $hasMots = fn(string $s) => true;
        if (mb_strlen(join($mots)) > 2) {
            // $reHighlight = "\\b" . join("|\\b", preg_quote($mots));
            $regexps = array_map(
                fn($m) => "\\b" . preg_quote($m),
                $mots
            );
            //TODO "exact phrase" between quotes
            $hasMots = function (string $s) use ($regexps) {
                $t = Util::replace_accents($s);
                foreach ($regexps as $re) {
                    if (!mb_eregi($re, $t)) {
                        return false;
                    }
                }
                return true;
            };
        }

        return $hasMots;
    }

    public static string $accents;

    public static function replace_accents(string $str)
    {
        if (!preg_match('/[\x80-\xff]/', $str)) {
            return $str;
        }
        return strtr(
            mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8'),
            self::$accents,
            'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'
        );
    }

    public static function from_timestamp(int $timestamp): \DateTime {
        // return date_create("@$timestamp"); // missing timezone!
        // with default timezone
        return date_create(date('Y-m-d H:i:s', $timestamp));
    }

    public static CommonMarkConverter $markdownParser;

    public static function init()
    {
        self::$accents = mb_convert_encoding('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ', 'ISO-8859-1', 'UTF-8');

        self::$markdownParser = new CommonMarkConverter([
            // 'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    public static function recursiveFileIterator(string $folder, string $regPattern)
    {
        return new \RegexIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folder)
            ),
            $regPattern,
            \RegexIterator::GET_MATCH
        );
    }
}

Util::init();
