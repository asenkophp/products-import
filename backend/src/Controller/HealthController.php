<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Json;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

/** GET /api/health — проверка доступности БД и брокера очередей. */
final class HealthController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TransportInterface $transport,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $checks = [
            'database' => $this->check(fn() => $this->em->getConnection()->executeQuery('SELECT 1')),
            'queue'    => $this->check(function (): void {
                if ($this->transport instanceof MessageCountAwareInterface) {
                    $this->transport->getMessageCount();
                }
            }),
        ];
        $healthy = !in_array(false, $checks, true);

        return Json::write($response, [
            'status' => $healthy ? 'ok' : 'fail',
            'checks' => array_map(static fn(bool $ok): string => $ok ? 'ok' : 'fail', $checks),
            'time'   => date(DATE_ATOM),
        ], $healthy ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
