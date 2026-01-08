<?php
// phpinfo(); exit();

use App\Kernel;

// require_once dirname(__DIR__) . '/vendor/autoload_runtime.php'; // from /public
require_once __DIR__ . '/vendor/autoload_runtime.php'; // from /

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
