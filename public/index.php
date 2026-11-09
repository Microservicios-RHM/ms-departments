<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Application\DepartmentService;
use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Http\DepartmentController;
use App\Infrastructure\Http\OpenApiDocument;
use App\Infrastructure\Persistence\MySqlDepartmentRepository;
use App\Infrastructure\Observability\Metrics;
use App\Infrastructure\Observability\Telemetry;
use App\Shared\ApiException;
use App\Shared\ResponseMessages;

$startedAt = microtime(true);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$traceparent = $_SERVER['HTTP_TRACEPARENT'] ?? '';
$traceId = preg_match('/^[\da-f]{2}-([\da-f]{32})-[\da-f]{16}-[\da-f]{2}$/i', $traceparent, $traceMatch) === 1
    ? strtolower($traceMatch[1]) : bin2hex(random_bytes(16));
[$span, $scope] = Telemetry::startHttpSpan($method, $path, ['traceparent' => $traceparent]);
$traceId = $span->getContext()->getTraceId();
register_shutdown_function(static function () use ($startedAt, $method, $path, $traceId): void {
    $status = http_response_code();
    $duration = microtime(true) - $startedAt;
    if ($path !== '/metrics') Metrics::observeHttp($method, $status, $duration);
    file_put_contents('php://stdout', json_encode([
        'timestamp' => gmdate('c'),
        'level' => $status >= 400 ? 'error' : 'info',
        'service' => 'departamentos-service',
        'message' => 'HTTP request completed',
        'context' => ['method' => $method, 'path' => $path, 'status' => $status, 'durationMs' => round($duration * 1000, 2)],
        'traceId' => $traceId,
        'method' => $method,
        'path' => $path,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
});
register_shutdown_function(static function () use ($span, $scope): void {
    $span->setAttribute('http.response.status_code', http_response_code());
    $scope->detach();
    $span->end();
});

try {
    if ($method === 'GET' && $path === '/metrics') {
        header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
        echo Metrics::render();
        exit;
    }
    if ($method === 'GET' && $path === '/health') {
        $checks = ['database' => 'DOWN'];
        try {
            ConnectionFactory::create()->query('SELECT 1');
            $checks['database'] = 'UP';
        } catch (Throwable) {
            // El contrato de health comunica la dependencia caÃ­da sin exponer su detalle interno.
        }
        $status = $checks['database'] === 'UP' ? 'UP' : 'DOWN';
        http_response_code($status === 'UP' ? 200 : 503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => $status, 'service' => 'departamentos-service', 'checks' => $checks]);
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
    file_put_contents('php://stdout', json_encode([
        'timestamp' => gmdate('c'),
        'level' => 'error',
        'service' => 'departamentos-service',
        'message' => 'Unhandled error',
        'context' => ['exception' => $exception::class],
        'traceId' => $traceId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    ApiResponse::error(500, ResponseMessages::INTERNAL_ERROR, 'INTERNAL_ERROR');
}
