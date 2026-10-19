<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$user=require_customer();
db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$user['id']]);
$stmt=db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');$stmt->execute([$user['id']]);$rows=$stmt->fetchAll();
render_header('Notifications');
?>
<div class="section-head"><div><p class="eyebrow">Updates</p><h1>Notifications</h1></div></div>
<?php if(!$rows):?><div class="empty">No notifications yet.</div><?php else:?><div class="panel"><?php foreach($rows as $row):?><article style="padding:16px 0;border-bottom:1px solid var(--line)"><strong><?=e($row['title'])?></strong><p style="margin:4px 0"><?=e($row['message'])?></p><small class="muted"><?=e(date('d M Y H:i',strtotime($row['created_at'])))?></small><?php if($row['order_id']):?> · <a class="text-success" href="<?=url('customer/order-details.php?id='.(int)$row['order_id'])?>">View order</a><?php endif;?></article><?php endforeach;?></div><?php endif;?>
<?php render_footer();?>
