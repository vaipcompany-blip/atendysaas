<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;
    private static ?PDO $serverPdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::$pdo = self::createConnection(true);

        return self::$pdo;
    }

    public static function serverConnection(): PDO
    {
        if (self::$serverPdo instanceof PDO) {
            return self::$serverPdo;
        }

        self::$serverPdo = self::createConnection(false);

        return self::$serverPdo;
    }

    public static function databaseName(): string
    {
        return (string) env('DB_DATABASE', 'atendy');
    }

    private static function createConnection(bool $withDatabase): PDO
    {
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $name = self::databaseName();
        $user = env('DB_USERNAME', 'root');
        $pass = env('DB_PASSWORD', '');

        $dsn = $withDatabase
            ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name)
            : sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}

