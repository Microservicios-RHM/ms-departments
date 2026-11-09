<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SemConv\ResourceAttributes;

final class Telemetry
{
    private static ?TracerProvider $provider = null;

    public static function tracer(): TracerInterface
    {
        if (self::$provider === null) {
            $endpoint = getenv('OTEL_EXPORTER_OTLP_TRACES_ENDPOINT') ?: 'http://jaeger:4318/v1/traces';
            $transport = (new OtlpHttpTransportFactory())->create($endpoint, 'application/x-protobuf');
            $resource = ResourceInfo::create(Attributes::create([ResourceAttributes::SERVICE_NAME => 'departamentos-service']));
            self::$provider = new TracerProvider(new SimpleSpanProcessor(new SpanExporter($transport)), resource: $resource);
        }
        return self::$provider->getTracer('departamentos-service');
    }

    public static function startHttpSpan(string $method, string $path, array $headers): array
    {
        $parent = TraceContextPropagator::getInstance()->extract($headers, ArrayAccessGetterSetter::getInstance());
        $span = self::tracer()->spanBuilder($method . ' ' . $path)->setParent($parent)->setSpanKind(SpanKind::KIND_SERVER)->startSpan();
        $scope = $span->activate();
        return [$span, $scope];
    }
}
