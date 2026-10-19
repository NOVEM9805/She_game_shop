<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) env_value('DB_HOST', '127.0.0.1');
    $port = (string) env_value('DB_PORT', '3306');
    $name = (string) env_value('DB_NAME', 'she_game_shop');
    $user = (string) env_value('DB_USER', 'root');
    $pass = (string) env_value('DB_PASS', '');
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        $message = APP_ENV === 'local' ? htmlspecialchars($e->getMessage()) : 'Database connection failed.';
        exit('<h1>Database connection error</h1><p>' . $message . '</p><p>Import database/schema.sql and check the .env file.</p>');
    }

    return $pdo;
}
