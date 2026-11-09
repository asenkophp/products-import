<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class HealthApiTest extends ApiTestCase
{
    public function testHealthy(): void
    {
        $response = $this->request('GET', '/api/health');

        self::assertSame(200, $response->getStatusCode());
        $data = $this->json($response);
        self::assertSame('ok', $data['status']);
        self::assertSame(['database' => 'ok', 'queue' => 'ok'], $data['checks']);
    }
}
