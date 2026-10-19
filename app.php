<?php
declare(strict_types=1);

if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

load_env_file(dirname(__DIR__) . '/.env');

function env_value(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

date_default_timezone_set((string) env_value('APP_TIMEZONE', 'Asia/Yangon'));

define('BASE_PATH', dirname(__DIR__));
define('APP_NAME', (string) env_value('APP_NAME', 'SHE Game Shop'));
define('APP_ENV', (string) env_value('APP_ENV', 'production'));
define('APP_URL', rtrim((string) env_value('APP_URL', 'http://localhost/she-game-shop'), '/'));
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);
