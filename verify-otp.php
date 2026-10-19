<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$userId = (int) ($_SESSION['pending_user_id'] ?? 0);
if (!$userId) redirect('auth/login.php');
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$pendingUser = $stmt->fetch();
if (!$pendingUser) redirect('auth/register.php');
$errors = [];

if (is_post()) {
    verify_csrf();
    $otp = preg_replace('/\D/', '', (string) ($_POST['otp'] ?? ''));
    $stmt = db()->prepare("SELECT * FROM email_otps WHERE user_id = ? AND purpose='verify_email' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $record = $stmt->fetch();
    if (!$record || strtotime($record['expires_at']) < time()) {
        $errors[] = 'This verification code has expired. Request a new code.';
    } elseif ((int) $record['attempts'] >= 5) {
        $errors[] = 'Too many incorrect attempts. Request a new code.';
    } elseif (!password_verify($otp, $record['otp_hash'])) {
        db()->prepare('UPDATE email_otps SET attempts = attempts + 1 WHERE id = ?')->execute([$record['id']]);
        $errors[] = 'The verification code is incorrect.';
    } else {
        db()->beginTransaction();
        db()->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$userId]);
        db()->prepare("DELETE FROM email_otps WHERE user_id = ? AND purpose='verify_email'")->execute([$userId]);
        db()->commit();
        unset($_SESSION['pending_user_id'], $_SESSION['otp_last_sent']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        flash('success', 'Email verified. Welcome to SHE Game Shop!');
        redirect('customer/home.php');
    }
}

$masked = substr($pendingUser['email'], 0, 2) . '***@' . substr(strrchr($pendingUser['email'], '@'), 1);
render_header('Verify email');
?>
<div class="auth-wrap"><section class="auth-card">
    <p class="eyebrow">Email verification</p><h2>Enter your 6-digit code</h2>
    <p>We sent a verification code to <?= e($masked) ?>. It expires in 5 minutes.</p>
    <?php if ($errors): ?><div class="form-errors"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group"><label for="otp">Verification code</label><input id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autocomplete="one-time-code"></div>
        <div class="actions"><button class="button" type="submit">Verify email</button></div>
    </form>
    <form method="post" action="<?= url('auth/resend-otp.php') ?>" style="margin-top:10px"><?= csrf_field() ?><button class="button secondary" type="submit">Resend code</button></form>
</section></div>
<?php render_footer(); ?>
