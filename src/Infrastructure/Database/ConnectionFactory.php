<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;

final class ConnectionFactory
{
    public static function create(): PDO
    {
        $host = self::environment('DB_HOST');
        $port = self::environment('DB_PORT', '3306');
        $database = self::environment('DB_NAME');
        $charset = self::environment('DB_CHARSET', 'utf8mb4');

        return new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
            self::environment('DB_USER'),
            self::environment('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    private static function environment(string $name, ?string $default = null): string
    {
        $value = getenv($name);
        if ($value === false || trim($value) === '') {
            if ($default !== null) return $default;
            throw new \RuntimeException("Falta la variable de entorno {$name}");
        }
        return $value;
    }
}

