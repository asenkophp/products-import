<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Import\ImageDownloaderInterface;
use App\Service\Import\ImageDownloadException;

/** Загрузчик без сети: ссылки со словом "broken" «не скачиваются». */
final class FakeImageDownloader implements ImageDownloaderInterface
{
    /** @var list<string> */
    public array $downloaded = [];

    public function download(string $url): string
    {
        if (str_contains($url, 'broken')) {
            throw new ImageDownloadException('Не удалось скачать ' . $url);
        }
        $this->downloaded[] = $url;

        return '/uploads/products/' . sha1($url) . '.jpg';
    }

    public function reset(): void {}
}
