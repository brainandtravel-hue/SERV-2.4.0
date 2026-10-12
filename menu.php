<?php
require __DIR__.'/config.php';
$u=require_login();$b=require_business();
if(!can('menu')){http_response_code(403);exit('Forbidden');}
$pdo=db();
$isHotel=$b['type']==='hotel';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $a=$_POST['action']??'';
        if($a==='cat'){
            $name=trim($_POST['name']??'');
            if($name==='')throw new RuntimeException('Category name is required.');
            $serviceId=$isHotel ? ((int)($_POST['service_id']??0)?:null) : null;
            $outletId=((int)($_POST['outlet_id']??0)?:null);
            if($isHotel && !$serviceId)throw new RuntimeException('Select the hotel service this category belongs to.');
            if($outletId){$chk=$pdo->prepare("SELECT id FROM outlets WHERE id=? AND business_id=? AND status='active'");$chk->execute([$outletId,$b['id']]);if(!$chk->fetchColumn())$outletId=null;}
            $s=$pdo->prepare("INSERT INTO menu_categories(business_id,outlet_id,business_service_id,name,created_at) VALUES(?,?,?,?,NOW())");
            $s->execute([$b['id'],$outletId,$serviceId,$name]);
            flash('Category created.');
        }elseif($a==='item'){
            if($err=plan_limit_error((int)$b['id'],'menu_items'))throw new RuntimeException($err);
            $name=trim($_POST['name']??'');$price=max(0,(float)($_POST['price']??0));$catId=(int)($_POST['category_id']??0);
            if($name==='')throw new RuntimeException('Item name is required.');
            if(!$catId)throw new RuntimeException('Select a category.');
            $chk=$pdo->prepare("SELECT id FROM menu_categories WHERE id=? AND business_id=? LIMIT 1");$chk->execute([$catId,$b['id']]);
            if(!$chk->fetchColumn())throw new RuntimeException('Invalid category.');
            $cs=$pdo->prepare("SELECT business_service_id FROM menu_categories WHERE id=? AND business_id=? LIMIT 1");$cs->execute([$catId,$b['id']]);$categoryService=(int)($cs->fetchColumn()?:0)?:null;
            $s=$pdo->prepare("INSERT INTO menu_items(business_id,category_id,business_service_id,name,description,price,available,created_at) VALUES(?,?,?,?,?,?,1,NOW())");
            $s->execute([$b['id'],$catId,$categoryService,$name,trim($_POST['description']??''),$price]);
            flash('Item added.');
        }elseif($a==='toggle'){
            $s=$pdo->prepare("UPDATE menu_items SET available=1-available WHERE id=? AND business_id=?");
            $s->execute([(int)$_POST['id'],$b['id']]);
        }elseif($a==='delete_item'){
            $s=$pdo->prepare("DELETE FROM menu_items WHERE id=? AND business_id=?");
            $s->execute([(int)$_POST['id'],$b['id']]);
            flash('Item deleted.');
        }elseif($a==='delete_cat'){
            $s=$pdo->prepare("DELETE FROM menu_categories WHERE id=? AND business_id=?");
            $s->execute([(int)$_POST['id'],$b['id']]);
            flash('Category deleted.');
        }
    }catch(Throwable $e){flash('Could not save: '.$e->getMessage(),'error');}
    redirect('menu.php');
}

$services=[];
if($isHotel){$ss=$pdo->prepare("SELECT * FROM business_services WHERE business_id=? ORDER BY id");$ss->execute([$b['id']]);$services=$ss->fetchAll();}
$outlets=$pdo->prepare("SELECT * FROM outlets WHERE business_id=? AND status='active' ORDER BY name");$outlets->execute([$b['id']]);$outlets=$outlets->fetchAll();
$cats=$pdo->prepare("SELECT c.*,s.name service_name,o.name outlet_name FROM menu_categories c LEFT JOIN business_services s ON s.id=c.business_service_id LEFT JOIN outlets o ON o.id=c.outlet_id WHERE c.business_id=? ORDER BY c.sort_order,c.id");$cats->execute([$b['id']]);$cats=$cats->fetchAll();
$items=$pdo->prepare("SELECT i.*,c.name category_name,c.business_service_id,s.name service_name,o.name outlet_name FROM menu_items i LEFT JOIN menu_categories c ON c.id=i.category_id LEFT JOIN business_services s ON s.id=c.business_service_id LEFT JOIN outlets o ON o.id=c.outlet_id WHERE i.business_id=? ORDER BY i.id DESC");$items->execute([$b['id']]);$items=$items->fetchAll();
include __DIR__.'/app/layout_top.php';
?>
<div class="page-head"><div><div class="eyebrow"><?=$isHotel?'Hotel catalogue':'Restaurant menu'?></div><h1><?=$isHotel?'Menus & service items':'Menu'?></h1><p><?=$isHotel?'Add items to Room Service, Restaurant, Bar and any other hotel service.':'Manage what customers can order.'?></p></div></div>
<?php if($isHotel):?><div class="panel"><h3>Hotel service menus</h3><p class="small muted">Each service can have its own categories and items. Guests only see items for the service they select.</p><div class="quick-grid"><?php foreach($services as $sv):?><a class="panel quick" href="#service-<?=$sv['id']?>"><b><?=e($sv['icon'])?> <?=e($sv['name'])?></b><span>Manage items →</span></a><?php endforeach;?></div></div><?php endif;?>
<div class="panel"><h3>Add category</h3><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="cat"><label>Name<input name="name" placeholder="Main Meals / Drinks / Treatments" required></label><?php if($isHotel):?><label>Hotel service<select name="service_id" required><option value="">Select service</option><?php foreach($services as $sv):?><option value="<?=$sv['id']?>"><?=e($sv['icon'].' '.$sv['name'])?></option><?php endforeach;?></select></label><?php endif;?><label>Outlet<select name="outlet_id"><option value="">General</option><?php foreach($outlets as $o):?><option value="<?=$o['id']?>"><?=e($o['name'])?></option><?php endforeach;?></select></label><button class="btn gold">Add category</button></form></div>
<div class="panel"><h3>Add item</h3><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="item"><label>Name<input name="name" required></label><label>Price<input type="number" name="price" step="0.01" min="0" required></label><label>Category<select name="category_id" required><option value="">Select category</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>"><?=e($c['name'].($c['service_name']?' · '.$c['service_name']:'').($c['outlet_name']?' · '.$c['outlet_name']:''))?></option><?php endforeach;?></select></label><label>Description<textarea name="description"></textarea></label><button class="btn primary">Add item</button></form></div>
<div class="panel"><h3>Published items</h3><?php foreach($items as $i):?><div class="row"><div><b><?=e($i['name'])?></b><div class="small muted"><?=e($i['category_name']??'Uncategorized')?><?php if($i['service_name']):?> · <?=e($i['service_name'])?><?php endif;?><?php if($i['outlet_name']):?> · <?=e($i['outlet_name'])?><?php endif;?> · <?=money((float)$i['price'])?></div></div><div class="actions"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$i['id']?>"><button class="btn"><?=$i['available']?'Available':'Unavailable'?></button></form><form method="post" onsubmit="return confirm('Delete this item?')"><?=csrf_field()?><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?=$i['id']?>"><button class="btn">Delete</button></form></div></div><?php endforeach;if(!$items):?><p class="muted">No items yet.</p><?php endif;?></div>
<?php include __DIR__.'/app/layout_bottom.php';
