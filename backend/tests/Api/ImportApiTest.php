<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Message\ImportProductsMessage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SpreadsheetFactory;
use Slim\Psr7\UploadedFile;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class ImportApiTest extends ApiTestCase
{
    private function upload(string $source, string $clientName): UploadedFile
    {
        $tmp = sys_get_temp_dir() . '/upload-' . bin2hex(random_bytes(6));
        copy($source, $tmp);

        return new UploadedFile($tmp, $clientName, null, (int) filesize($tmp));
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::container()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    public function testUploadQueuesImport(): void
    {
        $this->transport()->reset();

        $response = $this->request('POST', '/api/import', [], [
            'file' => $this->upload(SpreadsheetFactory::fixturePath(), 'import_example.ods'),
        ]);

        self::assertSame(202, $response->getStatusCode());
        $job = $this->json($response);
        self::assertSame('pending', $job['status']);
        self::assertSame('/api/import/' . $job['id'], $response->getHeaderLine('Location'));

        // задача ушла в очередь, а не выполнилась в запросе
        $sent = $this->transport()->getSent();
        self::assertCount(1, $sent);
        $message = $sent[0]->getMessage();
        self::assertInstanceOf(ImportProductsMessage::class, $message);
        self::assertSame($job['id'], $message->jobId);

        $status = $this->request('GET', '/api/import/' . $job['id']);
        self::assertSame(200, $status->getStatusCode());
        self::assertSame('import_example.ods', $this->json($status)['filename']);
    }

    public function testRejectsWrongExtension(): void
    {
        $response = $this->request('POST', '/api/import', [], [
            'file' => $this->upload(__FILE__, 'products.txt'),
        ]);

        self::assertSame(422, $response->getStatusCode());
        $error = $this->json($response)['error'];
        self::assertIsArray($error);
        self::assertStringContainsString('.xlsx', (string) $error['message']);
    }

    public function testRejectsFakeSpreadsheet(): void
    {
        // расширение правильное, а содержимое — нет: MIME проверяется по содержимому
        $response = $this->request('POST', '/api/import', [], [
            'file' => $this->upload(__FILE__, 'products.xlsx'),
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testRequiresFile(): void
    {
        self::assertSame(422, $this->request('POST', '/api/import')->getStatusCode());
    }

    public function testUnknownJobReturns404(): void
    {
        self::assertSame(404, $this->request('GET', '/api/import/9999')->getStatusCode());
    }
}
