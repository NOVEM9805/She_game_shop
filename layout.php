<?php
declare(strict_types=1);

function render_header(string $title, string $area = 'customer'): void
{
    if (ob_get_level() === 0) {
        ob_start();
    }
    $user = customer();
    $admin = admin_user();
    $shop = shop_settings();
    $flashes = pull_flashes();
    $unread = 0;
    if ($user) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$user['id']]);
        $unread = (int) $stmt->fetchColumn();
    }
    ?><!doctype html>
<html lang="<?= e($_SESSION['language'] ?? 'en') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SHE Game Shop game item and top-up ordering platform">
    <title><?= e($title) ?> · <?= e($shop['shop_name']) ?></title>
    <link rel="icon" href="<?= e(public_image($shop['favicon_path'], 'favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') . '?v=' . filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body class="<?= e($area) ?>-area">
<header class="site-header">
    <a class="brand" href="<?= url($area === 'admin' ? 'admin/dashboard.php' : 'customer/home.php') ?>">
        <img class="brand-logo" src="<?= e(public_image($shop['logo_path'], 'default-logo.svg')) ?>" alt="<?= e($shop['shop_name']) ?> logo"><span><?= e($shop['shop_name']) ?></span>
    </a>
    <button class="menu-toggle" type="button" aria-label="Toggle menu" data-menu-toggle>☰</button>
    <nav class="main-nav" data-menu>
        <?php if ($area === 'admin' && $admin): ?>
            <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
            <a href="<?= url('admin/games.php') ?>">Games</a>
            <a href="<?= url('admin/items.php') ?>">Items</a>
            <a href="<?= url('admin/promotions.php') ?>">Promotions</a>
            <a href="<?= url('admin/orders.php') ?>">Orders</a>
            <a href="<?= url('admin/payments.php') ?>">Payments</a>
            <a href="<?= url('admin/customers.php') ?>">Customers</a>
            <a href="<?= url('admin/support.php') ?>">Support</a>
            <a href="<?= url('admin/reports.php') ?>">Reports</a>
            <a href="<?= url('admin/settings.php') ?>">Settings</a>
            <a href="<?= url('admin/logout.php') ?>">Log out</a>
        <?php elseif ($user): ?>
            <a href="<?= url('customer/home.php') ?>"><?= e(t('home')) ?></a>
            <a href="<?= url('customer/orders.php') ?>"><?= e(t('orders')) ?></a>
            <a href="<?= url('customer/notifications.php') ?>"><?= e(t('notifications')) ?><?= $unread ? ' (' . $unread . ')' : '' ?></a>
            <a href="<?= url('customer/support.php') ?>"><?= e(t('support')) ?></a>
            <button class="nav-icon-button" type="button" data-search-toggle aria-label="Search games" title="Search games">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
            </button>
            <a class="profile-nav-link" href="<?= url('customer/profile.php') ?>" aria-label="Profile settings" title="Profile settings">
                <img src="<?= e($user['profile_photo'] && is_file(BASE_PATH . '/uploads/profiles/' . $user['profile_photo']) ? url('uploads/profiles/' . $user['profile_photo']) : url('assets/images/default-avatar.svg')) ?>" alt="Profile settings">
            </a>
            <a href="<?= url('auth/logout.php') ?>"><?= e(t('logout')) ?></a>
        <?php else: ?>
            <a href="<?= url('auth/login.php') ?>"><?= e(t('login')) ?></a>
            <a class="button small" href="<?= url('auth/register.php') ?>"><?= e(t('register')) ?></a>
        <?php endif; ?>
        <?php if ($area !== 'admin'): ?>
            <span class="language-switch"><a href="<?= url('customer/language.php?lang=mm') ?>">မြန်မာ</a><a href="<?= url('customer/language.php?lang=en') ?>">EN</a></span>
        <?php endif; ?>
    </nav>
</header>
<?php if ($area !== 'admin'): ?>
<div class="nav-search-panel" data-search-panel hidden>
    <form action="<?= url('customer/home.php') ?>" method="get" role="search" data-live-search-form data-search-url="<?= url('customer/search-games.php') ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
        <div class="live-search-wrap"><input name="q" type="search" placeholder="Type a game name..." autocomplete="off" aria-label="Search games" aria-controls="live-search-results" aria-expanded="false" data-live-search-input><div class="live-search-results" id="live-search-results" data-live-search-results hidden></div></div>
        <button class="search-close" type="button" data-search-close aria-label="Close search">×</button>
    </form>
</div>
<?php endif; ?>
<main class="page-shell">
<?php foreach ($flashes as $flash): ?>
    <div class="alert <?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
<?php
}

function render_footer(): void
{
    ?>
</main>
<footer class="site-footer">
    <span>© <?= date('Y') ?> <?= e(shop_settings()['shop_name']) ?></span>
    <span>Secure game item ordering · Never share your game password or OTP.</span>
</footer>
<script src="<?= url('assets/js/main.js') . '?v=' . filemtime(BASE_PATH . '/assets/js/main.js') ?>"></script>
</body>
</html><?php
    if (ob_get_level() > 0) {
        $html = (string) ob_get_clean();
        if (($_SESSION['language'] ?? 'en') === 'mm') {
            $html = strtr($html, [
                'Welcome back' => 'ပြန်လည်ကြိုဆိုပါတယ်',
                'Create your account' => 'အကောင့်အသစ်ဖွင့်ပါ',
                'Email address' => 'အီးမေးလ်လိပ်စာ',
                'Password' => 'စကားဝှက်',
                'Confirm password' => 'စကားဝှက်အတည်ပြုရန်',
                'Forgot password?' => 'စကားဝှက်မေ့နေပါသလား။',
                'Create account' => 'အကောင့်ဖွင့်ရန်',
                'Welcome,' => 'ကြိုဆိုပါတယ်၊',
                'Your next top-up starts here.' => 'နောက်ထပ် Game Top-up ကို ဒီနေရာမှာ စတင်ပါ။',
                'Choose your game' => 'ဂိမ်းရွေးချယ်ပါ',
                'Popular items' => 'လူကြိုက်များသော Item များ',
                'Top customers' => 'ဝယ်ယူမှုအများဆုံး Customer များ',
                'View items' => 'Item များကြည့်ရန်',
                'Buy now' => 'ဝယ်ယူရန်',
                'Game items' => 'Game Item များ',
                'All games' => 'Game အားလုံး',
                'Player information' => 'Player အချက်အလက်',
                'Confirm your order' => 'Order ကို အတည်ပြုပါ',
                'In-game name' => 'ဂိမ်းအတွင်းအမည်',
                'Display currency' => 'ငွေကြေးအမျိုးအစား',
                'Review order' => 'Order ပြန်စစ်ရန်',
                'Continue to payment' => 'ငွေပေးချေရန် ဆက်သွားပါ',
                'Edit information' => 'အချက်အလက်ပြင်ရန်',
                'Order summary' => 'Order အကျဉ်းချုပ်',
                'Choose payment' => 'ငွေပေးချေမှုရွေးပါ',
                'Manual verification' => 'Admin စစ်ဆေးမည်',
                'Payment screenshot' => 'ငွေလွှဲ Screenshot',
                'Transaction ID' => 'ငွေလွှဲ Transaction ID',
                'Payer name' => 'ငွေလွှဲသူအမည်',
                'Payer phone number' => 'ငွေလွှဲသူဖုန်းနံပါတ်',
                'Order history' => 'ဝယ်ယူမှုမှတ်တမ်း',
                'Order details' => 'Order အသေးစိတ်',
                'Profile information' => 'ပရိုဖိုင်အချက်အလက်',
                'Change password' => 'စကားဝှက်ပြောင်းရန်',
                'Notifications' => 'အသိပေးချက်များ',
                'Customer support' => 'Customer အကူအညီ',
                'Create a ticket' => 'အကူအညီတောင်းရန်',
                'Your tickets' => 'သင့် Support Ticket များ',
                'Frequently asked questions' => 'မေးလေ့ရှိသောမေးခွန်းများ',
                'Back to home' => 'ပင်မစာမျက်နှာသို့',
                'View order' => 'Order ကြည့်ရန်',
                'Save profile' => 'ပရိုဖိုင်သိမ်းရန်',
                'Log in' => 'အကောင့်ဝင်ရန်',
                'Log out' => 'ထွက်ရန်',
                'Search' => 'ရှာဖွေရန်',
                'Support' => 'အကူအညီ',
                'Profile' => 'ပရိုဖိုင်',
                'Available' => 'ရရှိနိုင်သည်',
                'Unavailable' => 'မရရှိနိုင်ပါ',
                'Total' => 'စုစုပေါင်း',
                'Game' => 'ဂိမ်း',
                'Item' => 'Item',
                'Server' => 'ဆာဗာ',
                'Continue' => 'ဆက်လုပ်ရန်',
            ]);
        }
        echo $html;
    }
}
