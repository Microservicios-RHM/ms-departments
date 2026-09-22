<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class OpenApiDocument
{
    /** @return array<string, mixed> */
    public static function get(): array
    {
        $department = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'nombre', 'descripcion'],
            'properties' => [
                'id' => ['type' => 'string', 'example' => 'IT', 'maxLength' => 50],
                'nombre' => ['type' => 'string', 'example' => 'Tecnología', 'maxLength' => 100],
                'descripcion' => ['type' => 'string', 'example' => 'Departamento de TI', 'maxLength' => 1000],
            ],
        ];
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Microservicio de departamentos',
                'version' => '1.0.0',
                'description' => 'API independiente para registrar y consultar departamentos.',
            ],
            'servers' => [['url' => '/', 'description' => 'Servidor actual']],
            'paths' => [
                '/health' => ['get' => [
                    'summary' => 'Consultar el estado del servicio',
                    'responses' => ['200' => ['description' => 'Servicio disponible']],
                ]],
                '/departamentos' => [
                    'get' => [
                        'summary' => 'Listar departamentos',
                        'responses' => ['200' => ['description' => 'Listado obtenido'], '500' => ['description' => 'Error interno']],
                    ],
                    'post' => [
                        'summary' => 'Registrar un departamento',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/Department'],
                        ]]],
                        'responses' => [
                            '201' => ['description' => 'Departamento creado'],
                            '400' => ['description' => 'Datos inválidos o identificador duplicado'],
                            '500' => ['description' => 'Error interno'],
                        ],
                    ],
                ],
                '/departamentos/{id}' => ['get' => [
                    'summary' => 'Consultar un departamento por identificador',
                    'parameters' => [[
                        'name' => 'id', 'in' => 'path', 'required' => true,
                        'schema' => ['type' => 'string'], 'example' => 'IT',
                    ]],
                    'responses' => [
                        '200' => ['description' => 'Departamento encontrado'],
                        '404' => ['description' => 'Departamento no encontrado'],
                        '500' => ['description' => 'Error interno'],
                    ],
                ]],
            ],
            'components' => ['schemas' => ['Department' => $department]],
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

