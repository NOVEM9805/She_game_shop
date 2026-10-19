<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$user = require_customer();
$orderId = (int) ($_GET['order'] ?? $_POST['order_id'] ?? 0);
$stmt = db()->prepare('SELECT o.*, g.name AS game_name, oi.item_name FROM orders o JOIN games g ON g.id=o.game_id JOIN order_items oi ON oi.order_id=o.id WHERE o.id=? AND o.user_id=?');
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();
if (!$order) { http_response_code(404); exit('Order not found.'); }
if ($order['status'] !== 'pending_payment') redirect('customer/order-details.php?id=' . $orderId);
$errors = [];
$shop = shop_settings();

if (is_post()) {
    verify_csrf();
    $method = (string) ($_POST['payment_method'] ?? '');
    if (!in_array($method, ['kbzpay','wave','paypal','card'], true)) $errors[] = 'Select a payment method.';
    $payerName = trim((string) ($_POST['payer_name'] ?? ''));
    $payerPhone = trim((string) ($_POST['payer_phone'] ?? ''));
    $transactionRef = trim((string) ($_POST['transaction_ref'] ?? ''));
    $proofPath = null;

    if (in_array($method, ['kbzpay','wave'], true)) {
        if ($payerName === '') $errors[] = 'Payer name is required.';
        if (!preg_match('/^[0-9+ ]{7,20}$/', $payerPhone)) $errors[] = 'Enter a valid payer phone number.';
        if ($transactionRef === '') $errors[] = 'Transaction ID is required.';
        $check = db()->prepare('SELECT id FROM payments WHERE transaction_ref=?');
        $check->execute([$transactionRef]);
        if ($check->fetch()) $errors[] = 'This Transaction ID has already been submitted.';
        if (!isset($_FILES['payment_proof'])) {
            $errors[] = 'Payment screenshot is required.';
        } else {
            [$valid, $result] = validate_image_upload($_FILES['payment_proof']);
            if (!$valid) $errors[] = $result;
        }
    }

    if (!$errors) {
        if (in_array($method, ['kbzpay','wave'], true)) {
            $proofDirectory = BASE_PATH . '/storage/private/payment-proofs';
            if (!is_dir($proofDirectory) && !mkdir($proofDirectory, 0755, true) && !is_dir($proofDirectory)) {
                $errors[] = 'The payment screenshot folder could not be created. Check the storage folder permission.';
            }
            $filename = 'proof_' . $orderId . '_' . bin2hex(random_bytes(8)) . '.' . $result;
            $target = $proofDirectory . '/' . $filename;
            if (!$errors && !move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target)) {
                $errors[] = 'The screenshot could not be saved.';
            } elseif (!$errors) {
                $proofPath = $filename;
            }
        } else {
            $transactionRef = strtoupper($method) . '-TEST-' . bin2hex(random_bytes(5));
            $payerName = $user['username'];
        }
    }

    if (!$errors) {
        db()->beginTransaction();
        $stmt = db()->prepare("INSERT INTO payments (order_id,method,payer_name,payer_phone,transaction_ref,proof_path,amount,status) VALUES (?,?,?,?,?,?,?,'reviewing')");
        $stmt->execute([$orderId,$method,$payerName,$payerPhone ?: null,$transactionRef,$proofPath,$order['total_amount']]);
        db()->prepare("UPDATE orders SET status='payment_reviewing' WHERE id=?")->execute([$orderId]);
        notify_user((int) $user['id'], 'Payment submitted', 'Your payment information is being reviewed.', $orderId);
        db()->commit();
        redirect('customer/payment-success.php?order=' . $orderId);
    }
}

render_header('Payment');
?>
<div class="section-head"><div><p class="eyebrow">Order <?= e($order['order_number']) ?></p><h1>Choose payment</h1></div><span class="badge warning">Pending payment</span></div>
<?php if ($errors): ?><div class="form-errors"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="split"><section class="panel">
<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="payment_method" value="<?= e($_POST['payment_method'] ?? '') ?>">
    <div class="payment-grid">
        <button class="payment-card" type="button" data-payment-card="kbzpay"><div class="payment-logo">KPay</div><small>Manual verification</small></button>
        <button class="payment-card" type="button" data-payment-card="wave"><div class="payment-logo">Wave</div><small>Manual verification</small></button>
        <button class="payment-card" type="button" data-payment-card="paypal"><div class="payment-logo">PayPal</div><small>Test mode</small></button>
        <button class="payment-card" type="button" data-payment-card="card"><div class="payment-logo">VISA</div><small>Stripe test mode</small></button>
    </div>
    <div data-payment-fields="kbzpay" hidden><div class="alert info">Transfer <?= money($order['total_amount'], $order['currency']) ?> to <?= e($shop['kbzpay_name']) ?> · <?= e($shop['kbzpay_phone']) ?>, then submit the receipt below.</div></div>
    <div data-payment-fields="wave" hidden><div class="alert info">Transfer <?= money($order['total_amount'], $order['currency']) ?> to <?= e($shop['wave_name']) ?> · <?= e($shop['wave_phone']) ?>, then submit the receipt below.</div></div>
    <div data-payment-fields="paypal" hidden><div class="alert warning">PayPal is included in test mode. Add PayPal credentials before accepting real payments.</div></div>
    <div data-payment-fields="card" hidden><div class="alert warning">Card payment is included in Stripe test mode. Card details are never collected by this website.</div></div>
    <div class="section"><h3>KPay/Wave transfer details</h3><div class="form-group"><label>Payer name</label><input name="payer_name" value="<?= e($_POST['payer_name'] ?? '') ?>"></div><div class="form-group"><label>Payer phone number</label><input name="payer_phone" value="<?= e($_POST['payer_phone'] ?? '') ?>" placeholder="09xxxxxxxxx"></div><div class="form-group"><label>Transaction ID</label><input name="transaction_ref" value="<?= e($_POST['transaction_ref'] ?? '') ?>" placeholder="Enter the receipt reference number"></div><div class="form-group"><label>Payment screenshot</label><input type="file" name="payment_proof" accept="image/png,image/jpeg,image/webp"><small class="help">JPG, PNG or WebP, maximum 5 MB.</small></div></div>
    <button class="button" type="submit">Continue</button>
</form>
</section><aside class="panel"><p class="eyebrow">Order summary</p><h2><?= e($order['item_name']) ?></h2><div class="summary-list"><div class="summary-row"><span>Game</span><strong><?= e($order['game_name']) ?></strong></div><div class="summary-row"><span>In-game name</span><strong><?= e($order['player_name']) ?></strong></div><div class="summary-row"><span>Total</span><strong><?= money($order['total_amount'], $order['currency']) ?></strong></div></div></aside></div>
<?php render_footer(); ?>
