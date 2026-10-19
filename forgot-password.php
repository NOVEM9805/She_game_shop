<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

if (isset($_GET['change'])) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_otp_last_sent'], $_SESSION['reset_verified_at']);
}

$errors = [];
$email = trim((string) ($_POST['email'] ?? ''));
if (is_post()) {
    verify_csrf();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $stmt = db()->prepare("SELECT id, email FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && issue_otp((int) $user['id'], $user['email'], 'reset_password')) {
            $_SESSION['reset_user_id'] = (int) $user['id'];
            $_SESSION['reset_otp_last_sent'] = time();
            unset($_SESSION['reset_verified_at']);
            redirect('auth/reset-code-sent.php');
        }
        $errors[] = 'We could not send a reset code. Check the email address and mail settings, then try again.';
    }
}

render_header('Forgot password');
?>
<div class="auth-wrap">
    <section class="auth-card">
        <p class="eyebrow">Account recovery</p>
        <h2>Forgot your password?</h2>
        <p>Enter the Gmail address registered with your account. We will send a six-digit code first.</p>
        <?php if ($errors): ?><div class="form-errors"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group"><label>Email address</label><input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required></div>
            <button class="button full-button" type="submit">Send reset code</button>
        </form>
        <div class="auth-links"><a href="<?= url('auth/login.php') ?>">Back to login</a></div>
    </section>
</div>
<?php render_footer(); ?>
