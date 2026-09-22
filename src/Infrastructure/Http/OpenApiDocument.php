<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Shared\ResponseMessages;

final class OpenApiDocument
{
    /** @return array<string, mixed> */
    public static function get(): array
    {
        $departmentExample = [
            'id' => 'IT',
            'nombre' => 'Tecnología',
            'descripcion' => 'Departamento de TI',
        ];

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Microservicio de departamentos',
                'version' => '1.0.0',
                'description' => 'API independiente para registrar y consultar departamentos.',
            ],
            'servers' => [['url' => '/', 'description' => 'Servidor actual']],
            'tags' => [
                ['name' => 'Health', 'description' => 'Estado operativo del microservicio'],
                ['name' => 'Departamentos', 'description' => 'Registro y consulta de departamentos'],
            ],
            'paths' => [
                '/health' => ['get' => [
                    'tags' => ['Health'],
                    'summary' => 'Consultar el estado del servicio',
                    'operationId' => 'getHealth',
                    'responses' => [
                        '200' => [
                            'description' => ResponseMessages::SERVICE_AVAILABLE,
                            'content' => ['application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/HealthResponse'],
                                'example' => [
                                    'success' => true,
                                    'message' => ResponseMessages::SERVICE_AVAILABLE,
                                    'data' => ['status' => 'UP'],
                                ],
                            ]],
                        ],
                    ],
                ]],
                '/departamentos' => [
                    'get' => [
                        'tags' => ['Departamentos'],
                        'summary' => 'Listar todos los departamentos',
                        'operationId' => 'listDepartments',
                        'responses' => [
                            '200' => [
                                'description' => ResponseMessages::DEPARTMENTS_LISTED,
                                'content' => ['application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/DepartmentListResponse'],
                                    'example' => [
                                        'success' => true,
                                        'message' => ResponseMessages::DEPARTMENTS_LISTED,
                                        'data' => [$departmentExample],
                                    ],
                                ]],
                            ],
                            '500' => ['$ref' => '#/components/responses/InternalServerError'],
                        ],
                    ],
                    'post' => [
                        'tags' => ['Departamentos'],
                        'summary' => 'Registrar un departamento',
                        'description' => 'El identificador del departamento debe ser único.',
                        'operationId' => 'createDepartment',
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CreateDepartmentRequest'],
                                'example' => $departmentExample,
                            ]],
                        ],
                        'responses' => [
                            '201' => [
                                'description' => ResponseMessages::DEPARTMENT_CREATED,
                                'content' => ['application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/DepartmentResponse'],
                                    'example' => [
                                        'success' => true,
                                        'message' => ResponseMessages::DEPARTMENT_CREATED,
                                        'data' => $departmentExample,
                                    ],
                                ]],
                            ],
                            '400' => [
                                'description' => 'Cuerpo JSON inválido, campos inválidos o identificador duplicado.',
                                'content' => ['application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                                    'example' => [
                                        'success' => false,
                                        'message' => ResponseMessages::duplicateDepartment('IT'),
                                        'data' => null,
                                        'error' => ['code' => 'DUPLICATE_DEPARTMENT_ID'],
                                    ],
                                ]],
                            ],
                            '500' => ['$ref' => '#/components/responses/InternalServerError'],
                        ],
                    ],
                ],
                '/departamentos/{id}' => ['get' => [
                    'tags' => ['Departamentos'],
                    'summary' => 'Consultar un departamento por identificador',
                    'operationId' => 'getDepartmentById',
                    'parameters' => [[
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'Identificador único del departamento.',
                        'schema' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50],
                        'example' => 'IT',
                    ]],
                    'responses' => [
                        '200' => [
                            'description' => ResponseMessages::DEPARTMENT_RETRIEVED,
                            'content' => ['application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/DepartmentResponse'],
                                'example' => [
                                    'success' => true,
                                    'message' => ResponseMessages::DEPARTMENT_RETRIEVED,
                                    'data' => $departmentExample,
                                ],
                            ]],
                        ],
                        '404' => [
                            'description' => 'El departamento solicitado no existe.',
                            'content' => ['application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                                'example' => [
                                    'success' => false,
                                    'message' => ResponseMessages::departmentNotFound('NO-EXISTE'),
                                    'data' => null,
                                    'error' => ['code' => 'DEPARTMENT_NOT_FOUND'],
                                ],
                            ]],
                        ],
                        '500' => ['$ref' => '#/components/responses/InternalServerError'],
                    ],
                ]],
            ],
            'components' => [
                'schemas' => [
                    'Department' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['id', 'nombre', 'descripcion'],
                        'properties' => [
                            'id' => ['type' => 'string', 'example' => 'IT', 'maxLength' => 50],
                            'nombre' => ['type' => 'string', 'example' => 'Tecnología', 'maxLength' => 100],
                            'descripcion' => ['type' => 'string', 'example' => 'Departamento de TI', 'maxLength' => 1000],
                        ],
                    ],
                    'CreateDepartmentRequest' => ['$ref' => '#/components/schemas/Department'],
                    'HealthResponse' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['success', 'message', 'data'],
                        'properties' => [
                            'success' => ['type' => 'boolean', 'const' => true],
                            'message' => ['type' => 'string'],
                            'data' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['status'],
                                'properties' => ['status' => ['type' => 'string', 'enum' => ['UP']]],
                            ],
                        ],
                    ],
                    'DepartmentResponse' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['success', 'message', 'data'],
                        'properties' => [
                            'success' => ['type' => 'boolean', 'const' => true],
                            'message' => ['type' => 'string'],
                            'data' => ['$ref' => '#/components/schemas/Department'],
                        ],
                    ],
                    'DepartmentListResponse' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['success', 'message', 'data'],
                        'properties' => [
                            'success' => ['type' => 'boolean', 'const' => true],
                            'message' => ['type' => 'string'],
                            'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Department']],
                        ],
                    ],
                    'ErrorResponse' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['success', 'message', 'data', 'error'],
                        'properties' => [
                            'success' => ['type' => 'boolean', 'const' => false],
                            'message' => ['type' => 'string'],
                            'data' => ['type' => 'null'],
                            'error' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['code'],
                                'properties' => ['code' => ['type' => 'string']],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    'InternalServerError' => [
                        'description' => 'Error no controlado en el servidor.',
                        'content' => ['application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                            'example' => [
                                'success' => false,
                                'message' => ResponseMessages::INTERNAL_ERROR,
                                'data' => null,
                                'error' => ['code' => 'INTERNAL_ERROR'],
                            ],
                        ]],
                    ],
                ],
            ],
        ];
    }

    public static function swaggerUi(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Departamentos API</title><link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css"></head>
<body><div id="swagger-ui"></div><script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>SwaggerUIBundle({url: '/openapi.json', dom_id: '#swagger-ui'});</script></body></html>
HTML;
    }
}
