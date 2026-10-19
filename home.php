<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_customer();
$q = trim((string)($_GET['q'] ?? ''));
$promotions = db()->query("SELECT p.*,g.name game_name FROM promotions p LEFT JOIN games g ON g.id=p.game_id WHERE p.is_active=1 AND NOW() BETWEEN p.starts_at AND p.ends_at ORDER BY p.sort_order,p.id DESC")->fetchAll();
$hotGames = [];
$otherGames = [];
$searchGames = [];
if ($q !== '') {
    $stmt = db()->prepare('SELECT * FROM games WHERE is_active=1 AND name LIKE ? ORDER BY is_hot DESC,sort_order,name');
    $stmt->execute([$q . '%']);
    $searchGames = $stmt->fetchAll();
} else {
    $hotGames = db()->query('SELECT * FROM games WHERE is_active=1 AND is_hot=1 ORDER BY sort_order,name')->fetchAll();
    $otherGames = db()->query('SELECT * FROM games WHERE is_active=1 AND is_hot=0 ORDER BY sort_order,name')->fetchAll();
}
$ranking=db()->query("SELECT u.username,SUM(o.total_amount) total FROM orders o JOIN users u ON u.id=o.user_id WHERE o.status='completed' AND o.currency='MMK' GROUP BY u.id,u.username ORDER BY total DESC LIMIT 5")->fetchAll();
render_header('Home');
$renderGames = static function(array $games, bool $hot = false): void { ?>
    <div class="game-cover-grid"><?php foreach($games as $game):?><a class="game-cover-card" href="<?=url('customer/game.php?id='.(int)$game['id'])?>"><div class="game-cover-media"><img src="<?=e(public_image($game['image_path'],'default-game.svg'))?>" alt="<?=e($game['name'])?>"><?php if($hot):?><span class="hot-badge">HOT</span><?php endif;?></div><div class="game-cover-title"><h3><?=e($game['name'])?></h3><p><?=e($game['description'])?></p></div></a><?php endforeach;?></div>
<?php };
?>
<?php if($promotions):?>
<section class="promotion-slider" data-slider aria-label="Current promotions">
    <div class="promotion-track"><?php foreach($promotions as $index=>$promotion):?><a class="promotion-slide <?=$index===0?'active':''?>" data-slide href="<?=e($promotion['game_id']?url('customer/game.php?id='.(int)$promotion['game_id']):url('customer/home.php'))?>"><img src="<?=e(public_image($promotion['banner_image'],'default-promo.svg'))?>" alt=""><div class="promotion-overlay"><span class="promo-discount"><?=e($promotion['discount_text']?:'Special offer')?></span><h2><?=e($promotion['title'])?></h2><p><?=e($promotion['description'])?></p><small>Ends <?=e(date('d M Y',strtotime($promotion['ends_at'])))?><?=$promotion['game_name']?' · '.e($promotion['game_name']):''?></small></div></a><?php endforeach;?></div>
    <?php if(count($promotions)>1):?><button class="slider-arrow prev" type="button" data-slide-prev aria-label="Previous promotion">‹</button><button class="slider-arrow next" type="button" data-slide-next aria-label="Next promotion">›</button><div class="slider-dots"><?php foreach($promotions as $index=>$promotion):?><button class="<?=$index===0?'active':''?>" type="button" data-slide-dot="<?=$index?>" aria-label="Show promotion <?=$index+1?>"></button><?php endforeach;?></div><?php endif;?>
</section>
<?php endif;?>

<?php if($q!==''):?>
<section class="section"><div class="section-head"><div><p class="eyebrow">Search results</p><h1 class="catalog-title">Results for “<?=e($q)?>”</h1></div><a class="button small secondary" href="<?=url('customer/home.php')?>">Clear search</a></div><?php if(!$searchGames):?><div class="empty">No games found. Try the beginning of another game name.</div><?php else:$renderGames($searchGames);endif;?></section>
<?php else:?>
<section class="section catalog-section"><div class="section-head"><div><p class="eyebrow">Featured catalog</p><h2>Hot Games</h2></div></div><?php if(!$hotGames):?><div class="empty">No Hot Games are selected yet.</div><?php else:$renderGames($hotGames,true);endif;?></section>
<section class="section catalog-section"><div class="section-head"><div><p class="eyebrow">Full catalog</p><h2>Other Games</h2></div></div><?php if(!$otherGames):?><div class="empty">All active games are currently marked as Hot.</div><?php else:$renderGames($otherGames);endif;?></section>
<?php endif;?>

<section class="section panel"><div class="section-head"><div><p class="eyebrow">Community</p><h2><?=e(t('ranking'))?></h2></div></div>
<?php if(!$ranking):?><div class="empty">Rankings appear after completed orders.</div><?php else:?><div class="table-wrap"><table class="responsive-table"><thead><tr><th>Rank</th><th>Customer</th><th>Total purchase</th></tr></thead><tbody><?php foreach($ranking as $index=>$row):$hidden=mb_substr($row['username'],0,1).str_repeat('*',max(3,mb_strlen($row['username'])-1));?><tr><td data-label="Rank" class="rank-number">#<?=$index+1?></td><td data-label="Customer"><?=e($hidden)?></td><td data-label="Total purchase"><?=money($row['total'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</section>
<?php render_footer();?>
