<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$admin = require_admin();
$errors = [];
$edit = null;
$selectedGameId = (int)($_GET['game'] ?? $_POST['game_id'] ?? 0);

if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM game_items WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
    if ($edit) $selectedGameId = (int)$edit['game_id'];
}

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    $id = (int)($_POST['id'] ?? 0);
    $selectedGameId = (int)($_POST['game_id'] ?? 0);
    if ($action === 'delete') {
        $stmt=db()->prepare('SELECT image_path FROM game_items WHERE id=? AND game_id=?');$stmt->execute([$id,$selectedGameId]);$image=$stmt->fetchColumn();
        $used=db()->prepare('SELECT COUNT(*) FROM order_items WHERE item_id=?');$used->execute([$id]);
        if((int)$used->fetchColumn()>0){db()->prepare('UPDATE game_items SET is_available=0,is_hot=0 WHERE id=? AND game_id=?')->execute([$id,$selectedGameId]);flash('warning','This item has orders, so it was made unavailable instead of deleted.');}
        else{db()->prepare('DELETE FROM game_items WHERE id=? AND game_id=?')->execute([$id,$selectedGameId]);delete_public_upload($image?:null);flash('success','Item deleted.');}
        log_admin((int)$admin['id'],'Deleted/deactivated item','item',$id);
        redirect('admin/items.php?game='.$selectedGameId);
    }

    $gameCheck=db()->prepare('SELECT id FROM games WHERE id=?');$gameCheck->execute([$selectedGameId]);
    if(!$gameCheck->fetch())$errors[]='Select a valid game.';
    $name=trim((string)($_POST['name']??''));$mmk=(float)($_POST['price_mmk']??0);$gbp=(float)($_POST['price_gbp']??0);
    if($name===''||mb_strlen($name)>150||$mmk<=0)$errors[]='Item name and a valid MMK price are required.';
    $saleMmk=($_POST['sale_price_mmk']??'')!==''?(float)$_POST['sale_price_mmk']:null;$saleGbp=($_POST['sale_price_gbp']??'')!==''?(float)$_POST['sale_price_gbp']:null;
    if($saleMmk!==null&&$saleMmk>=$mmk)$errors[]='MMK sale price must be lower than the regular price.';
    if($saleGbp!==null&&$gbp>0&&$saleGbp>=$gbp)$errors[]='GBP sale price must be lower than the regular price.';
    $imagePath=$edit['image_path']??null;$newImage=null;
    if(!empty($_FILES['image']['name'])){[$ok,$result]=upload_public_image($_FILES['image'],'items','item');if(!$ok)$errors[]=$result;else{$newImage=$result;$imagePath=$result;}}
    elseif(isset($_POST['remove_image']))$imagePath=null;
    if(!$errors){
        $values=[$selectedGameId,$name,trim((string)($_POST['description']??'')),trim((string)($_POST['bonus_text']??'')),$imagePath,$mmk,$gbp,$saleMmk,$saleGbp,isset($_POST['is_available'])?1:0,isset($_POST['is_hot'])?1:0,(int)($_POST['sort_order']??0)];
        if($id){$stmt=db()->prepare('UPDATE game_items SET game_id=?,name=?,description=?,bonus_text=?,image_path=?,price_mmk=?,price_gbp=?,sale_price_mmk=?,sale_price_gbp=?,is_available=?,is_hot=?,sort_order=? WHERE id=?');$stmt->execute([...$values,$id]);if(($edit['image_path']??null)!==$imagePath)delete_public_upload($edit['image_path']??null);}
        else{$stmt=db()->prepare('INSERT INTO game_items(game_id,name,description,bonus_text,image_path,price_mmk,price_gbp,sale_price_mmk,sale_price_gbp,is_available,is_hot,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute($values);$id=(int)db()->lastInsertId();}
        log_admin((int)$admin['id'],'Saved item','item',$id);flash('success','Item saved.');redirect('admin/items.php?game='.$selectedGameId);
    }elseif($newImage)delete_public_upload($newImage);
}

$games=db()->query('SELECT g.*,(SELECT COUNT(*) FROM game_items i WHERE i.game_id=g.id) item_count FROM games g ORDER BY g.sort_order,g.name')->fetchAll();
$selectedGame=null;$items=[];
if($selectedGameId){$stmt=db()->prepare('SELECT * FROM games WHERE id=?');$stmt->execute([$selectedGameId]);$selectedGame=$stmt->fetch();if(!$selectedGame)redirect('admin/items.php');$stmt=db()->prepare('SELECT * FROM game_items WHERE game_id=? ORDER BY sort_order,id');$stmt->execute([$selectedGameId]);$items=$stmt->fetchAll();}
$showForm=(bool)($selectedGame&&(isset($_GET['add'])||$edit||$errors));
render_header('Manage items','admin');
$v=fn($key,$default='')=>e($_POST[$key]??($edit[$key]??$default));
?>
<?php if(!$selectedGame):?>
<div class="section-head"><div><p class="eyebrow">Catalog management</p><h1>Choose a game</h1><p>Select a game to view, add or edit its items.</p></div></div>
<div class="admin-game-picker"><?php foreach($games as $game):?><a class="admin-game-card" href="<?=url('admin/items.php?game='.(int)$game['id'])?>"><img src="<?=e(public_image($game['image_path'],'default-game.svg'))?>" alt=""><div><h3><?=e($game['name'])?></h3><span><?=$game['item_count']?> item<?=$game['item_count']==1?'':'s'?></span><small><?=$game['is_active']?'Active':'Inactive'?></small></div><span class="picker-arrow">›</span></a><?php endforeach;?></div>
<?php else:?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?=url('admin/items.php')?>">Items</a><span>›</span><span><?=e($selectedGame['name'])?></span></nav>
<div class="section-head"><div class="admin-game-heading"><img src="<?=e(public_image($selectedGame['image_path'],'default-game.svg'))?>" alt=""><div><p class="eyebrow">Game items</p><h1><?=e($selectedGame['name'])?></h1><p><?=count($items)?> item<?=count($items)===1?'':'s'?> in this game.</p></div></div><div class="actions"><a class="button secondary" href="<?=url('admin/items.php')?>">Choose another game</a><?php if(!$showForm):?><a class="button" href="<?=url('admin/items.php?game='.$selectedGameId.'&add=1')?>">Add New Item</a><?php endif;?></div></div>
<?php if($errors):?><div class="form-errors"><?php foreach($errors as $error):?><div><?=e($error)?></div><?php endforeach;?></div><?php endif;?>
<?php if($showForm):?><div class="admin-editor-layout"><section class="panel"><h2><?=$edit?'Edit item':'Add new item'?></h2><p class="muted">Game: <strong><?=e($selectedGame['name'])?></strong></p><form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>"><input type="hidden" name="game_id" value="<?=$selectedGameId?>">
<div class="form-group"><label>Item image/icon</label><img class="admin-image-preview item-preview" data-item-preview src="<?=e(public_image($edit['image_path']??null,'default-item.svg'))?>" alt="Item preview"><input type="file" name="image" accept="image/png,image/jpeg,image/webp" data-preview-input="[data-item-preview]"><small class="help">Transparent PNG/WebP works best. Maximum 5 MB.</small><?php if(!empty($edit['image_path'])):?><label class="checkbox"><input type="checkbox" name="remove_image"><span>Remove current image</span></label><?php endif;?></div>
<div class="form-group"><label>Item name</label><input name="name" maxlength="150" value="<?=$v('name')?>" required></div><div class="form-group"><label>Bonus text</label><input name="bonus_text" maxlength="100" value="<?=$v('bonus_text')?>" placeholder="Example: 19 + 2 Bonus"></div><div class="form-group"><label>Description</label><input name="description" maxlength="255" value="<?=$v('description')?>"></div>
<div class="split compact-split"><div class="form-group"><label>MMK price</label><input type="number" min="1" step="1" name="price_mmk" value="<?=$v('price_mmk')?>" required></div><div class="form-group"><label>GBP price</label><input type="number" min="0" step="0.01" name="price_gbp" value="<?=$v('price_gbp','0')?>"></div></div><div class="split compact-split"><div class="form-group"><label>MMK sale price</label><input type="number" min="0" step="1" name="sale_price_mmk" value="<?=$v('sale_price_mmk')?>"></div><div class="form-group"><label>GBP sale price</label><input type="number" min="0" step="0.01" name="sale_price_gbp" value="<?=$v('sale_price_gbp')?>"></div></div>
<div class="form-group"><label>Display order</label><input type="number" name="sort_order" value="<?=$v('sort_order','0')?>"></div><div class="check-row"><label class="checkbox"><input type="checkbox" name="is_available" <?=isset($_POST['is_available'])||(!is_post()&&(!$edit||($edit['is_available']??0)))?'checked':''?>><span>Available for sale</span></label><label class="checkbox"><input type="checkbox" name="is_hot" <?=isset($_POST['is_hot'])||(!is_post()&&($edit['is_hot']??0))?'checked':''?>><span>Popular item badge</span></label></div><div class="actions"><button class="button">Save item</button><a class="button secondary" href="<?=url('admin/items.php?game='.$selectedGameId)?>">Cancel</a></div></form></section>
<aside class="panel"><h2>Items in this game</h2><div class="admin-card-list"><?php foreach($items as $item):?><a class="admin-list-card compact" href="<?=url('admin/items.php?game='.$selectedGameId.'&edit='.(int)$item['id'])?>"><img src="<?=e(public_image($item['image_path'],'default-item.svg'))?>" alt=""><div class="admin-list-content"><strong><?=e($item['name'])?></strong><div class="muted"><?=money($item['sale_price_mmk']?:$item['price_mmk'])?></div></div></a><?php endforeach;?></div></aside></div>
<?php else:?>
<?php if(!$items):?><div class="empty"><p>No items added yet.</p><a class="button" href="<?=url('admin/items.php?game='.$selectedGameId.'&add=1')?>">Add the first item</a></div><?php else:?><div class="item-management-grid"><?php foreach($items as $item):?><article class="managed-item-card"><img src="<?=e(public_image($item['image_path'],'default-item.svg'))?>" alt=""><div class="managed-item-body"><div class="actions"><span class="badge <?=$item['is_available']?'success':'danger'?>"><?=$item['is_available']?'Available':'Unavailable'?></span><?php if($item['is_hot']):?><span class="badge info">Popular</span><?php endif;?></div><h3><?=e($item['name'])?></h3><?php if($item['bonus_text']):?><p><?=e($item['bonus_text'])?></p><?php endif;?><strong><?=money($item['sale_price_mmk']?:$item['price_mmk'])?></strong><div class="actions item-actions"><a class="button small secondary" href="<?=url('admin/items.php?game='.$selectedGameId.'&edit='.(int)$item['id'])?>">Edit</a><form method="post" onsubmit="return confirm('Delete or deactivate this item?')"><?=csrf_field()?><input type="hidden" name="game_id" value="<?=$selectedGameId?>"><input type="hidden" name="id" value="<?=$item['id']?>"><input type="hidden" name="action" value="delete"><button class="button small danger">Delete</button></form></div></div></article><?php endforeach;?></div><?php endif;?>
<?php endif;?>
<?php endif;?>
<?php render_footer();?>
