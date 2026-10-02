<?php
// Background work: sends queued email and texts, makes the daily backup, and cleans up old private data.
// The site also does this on its own after normal page visits. For reliability, add a SiteGround cron job every 5 minutes:
//   curl -s "https://missions.journeychurch.org/cron.php?key=YOUR_CRON_KEY" > /dev/null
// with 'cron_key' => 'YOUR_CRON_KEY' in config.php. Or run it from the command line: php cron.php
define('NO_SESSION', true);
define('REAL_DB', true);
define('NO_HOUSEKEEPING', true);
define('ACTOR', 'Scheduled task');
require __DIR__ . '/inc/bootstrap.php';
global $config;
if (PHP_SAPI !== 'cli' && (empty($config['cron_key']) || !hash_equals((string)$config['cron_key'], g('key')))) { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain');
$sent = process_outbox(100);
data_key();   // the encryption key exists from day one, so it can be backed up
$stamp = data_dir() . '/last-daily';
if (!is_file($stamp) || filemtime($stamp) < time() - 86400) { touch($stamp); backup_now('daily'); $purged = purge_old_sensitive(); q('DELETE FROM rate_hits WHERE at < ?', [time() - 86400]); echo "daily: backup done, purged $purged\n"; }
echo "sent $sent\n";
