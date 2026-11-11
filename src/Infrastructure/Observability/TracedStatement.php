<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use PDOStatement;
use Throwable;

/**
 * Sentencia PDO instrumentada (Reto 8): cada execute() crea un span CLIENT hijo del span de la
 * petición, con la consulta SQL como atributo. Así, en Zipkin se distingue si la demora de
 * GET /departamentos/{id} está en MySQL o en el propio servicio.
 *
 * Se registra con PDO::ATTR_STATEMENT_CLASS en ConnectionFactory, sin tocar el repositorio.
 * PDO exige que el constructor de una clase de sentencia propia no sea público.
 */
class TracedStatement extends PDOStatement
{
    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        $span = Telemetry::tracer()->spanBuilder('mysql query')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('db.system.name', 'mysql')
            ->setAttribute('db.query.text', $this->queryString)
            ->startSpan();
        try {
            return parent::execute($params);
        } catch (Throwable $exception) {
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            throw $exception;
        } finally {
            $span->end();
        }
    }
}
