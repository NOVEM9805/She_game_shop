<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$admin = require_admin();

if (is_post()) {
    verify_csrf();
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $stmt = db()->prepare('SELECT p.*,o.user_id,o.id order_id FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.id=?');
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    if ($payment && in_array($decision, ['approve', 'reject'], true)) {
        db()->beginTransaction();
        if ($decision === 'approve') {
            db()->prepare("UPDATE payments SET status='approved',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$admin['id'], $paymentId]);
            db()->prepare("UPDATE orders SET status='processing',rejection_reason=NULL WHERE id=?")->execute([$payment['order_id']]);
            notify_user((int) $payment['user_id'], 'Payment approved', 'Your payment was approved and the order is now processing.', (int) $payment['order_id']);
        } else {
            $message = $reason ?: 'Payment could not be verified.';
            db()->prepare("UPDATE payments SET status='rejected',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$admin['id'], $paymentId]);
            db()->prepare("UPDATE orders SET status='rejected',rejection_reason=? WHERE id=?")->execute([$message, $payment['order_id']]);
            notify_user((int) $payment['user_id'], 'Payment rejected', $message, (int) $payment['order_id']);
        }
        db()->commit();
        log_admin((int) $admin['id'], 'Payment ' . $decision, 'payment', $paymentId);
        flash('success', 'Payment updated.');
    }
    redirect('admin/payments.php');
}

$payments = db()->query('SELECT p.*,o.order_number,o.currency,o.user_id,u.username,g.name game_name FROM payments p JOIN orders o ON o.id=p.order_id JOIN users u ON u.id=o.user_id JOIN games g ON g.id=o.game_id ORDER BY p.created_at DESC')->fetchAll();
render_header('Payments', 'admin');
?>
<div class="section-head"><div><p class="eyebrow">Verification queue</p><h1>Payments</h1><p>Compare each proof with your KPay or Wave transaction history.</p></div></div>
<?php if (!$payments): ?><div class="empty">No payment submissions yet.</div><?php else: ?>
<div class="table-wrap card-table-wrap">
<table class="mobile-card-table payment-admin-table">
    <thead><tr><th>Order</th><th>Customer</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th><th>Proof</th><th>Action</th></tr></thead>
    <tbody><?php foreach ($payments as $payment): ?><tr>
        <td data-label="Order" class="table-primary"><strong><?= e($payment['order_number']) ?></strong><small><?= e($payment['game_name']) ?></small></td>
        <td data-label="Customer"><strong><?= e($payment['username']) ?></strong><small><?= e($payment['payer_phone']) ?></small></td>
        <td data-label="Method"><?= e(strtoupper($payment['method'])) ?></td>
        <td data-label="Reference"><?= e($payment['transaction_ref']) ?></td>
        <td data-label="Amount"><strong><?= money($payment['amount'], $payment['currency']) ?></strong></td>
        <td data-label="Status"><span class="badge <?= status_class($payment['status']) ?>"><?= e($payment['status']) ?></span></td>
        <td data-label="Proof"><?php if ($payment['proof_path']): ?><a class="button small secondary" target="_blank" href="<?= url('admin/payment-proof.php?id=' . (int) $payment['id']) ?>">View proof</a><?php else: ?><span class="muted">Test mode</span><?php endif; ?></td>
        <td data-label="Action" class="table-actions"><?php if ($payment['status'] === 'reviewing'): ?><form class="table-control-form" method="post"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>"><input name="reason" placeholder="Rejection reason"><div class="actions"><button class="button small success" name="decision" value="approve">Approve</button><button class="button small danger" name="decision" value="reject">Reject</button></div></form><?php else: ?><span class="muted">Reviewed</span><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody>
</table>
</div>
<?php endif; ?>
<?php render_footer(); ?>
