<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

return static function (ContainerInterface $container): App {
    AppFactory::setContainer($container);
    $app = AppFactory::create();

    (require __DIR__ . '/middleware.php')($app);
    (require __DIR__ . '/routes.php')($app);

    return $app;
};
