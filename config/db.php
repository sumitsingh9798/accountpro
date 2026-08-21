<?php
// ============================================================
// AccountPro - Database Connection
// ============================================================
declare(strict_types=1);

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'accountpro';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Check Railway logs.');
}

// ------------------------------------------------------------
// Auto-initialize schema on first connection if it hasn't been
// applied yet (fresh database with no tables).
// ------------------------------------------------------------
try {
    $pdo->query('SELECT 1 FROM users LIMIT 1');
} catch (PDOException $e) {
    $schemaFile = __DIR__ . '/../database/schema.sql';

    if (!is_file($schemaFile)) {
        error_log("Database schema initialization failed: schema file not found at {$schemaFile}");
    } else {
        try {
            $sql = file_get_contents($schemaFile);

            if ($sql === false) {
                throw new RuntimeException("Unable to read schema file at {$schemaFile}");
            }

            $statements = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($statements as $statement) {
                if ($statement === '') {
                    continue;
                }
                $pdo->exec($statement);
            }

            error_log('Database schema initialized successfully from database/schema.sql.');
        } catch (Throwable $initError) {
            error_log('Database schema initialization failed: ' . $initError->getMessage());
        }
    }
}