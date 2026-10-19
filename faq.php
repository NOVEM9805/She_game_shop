<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_customer();render_header('FAQ');
?>
<div class="section-head"><div><p class="eyebrow">Help center</p><h1>Frequently asked questions</h1></div></div>
<section class="panel"><h3>How do I place an order?</h3><p>Choose a game and item, enter the requested player information, confirm it carefully, then select a payment method.</p><h3>Does the shop need my game password?</h3><p>No. Never share your game password, email password or OTP code. Only the player information shown in checkout is requested.</p><h3>How are KPay and Wave payments checked?</h3><p>Upload the transfer screenshot and Transaction ID. An admin compares it with the shop account transaction history before approval.</p><h3>Why is my payment still reviewing?</h3><p>Manual payments remain in Payment Reviewing until an admin checks the transfer. You can follow the status in Order History.</p><h3>What if I enter the wrong Game ID?</h3><p>Contact support immediately. Completed top-ups sent to an incorrect account may not be recoverable.</p></section>
<?php render_footer();?>
