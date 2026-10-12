<?php
require __DIR__.'/config.php';
$out=['app'=>APP_NAME,'version'=>APP_VERSION,'php'=>PHP_VERSION,'pdo_mysql'=>extension_loaded('pdo_mysql'),'curl'=>extension_loaded('curl'),'gd'=>extension_loaded('gd'),'https'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')];
header('Content-Type: application/json');echo json_encode($out,JSON_PRETTY_PRINT);