<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Container\ContainerInterface;

require_once __DIR__ . '/../vendor/autoload.php';

// Защита от случайной очистки рабочей базы: тесты работают только с БД *_test.
$testDb = (string) getenv('DB_NAME');
if (!str_ends_with($testDb, '_test')) {
    fwrite(STDERR, "Тесты должны работать с базой *_test, а сейчас DB_NAME={$testDb}. Запускайте через phpunit.xml.dist.\n");
    exit(1);
}

// 1. Отдельная тестовая база: создаём, если её ещё нет (подключаемся к служебной БД postgres).
$admin = DriverManager::getConnection([
    'driver'   => 'pdo_pgsql',
    'host'     => (string) (getenv('DB_HOST') ?: 'db'),
    'port'     => (int) (getenv('DB_PORT') ?: 5432),
    'dbname'   => 'postgres',
    'user'     => (string) getenv('DB_USER'),
    'password' => (string) getenv('DB_PASSWORD'),
]);
if ($admin->fetchOne('SELECT 1 FROM pg_database WHERE datname = ?', [$testDb]) === false) {
    $admin->executeStatement('CREATE DATABASE ' . $admin->quoteIdentifier($testDb));
}
$admin->close();

// 2. Схема пересоздаётся по сущностям перед каждым прогоном.
/** @var ContainerInterface $container */
$container = require __DIR__ . '/../config/bootstrap.php';
/** @var EntityManagerInterface $em */
$em       = $container->get(EntityManagerInterface::class);
$metadata = $em->getMetadataFactory()->getAllMetadata();
$tool     = new SchemaTool($em);
$tool->dropDatabase();
$tool->createSchema($metadata);
