<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Auth\TokenService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/** Запросы к API через Slim-приложение целиком (маршруты, middleware, контроллеры, БД). */
abstract class ApiTestCase extends DatabaseTestCase
{
    private ?App $app      = null;
    private ?string $token = null;

    /**
     * @param array<string, string|int>            $query
     * @param array<string, UploadedFileInterface> $files
     * @param array<string, mixed>|null            $json  тело запроса в JSON
     * @param bool|string                          $auth  true — токен тестового пользователя, false — без токена, строка — свой токен
     */
    protected function request(
        string $method,
        string $path,
        array $query = [],
        array $files = [],
        ?array $json = null,
        bool|string $auth = true,
    ): ResponseInterface {
        $uri     = $path . ($query === [] ? '' : '?' . http_build_query($query));
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $uri, ['REMOTE_ADDR' => '127.0.0.1'])
            ->withQueryParams($query)
            ->withUploadedFiles($files);

        if ($auth !== false) {
            $request = $request->withHeader('Authorization', 'Bearer ' . ($auth === true ? $this->token() : $auth));
        }
        if ($json !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream(json_encode($json, JSON_THROW_ON_ERROR)));
        }

        return $this->app()->handle($request);
    }

    /** @return array<string, mixed> */
    protected function json(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    /** Токен тестового пользователя (создаётся при первом обращении в тесте). */
    protected function token(): string
    {
        if ($this->token === null) {
            $user = new User('tester', password_hash('tester-password', PASSWORD_BCRYPT, ['cost' => 4]));
            self::service(UserRepository::class)->save($user);
            $this->token = self::service(TokenService::class)->issue($user)['token'];
        }

        return $this->token;
    }

    private function app(): App
    {
        if ($this->app === null) {
            /** @var callable(ContainerInterface): App $factory */
            $factory   = require __DIR__ . '/../../config/app.php';
            $this->app = $factory(self::container());
        }

        return $this->app;
    }
}
