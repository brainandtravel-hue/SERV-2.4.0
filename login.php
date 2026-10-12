<?php
require __DIR__.'/config.php';
if(current_user()) redirect('index.php');
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $email=trim((string)($_POST['email']??''));
 $s=db()->prepare("SELECT * FROM users WHERE email=? LIMIT 1");$s->execute([$email]);$u=$s->fetch();
 if($u && ($u['status']??'')==='active' && password_verify((string)($_POST['password']??''),$u['password_hash'])){session_regenerate_id(true);$_SESSION['user_id']=$u['id'];if(!empty($u['business_id'])){ $bs=db()->prepare('SELECT status,account_status FROM businesses WHERE id=? LIMIT 1');$bs->execute([(int)$u['business_id']]);$b=$bs->fetch();if($b&&((($b['status']??'active')!=='active')||(($b['account_status']??'active')!=='active')))redirect('account.php');}redirect('index.php');}
 $err='Invalid email or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#F7F0E3"><title>Log in · SERV</title><link rel="stylesheet" href="assets/css/app.css?v=2.3.0"></head><body class="auth-page"><main class="auth-shell"><header class="auth-top"><a class="auth-brand" href="login.php">SERV</a><a class="auth-link" href="signup.php">Sign up</a></header><section class="auth-card auth-login"><div class="auth-eyebrow">SERV Hospitality Platform</div><h1>Log in</h1><p class="auth-sub">Welcome back. Sign in to your account.</p><?php if($err):?><div class="alert error"><?=e($err)?></div><?php endif;?><form method="post" autocomplete="on"><?=csrf_field()?><label>Your email<input type="email" name="email" autocomplete="email" required></label><label class="password-field">Password<input id="login-password" type="password" name="password" autocomplete="current-password" required><button type="button" class="password-toggle" onclick="var i=document.getElementById('login-password');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'◉':'◌'" aria-label="Show password">◉</button></label><div class="auth-forgot"><span></span><span>Keep your password private.</span></div><button class="auth-submit" type="submit">Log in</button></form><p class="auth-switch">Don't have an account? <a href="signup.php">Sign up</a></p><p class="auth-terms">By signing in you agree to our terms of use and privacy policy.</p></section></main></body></html>
