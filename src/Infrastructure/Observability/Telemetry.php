<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\Contrib\Zipkin\Exporter as ZipkinExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

/**
 * Trazabilidad distribuida con OpenTelemetry (Reto 8).
 *
 * PHP bajo Apache no tiene un proceso de larga vida: cada petición arranca y termina el script.
 * Por eso no hay instrumentación automática como en Node o Python; el ciclo es manual:
 *   1. extraer el `traceparent` (W3C Trace Context) que envía empleados-service o el Gateway;
 *   2. abrir un span SERVER hijo de ese contexto y activarlo;
 *   3. las consultas a MySQL crean spans hijos (TracedStatement);
 *   4. al terminar la petición, cerrar el span y exportarlo a Zipkin.
 *
 * El exportador es síncrono (SimpleSpanProcessor): no hay proceso de fondo que envíe lotes, así que
 * cada petición envía sus spans al terminar, con un tiempo límite corto para no colgarse si Zipkin
 * no responde. Sin OTEL_EXPORTER_ZIPKIN_ENDPOINT el servicio usa un tracer no-op.
 */
final class Telemetry
{
    private static ?TracerProvider $provider = null;

    public static function configure(?SpanExporterInterface $exporter = null): bool
    {
        if ($exporter === null) {
            $endpoint = getenv('OTEL_EXPORTER_ZIPKIN_ENDPOINT');
            if ($endpoint === false || $endpoint === '') {
                return false;
            }
            $http = new HttpFactory();
            $transport = (new PsrTransportFactory(new Client(['timeout' => 2]), $http, $http))
                ->create($endpoint, 'application/json', [], null, 2.0, 100, 0);
            $exporter = new ZipkinExporter($transport);
        }

        self::$provider = TracerProvider::builder()
            ->addSpanProcessor(new SimpleSpanProcessor($exporter))
            ->setResource(ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => JsonLogger::serviceName(),
            ]))))
            ->build();
        return true;
    }

    public static function tracer(): TracerInterface
    {
        return self::$provider?->getTracer('rhm-departments') ?? NoopTracer::getInstance();
    }

    /**
     * Abre y activa el span SERVER de la petición, continuando la traza del llamador.
     *
     * @param array<string, string> $headers cabeceras HTTP de la petición
     * @return array{0: SpanInterface, 1: ScopeInterface}
     */
    public static function startServerSpan(string $method, string $route, array $headers): array
    {
        $carrier = array_change_key_case($headers, CASE_LOWER);
        $parent = TraceContextPropagator::getInstance()->extract($carrier);
        $span = self::tracer()->spanBuilder("{$method} {$route}")
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setParent($parent)
            ->setAttribute('http.request.method', $method)
            ->setAttribute('http.route', $route)
            ->startSpan();

        return [$span, $span->activate()];
    }

    public static function endServerSpan(SpanInterface $span, ScopeInterface $scope, int $status): void
    {
        $span->setAttribute('http.response.status_code', $status);
        if ($status >= 500) {
            $span->setStatus(StatusCode::STATUS_ERROR);
        }
        $span->end();
        $scope->detach();
        self::$provider?->forceFlush();
    }
}
