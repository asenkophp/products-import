<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/** Некорректные параметры запроса: поле → сообщение. Контроллер превращает в 422. */
final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Некорректные параметры запроса');
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
