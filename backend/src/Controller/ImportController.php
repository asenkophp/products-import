<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Json;
use App\Presenter\ImportJobPresenter;
use App\Repository\ImportJobRepository;
use App\Service\Import\ImportLauncher;
use App\Service\Import\InvalidImportFileException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

/** Контроллер только принимает запрос, делегирует сервису и возвращает ответ. */
final class ImportController
{
    public function __construct(
        private readonly ImportLauncher $launcher,
        private readonly ImportJobRepository $jobs,
        private readonly ImportJobPresenter $presenter,
    ) {}

    /** POST /api/import — загрузка файла, импорт ставится в очередь. */
    public function upload(Request $request, Response $response): Response
    {
        $file = $request->getUploadedFiles()['file'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            return Json::error($response, 'Файл не передан (ожидается поле "file")', 422);
        }

        try {
            $job = $this->launcher->launch($file);
        } catch (InvalidImportFileException $e) {
            return Json::error($response, $e->getMessage(), 422);
        }

        return Json::write($response, $this->presenter->present($job), 202)
            ->withHeader('Location', '/api/import/' . $job->getId());
    }

    /**
     * GET /api/import/{id} — статус, прогресс и отчёт об ошибках (для polling).
     *
     * @param array{id: string} $args
     */
    public function status(Request $request, Response $response, array $args): Response
    {
        $job = $this->jobs->find((int) $args['id']);
        if ($job === null) {
            return Json::error($response, 'Задача импорта не найдена', 404);
        }

        return Json::write($response, $this->presenter->present($job));
    }
}
