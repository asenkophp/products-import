<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Документация API: спецификация OpenAPI и Swagger UI. */
final class DocsController
{
    private const SPEC_FILE          = __DIR__ . '/../../openapi/openapi.yaml';
    private const SWAGGER_UI_VERSION = '5.33.3';

    /** GET /api/openapi.yaml */
    public function spec(Request $request, Response $response): Response
    {
        $response->getBody()->write((string) file_get_contents(self::SPEC_FILE));

        return $response->withHeader('Content-Type', 'application/yaml; charset=utf-8');
    }

    /** GET /api/docs — Swagger UI */
    public function ui(Request $request, Response $response): Response
    {
        $cdn  = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@' . self::SWAGGER_UI_VERSION;
        $html = <<<HTML
            <!doctype html>
            <html lang="ru">
            <head>
              <meta charset="utf-8">
              <title>Products Import API — Swagger UI</title>
              <link rel="stylesheet" href="{$cdn}/swagger-ui.css">
            </head>
            <body>
              <div id="swagger-ui"></div>
              <script src="{$cdn}/swagger-ui-bundle.js"></script>
              <script>
                window.ui = SwaggerUIBundle({ url: '/api/openapi.yaml', dom_id: '#swagger-ui', deepLinking: true, persistAuthorization: true });
              </script>
            </body>
            </html>
            HTML;

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
