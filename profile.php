<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$user = require_customer();
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'profile');
    if ($action === 'profile') {
        $username = trim((string) ($_POST['username'] ?? ''));
        if (mb_strlen($username) < 2 || mb_strlen($username) > 80) $errors[] = 'Username must be between 2 and 80 characters.';
        $photo = $user['profile_photo'];
        $newPhoto = false;
        if (!$errors && !empty($_FILES['profile_photo']['name'])) {
            [$valid,$result] = validate_image_upload($_FILES['profile_photo']);
            if (!$valid) $errors[] = $result;
            else {
                $profileDirectory = BASE_PATH . '/uploads/profiles';
                if (!is_dir($profileDirectory) && !mkdir($profileDirectory, 0755, true) && !is_dir($profileDirectory)) {
                    $errors[] = 'The profile photo folder could not be created.';
                }
                $filename = 'profile_' . $user['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $result;
                if (!$errors && move_uploaded_file($_FILES['profile_photo']['tmp_name'], $profileDirectory . '/' . $filename)) {
                    $photo = $filename;
                    $newPhoto = true;
                }
                elseif (!$errors) $errors[] = 'Profile photo could not be saved.';
            }
        }
        if (!$errors) {
            db()->prepare('UPDATE users SET username=?, profile_photo=? WHERE id=?')->execute([$username,$photo,$user['id']]);
            if ($newPhoto && $user['profile_photo'] && is_file(BASE_PATH . '/uploads/profiles/' . $user['profile_photo'])) unlink(BASE_PATH . '/uploads/profiles/' . $user['profile_photo']);
            flash('success','Profile updated.'); redirect('customer/profile.php');
        }
    } else {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($current,$user['password_hash'])) $errors[]='Current password is incorrect.';
        if (strlen($new)<8 || !preg_match('/[A-Za-z]/',$new) || !preg_match('/\d/',$new)) $errors[]='New password must be at least 8 characters with a letter and number.';
        if (!$errors) { db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]); flash('success','Password changed.'); redirect('customer/profile.php'); }
    }
}
render_header('Profile');
?>
<div class="section-head"><div><p class="eyebrow">Your account</p><h1>Profile</h1></div></div>
<section class="panel account-summary">
    <img class="profile-avatar" src="<?=e($user['profile_photo']&&is_file(BASE_PATH.'/uploads/profiles/'.$user['profile_photo'])?url('uploads/profiles/'.$user['profile_photo']):url('assets/images/default-avatar.svg'))?>" alt="Profile photo">
    <h2><?= e($user['username']) ?></h2>
    <dl class="information-grid">
        <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
        <div><dt>Account status</dt><dd><?= e(ucfirst($user['status'] ?? 'active')) ?></dd></div>
        <div><dt>Member since</dt><dd><?= e($user['created_at'] ?? '—') ?></dd></div>
    </dl>
    <div class="modal-actions">
        <button class="button" type="button" data-dialog-open="profile-edit" aria-haspopup="dialog" aria-controls="profile-edit">Edit Profile</button>
        <button class="button secondary" type="button" data-dialog-open="password-edit" aria-haspopup="dialog" aria-controls="password-edit">Change password</button>
    </div>
</section>

<dialog class="edit-dialog" id="profile-edit" aria-labelledby="profile-edit-title" <?= $errors && ($_POST['action'] ?? 'profile') === 'profile' ? 'data-dialog-reopen' : '' ?>>
    <div class="dialog-heading"><h2 id="profile-edit-title">Edit Profile</h2><button class="dialog-close" type="button" data-dialog-close aria-label="Close Edit Profile">&times;</button></div>
    <?php if ($errors && ($_POST['action'] ?? 'profile') === 'profile'): ?><div class="form-errors" role="alert"><?php foreach($errors as $error): ?><div><?=e($error)?></div><?php endforeach;?></div><?php endif;?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="form-group"><label for="profile-username">Username</label><input id="profile-username" name="username" value="<?=e($_POST['username'] ?? $user['username'])?>" minlength="2" maxlength="80" required></div>
        <div class="form-group"><label for="profile-email">Email</label><input id="profile-email" value="<?=e($user['email'])?>" disabled><small class="help">Your verified email cannot be changed here.</small></div>
        <div class="form-group"><label for="profile-photo">Profile photo</label><input id="profile-photo" type="file" name="profile_photo" accept="image/png,image/jpeg,image/webp"><small class="help">JPG, PNG or WebP, maximum 5 MB.</small></div>
        <div class="modal-actions"><button class="button" type="submit">Save profile</button><button class="button secondary" type="button" data-dialog-close>Cancel</button></div>
    </form>
</dialog>

<dialog class="edit-dialog" id="password-edit" aria-labelledby="password-edit-title" <?= $errors && ($_POST['action'] ?? 'profile') !== 'profile' ? 'data-dialog-reopen' : '' ?>>
    <div class="dialog-heading"><h2 id="password-edit-title">Change password</h2><button class="dialog-close" type="button" data-dialog-close aria-label="Close Change password">&times;</button></div>
    <?php if ($errors && ($_POST['action'] ?? 'profile') !== 'profile'): ?><div class="form-errors" role="alert"><?php foreach($errors as $error): ?><div><?=e($error)?></div><?php endforeach;?></div><?php endif;?>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="form-group"><label for="current-password">Current password</label><input id="current-password" type="password" name="current_password" autocomplete="current-password" required></div>
        <div class="form-group"><label for="new-password">New password</label><input id="new-password" type="password" name="new_password" autocomplete="new-password" minlength="8" required><small class="help">At least 8 characters, including a letter and a number.</small></div>
        <div class="modal-actions"><button class="button" type="submit">Change password</button><button class="button secondary" type="button" data-dialog-close>Cancel</button></div>
    </form>
</dialog>
<?php render_footer(); ?>
