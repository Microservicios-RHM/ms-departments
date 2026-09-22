<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class ApiResponse
{
    public static function success(int $status, string $message, mixed $data): never
    {
        self::send($status, ['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error(int $status, string $message, string $code): never
    {
        self::send($status, [
            'success' => false,
            'message' => $message,
            'data' => null,
            'error' => ['code' => $code],
        ]);
    }

    /** @param array<string, mixed> $body */
    private static function send(int $status, array $body): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
}

