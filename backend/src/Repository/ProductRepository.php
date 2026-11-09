<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

final class ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function find(int $id): ?Product
    {
        return $this->em->find(Product::class, $id);
    }

    /** Товар со всеми атрибутами и картинками одним запросом (для карточки). */
    public function findWithRelations(int $id): ?Product
    {
        /** @var Product|null $product */
        $product = $this->em->createQueryBuilder()
            ->select('p', 'a', 'i')
            ->from(Product::class, 'p')
            ->leftJoin('p.attributes', 'a')
            ->leftJoin('p.images', 'i')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->orderBy('a.id', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();

        return $product;
    }

    public function findByExternalCode(string $externalCode): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneBy(['externalCode' => $externalCode]);
    }

    /**
     * Серверная пагинация с поиском по названию и фильтром по диапазону цены.
     *
     * @return array{items: list<Product>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $name = null, ?string $priceMin = null, ?string $priceMax = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->orderBy('p.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($name !== null && $name !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :name')
                ->setParameter('name', '%' . addcslashes(mb_strtolower($name), '%_\\') . '%');
        }
        if ($priceMin !== null) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $priceMin);
        }
        if ($priceMax !== null) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $priceMax);
        }

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);

        /** @var list<Product> $items */
        $items = iterator_to_array($paginator->getIterator(), false);

        return ['items' => $items, 'total' => count($paginator)];
    }

    /**
     * Пути к первой скачанной картинке для каждого товара.
     *
     * @param list<int> $productIds
     *
     * @return array<int, string> id товара → путь
     */
    public function findMainImagePaths(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        /** @var list<array{productId: int|string, path: string}> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(i.product) AS productId', 'i.path')
            ->from(ProductImage::class, 'i')
            ->where('i.product IN (:ids)')
            ->andWhere('i.path IS NOT NULL')
            ->setParameter('ids', $productIds)
            ->orderBy('i.position', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $paths = [];
        foreach ($rows as $row) {
            $paths[(int) $row['productId']] ??= $row['path'];
        }

        return $paths;
    }

    public function save(Product $product): void
    {
        $this->em->persist($product);
    }
}
