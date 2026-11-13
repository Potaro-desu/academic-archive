<?php
require_once __DIR__.'/../config/bootstrap.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit('Method not allowed'); }
verify_csrf();
$action=$_POST['action']??'';
if($action==='logout'){ $_SESSION=[]; if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',$p['secure'],$p['httponly']); } session_destroy(); header('Location: ../index.php'); exit; }
if($action==='login'){
 $email=strtolower(trim((string)($_POST['email']??''))); $password=(string)($_POST['password']??'');
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$password===''){ flash('error','Enter a valid email and password.'); redirect('../login.php'); }
 $stmt=$pdo->prepare('SELECT id,name,email,password_hash,role,failed_logins,locked_until FROM users WHERE email=? AND is_active=1'); $stmt->execute([$email]); $user=$stmt->fetch();
 // Locked accounts are refused without revealing the exact unlock time.
 if($user && !empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time()){
   audit($pdo,(int)$user['id'],'login_locked');
   flash('error','This account is temporarily locked after too many failed sign-in attempts. Please try again in a few minutes.');
   redirect('../login.php');
 }
 if(!$user||!password_verify($password,$user['password_hash'])){
   usleep(350000);
   if($user){
     $fails=(int)($user['failed_logins']??0)+1;
     if($fails>=5){
       $upd=$pdo->prepare('UPDATE users SET failed_logins=?, locked_until=DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id=?');
       $upd->execute([$fails,(int)$user['id']]);
       audit($pdo,(int)$user['id'],'account_locked',null,['failed_logins'=>$fails]);
     } else {
       $upd=$pdo->prepare('UPDATE users SET failed_logins=? WHERE id=?');
       $upd->execute([$fails,(int)$user['id']]);
     }
   }
   flash('error','Email or password is incorrect.'); redirect('../login.php');
 }
 // Successful login clears the failure counter and any lock.
 $upd=$pdo->prepare('UPDATE users SET failed_logins=0, locked_until=NULL WHERE id=?'); $upd->execute([(int)$user['id']]);
 session_regenerate_id(true); unset($user['password_hash'], $user['failed_logins'], $user['locked_until']); $_SESSION['user']=$user; audit($pdo,(int)$user['id'],'login'); redirect('../dashboard.php');
}
if($action==='register'){
 $name=trim((string)($_POST['name']??'')); $email=strtolower(trim((string)($_POST['email']??''))); $role=(string)($_POST['role']??''); $password=(string)($_POST['password']??'');
 if(mb_strlen($name)<2||mb_strlen($name)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['student','faculty'],true)||strlen($password)<10||$password!==($_POST['password_confirm']??'')){ flash('error','Check your details. Use a valid email, student/faculty role, and matching password of at least 10 characters.'); redirect('../register.php'); }
 try{$stmt=$pdo->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)');$stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);$id=(int)$pdo->lastInsertId();audit($pdo,$id,'account_registered');$_SESSION['user']=['id'=>$id,'name'=>$name,'email'=>$email,'role'=>$role];session_regenerate_id(true);flash('success','Your account has been created.');redirect('../dashboard.php');}
 catch(PDOException $e){ if($e->getCode()==='23000'){flash('error','That email address is already registered.');redirect('../register.php');} throw $e; }
}
http_response_code(400); exit('Invalid action.');
