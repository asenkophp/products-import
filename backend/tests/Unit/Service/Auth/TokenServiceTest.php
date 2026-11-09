<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\Entity\User;
use App\Service\Auth\InvalidTokenException;
use App\Service\Auth\TokenService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class TokenServiceTest extends TestCase
{
    private const SECRET = 'unit-secret-0123456789abcdef0123456789';

    private function user(int $id): User
    {
        $user = new User('admin', 'hash');
        (new ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    public function testIssuesAndParsesToken(): void
    {
        $service = new TokenService(self::SECRET, 3600);
        $issued  = $service->issue($this->user(42));

        self::assertSame(42, $service->userId($issued['token']));
        self::assertGreaterThan(time(), $issued['expiresAt']->getTimestamp());
    }

    public function testRejectsTamperedToken(): void
    {
        $token = (new TokenService(self::SECRET, 3600))->issue($this->user(1))['token'];

        $this->expectException(InvalidTokenException::class);
        (new TokenService(self::SECRET, 3600))->userId($token . 'x');
    }

    public function testRejectsTokenSignedWithAnotherSecret(): void
    {
        $token = (new TokenService(str_repeat('a', 32), 3600))->issue($this->user(1))['token'];

        $this->expectException(InvalidTokenException::class);
        (new TokenService(self::SECRET, 3600))->userId($token);
    }

    public function testRejectsExpiredToken(): void
    {
        $token = (new TokenService(self::SECRET, -10))->issue($this->user(1))['token'];

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('истёк');
        (new TokenService(self::SECRET, 3600))->userId($token);
    }

    public function testRequiresLongSecret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TokenService('short', 3600);
    }
}
