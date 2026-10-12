<?php
require __DIR__.'/../config.php';
$u=require_login();
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'message'=>'POST required.'],405);
$raw=file_get_contents('php://input');$j=json_decode((string)$raw,true);$token=trim((string)($_POST['token']??($j['token']??'')));
if($token==='')json_response(['ok'=>false,'message'=>'FCM token is required.'],422);
try{
    fcm_store_token($token);
    register_fcm_device($u);
    json_response(['ok'=>true,'message'=>'FCM device token registered.','app_version'=>APP_VERSION]);
}catch(Throwable $e){json_response(['ok'=>false,'message'=>$e->getMessage()],422);}
