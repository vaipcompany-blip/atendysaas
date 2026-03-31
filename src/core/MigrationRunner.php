<?php

declare(strict_types=1);

final class MigrationRunner
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    public function run(bool $verbose = true): array
    {
        $executed = [];
        $skipped = [];

        try {
            $db = Database::connection();
        } catch (Throwable $e) {
            $this->ensureDatabaseExists();
            $db = Database::connection();
        }
        $this->ensureMigrationsTable($db);

        foreach ($this->discoverMigrationFiles() as $filePath) {
            $fileName = basename($filePath);
            $checksum = hash_file('sha256', $filePath) ?: '';
            $applied = $this->findAppliedMigration($db, $fileName);

            if ($applied !== null) {
                if ((string) ($applied['checksum'] ?? '') !== $checksum) {
                    throw new RuntimeException('Migration alterada após aplicação: ' . $fileName);
                }

                $skipped[] = $fileName;
                if ($verbose) {
                    echo '[skip] ' . $fileName . PHP_EOL;
                }
                continue;
            }

            $this->applyMigration($db, $filePath);
            $this->recordMigration($db, $fileName, $checksum);
            $executed[] = $fileName;

            if ($verbose) {
                echo '[ok] ' . $fileName . PHP_EOL;
            }
        }

        return [
            'executed' => $executed,
            'skipped' => $skipped,
        ];
    }

    private function ensureDatabaseExists(): void
    {
        $serverDb = Database::serverConnection();
        $databaseFile = $this->basePath . DIRECTORY_SEPARATOR . 'db_scripts' . DIRECTORY_SEPARATOR . '01_create_database.sql';
        if (!is_file($databaseFile)) {
            throw new RuntimeException('Arquivo de bootstrap do banco não encontrado: ' . $databaseFile);
        }

        foreach ($this->splitSqlStatements((string) file_get_contents($databaseFile)) as $statement) {
            $trimmed = trim($statement);
            if ($trimmed === '') {
                continue;
            }

            try {
                $serverDb->exec($statement);
            } catch (Throwable $e) {
                if ($this->shouldIgnoreBootstrapFailure($trimmed, $e)) {
                    continue;
                }

                throw $e;
            }
        }
    }

    private function shouldIgnoreBootstrapFailure(string $statement, Throwable $e): bool
    {
        $normalized = strtoupper(ltrim($statement));
        $isDbBootstrap = str_starts_with($normalized, 'CREATE DATABASE') || str_starts_with($normalized, 'USE ');
        if (!$isDbBootstrap) {
            return false;
        }

        $message = strtoupper($e->getMessage());
        $knownManagedDbErrors = [
            'SQLSTATE[42000]',
            'SQLSTATE[1044]',
            'SQLSTATE[1049]',
            'SQLSTATE[1227]',
            'ACCESS DENIED',
            'SYNTAX ERROR',
            'CREATE DATABASE',
        ];

        foreach ($knownManagedDbErrors as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function ensureMigrationsTable(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                file_name VARCHAR(190) NOT NULL,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_schema_migrations_file (file_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function discoverMigrationFiles(): array
    {
        $tablesPath = $this->basePath . DIRECTORY_SEPARATOR . 'tables';
        $files = glob($tablesPath . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false) {
            return [];
        }

        sort($files, SORT_NATURAL);
        return $files;
    }

    private function findAppliedMigration(PDO $db, string $fileName): ?array
    {
        $stmt = $db->prepare('SELECT file_name, checksum, applied_at FROM schema_migrations WHERE file_name = :file_name LIMIT 1');
        $stmt->execute(['file_name' => $fileName]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function applyMigration(PDO $db, string $filePath): void
    {
        $sql = file_get_contents($filePath);
        if ($sql === false) {
            throw new RuntimeException('Não foi possível ler migration: ' . $filePath);
        }

        $statements = $this->splitSqlStatements($sql);
        try {
            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if ($trimmed === '') {
                    continue;
                }

                $compatibleStatement = $this->normalizeSqlForCompatibility($trimmed);
                if ($compatibleStatement === '') {
                    continue;
                }

                try {
                    $db->exec($compatibleStatement);
                } catch (Throwable $e) {
                    if ($this->isIgnorableMigrationError($compatibleStatement, $e)) {
                        continue;
                    }

                    throw $e;
                }
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Falha ao aplicar migration ' . basename($filePath) . ': ' . $e->getMessage(), 0, $e);
        }
    }

    private function normalizeSqlForCompatibility(string $statement): string
    {
        $normalized = $statement;

        // Managed MySQL variants may not support these clauses on ALTER statements.
        $normalized = preg_replace('/\bADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\b/i', 'ADD COLUMN', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bADD\s+UNIQUE\s+KEY\s+IF\s+NOT\s+EXISTS\b/i', 'ADD UNIQUE KEY', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bADD\s+INDEX\s+IF\s+NOT\s+EXISTS\b/i', 'ADD INDEX', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function isIgnorableMigrationError(string $statement, Throwable $e): bool
    {
        $message = strtoupper($e->getMessage());
        $normalizedStatement = strtoupper($statement);

        $isAlterAdd = str_contains($normalizedStatement, 'ALTER TABLE') && str_contains($normalizedStatement, ' ADD ');
        if (!$isAlterAdd) {
            return false;
        }

        $ignorableNeedles = [
            'SQLSTATE[42S21]', // Column already exists
            'SQLSTATE[42000]', // Generic syntax/constraint errors (used with duplicate key name too)
            'SQLSTATE[23000]', // Integrity constraint violation
            'DUPLICATE COLUMN NAME',
            'DUPLICATE KEY NAME',
            'ALREADY EXISTS',
            '1060', // ER_DUP_FIELDNAME
            '1061', // ER_DUP_KEYNAME
            '1831', // Duplicate index/constraint variants on some engines
        ];

        foreach ($ignorableNeedles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function recordMigration(PDO $db, string $fileName, string $checksum): void
    {
        $stmt = $db->prepare(
            'INSERT INTO schema_migrations (file_name, checksum, applied_at)
             VALUES (:file_name, :checksum, NOW())'
        );
        $stmt->execute([
            'file_name' => $fileName,
            'checksum' => $checksum,
        ]);
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';
            $prev = $index > 0 ? $sql[$index - 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $buffer .= $char;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $index++;
                }
                continue;
            }

            if (!$inSingleQuote && !$inDoubleQuote) {
                if ($char === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
                    $inLineComment = true;
                    $index++;
                    continue;
                }

                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $index++;
                    continue;
                }
            }

            if ($char === "'" && !$inDoubleQuote && $prev !== '\\') {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote && $prev !== '\\') {
                $inDoubleQuote = !$inDoubleQuote;
            }

            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }
}

