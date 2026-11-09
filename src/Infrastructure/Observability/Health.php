<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use Throwable;

/**
 * Health Check API (Reto 8): GET /health verifica de verdad la base de datos MySQL.
 *   200 {"status":"UP","service":"departamentos-service","checks":{"database":"UP"}}
 *   503 {"status":"DOWN","service":"departamentos-service","checks":{"database":"DOWN"}}
 *
 * departamentos-service no usa el broker de mensajes, por eso su único check es la base de datos.
 */
final class Health
{
    /**
     * @param array<string, callable(): bool> $checks
     * @return array{status: string, service: string, checks: array<string, string>}
     */
    public static function evaluate(string $service, array $checks): array
    {
        $results = [];
        foreach ($checks as $name => $check) {
            try {
                $results[$name] = $check() ? 'UP' : 'DOWN';
            } catch (Throwable) {
                $results[$name] = 'DOWN';
            }
        }
        $healthy = !in_array('DOWN', $results, true);

        return ['status' => $healthy ? 'UP' : 'DOWN', 'service' => $service, 'checks' => $results];
    }
}
