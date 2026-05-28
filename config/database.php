<?php
declare(strict_types=1);

$dbConfig = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'name' => getenv('DB_NAME') ?: 'ppe_ai_system',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];

$pdo = null;
$dbConnectionError = null;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['name'],
        $dbConfig['charset']
    );

    $pdo = new PDO(
        $dsn,
        $dbConfig['user'],
        $dbConfig['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $exception) {
    $dbConnectionError = $exception->getMessage();
}

function db(): PDO
{
    global $pdo, $dbConnectionError;

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection unavailable: ' . (string) $dbConnectionError);
    }

    return $pdo;
}

function db_error(): ?string
{
    global $dbConnectionError;

    return $dbConnectionError;
}
