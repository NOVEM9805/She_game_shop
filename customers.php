<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$admin = require_admin();

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['user_id'] ?? 0);
    $status = ($_POST['status'] ?? 'blocked') === 'active' ? 'active' : 'blocked';
    db()->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status, $id]);
    log_admin((int) $admin['id'], 'Changed customer status to ' . $status, 'user', $id);
    flash('success', 'Customer status updated.');
    redirect('admin/customers.php');
}

$customers = db()->query("SELECT u.*,COUNT(o.id) order_count,COALESCE(SUM(CASE WHEN o.status='completed' AND o.currency='MMK' THEN o.total_amount ELSE 0 END),0) total_spent FROM users u LEFT JOIN orders o ON o.user_id=u.id GROUP BY u.id ORDER BY u.created_at DESC")->fetchAll();
render_header('Customers', 'admin');
?>
<div class="section-head"><div><p class="eyebrow">Accounts</p><h1>Customers</h1><p>Manage customer access and review purchase activity.</p></div></div>
<?php if (!$customers): ?><div class="empty">No customers yet.</div><?php else: ?>
<div class="table-wrap card-table-wrap">
<table class="mobile-card-table">
    <thead><tr><th>Customer</th><th>Email</th><th>Verified</th><th>Orders</th><th>Total completed</th><th>Status</th><th>Action</th></tr></thead>
    <tbody><?php foreach ($customers as $customer): ?><tr>
        <td data-label="Customer" class="table-primary"><strong><?= e($customer['username']) ?></strong></td>
        <td data-label="Email"><?= e($customer['email']) ?></td>
        <td data-label="Verified"><?= $customer['email_verified_at'] ? 'Yes' : 'No' ?></td>
        <td data-label="Orders"><?= (int) $customer['order_count'] ?></td>
        <td data-label="Total completed"><?= money($customer['total_spent']) ?></td>
        <td data-label="Status"><span class="badge <?= status_class($customer['status']) ?>"><?= e($customer['status']) ?></span></td>
        <td data-label="Action" class="table-actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $customer['id'] ?>"><input type="hidden" name="status" value="<?= $customer['status'] === 'active' ? 'blocked' : 'active' ?>"><button class="button small <?= $customer['status'] === 'active' ? 'danger' : 'success' ?>"><?= $customer['status'] === 'active' ? 'Block' : 'Activate' ?></button></form></td>
    </tr><?php endforeach; ?></tbody>
</table>
</div>
<?php endif; ?>
<?php render_footer(); ?>
