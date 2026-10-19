<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (!is_post()) redirect('auth/verify-otp.php');
verify_csrf();
$userId = (int) ($_SESSION['pending_user_id'] ?? 0);
if (!$userId) redirect('auth/login.php');
if ((time() - (int) ($_SESSION['otp_last_sent'] ?? 0)) < 60) {
    flash('warning', 'Please wait 60 seconds before requesting another code.');
    redirect('auth/verify-otp.php');
}
$stmt = db()->prepare('SELECT email FROM users WHERE id = ?');
$stmt->execute([$userId]);
$email = $stmt->fetchColumn();
if ($email && issue_otp($userId, (string) $email)) {
    $_SESSION['otp_last_sent'] = time();
    flash('success', 'A new verification code was sent.');
} else {
    flash('danger', 'The verification code could not be sent.');
}
redirect('auth/verify-otp.php');
