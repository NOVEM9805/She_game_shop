<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$admin = require_admin();
$stats = [
    'Customers' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'Active games' => (int) db()->query('SELECT COUNT(*) FROM games WHERE is_active=1')->fetchColumn(),
    'Orders' => (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'Pending payments' => (int) db()->query("SELECT COUNT(*) FROM payments WHERE status='reviewing'")->fetchColumn(),
];
$sales = (float) db()->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status='completed' AND currency='MMK'")->fetchColumn();
$recent = db()->query('SELECT o.*,u.username,g.name AS game_name FROM orders o JOIN users u ON u.id=o.user_id JOIN games g ON g.id=o.game_id ORDER BY o.created_at DESC LIMIT 8')->fetchAll();
render_header('Admin dashboard', 'admin');
?>
<div class="section-head"><div><p class="eyebrow">Administration</p><h1>Dashboard</h1><p>Welcome, <?= e($admin['name']) ?>.</p></div><div class="badge success">Total sales <?= money($sales) ?></div></div>
<div class="stat-grid"><?php foreach ($stats as $label => $value): ?><article class="stat-card"><span><?= e($label) ?></span><strong><?= number_format($value) ?></strong></article><?php endforeach; ?></div>
<section class="section panel"><div class="section-head"><h2>Recent orders</h2><a href="<?= url('admin/orders.php') ?>">View all</a></div>
<?php if (!$recent): ?><div class="empty">No orders yet.</div><?php else: ?><div class="table-wrap card-table-wrap"><table class="mobile-card-table"><thead><tr><th>Order</th><th>Customer</th><th>Game</th><th>Total</th><th>Status</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td data-label="Order" class="table-primary"><a href="<?= url('admin/orders.php?id=' . (int) $row['id']) ?>"><strong><?= e($row['order_number']) ?></strong></a></td><td data-label="Customer"><?= e($row['username']) ?></td><td data-label="Game"><?= e($row['game_name']) ?></td><td data-label="Total"><?= money($row['total_amount'], $row['currency']) ?></td><td data-label="Status"><span class="badge <?= status_class($row['status']) ?>"><?= e(str_replace('_', ' ', $row['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php render_footer(); ?>
