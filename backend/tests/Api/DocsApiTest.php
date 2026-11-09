<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class DocsApiTest extends ApiTestCase
{
    public function testSpecDescribesAllRoutes(): void
    {
        $response = $this->request('GET', '/api/openapi.yaml');

        self::assertSame(200, $response->getStatusCode());
        $spec = (string) $response->getBody();
        foreach (['/api/health:', '/api/auth/login:', '/api/auth/me:', '/api/import:', '/api/import/{id}:', '/api/products:', '/api/products/{id}:'] as $path) {
            self::assertStringContainsString($path, $spec);
        }
    }

    public function testSwaggerUi(): void
    {
        $response = $this->request('GET', '/api/docs');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('SwaggerUIBundle', (string) $response->getBody());
    }
}
