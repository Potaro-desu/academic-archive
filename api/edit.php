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
if(!can_edit_submission($item)){http_response_code(403);exit('403 Forbidden — you are not allowed to edit this record.');}
$isOwner=(int)$user['id']===(int)$item['user_id'];
$title=trim((string)($_POST['title']??'')); $description=trim((string)($_POST['description']??'')); $keywords=trim((string)($_POST['keywords']??'')); $category=(int)($_POST['category_id']??0); $year=trim((string)($_POST['academic_year']??'')); $type=$_POST['resource_type']??''; $visibility=$_POST['visibility']??'public';
$errors=[];
if($title===''||mb_strlen($title)>180)$errors[]='Title is required and must be under 180 characters.';
if($description===''||mb_strlen($description)>3000)$errors[]='Description is required and must be under 3,000 characters.';
$check=$pdo->prepare('SELECT id FROM categories WHERE id=?');$check->execute([$category]);if(!$check->fetch())$errors[]='Choose a valid category.';
if(!in_array($type,['file','link'],true)||!in_array($visibility,['public','private'],true))$errors[]='Choose a valid resource type and visibility.';
if(!valid_academic_year($year))$errors[]='Academic year must be in the form YYYY-YYYY, for example 2025-2026.';
$original=$item['original_name']; $stored=$item['stored_name']; $mime=$item['mime_type']; $size=$item['file_size']; $external=$item['external_url'];
$newStored=null; $newFolder=null; $oldStoredToDelete=null; $oldStoredVisibility=$item['visibility'];
if($type==='link'){
    $newUrl=trim((string)($_POST['external_url']??''));
    if($newUrl!==''){
        $parts=parse_url($newUrl);
        if(!$parts||!filter_var($newUrl,FILTER_VALIDATE_URL)||!in_array(strtolower($parts['scheme']??''),['http','https'],true)||empty($parts['host']))$errors[]='Enter a valid HTTP or HTTPS URL.';
        else $external=$newUrl;
    } elseif($item['resource_type']!=='link') {
        $errors[]='Choose a web link when the resource type is a link.';
    }
    if($item['resource_type']==='file'&&$newUrl!==''){$original=null;$stored=null;$mime=null;$size=null;$oldStoredToDelete=$item['stored_name'];}
}else{
    $f=$_FILES['resource_file']??null;
    if($f&&$f['error']!==UPLOAD_ERR_NO_FILE){
        if($f['error']!==UPLOAD_ERR_OK)$errors[]='The file upload failed. Try again.';
        else{
            $max=100*1024*1024;$newOriginal=basename((string)$f['name']);$ext=strtolower(pathinfo($newOriginal,PATHINFO_EXTENSION));
            if($f['size']<1||$f['size']>$max)$errors[]='File must be larger than 0 bytes and no more than 100 MB.';
            if(!in_array($ext,allowed_extensions(),true))$errors[]='This file extension is not allowed.';
            if(!is_uploaded_file($f['tmp_name']))$errors[]='Invalid upload.';
            if(!$errors){
                $fi=new finfo(FILEINFO_MIME_TYPE);
                $detected=$fi->file($f['tmp_name'])?:'application/octet-stream';
                if(!mime_matches_extension($ext,$detected))$errors[]='The file content does not match its extension (detected: '.$detected.'). Please upload a real .'.$ext.' file.';
                else{
                    $mime=$detected;$size=(int)$f['size'];
                    $newStored=bin2hex(random_bytes(24)).'.'.$ext;$newFolder=__DIR__.'/../uploads/'.($visibility==='public'?'public':'private');
                    $original=$newOriginal;$external=null;
                }
            }
        }
    } elseif($item['resource_type']!=='file') {
        $errors[]='Choose a file to upload when the resource type is a file.';
    }
}
if($errors){flash('error',implode(' ',$errors));redirect('../edit.php?id='.$id);}
// Status rules: an owner resubmitting a Returned item sends it back to Pending
// and clears the old feedback. A Pending item stays Pending. An admin edit
// never changes the status.
$status=$item['status']; $feedback=$item['feedback']; $published=$item['published_at'];
if($isOwner&&$item['status']==='returned'){$status='pending';$feedback=null;$published=null;}
try{
    $pdo->beginTransaction();
    if($newStored!==null){
        if(!is_dir($newFolder)&&!mkdir($newFolder,0750,true))throw new RuntimeException('Could not create upload directory.');
        if(!move_uploaded_file($_FILES['resource_file']['tmp_name'],$newFolder.'/'.$newStored))throw new RuntimeException('Could not store uploaded file.');
        if($item['stored_name']){@unlink(__DIR__.'/../uploads/'.($item['visibility']==='public'?'public':'private').'/'.$item['stored_name']);}
        $stored=$newStored;
    } elseif($oldStoredToDelete!==null){
        @unlink(__DIR__.'/../uploads/'.($oldStoredVisibility==='public'?'public':'private').'/'.$oldStoredToDelete);
    } elseif($item['resource_type']==='file'&&$item['stored_name']&&$visibility!==$item['visibility']){
        // Visibility changed: move the stored file to the matching folder at once.
        $oldFolder=__DIR__.'/../uploads/'.($item['visibility']==='public'?'public':'private');
        $destFolder=__DIR__.'/../uploads/'.($visibility==='public'?'public':'private');
        if(!is_dir($destFolder)&&!mkdir($destFolder,0750,true))throw new RuntimeException('Could not create upload directory.');
        if(!@rename($oldFolder.'/'.$item['stored_name'],$destFolder.'/'.$item['stored_name']))throw new RuntimeException('Could not move the stored file.');
    }
    $upd=$pdo->prepare('UPDATE submissions SET title=?,description=?,keywords=?,category_id=?,academic_year=?,resource_type=?,original_name=?,stored_name=?,mime_type=?,file_size=?,external_url=?,visibility=?,status=?,feedback=?,published_at=? WHERE id=?');
    $upd->execute([$title,$description,$keywords,$category,$year?:null,$type,$original,$stored,$mime,$size,$external,$visibility,$status,$feedback,$published,$id]);
    audit($pdo,(int)$user['id'],'submission_edited',$id,['status'=>$status,'visibility'=>$visibility]);
    $pdo->commit();
    flash('success',$status==='pending'&&$item['status']==='returned'?'Your resource was updated and sent back for review.':'Your resource was updated.');
    redirect('../view-resource.php?id='.$id);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    if($newStored!==null&&$newFolder!==null){@unlink($newFolder.'/'.$newStored);}
    flash('error','Could not save your changes. Please check storage permissions and try again.');redirect('../edit.php?id='.$id);
}
