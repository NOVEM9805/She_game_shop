<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$user = require_customer();
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT o.*,g.name AS game_name,oi.item_name,p.method,p.status AS payment_status,p.transaction_ref FROM orders o JOIN games g ON g.id=o.game_id JOIN order_items oi ON oi.order_id=o.id LEFT JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=?');
$stmt->execute([$id,$user['id']]);
$order = $stmt->fetch();
if (!$order) { http_response_code(404); exit('Order not found.'); }
render_header('Order details');
?>
<div class="section-head"><div><p class="eyebrow">Order details</p><h1><?= e($order['order_number']) ?></h1></div><span class="badge <?= status_class($order['status']) ?>"><?= e(str_replace('_',' ',$order['status'])) ?></span></div>
<div class="split"><section class="panel"><h2>Player and item</h2><div class="summary-list"><div class="summary-row"><span>Game</span><strong><?= e($order['game_name']) ?></strong></div><div class="summary-row"><span>Item</span><strong><?= e($order['item_name']) ?></strong></div><div class="summary-row"><span>Player ID</span><strong><?= e($order['player_id']) ?></strong></div><?php if ($order['zone_id']): ?><div class="summary-row"><span>Zone/Tag</span><strong><?= e($order['zone_id']) ?></strong></div><?php endif; ?><?php if ($order['server_name']): ?><div class="summary-row"><span>Server</span><strong><?= e($order['server_name']) ?></strong></div><?php endif; ?><div class="summary-row"><span>In-game name</span><strong><?= e($order['player_name']) ?></strong></div></div></section><aside class="panel"><h2>Payment</h2><div class="summary-list"><div class="summary-row"><span>Total</span><strong><?= money($order['total_amount'],$order['currency']) ?></strong></div><div class="summary-row"><span>Method</span><strong><?= e($order['method'] ?: 'Not submitted') ?></strong></div><div class="summary-row"><span>Reference</span><strong><?= e($order['transaction_ref'] ?: '—') ?></strong></div></div><div class="actions"><?php if ($order['status']==='pending_payment'): ?><a class="button" href="<?= url('customer/payment.php?order='.$id) ?>">Pay now</a><?php endif; ?><?php if ($order['status']==='completed'): ?><a class="button secondary" href="<?= url('customer/receipt.php?id='.$id) ?>">Print receipt</a><?php endif; ?></div></aside></div>
<?php if ($order['rejection_reason']): ?><div class="alert danger section"><strong>Reason:</strong> <?= e($order['rejection_reason']) ?></div><?php endif; ?>
<?php render_footer(); ?>
