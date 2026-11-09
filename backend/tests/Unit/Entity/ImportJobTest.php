<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ImportJob;
use App\Enum\ImportStatus;
use PHPUnit\Framework\TestCase;

final class ImportJobTest extends TestCase
{
    public function testTracksProgressAndReport(): void
    {
        $job = new ImportJob('file.xlsx', '/tmp/file.xlsx');
        self::assertSame(ImportStatus::Pending, $job->getStatus());
        self::assertSame(0, $job->getProgress());

        $job->start(4);
        $job->markCreated();
        $job->markUpdated();
        $job->markFailed(4, 'X-1', 'Не заполнено наименование');
        $job->addWarning(2, 'A-1', 'Картинка не скачалась');

        self::assertSame(ImportStatus::Processing, $job->getStatus());
        self::assertSame(75, $job->getProgress());
        self::assertSame(1, $job->getFailedCount());
        self::assertSame([['row' => 4, 'external_code' => 'X-1', 'message' => 'Не заполнено наименование']], $job->getErrors());
        self::assertCount(1, $job->getWarnings());

        $job->markCreated();
        $job->complete();

        self::assertSame(ImportStatus::Completed, $job->getStatus());
        self::assertSame(100, $job->getProgress());
        self::assertNotNull($job->getFinishedAt());
    }

    public function testFailedEmptyJobShowsFullProgress(): void
    {
        $job = new ImportJob('file.xlsx', '/tmp/file.xlsx');
        $job->fail('Файл пустой');

        self::assertSame(ImportStatus::Failed, $job->getStatus());
        self::assertSame(100, $job->getProgress());
    }
}
