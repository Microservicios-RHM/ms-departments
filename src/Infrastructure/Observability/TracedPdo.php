<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Conexión PDO instrumentada (Reto 8). Las sentencias preparadas se trazan en
 * TracedStatement::execute(); query() ejecuta directamente sin pasar por execute(), así que se
 * traza aquí. El repositorio sigue recibiendo un PDO y no conoce OpenTelemetry.
 */
class TracedPdo extends PDO
{
    /** @param array<int, mixed> $options */
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, array $options = [])
    {
        parent::__construct($dsn, $username, $password, $options + [
            PDO::ATTR_STATEMENT_CLASS => [TracedStatement::class, []],
        ]);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $span = Telemetry::tracer()->spanBuilder('mysql query')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('db.system.name', 'mysql')
            ->setAttribute('db.query.text', $query)
            ->startSpan();
        try {
            return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
        } catch (Throwable $exception) {
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            throw $exception;
        } finally {
            $span->end();
        }
    }
}
