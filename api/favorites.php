<?php
// Favorite / unfavorite a record. POST + CSRF. The user must be allowed to
// open the record (same rule as the detail page). Toggles one favorite row.
require_once __DIR__.'/../config/bootstrap.php';
require_login();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');}
verify_csrf();
$user=current_user();
$id=(int)($_POST['submission_id']??0);
$stmt=$pdo->prepare('SELECT s.id,s.user_id,s.visibility,s.status,s.resource_type,u.role AS author_role FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?');
$stmt->execute([$id]);$item=$stmt->fetch();
if(!$item){flash('error','Record not found.');redirect('../dashboard.php');}
$allowed=($item['status']==='approved'&&$item['visibility']==='public')||((int)$user['id']===(int)$item['user_id']||is_admin())||can_review_submission($item);
if(!$allowed){http_response_code(403);exit('403 Forbidden — you are not allowed to access this resource.');}
$fav=is_favorited($pdo,(int)$user['id'],$id);
if($fav){
    $del=$pdo->prepare('DELETE FROM favorites WHERE user_id=? AND submission_id=?');
    $del->execute([(int)$user['id'],$id]);
    audit($pdo,(int)$user['id'],'favorite_removed',$id);
    $msg='Removed from your favorites.';
} else {
    $ins=$pdo->prepare('INSERT IGNORE INTO favorites(user_id,submission_id) VALUES(?,?)');
    $ins->execute([(int)$user['id'],$id]);
    audit($pdo,(int)$user['id'],'favorite_added',$id);
    $msg='Added to your favorites.';
}
if(wants_json())json_response(['success'=>true,'favorited'=>!$fav,'message'=>$msg]);
flash('success',$msg);
redirect('../view-resource.php?id='.$id);
