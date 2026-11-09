<?php

declare(strict_types=1);

namespace App\Service\Auth;

use RuntimeException;

final class InvalidCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Неверный логин или пароль');
    }
}
