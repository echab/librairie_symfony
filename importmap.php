<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "php bin/console importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'util' => [
        'path' => './assets/util.js',
    ],
    'stock' => [
        'path' => './assets/stock.js',
    ],
    'panier' => [
        'path' => './assets/panier.js',
    ],
    'image' => [
        'path' => './assets/image.js',
    ],
    'post' => [
        'path' => './assets/post.js',
    ],
    'gallery' => [
        'path' => './assets/gallery.js',
    ],
    'solid-js' => [
        'version' => '1.9.9',
    ],
    'solid-js/web' => [
        'version' => '1.9.9',
    ],
    'solid-js/html' => [
        'version' => '1.9.9',
    ],
];
