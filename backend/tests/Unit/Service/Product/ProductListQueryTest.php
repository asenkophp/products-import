<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Product;

use App\Http\ValidationException;
use App\Service\Product\ProductListQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductListQueryTest extends TestCase
{
    public function testDefaults(): void
    {
        $query = ProductListQuery::fromArray([]);

        self::assertSame(1, $query->page);
        self::assertSame(20, $query->limit);
        self::assertNull($query->name);
        self::assertNull($query->priceMin);
        self::assertNull($query->priceMax);
    }

    public function testParsesParams(): void
    {
        $query = ProductListQuery::fromArray([
            'page' => '3', 'limit' => '10', 'name' => '  бермуды ', 'price_min' => '100', 'price_max' => '999,5',
        ]);

        self::assertSame(3, $query->page);
        self::assertSame(10, $query->limit);
        self::assertSame('бермуды', $query->name);
        self::assertSame('100.00', $query->priceMin);
        self::assertSame('999.50', $query->priceMax);
        self::assertSame(20, $query->offset());
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalid(): iterable
    {
        yield 'страница 0' => [['page' => '0'], 'page'];
        yield 'дробная страница' => [['page' => '1.5'], 'page'];
        yield 'лимит больше 100' => [['limit' => '101'], 'limit'];
        yield 'цена текстом' => [['price_min' => 'abc'], 'price_min'];
        yield 'отрицательная цена' => [['price_max' => '-1'], 'price_max'];
        yield 'min больше max' => [['price_min' => '500', 'price_max' => '100'], 'price_min'];
    }

    /** @param array<string, string> $params */
    #[DataProvider('invalid')]
    public function testValidation(array $params, string $field): void
    {
        try {
            ProductListQuery::fromArray($params);
            self::fail('Ожидалась ошибка валидации');
        } catch (ValidationException $e) {
            self::assertArrayHasKey($field, $e->getErrors());
        }
    }
}
