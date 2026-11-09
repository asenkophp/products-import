<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Product;
use App\Tests\Support\ApiTestCase;

final class ProductApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        for ($i = 1; $i <= 12; $i++) {
            $product = new Product(sprintf('P-%02d', $i), $i <= 3 ? "Бермуды $i" : "Платье $i", sprintf('%d.00', $i * 100));
            $product->syncAttributes(['Цвет' => 'Nero']);
            $product->syncImages(['http://example.com/' . $i . '.jpg']);
            $this->em()->persist($product);
        }
        $this->em()->flush();
        $this->em()->clear();
    }

    public function testListWithPagination(): void
    {
        $data = $this->json($this->request('GET', '/api/products', ['page' => 2, 'limit' => 5]));

        self::assertSame(['page' => 2, 'limit' => 5, 'total' => 12, 'pages' => 3], $data['meta']);
        self::assertIsArray($data['items']);
        self::assertCount(5, $data['items']);
        self::assertSame(
            ['id', 'externalCode', 'name', 'price', 'discount', 'image'],
            array_keys((array) $data['items'][0]),
        );
    }

    public function testFilters(): void
    {
        $data = $this->json($this->request('GET', '/api/products', ['name' => 'бермуды', 'price_min' => 200]));

        self::assertIsArray($data['meta']);
        self::assertSame(2, $data['meta']['total']);
    }

    public function testValidationErrors(): void
    {
        $response = $this->request('GET', '/api/products', ['page' => 0, 'price_min' => 'abc']);

        self::assertSame(422, $response->getStatusCode());
        $error = $this->json($response)['error'];
        self::assertIsArray($error);
        self::assertIsArray($error['fields']);
        self::assertArrayHasKey('page', $error['fields']);
        self::assertArrayHasKey('price_min', $error['fields']);
    }

    public function testShowProduct(): void
    {
        $response = $this->request('GET', '/api/products/1');

        self::assertSame(200, $response->getStatusCode());
        $data = $this->json($response);
        self::assertSame('P-01', $data['externalCode']);
        self::assertSame([['id' => 1, 'key' => 'Цвет', 'value' => 'Nero']], $data['attributes']);
        self::assertIsArray($data['images']);
        self::assertCount(1, $data['images']);
    }

    public function testUnknownProductReturns404(): void
    {
        self::assertSame(404, $this->request('GET', '/api/products/9999')->getStatusCode());
    }
}
