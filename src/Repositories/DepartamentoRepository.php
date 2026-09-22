<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

class DepartamentoRepository
{
    public function findAll(): array
    {
        return Connection::get()
            ->query('SELECT id, nombre, descripcion FROM departamentos ORDER BY nombre')
            ->fetchAll();
    }

    public function findById(string $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, nombre, descripcion FROM departamentos WHERE id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function save(string $id, string $nombre, string $descripcion): void
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO departamentos (id, nombre, descripcion) VALUES (?, ?, ?)'
        );
        $stmt->execute([$id, $nombre, $descripcion]);
    }
}
