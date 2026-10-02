<?php
// Opens a trip document or an uploaded file. Files live outside public_html, so this is the only way in.
if (!empty($_GET['g'])) define('REAL_DB', true);
require __DIR__ . '/inc/bootstrap.php';

$f = one('SELECT * FROM files WHERE id = ?', [gi('id')]);
// Parents open team documents (like the waiver they sign) with their private link, without signing in
$guardian = g('g') !== '' ? one('SELECT * FROM guardians WHERE token = ?', [g('g')]) : null;
if (g('g') !== '') {
    if (!$guardian || !guardian_link_valid($guardian) || !$f || !$f['visible'] || $f['person_id'] || !in_array($f['kind'], ['doc', 'link'], true) || !member_of((int)$f['trip_id'], (int)$guardian['person_id'])) { http_response_code(403); exit('You do not have access to this file.'); }
} else require_preview();
if (!$f) { http_response_code(404); exit('Not found'); }

$staff = !$guardian && is_staff_session();
$me = $guardian ? null : acting_person_id();
$allowed = $guardian || $staff
    || ($f['person_id'] && (int)$f['person_id'] === (int)$me && !impersonating())
    || ($f['visible'] && !$f['person_id'] && $f['trip_id'] && member_of((int)$f['trip_id'], (int)$me))
    || ($f['trip_id'] && !$f['person_id'] && can('documents', (int)$f['trip_id']))
    || ($f['trip_id'] && $f['person_id'] && can('team', (int)$f['trip_id']));
if (!$allowed) { http_response_code(403); exit('You do not have access to this file.'); }

if (!$staff && $me && !$guardian && !impersonating() && !$f['person_id']) {
    if (!one('SELECT id FROM file_acks WHERE file_id = ? AND person_id = ?', [$f['id'], $me])) insert('file_acks', ['file_id' => $f['id'], 'person_id' => $me, 'opened_at' => now()]);
}
if ($f['person_id'] && ($staff || can('team', (int)$f['trip_id']))) audit('file_view', 'files', (int)$f['id'], $f['trip_id'] ? (int)$f['trip_id'] : null);

if ($f['kind'] === 'link' || (!$f['path'] && $f['url'])) {
    if ($f['url'] && preg_match('#^https?://#i', (string)$f['url'])) { header('Location: ' . $f['url']); exit; }
}
$path = $f['path'] ? upload_dir() . '/' . basename($f['path']) : '';
if ($path && !is_file($path)) $path = data_dir() . '/uploads/' . basename($f['path']);
if (!$path || !is_file($path)) {
    if ($guardian) { public_open($f['title']); echo '<main class="pub-main" id="main"><section class="tile xl center-tile"><h1 class="disp" style="font-size:32px">' . e($f['title']) . '</h1><p class="muted" style="margin:0">This document hasn\'t been uploaded yet. The trip leader will post it soon.</p></section></main>'; public_close(); exit; }
    http_response_code(404);
    page_open($f['title']); ?>
    <main class="gate" id="main"><div class="card"><h1 class="disp" style="margin:0;font-size:32px"><?= e($f['title']) ?></h1>
    <p class="muted" style="margin:0">This document hasn't been uploaded yet. Your trip leader will post it soon.</p>
    <a class="btn" href="/trip/documents.php">Back to documents</a></div></main>
    <?php page_close(); exit;
}
$name = (string)($f['original'] ?: $f['title']);
$ascii = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'file';
$photo = $f['kind'] === 'photo' && str_starts_with((string)$f['mime'], 'image/');
header('Content-Type: ' . ($f['mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Cache-Control: ' . ($photo ? 'private, max-age=86400' : 'private, no-store'));
header('X-Content-Type-Options: nosniff');
readfile($path);
