<?php
// JSON search API for the public catalog.
// Same permission rules as index.php: only Approved + Public records.
// GET parameters: q, category, type (file|link), year, sort (newest|oldest|title).
// Response: {"success":true,"data":[...],"count":N}
// Errors: {"success":false,"message":"..."} with an HTTP 405 for non-GET requests.
require_once __DIR__.'/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);echo json_encode(['success'=>false,'message'=>'Method not allowed.']);exit;}
$q = trim((string)($_GET['q'] ?? ''));
$category = (int)($_GET['category'] ?? 0);
$type = trim((string)($_GET['type'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'newest'));
if (!in_array($sort, ['newest', 'oldest', 'title'], true)) $sort = 'newest';
$year = trim((string)($_GET['year'] ?? ''));
$yearRows = $pdo->query("SELECT DISTINCT academic_year FROM submissions WHERE status='approved' AND visibility='public' AND academic_year IS NOT NULL AND academic_year<>'' ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
if ($year !== '' && !in_array($year, $yearRows, true)) $year = '';
$sql = "SELECT s.id,s.title,s.description,s.resource_type,s.original_name,s.external_url,s.academic_year,s.created_at,c.name AS category_name,u.name AS author
        FROM submissions s LEFT JOIN categories c ON c.id=s.category_id LEFT JOIN users u ON u.id=s.user_id
        WHERE s.status='approved' AND s.visibility='public'";
$params = [];
if ($q !== '') { $sql .= " AND (s.title LIKE ? OR s.description LIKE ? OR s.keywords LIKE ?)"; $like='%'.$q.'%'; array_push($params,$like,$like,$like); }
if ($category > 0) { $sql .= " AND s.category_id=?"; $params[]=$category; }
if ($type !== '' && in_array($type,['file','link'],true)) { $sql .= " AND s.resource_type=?"; $params[]=$type; }
if ($year !== '') { $sql .= " AND s.academic_year=?"; $params[]=$year; }
$sql .= $sort === 'oldest' ? " ORDER BY s.created_at ASC LIMIT 60" : ($sort === 'title' ? " ORDER BY s.title ASC LIMIT 60" : " ORDER BY s.published_at DESC, s.created_at DESC LIMIT 60");
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
$data=[];
foreach($rows as $row){
    $ext = $row['resource_type']==='link' ? 'WEB RESOURCE' : strtoupper(pathinfo((string)($row['original_name']??''),PATHINFO_EXTENSION) ?: 'DOCUMENT');
    $tile = resource_tile($row['resource_type'], (string)($row['original_name'] ?? ''));
    $isImage = is_image_resource($row['resource_type'], (string)($row['original_name'] ?? ''));
    $data[]=[
        'id'=>(int)$row['id'],
        'title'=>$row['title'],
        'description'=>mb_strimwidth((string)$row['description'],0,140,'…'),
        'category'=>$row['category_name']??'General',
        'file_type'=>$ext,
        'academic_year'=>$row['academic_year']??'Academic collection',
        'author'=>$row['author']??'Campus contributor',
        'resource_type'=>$row['resource_type'],
        'url'=>'view-resource.php?id='.(int)$row['id'],
        'thumbnail_url'=>$isImage ? 'api/download.php?id='.(int)$row['id'].'&inline=1' : null,
        'tile_label'=>$tile['label'],
        'tile_class'=>$tile['class'],
    ];
}
echo json_encode(['success'=>true,'data'=>$data,'count'=>count($data)], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
