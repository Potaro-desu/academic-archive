<?php
// Seed demo files: generates real demo files and matching archive records.
// Run once after importing database/schema.sql and database/seeds.sql:
//   php database/seed_files.php
// Safe to run again: a title that already exists is skipped.
// Public files go in uploads/public/, the private file in uploads/private/,
// under random stored names, exactly like normal uploads.
require_once __DIR__.'/../config/database.php';

function seed_pdf(string $title): string {
    $text = str_replace(['(', ')', '\\'], '', $title);
    $stream = "BT /F1 20 Tf 72 720 Td (".substr($text, 0, 40).") Tj ET\n";
    return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
        . "4 0 obj<</Length ".strlen($stream).">>stream\n".$stream."endstream\nendobj\n"
        . "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n"
        . "trailer<</Root 1 0 R/Size 6>>\n%%EOF";
}
function seed_wav(): string {
    $frames = '';
    for ($i = 0; $i < 8000; $i++) { $s = (int)(12000 * sin(2 * M_PI * 440 * $i / 8000)); $frames .= pack('v', ($s + 65536) % 65536); }
    $dataLen = strlen($frames);
    return 'RIFF'.pack('V', 36 + $dataLen).'WAVEfmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', 8000).pack('V', 16000).pack('v', 2).pack('v', 16).'data'.pack('V', $dataLen).$frames;
}
function seed_office(string $kind, string $title): string {
    $tmp = tempnam(sys_get_temp_dir(), 'seed').'.zip';
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($kind === 'docx') {
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($title).'</w:t></w:r></w:p></w:body></w:document>');
    } else {
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/></Types>');
        $zip->addFromString('ppt/presentation.xml', '<?xml version="1.0"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:sldIdLst><p:sldId id="256" r:id="rId2" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/></p:sldIdLst></p:presentation>');
        $zip->addFromString('ppt/slides/slide1.xml', '<?xml version="1.0"?><p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:spTree><p:sp><p:txBody><a:p xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:p><a:r><a:t>'.htmlspecialchars($title).'</a:t></a:r></a:p></a:p></p:txBody></p:sp></p:spTree></p:cSld></p:sld>');
    }
    $zip->close();
    $data = file_get_contents($tmp);
    unlink($tmp);
    return $data;
}
function seed_video(string $format): ?string {
    $ffmpeg = trim((string)shell_exec('command -v ffmpeg 2>/dev/null'));
    if ($ffmpeg === '') return null;
    $tmp = tempnam(sys_get_temp_dir(), 'seed').'.'.$format;
    $cmd = $format === 'webm'
        ? escapeshellcmd($ffmpeg).' -y -f lavfi -i color=c=green:s=320x240:d=1 -f lavfi -i sine=frequency=440:d=1 -shortest -c:v libvpx -pix_fmt yuv420p '.escapeshellarg($tmp).' 2>/dev/null'
        : escapeshellcmd($ffmpeg).' -y -f lavfi -i color=c=blue:s=320x240:d=1 -f lavfi -i sine=frequency=440:d=1 -shortest -pix_fmt yuv420p '.escapeshellarg($tmp).' 2>/dev/null';
    shell_exec($cmd);
    if (!is_file($tmp) || filesize($tmp) < 1000) { @unlink($tmp); return null; }
    $data = file_get_contents($tmp);
    unlink($tmp);
    return $data;
}

$pngB64 = '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a5nmkQOxGQ4xDNkeuUtELl287OPjfef8UTujW7ZrvmX9tuDK5ffsiLrzlGEz3KOuXL7ic5eHSrVjy66YqAMVq4a0fLV59J6Y7Zt3vP5mu/XfbQypErx21YZcufxf6/rqlnVb506flzrk/t4QQJEcC5bNZsv/aP4x748XkXIhZSpULm/YbIYYImKaZmLi7e79ukbv3r95bWTdBrVFxL3rERHDMNyX2TxsU8ZPT05OMQyjeOkg9zd8isvlfpGcnHLn7iZVmrvcbTFHDsa6TNM9Q/ohrhRXYkKie2yae1y6eMnP39fbx7tI0cKLv1xy56n0o9I/lJt/gH/+gvlOHf9pe+TOA/sODRrZ/7ulqy3Tyh2YO2++wHNnzn05Y0GJMsXrN36mWq2qX81Y8N+nuK83BFAkxz4SBpUqdu7seffrE0dPlQ0uc/rkGfennlr1ajZp9fcngopE7dzn6elpt9tF5NTxn9xnK1ULEcMQkZPHTlWsFiIi5SuWbZzlnzqnucvdFpP5EDPT7/yYqIPP/b3B6ZNn0wQi/aj0D+VmidWp5+vuPxz08/e9duW6iOzduS+0bdPD+4/4+Hj3HtTzp+On50yfV75iORGxGYZhGPf3hgCK5NgOq2KV4GOHjrtfO53O+Lj4yHVbG7/U8JmGdRITE+dOn+9yufq+H3buzPmEhES73b5k/rL2/2j7TKM6p479lJKSIiJL5i1r80arp+vXdpmm+4NVViyZv+zVTq1T73K3xRQsVDDzIZmI2hk9aET/iNGTs7KY/z7U8dPOpKTU47du3po/6+tOPTs4k5Mt0/xq5gIRidoV3bJd82///V1i4u0D+w69PTjMZhirl68RkROxJ7v26bzoi8X38YYAihgF61W/78ERs8eLSFjHfg9uPX8t7bu0Wb960/mzF54IerxF26YTwidlcnFg3jzturSZOHbqH7Y84IHLZjRy8ofu2LR2S6vXQpOdyR52+6I5/87kypAqFV5s8fxXMxf+YWsD/oQIVk46+9PPme+qUsVEHYyJOvh7rwf4k+PfEgJQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUINgAVCDYAFQg2ABUMOe/SkiZo/P/iQAcE/ssACoYRSsVz2n1wAAWcIOC4AaBAuAGgQLgBoEC4AaBAuAGgQLgBoEC4AaBAuAGgQLgBoEC4AaBAuAGgQLgBoEC4AaBAuAGgQLgBoEC4AaBAuAGgQLgBoEC4AaBAuAGgQLgBoEC4AaBAuAGgQLgBoEC4AaBAuAGgQLgBoEC4Aa/wEnLgmN+Yh0GgAAAABJRU5ErkJggg==';
$files = [
    'pdf' => fn($t) => seed_pdf($t),
    'png' => fn($t) => base64_decode($pngB64),
    'wav' => fn($t) => seed_wav(),
    'docx' => fn($t) => seed_office('docx', $t),
    'pptx' => fn($t) => seed_office('pptx', $t),
    'webm' => fn($t) => seed_video('webm'),
    'mp4' => fn($t) => seed_video('mp4'),
];
$mimes = [
    'pdf' => 'application/pdf', 'png' => 'image/png', 'wav' => 'audio/wav',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml',
    'webm' => 'video/webm', 'mp4' => 'video/mp4',
];
$exts = ['pdf' => 'pdf', 'png' => 'png', 'wav' => 'wav', 'docx' => 'docx', 'pptx' => 'pptx', 'webm' => 'webm', 'mp4' => 'mp4'];

$userId = function (string $email) use ($pdo): ?int {
    $s = $pdo->prepare('SELECT id FROM users WHERE email=?');
    $s->execute([$email]);
    $id = $s->fetchColumn();
    return $id === false ? null : (int)$id;
};
$catId = function (string $name) use ($pdo): ?int {
    $s = $pdo->prepare('SELECT id FROM categories WHERE name=?');
    $s->execute([$name]);
    $id = $s->fetchColumn();
    return $id === false ? null : (int)$id;
};
$adminId = $userId('admin@college.edu');
$facultyId = $userId('faculty@college.edu');
$studentId = $userId('student@college.edu');
if ($adminId === null || $facultyId === null || $studentId === null) {
    echo "Demo accounts are missing. Import database/seeds.sql first.\n";
    exit(1);
}

$rows = [
    ['Campus Research Paper Sample', 'A sample research paper for the archive, with searchable metadata.', 'research, paper, sample', 'Research Papers', 'pdf', 'Research Paper Sample.pdf', 'public', 'approved', $facultyId, null],
    ['Campus Photo Collection Sample', 'A sample campus photo for the archive image collection.', 'campus, photo, sample', 'Multimedia', 'png', 'Campus Photo Sample.png', 'public', 'approved', $facultyId, null],
    ['Lecture Audio Sample', 'A short sample lecture recording for the archive audio collection.', 'lecture, audio, sample', 'Learning Materials', 'wav', 'Lecture Audio Sample.wav', 'public', 'approved', $facultyId, null],
    ['Campus Video Tour Sample', 'A short sample campus video tour in WebM format.', 'campus, video, tour', 'Multimedia', 'webm', 'Campus Video Tour Sample.webm', 'public', 'approved', $facultyId, null],
    ['Orientation Video Sample', 'A short sample orientation video in MP4 format.', 'orientation, video, sample', 'Multimedia', 'mp4', 'Orientation Video Sample.mp4', 'public', 'approved', $facultyId, null],
    ['Thesis Template Sample', 'A sample thesis document for the archive.', 'thesis, template, sample', 'Thesis and Capstone', 'docx', 'Thesis Template Sample.docx', 'public', 'approved', $facultyId, null],
    ['Seminar Slides Sample', 'Sample seminar slides for the archive presentation collection.', 'seminar, slides, sample', 'Presentations', 'pptx', 'Seminar Slides Sample.pptx', 'public', 'approved', $facultyId, null],
    ['Private Capstone Draft Sample', 'A private capstone draft. Only its owner and the admin can open it.', 'capstone, draft, private', 'Thesis and Capstone', 'pdf', 'Private Capstone Draft Sample.pdf', 'private', 'pending', $studentId, null],
    ['New Research Draft Sample', 'A new research draft waiting for review.', 'research, draft, sample', 'Research Papers', 'pdf', 'New Research Draft Sample.pdf', 'public', 'pending', $studentId, null],
    ['Returned Research Draft Sample', 'A research draft that was returned for revision.', 'research, draft, returned', 'Research Papers', 'pdf', 'Returned Research Draft Sample.pdf', 'public', 'returned', $studentId, 'Please add a fuller description.'],
];

$done = 0; $skipped = 0;
foreach ($rows as [$title, $desc, $keywords, $catName, $kind, $original, $visibility, $status, $owner, $feedback]) {
    $exists = $pdo->prepare('SELECT id FROM submissions WHERE title=?');
    $exists->execute([$title]);
    if ($exists->fetch()) { echo "Skipped (already exists): {$title}\n"; $skipped++; continue; }
    $data = ($files[$kind])($title);
    if ($data === null || $data === '') { echo "Skipped (could not generate file): {$title}\n"; $skipped++; continue; }
    $category = $catId($catName);
    if ($category === null) { echo "Skipped (category missing): {$title}\n"; $skipped++; continue; }
    $stored = bin2hex(random_bytes(24)).'.'.$exts[$kind];
    $folder = __DIR__.'/../uploads/'.($visibility==='public'?'public':'private');
    if (!is_dir($folder) && !mkdir($folder, 0750, true)) { echo "Skipped (cannot create folder): {$title}\n"; $skipped++; continue; }
    file_put_contents($folder.'/'.$stored, $data);
    $published = $status === 'approved' ? date('Y-m-d H:i:s') : null;
    $reviewedBy = $status === 'returned' ? $adminId : null;
    $stmt = $pdo->prepare('INSERT INTO submissions(user_id,category_id,title,description,keywords,academic_year,resource_type,original_name,stored_name,mime_type,file_size,external_url,visibility,status,feedback,reviewed_by,reviewed_at,published_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$owner, $category, $title, $desc, $keywords, '2025-2026', 'file', $original, $stored, $mimes[$kind], strlen($data), null, $visibility, $status, $feedback, $reviewedBy, $reviewedBy ? date('Y-m-d H:i:s') : null, $published]);
    echo "Seeded: {$title} ({$status}, {$visibility})\n";
    $done++;
}
echo "Done: {$done} seeded, {$skipped} skipped.\n";
