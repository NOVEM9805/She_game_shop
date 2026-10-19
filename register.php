<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

if (customer()) redirect('customer/home.php');
$errors = [];

if (is_post()) {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (mb_strlen($username) < 2 || mb_strlen($username) > 80) $errors[] = 'Username must be between 2 and 80 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) $errors[] = 'Password must be at least 8 characters and contain a letter and a number.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) $errors[] = 'This email is already registered.';

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int) db()->lastInsertId();
        if (!issue_otp($userId, $email)) {
            $errors[] = 'Account created, but the verification email could not be sent. Check the mail settings.';
        } else {
            $_SESSION['pending_user_id'] = $userId;
            $_SESSION['otp_last_sent'] = time();
            flash('success', env_value('MAIL_MODE', 'log') === 'log' ? 'OTP created. For local testing, open storage/logs/mail.log.' : 'A verification code was sent to your email.');
            redirect('auth/verify-otp.php');
        }
    }
}

render_header('Create account');
?>
<div class="auth-wrap"><section class="auth-card">
    <?php $shop=shop_settings(); ?><div class="brand"><img class="brand-logo" src="<?=e(public_image($shop['logo_path'],'default-logo.svg'))?>" alt=""><span><?=e($shop['shop_name'])?></span></div>
    <p class="eyebrow">New customer</p><h2>Create your account</h2>
    <?php if ($errors): ?><div class="form-errors"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group"><label for="username">Username</label><input id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autocomplete="username"></div>
        <div class="form-group"><label for="email">Email address</label><input id="email" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email"></div>
        <div class="form-group"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="new-password"><small class="help">At least 8 characters with a letter and number.</small></div>
        <div class="form-group"><label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password"></div>
        <button class="button" type="submit">Create account</button>
    </form>
    <p>Already registered? <a class="text-success" href="<?= url('auth/login.php') ?>">Log in</a></p>
</section></div>
<?php render_footer(); ?>
