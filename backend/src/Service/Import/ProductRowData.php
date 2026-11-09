<?php

declare(strict_types=1);

namespace App\Service\Import;

/** Провалидированные и нормализованные данные одной строки файла. */
final readonly class ProductRowData
{
    /**
     * @param array<string, string> $attributes ключ → значение из колонок «Доп. поле: …»
     * @param list<string>          $imageUrls
     * @param list<string>          $warnings   некритичные замечания (строка всё равно импортируется)
     */
    public function __construct(
        public string $externalCode,
        public string $name,
        public ?string $description,
        public string $price,
        public ?string $discount,
        public array $attributes,
        public array $imageUrls,
        public array $warnings,
    ) {}
}
