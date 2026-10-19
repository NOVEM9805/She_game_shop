<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$user = require_customer();
$stmt = db()->prepare('SELECT o.*, g.name AS game_name, oi.item_name FROM orders o JOIN games g ON g.id=o.game_id JOIN order_items oi ON oi.order_id=o.id WHERE o.user_id=? ORDER BY o.created_at DESC');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();
render_header('Order history');
?>
<div class="section-head"><div><p class="eyebrow">Your account</p><h1>Order history</h1></div><a class="button" href="<?= url('customer/home.php') ?>">Shop games</a></div>
<?php if (!$orders): ?><div class="empty">You have not placed an order yet.</div><?php else: ?>
<div class="table-wrap card-table-wrap"><table class="mobile-card-table"><thead><tr><th>Order</th><th>Game / Item</th><th>Total</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
<?php foreach ($orders as $order): ?><tr>
    <td data-label="Order" class="table-primary"><strong><?= e($order['order_number']) ?></strong></td>
    <td data-label="Game / Item"><strong><?= e($order['game_name']) ?></strong><small><?= e($order['item_name']) ?></small></td>
    <td data-label="Total"><strong><?= money($order['total_amount'], $order['currency']) ?></strong></td>
    <td data-label="Status"><span class="badge <?= status_class($order['status']) ?>"><?= e(str_replace('_', ' ', $order['status'])) ?></span></td>
    <td data-label="Date"><?= e(date('d M Y H:i', strtotime($order['created_at']))) ?></td>
    <td data-label="Action" class="table-actions"><a class="button small secondary" href="<?= url('customer/order-details.php?id=' . (int) $order['id']) ?>">View order</a></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php render_footer(); ?>
