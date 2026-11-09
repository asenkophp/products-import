<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\DocsController;
use App\Controller\HealthController;
use App\Controller\ImportController;
use App\Controller\ProductController;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    $app->group('/api', function (RouteCollectorProxy $api): void {
        // публичные маршруты
        $api->get('/health', HealthController::class);
        $api->get('/docs', [DocsController::class, 'ui']);
        $api->get('/openapi.yaml', [DocsController::class, 'spec']);
        $api->post('/auth/login', [AuthController::class, 'login'])
            ->add('middleware.rate_limit.login');

        // всё остальное — только с JWT
        $api->group('', function (RouteCollectorProxy $secured): void {
            $secured->get('/auth/me', [AuthController::class, 'me']);

            $secured->post('/import', [ImportController::class, 'upload'])
                ->add(RateLimitMiddleware::class);
            $secured->get('/import/{id:[0-9]+}', [ImportController::class, 'status']);

            $secured->get('/products', [ProductController::class, 'list']);
            $secured->get('/products/{id:[0-9]+}', [ProductController::class, 'show']);
        })->add(JwtAuthMiddleware::class);
    });
};
