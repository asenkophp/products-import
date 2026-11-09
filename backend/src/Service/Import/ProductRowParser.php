<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Service\Pricing\DiscountCalculator;

/**
 * Разбирает строки файла импорта: сопоставляет колонки по заголовкам,
 * валидирует и нормализует значения.
 */
final class ProductRowParser
{
    public const COLUMN_EXTERNAL_CODE  = 'Внешний код';
    public const COLUMN_NAME           = 'Наименование';
    public const COLUMN_DESCRIPTION    = 'Описание';
    public const COLUMN_PRICE          = 'Цена: Цена продажи';
    public const COLUMN_PURCHASE_PRICE = 'Закупочная цена';

    public const ATTRIBUTE_PREFIX = 'Доп. поле:';

    /** Колонки со ссылками на картинки: идут в product_images, а не в атрибуты. */
    public const IMAGE_COLUMNS = ['Доп. поле: Ссылки на фото', 'Доп. поле: Ссылка на упаковку'];

    private const REQUIRED_COLUMNS = [self::COLUMN_EXTERNAL_CODE, self::COLUMN_NAME, self::COLUMN_PRICE];

    private const MAX_PRICE        = 9_999_999_999.99;
    private const MAX_DISCOUNT_ABS = 99_999.99;
    private const MAX_URL_LENGTH   = 2048;
    private const MAX_CODE_LENGTH  = 100;
    private const MAX_NAME_LENGTH  = 255;

    /**
     * @param array<string, int> $columns          заголовок → индекс колонки
     * @param array<string, int> $attributeColumns ключ атрибута → индекс колонки
     * @param array<string, int> $imageColumns     заголовок колонки с картинками → индекс
     */
    private function __construct(
        private readonly array $columns,
        private readonly array $attributeColumns,
        private readonly array $imageColumns,
    ) {}

    /**
     * @param list<string> $headers
     *
     * @throws InvalidImportFileException если нет обязательных колонок
     */
    public static function fromHeaders(array $headers): self
    {
        $columns          = [];
        $attributeColumns = [];
        $imageColumns     = [];

        foreach ($headers as $index => $header) {
            $header = trim((string) preg_replace('/\s+/u', ' ', $header));
            if ($header === '' || isset($columns[$header])) {
                continue;
            }
            $columns[$header] = $index;

            if (in_array($header, self::IMAGE_COLUMNS, true)) {
                $imageColumns[$header] = $index;
            } elseif (str_starts_with($header, self::ATTRIBUTE_PREFIX)) {
                $key = trim(mb_substr($header, mb_strlen(self::ATTRIBUTE_PREFIX)));
                if ($key !== '') {
                    $attributeColumns[$key] = $index;
                }
            }
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($columns)));
        if ($missing !== []) {
            throw new InvalidImportFileException('В файле нет обязательных колонок: ' . implode(', ', $missing));
        }

        return new self($columns, $attributeColumns, $imageColumns);
    }

    /**
     * @param array<int, mixed> $row
     *
     * @throws RowValidationException
     */
    public function parse(array $row): ProductRowData
    {
        $errors   = [];
        $warnings = [];

        $externalCode = $this->text($row, self::COLUMN_EXTERNAL_CODE);
        $name         = $this->text($row, self::COLUMN_NAME);
        $description  = $this->text($row, self::COLUMN_DESCRIPTION);

        if ($externalCode === null) {
            $errors[] = 'Не заполнен внешний код';
        } elseif (mb_strlen($externalCode) > self::MAX_CODE_LENGTH) {
            $errors[] = sprintf('Внешний код длиннее %d символов', self::MAX_CODE_LENGTH);
        }

        if ($name === null) {
            $errors[] = 'Не заполнено наименование';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors[] = sprintf('Наименование длиннее %d символов', self::MAX_NAME_LENGTH);
        }

        // --- цены и скидка ---
        $price    = null;
        $rawPrice = $this->cell($row, self::COLUMN_PRICE);
        if (self::stringify($rawPrice) === null) {
            $errors[] = 'Не заполнена цена продажи';
        } else {
            $price = self::toFloat($rawPrice);
            if ($price === null) {
                $errors[] = sprintf('Некорректная цена продажи: «%s»', self::stringify($rawPrice));
            } elseif ($price <= 0) {
                $errors[] = 'Цена продажи должна быть больше нуля';
                $price    = null;
            } elseif ($price > self::MAX_PRICE) {
                $errors[] = 'Цена продажи слишком большая';
                $price    = null;
            }
        }

        $purchase    = null;
        $rawPurchase = $this->cell($row, self::COLUMN_PURCHASE_PRICE);
        if (self::stringify($rawPurchase) !== null) {
            $purchase = self::toFloat($rawPurchase);
            if ($purchase === null) {
                $errors[] = sprintf('Некорректная закупочная цена: «%s»', self::stringify($rawPurchase));
            } elseif ($purchase < 0) {
                $errors[] = 'Закупочная цена не может быть отрицательной';
                $purchase = null;
            }
        }

        $discount = null;
        if ($price !== null && $purchase !== null) {
            $discount = DiscountCalculator::calculate($price, $purchase);
            if (abs($discount) > self::MAX_DISCOUNT_ABS) {
                $errors[] = 'Скидка вне допустимого диапазона: проверьте цены';
            } elseif ($discount < 0) {
                $warnings[] = 'Закупочная цена выше цены продажи, скидка отрицательная';
            }
        }

        // каждое пустое обязательное поле добавляет ошибку, поэтому при пустом $errors
        // внешний код, имя и цена гарантированно заполнены (PHPStan выводит это сам)
        if ($errors !== []) {
            throw new RowValidationException($errors, $externalCode);
        }

        // --- доп. атрибуты ---
        $attributes = [];
        foreach ($this->attributeColumns as $key => $index) {
            $value = self::stringify($row[$index] ?? null);
            if ($value !== null) {
                $attributes[$key] = $value;
            }
        }

        // --- ссылки на картинки ---
        $imageUrls = [];
        // порядок как в IMAGE_COLUMNS: сначала фото товара, потом упаковка
        foreach (self::IMAGE_COLUMNS as $header) {
            if (!isset($this->imageColumns[$header])) {
                continue;
            }
            $raw = self::stringify($row[$this->imageColumns[$header]] ?? null);
            if ($raw === null) {
                continue;
            }
            foreach (preg_split('/[\s,;]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $url) {
                if (!self::isValidImageUrl($url)) {
                    $warnings[] = sprintf('Некорректная ссылка на изображение пропущена: %s', $url);
                    continue;
                }
                if (!in_array($url, $imageUrls, true)) {
                    $imageUrls[] = $url;
                }
            }
        }

        return new ProductRowData(
            externalCode: $externalCode,
            name: $name,
            description: $description,
            price: self::money($price),
            discount: $discount === null ? null : self::money($discount),
            attributes: $attributes,
            imageUrls: $imageUrls,
            warnings: $warnings,
        );
    }

    /** @param array<int, mixed> $row */
    private function cell(array $row, string $column): mixed
    {
        if (!isset($this->columns[$column])) {
            return null;
        }

        return $row[$this->columns[$column]] ?? null;
    }

    /** @param array<int, mixed> $row */
    private function text(array $row, string $column): ?string
    {
        return self::stringify($this->cell($row, $column));
    }

    /** Значение ячейки → обрезанная строка или null для пустых. 20.0 → "20". */
    public static function stringify(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (floor($value) === $value && abs($value) < 1e15) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (!is_string($value)) {
            return null;
        }

        $value = (string) preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $value);

        return $value === '' ? null : $value;
    }

    /** «1 320,00» / "1320.5" / 1320 → float; некорректное значение → null. */
    public static function toFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (!is_string($value)) {
            return null;
        }

        $normalized = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim($value));
        $normalized = str_replace(',', '.', $normalized);

        if (preg_match('/^-?\d+(\.\d+)?$/', $normalized) !== 1) {
            return null;
        }

        return (float) $normalized;
    }

    private static function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    private static function isValidImageUrl(string $url): bool
    {
        if (strlen($url) > self::MAX_URL_LENGTH || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }
}
