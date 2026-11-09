<?php

declare(strict_types=1);

namespace App\Service\Import;

use RuntimeException;

/** Строка файла невалидна: попадает в отчёт об ошибках, импорт продолжается. */
final class RowValidationException extends RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(private readonly array $errors, private readonly ?string $externalCode)
    {
        parent::__construct(implode('; ', $errors));
    }

    /** @return list<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getExternalCode(): ?string
    {
        return $this->externalCode;
    }
}
