<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
$admin = require_admin();
$errors = [];
$edit = null;

if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM games WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT * FROM games WHERE id=?'); $stmt->execute([$id]); $game = $stmt->fetch();
        $used = db()->prepare('SELECT COUNT(*) FROM orders WHERE game_id=?'); $used->execute([$id]);
        if ((int)$used->fetchColumn() > 0) {
            db()->prepare('UPDATE games SET is_active=0,is_hot=0 WHERE id=?')->execute([$id]);
            flash('warning', 'This game has orders, so it was deactivated instead of deleted.');
        } else {
            $images = db()->prepare('SELECT image_path FROM game_items WHERE game_id=?'); $images->execute([$id]);
            foreach ($images->fetchAll() as $row) delete_public_upload($row['image_path']);
            if ($game) delete_public_upload($game['image_path']);
            db()->prepare('DELETE FROM games WHERE id=?')->execute([$id]);
            flash('success', 'Game deleted.');
        }
        log_admin((int)$admin['id'], 'Deleted/deactivated game', 'game', $id);
        redirect('admin/games.php');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $slugInput = (string)($_POST['slug'] ?? '');
    $slug = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $slugInput !== '' ? $slugInput : $name), '-'));
    $short = strtoupper(trim((string)($_POST['short_code'] ?? '')));
    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Game name is required and must not exceed 100 characters.';
    if ($slug === '') $errors[] = 'A valid slug is required.';
    if ($short === '' || mb_strlen($short) > 8) $errors[] = 'Short code is required and must not exceed 8 characters.';
    $dupe = db()->prepare('SELECT id FROM games WHERE slug=? AND id<>?'); $dupe->execute([$slug,$id]);
    if ($dupe->fetch()) $errors[] = 'That slug is already used by another game.';

    $imagePath = $edit['image_path'] ?? null;
    $newImage = null;
    if (!empty($_FILES['image']['name'])) {
        [$ok,$result] = upload_public_image($_FILES['image'], 'games', 'game');
        if (!$ok) $errors[] = $result; else { $newImage = $result; $imagePath = $result; }
    } elseif (isset($_POST['remove_image'])) {
        $imagePath = null;
    }

    if (!$errors) {
        $values = [$name,$slug,$short,trim((string)($_POST['description']??'')),$imagePath,(string)($_POST['accent_color']??'#7c3aed'),trim((string)($_POST['id_label']??'Player ID')),trim((string)($_POST['id_placeholder']??'')),trim((string)($_POST['id_help']??'')),isset($_POST['requires_zone'])?1:0,trim((string)($_POST['zone_label']??'')),trim((string)($_POST['zone_placeholder']??'')),isset($_POST['requires_server'])?1:0,trim((string)($_POST['server_options']??'')),isset($_POST['requires_region'])?1:0,trim((string)($_POST['region_options']??'')),isset($_POST['is_hot'])?1:0,(int)($_POST['sort_order']??0),isset($_POST['is_active'])?1:0];
        if ($id) {
            $stmt=db()->prepare('UPDATE games SET name=?,slug=?,short_code=?,description=?,image_path=?,accent_color=?,id_label=?,id_placeholder=?,id_help=?,requires_zone=?,zone_label=?,zone_placeholder=?,requires_server=?,server_options=?,requires_region=?,region_options=?,is_hot=?,sort_order=?,is_active=? WHERE id=?');
            $stmt->execute([...$values,$id]);
            if (($edit['image_path'] ?? null) && ($edit['image_path'] !== $imagePath)) delete_public_upload($edit['image_path']);
        } else {
            $stmt=db()->prepare('INSERT INTO games(name,slug,short_code,description,image_path,accent_color,id_label,id_placeholder,id_help,requires_zone,zone_label,zone_placeholder,requires_server,server_options,requires_region,region_options,is_hot,sort_order,is_active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute($values); $id=(int)db()->lastInsertId();
        }
        log_admin((int)$admin['id'], 'Saved game', 'game', $id);
        flash('success', 'Game saved.'); redirect('admin/games.php');
    } elseif ($newImage) delete_public_upload($newImage);
}

$games=db()->query('SELECT g.*,(SELECT COUNT(*) FROM game_items i WHERE i.game_id=g.id) item_count FROM games g ORDER BY g.sort_order,g.name')->fetchAll();
render_header('Manage games','admin');
$v=fn($key,$default='')=>e($_POST[$key]??($edit[$key]??$default));
?>
<div class="section-head"><div><p class="eyebrow">Catalog management</p><h1>Games</h1></div></div>
<?php if($errors):?><div class="form-errors"><?php foreach($errors as $error):?><div><?=e($error)?></div><?php endforeach;?></div><?php endif;?>
<div class="admin-editor-layout"><section class="panel"><h2><?=$edit?'Edit game':'Add new game'?></h2>
<form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>">
<div class="form-group"><label>Game cover image</label><img class="admin-image-preview game-preview" data-game-preview src="<?=e(public_image($edit['image_path']??null,'default-game.svg'))?>" alt="Game preview"><input type="file" name="image" accept="image/png,image/jpeg,image/webp" data-preview-input="[data-game-preview]"><small class="help">Square image such as 800 × 800 px is recommended. JPG, PNG or WebP; maximum 5 MB.</small><?php if(!empty($edit['image_path'])):?><label class="checkbox"><input type="checkbox" name="remove_image"><span>Remove current image</span></label><?php endif;?></div>
<div class="split"><div class="form-group"><label>Game name</label><input name="name" maxlength="100" value="<?=$v('name')?>" required></div><div class="form-group"><label>Short code</label><input name="short_code" value="<?=$v('short_code')?>" maxlength="8" required></div></div>
<div class="form-group"><label>Slug</label><input name="slug" value="<?=$v('slug')?>" placeholder="mobile-legends"><small class="help">Leave empty to create it from the game name.</small></div>
<div class="form-group"><label>Description</label><input name="description" maxlength="255" value="<?=$v('description')?>"></div>
<div class="split"><div class="form-group"><label>Accent color</label><input type="color" name="accent_color" value="<?=$v('accent_color','#7c3aed')?>"></div><div class="form-group"><label>Display order</label><input type="number" name="sort_order" value="<?=$v('sort_order','0')?>"></div></div>
<div class="split"><div class="form-group"><label>ID label</label><input name="id_label" value="<?=$v('id_label','Player ID')?>"></div><div class="form-group"><label>ID example</label><input name="id_placeholder" value="<?=$v('id_placeholder')?>"></div></div>
<div class="form-group"><label>ID help text</label><input name="id_help" value="<?=$v('id_help')?>"></div>
<label class="checkbox"><input type="checkbox" name="requires_zone" <?=isset($_POST['requires_zone'])||(!is_post()&&($edit['requires_zone']??0))?'checked':''?>><span>Requires Zone ID / Tagline</span></label>
<div class="split"><div class="form-group"><label>Zone label</label><input name="zone_label" value="<?=$v('zone_label')?>"></div><div class="form-group"><label>Zone example</label><input name="zone_placeholder" value="<?=$v('zone_placeholder')?>"></div></div>
<label class="checkbox"><input type="checkbox" name="requires_server" <?=isset($_POST['requires_server'])||(!is_post()&&($edit['requires_server']??0))?'checked':''?>><span>Requires server selection</span></label>
<div class="form-group"><label>Server options</label><input name="server_options" value="<?=$v('server_options')?>" placeholder="Asia,Europe,America"><small class="help">Separate options with commas.</small></div>
<label class="checkbox"><input type="checkbox" name="requires_region" <?=isset($_POST['requires_region'])||(!is_post()&&($edit['requires_region']??0))?'checked':''?>><span>Requires account region</span></label>
<div class="form-group"><label>Region options</label><input name="region_options" value="<?=$v('region_options')?>"></div>
<div class="check-row"><label class="checkbox"><input type="checkbox" name="is_hot" <?=isset($_POST['is_hot'])||(!is_post()&&($edit['is_hot']??0))?'checked':''?>><span>Show in Hot Games</span></label><label class="checkbox"><input type="checkbox" name="is_active" <?=isset($_POST['is_active'])||(!is_post()&&(!$edit||($edit['is_active']??0)))?'checked':''?>><span>Active</span></label></div>
<div class="actions"><button class="button">Save game</button><?php if($edit):?><a class="button secondary" href="<?=url('admin/games.php')?>">Cancel</a><?php endif;?></div></form></section>
<section class="panel"><h2>Current games</h2><div class="admin-card-list"><?php foreach($games as $game):?><article class="admin-list-card"><img src="<?=e(public_image($game['image_path'],'default-game.svg'))?>" alt=""><div class="admin-list-content"><strong><?=e($game['name'])?></strong><div class="muted"><?=$game['item_count']?> items · <?=$game['is_active']?'Active':'Inactive'?><?=$game['is_hot']?' · Hot':''?></div><div class="actions"><a class="button small secondary" href="<?=url('admin/games.php?edit='.(int)$game['id'])?>">Edit</a><form method="post" onsubmit="return confirm('Delete or deactivate this game?')"><?=csrf_field()?><input type="hidden" name="id" value="<?=$game['id']?>"><input type="hidden" name="action" value="delete"><button class="button small danger">Delete</button></form></div></div></article><?php endforeach;?></div></section></div>
<?php render_footer();?>
