<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$user=require_customer();$errors=[];
if(is_post()){
 verify_csrf();$subject=trim((string)($_POST['subject']??''));$category=(string)($_POST['category']??'Other');$message=trim((string)($_POST['message']??''));$orderId=(int)($_POST['order_id']??0)?:null;
 if($subject===''||mb_strlen($subject)>150)$errors[]='Enter a subject of 150 characters or fewer.';if($message===''||mb_strlen($message)>3000)$errors[]='Enter a message of 3000 characters or fewer.';
 if(!$errors){$stmt=db()->prepare('INSERT INTO support_tickets(user_id,order_id,subject,category,message) VALUES(?,?,?,?,?)');$stmt->execute([$user['id'],$orderId,$subject,$category,$message]);flash('success','Support ticket submitted.');redirect('customer/support.php');}
}
$stmt=db()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');$stmt->execute([$user['id']]);$orders=$stmt->fetchAll();
$stmt=db()->prepare("SELECT t.*,(SELECT r.message FROM support_replies r WHERE r.ticket_id=t.id ORDER BY r.id DESC LIMIT 1) latest_reply FROM support_tickets t WHERE t.user_id=? ORDER BY t.created_at DESC");$stmt->execute([$user['id']]);$tickets=$stmt->fetchAll();
render_header('Support');
?>
<div class="section-head"><div><p class="eyebrow">Help center</p><h1>Customer support</h1></div><a href="<?=url('customer/faq.php')?>">Read FAQ</a></div>
<?php if($errors):?><div class="form-errors"><?php foreach($errors as $error):?><div><?=e($error)?></div><?php endforeach;?></div><?php endif;?>
<div class="split"><section class="panel"><h2>Create a ticket</h2><form method="post"><?=csrf_field()?><div class="form-group"><label>Related order (optional)</label><select name="order_id"><option value="">No order selected</option><?php foreach($orders as $order):?><option value="<?=$order['id']?>"><?=e($order['order_number'])?></option><?php endforeach;?></select></div><div class="form-group"><label>Problem type</label><select name="category"><option>Payment</option><option>Order</option><option>Game ID</option><option>Account</option><option>Other</option></select></div><div class="form-group"><label>Subject</label><input name="subject" required></div><div class="form-group"><label>Message</label><textarea name="message" required></textarea></div><button class="button">Submit ticket</button></form></section><section class="panel"><h2>Your tickets</h2><?php if(!$tickets):?><div class="empty">No support tickets.</div><?php else:?><?php foreach($tickets as $ticket):?><article style="padding:14px 0;border-bottom:1px solid var(--line)"><span class="badge <?=status_class($ticket['status'])?>"><?=e($ticket['status'])?></span><h3 style="margin:8px 0"><?=e($ticket['subject'])?></h3><p><?=e($ticket['message'])?></p><?php if($ticket['latest_reply']):?><div class="alert info"><strong>Admin reply:</strong><br><?=nl2br(e($ticket['latest_reply']))?></div><?php endif;?><small class="muted"><?=e(date('d M Y H:i',strtotime($ticket['created_at'])))?></small></article><?php endforeach;?><?php endif;?></section></div>
<?php render_footer();?>
