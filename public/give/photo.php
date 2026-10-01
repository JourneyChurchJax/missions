<?php
// A traveler's fundraising page photo, shown to the public only while their page is live.
require dirname(__DIR__) . '/inc/bootstrap.php';
$pg = !empty($_GET['s']) ? page_by_slug((string)$_GET['s']) : null;
$f = $pg && $pg['page_photo_id'] ? one('SELECT * FROM files WHERE id = ? AND person_id = ?', [(int)$pg['page_photo_id'], (int)$pg['person_id']]) : null;
$canSee = $pg && ($pg['page_status'] === 'live' || !empty($_SESSION['preview_ok']) || !empty($_SESSION['auth']));
$path = $f && $f['path'] ? data_dir() . '/uploads/' . basename($f['path']) : '';
if (!$canSee || !$path || !is_file($path) || !str_starts_with((string)$f['mime'], 'image/')) { http_response_code(404); exit; }
header('Content-Type: ' . $f['mime']);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($path);
