<?php
require_once __DIR__.'/../config/bootstrap.php';
require_login();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');}
verify_csrf();
$action=$_POST['action']??'';
if($action==='mark_all_read'){
    $stmt=$pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0');
    $stmt->execute([(int)current_user()['id']]);
    flash('success','All notifications marked as read.');
}
redirect('../dashboard.php');
