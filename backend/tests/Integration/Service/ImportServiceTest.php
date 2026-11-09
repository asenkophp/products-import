<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\ImportJob;
use App\Enum\ImportStatus;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use App\Service\Import\ImportService;
use App\Service\Import\SpreadsheetReader;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\FakeImageDownloader;
use App\Tests\Support\SpreadsheetFactory;

/** Полный импорт файла в БД (без очереди и без сети). */
final class ImportServiceTest extends DatabaseTestCase
{
    private const HEADERS = ['Внешний код', 'Наименование', 'Цена: Цена продажи', 'Закупочная цена', 'Доп. поле: Цвет', 'Доп. поле: Ссылки на фото'];

    private FakeImageDownloader $images;
    private ImportService $service;
    private ImportJobRepository $jobs;
    private ProductRepository $products;

    protected function setUp(): void
    {
        parent::setUp();
        $this->images   = new FakeImageDownloader();
        $this->jobs     = self::service(ImportJobRepository::class);
        $this->products = self::service(ProductRepository::class);
        $this->service  = new ImportService($this->em(), $this->products, $this->jobs, new SpreadsheetReader(), $this->images);
    }

    private function runImport(string $file): ImportJob
    {
        $job = new ImportJob(basename($file), $file);
        $this->jobs->save($job);
        $id = (int) $job->getId();

        $this->service->run($id);
        $this->em()->clear();

        $reloaded = $this->jobs->find($id);
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    public function testImportsExampleFile(): void
    {
        $job = $this->runImport(SpreadsheetFactory::fixturePath());

        self::assertSame(ImportStatus::Completed, $job->getStatus(), json_encode($job->getErrors(), JSON_UNESCAPED_UNICODE) ?: '');
        self::assertSame(40, $job->getTotalRows());
        self::assertSame(40, $job->getCreatedCount());
        self::assertSame(0, $job->getFailedCount());
        self::assertSame(40, $this->countRows('products'));

        $product = $this->products->findByExternalCode('3UHfAid1jaMiwgBuNvnsf3');
        self::assertNotNull($product);
        self::assertSame('1320.00', $product->getPrice());
        self::assertSame('33.33', $product->getDiscount());
        self::assertCount(15, $product->getAttributes());
        self::assertCount(4, $product->getImages());
        foreach ($product->getImages() as $image) {
            self::assertNotNull($image->getPath());
        }
    }

    public function testReimportUpdatesWithoutDuplicates(): void
    {
        $this->runImport(SpreadsheetFactory::fixturePath());
        $attributes = $this->countRows('product_attributes');
        $images     = $this->countRows('product_images');

        $second = $this->runImport(SpreadsheetFactory::fixturePath());

        self::assertSame(0, $second->getCreatedCount());
        self::assertSame(40, $second->getUpdatedCount());
        self::assertSame(40, $this->countRows('products'));
        self::assertSame($attributes, $this->countRows('product_attributes'));
        self::assertSame($images, $this->countRows('product_images'));
    }

    public function testInvalidRowsAreReportedAndDoNotStopImport(): void
    {
        $file = SpreadsheetFactory::xlsx(self::HEADERS, [
            ['OK-1', 'Товар 1', '100,00', '60,00', 'Nero', 'http://example.com/1.jpg'],
            ['BAD-1', '', '100,00', '60,00', 'Nero', null],
            ['BAD-2', 'Товар 3', 'дорого', '60,00', 'Nero', null],
            [null, null, null, null, null, null],
            ['OK-2', 'Товар 5', '100', '150', 'Bianco', 'http://example.com/broken.jpg'],
        ]);

        $job = $this->runImport($file);

        self::assertSame(ImportStatus::Completed, $job->getStatus());
        self::assertSame(4, $job->getTotalRows(), 'Пустая строка не считается');
        self::assertSame(2, $job->getCreatedCount());
        self::assertSame(2, $job->getFailedCount());
        self::assertSame([3, 4], array_column($job->getErrors(), 'row'));
        self::assertSame(['BAD-1', 'BAD-2'], array_column($job->getErrors(), 'external_code'));

        // OK-2: отрицательная скидка и нескачанная картинка — предупреждения, товар сохранён
        $warnings = array_column($job->getWarnings(), 'message');
        self::assertCount(2, $warnings);
        $product = $this->products->findByExternalCode('OK-2');
        self::assertNotNull($product);
        self::assertSame('-50.00', $product->getDiscount());
        $image = $product->getImages()->first();
        self::assertNotFalse($image);
        self::assertNull($image->getPath(), 'Картинка не скачалась — путь пустой, ссылка сохранена');

        unlink($file);
    }

    public function testFileWithoutRequiredColumnsFails(): void
    {
        $file = SpreadsheetFactory::xlsx(['Наименование', 'Цена'], [['Товар', '100']]);

        $job = $this->runImport($file);

        self::assertSame(ImportStatus::Failed, $job->getStatus());
        self::assertStringContainsString('Внешний код', $job->getErrors()[0]['message']);
        self::assertSame(0, $this->countRows('products'));

        unlink($file);
    }

    public function testAlreadyProcessedJobIsSkipped(): void
    {
        $job = new ImportJob('x.xlsx', SpreadsheetFactory::fixturePath());
        $job->start(0);
        $job->complete();
        $this->jobs->save($job);

        $this->service->run((int) $job->getId());

        self::assertSame(0, $this->countRows('products'));
    }
}
