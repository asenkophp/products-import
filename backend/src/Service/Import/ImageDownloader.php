<?php

declare(strict_types=1);

namespace App\Service\Import;

use finfo;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Скачивает картинки товаров в public/uploads/products.
 *
 * - Имя файла = sha1(url): одна и та же ссылка (например, общее фото упаковки)
 *   скачивается один раз, повторный импорт не качает файлы заново.
 * - Тип определяется по содержимому, а не по заголовку ответа.
 * - Запрещены ссылки на внутренние адреса (защита от SSRF).
 * - Если хост недоступен, остальные ссылки на него в рамках импорта пропускаются сразу,
 *   чтобы не ждать таймаут на каждой картинке.
 */
final class ImageDownloader implements ImageDownloaderInterface
{
    private const MAX_BYTES       = 10 * 1024 * 1024;
    private const TIMEOUT         = 15;
    private const CONNECT_TIMEOUT = 5;

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /** @var array<string, true> */
    private array $failedHosts = [];

    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $storageDir,
        private readonly string $publicPrefix,
    ) {}

    /** Сбросить кеш недоступных хостов (вызывается в начале каждого импорта). */
    public function reset(): void
    {
        $this->failedHosts = [];
    }

    /**
     * @return string публичный путь к файлу, например /uploads/products/ab12….jpg
     *
     * @throws ImageDownloadException
     */
    public function download(string $url): string
    {
        $hash = sha1($url);
        foreach (self::EXTENSIONS as $extension) {
            if (is_file(sprintf('%s/%s.%s', $this->storageDir, $hash, $extension))) {
                return sprintf('%s/%s.%s', $this->publicPrefix, $hash, $extension);
            }
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        if (isset($this->failedHosts[$host])) {
            throw new ImageDownloadException(sprintf('Хост %s недоступен, картинка пропущена: %s', $host, $url));
        }
        $this->assertPublicHost($host, $url);

        try {
            $response = $this->http->request('GET', $url, [
                'timeout'         => self::TIMEOUT,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'allow_redirects' => ['max' => 3, 'protocols' => ['http', 'https']],
                'headers'         => ['User-Agent' => 'products-import/1.0'],
                // проверяем размер по заголовкам, не скачивая тело (mixed: тестовый MockHandler передаёт и исключения)
                'on_headers' => static function (mixed $response): void {
                    if ($response instanceof ResponseInterface && (int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                        throw new RuntimeException('файл больше 10 МБ');
                    }
                },
            ]);
        } catch (ConnectException $e) {
            $this->failedHosts[$host] = true;
            throw new ImageDownloadException(sprintf('Не удалось подключиться к %s: %s', $host, $url), 0, $e);
        } catch (GuzzleException $e) {
            throw new ImageDownloadException(sprintf('Не удалось скачать %s: %s', $url, $e->getMessage()), 0, $e);
        }

        $body = (string) $response->getBody();
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            throw new ImageDownloadException(sprintf('Пустой или слишком большой файл: %s', $url));
        }

        $mime      = (new finfo(FILEINFO_MIME_TYPE))->buffer($body);
        $extension = self::EXTENSIONS[$mime] ?? null;
        if ($extension === null) {
            throw new ImageDownloadException(sprintf('По ссылке не изображение (%s): %s', $mime, $url));
        }

        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0o775, true) && !is_dir($this->storageDir)) {
            throw new ImageDownloadException('Не удалось создать папку для картинок');
        }

        $target = sprintf('%s/%s.%s', $this->storageDir, $hash, $extension);
        $tmp    = tempnam($this->storageDir, 'img_');
        if ($tmp === false || file_put_contents($tmp, $body) === false || !rename($tmp, $target)) {
            throw new ImageDownloadException(sprintf('Не удалось сохранить картинку: %s', $url));
        }
        chmod($target, 0o644);

        return sprintf('%s/%s.%s', $this->publicPrefix, $hash, $extension);
    }

    private function assertPublicHost(string $host, string $url): void
    {
        if ($host === '') {
            throw new ImageDownloadException(sprintf('Некорректная ссылка: %s', $url));
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) !== false ? $host : gethostbyname($host);
        if ($ip === $host && filter_var($host, FILTER_VALIDATE_IP) === false) {
            $this->failedHosts[$host] = true;
            throw new ImageDownloadException(sprintf('Не удалось определить адрес хоста %s', $host));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new ImageDownloadException(sprintf('Ссылки на внутренние адреса запрещены: %s', $url));
        }
    }
}
