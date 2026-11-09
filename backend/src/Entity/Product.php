<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\Index(name: 'idx_products_name', columns: ['name'])]
#[ORM\Index(name: 'idx_products_price', columns: ['price'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'external_code', type: Types::STRING, length: 100, unique: true)]
    private string $externalCode;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Цена продажи. Decimal в Doctrine хранится строкой, чтобы не терять точность. */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $price;

    /** Скидка в %: (цена продажи − закупочная цена) / цена продажи × 100. */
    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2, nullable: true)]
    private ?string $discount = null;

    /** @var Collection<int, ProductAttribute> */
    #[ORM\OneToMany(targetEntity: ProductAttribute::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $attributes;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $images;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $externalCode, string $name, string $price)
    {
        $this->externalCode = $externalCode;
        $this->name         = $name;
        $this->price        = $price;
        $this->attributes   = new ArrayCollection();
        $this->images       = new ArrayCollection();
        $this->createdAt    = new DateTimeImmutable();
        $this->updatedAt    = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function update(string $name, ?string $description, string $price, ?string $discount): void
    {
        $this->name        = $name;
        $this->description = $description;
        $this->price       = $price;
        $this->discount    = $discount;
    }

    /**
     * Синхронизирует атрибуты с переданным набором «ключ → значение».
     * Существующие обновляются на месте, лишние удаляются, новые добавляются.
     * Так при повторном импорте не нарушается уникальность (product_id, key):
     * Doctrine выполняет INSERT раньше DELETE, поэтому «удалить всё и вставить заново» не работает.
     *
     * @param array<string, string> $values
     */
    public function syncAttributes(array $values): void
    {
        foreach ($this->attributes as $attribute) {
            $key = $attribute->getKey();
            if (array_key_exists($key, $values)) {
                $attribute->setValue($values[$key]);
                unset($values[$key]);
            } else {
                $this->attributes->removeElement($attribute);
            }
        }

        foreach ($values as $key => $value) {
            $this->attributes->add(new ProductAttribute($this, $key, $value));
        }
    }

    /**
     * Синхронизирует изображения со списком ссылок, сохраняя порядок.
     * Уже скачанные картинки с той же ссылкой не трогаются (повторно не скачиваются).
     *
     * @param list<string> $urls
     */
    public function syncImages(array $urls): void
    {
        $positions = array_flip($urls);

        foreach ($this->images as $image) {
            $url = $image->getUrl();
            if (array_key_exists($url, $positions)) {
                $image->setPosition($positions[$url]);
                unset($positions[$url]);
            } else {
                $this->images->removeElement($image);
            }
        }

        foreach ($positions as $url => $position) {
            $this->images->add(new ProductImage($this, $url, $position));
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalCode(): string
    {
        return $this->externalCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function getDiscount(): ?string
    {
        return $this->discount;
    }

    /** @return Collection<int, ProductAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
