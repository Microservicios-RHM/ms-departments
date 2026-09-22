<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Department
{
    public function __construct(
        public string $id,
        public string $nombre,
        public string $descripcion,
    ) {}

    /** @return array{id: string, nombre: string, descripcion: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
        ];
    }
}

