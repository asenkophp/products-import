<?php

declare(strict_types=1);

namespace App\Service\Import;

use finfo;
use Psr\Http\Message\UploadedFileInterface;

/** Проверка загружаемого файла: ошибка загрузки, размер, расширение, MIME по содержимому. */
final class ImportFileValidator
{
    public const ALLOWED_EXTENSIONS = ['xlsx', 'ods'];

    private const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
        // xlsx и ods — это zip-архивы, старые версии libmagic определяют их так
        'application/zip',
    ];

    public function __construct(private readonly int $maxFileSize) {}

    /**
     * @return string расширение файла
     *
     * @throws InvalidImportFileException
     */
    public function validate(UploadedFileInterface $file): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidImportFileException(match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой',
                UPLOAD_ERR_NO_FILE                        => 'Файл не выбран',
                default                                   => 'Ошибка загрузки файла',
            });
        }

        $size = $file->getSize();
        if ($size === null || $size === 0) {
            throw new InvalidImportFileException('Файл пустой');
        }
        if ($size > $this->maxFileSize) {
            throw new InvalidImportFileException(sprintf('Файл больше %d МБ', intdiv($this->maxFileSize, 1024 * 1024)));
        }

        $extension = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidImportFileException('Допустимы только файлы .xlsx и .ods');
        }

        $tmpPath = $file->getStream()->getMetadata('uri');
        $mime    = is_string($tmpPath) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath) : false;
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidImportFileException(sprintf('Неверный тип файла: %s', is_string($mime) ? $mime : 'не определён'));
        }

        return $extension;
    }
}
