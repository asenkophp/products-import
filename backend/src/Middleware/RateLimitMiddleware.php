<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Json;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/** Ограничение частоты запросов по IP (sliding window). Превышение → 429 + Retry-After. */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiterFactory $limiterFactory,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip    = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $limit = $this->limiterFactory->create($ip)->consume();

        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            return Json::error($this->responseFactory->createResponse(), 'Слишком много запросов, попробуйте позже', 429)
                ->withHeader('Retry-After', (string) $retryAfter);
        }

        return $handler->handle($request)
            ->withHeader('X-RateLimit-Limit', (string) $limit->getLimit())
            ->withHeader('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
    }
}
