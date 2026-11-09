<?php

declare(strict_types=1);

namespace App\Tests\Unit\Middleware;

use App\Middleware\RateLimitMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RateLimitMiddlewareTest extends TestCase
{
    public function testReturns429WhenLimitExceeded(): void
    {
        $middleware = new RateLimitMiddleware(
            new RateLimiterFactory(
                ['id' => 'test', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 minute'],
                new InMemoryStorage(),
            ),
            new ResponseFactory(),
        );
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(202);
            }
        };
        $request = self::request('1.2.3.4');

        self::assertSame(202, $middleware->process($request, $handler)->getStatusCode());
        self::assertSame(202, $middleware->process($request, $handler)->getStatusCode());

        $blocked = $middleware->process($request, $handler);
        self::assertSame(429, $blocked->getStatusCode());
        self::assertGreaterThan(0, (int) $blocked->getHeaderLine('Retry-After'));

        // другой IP — свой лимит
        self::assertSame(202, $middleware->process(self::request('5.6.7.8'), $handler)->getStatusCode());
    }

    private static function request(string $ip): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('POST', '/api/import', ['REMOTE_ADDR' => $ip]);
    }
}
