<?php
// Admin audit log - every recorded action, newest first, admin only.
require_once __DIR__.'/config/bootstrap.php';
require_admin();

$actionFilter = trim((string)($_GET['action'] ?? ''));
$userSearch = trim((string)($_GET['user'] ?? ''));
$userSearch = mb_substr($userSearch, 0, 100);
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 50;

$actions = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
if ($actionFilter !== '' && !in_array($actionFilter, $actions, true)) $actionFilter = '';

$where = [];
$params = [];
if ($actionFilter !== '') { $where[] = 'a.action=?'; $params[] = $actionFilter; }
if ($userSearch !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ?)'; $like = '%'.$userSearch.'%'; $params[] = $like; $params[] = $like; }
$whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id'.$whereSql);
$totalStmt->execute($params);
$totalRows = (int)$totalStmt->fetchColumn();
$pageCount = max(1, (int)ceil($totalRows / $pageSize));
$page = min($page, $pageCount);
$offset = ($page - 1) * $pageSize;

$rowsStmt = $pdo->prepare("SELECT a.*,u.name AS user_name,u.email AS user_email,s.title AS submission_title
    FROM audit_logs a
    LEFT JOIN users u ON u.id=a.user_id
    LEFT JOIN submissions s ON s.id=a.submission_id
    {$whereSql}
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT {$pageSize} OFFSET {$offset}");
$rowsStmt->execute($params);
$rows = $rowsStmt->fetchAll();

function audit_detail_text(?string $json): string {
    if ($json === null || $json === '') return '';
    $data = json_decode($json, true);
    if (!is_array($data) || $data === []) return '';
    $parts = [];
    foreach ($data as $k => $v) {
        if (is_bool($v)) $v = $v ? 'yes' : 'no';
        $parts[] = $k . ': ' . (string)$v;
    }
    return mb_strimwidth(implode('; ', $parts), 0, 120, '…');
}

render_header('Audit log');
?>
<main id="main" class="container section admin-users-page">
    <div class="user-directory-heading">
        <div><p class="eyebrow">ADMINISTRATION · ACCOUNTABILITY</p><h1>Audit log</h1><p class="muted">Every login, registration, submission, review, edit, delete, favorite, and lockout recorded by the system.</p></div>
        <span class="result-count"><?=$totalRows?> entries</span>
    </div>

    <form class="user-directory-search" method="get" action="admin-audit.php">
        <label><span class="sr-only">Filter by action</span><select name="action"><option value="">All actions</option><?php foreach ($actions as $a): ?><option value="<?=e($a)?>" <?= $actionFilter===$a?'selected':'' ?>><?=e($a)?></option><?php endforeach; ?></select></label>
        <label><span class="sr-only">Search by user</span><input type="search" name="user" value="<?=e($userSearch)?>" placeholder="Search user name or email"></label>
        <button class="button button-admin" type="submit">Filter</button>
        <?php if ($actionFilter !== '' || $userSearch !== ''): ?><a class="button button-quiet" href="admin-audit.php">Clear</a><?php endif; ?>
    </form>

    <section class="user-directory-panel" aria-label="Audit entries">
        <div class="user-panel-heading"><div><h2>Recorded actions</h2><p>Newest first, 50 per page</p></div><span class="result-count">Page <?=$page?> of <?=$pageCount?></span></div>
        <?php if (!$rows): ?>
            <div class="empty-state"><h3>No matching entries</h3><p>Try a different action filter or user search.</p></div>
        <?php else: ?>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?=e(date('M j, Y g:i A', strtotime($row['created_at'])))?></td>
                    <td><strong><?=e($row['user_name'] ?? 'Signed out')?></strong><?php if (!empty($row['user_email'])): ?><small><?=e($row['user_email'])?></small><?php endif; ?></td>
                    <td><?=e($row['action'])?></td>
                    <td><?php if (!empty($row['submission_id'])): ?><a class="text-link" href="view-resource.php?id=<?=(int)$row['submission_id']?>"><?=e($row['submission_title'] ?? ('Record #'.(int)$row['submission_id']))?></a><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                    <td><small><?=e(audit_detail_text($row['details']))?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php if ($pageCount > 1): ?>
                <nav class="user-pagination" aria-label="Audit log pages">
                    <?php if ($page > 1): ?><a href="admin-audit.php?action=<?=urlencode($actionFilter)?>&amp;user=<?=urlencode($userSearch)?>&amp;page=<?=$page-1?>">← Previous</a><?php endif; ?>
                    <span>Page <?=$page?> of <?=$pageCount?></span>
                    <?php if ($page < $pageCount): ?><a href="admin-audit.php?action=<?=urlencode($actionFilter)?>&amp;user=<?=urlencode($userSearch)?>&amp;page=<?=$page+1?>">Next →</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <p><a class="text-link" href="dashboard.php">← Back to dashboard</a></p>
</main>
<?php render_footer(); ?>
