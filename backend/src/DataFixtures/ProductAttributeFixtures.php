<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Доп. атрибуты товаров (таблица product_attributes). */
final class ProductAttributeFixtures extends AbstractFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= ProductFixtures::COUNT; $i++) {
            $product = $this->getReference(ProductFixtures::reference($i), Product::class);

            $product->syncAttributes([
                'Размер'            => ProductFixtures::SIZES[$i % count(ProductFixtures::SIZES)],
                'Цвет'              => ProductFixtures::COLORS[$i % count(ProductFixtures::COLORS)],
                'Бренд'             => ProductFixtures::BRANDS[$i % count(ProductFixtures::BRANDS)],
                'Состав'            => $i % 2 === 0 ? '100% Хлопок' : '86% Полиамид, 14% Эластан',
                'Страна'            => $i % 3 === 0 ? 'Россия' : 'Китай',
                'Кол-во в упаковке' => (string) (1 + $i % 3),
            ]);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ProductFixtures::class];
    }
}
