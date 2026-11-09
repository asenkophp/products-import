<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Config\AppSettings;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Auth\TokenService;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthApiTest extends ApiTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = new User('admin', password_hash('correct-password', PASSWORD_BCRYPT, ['cost' => 4]));
        self::service(UserRepository::class)->save($this->admin);
    }

    /** @param array<string, string> $credentials */
    private function login(array $credentials): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('POST', '/api/auth/login', json: $credentials, auth: false);
    }

    public function testLoginReturnsTokenThatGrantsAccess(): void
    {
        $response = $this->login(['username' => 'admin', 'password' => 'correct-password']);

        self::assertSame(200, $response->getStatusCode());
        $data = $this->json($response);
        self::assertIsString($data['token']);
        self::assertSame(['id' => $this->admin->getId(), 'username' => 'admin'], $data['user']);
        self::assertIsString($data['expiresAt']);

        $me = $this->request('GET', '/api/auth/me', auth: $data['token']);
        self::assertSame(200, $me->getStatusCode());
        self::assertSame('admin', $this->json($me)['username']);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function wrongCredentials(): iterable
    {
        yield 'неверный пароль' => [['username' => 'admin', 'password' => 'wrong-password']];
        yield 'неизвестный пользователь' => [['username' => 'nobody', 'password' => 'correct-password']];
    }

    /** @param array<string, string> $credentials */
    #[DataProvider('wrongCredentials')]
    public function testWrongCredentials(array $credentials): void
    {
        $response = $this->login($credentials);

        self::assertSame(401, $response->getStatusCode());
        // одинаковый ответ: по нему нельзя понять, существует ли логин
        self::assertSame(['error' => ['message' => 'Неверный логин или пароль']], $this->json($response));
    }

    public function testEmptyCredentials(): void
    {
        $response = $this->login(['username' => ' ', 'password' => '']);

        self::assertSame(422, $response->getStatusCode());
        $error = $this->json($response)['error'];
        self::assertIsArray($error);
        self::assertIsArray($error['fields']);
        self::assertSame(['username', 'password'], array_keys($error['fields']));
    }

    /** @return iterable<string, array{string, string}> */
    public static function protectedRoutes(): iterable
    {
        yield 'список товаров' => ['GET', '/api/products'];
        yield 'карточка' => ['GET', '/api/products/1'];
        yield 'загрузка файла' => ['POST', '/api/import'];
        yield 'статус импорта' => ['GET', '/api/import/1'];
        yield 'текущий пользователь' => ['GET', '/api/auth/me'];
    }

    #[DataProvider('protectedRoutes')]
    public function testProtectedRoutesRequireToken(string $method, string $path): void
    {
        $response = $this->request($method, $path, auth: false);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testRejectsForgedAndExpiredTokens(): void
    {
        self::assertSame(401, $this->request('GET', '/api/products', auth: 'not-a-jwt')->getStatusCode());

        $secret   = AppSettings::from(self::container())['auth']['jwt_secret'];
        $expired  = (new TokenService($secret, -60))->issue($this->admin)['token'];
        $response = $this->request('GET', '/api/products', auth: $expired);

        self::assertSame(401, $response->getStatusCode());
        $error = $this->json($response)['error'];
        self::assertIsArray($error);
        self::assertStringContainsString('истёк', (string) $error['message']);

        $foreign = (new TokenService(str_repeat('x', 40), 3600))->issue($this->admin)['token'];
        self::assertSame(401, $this->request('GET', '/api/products', auth: $foreign)->getStatusCode());
    }

    public function testPublicRoutesWorkWithoutToken(): void
    {
        foreach (['/api/health', '/api/docs', '/api/openapi.yaml'] as $path) {
            self::assertSame(200, $this->request('GET', $path, auth: false)->getStatusCode(), $path);
        }
    }
}
