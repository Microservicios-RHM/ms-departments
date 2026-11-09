<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Application\DepartmentService;
use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Http\DepartmentController;
use App\Infrastructure\Http\OpenApiDocument;
use App\Infrastructure\Observability\Health;
use App\Infrastructure\Observability\JsonLogger;
use App\Infrastructure\Observability\Metrics;
use App\Infrastructure\Observability\Telemetry;
use App\Infrastructure\Persistence\MySqlDepartmentRepository;
use App\Shared\ApiException;
use App\Shared\ResponseMessages;

$startedAt = microtime(true);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$route = Metrics::routeTemplate($method, $path);
$service = JsonLogger::serviceName();
$logger = new JsonLogger($service);
$metrics = new Metrics($service);

// Reto 8: /metrics lo consulta Prometheus cada 15 s; no se mide ni se traza a sí mismo.
if ($method === 'GET' && $path === '/metrics') {
    header('Content-Type: ' . Metrics::contentType());
    echo $metrics->render();
    exit;
}

// Reto 8: span SERVER de la petición, hijo del traceparent que envían el Gateway o
// empleados-service. /health no se traza: el healthcheck de Docker taparía las trazas reales.
$serverSpan = null;
if ($path !== '/health' && Telemetry::configure()) {
    $serverSpan = Telemetry::startServerSpan($method, $route, getallheaders() ?: []);
}

// ApiResponse termina el script con exit, así que métricas, log y cierre del span van en el
// shutdown: se ejecutan siempre, también en las respuestas de error.
register_shutdown_function(static function () use ($startedAt, $method, $path, $route, $logger, $metrics, $serverSpan): void {
    $status = (int) http_response_code();
    $seconds = microtime(true) - $startedAt;
    $metrics->observe($method, $route, $status, $seconds);
    if ($path !== '/health') {
        $logger->log($status >= 500 ? 'ERROR' : ($status >= 400 ? 'WARN' : 'INFO'), 'HTTP request completed', [
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'durationMs' => round($seconds * 1000, 2),
        ]);
    }
    if ($serverSpan !== null) {
        Telemetry::endServerSpan($serverSpan[0], $serverSpan[1], $status);
    }
});

try {
    // Reto 8: estado real del servicio (200 UP / 503 DOWN) verificando la conexión a MySQL.
    if ($method === 'GET' && $path === '/health') {
        $report = Health::evaluate($service, [
            'database' => static fn (): bool => ConnectionFactory::create()->query('SELECT 1')->fetchColumn() == 1,
        ]);
        http_response_code($report['status'] === 'UP' ? 200 : 503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($report, JSON_UNESCAPED_SLASHES);
        exit;
    }
    // Montadas bajo /departamentos (no en la raíz) para poder vivir detrás del API Gateway sin
    // reescritura de rutas: el Gateway ya proxea /departamentos/* preservando la ruta. Se
    // resuelven antes que la regex de /departamentos/{id}, así que "docs" nunca se interpreta
    // como un id de departamento.
    if ($method === 'GET' && $path === '/departamentos/openapi.json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(OpenApiDocument::get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($method === 'GET' && ($path === '/departamentos/docs' || $path === '/departamentos/docs/')) {
        header('Content-Type: text/html; charset=utf-8');
        echo OpenApiDocument::swaggerUi();
        exit;
    }

    $controller = static fn (): DepartmentController => new DepartmentController(
        new DepartmentService(new MySqlDepartmentRepository(ConnectionFactory::create())),
    );

    if ($method === 'POST' && $path === '/departamentos') $controller()->create();
    if ($method === 'GET' && $path === '/departamentos') $controller()->findAll();
    if ($method === 'GET' && preg_match('#^/departamentos/([^/]+)$#', $path, $matches) === 1) {
        $controller()->findById(rawurldecode($matches[1]));
    }

    ApiResponse::error(404, ResponseMessages::RESOURCE_NOT_FOUND, 'RESOURCE_NOT_FOUND');
} catch (ApiException $exception) {
    ApiResponse::error($exception->status, $exception->getMessage(), $exception->errorCode);
} catch (Throwable $exception) {
    $logger->error($exception->getMessage(), ['exception' => $exception::class]);
    if ($serverSpan !== null) {
        $serverSpan[0]->recordException($exception);
    }
    ApiResponse::error(500, ResponseMessages::INTERNAL_ERROR, 'INTERNAL_ERROR');
}
