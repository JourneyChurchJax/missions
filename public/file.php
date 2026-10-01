<?php
// Opens a trip document or an uploaded file. Files live outside public_html, so this is the only way in.
require __DIR__ . '/inc/bootstrap.php';

$f = one('SELECT * FROM files WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
// Parents open team documents (like the waiver they sign) with their private link, without signing in
$guardian = !empty($_GET['g']) ? one('SELECT * FROM guardians WHERE token = ?', [(string)$_GET['g']]) : null;
if ($guardian) {
    if (!$f || !$f['visible'] || $f['person_id'] || !in_array($f['kind'], ['doc', 'link'], true) || !member_of((int)$f['trip_id'], (int)$guardian['person_id'])) { http_response_code(403); exit('You do not have access to this file.'); }
} else require_preview();
if (!$f) { http_response_code(404); exit('Not found'); }

$staff = !$guardian && ($_SESSION['view'] ?? 'staff') === 'staff';
$me = $guardian ? null : acting_person_id();
$allowed = $guardian || $staff
    || ((int)$f['person_id'] === $me)
    || ($f['visible'] && $f['trip_id'] && member_of((int)$f['trip_id'], (int)$me));
if (!$allowed) { http_response_code(403); exit('You do not have access to this file.'); }

if (!$staff && $me && !$guardian) {
    $ack = one('SELECT id FROM file_acks WHERE file_id = ? AND person_id = ?', [$f['id'], $me]);
    if (!$ack) insert('file_acks', ['file_id' => $f['id'], 'person_id' => $me, 'opened_at' => now()]);
}

if ($f['kind'] === 'link' || (!$f['path'] && $f['url'])) {
    if ($f['url']) { header('Location: ' . $f['url']); exit; }
}
$path = $f['path'] ? data_dir() . '/uploads/' . basename($f['path']) : '';
if (!$path || !is_file($path)) {
    if ($guardian) { public_open($f['title']); echo '<main class="pub-main"><section class="tile xl" style="padding:36px;gap:10px"><h1 class="disp" style="font-size:32px">' . e($f['title']) . '</h1><p class="muted" style="margin:0">This document hasn\'t been uploaded yet. The trip leader will post it soon.</p></section></main></body></html>'; exit; }
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
