<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_admin();

$summary = db()->query("SELECT COUNT(*) total_orders, SUM(status='completed') completed_orders, COALESCE(SUM(CASE WHEN status='completed' AND currency='MMK' THEN total_amount ELSE 0 END),0) sales FROM orders")->fetch();
$customerCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$daily = db()->query("SELECT DATE(created_at) day, COUNT(*) orders, COALESCE(SUM(CASE WHEN status='completed' AND currency='MMK' THEN total_amount ELSE 0 END),0) sales FROM orders GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 30")->fetchAll();
$games = db()->query("SELECT g.name, COUNT(o.id) order_count, COALESCE(SUM(CASE WHEN o.status='completed' AND o.currency='MMK' THEN o.total_amount ELSE 0 END),0) sales FROM games g LEFT JOIN orders o ON o.game_id=g.id GROUP BY g.id,g.name ORDER BY order_count DESC,g.name")->fetchAll();

render_header('Reports', 'admin');
?>
<div class="section-head"><div><p class="eyebrow">Performance</p><h1>Sales reports</h1><p>Order, sales and game performance overview.</p></div></div>

<div class="stat-grid">
    <article class="stat-card"><span>Total orders</span><strong><?= (int) ($summary['total_orders'] ?? 0) ?></strong></article>
    <article class="stat-card"><span>Completed orders</span><strong><?= (int) ($summary['completed_orders'] ?? 0) ?></strong></article>
    <article class="stat-card"><span>Completed MMK sales</span><strong><?= money($summary['sales'] ?? 0) ?></strong></article>
    <article class="stat-card"><span>Active customers</span><strong><?= $customerCount ?></strong></article>
</div>

<div class="section report-grid">
    <section class="panel">
        <h2>Last 30 active days</h2>
        <?php if (!$daily): ?><div class="empty">No order activity yet.</div><?php else: ?>
        <div class="table-wrap"><table class="responsive-table report-table"><thead><tr><th>Date</th><th>Orders</th><th>Completed MMK sales</th></tr></thead><tbody>
        <?php foreach ($daily as $row): ?><tr><td data-label="Date"><?= e(date('d M Y', strtotime($row['day']))) ?></td><td data-label="Orders"><?= (int) $row['orders'] ?></td><td data-label="Sales"><?= money($row['sales']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
    <section class="panel">
        <h2>Game performance</h2>
        <?php if (!$games): ?><div class="empty">No games available.</div><?php else: ?>
        <div class="table-wrap"><table class="responsive-table report-table"><thead><tr><th>Game</th><th>Orders</th><th>Completed MMK sales</th></tr></thead><tbody>
        <?php foreach ($games as $row): ?><tr><td data-label="Game"><?= e($row['name']) ?></td><td data-label="Orders"><?= (int) $row['order_count'] ?></td><td data-label="Sales"><?= money($row['sales']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
</div>
<?php render_footer(); ?>
