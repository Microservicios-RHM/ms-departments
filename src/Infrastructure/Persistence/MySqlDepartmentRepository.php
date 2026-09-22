<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Department;
use App\Domain\DepartmentRepository;
use App\Shared\ApiException;
use App\Shared\ResponseMessages;
use PDO;
use PDOException;

final readonly class MySqlDepartmentRepository implements DepartmentRepository
{
    public function __construct(private PDO $connection) {}

    public function findAll(): array
    {
        $rows = $this->connection
            ->query('SELECT id, nombre, descripcion FROM departamentos ORDER BY id')
            ->fetchAll();

        return array_map($this->hydrate(...), $rows);
    }

    public function findById(string $id): ?Department
    {
        $statement = $this->connection->prepare(
            'SELECT id, nombre, descripcion FROM departamentos WHERE id = :id',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(Department $department): Department
    {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO departamentos (id, nombre, descripcion) VALUES (:id, :nombre, :descripcion)',
            );
            $statement->execute($department->toArray());
            return $department;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new ApiException(
                    400,
                    'DUPLICATE_DEPARTMENT_ID',
                    ResponseMessages::duplicateDepartment($department->id),
                );
            }
            throw $exception;
        }
    }

    /** @param array{id: string, nombre: string, descripcion: string} $row */
    private function hydrate(array $row): Department
    {
        return new Department($row['id'], $row['nombre'], $row['descripcion']);
    }
}

