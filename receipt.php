<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_customer();
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT o.*,g.name game_name,oi.item_name,p.method,p.transaction_ref FROM orders o JOIN games g ON g.id=o.game_id JOIN order_items oi ON oi.order_id=o.id LEFT JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=? AND o.status='completed'");
$stmt->execute([$id, $user['id']]);
$order = $stmt->fetch();
if (!$order) { http_response_code(404); exit('Completed receipt not found.'); }
$settings = shop_settings();
$logo = !empty($settings['logo_path']) ? public_image($settings['logo_path'], 'default-logo.svg') : public_image(null, 'default-logo.svg');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($order['order_number']) ?> Receipt</title>
<style>
*{box-sizing:border-box}body{font:16px/1.5 Arial,sans-serif;color:#111;background:#f2f3f7;margin:0;padding:32px 16px}.receipt{width:min(720px,100%);margin:auto;padding:clamp(20px,5vw,42px);background:#fff;border-radius:18px;box-shadow:0 15px 45px #0002}.head{display:flex;justify-content:space-between;gap:24px;padding-bottom:20px;border-bottom:2px solid #111}.brand{display:flex;align-items:center;gap:12px}.brand img{width:58px;height:58px;object-fit:contain}.head h1{margin:0;font-size:clamp(1.5rem,5vw,2.2rem)}p{margin:5px 0;color:#555}.meta{text-align:right;overflow-wrap:anywhere}.row{display:grid;grid-template-columns:minmax(120px,.7fr) minmax(0,1.3fr);gap:18px;padding:12px 0;border-bottom:1px solid #ddd}.row strong{text-align:right;overflow-wrap:anywhere}.total{font-size:1.3rem;font-weight:bold}.print{margin-top:24px;padding:11px 18px;border:0;border-radius:9px;color:#fff;background:#6d28d9;font-weight:bold;cursor:pointer}@media(max-width:520px){body{padding:0;background:#fff}.receipt{min-height:100vh;border-radius:0;box-shadow:none}.head{flex-direction:column}.meta{text-align:left}.row{grid-template-columns:1fr;gap:3px}.row strong{text-align:left}}@media print{body{padding:0;background:#fff}.receipt{box-shadow:none}.print{display:none}}
</style></head><body><main class="receipt"><header class="head"><div class="brand"><img src="<?= e($logo) ?>" alt=""><div><h1><?= e($settings['shop_name']) ?></h1><p>Payment receipt</p></div></div><div class="meta"><strong><?= e($order['order_number']) ?></strong><p><?= e(date('d M Y', strtotime($order['updated_at']))) ?></p></div></header>
<?php foreach ([['Game',$order['game_name']],['Item',$order['item_name']],['Player ID',$order['player_id']],['In-game name',$order['player_name']],['Payment method',strtoupper((string)$order['method'])],['Reference',$order['transaction_ref']]] as [$label,$value]): ?><div class="row"><span><?= e($label) ?></span><strong><?= e((string)$value) ?></strong></div><?php endforeach; ?>
<div class="row total"><span>Total</span><strong><?= money($order['total_amount'],$order['currency']) ?></strong></div><p>Status: Completed</p><button class="print" onclick="window.print()">Print receipt</button></main></body></html>
