<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;

/** Страница списка товаров. */
final readonly class ProductPage
{
    /**
     * @param list<Product>      $items
     * @param array<int, string> $mainImages id товара → путь к главной картинке
     */
    public function __construct(
        public array $items,
        public array $mainImages,
        public int $total,
        public int $page,
        public int $limit,
    ) {}

    public function pages(): int
    {
        return $this->total === 0 ? 0 : (int) ceil($this->total / $this->limit);
    }
}
