<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_customer();
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM games WHERE id=? AND is_active=1');
$stmt->execute([$id]);
$game = $stmt->fetch();
if (!$game) { http_response_code(404); exit('Game not found.'); }
$stmt = db()->prepare('SELECT * FROM game_items WHERE game_id=? ORDER BY sort_order, id');
$stmt->execute([$id]);
$items = $stmt->fetchAll();
render_header($game['name']);
?>
<div class="game-page-heading"><img src="<?=e(public_image($game['image_path'],'default-game.svg'))?>" alt="<?=e($game['name'])?>"><div><p class="eyebrow"><?=e(t('game_items'))?></p><h1><?=e($game['name'])?></h1><p><?=e($game['description'])?></p></div><a class="button secondary" href="<?=url('customer/home.php')?>">← <?=e(t('all_games'))?></a></div>
<?php if(!$items):?><div class="empty"><?=e(t('items_coming_soon'))?></div><?php else:?><div class="shop-item-grid">
<?php foreach($items as $item):$sale=(float)$item['sale_price_mmk'];$regular=(float)$item['price_mmk'];$discount=$sale>0&&$regular>0?(int)round((1-$sale/$regular)*100):0;?><article class="shop-item-card">
    <div class="shop-item-top"><?php if($item['is_hot']):?><span class="item-ribbon">★ <?=e(t('popular'))?></span><?php endif;?><h3><?=e($item['name'])?></h3><?php if($item['bonus_text']):?><strong><?=e($item['bonus_text'])?></strong><?php endif;?><img src="<?=e(public_image($item['image_path'],'default-item.svg'))?>" alt="<?=e($item['name'])?>"></div>
    <div class="shop-item-bottom"><?php if(!$item['is_available']):?><span class="badge danger"><?=e(t('unavailable'))?></span><?php else:?><small><?=e(t('from'))?></small><div class="item-price"><?=money($sale?:$regular)?></div><?php if($sale):?><div><span class="discount-pill">-<?=$discount?>%</span> <span class="old-price"><?=money($regular)?></span></div><?php endif;?><a class="button small" href="<?=url('customer/checkout.php?item='.(int)$item['id'])?>"><?=e(t('buy_now'))?></a><?php endif;?></div>
</article><?php endforeach;?></div><?php endif;?>
<?php render_footer(); ?>
