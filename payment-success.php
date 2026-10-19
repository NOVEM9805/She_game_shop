<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$user = require_customer();
$orderId = (int) ($_GET['order'] ?? 0);
$stmt = db()->prepare('SELECT order_number FROM orders WHERE id=? AND user_id=?');
$stmt->execute([$orderId, $user['id']]);
$orderNumber = $stmt->fetchColumn();
if (!$orderNumber) redirect('customer/orders.php');
render_header('Payment submitted');
?>
<section class="panel modal-message"><span class="brand-mark" style="margin:0 auto 20px">✓</span><p class="eyebrow">Order <?= e((string) $orderNumber) ?></p><h1>အချက်အလက် စစ်ဆေးနေပါသည်</h1><p>ငွေပေးချေမှုအချက်အလက်များကို စစ်ဆေးနေပါသည်။ အချက်အလက်မှန်ကန်ပါက ဝယ်ယူထားသော Item ကို ထည့်ပေးပါမည်။ ခဏစောင့်ပေးပါ။</p><p>Your payment information is being reviewed. If the information is correct, the purchased item will be credited to your account. Please wait.</p><div class="actions" style="justify-content:center"><a class="button" href="<?= url('customer/order-details.php?id=' . $orderId) ?>">View order</a><a class="button secondary" href="<?= url('customer/home.php') ?>">Back to home</a></div></section>
<?php render_footer(); ?>
