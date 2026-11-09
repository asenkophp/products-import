<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use DateTimeImmutable;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Throwable;

/** Выпуск и проверка JWT (HS256). В токене только ID и логин — без чувствительных данных. */
final class TokenService
{
    private const ALGORITHM = 'HS256';
    private const ISSUER    = 'products-import';

    public function __construct(
        private readonly string $secret,
        private readonly int $ttl,
    ) {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('JWT_SECRET должен быть не короче 32 символов');
        }
    }

    /** @return array{token: string, expiresAt: DateTimeImmutable} */
    public function issue(User $user): array
    {
        $now       = time();
        $expiresAt = $now + $this->ttl;

        $token = JWT::encode([
            'iss'      => self::ISSUER,
            'sub'      => (string) $user->getId(),
            'username' => $user->getUsername(),
            'iat'      => $now,
            'exp'      => $expiresAt,
        ], $this->secret, self::ALGORITHM);

        return ['token' => $token, 'expiresAt' => (new DateTimeImmutable())->setTimestamp($expiresAt)];
    }

    /**
     * @return int ID пользователя из токена
     *
     * @throws InvalidTokenException
     */
    public function userId(string $token): int
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (ExpiredException) {
            throw new InvalidTokenException('Срок действия токена истёк, войдите заново');
        } catch (Throwable) {
            throw new InvalidTokenException('Недействительный токен');
        }

        $issuer  = $payload->iss ?? null;
        $subject = $payload->sub ?? null;
        if ($issuer !== self::ISSUER || !is_string($subject) || !ctype_digit($subject)) {
            throw new InvalidTokenException('Недействительный токен');
        }

        return (int) $subject;
    }
}
