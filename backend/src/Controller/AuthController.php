<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Http\Json;
use App\Middleware\JwtAuthMiddleware;
use App\Service\Auth\AuthService;
use App\Service\Auth\InvalidCredentialsException;
use App\Service\Auth\TokenService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly TokenService $tokens,
    ) {}

    /** POST /api/auth/login {"username": "...", "password": "..."} */
    public function login(Request $request, Response $response): Response
    {
        $body     = $request->getParsedBody();
        $username = is_array($body) && is_string($body['username'] ?? null) ? trim($body['username']) : '';
        $password = is_array($body) && is_string($body['password'] ?? null) ? $body['password'] : '';

        $errors = [];
        if ($username === '') {
            $errors['username'] = 'Введите логин';
        }
        if ($password === '') {
            $errors['password'] = 'Введите пароль';
        }
        if ($errors !== []) {
            return Json::error($response, 'Некорректные параметры запроса', 422, $errors);
        }

        try {
            $user = $this->auth->authenticate($username, $password);
        } catch (InvalidCredentialsException $e) {
            return Json::error($response, $e->getMessage(), 401);
        }

        $issued = $this->tokens->issue($user);

        return Json::write($response, [
            'token'     => $issued['token'],
            'expiresAt' => $issued['expiresAt']->format(DATE_ATOM),
            'user'      => self::user($user),
        ]);
    }

    /** GET /api/auth/me — текущий пользователь по токену. */
    public function me(Request $request, Response $response): Response
    {
        $user = $request->getAttribute(JwtAuthMiddleware::USER_ATTRIBUTE);
        if (!$user instanceof User) {
            return Json::error($response, 'Требуется авторизация', 401);
        }

        return Json::write($response, self::user($user));
    }

    /** @return array{id: int|null, username: string} */
    private static function user(User $user): array
    {
        return ['id' => $user->getId(), 'username' => $user->getUsername()];
    }
}
