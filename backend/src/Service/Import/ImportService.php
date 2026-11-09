<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Doctrine\ResettableEntityManager;
use App\Entity\ImportJob;
use App\Entity\Product;
use App\Enum\ImportStatus;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use RuntimeException;
use Throwable;

/**
 * Импорт товаров из файла (выполняется в воркере).
 *
 * Каждая строка обрабатывается независимо:
 * - невалидная строка попадает в отчёт об ошибках, импорт продолжается;
 * - картинки скачиваются ДО транзакции (сеть не держит транзакцию открытой);
 * - товар, атрибуты и записи о картинках пишутся в одной транзакции;
 * - upsert по external_code: повторный импорт обновляет, а не дублирует.
 */
final class ImportService
{
    /** Раз в N строк очищаем EntityManager, чтобы не копить объекты в памяти. */
    private const CLEAR_EVERY = 50;

    public function __construct(
        private readonly ResettableEntityManager $em,
        private readonly ProductRepository $products,
        private readonly ImportJobRepository $jobs,
        private readonly SpreadsheetReader $reader,
        private readonly ImageDownloaderInterface $images,
    ) {}

    public function run(int $jobId): void
    {
        $job = $this->jobs->find($jobId);
        // задача удалена или уже обработана (защита от повторной доставки сообщения)
        if ($job === null || $job->getStatus() !== ImportStatus::Pending) {
            return;
        }

        try {
            [$headers, $rows] = $this->reader->read($job->getFilePath());
            $parser           = ProductRowParser::fromHeaders($headers);
        } catch (InvalidImportFileException $e) {
            $job->fail($e->getMessage());
            $this->em->flush();

            return;
        }

        // номера строк как в файле: +1 за нумерацию с единицы, +1 за строку заголовков
        $dataRows = [];
        foreach ($rows as $index => $row) {
            if (!self::isEmptyRow($row)) {
                $dataRows[$index + 2] = $row;
            }
        }

        $job->start(count($dataRows));
        $this->em->flush();
        $this->images->reset();

        try {
            $processed = 0;
            foreach ($dataRows as $rowNumber => $row) {
                $this->importRow($jobId, $rowNumber, $row, $parser);

                if (++$processed % self::CLEAR_EVERY === 0) {
                    $this->em->clear();
                }
            }

            $this->job($jobId)->complete();
            $this->em->flush();
        } catch (Throwable $e) {
            $this->em->resetIfClosed();
            $this->em->clear();
            $this->job($jobId)->fail('Импорт прерван: ' . $e->getMessage());
            $this->em->flush();
        }
    }

    /** @param array<int, mixed> $row */
    private function importRow(int $jobId, int $rowNumber, array $row, ProductRowParser $parser): void
    {
        try {
            $data = $parser->parse($row);
        } catch (RowValidationException $e) {
            $this->job($jobId)->markFailed($rowNumber, $e->getExternalCode(), $e->getMessage());
            $this->em->flush();

            return;
        }

        $warnings = $data->warnings;
        $paths    = [];
        foreach ($data->imageUrls as $url) {
            try {
                $paths[$url] = $this->images->download($url);
            } catch (ImageDownloadException $e) {
                $warnings[] = $e->getMessage();
            }
        }

        try {
            $created = (bool) $this->em->wrapInTransaction(fn(): bool => $this->saveProduct($data, $paths));
        } catch (Throwable $e) {
            // Doctrine закрывает EM после ошибки — пересоздаём и продолжаем со следующей строки
            $this->em->resetIfClosed();
            $this->em->clear();
            $this->job($jobId)->markFailed($rowNumber, $data->externalCode, 'Ошибка сохранения: ' . $e->getMessage());
            $this->em->flush();

            return;
        }

        $job = $this->job($jobId);
        $created ? $job->markCreated() : $job->markUpdated();
        foreach ($warnings as $warning) {
            $job->addWarning($rowNumber, $data->externalCode, $warning);
        }
        $this->em->flush();
    }

    /**
     * @param array<string, string> $paths url → публичный путь скачанного файла
     *
     * @return bool true — товар создан, false — обновлён
     */
    private function saveProduct(ProductRowData $data, array $paths): bool
    {
        $product = $this->products->findByExternalCode($data->externalCode);
        $created = $product === null;

        if ($product === null) {
            $product = new Product($data->externalCode, $data->name, $data->price);
            $this->products->save($product);
        }

        $product->update($data->name, $data->description, $data->price, $data->discount);
        $product->syncAttributes($data->attributes);
        $product->syncImages($data->imageUrls);

        foreach ($product->getImages() as $image) {
            $path = $paths[$image->getUrl()] ?? null;
            if ($path !== null && $image->getPath() !== $path) {
                $image->setPath($path);
            }
        }

        return $created;
    }

    private function job(int $jobId): ImportJob
    {
        return $this->jobs->find($jobId) ?? throw new RuntimeException(sprintf('Задача импорта #%d не найдена', $jobId));
    }

    /** @param array<int, mixed> $row */
    private static function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (ProductRowParser::stringify($value) !== null) {
                return false;
            }
        }

        return true;
    }
}
