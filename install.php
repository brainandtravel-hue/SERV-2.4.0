<?php
declare(strict_types=1);
require __DIR__.'/config.php';

if (is_file(__DIR__.'/storage/config.php') || is_file(__DIR__.'/config.local.php')) {
    exit('<!doctype html><meta name="viewport" content="width=device-width"><body style="font-family:system-ui;padding:40px"><h2>Digital Menu is already installed.</h2><p>For security, delete or rename install.php if your host permits it.</p><a href="login.php">Go to login</a></body>');
}
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $host=trim($_POST['db_host']??'localhost'); $name=trim($_POST['db_name']??'');
        $user=trim($_POST['db_user']??''); $pass=(string)($_POST['db_pass']??'');
        $email=trim($_POST['email']??''); $adminpass=(string)($_POST['admin_pass']??''); $platformEmail=trim($_POST['platform_email']??''); $platformPass=(string)($_POST['platform_pass']??'');
        $biz=trim($_POST['business_name']??''); $type=$_POST['business_type']??'restaurant';
        if (!$name||!$user||!$email||!$adminpass||!$biz) throw new RuntimeException('Please complete all required fields.');
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email.');
        if (strlen($adminpass)<8) throw new RuntimeException('Admin password must be at least 8 characters.');
        if (!in_array($type,['restaurant','hotel'],true)) $type='restaurant';
        $pdo=new PDO("mysql:host={$host};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `".str_replace('`','',$name)."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `".str_replace('`','',$name)."`");
        $schema=file_get_contents(__DIR__.'/database/schema.sql'); $pdo->exec($schema);
        $pdo->beginTransaction();

        // Make retries safe. If the first installation created the tables and
        // account but failed to persist its config file, reuse that account
        // instead of throwing a duplicate-email error.
        $existing=$pdo->prepare("SELECT id,business_id FROM users WHERE email=? LIMIT 1");
        $existing->execute([$email]);
        $existingUser=$existing->fetch();
        if ($existingUser) {
            $bid=(int)($existingUser['business_id'] ?? 0);
            if ($bid < 1) throw new RuntimeException('This email already exists but is not attached to a business. Use a different email or repair the existing account first.');
            $check=$pdo->prepare("SELECT id FROM businesses WHERE id=? LIMIT 1");
            $check->execute([$bid]);
            if (!$check->fetchColumn()) throw new RuntimeException('The existing admin account points to a missing business.');
        } else {
            $s=$pdo->prepare("INSERT INTO businesses(name,type,slug,trial_ends_at,status,created_at) VALUES(?,?,?,?,?,NOW())");
            $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $biz),'-')).'-'.bin2hex(random_bytes(3));
            $s->execute([$biz,$type,$slug,date('Y-m-d H:i:s',time()+TRIAL_DAYS*86400),'active']);
            $bid=(int)$pdo->lastInsertId();
            $s=$pdo->prepare("INSERT INTO users(business_id,name,email,password_hash,role,status,created_at) VALUES(?,?,?,?,?,?,NOW())");
            $s->execute([$bid,'Owner',$email,password_hash($adminpass,PASSWORD_DEFAULT),'owner','active']);
        }

        $plan=$pdo->prepare("SELECT id FROM subscriptions WHERE business_id=? LIMIT 1");
        $plan->execute([$bid]);
        if (!$plan->fetchColumn()) {
            $insPlan=$pdo->prepare("INSERT INTO subscriptions(business_id,plan,starts_at,ends_at,status) VALUES(?,?,?,?,?)");
            $insPlan->execute([$bid,'trial',date('Y-m-d H:i:s'),date('Y-m-d H:i:s',time()+TRIAL_DAYS*86400),'active']);
        }
        if($platformEmail && $platformPass){if(!filter_var($platformEmail,FILTER_VALIDATE_EMAIL)||strlen($platformPass)<8)throw new RuntimeException('Platform admin email/password is invalid.');$ps=$pdo->prepare("INSERT INTO users(business_id,name,email,password_hash,role,status,created_at) VALUES(NULL,?,?,?,?,?,NOW())");$ps->execute(['SERV Platform Admin',$platformEmail,password_hash($platformPass,PASSWORD_DEFAULT),'super_admin','active']);}
        $pdo->commit();

        // Persist database credentials before redirecting. Some free hosts do not
        // allow PHP to write inside /storage, so try storage first and then the
        // application root as a fallback. The installer only reports success if
        // the credentials were actually saved and can be read back.
        $configPhp = "<?php\nreturn " . var_export(['host'=>$host,'name'=>$name,'user'=>$user,'pass'=>$pass], true) . ";\n";
        $configPaths = [__DIR__.'/storage/config.php', __DIR__.'/config.local.php'];
        $saved = false;
        foreach ($configPaths as $configPath) {
            $dir = dirname($configPath);
            if (!is_dir($dir) || !is_writable($dir)) continue;
            if (@file_put_contents($configPath, $configPhp, LOCK_EX) !== false && is_file($configPath)) {
                @chmod($configPath, 0600);
                $saved = true;
                break;
            }
        }
        if (!$saved) {
            throw new RuntimeException('The database setup completed, but Digital Menu could not save its configuration file. Make the storage folder writable (755/775) or allow the application directory to be writable, then run the installer again.');
        }
        @file_put_contents(__DIR__.'/storage/installed.lock', date('c'), LOCK_EX);
        redirect('login.php?installed=1');
    } catch(Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $error=$e->getMessage();
    }
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Install · Digital Menu</title>
<link rel="stylesheet" href="assets/css/app.css"></head><body class="center"><div class="install card"><div class="brand">SERV</div><p class="muted">Luxury hospitality platform · Installation</p>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form method="post" class="form-grid">
<h3>Database</h3>
<label>Host<input name="db_host" placeholder="e.g. sql205.hstn.me" required></label><label>Database name<input name="db_name" required></label>
<label>Database username<input name="db_user" required></label><label>Database password<input name="db_pass" type="password"></label>
<h3>First business</h3>
<label>Business name<input name="business_name" required></label><label>Type<select name="business_type"><option value="restaurant">Restaurant</option><option value="hotel">Hotel</option></select></label>
<h3>Owner account</h3>
<label>Email<input name="email" type="email" required></label><label>Password<input name="admin_pass" type="password" minlength="8" required></label>
<h3>Optional SERV platform admin</h3><label>Platform admin email<input name="platform_email" type="email"></label><label>Platform admin password<input name="platform_pass" type="password" minlength="8"></label><button class="btn primary full">Install SERV</button></form></div></body></html>