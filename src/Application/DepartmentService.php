<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Department;
use App\Domain\DepartmentRepository;
use App\Shared\ApiException;
use App\Shared\ResponseMessages;

final readonly class DepartmentService
{
    public function __construct(private DepartmentRepository $repository) {}

    /** @return list<array{id: string, nombre: string, descripcion: string}> */
    public function findAll(): array
    {
        return array_map(
            static fn (Department $department): array => $department->toArray(),
            $this->repository->findAll(),
        );
    }

    /** @return array{id: string, nombre: string, descripcion: string} */
    public function findById(string $id): array
    {
        $department = $this->repository->findById(trim($id));
        if ($department === null) {
            throw new ApiException(404, 'DEPARTMENT_NOT_FOUND', ResponseMessages::departmentNotFound($id));
        }

        return $department->toArray();
    }

    /** @param array<string, mixed> $input
     *  @return array{id: string, nombre: string, descripcion: string}
     */
    public function create(array $input): array
    {
        $id = $this->requiredText($input, 'id', 50);
        $nombre = $this->requiredText($input, 'nombre', 100);
        $descripcion = $this->requiredText($input, 'descripcion', 1000);

        if ($this->repository->findById($id) !== null) {
            throw new ApiException(400, 'DUPLICATE_DEPARTMENT_ID', ResponseMessages::duplicateDepartment($id));
        }

        return $this->repository
            ->save(new Department($id, $nombre, $descripcion))
            ->toArray();
    }

    /** @param array<string, mixed> $input */
    private function requiredText(array $input, string $field, int $maxLength): string
    {
        $value = isset($input[$field]) && is_string($input[$field]) ? trim($input[$field]) : '';
        if ($value === '') {
            throw new ApiException(400, 'VALIDATION_ERROR', ResponseMessages::required($field));
        }
        $length = mb_strlen($value, 'UTF-8');
        if ($length > $maxLength) {
            throw new ApiException(400, 'VALIDATION_ERROR', ResponseMessages::tooLong($field, $maxLength));
        }
        return $value;
    }
}
