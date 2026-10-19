<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$userId = (int) ($_SESSION['reset_user_id'] ?? 0);
if (!$userId) redirect('auth/forgot-password.php');
$stmt = db()->prepare("SELECT email FROM users WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$userId]);
$email = $stmt->fetchColumn();
if (!$email) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_otp_last_sent'], $_SESSION['reset_verified_at']);
    redirect('auth/forgot-password.php');
}

render_header('Reset code sent');
?>
<div class="auth-wrap">
    <section class="auth-card centered-auth">
        <span class="status-icon" aria-hidden="true">✉</span>
        <p class="eyebrow">Check your inbox</p>
        <h2>Reset code sent</h2>
        <p>We sent a six-digit password reset code to:</p>
        <div class="masked-email"><?= e(mask_email((string) $email)) ?></div>
        <p class="help">The code expires in 5 minutes. Also check your spam folder.</p>
        <div class="stack-actions">
            <a class="button full-button" href="<?= url('auth/verify-reset-otp.php') ?>">Continue</a>
            <form method="post" action="<?= url('auth/resend-reset-otp.php') ?>"><?= csrf_field() ?><button class="button secondary full-button" type="submit">Resend code</button></form>
            <a href="<?= url('auth/forgot-password.php?change=1') ?>">Use a different email</a>
        </div>
    </section>
</div>
<?php render_footer(); ?>
