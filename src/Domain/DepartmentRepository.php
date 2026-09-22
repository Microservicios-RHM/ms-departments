<?php

declare(strict_types=1);

namespace App\Domain;

interface DepartmentRepository
{
    /** @return list<Department> */
    public function findAll(): array;

    public function findById(string $id): ?Department;

    public function save(Department $department): Department;
}

