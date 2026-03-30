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
        $host = self::firstEnv(['DB_HOST', 'MYSQLHOST'], '127.0.0.1');
        $port = self::firstEnv(['DB_PORT', 'MYSQLPORT'], '3306');
        $name = self::firstEnv(['DB_DATABASE', 'MYSQLDATABASE'], 'atendy');
        $user = self::firstEnv(['DB_USERNAME', 'MYSQLUSER'], 'root');
        $pass = self::firstEnv(['DB_PASSWORD', 'MYSQLPASSWORD'], '');

        // Railway fallback: parse MYSQL_PUBLIC_URL / DATABASE_URL if explicit vars are absent.
        if ($host === '127.0.0.1' || $name === 'atendy') {
            $url = self::firstEnv(['MYSQL_PUBLIC_URL', 'DATABASE_URL'], '');
            if ($url !== '') {
                $parts = parse_url($url);
                if (is_array($parts)) {
                    $host = (string) ($parts['host'] ?? $host);
                    $port = isset($parts['port']) ? (string) $parts['port'] : $port;
                    $user = isset($parts['user']) ? urldecode((string) $parts['user']) : $user;
                    $pass = isset($parts['pass']) ? urldecode((string) $parts['pass']) : $pass;
                    $path = (string) ($parts['path'] ?? '');
                    if ($path !== '') {
                        $parsedName = ltrim($path, '/');
                        if ($parsedName !== '') {
                            $name = $parsedName;
                        }
                    }
                }
            }
        }

        $dsn = $withDatabase
            ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name)
            : sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private static function firstEnv(array $keys, string $default): string
    {
        foreach ($keys as $key) {
            $value = trim((string) env($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }
}

