<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\DepartmentService;
use App\Domain\Department;
use App\Domain\DepartmentRepository;
use App\Shared\ApiException;
use PHPUnit\Framework\TestCase;

final class DepartmentServiceTest extends TestCase
{
    public function testListsAndFindsDepartments(): void
    {
        $repository = new InMemoryDepartmentRepository([new Department('IT', 'Tecnología', 'Servicios digitales')]);
        $service = new DepartmentService($repository);

        self::assertSame([['id' => 'IT', 'nombre' => 'Tecnología', 'descripcion' => 'Servicios digitales']], $service->findAll());
        self::assertSame('IT', $service->findById(' IT ')['id']);
    }

    public function testFailsWhenDepartmentDoesNotExist(): void
    {
        $service = new DepartmentService(new InMemoryDepartmentRepository());
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('no existe');
        $service->findById('MISSING');
    }

    public function testCreatesTrimmedDepartmentAndRejectsDuplicates(): void
    {
        $repository = new InMemoryDepartmentRepository();
        $service = new DepartmentService($repository);
        self::assertSame('IT', $service->create(['id' => ' IT ', 'nombre' => ' Tecnología ', 'descripcion' => ' Equipo '])['id']);

        try {
            $service->create(['id' => 'IT', 'nombre' => 'Tecnología', 'descripcion' => 'Equipo']);
            self::fail('Expected duplicate exception');
        } catch (ApiException $exception) {
            self::assertSame('DUPLICATE_DEPARTMENT_ID', $exception->errorCode);
        }
    }

    /** @dataProvider invalidInputs */
    public function testValidatesRequiredAndMaximumTextFields(array $input): void
    {
        $service = new DepartmentService(new InMemoryDepartmentRepository());
        try {
            $service->create($input);
            self::fail('Expected validation exception');
        } catch (ApiException $exception) {
            self::assertSame('VALIDATION_ERROR', $exception->errorCode);
        }
    }

    public static function invalidInputs(): array
    {
        return [
            'missing id' => [['nombre' => 'Tecnología', 'descripcion' => 'Equipo']],
            'blank name' => [['id' => 'IT', 'nombre' => ' ', 'descripcion' => 'Equipo']],
            'long description' => [['id' => 'IT', 'nombre' => 'Tecnología', 'descripcion' => str_repeat('á', 1001)]],
        ];
    }
}

final class InMemoryDepartmentRepository implements DepartmentRepository
{
    /** @var array<string, Department> */
    private array $departments = [];

    /** @param list<Department> $departments */
    public function __construct(array $departments = [])
    {
        foreach ($departments as $department) $this->departments[$department->id] = $department;
    }

    public function findAll(): array { return array_values($this->departments); }
    public function findById(string $id): ?Department { return $this->departments[$id] ?? null; }
    public function save(Department $department): Department { return $this->departments[$department->id] = $department; }
}
