<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ImportJob;
use App\Message\ImportProductsMessage;
use App\Repository\ImportJobRepository;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/** Принимает файл: валидирует, сохраняет, создаёт задачу и ставит её в очередь. */
final class ImportLauncher
{
    public function __construct(
        private readonly ImportJobRepository $jobs,
        private readonly MessageBusInterface $bus,
        private readonly ImportFileValidator $validator,
        private readonly string $storageDir,
    ) {}

    /** @throws InvalidImportFileException */
    public function launch(UploadedFileInterface $file): ImportJob
    {
        $extension = $this->validator->validate($file);

        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0o775, true) && !is_dir($this->storageDir)) {
            throw new \RuntimeException('Не удалось создать папку для файлов импорта');
        }

        $target = sprintf('%s/%s_%s.%s', $this->storageDir, date('Ymd_His'), bin2hex(random_bytes(4)), $extension);
        $file->moveTo($target);

        $job = new ImportJob($file->getClientFilename() ?? basename($target), $target);
        $this->jobs->save($job);

        $this->bus->dispatch(new ImportProductsMessage((int) $job->getId()));

        return $job;
    }
}
