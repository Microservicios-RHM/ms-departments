<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DepartamentoRepository;
use InvalidArgumentException;

class DepartamentService
{
    public function __construct(private readonly DepartamentoRepository $repo) {}

    public function findAll(): array
    {
        return $this->repo->findAll();
    }

    public function findById(string $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function save(array $datos): array
    {
        if (empty($datos['nombre']) || empty($datos['descripcion'])) {
            throw new InvalidArgumentException('Los campos nombre y descripcion son requeridos');
        }

        $id = $this->generarUuid();

        $this->repo->save($id, trim($datos['nombre']), trim($datos['descripcion']));

        return $this->repo->findById($id);
    }

    private function generarUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
