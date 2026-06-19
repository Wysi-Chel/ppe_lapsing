<?php
declare(strict_types=1);

function initialize_runtime_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS asset_transfers (
            transfer_id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            from_department_id INT NULL,
            to_department_id INT NULL,
            from_location VARCHAR(150) DEFAULT NULL,
            to_location VARCHAR(150) DEFAULT NULL,
            transfer_date DATE NOT NULL,
            notes TEXT DEFAULT NULL,
            transferred_by_user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_asset_transfers_asset (asset_id),
            INDEX idx_asset_transfers_date (transfer_date),
            CONSTRAINT fk_asset_transfers_asset
                FOREIGN KEY (asset_id) REFERENCES assets(asset_id) ON DELETE CASCADE,
            CONSTRAINT fk_asset_transfers_from_department
                FOREIGN KEY (from_department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
            CONSTRAINT fk_asset_transfers_to_department
                FOREIGN KEY (to_department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
            CONSTRAINT fk_asset_transfers_user
                FOREIGN KEY (transferred_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
        )'
    );

    sync_asset_categories($pdo);
}

function sync_asset_categories(PDO $pdo): void
{
    if (!defined('ASSET_CATEGORY_NAMES')) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO categories (category_name)
         VALUES (:category_name)
         ON DUPLICATE KEY UPDATE category_name = VALUES(category_name)'
    );

    foreach (ASSET_CATEGORY_NAMES as $categoryName) {
        $insert->execute(['category_name' => $categoryName]);
    }

    if (!defined('ASSET_CATEGORY_ALIASES')) {
        return;
    }

    $select = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = :category_name LIMIT 1');
    $updateAssets = $pdo->prepare('UPDATE assets SET category_id = :target_id WHERE category_id = :source_id');
    $deleteCategory = $pdo->prepare('DELETE FROM categories WHERE category_id = :source_id');

    foreach (ASSET_CATEGORY_ALIASES as $sourceName => $targetName) {
        $select->execute(['category_name' => $sourceName]);
        $sourceId = (int) ($select->fetchColumn() ?: 0);

        $select->execute(['category_name' => $targetName]);
        $targetId = (int) ($select->fetchColumn() ?: 0);

        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            continue;
        }

        $updateAssets->execute([
            'target_id' => $targetId,
            'source_id' => $sourceId,
        ]);
        $deleteCategory->execute(['source_id' => $sourceId]);
    }
}

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
    initialize_runtime_schema($pdo);
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
