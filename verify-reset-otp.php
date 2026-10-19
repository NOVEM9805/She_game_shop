<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$userId = (int) ($_SESSION['reset_user_id'] ?? 0);
if (!$userId) redirect('auth/forgot-password.php');
$stmt = db()->prepare("SELECT email FROM users WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$userId]);
$email = $stmt->fetchColumn();
if (!$email) redirect('auth/forgot-password.php?change=1');

$errors = [];
if (is_post()) {
    verify_csrf();
    $otp = preg_replace('/\D/', '', (string) ($_POST['otp'] ?? ''));
    $stmt = db()->prepare("SELECT * FROM email_otps WHERE user_id = ? AND purpose = 'reset_password' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $record = $stmt->fetch();
    if (strlen($otp) !== 6) {
        $errors[] = 'Enter the six-digit reset code.';
    } elseif (!$record || strtotime($record['expires_at']) < time()) {
        $errors[] = 'The reset code has expired. Request a new code.';
    } elseif ((int) $record['attempts'] >= 5) {
        $errors[] = 'Too many incorrect attempts. Request a new code.';
    } elseif (!password_verify($otp, $record['otp_hash'])) {
        db()->prepare('UPDATE email_otps SET attempts = attempts + 1 WHERE id = ?')->execute([$record['id']]);
        $errors[] = 'The reset code is incorrect.';
    } else {
        db()->prepare("DELETE FROM email_otps WHERE user_id = ? AND purpose = 'reset_password'")->execute([$userId]);
        $_SESSION['reset_verified_at'] = time();
        redirect('auth/new-password.php');
    }
}

render_header('Verify reset code');
?>
<div class="auth-wrap">
    <section class="auth-card centered-auth">
        <p class="eyebrow">Account recovery</p>
        <h2>Enter your reset code</h2>
        <p>Code sent to <strong><?= e(mask_email((string) $email)) ?></strong></p>
        <?php if ($errors): ?><div class="form-errors"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group"><label>6-digit code</label><input class="otp-input" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus></div>
            <button class="button full-button" type="submit">Verify code</button>
        </form>
        <div class="auth-links"><a href="<?= url('auth/reset-code-sent.php') ?>">Resend or change email</a></div>
    </section>
</div>
<?php render_footer(); ?>
