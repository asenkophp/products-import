<?php

declare(strict_types=1);

namespace App\Service\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/** Читает первый лист xlsx/ods-файла: заголовки и строки данных. */
final class SpreadsheetReader
{
    /**
     * @return array{0: list<string>, 1: list<array<int, mixed>>} [заголовки, строки]
     *
     * @throws InvalidImportFileException
     */
    public function read(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidImportFileException('Файл импорта не найден на сервере');
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $spreadsheet = $reader->load($path);
            /** @var array<int, array<int, mixed>> $rows */
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $e) {
            throw new InvalidImportFileException('Не удалось прочитать файл: ' . $e->getMessage(), 0, $e);
        }

        $rows = array_values($rows);
        if ($rows === []) {
            throw new InvalidImportFileException('Файл пустой');
        }

        $headers = array_map(
            static fn(mixed $value): string => is_scalar($value) ? (string) $value : '',
            array_values(array_shift($rows)),
        );

        return [$headers, $rows];
    }
}
