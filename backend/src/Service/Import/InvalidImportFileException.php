<?php

declare(strict_types=1);

namespace App\Service\Import;

use RuntimeException;

/** Файл целиком непригоден для импорта: неверный формат, размер, нет обязательных колонок. */
final class InvalidImportFileException extends RuntimeException {}
