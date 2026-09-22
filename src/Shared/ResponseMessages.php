<?php

declare(strict_types=1);

namespace App\Shared;

final class ResponseMessages
{
    public const SERVICE_AVAILABLE = 'Servicio disponible';
    public const DEPARTMENT_CREATED = 'Departamento registrado correctamente';
    public const DEPARTMENT_RETRIEVED = 'Departamento consultado correctamente';
    public const DEPARTMENTS_LISTED = 'Departamentos consultados correctamente';
    public const INVALID_JSON = 'El cuerpo no es JSON válido';
    public const RESOURCE_NOT_FOUND = 'Recurso no encontrado';
    public const INTERNAL_ERROR = 'Error interno del servidor';

    public static function departmentNotFound(string $id): string
    {
        return "El departamento con id {$id} no existe";
    }

    public static function duplicateDepartment(string $id): string
    {
        return "El departamento con id {$id} ya existe";
    }

    public static function required(string $field): string
    {
        return "{$field} es obligatorio";
    }

    public static function tooLong(string $field, int $maxLength): string
    {
        return "{$field} no puede superar {$maxLength} caracteres";
    }
}

