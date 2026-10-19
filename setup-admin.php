<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$count=(int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if($count>0)redirect('admin/login.php');
$errors=[];
if(is_post()){
 verify_csrf();$name=trim((string)($_POST['name']??''));$email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');
 if(mb_strlen($name)<2)$errors[]='Enter the administrator name.';if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid email.';if(strlen($password)<10||!preg_match('/[A-Za-z]/',$password)||!preg_match('/\d/',$password))$errors[]='Password must be at least 10 characters with a letter and number.';
 if(!$errors){$stmt=db()->prepare('INSERT INTO admins(name,email,password_hash) VALUES(?,?,?)');$stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);flash('success','Administrator account created.');redirect('admin/login.php');}
}
render_header('Create administrator');
?>
<div class="auth-wrap"><section class="auth-card"><p class="eyebrow">One-time setup</p><h2>Create the first administrator</h2><p>This page is automatically disabled after the first administrator is created.</p><?php if($errors):?><div class="form-errors"><?php foreach($errors as $error):?><div><?=e($error)?></div><?php endforeach;?></div><?php endif;?><form method="post"><?=csrf_field()?><div class="form-group"><label>Name</label><input name="name" required></div><div class="form-group"><label>Email</label><input type="email" name="email" required></div><div class="form-group"><label>Password</label><input type="password" name="password" required><small class="help">At least 10 characters with a letter and number.</small></div><button class="button">Create administrator</button></form></section></div>
<?php render_footer();?>
