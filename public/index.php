<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Application\DepartmentService;
use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Http\DepartmentController;
use App\Infrastructure\Http\OpenApiDocument;
use App\Infrastructure\Persistence\MySqlDepartmentRepository;
use App\Shared\ApiException;
use App\Shared\ResponseMessages;

$startedAt = microtime(true);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

register_shutdown_function(static function () use ($startedAt, $method, $path): void {
    error_log(json_encode([
        'level' => 'info',
        'method' => $method,
        'path' => $path,
        'status' => http_response_code(),
        'durationMs' => round((microtime(true) - $startedAt) * 1000, 2),
        'message' => 'HTTP request completed',
    ], JSON_UNESCAPED_SLASHES));
});

try {
    if ($method === 'GET' && $path === '/health') {
        ApiResponse::success(200, ResponseMessages::SERVICE_AVAILABLE, ['status' => 'UP']);
    }
    if ($method === 'GET' && $path === '/openapi.json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(OpenApiDocument::get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($method === 'GET' && ($path === '/docs' || $path === '/docs/')) {
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
    error_log(json_encode([
        'level' => 'error',
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    ApiResponse::error(500, ResponseMessages::INTERNAL_ERROR, 'INTERNAL_ERROR');
}
