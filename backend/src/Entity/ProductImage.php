<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_images')]
#[ORM\Index(name: 'idx_product_images_product', columns: ['product_id'])]
class ProductImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    /** Исходная ссылка из файла импорта. */
    #[ORM\Column(type: Types::STRING, length: 2048)]
    private string $url;

    /** Локальный путь к скачанному файлу (null, пока не скачан или если скачать не удалось). */
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $path = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $position;

    public function __construct(Product $product, string $url, int $position = 0)
    {
        $this->product  = $product;
        $this->url      = $url;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function isDownloaded(): bool
    {
        return $this->path !== null;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
