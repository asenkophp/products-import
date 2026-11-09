<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Http\ValidationException;

/** Параметры списка товаров: пагинация, поиск по названию, диапазон цены. */
final readonly class ProductListQuery
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT     = 100;

    public function __construct(
        public int $page = 1,
        public int $limit = self::DEFAULT_LIMIT,
        public ?string $name = null,
        public ?string $priceMin = null,
        public ?string $priceMax = null,
    ) {}

    /**
     * @param array<string, mixed> $params query-параметры: page, limit, name, price_min, price_max
     *
     * @throws ValidationException
     */
    public static function fromArray(array $params): self
    {
        $errors = [];

        $page = self::int($params['page'] ?? null, 1);
        if ($page === null || $page < 1) {
            $errors['page'] = 'Номер страницы должен быть целым числом от 1';
        }

        $limit = self::int($params['limit'] ?? null, self::DEFAULT_LIMIT);
        if ($limit === null || $limit < 1 || $limit > self::MAX_LIMIT) {
            $errors['limit'] = sprintf('Размер страницы должен быть от 1 до %d', self::MAX_LIMIT);
        }

        $name = isset($params['name']) && is_string($params['name']) ? trim($params['name']) : null;
        if ($name !== null && mb_strlen($name) > 255) {
            $errors['name'] = 'Строка поиска слишком длинная';
        }

        $priceMin = self::price($params['price_min'] ?? null);
        if ($priceMin === false) {
            $errors['price_min'] = 'Минимальная цена должна быть неотрицательным числом';
        }

        $priceMax = self::price($params['price_max'] ?? null);
        if ($priceMax === false) {
            $errors['price_max'] = 'Максимальная цена должна быть неотрицательным числом';
        }

        if (is_string($priceMin) && is_string($priceMax) && (float) $priceMin > (float) $priceMax) {
            $errors['price_min'] = 'Минимальная цена больше максимальной';
        }

        if ($errors !== [] || $limit === null || $priceMin === false || $priceMax === false) {
            throw new ValidationException($errors);
        }

        return new self($page, $limit, $name === '' ? null : $name, $priceMin, $priceMax);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    private static function int(mixed $value, int $default): ?int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return $int === false ? null : $int;
    }

    /** @return string|null|false строка «123.45», null — не задано, false — некорректно */
    private static function price(mixed $value): string|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return false;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            return false;
        }

        return number_format((float) $normalized, 2, '.', '');
    }
}
