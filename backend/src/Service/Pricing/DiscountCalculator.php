<?php

declare(strict_types=1);

namespace App\Service\Pricing;

use InvalidArgumentException;

final class DiscountCalculator
{
    /** Скидка в % = (цена продажи − закупочная цена) / цена продажи × 100, с округлением до 2 знаков. */
    public static function calculate(float $price, float $purchasePrice): float
    {
        if ($price <= 0) {
            throw new InvalidArgumentException('Цена продажи должна быть больше нуля');
        }

        return round(($price - $purchasePrice) / $price * 100, 2);
    }
}
