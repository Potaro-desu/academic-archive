<?php
// My Submissions - the signed-in user's own records on one page.
require_once __DIR__.'/config/bootstrap.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];

$mySort = $_GET['mysort'] ?? 'newest';
if (!in_array($mySort, ['newest', 'oldest', 'title'], true)) $mySort = 'newest';
$myVis = $_GET['myvis'] ?? '';
if (!in_array($myVis, ['', 'public', 'private'], true)) $myVis = '';
$favOnly = !empty($_GET['fav']);

$myOrder = $mySort === 'oldest' ? 's.created_at ASC' : ($mySort === 'title' ? 's.title ASC' : 's.created_at DESC');
$mySql = "SELECT s.*,c.name AS category_name FROM submissions s LEFT JOIN categories c ON c.id=s.category_id";
$myParams = [];
if ($favOnly) { $mySql .= " JOIN favorites f ON f.submission_id=s.id AND f.user_id=?"; $myParams[] = $userId; }
$mySql .= " WHERE s.user_id=?";
$myParams[] = $userId;
if ($myVis !== '') { $mySql .= " AND s.visibility=?"; $myParams[] = $myVis; }
$mySql .= " ORDER BY {$myOrder} LIMIT 100";
$stmt = $pdo->prepare($mySql);
$stmt->execute($myParams);
$items = $stmt->fetchAll();

$statsStmt = $pdo->prepare("SELECT COUNT(*) AS total,
    SUM(status='pending') AS pending,
    SUM(status='approved') AS approved,
    SUM(status='returned') AS returned,
    SUM(status='rejected') AS rejected
    FROM submissions WHERE user_id=?");
$statsStmt->execute([$userId]);
$counts = $statsStmt->fetch();

render_header('My submissions');
?>
<main id="main" class="section dashboard role-page submission-page role-<?=e($user['role'])?>"><div class="container">
    <div class="user-directory-heading">
        <div><p class="eyebrow">YOUR ACADEMIC WORK</p><h1>My submissions</h1><p class="muted">Every record you submitted, with its review status and feedback.</p></div>
        <span class="result-count"><?= (int)$counts['total'] ?> records</span>
    </div>

    <section class="dashboard-stats student-stats" aria-label="My submission totals">
        <article class="dashboard-stat stat-green"><span>Total</span><strong data-count-target="<?= (int)$counts['total'] ?>"><?= (int)$counts['total'] ?></strong><small>Records submitted by you</small></article>
        <article class="dashboard-stat stat-yellow"><span>Waiting for review</span><strong data-count-target="<?= (int)$counts['pending'] ?>"><?= (int)$counts['pending'] ?></strong><small>Submitted to the archive team</small></article>
        <article class="dashboard-stat stat-red"><span>Published</span><strong data-count-target="<?= (int)$counts['approved'] ?>"><?= (int)$counts['approved'] ?></strong><small>Approved and available resources</small></article>
        <article class="dashboard-stat stat-neutral"><span>Needs attention</span><strong data-count-target="<?= (int)$counts['returned'] + (int)$counts['rejected'] ?>"><?= (int)$counts['returned'] + (int)$counts['rejected'] ?></strong><small>Returned or declined submissions</small></article>
    </section>

    <section class="dashboard-section" id="my-submissions">
        <form class="my-filter" method="get" action="my-submissions.php">
            <label>Sort <select name="mysort"><option value="newest" <?= $mySort==='newest'?'selected':'' ?>>Newest first</option><option value="oldest" <?= $mySort==='oldest'?'selected':'' ?>>Oldest first</option><option value="title" <?= $mySort==='title'?'selected':'' ?>>Title A to Z</option></select></label>
            <label>Visibility <select name="myvis"><option value="" <?= $myVis===''?'selected':'' ?>>All</option><option value="public" <?= $myVis==='public'?'selected':'' ?>>Public</option><option value="private" <?= $myVis==='private'?'selected':'' ?>>Private</option></select></label>
            <label class="choice"><input type="checkbox" name="fav" value="1" <?= $favOnly?'checked':'' ?>> Favorites only</label>
            <button class="button button-quiet" type="submit">Apply</button>
            <?php if ($mySort!=='newest' || $myVis!=='' || $favOnly): ?><a class="button button-quiet" href="my-submissions.php">Clear</a><?php endif; ?>
        </form>
        <?php if (!$items): ?>
            <div class="empty-state"><div class="empty-icon">＋</div><h3><?= $favOnly ? 'No favorites yet' : 'Your archive starts here' ?></h3><p><?= $favOnly ? 'Open any record and use "Add to favorites" to keep it here.' : 'Submit your first paper, project, or learning resource for review.' ?></p><a class="button button-student" href="submit.php">Submit a resource</a></div>
        <?php else: ?>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Resource</th><th>Visibility</th><th>Status</th><th>Downloads</th><th>Date</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($items as $item): ?>
                <tr><td><strong><?=e($item['title'])?></strong><small><?=e($item['category_name']??'General')?> · <?=e($item['resource_type'])?></small><?php if (!empty($item['feedback'])): ?><small class="feedback">Feedback: <?=e($item['feedback'])?></small><?php endif; ?></td><td><span class="visibility"><?=e(ucfirst($item['visibility']))?></span></td><td><span class="status status-<?=e($item['status'])?>"><?=e(status_label($item['status']))?></span></td><td><?= (int)($item['download_count'] ?? 0) ?></td><td><?=e(date('M j, Y',strtotime($item['created_at'])))?></td><td><a class="text-link" href="view-resource.php?id=<?=(int)$item['id']?>">View →</a><?php if (can_edit_submission($item)): ?> <a class="text-link" href="edit.php?id=<?=(int)$item['id']?>">Edit</a><?php endif; ?><?php if (can_delete_submission($item)): ?> <form class="inline-form" action="api/delete.php" method="post" onsubmit="return confirm('Delete this record? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="submission_id" value="<?=(int)$item['id']?>"><button class="link-button" type="submit">Delete</button></form><?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <p><a class="text-link" href="dashboard.php">← Back to dashboard</a></p>
    </section>
</div></main>
<?php render_footer(); ?>
