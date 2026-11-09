<?php

declare(strict_types=1);

use DI\ContainerBuilder;

require_once __DIR__ . '/../vendor/autoload.php';

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(require __DIR__ . '/container.php');

return $containerBuilder->build();
