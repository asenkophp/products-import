<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Pricing;

use App\Service\Pricing\DiscountCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiscountCalculatorTest extends TestCase
{
    /** @return iterable<string, array{float, float, float}> */
    public static function cases(): iterable
    {
        yield 'пример из файла' => [1320.0, 880.0, 33.33];
        yield 'без наценки' => [500.0, 500.0, 0.0];
        yield 'закупка выше продажи' => [100.0, 150.0, -50.0];
        yield 'бесплатная закупка' => [100.0, 0.0, 100.0];
    }

    #[DataProvider('cases')]
    public function testCalculatesDiscount(float $price, float $purchase, float $expected): void
    {
        self::assertSame($expected, DiscountCalculator::calculate($price, $purchase));
    }

    public function testRejectsZeroPrice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DiscountCalculator::calculate(0.0, 10.0);
    }
}
