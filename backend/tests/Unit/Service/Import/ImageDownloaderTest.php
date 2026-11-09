<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use App\Service\Import\ImageDownloader;
use App\Service\Import\ImageDownloadException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImageDownloaderTest extends TestCase
{
    // IP-адреса вместо доменов: тест не зависит от DNS
    private const URL = 'http://93.184.216.34/pics/a.png';

    private string $dir;
    private MockHandler $http;
    private ImageDownloader $downloader;

    protected function setUp(): void
    {
        $this->dir        = sys_get_temp_dir() . '/images-test-' . bin2hex(random_bytes(4));
        $this->http       = new MockHandler();
        $this->downloader = new ImageDownloader(
            new Client(['handler' => HandlerStack::create($this->http)]),
            $this->dir,
            '/uploads/products',
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testDownloadsImageOnceAndReusesFile(): void
    {
        $this->http->append(new Response(200, ['Content-Type' => 'image/png'], self::png()));

        $path = $this->downloader->download(self::URL);

        self::assertSame('/uploads/products/' . sha1(self::URL) . '.png', $path);
        self::assertFileExists($this->dir . '/' . sha1(self::URL) . '.png');

        // повторно сеть не трогается: в MockHandler больше нет ответов, иначе было бы исключение
        self::assertSame($path, $this->downloader->download(self::URL));
    }

    public function testRejectsNonImageContent(): void
    {
        $this->http->append(new Response(200, ['Content-Type' => 'image/png'], '<html>not an image</html>'));

        $this->expectException(ImageDownloadException::class);
        $this->expectExceptionMessage('не изображение');
        $this->downloader->download(self::URL);
    }

    public function testRejectsHttpErrors(): void
    {
        $this->http->append(new Response(403));

        $this->expectException(ImageDownloadException::class);
        $this->downloader->download(self::URL);
    }

    /** @return iterable<string, array{string}> */
    public static function internalUrls(): iterable
    {
        yield 'localhost' => ['http://127.0.0.1/a.png'];
        yield 'частная сеть 10.x' => ['http://10.1.2.3/a.png'];
        yield 'частная сеть 192.168.x' => ['http://192.168.0.10/a.png'];
    }

    #[DataProvider('internalUrls')]
    public function testBlocksInternalAddresses(string $url): void
    {
        $this->expectException(ImageDownloadException::class);
        $this->expectExceptionMessage('внутренние адреса');
        $this->downloader->download($url);
    }

    public function testSkipsUnreachableHostAfterFirstFailure(): void
    {
        $this->http->append(new ConnectException('timeout', new Request('GET', self::URL)));

        try {
            $this->downloader->download(self::URL);
            self::fail('Ожидалась ошибка подключения');
        } catch (ImageDownloadException) {
        }

        $this->expectException(ImageDownloadException::class);
        $this->expectExceptionMessage('недоступен');
        $this->downloader->download('http://93.184.216.34/pics/b.png');
    }

    private static function png(): string
    {
        $image = imagecreatetruecolor(2, 2);
        self::assertNotFalse($image);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
