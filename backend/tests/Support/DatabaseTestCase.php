<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Doctrine\ResettableEntityManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/** База для тестов с БД: перед каждым тестом таблицы очищаются. */
abstract class DatabaseTestCase extends TestCase
{
    private static ?ContainerInterface $container = null;

    protected static function container(): ContainerInterface
    {
        if (self::$container === null) {
            /** @var ContainerInterface $container */
            $container       = require __DIR__ . '/../../config/bootstrap.php';
            self::$container = $container;
        }

        return self::$container;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected static function service(string $id): object
    {
        /** @var T $service */
        $service = self::container()->get($id);

        return $service;
    }

    protected function em(): ResettableEntityManager
    {
        return self::service(ResettableEntityManager::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $em = $this->em();
        $em->resetIfClosed();
        $em->clear();
        $em->getConnection()->executeStatement(
            'TRUNCATE products, product_attributes, product_images, import_jobs, users RESTART IDENTITY CASCADE',
        );
    }

    protected function countRows(string $table): int
    {
        return (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }
}
