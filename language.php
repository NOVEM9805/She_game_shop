<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$lang = (string) ($_GET['lang'] ?? 'en');
if (in_array($lang, ['en', 'mm'], true)) {
    $_SESSION['language'] = $lang;
    setcookie('she_language', $lang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
}
$back = $_SERVER['HTTP_REFERER'] ?? url('customer/home.php');
header('Location: ' . $back);
exit;
