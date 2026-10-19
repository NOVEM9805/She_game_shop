<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$userId = (int) ($_SESSION['reset_user_id'] ?? 0);
$verifiedAt = (int) ($_SESSION['reset_verified_at'] ?? 0);
if (!$userId || !$verifiedAt || time() - $verifiedAt > 600) {
    unset($_SESSION['reset_verified_at']);
    flash('warning', 'Please verify a new reset code before changing your password.');
    redirect('auth/forgot-password.php');
}

$errors = [];
if (is_post()) {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include a letter and number.';
    }
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        $stmt = db()->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND status = 'active'");
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        unset($_SESSION['reset_user_id'], $_SESSION['reset_otp_last_sent'], $_SESSION['reset_verified_at']);
        session_regenerate_id(true);
        flash('success', 'Password changed. You can now log in.');
        redirect('auth/login.php');
    }
}

render_header('New password');
?>
<div class="auth-wrap">
    <section class="auth-card">
        <p class="eyebrow">Code verified</p>
        <h2>Create a new password</h2>
        <p>Use at least 8 characters with a letter and a number.</p>
        <?php if ($errors): ?><div class="form-errors"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group"><label>New password</label><input type="password" name="password" autocomplete="new-password" required></div>
            <div class="form-group"><label>Confirm new password</label><input type="password" name="confirm_password" autocomplete="new-password" required></div>
            <button class="button full-button" type="submit">Change password</button>
        </form>
    </section>
</div>
<?php render_footer(); ?>
