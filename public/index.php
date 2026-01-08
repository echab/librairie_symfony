<?php
// phpinfo(); exit();

// if (isset($_REQUEST['page']) && $_REQUEST['page'] === 'install') { require_once '../install.php.inc'; exit(); }

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
