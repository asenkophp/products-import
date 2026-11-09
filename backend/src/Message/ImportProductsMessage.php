<?php

declare(strict_types=1);

namespace App\Message;

/** Сообщение в очередь: обработать задачу импорта с указанным ID. */
final readonly class ImportProductsMessage
{
    public function __construct(public int $jobId) {}
}
