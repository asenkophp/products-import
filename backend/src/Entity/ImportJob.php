<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ImportStatus;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Задача импорта: статус, прогресс и отчёт об ошибках.
 * Нужна для асинхронной обработки через очередь и polling прогресса на фронте.
 */
#[ORM\Entity]
#[ORM\Table(name: 'import_jobs')]
class ImportJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ImportStatus::class)]
    private ImportStatus $status = ImportStatus::Pending;

    #[ORM\Column(name: 'original_filename', type: Types::STRING, length: 255)]
    private string $originalFilename;

    #[ORM\Column(name: 'file_path', type: Types::STRING, length: 512)]
    private string $filePath;

    #[ORM\Column(name: 'total_rows', type: Types::INTEGER)]
    private int $totalRows = 0;

    #[ORM\Column(name: 'processed_rows', type: Types::INTEGER)]
    private int $processedRows = 0;

    #[ORM\Column(name: 'created_count', type: Types::INTEGER)]
    private int $createdCount = 0;

    #[ORM\Column(name: 'updated_count', type: Types::INTEGER)]
    private int $updatedCount = 0;

    #[ORM\Column(name: 'failed_count', type: Types::INTEGER)]
    private int $failedCount = 0;

    /** @var list<array{row: int, external_code: ?string, message: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $errors = [];

    /** @var list<array{row: int, external_code: ?string, message: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $warnings = [];

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    public function __construct(string $originalFilename, string $filePath)
    {
        $this->originalFilename = $originalFilename;
        $this->filePath         = $filePath;
        $this->createdAt        = new DateTimeImmutable();
    }

    public function start(int $totalRows): void
    {
        $this->status    = ImportStatus::Processing;
        $this->totalRows = $totalRows;
        $this->startedAt = new DateTimeImmutable();
    }

    public function markCreated(): void
    {
        $this->createdCount++;
        $this->processedRows++;
    }

    public function markUpdated(): void
    {
        $this->updatedCount++;
        $this->processedRows++;
    }

    public function markFailed(int $row, ?string $externalCode, string $message): void
    {
        $this->failedCount++;
        $this->processedRows++;
        $this->errors[] = ['row' => $row, 'external_code' => $externalCode, 'message' => $message];
    }

    /** Некритичная проблема: строка импортирована, но, например, не скачалась картинка. */
    public function addWarning(int $row, ?string $externalCode, string $message): void
    {
        $this->warnings[] = ['row' => $row, 'external_code' => $externalCode, 'message' => $message];
    }

    public function complete(): void
    {
        $this->status     = ImportStatus::Completed;
        $this->finishedAt = new DateTimeImmutable();
    }

    public function fail(string $message): void
    {
        $this->status     = ImportStatus::Failed;
        $this->errors[]   = ['row' => 0, 'external_code' => null, 'message' => $message];
        $this->finishedAt = new DateTimeImmutable();
    }

    public function getProgress(): int
    {
        if ($this->totalRows === 0) {
            return $this->status->isFinished() ? 100 : 0;
        }

        return (int) floor($this->processedRows / $this->totalRows * 100);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ImportStatus
    {
        return $this->status;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    /** @return list<array{row: int, external_code: ?string, message: string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return list<array{row: int, external_code: ?string, message: string}> */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }
}
