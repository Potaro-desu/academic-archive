<?php
require_once __DIR__.'/../config/bootstrap.php';
require_login();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');}
verify_csrf();
$user=current_user(); $title=trim((string)($_POST['title']??'')); $description=trim((string)($_POST['description']??'')); $keywords=trim((string)($_POST['keywords']??'')); $category=(int)($_POST['category_id']??0); $year=trim((string)($_POST['academic_year']??'')); $type=$_POST['resource_type']??''; $visibility=$_POST['visibility']??'public';
$errors=[];
if($title===''||mb_strlen($title)>180)$errors[]='Title is required and must be under 180 characters.';
if($description===''||mb_strlen($description)>3000)$errors[]='Description is required and must be under 3,000 characters.';
$check=$pdo->prepare('SELECT id FROM categories WHERE id=?');$check->execute([$category]);if(!$check->fetch())$errors[]='Choose a valid category.';
if(!in_array($type,['file','link'],true)||!in_array($visibility,['public','private'],true))$errors[]='Choose a valid resource type and visibility.';
if(!valid_academic_year($year))$errors[]='Academic year must be in the form YYYY-YYYY, for example 2025-2026.';
$resourceType=$type; $original=null; $stored=null; $mime=null; $size=null; $external=null;
if($type==='link'){
 $external=trim((string)($_POST['external_url']??''));$parts=parse_url($external);
 if(!$parts||!filter_var($external,FILTER_VALIDATE_URL)||!in_array(strtolower($parts['scheme']??''),['http','https'],true)||empty($parts['host']))$errors[]='Enter a valid HTTP or HTTPS URL.';
}else{
 $f=$_FILES['resource_file']??null;
 if(!$f||$f['error']===UPLOAD_ERR_NO_FILE)$errors[]='Choose a file to upload.';
 elseif($f['error']!==UPLOAD_ERR_OK)$errors[]='The file upload failed. Try again.';
 else{
  $max=100*1024*1024;$original=basename((string)$f['name']);$ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
  if($f['size']<1||$f['size']>$max)$errors[]='File must be larger than 0 bytes and no more than 100 MB.';
  if(!in_array($ext,allowed_extensions(),true))$errors[]='This file extension is not allowed.';
  if(!is_uploaded_file($f['tmp_name']))$errors[]='Invalid upload.';
  if(!$errors){
    $fi=new finfo(FILEINFO_MIME_TYPE);
    $detected=$fi->file($f['tmp_name'])?:'application/octet-stream';
    if(!mime_matches_extension($ext,$detected))$errors[]='The file content does not match its extension (detected: '.$detected.'). Please upload a real .'.$ext.' file.';
    else{$mime=$detected;$size=(int)$f['size'];$stored=bin2hex(random_bytes(24)).'.'.$ext;}
  }
 }
}
if($errors){
 if(wants_json())json_response(['success'=>false,'message'=>implode(' ',$errors)],422);
 flash('error',implode(' ',$errors));redirect('../submit.php');
}
$publishNow=is_admin()&&!empty($_POST['publish_now']);
$status=$publishNow?'approved':'pending';$published=$status==='approved'?date('Y-m-d H:i:s'):null;
$recordId=0;
try{
 $pdo->beginTransaction();
 $stmt=$pdo->prepare('INSERT INTO submissions(user_id,category_id,title,description,keywords,academic_year,resource_type,original_name,stored_name,mime_type,file_size,external_url,visibility,status,published_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $stmt->execute([(int)$user['id'],$category,$title,$description,$keywords,$year?:null,$resourceType,$original,$stored,$mime,$size,$external,$visibility,$status,$published]);
 $id=(int)$pdo->lastInsertId(); $recordId=$id;
 if($type==='file'){
  $folder=__DIR__.'/../uploads/'.($visibility==='public'?'public':'private');
  if(!is_dir($folder)&&!mkdir($folder,0750,true))throw new RuntimeException('Could not create upload directory.');
  if(!move_uploaded_file($_FILES['resource_file']['tmp_name'],$folder.'/'.$stored))throw new RuntimeException('Could not store uploaded file.');
 }
 audit($pdo,(int)$user['id'],is_admin()?'admin_record_created':'submission_created',$id,['visibility'=>$visibility,'type'=>$type,'status'=>$status,'publish_now'=>$publishNow]);
 $pdo->commit();
 $msg=$status==='approved'?'Archive record created and published.':'Your resource was submitted and is waiting for admin review.';
 if(wants_json())json_response(['success'=>true,'id'=>$id,'status'=>$status,'message'=>$msg,'url'=>'view-resource.php?id='.$id,'redirect'=>'../dashboard.php']);
 flash('success',$msg);redirect('../dashboard.php');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($stored){@unlink(__DIR__.'/../uploads/'.($visibility==='public'?'public':'private').'/'.$stored);}if(wants_json())json_response(['success'=>false,'message'=>'Could not save the resource. Please check storage permissions and try again.'],500);flash('error','Could not save the resource. Please check storage permissions and try again.');redirect('../submit.php');}
