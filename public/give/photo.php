<?php
// A traveler's fundraising page photo: public while their page is live; otherwise only for them and staff.
define('REAL_DB', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$pg = g('s') !== '' ? page_by_slug(g('s')) : null;
$f = $pg && $pg['page_photo_id'] ? one('SELECT * FROM files WHERE id = ? AND person_id = ?', [(int)$pg['page_photo_id'], (int)$pg['person_id']]) : null;
$public = $pg && $pg['page_status'] === 'live';
$canSee = $public || is_staff_session() || ($pg && (int)($_SESSION['auth']['person_id'] ?? 0) === (int)$pg['person_id']);
$path = $f && $f['path'] ? data_dir() . '/uploads/' . basename($f['path']) : '';
if (!$canSee || !$path || !is_file($path) || !str_starts_with((string)$f['mime'], 'image/')) { http_response_code(404); exit; }
header('Content-Type: ' . $f['mime']);
header('Content-Length: ' . filesize($path));
header('Cache-Control: ' . ($public ? 'public, max-age=86400' : 'private, no-store'));
header('X-Content-Type-Options: nosniff');
readfile($path);
