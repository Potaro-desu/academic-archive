<?php
require_once __DIR__.'/../config/bootstrap.php';
$id=(int)($_GET['id']??0);$stmt=$pdo->prepare('SELECT s.id,s.user_id,s.visibility,s.status,s.resource_type,s.stored_name,s.original_name,s.mime_type,s.file_size,u.role AS author_role FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?');$stmt->execute([$id]);$item=$stmt->fetch();
if(!$item||$item['resource_type']!=='file'){http_response_code(404);exit('File not found.');}
$u=current_user();$allowed=($item['status']==='approved'&&$item['visibility']==='public')||($u&&((int)$u['id']===(int)$item['user_id']||is_admin()))||can_review_submission($item);
if(!$allowed){http_response_code(403);exit('403 Forbidden — you are not authorized to access this resource.');}
if(!preg_match('/^[a-f0-9]{48}\.[a-z0-9]{1,8}$/',$item['stored_name'])){http_response_code(404);exit('File not found.');}
$folder=__DIR__.'/../uploads/'.($item['visibility']==='public'?'public':'private');$path=$folder.'/'.$item['stored_name'];
if(!is_file($path)){http_response_code(404);exit('Stored file is missing.');}
$mime=$item['mime_type']?:'application/octet-stream';$inline=isset($_GET['inline'])&&$_GET['inline']==='1';
// A real download (not an inline preview) counts once, only after the
// permission check above has passed. Failed or forbidden requests never count.
if(!$inline){$cnt=$pdo->prepare('UPDATE submissions SET download_count=download_count+1 WHERE id=?');$cnt->execute([$id]);}
$inlineAllowed=in_array(strtolower(pathinfo($item['original_name'],PATHINFO_EXTENSION)),['pdf','mp4','webm','mp3','wav','m4a','jpg','jpeg','png','gif','webp'],true);
if(!$inline||!$inlineAllowed){header('Content-Disposition: attachment; filename="'.str_replace(['"',"\\","\r","\n"],'_',$item['original_name']).'"; filename*=UTF-8\'\''.rawurlencode($item['original_name']));}
else header('Content-Disposition: inline; filename="'.str_replace('"','_', $item['original_name']).'"');
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store, max-age=0');header('X-Content-Type-Options: nosniff');
readfile($path);exit;
