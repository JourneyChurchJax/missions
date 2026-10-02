<?php
// For uptime monitors: returns "ok" when the site and database work. Point a free monitor (UptimeRobot, Better Stack) here.
define('NO_SESSION', true);
define('REAL_DB', true);
define('NO_HOUSEKEEPING', true);
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: text/plain'); header('Cache-Control: no-store');
try { val('SELECT 1'); $failed = (int)val("SELECT COUNT(*) FROM outbox WHERE status = 'failed' AND created_at > ?", [date('Y-m-d H:i:s', strtotime('-1 day'))]); echo 'ok' . ($failed ? " (failed messages today: $failed)" : ''); }
catch (Throwable $e) { http_response_code(500); echo 'database error'; }
