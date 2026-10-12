<?php
require __DIR__.'/config.php';
if(current_user()) redirect('index.php');
$err='';
$plans=[];
try{$plans=db()->query("SELECT * FROM plans WHERE product_type='monthly' AND status='active' ORDER BY sort_order,id")->fetchAll();}catch(Throwable $e){$err='SERV is not ready for registration yet.';}
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $pdo=db();
        $name=trim((string)($_POST['name']??''));
        $email=trim((string)($_POST['email']??''));
        $phone=trim((string)($_POST['phone']??''));
        $password=(string)($_POST['password']??'');
        $confirm=(string)($_POST['password_confirm']??'');
        $businessName=trim((string)($_POST['business_name']??''));
        $type=(string)($_POST['business_type']??'restaurant');
        $planId=(int)($_POST['plan_id']??0);
        if($name===''||$businessName===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8||$password!==$confirm||!in_array($type,['restaurant','hotel','event_centre'],true)||$planId<1)throw new RuntimeException('Please complete all required fields correctly.');
        $s=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$s->execute([$email]);if($s->fetchColumn())throw new RuntimeException('An account with this email already exists. Please log in instead.');
        $s=$pdo->prepare("SELECT * FROM plans WHERE id=? AND product_type='monthly' AND status='active' LIMIT 1");$s->execute([$planId]);$plan=$s->fetch();if(!$plan)throw new RuntimeException('Please select an available SERV plan.');
        $pdo->beginTransaction();
        $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$businessName),'-')).'-'.bin2hex(random_bytes(4));
        $s=$pdo->prepare("INSERT INTO businesses(name,type,slug,phone,status,account_status,onboarding_status,created_at) VALUES(?,?,?,?, 'active','pending_payment','new',NOW())");
        $s->execute([$businessName,$type,$slug,$phone?:null]);$bid=(int)$pdo->lastInsertId();
        $s=$pdo->prepare("INSERT INTO users(business_id,name,email,password_hash,role,status,created_at) VALUES(?,?,?,?, 'owner','active',NOW())");
        $s->execute([$bid,$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$uid=(int)$pdo->lastInsertId();
        $starts=date('Y-m-d H:i:s');
        $s=$pdo->prepare("INSERT INTO subscriptions(business_id,plan_id,plan,starts_at,ends_at,status,amount) VALUES(?,?, 'monthly',?,?, 'suspended',?)");
        $s->execute([$bid,$planId,$starts,date('Y-m-d H:i:s',strtotime('+1 month')),(float)$plan['price']]);
        $pdo->commit();
        session_regenerate_id(true);$_SESSION['user_id']=$uid;
        audit($bid,$uid,'account.signup','Public business registration · '.$plan['name'].' · pending payment');
        redirect('account.php?registered=1');
    }catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$err=$e->getMessage();}
}
function plan_features(string $features):array{return array_values(array_filter(array_map('trim',preg_split('/\R+/',$features))));}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#F7F0E3"><title>Sign up · SERV</title><link rel="stylesheet" href="assets/css/app.css?v=2.3.0"></head>
<body class="auth-page"><main class="auth-shell">
<header class="auth-top"><a class="auth-brand" href="login.php">SERV</a><a class="auth-link" href="login.php">Log in</a></header>
<section class="auth-card auth-signup"><div class="auth-eyebrow">SERV Hospitality Platform</div><h1>Sign up</h1><p class="auth-sub">Create your business account and choose your SERV plan.</p>
<?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?>
<form method="post" autocomplete="on" enctype="application/x-www-form-urlencoded">
<?=csrf_field()?>
<div class="auth-section"><div class="auth-section-title">Your account</div>
<label>Your name<input name="name" value="<?=e($_POST['name']??'')?>" autocomplete="name" required></label>
<label>Your email<input type="email" name="email" value="<?=e($_POST['email']??'')?>" autocomplete="email" required></label>
<label>Phone number<input type="tel" name="phone" value="<?=e($_POST['phone']??'')?>" autocomplete="tel" placeholder="080..." required></label>
<label>Password<input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
<label>Confirm password<input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required></label></div>
<div class="auth-section"><div class="auth-section-title">Your business</div>
<label>Business name<input name="business_name" value="<?=e($_POST['business_name']??'')?>" autocomplete="organization" required></label>
<label>Business type<select name="business_type" required><option value="restaurant" <?=($_POST['business_type']??'')==='restaurant'?'selected':''?>>Restaurant</option><option value="hotel" <?=($_POST['business_type']??'')==='hotel'?'selected':''?>>Hotel</option><option value="event_centre" <?=($_POST['business_type']??'')==='event_centre'?'selected':''?>>Event Centre</option></select></label></div>
<div class="auth-section"><div class="auth-section-title">Choose your plan</div><div class="plan-choice-list">
<?php foreach($plans as $p):?><label class="plan-choice"><input type="radio" name="plan_id" value="<?=$p['id']?>" <?=((int)($_POST['plan_id']??0)===(int)$p['id']?'checked':'')?> required><span class="plan-choice-main"><b><?=e($p['name'])?></b><strong>₦<?=number_format((float)$p['price'],0)?> <small>/ month</small></strong><?php if($p['features']):?><small><?=e(str_replace("\n",' · ',$p['features']))?></small><?php endif;?></span><span class="plan-radio">✓</span></label><?php endforeach; if(!$plans):?><p class="muted">No active subscription plans are available.</p><?php endif;?></div></div>
<div class="auth-note">Your account will remain <b>inactive</b> until SERV verifies your payment. After signup, you can upload your payment receipt from your account.</div>
<button class="auth-submit" type="submit">Sign up</button>
</form>
<p class="auth-terms">By signing up you agree to our terms of use and privacy policy.</p>
<p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
</section></main></body></html>
