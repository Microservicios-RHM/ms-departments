<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\DepartamentoController;
use App\Repositories\DepartamentoRepository;
use App\Services\DepartamentoService;

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$controller = new DepartamentoController(
    new DepartamentoService(
        new DepartamentoRepository()
    )
);

// POST /departamentos
if ($method === 'POST' && $uri === '/departamentos') {
    $controller->save();
    exit;
}

// GET /departamentos/{id}
if ($method === 'GET' && preg_match('#^/departamentos/([^/]+)$#', $uri, $matches)) {
    $controller->findById($matches[1]);
    exit;
}

// GET /departamentos
if ($method === 'GET' && $uri === '/departamentos') {
    $controller->findAll();
    exit;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['mensaje' => 'Ruta no encontrada']);
