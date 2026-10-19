<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$admin = require_admin();
$errors = [];
$settings = shop_settings();

if (is_post()) {
    verify_csrf();
    $shopName = trim((string) ($_POST['shop_name'] ?? ''));
    if ($shopName === '' || mb_strlen($shopName) > 100) $errors[] = 'Shop name is required and must not exceed 100 characters.';
    $logo = $settings['logo_path'];
    $favicon = $settings['favicon_path'];
    $newUploads = [];

    foreach (['logo' => 'logo', 'favicon' => 'favicon'] as $field => $prefix) {
        if (!empty($_FILES[$field]['name'])) {
            [$ok, $result] = upload_public_image($_FILES[$field], 'branding', $prefix);
            if (!$ok) $errors[] = ucfirst($field) . ': ' . $result;
            elseif ($field === 'logo') { $logo = $result; $newUploads[] = $result; }
            else { $favicon = $result; $newUploads[] = $result; }
        }
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO shop_settings (id,shop_name,logo_path,favicon_path,support_email,contact_phone,kbzpay_name,kbzpay_phone,wave_name,wave_phone) VALUES (1,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE shop_name=VALUES(shop_name),logo_path=VALUES(logo_path),favicon_path=VALUES(favicon_path),support_email=VALUES(support_email),contact_phone=VALUES(contact_phone),kbzpay_name=VALUES(kbzpay_name),kbzpay_phone=VALUES(kbzpay_phone),wave_name=VALUES(wave_name),wave_phone=VALUES(wave_phone)');
        $stmt->execute([$shopName,$logo,$favicon,trim((string)($_POST['support_email']??'')),trim((string)($_POST['contact_phone']??'')),trim((string)($_POST['kbzpay_name']??'')),trim((string)($_POST['kbzpay_phone']??'')),trim((string)($_POST['wave_name']??'')),trim((string)($_POST['wave_phone']??''))]);
        if ($settings['logo_path'] && $settings['logo_path'] !== $logo) delete_public_upload($settings['logo_path']);
        if ($settings['favicon_path'] && $settings['favicon_path'] !== $favicon) delete_public_upload($settings['favicon_path']);
        log_admin((int)$admin['id'], 'Updated shop settings', 'shop_settings', 1);
        flash('success', 'Shop settings saved.');
        redirect('admin/settings.php');
    }
    foreach ($newUploads as $newUpload) delete_public_upload($newUpload);
}

render_header('Shop settings', 'admin');
$v = fn(string $key) => e($_POST[$key] ?? $settings[$key] ?? '');
?>
<div class="section-head"><div><p class="eyebrow">Brand and payment</p><h1>Shop settings</h1></div></div>
<section class="panel shop-summary">
    <div class="shop-brand-preview"><img class="admin-image-preview logo-preview" src="<?= e(public_image($settings['logo_path'], 'default-logo.svg')) ?>" alt="Shop logo"><div><p class="eyebrow">Shop information</p><h2><?= e($settings['shop_name']) ?></h2></div></div>
    <dl class="information-grid">
        <?php foreach (['support_email'=>'Support email', 'contact_phone'=>'Contact phone', 'kbzpay_name'=>'KBZPay account name', 'kbzpay_phone'=>'KBZPay phone', 'wave_name'=>'Wave account name', 'wave_phone'=>'Wave phone'] as $key=>$label): ?>
        <div><dt><?= e($label) ?></dt><dd><?= e(trim((string)($settings[$key] ?? '')) !== '' ? $settings[$key] : 'Not set') ?></dd></div>
        <?php endforeach; ?>
        <div><dt>Browser favicon</dt><dd><img class="favicon-preview" src="<?= e(public_image($settings['favicon_path'], 'favicon.svg')) ?>" alt="Browser favicon"></dd></div>
    </dl>
    <button class="button" type="button" data-dialog-open="settings-edit" aria-haspopup="dialog" aria-controls="settings-edit">Edit Settings</button>
</section>

<dialog class="edit-dialog edit-dialog-wide" id="settings-edit" aria-labelledby="settings-edit-title" <?= $errors ? 'data-dialog-reopen' : '' ?>>
    <div class="dialog-heading"><h2 id="settings-edit-title">Edit Shop Settings</h2><button class="dialog-close" type="button" data-dialog-close aria-label="Close Edit Shop Settings">&times;</button></div>
    <?php if ($errors): ?><div class="form-errors" role="alert"><?php foreach($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
    <div class="split">
        <div>
            <div class="form-group"><label for="setting-shop_name">Shop name</label><input id="setting-shop_name" name="shop_name" maxlength="100" value="<?= $v('shop_name') ?>" required></div>
            <div class="form-group"><label for="setting-logo">Shop logo</label><img class="admin-image-preview logo-preview" data-logo-preview src="<?= e(public_image($settings['logo_path'], 'default-logo.svg')) ?>" alt="Logo preview"><input id="setting-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" data-preview-input="[data-logo-preview]"><small class="help">Transparent PNG or WebP is recommended.</small></div>
            <div class="form-group"><label for="setting-favicon">Browser favicon</label><img class="admin-image-preview favicon-preview" data-favicon-preview src="<?= e(public_image($settings['favicon_path'], 'favicon.svg')) ?>" alt="Favicon preview"><input id="setting-favicon" type="file" name="favicon" accept="image/png,image/jpeg,image/webp" data-preview-input="[data-favicon-preview]"></div>
            <div class="form-group"><label for="setting-support_email">Support email</label><input id="setting-support_email" type="email" name="support_email" value="<?= $v('support_email') ?>"></div>
            <div class="form-group"><label for="setting-contact_phone">Contact phone</label><input id="setting-contact_phone" name="contact_phone" value="<?= $v('contact_phone') ?>"></div>
        </div>
        <div>
            <h2>Manual payment accounts</h2>
            <div class="form-group"><label for="setting-kbzpay_name">KBZPay account name</label><input id="setting-kbzpay_name" name="kbzpay_name" value="<?= $v('kbzpay_name') ?>"></div>
            <div class="form-group"><label for="setting-kbzpay_phone">KBZPay phone</label><input id="setting-kbzpay_phone" name="kbzpay_phone" value="<?= $v('kbzpay_phone') ?>"></div>
            <div class="form-group"><label for="setting-wave_name">Wave account name</label><input id="setting-wave_name" name="wave_name" value="<?= $v('wave_name') ?>"></div>
            <div class="form-group"><label for="setting-wave_phone">Wave phone</label><input id="setting-wave_phone" name="wave_phone" value="<?= $v('wave_phone') ?>"></div>
        </div>
    </div>
    <div class="modal-actions"><button class="button" type="submit">Save settings</button><button class="button secondary" type="button" data-dialog-close>Cancel</button></div>
</form>
</dialog>
<?php render_footer(); ?>
