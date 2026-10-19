<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$user = require_customer();
$itemId = (int) ($_GET['item'] ?? $_POST['item_id'] ?? 0);
$stmt = db()->prepare('SELECT i.*, g.name AS game_name, g.id_label, g.id_placeholder, g.id_help, g.requires_zone, g.zone_label, g.zone_placeholder, g.requires_server, g.server_options, g.requires_region, g.region_options FROM game_items i JOIN games g ON g.id=i.game_id WHERE i.id=? AND i.is_available=1 AND g.is_active=1');
$stmt->execute([$itemId]);
$item = $stmt->fetch();
if (!$item) { http_response_code(404); exit('Item is not available.'); }
$errors = [];
$showConfirm = false;

if (is_post()) {
    verify_csrf();
    $playerId = trim((string) ($_POST['player_id'] ?? ''));
    $playerName = trim((string) ($_POST['player_name'] ?? ''));
    $zoneId = trim((string) ($_POST['zone_id'] ?? ''));
    $serverName = trim((string) ($_POST['server_name'] ?? ''));
    $region = trim((string) ($_POST['account_region'] ?? ''));
    if ($playerId === '' || mb_strlen($playerId) > 120) $errors[] = $item['id_label'] . ' is required.';
    if ($item['game_name'] === 'Clash of Clans' && !str_starts_with($playerId, '#')) $errors[] = 'Clash of Clans Player Tag must begin with #.';
    if ($playerName === '' || mb_strlen($playerName) > 120) $errors[] = 'In-game name is required.';
    if ($item['requires_zone'] && $zoneId === '') $errors[] = ($item['zone_label'] ?: 'Zone ID') . ' is required.';
    if ($item['requires_server'] && $serverName === '') $errors[] = 'Select the game server.';
    if ($item['requires_region'] && $region === '') $errors[] = 'Select the account region.';
    if (!isset($_POST['confirmed'])) $errors[] = 'Confirm that the player information is correct.';
    if (!$errors && isset($_POST['create_order'])) {
        $currency = ($_POST['currency'] ?? 'MMK') === 'GBP' ? 'GBP' : 'MMK';
        $price = $currency === 'GBP' ? ($item['sale_price_gbp'] ?: $item['price_gbp']) : ($item['sale_price_mmk'] ?: $item['price_mmk']);
        db()->beginTransaction();
        $stmt = db()->prepare('INSERT INTO orders (order_number,user_id,game_id,player_id,zone_id,server_name,account_region,player_name,currency,total_amount) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([order_number(),$user['id'],$item['game_id'],$playerId,$zoneId ?: null,$serverName ?: null,$region ?: null,$playerName,$currency,$price]);
        $orderId = (int) db()->lastInsertId();
        db()->prepare('INSERT INTO order_items (order_id,item_id,item_name,quantity,unit_price,subtotal) VALUES (?,?,?,?,?,?)')->execute([$orderId,$item['id'],$item['name'],1,$price,$price]);
        db()->commit();
        redirect('customer/payment.php?order=' . $orderId);
    }
    $showConfirm = !$errors;
}

$serverOptions = array_filter(array_map('trim', explode(',', (string) $item['server_options'])));
$regionOptions = array_filter(array_map('trim', explode(',', (string) $item['region_options'])));
render_header('Checkout');
?>
<div class="section-head"><div><p class="eyebrow">Secure checkout</p><h1><?= $showConfirm ? 'Confirm your order' : 'Player information' ?></h1></div><a href="<?= url('customer/game.php?id=' . (int) $item['game_id']) ?>">← Back to items</a></div>
<?php if ($errors): ?><div class="form-errors"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="split"><section class="panel">
<?php if (!$showConfirm): ?>
<form method="post">
    <?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
    <div class="form-group"><label><?= e($item['id_label']) ?></label><input name="player_id" value="<?= e($_POST['player_id'] ?? '') ?>" placeholder="<?= e($item['id_placeholder']) ?>" required><small class="help"><?= e($item['id_help']) ?></small></div>
    <?php if ($item['requires_zone']): ?><div class="form-group"><label><?= e($item['zone_label'] ?: 'Zone ID') ?></label><input name="zone_id" value="<?= e($_POST['zone_id'] ?? '') ?>" placeholder="<?= e($item['zone_placeholder']) ?>" required></div><?php endif; ?>
    <?php if ($item['requires_server']): ?><div class="form-group"><label>Server</label><select name="server_name" required><option value="">Select server</option><?php foreach ($serverOptions as $option): ?><option <?= ($_POST['server_name'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <?php if ($item['requires_region']): ?><div class="form-group"><label>Account region</label><select name="account_region" required><option value="">Select region</option><?php foreach ($regionOptions as $option): ?><option <?= ($_POST['account_region'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="form-group"><label>In-game name</label><input name="player_name" value="<?= e($_POST['player_name'] ?? '') ?>" placeholder="Example: SHE Queen" required><small class="help">Entered manually for order checking. It is not verified automatically.</small></div>
    <div class="form-group"><label>Display currency</label><select name="currency"><option value="MMK">Myanmar Kyat (MMK)</option><option value="GBP" <?= ($_POST['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>British Pound (GBP)</option></select></div>
    <label class="checkbox"><input type="checkbox" name="confirmed" value="1" required><span>I confirm that my Game ID, Server/Zone and In-game Name are correct. Items sent to an incorrect account may not be recoverable.</span></label><br>
    <button class="button" type="submit">Review order</button>
</form>
<?php else: ?>
<div class="summary-list"><div class="summary-row"><span>Game</span><strong><?= e($item['game_name']) ?></strong></div><div class="summary-row"><span>Item</span><strong><?= e($item['name']) ?></strong></div><div class="summary-row"><span><?= e($item['id_label']) ?></span><strong><?= e($_POST['player_id']) ?></strong></div><?php if ($item['requires_zone']): ?><div class="summary-row"><span><?= e($item['zone_label']) ?></span><strong><?= e($_POST['zone_id']) ?></strong></div><?php endif; ?><?php if ($item['requires_server']): ?><div class="summary-row"><span>Server</span><strong><?= e($_POST['server_name']) ?></strong></div><?php endif; ?><?php if ($item['requires_region']): ?><div class="summary-row"><span>Region</span><strong><?= e($_POST['account_region']) ?></strong></div><?php endif; ?><div class="summary-row"><span>In-game name</span><strong><?= e($_POST['player_name']) ?></strong></div></div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><?php foreach (['player_id','player_name','zone_id','server_name','account_region','currency'] as $field): ?><input type="hidden" name="<?= $field ?>" value="<?= e($_POST[$field] ?? '') ?>"><?php endforeach; ?><input type="hidden" name="confirmed" value="1"><div class="actions"><button class="button" name="create_order" value="1">Continue to payment</button><a class="button secondary" href="<?= url('customer/checkout.php?item=' . (int) $item['id']) ?>">Edit information</a></div></form>
<?php endif; ?>
</section><aside class="panel"><p class="eyebrow">Order summary</p><h2><?= e($item['name']) ?></h2><p><?= e($item['game_name']) ?></p><div class="price"><?= money($item['sale_price_mmk'] ?: $item['price_mmk']) ?></div><p>GBP price: <?= money($item['sale_price_gbp'] ?: $item['price_gbp'], 'GBP') ?></p><div class="alert warning">Never enter your game password, email password or personal OTP code.</div></aside></div>
<?php render_footer(); ?>
