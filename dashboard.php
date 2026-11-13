<?php
require_once __DIR__.'/config/bootstrap.php';
require_login();

$user = current_user();
$role = $user['role'] ?? 'student';
$userId = (int)$user['id'];
$dashboardMenu = match ($role) {
    'admin' => [
        ['label'=>'Review submissions','detail'=>'Open the archive review queue','href'=>'#review-queue','icon'=>'✓'],
        ['label'=>'Add official record','detail'=>'Create a college archive entry','href'=>'submit.php','icon'=>'+'],
        ['label'=>'Manage users','detail'=>'Browse accounts and submission history','href'=>'admin-users.php','icon'=>'◎'],
        ['label'=>'Audit log','detail'=>'See who did what, and when','href'=>'admin-audit.php','icon'=>'≡'],
        ['label'=>'Public catalog','detail'=>'Browse approved campus resources','href'=>'index.php','icon'=>'↗'],
        ['label'=>'Archive overview','detail'=>'View archive and member totals','href'=>'#archive-overview','icon'=>'▤'],
    ],
    'faculty' => [
        ['label'=>'Student review queue','detail'=>'Review pending public student work','href'=>'#faculty-review-queue','icon'=>'✓'],
        ['label'=>'My contributions','detail'=>'Check your research submissions','href'=>'my-submissions.php','icon'=>'▤'],
        ['label'=>'Share teaching material','detail'=>'Submit a resource for review','href'=>'submit.php','icon'=>'+'],
        ['label'=>'Campus collection','detail'=>'See recent work by colleagues','href'=>'#campus-collection','icon'=>'✳'],
        ['label'=>'Public archive','detail'=>'Browse the published collection','href'=>'index.php','icon'=>'↗'],
    ],
    default => [
        ['label'=>'My coursework','detail'=>'Check your academic submissions','href'=>'my-submissions.php','icon'=>'▤'],
        ['label'=>'Submit coursework','detail'=>'Add a paper or learning resource','href'=>'submit.php','icon'=>'+'],
        ['label'=>'Review status & feedback','detail'=>'Find decisions on your work','href'=>'#my-coursework','icon'=>'✓'],
        ['label'=>'Browse published work','detail'=>'Explore the student archive','href'=>'index.php','icon'=>'↗'],
        ['label'=>'How archiving works','detail'=>'See the steps for sharing your work','href'=>'#student-guide','icon'=>'?'],
    ],
};
$dashboardMenuTitle = match ($role) {
    'admin' => 'Archive tools',
    'faculty' => 'Faculty links',
    default => 'Student links',
};

if ($role === 'admin') {
    $counts = $pdo->query("SELECT COUNT(*) AS total,
        SUM(status='pending') AS pending,
        SUM(status='approved') AS approved,
        COALESCE(SUM(download_count),0) AS downloads
        FROM submissions")->fetch();
    $memberCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stmt = $pdo->query("SELECT s.*,u.name AS author,c.name AS category_name
        FROM submissions s
        JOIN users u ON u.id=s.user_id
        LEFT JOIN categories c ON c.id=s.category_id
        ORDER BY FIELD(s.status,'pending','returned','approved','rejected'),s.created_at DESC
        LIMIT 20");
    $items = $stmt->fetchAll();
} else {
    $statsStmt = $pdo->prepare("SELECT COUNT(*) AS total,
        SUM(status='pending') AS pending,
        SUM(status='approved') AS approved,
        SUM(status='returned') AS returned,
        SUM(status='rejected') AS rejected
        FROM submissions WHERE user_id=?");
    $statsStmt->execute([$userId]);
    $counts = $statsStmt->fetch();

    $mySort = $_GET['mysort'] ?? 'newest';
    if (!in_array($mySort, ['newest', 'oldest', 'title'], true)) $mySort = 'newest';
    $myVis = $_GET['myvis'] ?? '';
    if (!in_array($myVis, ['', 'public', 'private'], true)) $myVis = '';
    $myOrder = $mySort === 'oldest' ? 's.created_at ASC' : ($mySort === 'title' ? 's.title ASC' : 's.created_at DESC');
    $mySql = "SELECT s.*,c.name AS category_name FROM submissions s LEFT JOIN categories c ON c.id=s.category_id WHERE s.user_id=?";
    $myParams = [$userId];
    if ($myVis !== '') { $mySql .= " AND s.visibility=?"; $myParams[] = $myVis; }
    $mySql .= " ORDER BY {$myOrder} LIMIT 20";
    $stmt = $pdo->prepare($mySql);
    $stmt->execute($myParams);
    $items = $stmt->fetchAll();

    if ($role === 'faculty') {
        $reviewQueueStmt = $pdo->prepare("SELECT s.id,s.title,s.resource_type,s.created_at,
            c.name AS category_name,u.name AS author
            FROM submissions s
            JOIN users u ON u.id=s.user_id
            LEFT JOIN categories c ON c.id=s.category_id
            WHERE u.role='student' AND s.visibility='public' AND s.status='pending'
            ORDER BY s.created_at ASC
            LIMIT 25");
        $reviewQueueStmt->execute();
        $reviewQueue = $reviewQueueStmt->fetchAll();
        $reviewQueueCount = (int)$pdo->query("SELECT COUNT(*) FROM submissions s
            JOIN users u ON u.id=s.user_id
            WHERE u.role='student' AND s.visibility='public' AND s.status='pending'")->fetchColumn();

        $featuredStmt = $pdo->prepare("SELECT s.id,s.title,s.description,s.academic_year,
            c.name AS category_name,u.name AS author
            FROM submissions s
            LEFT JOIN categories c ON c.id=s.category_id
            JOIN users u ON u.id=s.user_id
            WHERE s.status='approved' AND s.visibility='public' AND s.user_id<>?
            ORDER BY s.published_at DESC,s.created_at DESC
            LIMIT 3");
        $featuredStmt->execute([$userId]);
        $featured = $featuredStmt->fetchAll();
        $publicCount = (int)$pdo->query("SELECT COUNT(*) FROM submissions
            WHERE status='approved' AND visibility='public'")->fetchColumn();
    }
}

$dashboardTitle = match ($role) {
    'admin' => 'Archive management',
    'faculty' => 'Faculty workspace',
    default => 'Student workspace',
};
render_header($dashboardTitle);
?>
<main id="main" class="container section dashboard dashboard-<?=e($role)?>">
    <div class="dashboard-menu-wrap">
        <button class="dashboard-menu-toggle" type="button" aria-expanded="false" aria-controls="dashboard-role-menu">
            <span class="dashboard-menu-glyph" aria-hidden="true"><i></i><i></i><i></i></span>
            <span><?=$dashboardMenuTitle?></span>
            <span class="dashboard-menu-chevron" aria-hidden="true">⌄</span>
        </button>
        <nav class="dashboard-role-menu" id="dashboard-role-menu" aria-label="<?=$dashboardMenuTitle?>" hidden>
            <p class="dashboard-menu-heading"><?=e($dashboardMenuTitle)?></p>
            <?php foreach ($dashboardMenu as $menuItem): ?>
                <a class="dashboard-menu-link" href="<?=e($menuItem['href'])?>">
                    <span class="dashboard-menu-icon" aria-hidden="true"><?=e($menuItem['icon'])?></span>
                    <span class="dashboard-menu-copy"><strong><?=e($menuItem['label'])?></strong><small><?=e($menuItem['detail'])?></small></span>
                    <span class="dashboard-menu-arrow" aria-hidden="true">→</span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
    <section class="dashboard-hero" id="dashboard-overview">
        <div class="dashboard-welcome">
            <p class="eyebrow"><?= $role === 'admin' ? 'ISPSC · ARCHIVE ADMINISTRATION' : ($role === 'faculty' ? 'ISPSC · FACULTY PORTAL' : 'ISPSC · STUDENT PORTAL') ?></p>
            <h1><?= $role === 'admin' ? 'Archive control center' : ($role === 'faculty' ? 'Good day, '.e($user['name']) : 'Welcome, '.e($user['name'])) ?></h1>
            <p><?= $role === 'admin'
                ? 'Review new academic work, publish approved records, and keep the campus collection organized.'
                : ($role === 'faculty'
                    ? 'Share teaching and research materials, follow your submissions, and discover the campus collection.'
                    : 'Keep track of your research and coursework submissions in one place.') ?></p>
            <?php if ($role === 'student'): ?><span class="dashboard-progress-chip"><strong data-count-target="<?= (int)$counts['total'] ?>"><?= (int)$counts['total'] ?></strong> My submissions</span><?php endif; ?>
        </div>
        <a class="button <?= $role === 'admin' ? 'button-admin' : ($role === 'faculty' ? 'button-faculty' : 'button-student') ?>" href="submit.php">
            <span aria-hidden="true"><?= $role === 'admin' ? '＋' : ($role === 'faculty' ? '↗' : '＋') ?></span>
            <?= $role === 'admin' ? 'Add official record' : ($role === 'faculty' ? 'Share a resource' : 'Submit coursework') ?>
        </a>
    </section>
    <?php if ($role === 'admin'): ?>
        <section class="dashboard-section" id="review-queue">
            <div class="section-heading compact-heading">
                <div><p class="eyebrow">ADMINISTRATION</p><h2>Review queue &amp; archive activity</h2><p class="muted">Pending submissions are listed first.</p></div>
                <span class="result-count"><?=count($items)?> recent records</span>
            </div>
            <?php if (!$items): ?>
                <div class="empty-state"><div class="empty-icon">✓</div><h3>The archive is up to date</h3><p>New submissions will appear here when they are received.</p></div>
            <?php else: ?>
                <div class="table-wrap"><table class="data-table"><thead><tr><th>Resource</th><th>Submitted by</th><th>Visibility</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($items as $item): ?>
                    <tr><td><strong><?=e($item['title'])?></strong><small><?=e($item['category_name']??'General')?> · <?=e($item['resource_type'])?></small></td><td><?=e($item['author'])?></td><td><span class="visibility"><?=e(ucfirst($item['visibility']))?></span></td><td><span class="status status-<?=e($item['status'])?>"><?=e(status_label($item['status']))?></span></td><td><?=e(date('M j, Y',strtotime($item['created_at'])))?></td><td><a class="text-link" href="view-resource.php?id=<?=(int)$item['id']?>"><?= $item['status'] === 'pending' ? 'Review' : 'View' ?> →</a> <a class="text-link" href="edit.php?id=<?=(int)$item['id']?>">Edit</a> <form class="inline-form" action="api/delete.php" method="post" onsubmit="return confirm('Delete this record? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="submission_id" value="<?=(int)$item['id']?>"><button class="link-button" type="submit">Delete</button></form></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
        <section class="dashboard-stats dashboard-stats-five" id="archive-overview" aria-label="Archive overview">
            <article class="dashboard-stat stat-green"><span>Archive records</span><strong data-count-target="<?= (int)$counts['total'] ?>"><?= (int)$counts['total'] ?></strong><small>Across all categories</small></article>
            <article class="dashboard-stat stat-yellow"><span>Waiting for review</span><strong data-count-target="<?= (int)$counts['pending'] ?>"><?= (int)$counts['pending'] ?></strong><small>Student and faculty submissions</small></article>
            <article class="dashboard-stat stat-red"><span>Published records</span><strong data-count-target="<?= (int)$counts['approved'] ?>"><?= (int)$counts['approved'] ?></strong><small>Approved archive resources</small></article>
            <a class="dashboard-stat stat-neutral dashboard-stat-link" href="admin-users.php"><span>Registered members</span><strong data-count-target="<?=$memberCount?>"><?=$memberCount?></strong><small>Browse accounts and submission history →</small></a>
            <article class="dashboard-stat stat-neutral"><span>Total downloads</span><strong data-count-target="<?= (int)$counts['downloads'] ?>"><?= (int)$counts['downloads'] ?></strong><small>File downloads served</small></article>
        </section>
        <section class="admin-shortcuts" aria-label="Administration links">
            <a class="admin-shortcut" href="admin-users.php"><strong>Manage users</strong><small>Browse accounts and submission history</small></a>
            <a class="admin-shortcut" href="admin-audit.php"><strong>Audit log</strong><small>See who did what, and when</small></a>
        </section>
    <?php elseif ($role === 'faculty'): ?>
        <section class="dashboard-stats faculty-stats" id="faculty-overview" aria-label="Faculty archive overview">
            <article class="dashboard-stat stat-green"><span>My contributions</span><strong data-count-target="<?= (int)$counts['total'] ?>"><?= (int)$counts['total'] ?></strong><small>Resources submitted by you</small></article>
            <article class="dashboard-stat stat-yellow"><span>Under review</span><strong data-count-target="<?= (int)$counts['pending'] ?>"><?= (int)$counts['pending'] ?></strong><small>Awaiting an archive decision</small></article>
            <article class="dashboard-stat stat-red"><span>Published</span><strong data-count-target="<?= (int)$counts['approved'] ?>"><?= (int)$counts['approved'] ?></strong><small>Available in the archive</small></article>
            <article class="dashboard-stat stat-neutral"><span>Campus collection</span><strong data-count-target="<?=$publicCount?>"><?=$publicCount?></strong><small>Approved public resources</small></article>
        </section>
        <section class="dashboard-section faculty-review-queue" id="faculty-review-queue">
            <div class="section-heading compact-heading">
                <div><p class="eyebrow">FACULTY REVIEW</p><h2>Student submission queue</h2><p class="muted">Only public student submissions are available to faculty reviewers. Private submissions remain restricted.</p></div>
                <span class="result-count"><?=$reviewQueueCount?> awaiting review</span>
            </div>
            <?php if (!$reviewQueue): ?>
                <div class="empty-state"><div class="empty-icon">✓</div><h3>No student submissions need review</h3><p>New public student submissions will appear here.</p></div>
            <?php else: ?>
                <div class="faculty-review-grid">
                <?php foreach ($reviewQueue as $submission): ?>
                    <article class="review-queue-card"><div><h3><?=e($submission['title'])?></h3><p><?=e($submission['category_name']??'General')?> · <?=e($submission['resource_type'])?></p><p><?=e($submission['author'])?> · <?=e(date('M j, Y',strtotime($submission['created_at'])))?></p></div><a class="button button-faculty" href="view-resource.php?id=<?=(int)$submission['id']?>">Review →</a></article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <div class="faculty-layout">
            <section class="dashboard-section faculty-submissions" id="my-contributions">
            <form class="my-filter" method="get" action="dashboard.php"><label>Sort <select name="mysort"><option value="newest" <?=($mySort??'newest')==='newest'?'selected':''?>>Newest first</option><option value="oldest" <?=($mySort??'')==='oldest'?'selected':''?>>Oldest first</option><option value="title" <?=($mySort??'')==='title'?'selected':''?>>Title A to Z</option></select></label><label>Visibility <select name="myvis"><option value="" <?=($myVis??'')===''?'selected':''?>>All</option><option value="public" <?=($myVis??'')==='public'?'selected':''?>>Public</option><option value="private" <?=($myVis??'')==='private'?'selected':''?>>Private</option></select></label><button class="button button-quiet" type="submit">Apply</button></form>
                <div class="section-heading compact-heading"><div><p class="eyebrow">YOUR CONTRIBUTIONS</p><h2>My submissions</h2></div><span class="result-count"><?=count($items)?> records</span> <a class="text-link" href="my-submissions.php">View all →</a></div>
                <?php if (!$items): ?>
                    <div class="empty-state"><div class="empty-icon">＋</div><h3>Start your faculty collection</h3><p>Share a learning resource or research output with the campus community.</p><a class="button button-faculty" href="submit.php">Share your first resource</a></div>
                <?php else: ?>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Resource</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
                    <?php foreach ($items as $item): ?>
                        <tr><td><strong><?=e($item['title'])?></strong><small><?=e($item['category_name']??'General')?> · <?=e($item['resource_type'])?></small><?php if (!empty($item['feedback'])): ?><small class="feedback">Feedback: <?=e($item['feedback'])?></small><?php endif; ?></td><td><span class="status status-<?=e($item['status'])?>"><?=e(status_label($item['status']))?></span></td><td><?=e(date('M j, Y',strtotime($item['created_at'])))?></td><td><a class="text-link" href="view-resource.php?id=<?=(int)$item['id']?>">View →</a><?php if (can_edit_submission($item)): ?> <a class="text-link" href="edit.php?id=<?=(int)$item['id']?>">Edit</a><?php endif; ?><?php if (can_delete_submission($item)): ?> <form class="inline-form" action="api/delete.php" method="post" onsubmit="return confirm('Delete this record? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="submission_id" value="<?=(int)$item['id']?>"><button class="link-button" type="submit">Delete</button></form><?php endif; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
            <aside class="campus-picks" id="campus-collection">
                <p class="eyebrow">SHARED KNOWLEDGE</p><h2>From the campus collection</h2><p class="muted">Recently published public resources from other contributors.</p>
                <?php if (!$featured): ?>
                    <p class="picks-empty">New campus resources will appear here after they are approved.</p>
                <?php else: ?>
                    <?php foreach ($featured as $resource): ?>
                        <article class="pick-card"><span><?=e($resource['category_name']??'General')?></span><h3><a href="view-resource.php?id=<?=(int)$resource['id']?>"><?=e($resource['title'])?></a></h3><p><?=e($resource['author'])?> · <?=e($resource['academic_year']?:'Academic resource')?></p></article>
                    <?php endforeach; ?>
                    <a class="text-link" href="index.php">Explore the public archive →</a>
                <?php endif; ?>
            </aside>
        </div>
    <?php else: ?>
        <section class="dashboard-section student-submissions" id="my-coursework">
            <form class="my-filter" method="get" action="dashboard.php"><label>Sort <select name="mysort"><option value="newest" <?=($mySort??'newest')==='newest'?'selected':''?>>Newest first</option><option value="oldest" <?=($mySort??'')==='oldest'?'selected':''?>>Oldest first</option><option value="title" <?=($mySort??'')==='title'?'selected':''?>>Title A to Z</option></select></label><label>Visibility <select name="myvis"><option value="" <?=($myVis??'')===''?'selected':''?>>All</option><option value="public" <?=($myVis??'')==='public'?'selected':''?>>Public</option><option value="private" <?=($myVis??'')==='private'?'selected':''?>>Private</option></select></label><button class="button button-quiet" type="submit">Apply</button></form>
            <div class="section-heading compact-heading"><div><p class="eyebrow">YOUR ACADEMIC WORK</p><h2>My submissions</h2><p class="muted">Check each resource’s review status and feedback.</p></div><span class="result-count"><?=count($items)?> records</span> <a class="text-link" href="my-submissions.php">View all →</a></div>
            <?php if (!$items): ?>
                <div class="empty-state"><div class="empty-icon">＋</div><h3>Your archive starts here</h3><p>Submit your first paper, project, or learning resource for review.</p><a class="button button-student" href="submit.php">Submit your first resource</a></div>
            <?php else: ?>
                <div class="table-wrap"><table class="data-table"><thead><tr><th>Resource</th><th>Visibility</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($items as $item): ?>
                    <tr><td><strong><?=e($item['title'])?></strong><small><?=e($item['category_name']??'General')?> · <?=e($item['resource_type'])?></small><?php if (!empty($item['feedback'])): ?><small class="feedback">Feedback: <?=e($item['feedback'])?></small><?php endif; ?></td><td><span class="visibility"><?=e(ucfirst($item['visibility']))?></span></td><td><span class="status status-<?=e($item['status'])?>"><?=e(status_label($item['status']))?></span></td><td><?=e(date('M j, Y',strtotime($item['created_at'])))?></td><td><a class="text-link" href="view-resource.php?id=<?=(int)$item['id']?>">View →</a><?php if (can_edit_submission($item)): ?> <a class="text-link" href="edit.php?id=<?=(int)$item['id']?>">Edit</a><?php endif; ?><?php if (can_delete_submission($item)): ?> <form class="inline-form" action="api/delete.php" method="post" onsubmit="return confirm('Delete this record? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="submission_id" value="<?=(int)$item['id']?>"><button class="link-button" type="submit">Delete</button></form><?php endif; ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
        <section class="student-browse" id="student-browse" aria-label="Browse published work">
            <a class="button button-student" href="index.php">Browse published work →</a>
        </section>
        <section class="student-next-steps" id="student-guide" aria-label="How the archive works">
            <div><span class="step-number">01</span><h3>Share your work</h3><p>Upload an academic file or add a trusted web resource.</p></div>
            <div><span class="step-number">02</span><h3>Track the review</h3><p>Follow the status and read any feedback from the archive team.</p></div>
            <div><span class="step-number">03</span><h3>Grow the archive</h3><p>Approved public resources become part of the campus collection.</p></div>
        </section>
        <section class="dashboard-stats student-stats" id="student-overview" aria-label="Student archive overview">
            <article class="dashboard-stat stat-green"><span>My submissions</span><strong data-count-target="<?= (int)$counts['total'] ?>"><?= (int)$counts['total'] ?></strong><small>Coursework and research resources</small></article>
            <article class="dashboard-stat stat-yellow"><span>Waiting for review</span><strong data-count-target="<?= (int)$counts['pending'] ?>"><?= (int)$counts['pending'] ?></strong><small>Submitted to the archive team</small></article>
            <article class="dashboard-stat stat-red"><span>Published</span><strong data-count-target="<?= (int)$counts['approved'] ?>"><?= (int)$counts['approved'] ?></strong><small>Approved and available resources</small></article>
            <article class="dashboard-stat stat-neutral"><span>Needs attention</span><strong data-count-target="<?= (int)$counts['returned'] + (int)$counts['rejected'] ?>"><?= (int)$counts['returned'] + (int)$counts['rejected'] ?></strong><small>Returned or declined submissions</small></article>
        </section>
    <?php endif; ?>
    <?php
    $notifStmt = $pdo->prepare('SELECT n.*, s.title AS submission_title FROM notifications n LEFT JOIN submissions s ON s.id=n.submission_id WHERE n.user_id=? ORDER BY n.created_at DESC LIMIT 10');
    $notifStmt->execute([$userId]);
    $notifications = $notifStmt->fetchAll();
    $unreadCount = unread_notification_count($pdo, $userId);
    ?>
    <section class="dashboard-section notifications-section" id="notifications" aria-label="Notifications">
        <div class="section-heading compact-heading">
            <div><p class="eyebrow">NOTIFICATIONS</p><h2>Review updates</h2><p class="muted">Decisions on your submissions appear here.</p></div>
            <span class="result-count"><?=$unreadCount?> unread</span>
        </div>
        <?php if (!$notifications): ?>
            <p class="muted">No notifications yet. When the archive team reviews your work, the decision will show up here.</p>
        <?php else: ?>
            <ul class="notification-list">
            <?php foreach ($notifications as $n): ?>
                <li class="<?= (int)$n['is_read'] === 0 ? 'notification-unread' : 'notification-read' ?>"><span><?=e($n['message'])?></span><small><?=e(date('M j, Y g:i A', strtotime($n['created_at'])))?></small><?php if (!empty($n['submission_id'])): ?> <a class="text-link" href="view-resource.php?id=<?=(int)$n['submission_id']?>">View &rarr;</a><?php endif; ?></li>
            <?php endforeach; ?>
            </ul>
            <?php if ($unreadCount > 0): ?>
                <form class="inline-form" action="api/notifications.php" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="mark_all_read"><button class="button button-quiet" type="submit">Mark all read</button></form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php
    $favStmt = $pdo->prepare('SELECT s.id,s.title,s.status,s.visibility,c.name AS category_name FROM favorites f JOIN submissions s ON s.id=f.submission_id LEFT JOIN categories c ON c.id=s.category_id WHERE f.user_id=? ORDER BY f.created_at DESC LIMIT 10');
    $favStmt->execute([$userId]);
    $favorites = $favStmt->fetchAll();
    ?>
    <section class="dashboard-section favorites-section" id="favorites" aria-label="My favorites">
        <div class="section-heading compact-heading">
            <div><p class="eyebrow">SAVED FOR LATER</p><h2>My favorites</h2><p class="muted">Records you marked with a star on their detail page.</p></div>
            <span class="result-count"><?=count($favorites)?> saved</span>
        </div>
        <?php if (!$favorites): ?>
            <p class="muted">No favorites yet. Open any record and use "Add to favorites" to keep it here.</p>
        <?php else: ?>
            <ul class="favorite-list">
            <?php foreach ($favorites as $fav): ?>
                <li><a href="view-resource.php?id=<?=(int)$fav['id']?>"><?=e($fav['title'])?></a> <small><?=e($fav['category_name']??'General')?> · <?=e(ucfirst($fav['visibility']))?></small> <span class="status status-<?=e($fav['status'])?>"><?=e(status_label($fav['status']))?></span></li>
            <?php endforeach; ?>
            </ul>
            <p><a class="text-link" href="my-submissions.php?fav=1">See only favorites in My submissions →</a></p>
        <?php endif; ?>
    </section>
</main>
<?php render_footer(); ?>
