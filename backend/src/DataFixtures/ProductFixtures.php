<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use App\Service\Pricing\DiscountCalculator;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

/** Товары (таблица products). Данные детерминированы — при каждой загрузке одинаковые. */
final class ProductFixtures extends AbstractFixture
{
    public const COUNT = 30;

    private const TYPES = [
        ['name' => 'Футболка мужская', 'price' => 990],
        ['name' => 'Платье женское', 'price' => 2490],
        ['name' => 'Леггинсы женские', 'price' => 1290],
        ['name' => 'Худи унисекс', 'price' => 3190],
        ['name' => 'Носки спортивные', 'price' => 290],
        ['name' => 'Пижама детская', 'price' => 1590],
    ];

    public const COLORS = ['Nero', 'Bianco', 'Blu Scuro', 'Bordo', 'Grigio', 'Militari'];
    public const SIZES  = ['42 (XS)', '44 (S)', '46 (M)', '48 (L)', '50 (XL)'];
    public const BRANDS = ['MINIMI', 'OMSA', 'Collant'];

    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            $type  = self::TYPES[($i - 1) % count(self::TYPES)];
            $color = self::COLORS[$i % count(self::COLORS)];
            $size  = self::SIZES[$i % count(self::SIZES)];
            $brand = self::BRANDS[$i % count(self::BRANDS)];

            $price    = (float) ($type['price'] + ($i % 5) * 100);
            $purchase = round($price * (0.55 + ($i % 4) * 0.05), 2);
            $name     = sprintf('%s, %s, %s, %s', $type['name'], $color, $brand, $size);

            $product = new Product(self::externalCode($i), $name, self::money($price));
            $product->update(
                $name,
                sprintf('%s от бренда %s. Цвет %s, размер %s. Тестовый товар из фикстур.', $type['name'], $brand, $color, $size),
                self::money($price),
                self::money(DiscountCalculator::calculate($price, $purchase)),
            );

            $manager->persist($product);
            $this->addReference(self::reference($i), $product);
        }

        $manager->flush();
    }

    public static function reference(int $index): string
    {
        return 'product-' . $index;
    }

    public static function externalCode(int $index): string
    {
        return sprintf('FIX-%04d', $index);
    }

    private static function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
