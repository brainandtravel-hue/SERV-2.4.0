<?php
require __DIR__.'/../config.php';
$u=require_login();
$status=fcm_status($u);
json_response([
  'ok'=>true,
  'fcm_configured'=>$status['configured'],
  'registered_devices'=>$status['registered_devices'],
  'app_version'=>APP_VERSION,
  'message'=>$status['message']
]);
