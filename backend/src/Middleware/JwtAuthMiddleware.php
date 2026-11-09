<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Json;
use App\Repository\UserRepository;
use App\Service\Auth\InvalidTokenException;
use App\Service\Auth\TokenService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Проверка заголовка Authorization: Bearer <JWT>. Пользователь передаётся дальше атрибутом запроса. */
final class JwtAuthMiddleware implements MiddlewareInterface
{
    public const USER_ATTRIBUTE = 'user';

    public function __construct(
        private readonly TokenService $tokens,
        private readonly UserRepository $users,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (preg_match('/^Bearer\s+(\S+)$/i', $request->getHeaderLine('Authorization'), $matches) !== 1) {
            return $this->unauthorized('Требуется авторизация');
        }

        try {
            $userId = $this->tokens->userId($matches[1]);
        } catch (InvalidTokenException $e) {
            return $this->unauthorized($e->getMessage());
        }

        $user = $this->users->find($userId);
        if ($user === null) {
            return $this->unauthorized('Пользователь не найден');
        }

        return $handler->handle($request->withAttribute(self::USER_ATTRIBUTE, $user));
    }

    private function unauthorized(string $message): ResponseInterface
    {
        return Json::error($this->responseFactory->createResponse(), $message, 401)
            ->withHeader('WWW-Authenticate', 'Bearer');
    }
}
