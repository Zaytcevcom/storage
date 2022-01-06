<?php

use DI\Container;
use Slim\Factory\AppFactory;

define('ROOT_DIR', __DIR__);

require ROOT_DIR . '/vendor/autoload.php';

$config = require ROOT_DIR . '/config/core.local.php';

// Configure the application via container
$app = AppFactory::createFromContainer(new Container());

require ROOT_DIR . '/config/dependencies.php';
require ROOT_DIR . '/config/middleware.php';

$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

require ROOT_DIR . '/config/handlers.php';

require ROOT_DIR . '/routes/web.php';
require ROOT_DIR . '/routes/method.php';

$app->run();