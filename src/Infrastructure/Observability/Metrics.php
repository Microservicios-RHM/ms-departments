<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

final class Metrics
{
    private const FILE = '/tmp/rhm-departments-metrics.json';
    private const SERVICE = 'departamentos-service';

    public static function observeHttp(string $method, int $status, float $durationSeconds): void
    {
        self::mutate(static function (array &$data) use ($method, $status, $durationSeconds): void {
            $key = $method . '|' . $status;
            $data['requests'][$key] = ($data['requests'][$key] ?? 0) + 1;
            $data['durations'][$method] = ($data['durations'][$method] ?? 0.0) + $durationSeconds;
        });
    }

    public static function render(): string
    {
        $data = self::read();
        $lines = [
            '# HELP rhm_service_info Static service identity metric',
            '# TYPE rhm_service_info gauge',
            'rhm_service_info{service="' . self::SERVICE . '"} 1',
            '# HELP rhm_http_requests_total HTTP requests by method and status',
            '# TYPE rhm_http_requests_total counter',
        ];
        ksort($data['requests']);
        foreach ($data['requests'] as $key => $value) {
            [$method, $status] = explode('|', $key, 2);
            $lines[] = sprintf('rhm_http_requests_total{service="%s",method="%s",status="%s"} %d', self::SERVICE, $method, $status, $value);
        }
        $lines[] = '# HELP rhm_http_request_duration_seconds_sum Cumulative HTTP request duration';
        $lines[] = '# TYPE rhm_http_request_duration_seconds_sum counter';
        foreach ($data['durations'] as $method => $value) {
            $lines[] = sprintf('rhm_http_request_duration_seconds_sum{service="%s",method="%s"} %F', self::SERVICE, $method, $value);
        }
        return implode("\n", $lines) . "\n";
    }

    private static function mutate(callable $callback): void
    {
        $handle = fopen(self::FILE, 'c+');
        if ($handle === false) return;
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $data = $raw ? json_decode($raw, true) : [];
            $data = is_array($data) ? $data : [];
            $data += ['requests' => [], 'durations' => []];
            $callback($data);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function read(): array
    {
        $raw = @file_get_contents(self::FILE);
        $data = $raw ? json_decode($raw, true) : [];
        return is_array($data) ? $data + ['requests' => [], 'durations' => []] : ['requests' => [], 'durations' => []];
    }
}
