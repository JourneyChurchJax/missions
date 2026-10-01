<?php
// New chat messages since ?after=, as HTML bubbles. Used by the composer to refresh without reloading.
require __DIR__ . '/inc/bootstrap.php';
require_preview();
$trip_id = (int)($_GET['trip'] ?? 0); $thread = (string)($_GET['thread'] ?? ''); $after = (int)($_GET['after'] ?? 0);
$staff = ($_SESSION['view'] ?? 'staff') === 'staff'; $me = acting_person_id();
if (!chat_thread_ok($thread, $trip_id, $staff, $me)) { http_response_code(403); exit; }
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
foreach (chat_messages($trip_id, $thread, $after) as $m) echo chat_bubble($m, chat_is_mine($m, $staff, $me));
