<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testSyncAttributesUpdatesInPlace(): void
    {
        $product = new Product('A-1', 'Товар', '100.00');
        $product->syncAttributes(['Цвет' => 'Nero', 'Размер' => 'M']);
        $colorBefore = $this->attribute($product, 'Цвет');

        $product->syncAttributes(['Цвет' => 'Bianco', 'Бренд' => 'OMSA']);

        self::assertSame(['Цвет' => 'Bianco', 'Бренд' => 'OMSA'], $this->attributesMap($product));
        // тот же объект обновлён, а не пересоздан — иначе нарушится уникальность (product_id, key)
        self::assertSame($colorBefore, $this->attribute($product, 'Цвет'));
    }

    public function testSyncImagesKeepsDownloadedAndReorders(): void
    {
        $product = new Product('A-1', 'Товар', '100.00');
        $product->syncImages(['http://x/1.jpg', 'http://x/2.jpg']);
        $first = $this->image($product, 'http://x/1.jpg');
        $first->setPath('/uploads/products/1.jpg');

        $product->syncImages(['http://x/3.jpg', 'http://x/1.jpg']);

        $urls = array_map(static fn(ProductImage $i): string => $i->getUrl(), $product->getImages()->getValues());
        self::assertSame(['http://x/1.jpg', 'http://x/3.jpg'], $urls);
        self::assertSame($first, $this->image($product, 'http://x/1.jpg'));
        self::assertSame('/uploads/products/1.jpg', $first->getPath());
        self::assertSame(1, $first->getPosition());
    }

    /** @return array<string, string> */
    private function attributesMap(Product $product): array
    {
        $map = [];
        foreach ($product->getAttributes() as $attribute) {
            $map[$attribute->getKey()] = $attribute->getValue();
        }

        return $map;
    }

    private function attribute(Product $product, string $key): ProductAttribute
    {
        foreach ($product->getAttributes() as $attribute) {
            if ($attribute->getKey() === $key) {
                return $attribute;
            }
        }
        self::fail('Нет атрибута ' . $key);
    }

    private function image(Product $product, string $url): ProductImage
    {
        foreach ($product->getImages() as $image) {
            if ($image->getUrl() === $url) {
                return $image;
            }
        }
        self::fail('Нет картинки ' . $url);
    }
}
