<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Tests\Support\DatabaseTestCase;

/** CRUD товаров с атрибутами и картинками на реальной PostgreSQL. */
final class ProductRepositoryTest extends DatabaseTestCase
{
    private ProductRepository $products;

    protected function setUp(): void
    {
        parent::setUp();
        $this->products = self::service(ProductRepository::class);
    }

    private function createProduct(string $code, string $name = 'Товар', string $price = '100.00'): Product
    {
        $product = new Product($code, $name, $price);
        $product->update($name, 'Описание', $price, '10.00');
        $product->syncAttributes(['Цвет' => 'Nero', 'Размер' => 'M']);
        $product->syncImages(['http://example.com/1.jpg', 'http://example.com/2.jpg']);
        $this->products->save($product);
        $this->em()->flush();

        return $product;
    }

    public function testCreateAndRead(): void
    {
        $id = $this->createProduct('A-1', 'Бермуды')->getId();
        $this->em()->clear();

        $found = $this->products->findByExternalCode('A-1');
        self::assertNotNull($found);
        self::assertSame($id, $found->getId());

        $full = $this->products->findWithRelations((int) $id);
        self::assertNotNull($full);
        self::assertSame('Бермуды', $full->getName());
        self::assertSame('100.00', $full->getPrice());
        self::assertSame('10.00', $full->getDiscount());
        self::assertCount(2, $full->getAttributes());
        self::assertCount(2, $full->getImages());

        self::assertNull($this->products->findByExternalCode('missing'));
    }

    public function testUpdate(): void
    {
        $id = (int) $this->createProduct('A-1')->getId();
        $this->em()->clear();

        $product = $this->products->findWithRelations($id);
        self::assertNotNull($product);
        $product->update('Новое имя', null, '250.50', null);
        $product->syncAttributes(['Цвет' => 'Bianco', 'Бренд' => 'OMSA']);
        $this->em()->flush();
        $this->em()->clear();

        $updated = $this->products->findWithRelations($id);
        self::assertNotNull($updated);
        self::assertSame('Новое имя', $updated->getName());
        self::assertSame('250.50', $updated->getPrice());
        self::assertNull($updated->getDiscount());
        self::assertSame(2, $this->countRows('product_attributes'), 'Размер удалён, Бренд добавлен, Цвет обновлён');
    }

    public function testDeleteCascadesToAttributesAndImages(): void
    {
        $product = $this->createProduct('A-1');

        $this->em()->remove($product);
        $this->em()->flush();

        self::assertSame(0, $this->countRows('products'));
        self::assertSame(0, $this->countRows('product_attributes'));
        self::assertSame(0, $this->countRows('product_images'));
    }

    public function testPaginationAndFilters(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->createProduct(sprintf('P-%02d', $i), $i <= 5 ? "Бермуды №$i" : "Леггинсы №$i", sprintf('%d.00', $i * 100));
        }
        $this->em()->clear();

        $page = $this->products->paginate(2, 10);
        self::assertSame(25, $page['total']);
        self::assertCount(10, $page['items']);
        self::assertSame('P-11', $page['items'][0]->getExternalCode());

        $search = $this->products->paginate(1, 20, 'БЕРМУДЫ');
        self::assertSame(5, $search['total'], 'Поиск без учёта регистра');

        $range = $this->products->paginate(1, 20, null, '300.00', '700.00');
        self::assertSame(5, $range['total']);

        // границы диапазона включительно: 600, 700, 800
        $combined = $this->products->paginate(1, 20, 'леггинсы', '600.00', '800.00');
        self::assertSame(3, $combined['total']);
    }

    public function testFindMainImagePathsUsesFirstDownloadedImage(): void
    {
        $product = $this->createProduct('A-1');
        foreach ($product->getImages() as $image) {
            if ($image->getUrl() === 'http://example.com/2.jpg') {
                $image->setPath('/uploads/products/2.jpg');
            }
        }
        $this->em()->flush();

        $paths = $this->products->findMainImagePaths([(int) $product->getId()]);

        self::assertSame([(int) $product->getId() => '/uploads/products/2.jpg'], $paths);
        self::assertSame([], $this->products->findMainImagePaths([]));
    }
}
