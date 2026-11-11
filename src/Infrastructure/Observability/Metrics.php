<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;

/**
 * Métricas en formato Prometheus (Reto 8) con promphp/prometheus_client_php.
 *
 * Mismos nombres y etiquetas que en los demás servicios del ecosistema, para que el dashboard de
 * Grafana use una sola consulta por panel:
 *   - http_requests_total{method, route, status}
 *   - http_request_duration_seconds{method, route, status}   (_bucket, _sum, _count)
 *   - process_cpu_seconds_total, process_resident_memory_bytes
 *
 * PHP bajo Apache no es un proceso único: cada petición la atiende un worker distinto y la memoria
 * de PHP se descarta al terminar. Por eso los contadores viven en APCu (memoria compartida entre
 * los workers de Apache), no en el proceso. Por la misma razón, CPU y memoria se toman del cgroup
 * del contenedor (todos los workers juntos) en vez de un solo proceso.
 *
 * `route` es la plantilla (/departamentos/{id}), nunca la URL con el id real (alta cardinalidad).
 */
final class Metrics
{
    public const UNMATCHED_ROUTE = 'unmatched';
    private const BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2, 5, 10];
    private const LABELS = ['service', 'method', 'route', 'status'];

    private CollectorRegistry $registry;

    public function __construct(
        private readonly string $service,
        ?Adapter $storage = null,
        private readonly string $cgroupPath = '/sys/fs/cgroup',
    ) {
        $this->registry = new CollectorRegistry($storage ?? new APC(), false);
    }

    public function observe(string $method, string $route, int $status, float $seconds): void
    {
        $labels = [$this->service, $method, $route, (string) $status];
        $this->registry
            ->getOrRegisterCounter('', 'http_requests_total', 'Total de peticiones HTTP recibidas, por método, ruta y código de respuesta', self::LABELS)
            ->inc($labels);
        $this->registry
            ->getOrRegisterHistogram('', 'http_request_duration_seconds', 'Tiempo de respuesta de las peticiones HTTP en segundos', self::LABELS, self::BUCKETS)
            ->observe($seconds, $labels);
    }

    public function render(): string
    {
        $cpu = $this->cgroupCpuSeconds();
        if ($cpu !== null) {
            $this->registry
                ->getOrRegisterGauge('', 'process_cpu_seconds_total', 'Tiempo de CPU consumido por el contenedor (todos los workers) en segundos', ['service'])
                ->set($cpu, [$this->service]);
        }
        $memory = $this->cgroupMemoryBytes();
        if ($memory !== null) {
            $this->registry
                ->getOrRegisterGauge('', 'process_resident_memory_bytes', 'Memoria en uso del contenedor (todos los workers) en bytes', ['service'])
                ->set($memory, [$this->service]);
        }

        return (new RenderTextFormat())->render($this->registry->getMetricFamilySamples());
    }

    public static function contentType(): string
    {
        return RenderTextFormat::MIME_TYPE;
    }

    /** Plantilla de ruta para la etiqueta `route`. */
    public static function routeTemplate(string $method, string $path): string
    {
        return match (true) {
            $path === '/health', $path === '/departamentos', $path === '/departamentos/openapi.json' => $path,
            $path === '/departamentos/docs', $path === '/departamentos/docs/' => '/departamentos/docs',
            preg_match('#^/departamentos/[^/]+$#', $path) === 1 => '/departamentos/{id}',
            default => self::UNMATCHED_ROUTE,
        };
    }

    private function cgroupCpuSeconds(): ?float
    {
        $stat = @file_get_contents($this->cgroupPath . '/cpu.stat');
        if ($stat === false || preg_match('/^usage_usec (\d+)$/m', $stat, $matches) !== 1) {
            return null;
        }
        return ((int) $matches[1]) / 1_000_000;
    }

    private function cgroupMemoryBytes(): ?float
    {
        $memory = @file_get_contents($this->cgroupPath . '/memory.current');
        return $memory === false ? null : (float) trim($memory);
    }
}
