<?php
require_once __DIR__ . '/env.php';

$GLOBALS['_pdo'] = null;
$GLOBALS['_pdo_db'] = null;

function db(): PDO {
    $currentDb = $_ENV['DB_NAME'] ?? env('DB_NAME', 'restaurant');

    if ($GLOBALS['_pdo'] === null || $GLOBALS['_pdo_db'] !== $currentDb) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            $currentDb
        );
        $GLOBALS['_pdo'] = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $GLOBALS['_pdo_db'] = $currentDb;
    }
    return $GLOBALS['_pdo'];
}

function db_reset(): void {
    $GLOBALS['_pdo'] = null;
    $GLOBALS['_pdo_db'] = null;
}
