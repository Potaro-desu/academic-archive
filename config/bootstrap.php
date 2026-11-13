<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('academic_archive_session');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $isHttps,
    'httponly' => true, 'samesite' => 'Lax'
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function require_login(): void {
    if (!current_user()) { header('Location: login.php'); exit; }
}
function require_admin(): void {
    require_login();
    if (!is_admin()) { http_response_code(403); exit('403 Forbidden, administrator access required.'); }
}
function can_review_submission(array $submission): bool {
    if (is_admin()) return true;
    $user = current_user();
    return ($user['role'] ?? '') === 'faculty'
        && ($submission['author_role'] ?? '') === 'student'
        && ($submission['visibility'] ?? '') === 'public'
        && ($submission['status'] ?? '') === 'pending';
}
function can_edit_submission(array $submission): bool {
    $user = current_user();
    if (!$user) return false;
    if (is_admin()) return true;
    return (int)$user['id'] === (int)($submission['user_id'] ?? 0)
        && in_array($submission['status'] ?? '', ['pending', 'returned'], true);
}
function can_delete_submission(array $submission): bool {
    $user = current_user();
    if (!$user) return false;
    if (is_admin()) return true;
    return (int)$user['id'] === (int)($submission['user_id'] ?? 0)
        && in_array($submission['status'] ?? '', ['pending', 'returned', 'rejected'], true);
}
function notify_user(PDO $pdo, int $userId, ?int $submissionId, string $message): void {
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, submission_id, message) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $submissionId, $message]);
}
function unread_notification_count(PDO $pdo, int $userId): int {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    $sent = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419); exit('Your session token expired. Refresh the page and try again.');
    }
}
function flash(string $type, string $message): void { $_SESSION['flash'] = ['type'=>$type, 'message'=>$message]; }
function get_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function audit(PDO $pdo, ?int $userId, string $action, ?int $submissionId = null, array $details = []): void {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, submission_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $submissionId, $action, json_encode($details, JSON_UNESCAPED_SLASHES), $_SERVER['REMOTE_ADDR'] ?? null]);
}
function redirect(string $url): never { header('Location: ' . $url); exit; }
function allowed_extensions(): array {
    return ['pdf','mp4','webm','mp3','wav','m4a','jpg','jpeg','png','gif','webp','doc','docx','txt','ppt','pptx','xls','xlsx','csv','zip'];
}
// MIME types a file extension may legitimately have, as detected by finfo.
// Aliases are documented, not silent: WAV files are reported as audio/x-wav
// by libmagic, CSV as text/plain or text/csv, and minimal OOXML files
// (DOCX, XLSX, PPTX built by ZipArchive) are reported as application/zip
// because OOXML files are ZIP containers and libmagic cannot tell a minimal
// one from a plain ZIP. A renamed file still fails: a PNG renamed to .pdf is
// detected as image/png, which is not in the pdf list.
function expected_mimes(string $ext): array {
    return match (strtolower($ext)) {
        'pdf' => ['application/pdf'],
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
        'mp3' => ['audio/mpeg'],
        'wav' => ['audio/wav', 'audio/x-wav'],
        'm4a' => ['audio/mp4', 'audio/x-m4a'],
        'jpg', 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', 'application/zip'],
        'txt' => ['text/plain'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml', 'application/zip'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml', 'application/zip'],
        'csv' => ['text/csv', 'text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        default => [],
    };
}
function mime_matches_extension(string $ext, string $detected): bool {
    $allowed = expected_mimes($ext);
    if ($allowed === []) return false;
    return in_array(strtolower(trim($detected)), $allowed, true);
}
// Academic year is optional, but when given it must be YYYY-YYYY with the
// second year exactly one after the first, for example 2025-2026.
function valid_academic_year(string $year): bool {
    if ($year === '') return true;
    if (!preg_match('/^(\d{4})-(\d{4})$/', $year, $m)) return false;
    return (int)$m[2] === (int)$m[1] + 1;
}
function is_favorited(PDO $pdo, int $userId, int $submissionId): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id=? AND submission_id=?');
    $stmt->execute([$userId, $submissionId]);
    return (int)$stmt->fetchColumn() > 0;
}
// Host part of an external URL, for example "example.com" from
// "https://example.com/resource". Returns null when there is no host.
function link_domain(?string $url): ?string {
    if ($url === null || $url === '') return null;
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($host) && $host !== '' ? $host : null;
}
// Label and colour class for a catalog card tile, by resource type and file extension.
function resource_tile(string $resourceType, string $originalName): array {
    if ($resourceType === 'link') return ['label' => 'WEB LINK', 'class' => 'tile-link'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf' => ['label' => 'PDF', 'class' => 'tile-pdf'],
        'mp4', 'webm' => ['label' => 'VIDEO', 'class' => 'tile-video'],
        'mp3', 'wav', 'm4a' => ['label' => 'AUDIO', 'class' => 'tile-audio'],
        'jpg', 'jpeg', 'png', 'gif', 'webp' => ['label' => 'IMAGE', 'class' => 'tile-image'],
        'doc', 'docx' => ['label' => 'DOCUMENT', 'class' => 'tile-doc'],
        'ppt', 'pptx' => ['label' => 'PRESENTATION', 'class' => 'tile-doc'],
        'xls', 'xlsx' => ['label' => 'SPREADSHEET', 'class' => 'tile-sheet'],
        'txt' => ['label' => 'TEXT', 'class' => 'tile-text'],
        'csv' => ['label' => 'DATA', 'class' => 'tile-sheet'],
        'zip' => ['label' => 'ARCHIVE', 'class' => 'tile-zip'],
        default => ['label' => 'FILE', 'class' => 'tile-other'],
    };
}
// True when a file record is an image, so the catalog card can show it.
function is_image_resource(string $resourceType, string $originalName): bool {
    return $resourceType === 'file'
        && in_array(strtolower(pathinfo($originalName, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
}
// True when the caller asked for JSON (fetch with Accept: application/json).
function wants_json(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json');
}
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function category_rows(PDO $pdo): array { return $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(); }
function status_label(string $status): string {
    return match($status) { 'approved'=>'Approved / Published', 'rejected'=>'Rejected', 'returned'=>'Returned for Revision', default=>'Pending' };
}
function render_header(string $title): void {
    $user = current_user();
    $flash = get_flash();
    $homeStylesheet = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php'
        ? '<link rel="stylesheet" href="assets/css/home.css?v='.filemtime(__DIR__.'/../assets/css/home.css').'">'
        : '';
    $animePath = __DIR__.'/../assets/vendor/anime/anime.umd.min.js';
    $animeScript = is_file($animePath)
        ? '<script defer src="assets/vendor/anime/anime.umd.min.js?v='.filemtime($animePath).'"></script>'
        : '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="'.e(csrf_token()).'"><title>'.e($title).' · ISPSC Sta. Maria</title><link rel="stylesheet" href="assets/css/style.css?v='.filemtime(__DIR__.'/../assets/css/style.css').'"><link rel="stylesheet" href="assets/css/campus.css?v='.filemtime(__DIR__.'/../assets/css/campus.css').'">'.$homeStylesheet.$animeScript.'<script defer src="assets/js/app.js?v='.filemtime(__DIR__.'/../assets/js/app.js').'"></script></head><body>';
    echo '<a class="skip-link" href="#main">Skip to content</a><header class="site-header"><div class="nav-wrap"><a class="brand" href="index.php"><img class="brand-logo" src="assets/images/ispsc-logo.png" alt="ISPSC college seal"><span>Ilocos Sur Polytechnic State College<small>Sta. Maria Campus · Academic Repository</small></span></a><button class="nav-toggle" aria-expanded="false" aria-controls="site-nav">Menu</button><nav id="site-nav" class="site-nav"><a href="index.php">Explore</a>';
    if ($user) echo '<a href="dashboard.php">Dashboard</a><a href="my-submissions.php">My submissions</a><a href="submit.php">Submit resource</a><span class="nav-user">'.e($user['name']).'</span><form class="inline-form" action="api/auth.php" method="post"><input type="hidden" name="csrf_token" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="logout"><button class="link-button" type="submit">Sign out</button></form>';
    else echo '<a href="login.php">Sign in</a><a class="nav-cta" href="register.php">Create account</a>';
    echo '</nav></div></header>';
    if ($flash) echo '<div class="flash '.e($flash['type']).'" role="status">'.e($flash['message']).'</div>';
}
function render_footer(): void {
    echo '<footer class="footer"><div class="container footer-inner"><span>ISPSC Sta. Maria · Academic Archiving System · Version: v1.3.1</span><span>Organize knowledge. Share responsibly.</span></div></footer></body></html>';
}
