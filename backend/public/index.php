<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;

/** @var ContainerInterface $container */
$container = require __DIR__ . '/../config/bootstrap.php';

(require __DIR__ . '/../config/app.php')($container)->run();
