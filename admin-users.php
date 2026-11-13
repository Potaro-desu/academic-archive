<?php
require_once __DIR__.'/config/bootstrap.php';
require_admin();

$search = trim((string)($_GET['q'] ?? ''));
$search = mb_substr($search, 0, 100);
$selectedId = max(0, (int)($_GET['user'] ?? 0));
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 25;
$like = '%'.$search.'%';
$whereSql = $search !== '' ? ' WHERE (u.name LIKE ? OR u.email LIKE ? OR u.role LIKE ?)' : '';
$whereParams = $search !== '' ? [$like,$like,$like] : [];

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM users u'.$whereSql);
$totalStmt->execute($whereParams);
$totalUsers = (int)$totalStmt->fetchColumn();
$pageCount = max(1, (int)ceil($totalUsers / $pageSize));
$page = min($page, $pageCount);
$offset = ($page - 1) * $pageSize;

$usersStmt = $pdo->prepare("SELECT u.id,u.name,u.email,u.role,u.is_active,u.created_at,
        COUNT(s.id) AS submission_count,
        COALESCE(SUM(s.status='pending'),0) AS pending_count,
        COALESCE(SUM(s.status='approved'),0) AS approved_count
    FROM users u
    LEFT JOIN submissions s ON s.user_id=u.id
    {$whereSql}
    GROUP BY u.id,u.name,u.email,u.role,u.is_active,u.created_at
    ORDER BY FIELD(u.role,'admin','faculty','student'),u.name
    LIMIT {$pageSize} OFFSET {$offset}");
$usersStmt->execute($whereParams);
$users = $usersStmt->fetchAll();

$selectedUser = null;
$submissions = [];
$selectedSubmissionCount = 0;
if ($selectedId > 0) {
    $selectedStmt = $pdo->prepare("SELECT u.id,u.name,u.email,u.role,u.is_active,u.created_at,
            COUNT(s.id) AS submission_count
        FROM users u
        LEFT JOIN submissions s ON s.user_id=u.id
        WHERE u.id=?
        GROUP BY u.id,u.name,u.email,u.role,u.is_active,u.created_at");
    $selectedStmt->execute([$selectedId]);
    $selectedUser = $selectedStmt->fetch();
    if (!$selectedUser) {
        http_response_code(404);
    } else {
        $selectedSubmissionCount = (int)$selectedUser['submission_count'];
        $submissionsStmt = $pdo->prepare("SELECT s.id,s.title,s.visibility,s.status,s.created_at,c.name AS category_name
            FROM submissions s
            LEFT JOIN categories c ON c.id=s.category_id
            WHERE s.user_id=?
            ORDER BY s.created_at DESC
            LIMIT 50");
        $submissionsStmt->execute([$selectedId]);
        $submissions = $submissionsStmt->fetchAll();
    }
}

render_header('User directory');
?>
<main id="main" class="container section admin-users-page">
    <div class="user-directory-heading">
        <div><p class="eyebrow">ADMINISTRATION · ACCOUNT OVERSIGHT</p><h1>User directory</h1><p class="muted">Browse student and faculty accounts and review their archive submission history.</p></div>
        <span class="result-count"><?=$totalUsers?> accounts</span>
    </div>

    <form class="user-directory-search" method="get" action="admin-users.php">
        <label><span class="sr-only">Search user accounts</span><input type="search" name="q" value="<?=e($search)?>" placeholder="Search name, email, or role"></label>
        <button class="button button-admin" type="submit">Search accounts</button>
        <?php if ($search !== ''): ?><a class="button button-quiet" href="admin-users.php">Clear</a><?php endif; ?>
    </form>

    <section class="user-directory-panel" aria-label="Registered users">
        <div class="user-panel-heading"><div><h2>Registered accounts</h2><p><?= $search !== '' ? 'Matching accounts' : 'Students, faculty, and administrators' ?></p></div><span class="result-count">Page <?=$page?> of <?=$pageCount?></span></div>
        <?php if (!$users): ?>
            <div class="empty-state"><h3>No matching accounts</h3><p>Try searching by another name, email address, or role.</p></div>
        <?php else: ?>
            <div class="table-wrap"><table class="data-table user-directory-table"><thead><tr><th>Account</th><th>Role</th><th>Submissions</th><th>Pending</th><th>Joined</th><th>History</th></tr></thead><tbody>
            <?php foreach ($users as $account): ?>
                <tr>
                    <td><strong><?=e($account['name'])?></strong><small><?=e($account['email'])?></small></td>
                    <td><span class="account-role role-<?=e($account['role'])?>"><?=e(ucfirst($account['role']))?></span></td>
                    <td><?= (int)$account['submission_count'] ?></td>
                    <td><?= (int)$account['pending_count'] ?></td>
                    <td><?=e(date('M j, Y',strtotime($account['created_at'])))?></td>
                    <td><a class="text-link" href="admin-users.php?user=<?=(int)$account['id']?>&amp;q=<?=urlencode($search)?>&amp;page=<?=$page?>">View history →</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php if ($pageCount > 1): ?>
                <nav class="user-pagination" aria-label="User directory pages">
                    <?php if ($page > 1): ?><a href="admin-users.php?q=<?=urlencode($search)?>&amp;page=<?=$page-1?>">← Previous</a><?php endif; ?>
                    <span>Page <?=$page?> of <?=$pageCount?></span>
                    <?php if ($page < $pageCount): ?><a href="admin-users.php?q=<?=urlencode($search)?>&amp;page=<?=$page+1?>">Next →</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($selectedId > 0): ?>
        <?php if (!$selectedUser): ?>
            <section class="user-history-panel"><h2>Account not found</h2><p>This account may have been removed or the link may be incorrect.</p></section>
        <?php else: ?>
            <section class="user-history-panel">
                <div class="user-panel-heading"><div><p class="eyebrow">SUBMISSION HISTORY</p><h2><?=e($selectedUser['name'])?></h2><p><?=e($selectedUser['email'])?> · <?=e(ucfirst($selectedUser['role']))?> · Joined <?=e(date('M j, Y',strtotime($selectedUser['created_at'])))?></p></div><span class="result-count"><?=$selectedSubmissionCount?> total</span></div>
                <?php if (!$submissions): ?>
                    <div class="empty-state"><h3>No submissions yet</h3><p>Submissions from this account will appear here.</p></div>
                <?php else: ?>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Submission</th><th>Visibility</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
                    <?php foreach ($submissions as $submission): ?>
                        <tr><td><strong><?=e($submission['title'])?></strong><small><?=e($submission['category_name']??'General')?></small></td><td><?=e(ucfirst($submission['visibility']))?></td><td><span class="status status-<?=e($submission['status'])?>"><?=e(status_label($submission['status']))?></span></td><td><?=e(date('M j, Y',strtotime($submission['created_at'])))?></td><td><a class="text-link" href="view-resource.php?id=<?=(int)$submission['id']?>">Open record →</a></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                    <?php if ($selectedSubmissionCount > count($submissions)): ?><p class="muted user-history-note">Showing the 50 most recent submissions.</p><?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php render_footer(); ?>
