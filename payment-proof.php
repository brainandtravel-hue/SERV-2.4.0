<?php
require __DIR__.'/config.php';
$u=require_login();
if(!is_super_admin()){http_response_code(403);exit('Super Admin only.');}
$id=(int)($_GET['id']??0);$s=db()->prepare('SELECT proof_path,proof_mime,proof_original_name FROM payment_submissions WHERE id=? LIMIT 1');$s->execute([$id]);$p=$s->fetch();
if(!$p){http_response_code(404);exit('Payment proof not found.');}
$base=realpath(__DIR__.'/uploads/payment_proofs');$file=realpath(__DIR__.'/'.$p['proof_path']);
if(!$base||!$file||strpos($file,$base.DIRECTORY_SEPARATOR)!==0||!is_file($file)){http_response_code(404);exit('Payment proof file not found.');}
header('Content-Type: '.($p['proof_mime']?:'application/octet-stream'));header('Content-Length: '.filesize($file));header('Content-Disposition: inline; filename="'.str_replace(['"',"\r","\n"],'_',basename($p['proof_original_name']?:'payment-proof')).'"');readfile($file);exit;
