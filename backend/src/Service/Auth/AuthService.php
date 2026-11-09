<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Repository\UserRepository;

final class AuthService
{
    /** Хеш-заглушка: пароль проверяется и для несуществующего логина, чтобы время ответа не выдавало, есть ли такой пользователь. */
    private const DUMMY_HASH = '$2y$10$aJbu4jtPYfenvVHqInUTfOxRd4m5xnaHMlpLqNOrQLpu6tO18iv1.';

    public function __construct(private readonly UserRepository $users) {}

    /** @throws InvalidCredentialsException */
    public function authenticate(string $username, string $password): User
    {
        $user  = $this->users->findByUsername($username);
        $valid = password_verify($password, $user?->getPasswordHash() ?? self::DUMMY_HASH);

        if ($user === null || !$valid) {
            throw new InvalidCredentialsException();
        }

        return $user;
    }
}
