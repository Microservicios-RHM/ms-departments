<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class ApiResponse
{
    /** @param array<string, string> $headers */
    public static function success(int $status, string $message, mixed $data, array $headers = []): never
    {
        self::send($status, ['success' => true, 'message' => $message, 'data' => $data], $headers);
    }

    public static function error(int $status, string $message, string $code): never
    {
        self::send($status, [
            'success' => false,
            'message' => $message,
            'data' => null,
            'error' => [
                'code' => $code,
                'status' => $status,
                'path' => self::requestPath(),
                'timestamp' => gmdate('c'),
            ],
        ]);
    }

    /** @param array<string, mixed> $body */
    /** @param array<string, string> $headers */
    private static function send(int $status, array $body, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    private static function requestPath(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }
}
