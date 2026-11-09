<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use OpenTelemetry\API\Trace\Span;

/**
 * Log estructurado JSON (Reto 8) con los campos mínimos del reto, iguales a los de los demás
 * servicios del ecosistema:
 *   {"timestamp":"...","level":"INFO","service":"departamentos-service","traceId":"...","message":"..."}
 *
 * Escribe directo a php://stderr (una línea por evento). error_log() pasaría por el ErrorLog de
 * Apache, que antepone su propio prefijo de texto y rompe el JSON que Promtail envía a Loki.
 */
final class JsonLogger
{
    /** @var resource|null */
    private $stream;

    /** @param resource|null $stream */
    public function __construct(private readonly string $service = '', $stream = null)
    {
        $this->stream = $stream;
    }

    public static function serviceName(): string
    {
        $name = getenv('OTEL_SERVICE_NAME');
        return $name === false || $name === '' ? 'departamentos-service' : $name;
    }

    /** @param array<string, mixed> $fields */
    public function info(string $message, array $fields = []): void
    {
        $this->log('INFO', $message, $fields);
    }

    /** @param array<string, mixed> $fields */
    public function warn(string $message, array $fields = []): void
    {
        $this->log('WARN', $message, $fields);
    }

    /** @param array<string, mixed> $fields */
    public function error(string $message, array $fields = []): void
    {
        $this->log('ERROR', $message, $fields);
    }

    /** @param array<string, mixed> $fields */
    public function log(string $level, string $message, array $fields = []): void
    {
        $payload = [
            'timestamp' => gmdate('Y-m-d\TH:i:s') . sprintf('.%03dZ', (int) (microtime(true) * 1000) % 1000),
            'level' => $level,
            'service' => $this->service !== '' ? $this->service : self::serviceName(),
            ...self::traceFields(),
            'message' => $message,
            ...$fields,
        ];
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        if ($this->stream !== null) {
            fwrite($this->stream, $line);
            return;
        }
        file_put_contents('php://stderr', $line);
    }

    /**
     * traceId/spanId del span activo: con ellos un log de Loki lleva a su traza en Zipkin.
     *
     * @return array{traceId?: string, spanId?: string}
     */
    public static function traceFields(): array
    {
        $context = Span::getCurrent()->getContext();
        if (!$context->isValid()) {
            return [];
        }
        return ['traceId' => $context->getTraceId(), 'spanId' => $context->getSpanId()];
    }
}
