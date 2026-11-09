<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;
use App\Repository\ProductRepository;

/** Чтение каталога товаров для API. */
final class ProductCatalog
{
    public function __construct(private readonly ProductRepository $products) {}

    public function list(ProductListQuery $query): ProductPage
    {
        $result = $this->products->paginate($query->page, $query->limit, $query->name, $query->priceMin, $query->priceMax);

        $ids = array_values(array_filter(array_map(
            static fn(Product $product): ?int => $product->getId(),
            $result['items'],
        )));

        // главные картинки одним запросом, без N+1
        $mainImages = $this->products->findMainImagePaths($ids);

        return new ProductPage($result['items'], $mainImages, $result['total'], $query->page, $query->limit);
    }

    public function get(int $id): ?Product
    {
        return $this->products->findWithRelations($id);
    }
}
