<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('she_game_shop_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!isset($_SESSION['created_at'])) {
    session_regenerate_id(true);
    $_SESSION['created_at'] = time();
}

if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > 1800) {
    unset($_SESSION['user_id'], $_SESSION['admin_id']);
    session_regenerate_id(true);
    $_SESSION['flash'][] = ['type' => 'warning', 'message' => 'Your session expired. Please log in again.'];
}
$_SESSION['last_activity'] = time();

$language = $_SESSION['language'] ?? ($_COOKIE['she_language'] ?? 'en');
$language = in_array($language, ['en', 'mm'], true) ? $language : 'en';
$translations = require dirname(__DIR__) . '/lang/' . $language . '.php';
