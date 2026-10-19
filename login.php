<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';

if (customer()) redirect('customer/home.php');
$errors = [];
$blockedUntil = (int) ($_SESSION['login_blocked_until'] ?? 0);

if (is_post()) {
    verify_csrf();
    if ($blockedUntil > time()) {
        $errors[] = 'Too many login attempts. Try again in a few minutes.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= 5) $_SESSION['login_blocked_until'] = time() + 300;
            $errors[] = 'Email or password is incorrect.';
        } elseif (!$user['email_verified_at']) {
            $_SESSION['pending_user_id'] = (int) $user['id'];
            flash('warning', 'Verify your email before logging in.');
            redirect('auth/verify-otp.php');
        } elseif ($user['status'] !== 'active') {
            $errors[] = 'This account has been blocked. Contact support.';
        } else {
            unset($_SESSION['login_attempts'], $_SESSION['login_blocked_until']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            redirect('customer/home.php');
        }
    }
}

render_header('Log in');
?>
<div class="auth-wrap"><section class="auth-card">
    <?php $shop=shop_settings(); ?><div class="brand"><img class="brand-logo" src="<?=e(public_image($shop['logo_path'],'default-logo.svg'))?>" alt=""><span><?=e($shop['shop_name'])?></span></div>
    <p class="eyebrow">Customer account</p><h2>Welcome back</h2>
    <?php if ($errors): ?><div class="form-errors"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group"><label for="email">Email address</label><input id="email" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email"></div>
        <div class="form-group"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
        <div class="actions"><button class="button" type="submit">Log in</button><a href="<?= url('auth/forgot-password.php') ?>">Forgot password?</a></div>
    </form>
    <p>New to the shop? <a class="text-success" href="<?= url('auth/register.php') ?>">Create an account</a></p>
</section></div>
<?php render_footer(); ?>
