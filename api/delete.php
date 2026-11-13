<?php
require_once __DIR__.'/../config/bootstrap.php';
require_login();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');}
verify_csrf();
$user=current_user();
$id=(int)($_POST['submission_id']??0);
$stmt=$pdo->prepare('SELECT s.*,u.role AS author_role FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?');
$stmt->execute([$id]);$item=$stmt->fetch();
if(!$item){flash('error','Submission not found.');redirect('../dashboard.php');}
if(!can_delete_submission($item)){http_response_code(403);exit('403 Forbidden — you are not allowed to delete this record.');}
try{
    $pdo->beginTransaction();
    audit($pdo,(int)$user['id'],'submission_deleted',$id,['title'=>$item['title'],'status'=>$item['status']]);
    if($item['resource_type']==='file'&&!empty($item['stored_name'])&&preg_match('/^[a-f0-9]{48}\.[a-z0-9]{1,8}$/',$item['stored_name'])){
        @unlink(__DIR__.'/../uploads/'.($item['visibility']==='public'?'public':'private').'/'.$item['stored_name']);
    }
    $del=$pdo->prepare('DELETE FROM submissions WHERE id=?');$del->execute([$id]);
    $pdo->commit();
    flash('success','The record was deleted.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    flash('error','Could not delete the record. Please try again.');
}
redirect('../dashboard.php');
