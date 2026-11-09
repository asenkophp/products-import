<?php

declare(strict_types=1);

namespace App\Service\Import;

interface ImageDownloaderInterface
{
    /**
     * @return string публичный путь к сохранённому файлу
     *
     * @throws ImageDownloadException
     */
    public function download(string $url): string;

    /** Сбросить состояние между импортами (например, кеш недоступных хостов). */
    public function reset(): void;
}
