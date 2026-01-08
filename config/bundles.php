<?php

// allow dev in prod, setting 'APP_ENV' => 'dev' into `.env.local.php`
$isWeb = str_starts_with($_SERVER['DOCUMENT_ROOT'], '/var/www/sites/librairielespa/public_html');
$prod_dev = $isWeb && $_SERVER['APP_ENV'] === 'dev';

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true, 'prod' => $prod_dev],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => !$prod_dev, 'test' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => !$prod_dev],
];
