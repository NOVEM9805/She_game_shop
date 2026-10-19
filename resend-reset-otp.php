<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!is_post()) redirect('auth/reset-code-sent.php');
verify_csrf();
$userId = (int) ($_SESSION['reset_user_id'] ?? 0);
if (!$userId) redirect('auth/forgot-password.php');

$lastSent = (int) ($_SESSION['reset_otp_last_sent'] ?? 0);
if ($lastSent && time() - $lastSent < 60) {
    flash('warning', 'Please wait 60 seconds before requesting another code.');
    redirect('auth/reset-code-sent.php');
}

$stmt = db()->prepare("SELECT email FROM users WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$userId]);
$email = $stmt->fetchColumn();
if (!$email || !issue_otp($userId, (string) $email, 'reset_password')) {
    flash('danger', 'The reset code could not be sent. Please check the mail settings and try again.');
} else {
    $_SESSION['reset_otp_last_sent'] = time();
    unset($_SESSION['reset_verified_at']);
    flash('success', 'A new reset code has been sent.');
}
redirect('auth/reset-code-sent.php');
