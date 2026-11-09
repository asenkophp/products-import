<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Создаёт временный xlsx-файл с заданными строками. */
final class SpreadsheetFactory
{
    /**
     * @param list<string>             $headers
     * @param list<list<string|float|int|null>> $rows
     */
    public static function xlsx(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([$headers, ...$rows], null, 'A1', true);

        $path = sys_get_temp_dir() . '/import-test-' . bin2hex(random_bytes(6)) . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    public static function fixturePath(): string
    {
        return __DIR__ . '/../fixtures/import_example.ods';
    }
}
