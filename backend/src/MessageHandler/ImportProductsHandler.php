<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ImportProductsMessage;
use App\Service\Import\ImportService;

final class ImportProductsHandler
{
    public function __construct(private readonly ImportService $importService) {}

    public function __invoke(ImportProductsMessage $message): void
    {
        $this->importService->run($message->jobId);
    }
}
