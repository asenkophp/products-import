<?php

declare(strict_types=1);

namespace App\Presenter;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use App\Service\Product\ProductPage;

final class ProductPresenter
{
    /** @return array{items: list<array<string, mixed>>, meta: array{page: int, limit: int, total: int, pages: int}} */
    public function page(ProductPage $page): array
    {
        $items = [];
        foreach ($page->items as $product) {
            $items[] = $this->listItem($product, $page->mainImages[(int) $product->getId()] ?? null);
        }

        return [
            'items' => $items,
            'meta'  => [
                'page'  => $page->page,
                'limit' => $page->limit,
                'total' => $page->total,
                'pages' => $page->pages(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function listItem(Product $product, ?string $image): array
    {
        return [
            'id'           => $product->getId(),
            'externalCode' => $product->getExternalCode(),
            'name'         => $product->getName(),
            'price'        => (float) $product->getPrice(),
            'discount'     => $product->getDiscount() === null ? null : (float) $product->getDiscount(),
            'image'        => $image,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Product $product): array
    {
        return [
            'id'           => $product->getId(),
            'externalCode' => $product->getExternalCode(),
            'name'         => $product->getName(),
            'description'  => $product->getDescription(),
            'price'        => (float) $product->getPrice(),
            'discount'     => $product->getDiscount() === null ? null : (float) $product->getDiscount(),
            'attributes'   => array_values(array_map(
                static fn(ProductAttribute $a): array => ['id' => $a->getId(), 'key' => $a->getKey(), 'value' => $a->getValue()],
                $product->getAttributes()->toArray(),
            )),
            'images' => array_values(array_map(
                static fn(ProductImage $i): array => [
                    'id'       => $i->getId(),
                    'url'      => $i->getUrl(),
                    'path'     => $i->getPath(),
                    'position' => $i->getPosition(),
                ],
                $product->getImages()->toArray(),
            )),
            'createdAt' => $product->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $product->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
