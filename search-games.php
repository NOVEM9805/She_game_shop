<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_customer();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) > 100) {
    echo json_encode(['games' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$stmt = db()->prepare('SELECT id,name,image_path,description FROM games WHERE is_active=1 AND name LIKE ? ORDER BY is_hot DESC,sort_order,name LIMIT 8');
$stmt->execute([$q . '%']);
$games = [];
foreach ($stmt->fetchAll() as $game) {
    $games[] = [
        'name' => $game['name'],
        'description' => $game['description'] ?: '',
        'image' => public_image($game['image_path'], 'default-game.svg'),
        'url' => url('customer/game.php?id=' . (int)$game['id']),
    ];
}
echo json_encode(['games' => $games], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
