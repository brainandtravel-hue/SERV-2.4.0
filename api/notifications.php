<?php
require __DIR__.'/../config.php';
$u=require_login();$b=require_business();
$after=max(0,(int)($_GET['after']??0));
if($after===0){$after=(int)db()->query("SELECT COALESCE(MAX(id),0) FROM notification_logs")->fetchColumn();}
$s=db()->prepare("SELECT id,subject,body,created_at,response FROM notification_logs WHERE business_id=? AND user_id=? AND id>? ORDER BY id ASC LIMIT 20");$s->execute([$b['id'],$u['id'],$after]);
$rows=$s->fetchAll();$out=[];
foreach($rows as $r){$meta=json_decode((string)($r['response']??''),true);$out[]=['id'=>(int)$r['id'],'title'=>$r['subject'],'body'=>$r['body'],'created_at'=>$r['created_at'],'url'=>$meta['url']??'orders.php'];}
json_response(['ok'=>true,'notifications'=>$out]);
