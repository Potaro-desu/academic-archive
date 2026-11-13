<?php
require_once __DIR__.'/../config/bootstrap.php';require_login();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');}verify_csrf();
$id=(int)($_POST['submission_id']??0);$decision=$_POST['decision']??'';$feedback=trim((string)($_POST['feedback']??''));
if($id<1||!in_array($decision,['approved','rejected','returned'],true)||mb_strlen($feedback)>1500){
 if(wants_json())json_response(['success'=>false,'message'=>'Invalid review details.'],400);
 flash('error','Invalid review details.');redirect('../dashboard.php');
}
$stmt=$pdo->prepare('SELECT s.id,s.user_id,s.status,s.feedback,s.visibility,u.role AS author_role FROM submissions s JOIN users u ON u.id=s.user_id WHERE s.id=?');$stmt->execute([$id]);$submission=$stmt->fetch();
if(!$submission){
 if(wants_json())json_response(['success'=>false,'message'=>'Submission not found.'],404);
 flash('error','Submission not found.');redirect('../dashboard.php');
}
if(!can_review_submission($submission)){
 if(wants_json())json_response(['success'=>false,'message'=>'Faculty may review only pending public student submissions.'],403);
 http_response_code(403);exit('403 Forbidden — faculty may review only pending public student submissions.');
}
if(in_array($decision,['rejected','returned'],true)&&$feedback===''){
 if(wants_json())json_response(['success'=>false,'message'=>'Feedback is required when rejecting or returning a submission. Tell the owner what to fix.'],422);
 flash('error','Feedback is required when rejecting or returning a submission. Tell the owner what to fix.');redirect('../view-resource.php?id='.$id);
}
$finalFeedback=$feedback!==''?$feedback:$submission['feedback'];
$published=$decision==='approved'?date('Y-m-d H:i:s'):null;
$upd=$pdo->prepare('UPDATE submissions SET status=?,feedback=?,reviewed_by=?,reviewed_at=NOW(),published_at=? WHERE id=?');$upd->execute([$decision,$finalFeedback?:null,(int)current_user()['id'],$published,$id]);
audit($pdo,(int)current_user()['id'],'submission_'.$decision,$id,['feedback'=>$finalFeedback]);
$titleStmt=$pdo->prepare('SELECT title FROM submissions WHERE id=?');$titleStmt->execute([$id]);$title=(string)($titleStmt->fetchColumn()?:'your submission');
$msg=$decision==='approved'?"Your submission '{$title}' was approved and published in the archive."
  :($decision==='returned'?"Your submission '{$title}' was returned for revision: {$finalFeedback}"
  :"Your submission '{$title}' was rejected: {$finalFeedback}");
notify_user($pdo,(int)$submission['user_id'],$id,$msg);
if(wants_json())json_response(['success'=>true,'status'=>$decision,'status_label'=>status_label($decision),'feedback'=>$finalFeedback,'message'=>'Review decision saved: '.status_label($decision).'.']);
flash('success','Review decision saved: '.status_label($decision).'.');redirect(is_admin()?'../view-resource.php?id='.$id:'../dashboard.php#faculty-review-queue');
