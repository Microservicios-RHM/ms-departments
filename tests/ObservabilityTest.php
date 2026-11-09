<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\Observability\Health;
use App\Infrastructure\Observability\JsonLogger;
use App\Infrastructure\Observability\Metrics;
use App\Infrastructure\Observability\Telemetry;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use PHPUnit\Framework\TestCase;
use Prometheus\Storage\InMemory;
use RuntimeException;

final class ObservabilityTest extends TestCase
{
    public function testHealthReportaUpYDownSegunLaBaseDeDatos(): void
    {
        self::assertSame(
            ['status' => 'UP', 'service' => 'departamentos-service', 'checks' => ['database' => 'UP']],
            Health::evaluate('departamentos-service', ['database' => static fn (): bool => true]),
        );
        $down = Health::evaluate('departamentos-service', [
            'database' => static fn (): bool => throw new RuntimeException('Connection refused'),
        ]);
        self::assertSame('DOWN', $down['status']);
        self::assertSame(['database' => 'DOWN'], $down['checks']);
    }

    public function testMetricasPorPlantillaDeRutaSinIds(): void
    {
        $cgroup = sys_get_temp_dir() . '/cgroup-' . uniqid();
        mkdir($cgroup);
        file_put_contents($cgroup . '/cpu.stat', "usage_usec 2500000\nuser_usec 1\n");
        file_put_contents($cgroup . '/memory.current', "4194304\n");

        $metrics = new Metrics('departamentos-service', new InMemory(), $cgroup);
        $metrics->observe('GET', Metrics::routeTemplate('GET', '/departamentos/IT'), 200, 0.02);
        $metrics->observe('GET', Metrics::routeTemplate('GET', '/departamentos/RRHH'), 200, 0.03);
        $metrics->observe('GET', Metrics::routeTemplate('GET', '/departamentos/X'), 404, 0.01);
        $body = $metrics->render();

        self::assertStringContainsString(
            'http_requests_total{service="departamentos-service",method="GET",route="/departamentos/{id}",status="200"} 2',
            $body,
        );
        self::assertStringContainsString('http_request_duration_seconds_count{service="departamentos-service",method="GET",route="/departamentos/{id}",status="404"} 1', $body);
        self::assertStringContainsString('process_cpu_seconds_total{service="departamentos-service"} 2.5', $body);
        self::assertStringContainsString('process_resident_memory_bytes{service="departamentos-service"} 4194304', $body);
        self::assertStringNotContainsString('RRHH', $body);
    }

    public function testPlantillasDeRuta(): void
    {
        self::assertSame('/health', Metrics::routeTemplate('GET', '/health'));
        self::assertSame('/departamentos', Metrics::routeTemplate('POST', '/departamentos'));
        self::assertSame('/departamentos/docs', Metrics::routeTemplate('GET', '/departamentos/docs/'));
        self::assertSame('/departamentos/openapi.json', Metrics::routeTemplate('GET', '/departamentos/openapi.json'));
        self::assertSame(Metrics::UNMATCHED_ROUTE, Metrics::routeTemplate('GET', '/otra/cosa'));
    }

    public function testElSpanDeLaPeticionContinuaElTraceparentYElLogLlevaElTraceId(): void
    {
        $exporter = new InMemoryExporter();
        self::assertTrue(Telemetry::configure($exporter));
        $traceId = '4bf92f3577b34da6a3ce929d0e0e4736';

        [$span, $scope] = Telemetry::startServerSpan('GET', '/departamentos/{id}', [
            'Traceparent' => "00-{$traceId}-00f067aa0ba902b7-01",
        ]);
        $stream = fopen('php://memory', 'w+');
        (new JsonLogger('departamentos-service', $stream))->info('Departamento consultado', ['id' => 'IT']);
        Telemetry::endServerSpan($span, $scope, 500);

        [$exported] = $exporter->getSpans();
        self::assertSame('GET /departamentos/{id}', $exported->getName());
        self::assertSame(SpanKind::KIND_SERVER, $exported->getKind());
        self::assertSame($traceId, $exported->getTraceId());
        self::assertSame('00f067aa0ba902b7', $exported->getParentSpanId());
        self::assertSame('Error', $exported->getStatus()->getCode());

        rewind($stream);
        $log = json_decode((string) stream_get_contents($stream), true);
        self::assertSame('INFO', $log['level']);
        self::assertSame('departamentos-service', $log['service']);
        self::assertSame($traceId, $log['traceId']);
        self::assertSame('Departamento consultado', $log['message']);
        self::assertSame('IT', $log['id']);
        self::assertArrayHasKey('timestamp', $log);
    }
}
