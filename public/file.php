<?php
// Opens a trip document or an uploaded file. Files live outside public_html, so this is the only way in.
require __DIR__ . '/inc/bootstrap.php';
require_preview();

$f = one('SELECT * FROM files WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$f) { http_response_code(404); exit('Not found'); }

$staff = ($_SESSION['view'] ?? 'staff') === 'staff';
$me = acting_person_id();
$allowed = $staff
    || ((int)$f['person_id'] === $me)
    || ($f['visible'] && $f['trip_id'] && member_of((int)$f['trip_id'], (int)$me));
if (!$allowed) { http_response_code(403); exit('You do not have access to this file.'); }

if (!$staff && $me) {
    $ack = one('SELECT id FROM file_acks WHERE file_id = ? AND person_id = ?', [$f['id'], $me]);
    if (!$ack) insert('file_acks', ['file_id' => $f['id'], 'person_id' => $me, 'opened_at' => now()]);
}

if ($f['kind'] === 'link' || (!$f['path'] && $f['url'])) {
    if ($f['url']) { header('Location: ' . $f['url']); exit; }
}
$path = $f['path'] ? data_dir() . '/uploads/' . basename($f['path']) : '';
if (!$path || !is_file($path)) {
    page_open($f['title']); ?>
    <main class="gate"><div class="card"><h1 class="disp" style="margin:0;font-size:32px"><?= e($f['title']) ?></h1>
    <p class="muted" style="margin:0">This document hasn't been uploaded yet. Your trip leader will post it soon.</p>
    <a class="btn" href="javascript:history.back()">Go back</a></div></main>
    <?php page_close(); exit;
}
header('Content-Type: ' . ($f['mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $f['original'] ?: $f['title']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
