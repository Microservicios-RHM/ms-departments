<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DepartamentService;
use InvalidArgumentException;

class DepartamentsController
{
    public function __construct(private readonly DepartamentService $service) {}

    public function findAll(): void
    {
        $this->json($this->service->findAll(), 200);
    }

    public function findById(string $id): void
    {
        $departamento = $this->service->findById($id);

        if ($departamento === null) {
            $this->json(['mensaje' => "Departamento con id '$id' no encontrado"], 404);
            return;
        }

        $this->json($departamento, 200);
    }

    public function save(): void
    {
        $datos = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $nuevo = $this->service->save($datos);
            $this->json($nuevo, 201);
        } catch (InvalidArgumentException $e) {
            $this->json(['mensaje' => $e->getMessage()], 422);
        }
    }

    private function json(mixed $data, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
