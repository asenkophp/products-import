<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

/** EntityManager для phpstan-doctrine: знание о сущностях, ассоциациях и проверка DQL. */

/** @var ContainerInterface $container */
$container = require __DIR__ . '/../config/bootstrap.php';

return $container->get(EntityManagerInterface::class);
