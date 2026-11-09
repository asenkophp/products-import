<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;

final class Json
{
    public static function write(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
        );

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    /** @param array<string, string> $fields ошибки по полям (для 422) */
    public static function error(ResponseInterface $response, string $message, int $status, array $fields = []): ResponseInterface
    {
        $error = ['message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return self::write($response, ['error' => $error], $status);
    }
}
