<?php
require __DIR__.'/config.php';
$u=require_login();
if(is_super_admin())redirect('admin.php');
$b=require_business();
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $action=(string)($_POST['action']??'');
        if($action==='submit_payment'){
            if(($b['account_status']??'active')==='active')throw new RuntimeException('Your account is already active.');
            $s=$pdo->prepare("SELECT s.*,p.name plan_name,p.price plan_price,p.status plan_status FROM subscriptions s LEFT JOIN plans p ON p.id=s.plan_id WHERE s.business_id=? ORDER BY s.id DESC LIMIT 1");$s->execute([$b['id']]);$sub=$s->fetch();
            if(!$sub||empty($sub['plan_id'])||($sub['plan_status']??'')!=='active')throw new RuntimeException('Your selected plan is no longer available. Please contact SERV administration.');
            $pending=$pdo->prepare("SELECT id FROM payment_submissions WHERE business_id=? AND status='pending' LIMIT 1");$pending->execute([$b['id']]);if($pending->fetchColumn())throw new RuntimeException('A payment is already awaiting verification.');
            $file=$_FILES['proof']??null;
            if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Please choose a payment receipt image or PDF.');
            if((int)$file['size']>5*1024*1024)throw new RuntimeException('Payment proof must be 5 MB or smaller.');
            $tmp=(string)$file['tmp_name'];$original=(string)$file['name'];$ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
            $allowed=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','pdf'=>'application/pdf'];if(!isset($allowed[$ext]))throw new RuntimeException('Only JPG, PNG or PDF payment proof is accepted.');
            $mime='';if(function_exists('finfo_open')){$fi=finfo_open(FILEINFO_MIME_TYPE);$mime=(string)finfo_file($fi,$tmp);finfo_close($fi);}else{$mime=(string)($file['type']??'');}
            if($mime!==$allowed[$ext])throw new RuntimeException('The uploaded file type does not match its extension.');
            if(str_starts_with($mime,'image/') && @getimagesize($tmp)===false)throw new RuntimeException('The uploaded image could not be verified.');
            $dir=__DIR__.'/uploads/payment_proofs';if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('SERV could not create the payment proof storage folder.');
            if(!is_writable($dir))throw new RuntimeException('Payment proof storage is not writable.');
            $filename='payment_'.bin2hex(random_bytes(16)).'.'.$ext;$path=$dir.'/'.$filename;
            if(!move_uploaded_file($tmp,$path))throw new RuntimeException('SERV could not save the payment proof. Please try again.');
            $reference=trim((string)($_POST['payment_reference']??''));if($reference!==''&&strlen($reference)>150)throw new RuntimeException('Payment reference is too long.');
            $relative='uploads/payment_proofs/'.$filename;
            $s=$pdo->prepare("INSERT INTO payment_submissions(business_id,user_id,subscription_id,plan_id,amount,payment_reference,proof_path,proof_original_name,proof_mime,status,submitted_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,'pending',NOW(),NOW())");
            $s->execute([$b['id'],$u['id'],$sub['id'],$sub['plan_id'],(float)$sub['plan_price'],$reference?:null,$relative,basename($original),$mime]);
            $s=$pdo->prepare("UPDATE subscriptions SET status='suspended',amount=?,payment_reference=? WHERE id=?");$s->execute([(float)$sub['plan_price'],$reference?:null,$sub['id']]);
            audit((int)$b['id'],(int)$u['id'],'payment.submitted','₦'.number_format((float)$sub['plan_price'],2).' · '.$sub['plan_name']);
            flash('Payment proof submitted. SERV administration will verify it before activating your account.');
        }
    }catch(Throwable $e){if(is_string($path??null)&&$path!==''&&is_file($path))@unlink($path);flash($e->getMessage(),'error');}
    redirect('account.php');
}
$s=$pdo->prepare("SELECT s.*,p.name plan_name,p.price plan_price,p.features plan_features,p.status plan_status FROM subscriptions s LEFT JOIN plans p ON p.id=s.plan_id WHERE s.business_id=? ORDER BY s.id DESC LIMIT 1");$s->execute([$b['id']]);$sub=$s->fetch();
$s=$pdo->prepare("SELECT * FROM payment_submissions WHERE business_id=? ORDER BY id DESC LIMIT 5");$s->execute([$b['id']]);$payments=$s->fetchAll();
$pay=payment_instructions();$status=$b['account_status']??'active';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#F7F0E3"><title>Account · SERV</title><link rel="stylesheet" href="assets/css/app.css?v=2.3.0"></head><body class="auth-page account-page"><main class="auth-shell"><header class="auth-top"><a class="auth-brand" href="account.php">SERV</a><a class="auth-link" href="logout.php">Sign out</a></header><section class="account-card">
<div class="account-status-icon"><?= $status==='active'?'✓':'!' ?></div><div class="auth-eyebrow">Account</div><h1><?= $status==='active'?'Account active':'Account not active' ?></h1>
<?php if($status==='active'):?><p class="auth-sub">Your SERV account is active. Continue to your business workspace.</p><a class="auth-submit account-button" href="index.php">Open SERV dashboard</a><?php elseif($status==='suspended'):?><p class="auth-sub">Your business account is currently suspended. Please contact SERV administration for assistance.</p><?php else:?><p class="auth-sub">Your registration is complete. Select payment details below, make your payment, and upload the receipt. SERV administration will verify it before activation.</p><?php endif;?>
<?php if($sub):?><div class="account-summary"><div><span>Business</span><b><?=e($b['name'])?></b></div><div><span>Plan</span><b><?=e($sub['plan_name']??'Selected plan')?></b></div><div><span>Amount</span><b>₦<?=number_format((float)($sub['plan_price']??$sub['amount']),0)?> / month</b></div><div><span>Status</span><b class="status-pill status-<?=e($status)?>"><?=e(str_replace('_',' ',ucwords($status)))?></b></div></div><?php endif;?>
<?php if($status!=='active' && $status!=='suspended'):?><div class="payment-panel"><div class="auth-section-title">Payment instructions</div><p class="small muted"><?=e($pay['instructions'])?></p><?php if($pay['bank_name']||$pay['account_name']||$pay['account_number']):?><div class="bank-details"><?php if($pay['bank_name']):?><div><span>Bank</span><b><?=e($pay['bank_name'])?></b></div><?php endif;?><?php if($pay['account_name']):?><div><span>Account name</span><b><?=e($pay['account_name'])?></b></div><?php endif;?><?php if($pay['account_number']):?><div><span>Account number</span><b><?=e($pay['account_number'])?></b></div><?php endif;?></div><?php else:?><div class="alert error">Payment account details have not been configured yet. Please contact SERV administration.</div><?php endif;?></div>
<div class="payment-panel"><div class="auth-section-title">Upload payment proof</div><form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="submit_payment"><label>Payment reference <span class="muted">(optional)</span><input name="payment_reference" maxlength="150" placeholder="Transaction ID / transfer reference"></label><label>Receipt / payment slip<input type="file" name="proof" accept="image/jpeg,image/png,application/pdf" required></label><p class="small muted">JPG, PNG or PDF · maximum 5 MB.</p><button class="auth-submit" type="submit" <?=(!$pay['bank_name']&&!$pay['account_number'])?'disabled':''?>>Submit payment for verification</button></form></div><?php endif;?>
<?php if($payments):?><div class="payment-panel"><div class="auth-section-title">Payment history</div><?php foreach($payments as $p):?><div class="payment-history-row"><div><b><?=e($p['payment_reference']?:'Payment submission')?></b><span><?=e(date('d M Y, H:i',strtotime($p['submitted_at'])))?> · ₦<?=number_format((float)$p['amount'],0)?></span></div><span class="status-pill status-<?=e($p['status'])?>"><?=e(ucfirst($p['status']))?></span></div><?php if($p['status']==='rejected'&&$p['review_note']):?><div class="reject-note">Reason: <?=e($p['review_note'])?></div><?php endif;?><?php endforeach;?></div><?php endif;?>
<p class="auth-switch"><a href="logout.php">Sign out</a></p></section></main></body></html>
