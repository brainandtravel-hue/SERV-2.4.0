<?php
declare(strict_types=1);
session_start();
const APP_NAME='SERV'; const APP_VERSION='2.4.0'; const TRIAL_DAYS=14;
function base_url():string{ $https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||((string)($_SERVER['SERVER_PORT']??'')==='443');$scheme=$https?'https':'http';$host=$_SERVER['HTTP_HOST']??'localhost';$script=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'/');$dir=rtrim(dirname($script),'/\\.');return $scheme.'://'.$host.($dir==='.'?'':$dir); }
function redirect(string $url):never{header('Location: '.$url);exit;} function e(?string $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];} function csrf_field():string{return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">';}
function verify_csrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Invalid security token.');}}

function flash(?string $message=null,string $type='success'):?array{if($message!==null){$_SESSION['flash']=[$type,$message];return null;}$f=$_SESSION['flash']??null;unset($_SESSION['flash']);return $f;}
function db():PDO{static $pdo=null;if($pdo instanceof PDO)return $pdo;$cfg=__DIR__.'/storage/config.php';if(!is_file($cfg))$cfg=__DIR__.'/config.local.php';if(!is_file($cfg))redirect('install.php');$c=require $cfg;$pdo=new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);migrate($pdo);return $pdo;}
function migrate(PDO $pdo):void{static $done=false;if($done)return;$done=true;
$pdo->exec("CREATE TABLE IF NOT EXISTS notification_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NULL,channel VARCHAR(30) NOT NULL,recipient VARCHAR(190) NULL,subject VARCHAR(190) NULL,body TEXT NULL,status VARCHAR(30) NOT NULL,response TEXT NULL,created_at DATETIME NOT NULL,INDEX(business_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_status_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,order_id BIGINT UNSIGNED NOT NULL,business_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NULL,from_status VARCHAR(30) NULL,to_status VARCHAR(30) NOT NULL,note TEXT NULL,created_at DATETIME NOT NULL,INDEX(order_id,created_at),FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS service_request_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_id BIGINT UNSIGNED NOT NULL,business_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NULL,from_status VARCHAR(30) NULL,to_status VARCHAR(30) NOT NULL,note TEXT NULL,created_at DATETIME NOT NULL,INDEX(request_id,created_at),FOREIGN KEY(request_id) REFERENCES service_requests(id) ON DELETE CASCADE,FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS customer_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NOT NULL,qr_id BIGINT UNSIGNED NULL,location_id INT UNSIGNED NULL,session_token CHAR(64) NOT NULL UNIQUE,last_seen_at DATETIME NOT NULL,created_at DATETIME NOT NULL,INDEX(business_id,last_seen_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS plans (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE,code VARCHAR(50) NOT NULL UNIQUE,product_type ENUM('monthly','event') NOT NULL DEFAULT 'monthly',price DECIMAL(12,2) NOT NULL DEFAULT 0,currency VARCHAR(10) NOT NULL DEFAULT 'NGN',billing_interval VARCHAR(20) NOT NULL DEFAULT 'month',max_staff INT NULL,max_tables INT NULL,max_rooms INT NULL,max_menu_items INT NULL,max_services INT NULL,max_events INT NULL,max_guests INT NULL,features TEXT NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS event_packages (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE,code VARCHAR(50) NOT NULL UNIQUE,min_guests INT UNSIGNED NOT NULL DEFAULT 1,max_guests INT UNSIGNED NULL,price DECIMAL(12,2) NOT NULL DEFAULT 0,currency VARCHAR(10) NOT NULL DEFAULT 'NGN',features TEXT NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS events (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NULL,package_id INT UNSIGNED NULL,name VARCHAR(180) NOT NULL,event_type ENUM('private','corporate','conference','seminar','wedding','other') NOT NULL DEFAULT 'private',organiser_name VARCHAR(160) NOT NULL,organiser_email VARCHAR(190) NULL,organiser_phone VARCHAR(40) NULL,venue VARCHAR(190) NULL,event_date DATE NOT NULL,start_time TIME NULL,end_time TIME NULL,guest_count INT UNSIGNED NOT NULL DEFAULT 0,table_count INT UNSIGNED NOT NULL DEFAULT 0,status ENUM('draft','scheduled','active','completed','cancelled') NOT NULL DEFAULT 'draft',amount DECIMAL(12,2) NOT NULL DEFAULT 0,payment_status ENUM('unpaid','paid','partial','refunded') NOT NULL DEFAULT 'unpaid',payment_reference VARCHAR(120) NULL,access_token CHAR(48) NULL UNIQUE,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,INDEX(business_id,event_date,status),INDEX(event_type,event_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS event_tables (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,table_number VARCHAR(40) NOT NULL,capacity INT UNSIGNED NOT NULL DEFAULT 1,qr_token CHAR(40) NOT NULL UNIQUE,status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,UNIQUE KEY event_table(event_id,table_number)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS event_guests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,table_id BIGINT UNSIGNED NULL,name VARCHAR(160) NOT NULL,email VARCHAR(190) NULL,phone VARCHAR(40) NULL,seat_number VARCHAR(30) NULL,invitation_token CHAR(48) NOT NULL UNIQUE,status ENUM('invited','checked_in','declined','served') NOT NULL DEFAULT 'invited',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,FOREIGN KEY(table_id) REFERENCES event_tables(id) ON DELETE SET NULL,INDEX(event_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS event_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,table_id BIGINT UNSIGNED NULL,guest_id BIGINT UNSIGNED NULL,request_type VARCHAR(80) NOT NULL DEFAULT 'food',message TEXT NULL,status ENUM('new','accepted','preparing','ready','served','cancelled') NOT NULL DEFAULT 'new',assigned_to INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,FOREIGN KEY(table_id) REFERENCES event_tables(id) ON DELETE SET NULL,FOREIGN KEY(guest_id) REFERENCES event_guests(id) ON DELETE SET NULL,INDEX(event_id,status,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS payment_transactions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NULL,event_id BIGINT UNSIGNED NULL,subscription_id BIGINT UNSIGNED NULL,type ENUM('subscription','event','upgrade','renewal','other') NOT NULL,amount DECIMAL(12,2) NOT NULL DEFAULT 0,currency VARCHAR(10) NOT NULL DEFAULT 'NGN',status ENUM('pending','successful','failed','refunded','offline') NOT NULL DEFAULT 'pending',reference VARCHAR(150) NULL UNIQUE,payer_name VARCHAR(160) NULL,payer_email VARCHAR(190) NULL,paid_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX(business_id,created_at),INDEX(event_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NULL,token TEXT NOT NULL,platform VARCHAR(30) NOT NULL DEFAULT 'android',app_version VARCHAR(30) NULL,last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX(business_id,status),INDEX(user_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(100) PRIMARY KEY,setting_value TEXT NULL,updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
try{$pdo->exec("ALTER TABLE businesses ADD COLUMN hero_image VARCHAR(255) NULL AFTER logo_path");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE events ADD COLUMN hero_image VARCHAR(255) NULL AFTER access_token");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE businesses MODIFY type ENUM('restaurant','hotel','event_centre') NOT NULL");
try{$pdo->exec("ALTER TABLE subscriptions ADD COLUMN plan_id INT UNSIGNED NULL AFTER business_id");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE subscriptions ADD COLUMN grace_ends_at DATETIME NULL AFTER ends_at");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE subscriptions ADD COLUMN amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER status");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE subscriptions ADD COLUMN payment_reference VARCHAR(150) NULL AFTER amount");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE subscriptions ADD COLUMN auto_renew TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_reference");}catch(Throwable $e){}

try{$pdo->exec("ALTER TABLE businesses ADD COLUMN onboarding_status ENUM('new','in_progress','complete') NOT NULL DEFAULT 'new' AFTER status");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE businesses ADD COLUMN onboarding_step TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER onboarding_status");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE businesses ADD COLUMN onboarding_completed_at DATETIME NULL AFTER onboarding_step");}catch(Throwable $e){}
}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE subscriptions ADD COLUMN plan_id INT UNSIGNED NULL AFTER business_id");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE subscriptions ADD COLUMN auto_renew TINYINT(1) NOT NULL DEFAULT 0 AFTER status");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE subscriptions ADD COLUMN payment_reference VARCHAR(150) NULL AFTER auto_renew");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE subscriptions ADD COLUMN amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER payment_reference");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE subscriptions ADD COLUMN grace_ends_at DATETIME NULL AFTER ends_at");}catch(Throwable $e){}
$seedPlans=[
 ['SERV Starter','starter','monthly',5000,10,10,null,100,5,null,null,'QR ordering, basic reports, notifications'],
 ['SERV Business','business','monthly',10000,null,null,null,null,null,null,null,'Unlimited tables, staff and menu, advanced reports, notifications'],
 ['SERV Hotel','hotel','monthly',25000,null,null,null,null,null,null,null,'Hotel services, rooms, restaurant/bar outlets, advanced hotel reports'],
 ['SERV Event Centre','event_centre','monthly',15000,null,null,null,null,null,null,null,'Event centre operations, events and conference management'],
];
$sp=$pdo->prepare("INSERT IGNORE INTO plans(name,code,product_type,price,max_staff,max_tables,max_rooms,max_menu_items,max_services,max_events,max_guests,features,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");
foreach($seedPlans as $i=>$v){$sp->execute([$v[0],$v[1],$v[2],$v[3],$v[4],$v[5],$v[6],$v[7],$v[8],$v[9],$v[10],$v[11],$i+1]);}
$eventPlans=[
 ['Event Basic','event_basic',1,100,5000,'Guest list, table allocation, table QR, food/service requests'],
 ['Event Standard','event_standard',101,300,10000,'Everything in Basic plus larger guest capacity and reports'],
 ['Event Plus','event_plus',301,500,15000,'Everything in Standard plus advanced event dashboard'],
 ['Event Pro','event_pro',501,1000,25000,'Everything in Plus plus priority event support'],
 ['Event Enterprise','event_enterprise',1001,null,40000,'Large events, custom support and configuration'],
 ['Conference Starter','conference_starter',1,100,25000,'Corporate branding, registration, check-in and event operations'],
 ['Conference Business','conference_business',101,300,50000,'Corporate conference operations and analytics'],
 ['Conference Professional','conference_professional',301,500,100000,'Premium conference operations and reporting'],
 ['Conference Enterprise','conference_enterprise',501,1000,150000,'Large corporate conference operations'],
 ['Large Conference','large_conference',1001,null,250000,'Large-scale conference package; custom scope available'],
];
$ep=$pdo->prepare("INSERT IGNORE INTO event_packages(name,code,min_guests,max_guests,price,features,sort_order) VALUES(?,?,?,?,?,?,?)");
foreach($eventPlans as $i=>$v){$ep->execute([$v[0],$v[1],$v[2],$v[3],$v[4],$v[5],$i+1]);}
$pdo->exec("CREATE TABLE IF NOT EXISTS business_services (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NOT NULL,name VARCHAR(100) NOT NULL,icon VARCHAR(20) NOT NULL DEFAULT '•',enabled TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY business_service(business_id,name),FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$cols2=$pdo->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('menu_categories','menu_items')")->fetchAll();$map2=[];foreach($cols2 as $c)$map2[$c['TABLE_NAME'].'.'.$c['COLUMN_NAME']]=1;
if(!isset($map2['menu_categories.business_service_id'])){$pdo->exec("ALTER TABLE menu_categories ADD COLUMN business_service_id INT UNSIGNED NULL AFTER outlet_id");try{$pdo->exec("ALTER TABLE menu_categories ADD INDEX idx_menu_categories_service (business_service_id)");}catch(Throwable $e){}}
if(!isset($map2['menu_items.business_service_id'])){$pdo->exec("ALTER TABLE menu_items ADD COLUMN business_service_id INT UNSIGNED NULL AFTER category_id");try{$pdo->exec("ALTER TABLE menu_items ADD INDEX idx_menu_items_service (business_service_id)");}catch(Throwable $e){}}
try{$pdo->exec("ALTER TABLE menu_categories ADD CONSTRAINT fk_menu_categories_service FOREIGN KEY (business_service_id) REFERENCES business_services(id) ON DELETE SET NULL");}catch(Throwable $e){}
try{$pdo->exec("ALTER TABLE menu_items ADD CONSTRAINT fk_menu_items_service FOREIGN KEY (business_service_id) REFERENCES business_services(id) ON DELETE SET NULL");}catch(Throwable $e){}
$pdo->exec("UPDATE menu_items i JOIN menu_categories c ON c.id=i.category_id SET i.business_service_id=c.business_service_id WHERE i.business_service_id IS NULL AND c.business_service_id IS NOT NULL");
$pdo->exec("CREATE TABLE IF NOT EXISTS service_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NOT NULL,location_id INT UNSIGNED NULL,qr_id BIGINT UNSIGNED NULL,business_service_id INT UNSIGNED NULL,type VARCHAR(80) NOT NULL,message TEXT NULL,status ENUM('new','accepted','in_progress','completed','cancelled') DEFAULT 'new',assigned_to INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,INDEX(business_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$srCols=$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='service_requests'")->fetchAll();$srMap=[];foreach($srCols as $c)$srMap[$c['COLUMN_NAME']]=1;if(!isset($srMap['business_service_id'])){$pdo->exec("ALTER TABLE service_requests ADD COLUMN business_service_id INT UNSIGNED NULL AFTER qr_id");try{$pdo->exec("ALTER TABLE service_requests ADD INDEX idx_service_requests_service (business_service_id)");}catch(Throwable $e){}}
$pdo->exec("CREATE TABLE IF NOT EXISTS notification_preferences (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL UNIQUE,sound_enabled TINYINT(1) NOT NULL DEFAULT 1,push_enabled TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$cols=$pdo->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('menu_categories','menu_items','order_items','service_requests','orders','users','notification_logs')")->fetchAll();$map=[];foreach($cols as $c)$map[$c['TABLE_NAME'].'.'.$c['COLUMN_NAME']]=1;
foreach(['menu_categories','menu_items','order_items'] as $t)if(!isset($map[$t.'.created_at']))$pdo->exec("ALTER TABLE {$t} ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
if(!isset($map['orders.served_by']))$pdo->exec("ALTER TABLE orders ADD COLUMN served_by INT UNSIGNED NULL");
if(!isset($map['orders.served_at']))$pdo->exec("ALTER TABLE orders ADD COLUMN served_at DATETIME NULL");
if(!isset($map['orders.assigned_to']))$pdo->exec("ALTER TABLE orders ADD COLUMN assigned_to INT UNSIGNED NULL");
$pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS client_token CHAR(64) NULL");
try{$pdo->exec("ALTER TABLE orders ADD UNIQUE KEY uniq_order_client_token (business_id,client_token)");}catch(Throwable $e){}
if(!isset($map['service_requests.assigned_to']))$pdo->exec("ALTER TABLE service_requests ADD COLUMN assigned_to INT UNSIGNED NULL");

try{$pdo->exec("ALTER TABLE businesses ADD COLUMN account_status ENUM('pending_payment','active','suspended','inactive') NOT NULL DEFAULT 'active' AFTER status");}catch(Throwable $e){}
$pdo->exec("CREATE TABLE IF NOT EXISTS payment_submissions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,subscription_id BIGINT UNSIGNED NULL,plan_id INT UNSIGNED NULL,amount DECIMAL(12,2) NOT NULL DEFAULT 0,payment_reference VARCHAR(150) NULL,proof_path VARCHAR(255) NOT NULL,proof_original_name VARCHAR(255) NULL,proof_mime VARCHAR(100) NULL,status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',review_note TEXT NULL,reviewed_by INT UNSIGNED NULL,submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,reviewed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,FOREIGN KEY(plan_id) REFERENCES plans(id) ON DELETE SET NULL,FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,INDEX(business_id,status,created_at),INDEX(status,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$roleType=$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'")->fetchColumn(); if($roleType && strpos($roleType,"'super_admin'")===false)$pdo->exec("ALTER TABLE users MODIFY role ENUM('super_admin','owner','admin','manager','cashier','kitchen','reception','housekeeping','maintenance','waiter','runner','staff') NOT NULL DEFAULT 'staff'");
$orderType=$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='status'")->fetchColumn(); if($orderType && strpos($orderType,"'serving'")===false)$pdo->exec("ALTER TABLE orders MODIFY status ENUM('new','accepted','preparing','ready','serving','served','completed','cancelled') NOT NULL DEFAULT 'new'");
$payType=$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='payment_status'")->fetchColumn(); if($payType && strpos($payType,"'refunded'")===false)$pdo->exec("ALTER TABLE orders MODIFY payment_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid'");
$methodType=$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='payment_method'")->fetchColumn(); if($methodType && strpos($methodType,"'online'")===false)$pdo->exec("ALTER TABLE orders MODIFY payment_method ENUM('till','cash','card','transfer','online') NOT NULL DEFAULT 'till'"); }
function current_user():?array{if(empty($_SESSION['user_id']))return null;$s=db()->prepare("SELECT * FROM users WHERE id=? AND status='active' LIMIT 1");$s->execute([(int)$_SESSION['user_id']]);return $s->fetch()?:null;}
function require_login():array{$u=current_user();if(!$u)redirect('login.php');if(!empty($u['business_id'])&&basename($_SERVER['PHP_SELF']??'')!=='account.php'){ $bs=db()->prepare('SELECT status,account_status FROM businesses WHERE id=? LIMIT 1');$bs->execute([(int)$u['business_id']]);$br=$bs->fetch();if($br&&($br['status']??'active')!=='active')redirect('account.php');if($br&&($br['account_status']??'active')!=='active')redirect('account.php');}register_fcm_device($u);return $u;}
function business():?array{$u=current_user();if(!$u||empty($u['business_id']))return null;$s=db()->prepare("SELECT * FROM businesses WHERE id=? LIMIT 1");$s->execute([(int)$u['business_id']]);return $s->fetch()?:null;}
function require_business():array{$b=business();if(!$b){flash('No business is assigned to this account.','error');redirect('index.php');}return $b;}
function is_super_admin():bool{$u=current_user();return $u&&in_array($u['role'],['super_admin'],true);}
function can(string $permission):bool{$u=current_user();if(!$u)return false;if($u['role']==='super_admin'||in_array($u['role'],['owner','admin'],true))return true;$map=['menu'=>['manager','staff','kitchen'],'orders'=>['manager','cashier','kitchen','staff','reception','housekeeping','maintenance','waiter','runner'],'qr'=>['manager','staff'],'hotel'=>['manager','reception','housekeeping','maintenance'],'staff'=>['manager'],'settings'=>['manager'],'reports'=>['manager','cashier'],'services'=>['manager','reception','housekeeping','maintenance','staff'],'events'=>['manager','staff','reception','waiter','runner']];return in_array($u['role'],$map[$permission]??[],true);}

function subscription_info(int $businessId):?array{
    $pdo=db();
    $s=$pdo->prepare("SELECT s.*,p.name plan_name,p.code plan_code,p.product_type,p.price plan_price,p.max_staff,p.max_tables,p.max_rooms,p.max_menu_items,p.max_services,p.max_events,p.max_guests,p.features plan_features,p.status plan_status FROM subscriptions s LEFT JOIN plans p ON p.id=s.plan_id WHERE s.business_id=? ORDER BY s.id DESC LIMIT 1");
    $s->execute([$businessId]);
    return $s->fetch()?:null;
}
function current_plan(int $businessId):?array{
    if(!account_active($businessId)) return null;
    $s=subscription_info($businessId);
    if(!$s || ($s['status']??'')!=='active') return null;
    if(!empty($s['ends_at']) && strtotime($s['ends_at'])<time() && empty($s['grace_ends_at'])) return null;
    if(($s['plan_status']??'active')!=='active') return null;
    return $s;
}
function plan_limit_error(int $businessId,string $resource,int $additional=1):?string{
    $p=current_plan($businessId);
    if(!$p) return 'Your subscription is not active. Please select or renew a plan before adding more resources.';
    $map=['staff'=>'max_staff','tables'=>'max_tables','rooms'=>'max_rooms','menu_items'=>'max_menu_items','services'=>'max_services','events'=>'max_events','guests'=>'max_guests'];
    if(!isset($map[$resource])) return null;
    $limit=$p[$map[$resource]];
    if($limit===null) return null;
    $pdo=db();
    $queries=[
      'staff'=>"SELECT COUNT(*) FROM users WHERE business_id=? AND role NOT IN ('owner','admin','super_admin') AND status='active'",
      'tables'=>"SELECT COUNT(*) FROM locations WHERE business_id=? AND type='table' AND status='active'",
      'rooms'=>"SELECT COUNT(*) FROM locations WHERE business_id=? AND type='room' AND status='active'",
      'menu_items'=>"SELECT COUNT(*) FROM menu_items WHERE business_id=?",
      'services'=>"SELECT COUNT(*) FROM business_services WHERE business_id=? AND enabled=1",
      'events'=>"SELECT COUNT(*) FROM events WHERE business_id=? AND status<>'cancelled'",
      'guests'=>"SELECT COUNT(*) FROM event_guests g JOIN events e ON e.id=g.event_id WHERE e.business_id=?"
    ];
    $q=$pdo->prepare($queries[$resource]);$q->execute([$businessId]);$count=(int)$q->fetchColumn();
    if($count+$additional>(int)$limit) return "{$resource} limit reached: {$count}/{$limit} on {$p['plan_name']}. Upgrade your plan to continue.";
    return null;
}
function require_active_subscription(int $businessId):array{
    $p=current_plan($businessId);
    if(!$p){flash('Your subscription is not active. Select or renew a plan to continue.','error');redirect('onboarding.php?step=6');}
    return $p;
}

function setting(string $key,string $default=''):string{$s=db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:(string)$v;}
function account_status(int $businessId):string{$s=db()->prepare('SELECT account_status FROM businesses WHERE id=? LIMIT 1');$s->execute([$businessId]);return (string)($s->fetchColumn()?:'active');}
function account_active(int $businessId):bool{return account_status($businessId)==='active';}
function payment_instructions():array{return ['bank_name'=>setting('payment_bank_name'),'account_name'=>setting('payment_account_name'),'account_number'=>setting('payment_account_number'),'instructions'=>setting('payment_instructions','Make payment to the account below and upload your payment receipt for verification.')];}
function money(float $n):string{return '₦'.number_format($n,2);} function random_token(int $bytes=16):string{return bin2hex(random_bytes($bytes));}
function json_response(array $data,int $status=200):never{http_response_code($status);header('Content-Type: application/json');echo json_encode($data);exit;}
function hero_upload_dir():string{return __DIR__.'/uploads/heroes';}
function hero_upload(string $field):string{
 $f=$_FILES[$field]??null;if(!$f||($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Please choose a hero image to upload.');
 if(($f['size']??0)>5*1024*1024)throw new RuntimeException('Hero image must be 5MB or smaller.');
 $tmp=(string)$f['tmp_name'];if(!is_uploaded_file($tmp))throw new RuntimeException('Invalid uploaded image.');
 $info=@getimagesize($tmp);if($info===false)throw new RuntimeException('The hero file is not a valid image.');
 $w=(int)($info[0]??0);$h=(int)($info[1]??0);if($w<600||$h<300)throw new RuntimeException('Hero image must be at least 600 × 300 pixels.');
 if($w>5000||$h>5000)throw new RuntimeException('Hero image dimensions are too large. Please upload an image up to 5000 × 5000 pixels.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($allowed[$mime]))throw new RuntimeException('Hero image must be JPG, PNG or WebP.');
 $dir=hero_upload_dir();if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('SERV could not create the hero image folder.');
 $name='hero_'.bin2hex(random_bytes(12)).'.'.$allowed[$mime];$path=$dir.'/'.$name;if(!move_uploaded_file($tmp,$path))throw new RuntimeException('SERV could not save the hero image.');
 @chmod($path,0644);return 'uploads/heroes/'.$name;
}
function delete_hero_file(?string $relative):void{if(!$relative)return;$base=realpath(hero_upload_dir());$file=realpath(__DIR__.'/'.$relative);if($base&&$file&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)&&is_file($file))@unlink($file);}

function audit(int $businessId,?int $userId,string $action,string $details=''):void{try{$s=db()->prepare("INSERT INTO audit_logs(business_id,user_id,action,details,ip,created_at) VALUES(?,?,?,?,?,NOW())");$s->execute([$businessId,$userId,$action,$details,$_SERVER['REMOTE_ADDR']??null]);}catch(Throwable $e){}}
function fcm_setting(string $key, ?string $default=null):?string{
    try{$s=db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:(string)$v;}catch(Throwable $e){return $default;}
}
function fcm_service_account_path():string{return __DIR__.'/storage/firebase-service-account.json';}
function fcm_base64url(string $value):string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
function fcm_service_account():array{
    static $account=null;
    if(is_array($account))return $account;
    $path=fcm_service_account_path();
    if(!is_file($path)||!is_readable($path))throw new RuntimeException('Firebase service-account file is missing or unreadable.');
    $raw=@file_get_contents($path);$j=json_decode((string)$raw,true);
    if(!is_array($j)||empty($j['client_email'])||empty($j['private_key'])||empty($j['project_id']))throw new RuntimeException('Firebase service-account JSON is invalid or incomplete.');
    $account=$j;return $account;
}
function fcm_access_token():string{
    static $cachedToken='';static $cachedUntil=0;
    if($cachedToken!=='' && $cachedUntil>time()+60)return $cachedToken;
    $a=fcm_service_account();$tokenUri=trim((string)($a['token_uri']??'https://oauth2.googleapis.com/token'));
    $now=time();
    $header=fcm_base64url(json_encode(['alg'=>'RS256','typ'=>'JWT'],JSON_UNESCAPED_SLASHES));
    $claim=fcm_base64url(json_encode(['iss'=>$a['client_email'],'scope'=>'https://www.googleapis.com/auth/firebase.messaging','aud'=>$tokenUri,'iat'=>$now,'exp'=>$now+3600],JSON_UNESCAPED_SLASHES));
    $unsigned=$header.'.'.$claim;$signature='';
    if(!openssl_sign($unsigned,$signature,(string)$a['private_key'],OPENSSL_ALGO_SHA256))throw new RuntimeException('Could not sign the Firebase OAuth assertion.');
    $assertion=$unsigned.'.'.fcm_base64url($signature);
    $ch=curl_init($tokenUri);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json'],CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$assertion]),CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20]);
    $raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($err!=='')throw new RuntimeException('Firebase OAuth connection failed: '.$err);
    $j=json_decode((string)$raw,true);$access=trim((string)($j['access_token']??''));
    if($code<200||$code>=300||$access==='')throw new RuntimeException('Firebase OAuth token request failed (HTTP '.$code.').');
    $cachedToken=$access;$cachedUntil=$now+(int)($j['expires_in']??3600);return $cachedToken;
}
function fcm_channel_id(string $type='general'):string{
    return match($type){'orders'=>'serv_orders','requests'=>'serv_requests',default=>'serv_general'};
}
function register_fcm_device(array $user):void{
    $token=trim((string)($_SESSION['fcm_token']??($_COOKIE['serv_fcm_token']??'')));
    if($token===''||empty($user['business_id'])||empty($user['id']))return;
    if(strlen($token)>4096)return;
    try{$pdo=db();$off=$pdo->prepare('UPDATE device_tokens SET status=\'inactive\',last_seen_at=NOW() WHERE token=? AND NOT (business_id=? AND user_id=?)');$off->execute([$token,(int)$user['business_id'],(int)$user['id']]);$s=$pdo->prepare('SELECT id FROM device_tokens WHERE business_id=? AND user_id=? AND token=? LIMIT 1');$s->execute([(int)$user['business_id'],(int)$user['id'],$token]);$id=$s->fetchColumn();if($id){$s=$pdo->prepare("UPDATE device_tokens SET last_seen_at=NOW(),status='active',platform='android',app_version=? WHERE id=?");$s->execute([APP_VERSION,(int)$id]);}else{$s=$pdo->prepare("INSERT INTO device_tokens(business_id,user_id,token,platform,app_version,last_seen_at,status,created_at) VALUES(?,?,?,'android',?,NOW(),'active',NOW())");$s->execute([(int)$user['business_id'],(int)$user['id'],$token,APP_VERSION]);}}catch(Throwable $e){error_log('SERV FCM device registration error: '.$e->getMessage());}
}
function fcm_store_token(string $token):void{
    $token=trim($token);if($token===''||strlen($token)>4096||preg_match('/[\x00-\x1F\x7F]/',$token))throw new RuntimeException('Invalid Firebase device token.');
    $_SESSION['fcm_token']=$token;setcookie('serv_fcm_token',$token,['expires'=>time()+31536000,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'httponly'=>true,'samesite'=>'Lax']);
}
function fcm_send_token(string $token,string $title,string $body,string $url='orders.php',array $data=[],string $channelType='general'):array{
    $token=trim($token);if($token==='')return ['ok'=>false,'skipped'=>true,'message'=>'No FCM token.'];
    $a=fcm_service_account();$project=rawurlencode((string)$a['project_id']);$access=fcm_access_token();
    $data=array_merge($data,['title'=>$title,'body'=>$body,'voice_text'=>$title.'. '.$body,'url'=>$url,'notification_type'=>$channelType]);$clean=[];foreach($data as $k=>$v){$clean[(string)$k]=is_scalar($v)?(string)$v:json_encode($v);}
    // Data-only high-priority FCM is intentional: the Android app must receive the callback so it can create the notification and speak voice_text itself.
    $payload=['message'=>['token'=>$token,'data'=>$clean,'android'=>['priority'=>'HIGH']]];
    $endpoint='https://fcm.googleapis.com/v1/projects/'.$project.'/messages:send';$ch=curl_init($endpoint);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$access,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES),CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20]);$raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$ok=$err===''&&$code>=200&&$code<300;return ['ok'=>$ok,'status'=>$code,'response'=>$raw?:$err];
}
function notify_business(int $businessId,string $title,string $body,?int $excludeUser=null,string $url='orders.php',string $channelType='general'):void{
    try{$pdo=db();$s=$pdo->prepare("SELECT id,role FROM users WHERE business_id=? AND status='active'");$s->execute([$businessId]);foreach($s->fetchAll() as $r){$uid=(int)$r['id'];if($excludeUser&&$uid===$excludeUser)continue;
        $pref=$pdo->prepare('SELECT push_enabled FROM notification_preferences WHERE user_id=? LIMIT 1');$pref->execute([$uid]);$pushEnabled=$pref->fetchColumn();if($pushEnabled!==false && (int)$pushEnabled!==1)continue;
        $d=$pdo->prepare("SELECT id,token FROM device_tokens WHERE business_id=? AND user_id=? AND status='active'");$d->execute([$businessId,$uid]);$devices=$d->fetchAll();$results=[];$sent=false;
        foreach($devices as $device){$res=fcm_send_token((string)$device['token'],$title,$body,$url,['business_id'=>$businessId,'user_id'=>$uid],$channelType);$results[]=['device_id'=>(int)$device['id'],'result'=>$res];if($res['ok'])$sent=true;if((int)($res['status']??0)===404 || (isset($res['response']) && stripos((string)$res['response'],'UNREGISTERED')!==false)){$up=$pdo->prepare("UPDATE device_tokens SET status='inactive',last_seen_at=NOW() WHERE id=?");$up->execute([(int)$device['id']]);}}
        $status=$sent?'sent':($devices?'failed':'queued');$recipient=$devices?implode(',',array_map(fn($x)=>(string)$x['token'],$devices)):'none';$response=['url'=>$url,'channel_type'=>$channelType,'channel_id'=>fcm_channel_id($channelType),'fcm'=>$results];$lg=$pdo->prepare("INSERT INTO notification_logs(business_id,user_id,channel,recipient,subject,body,status,response,created_at) VALUES(?,?,?,?,?,?,?, ?,NOW())");$lg->execute([$businessId,$uid,'push',$recipient,$title,$body,$status,json_encode($response)]);
    }}catch(Throwable $e){error_log('SERV FCM notification error: '.$e->getMessage());}
}
function fcm_status(array $user):array{
    $configured=false;$message='Firebase service-account file is not configured.';try{$a=fcm_service_account();$configured=true;$message='Firebase FCM service account is configured for project '.(string)$a['project_id'].'.';}catch(Throwable $e){$message=$e->getMessage();}
    $devices=0;try{$s=db()->prepare("SELECT COUNT(*) FROM device_tokens WHERE business_id=? AND user_id=? AND status='active'");$s->execute([(int)$user['business_id'],(int)$user['id']]);$devices=(int)$s->fetchColumn();}catch(Throwable $e){}
    return ['configured'=>$configured,'registered_devices'=>$devices,'message'=>$message];
}
