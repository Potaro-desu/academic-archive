<?php
require_once __DIR__.'/config/bootstrap.php';
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
$stmt=$pdo->prepare($sql); $stmt->execute($params); $items=$stmt->fetchAll();
$collectionStats=$pdo->query("SELECT COUNT(*) AS resources,COUNT(DISTINCT category_id) AS subjects
        FROM submissions WHERE status='approved' AND visibility='public'")->fetch();
$categoriesStmt=$pdo->query("SELECT c.id,c.name,COUNT(s.id) AS resource_count
        FROM categories c
        LEFT JOIN submissions s ON s.category_id=c.id AND s.status='approved' AND s.visibility='public'
        GROUP BY c.id,c.name
        ORDER BY resource_count DESC,c.name ASC
        LIMIT 4");
$featuredCategories=$categoriesStmt->fetchAll();
render_header('Public Archive');
?>
<main id="main" class="home-page">
<section class="home-hero"><div class="home-container home-hero-grid"><div class="home-intro"><p class="home-kicker"><span></span> ISPSC · STA. MARIA CAMPUS</p><h1>Knowledge made <em>part of our story.</em></h1><p class="home-lede">A trusted home for the research, projects, and learning resources created across our campus community.</p><div class="home-hero-actions"><a class="home-button home-button-primary" href="#catalog">Browse the collection <span aria-hidden="true">↓</span></a><a class="home-text-link" href="<?=current_user()?'submit.php':'login.php'?>">Contribute your work <span aria-hidden="true">↗</span></a></div><p class="home-privacy-note"><span aria-hidden="true">✓</span> Curated academic work · Public records only</p></div><aside class="repository-plate" aria-label="About the campus repository"><div class="plate-top"><span>THE CAMPUS REPOSITORY</span><span class="plate-rule"></span><span>EST. 1998</span></div><div class="plate-seal"><img src="assets/images/ispsc-logo.png" alt="" width="90" height="90"></div><p class="plate-campus">STA. MARIA CAMPUS</p><h2>Ideas that serve.<br>Knowledge that stays.</h2><div class="plate-bottom"><span>RESEARCH</span><span>LEARNING</span><span>COMMUNITY</span></div><span class="plate-index" aria-hidden="true">01</span></aside></div></section>

<section class="collection-ribbon" aria-label="Collection statistics"><div class="home-container ribbon-inner"><p><span class="ribbon-mark"></span> A growing record of campus scholarship</p><div class="ribbon-stat"><strong><?= (int)$collectionStats['resources'] ?></strong><span>published resources</span></div><div class="ribbon-stat"><strong><?= (int)$collectionStats['subjects'] ?></strong><span>subject areas</span></div><div class="ribbon-stat"><strong>ISPSC</strong><span>Sta. Maria Campus</span></div></div></section>

<section class="home-container home-catalog" id="catalog"><div class="catalog-heading"><div><p class="home-kicker">THE OPEN COLLECTION</p><h2>Find something worth keeping.</h2><p>Search approved research, teaching materials, and campus resources.</p></div><span class="catalog-count"><?= (int)$collectionStats['resources'] ?> records</span></div>
<form class="archive-search" method="get" action="index.php"><label class="archive-query"><span class="search-lens" aria-hidden="true"></span><span class="sr-only">Search the archive</span><input name="q" value="<?=e($q)?>" placeholder="Search by title, topic, or keyword" autocomplete="off"></label><label class="archive-filter"><span class="sr-only">Subject category</span><select name="category"><option value="0">All subjects</option><?php foreach(category_rows($pdo) as $c): ?><option value="<?=$c['id']?>" <?=$category===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach; ?></select></label><label class="archive-filter"><span class="sr-only">Resource type</span><select name="type"><option value="">All formats</option><option value="file" <?=$type==='file'?'selected':''?>>Documents &amp; media</option><option value="link" <?=$type==='link'?'selected':''?>>Web resources</option></select></label><label class="archive-filter"><span class="sr-only">Academic year</span><select name="year"><option value="">All years</option><?php foreach($yearRows as $y): ?><option value="<?=e($y)?>" <?=$year===$y?'selected':''?>><?=e($y)?></option><?php endforeach; ?></select></label><label class="archive-filter"><span class="sr-only">Sort order</span><select name="sort"><option value="newest" <?=$sort==='newest'?'selected':''?>>Newest first</option><option value="oldest" <?=$sort==='oldest'?'selected':''?>>Oldest first</option><option value="title" <?=$sort==='title'?'selected':''?>>Title A to Z</option></select></label><button class="home-button home-search-button" type="submit">Search archive <span aria-hidden="true">→</span></button><?php if ($q!==''||$category>0||$type!==''||$year!==''||$sort!=='newest'): ?><a class="clear-search" href="index.php">Clear filters</a><?php endif; ?></form>
<p class="search-status" id="search-status" aria-live="polite"></p>

<div class="subject-shortcuts"><div class="shortcuts-label"><span>Browse by subject</span><span>SELECT A COLLECTION</span></div><div class="subject-grid"><?php foreach($featuredCategories as $index=>$subject): ?><a class="subject-card subject-card-<?=$index+1?>" href="index.php?category=<?=(int)$subject['id']?>#catalog"><span class="subject-number">0<?=$index+1?></span><span class="subject-name"><?=e($subject['name'])?></span><span class="subject-count"><?= (int)$subject['resource_count'] ?> <?= (int)$subject['resource_count']===1?'record':'records' ?></span><span class="subject-arrow" aria-hidden="true">↗</span></a><?php endforeach; ?></div></div>

<div class="recent-heading"><div><p class="home-kicker"><?= $q!==''||$category>0||$type!==''||$year!==''||$sort!=='newest'?'SEARCH RESULTS':'RECENTLY PUBLISHED' ?></p><h2><?= $q!==''||$category>0||$type!==''||$year!==''||$sort!=='newest'?'Matching resources':'Latest additions' ?></h2></div><span class="catalog-count"><?=count($items)?> shown</span></div>
<div id="catalog-results"><?php if (!$items): ?><div class="home-empty"><span class="empty-search-icon" aria-hidden="true"></span><h3>No matching resources yet</h3><p>Try a different search or browse another subject area.</p><a class="home-text-link" href="index.php#catalog">See the full collection →</a></div><?php else: ?><div class="home-resource-grid"><?php foreach($items as $item): $tile=resource_tile($item['resource_type'],(string)($item['original_name']??'')); $isImage=is_image_resource($item['resource_type'],(string)($item['original_name']??'')); ?><article class="home-resource-card"><?php if($isImage): ?><a class="card-thumb" href="view-resource.php?id=<?=(int)$item['id']?>"><img src="api/download.php?id=<?=(int)$item['id']?>&amp;inline=1" alt="<?=e($item['title'])?>" loading="lazy"></a><?php else: ?><a class="card-thumb card-tile <?=$tile['class']?>" href="view-resource.php?id=<?=(int)$item['id']?>"><span><?=e($tile['label'])?></span></a><?php endif; ?><div class="card-body"><div class="home-resource-meta"><span><?=e($item['category_name']??'General')?></span><span class="meta-separator">/</span><span><?=strtoupper(e($item['resource_type']==='link'?'WEB RESOURCE':(pathinfo($item['original_name']??'',PATHINFO_EXTENSION) ?: 'DOCUMENT')))?></span></div><h3><a href="view-resource.php?id=<?=(int)$item['id']?>"><?=e($item['title'])?></a></h3><p><?=e(mb_strimwidth((string)$item['description'],0,140,'…'))?></p><div class="home-resource-byline"><span><?=e($item['author']??'Campus contributor')?></span><span><?=e($item['academic_year']??'Academic collection')?></span></div></div></article><?php endforeach; ?></div><?php endif; ?></div>
</section>

<section class="home-contribute"><div class="home-container contribute-inner"><div><p class="home-kicker">PRESERVE WHAT YOU CREATE</p><h2>Good work deserves a lasting place.</h2><p>Students and faculty can submit research and learning resources for review by the archive team.</p></div><a class="home-button home-button-light" href="<?=current_user()?'submit.php':'login.php'?>">Submit academic work <span aria-hidden="true">→</span></a></div></section>
</main>
<?php render_footer(); ?>
