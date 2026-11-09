<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Изображения товаров (таблица product_images): локально сгенерированные PNG. */
final class ProductImageFixtures extends AbstractFixture implements DependentFixtureInterface
{
    private const IMAGES_PER_PRODUCT = 3;

    public function __construct(private readonly PlaceholderImageGenerator $generator) {}

    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= ProductFixtures::COUNT; $i++) {
            $product = $this->getReference(ProductFixtures::reference($i), Product::class);
            $code    = ProductFixtures::externalCode($i);

            $paths = [];
            for ($n = 1; $n <= self::IMAGES_PER_PRODUCT; $n++) {
                $url         = sprintf('https://example.com/fixtures/%s-%d.png', $code, $n);
                $paths[$url] = $this->generator->generate(
                    sprintf('fixture-%s-%d', strtolower($code), $n),
                    sprintf('%s #%d', $code, $n),
                    $i * 37 + $n * 25,
                );
            }

            $product->syncImages(array_keys($paths));
            foreach ($product->getImages() as $image) {
                $image->setPath($paths[$image->getUrl()] ?? null);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ProductFixtures::class];
    }
}
