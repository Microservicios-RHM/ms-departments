<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\DepartmentService;
use App\Shared\ApiException;
use App\Shared\ResponseMessages;
use JsonException;

final readonly class DepartmentController
{
    public function __construct(private DepartmentService $service) {}

    public function findAll(): never
    {
        ApiResponse::success(200, ResponseMessages::DEPARTMENTS_LISTED, $this->service->findAll());
    }

    public function findById(string $id): never
    {
        ApiResponse::success(200, ResponseMessages::DEPARTMENT_RETRIEVED, $this->service->findById($id));
    }

    public function create(): never
    {
        $rawBody = file_get_contents('php://input');
        try {
            $input = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            error_log(json_encode([
                'level' => 'warning',
                'message' => 'Invalid JSON request body',
                'bodyLength' => is_string($rawBody) ? strlen($rawBody) : 0,
                'reason' => $exception->getMessage(),
            ]));
            throw new ApiException(400, 'INVALID_JSON', ResponseMessages::INVALID_JSON);
        }
        if (!is_array($input)) {
            throw new ApiException(400, 'VALIDATION_ERROR', 'El cuerpo debe ser un objeto JSON');
        }
        $department = $this->service->create($input);
        ApiResponse::success(
            201,
            ResponseMessages::DEPARTMENT_CREATED,
            $department,
            ['Location' => '/departamentos/' . rawurlencode($department['id'])],
        );
    }
}
