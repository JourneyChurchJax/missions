<?php
// Journey Missions — loaded first by every page.
declare(strict_types=1);

date_default_timezone_set('America/New_York');

// Server-only settings. config.php lives one folder ABOVE public_html so it can never be downloaded,
// and it is never committed to git. See README → "Server config".
$config = [];
foreach ([dirname(__DIR__, 2) . '/config.php', dirname(__DIR__) . '/config.php'] as $path) {
    if (is_file($path)) { $config = require $path; $config['_path'] = $path; break; }
}

// ---------- Errors: never shown to visitors, always logged, staff alerted ----------
function log_dir(): string { $d = dirname(__DIR__, 2) . '/data/logs'; if (!is_dir($d)) @mkdir($d, 0750, true); return $d; }
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', log_dir() . '/php-errors.log');
function app_log(string $msg, string $file = 'app'): void {
    @file_put_contents(log_dir() . "/$file.log", '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}
// One alert email per kind of problem per hour, so a broken page can't flood the inbox
function alert_staff(string $subject, string $body): void {
    global $config;
    $to = $config['alert_email'] ?? null;
    if (!$to) return;
    $stamp = log_dir() . '/alert-' . substr(md5($subject), 0, 12);
    if (is_file($stamp) && filemtime($stamp) > time() - 3600) return;
    @touch($stamp);
    @mail((string)$to, '[Journey Missions] ' . $subject, $body . "\n\n" . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . ($_SERVER['REQUEST_URI'] ?? ''), 'From: ' . ($config['mail_from'] ?? 'no-reply@journeychurch.org'));
}
set_exception_handler(function (Throwable $e) {
    app_log(get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString(), 'errors');
    alert_staff('Site error: ' . mb_substr($e->getMessage(), 0, 80), $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Something went wrong</title>'
        . '<body style="font-family:-apple-system,Inter,sans-serif;background:#f7f4f0;color:#0a0a0a;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px">'
        . '<div style="max-width:440px;background:#fbf9f6;border-radius:20px;padding:36px;box-shadow:0 8px 30px rgba(0,0,0,.08)"><h1 style="margin:0 0 8px;font-size:28px">Something went wrong.</h1>'
        . '<p style="margin:0 0 18px;color:#5a574f">We\'ve been notified. Please go back and try again in a minute.</p><a href="/" style="color:#0a0a0a;font-weight:600">Go to the start</a></div>';
});

// ---------- Small input helpers: always strings, never arrays ----------
function g(string $k, string $default = ''): string { $v = $_GET[$k] ?? $default; return is_scalar($v) ? trim((string)$v) : $default; }
function gi(string $k): int { $v = $_GET[$k] ?? 0; return is_scalar($v) ? (int)$v : 0; }
function is_https(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443; }
function is_local(): bool { return in_array($_SERVER['SERVER_NAME'] ?? '', ['127.0.0.1', 'localhost'], true) || PHP_SAPI === 'cli'; }
// Only same-site paths like /admin/trip.php?id=3. Rejects //evil.com, /\evil.com and full URLs.
function safe_path(?string $p, string $fallback = '/'): string {
    $p = (string)$p;
    return preg_match('#^/(?![/\\\\])[A-Za-z0-9_\-./?=&%+:~,]*$#', $p) && !str_contains($p, '\\') ? $p : $fallback;
}

// ---------- Session ----------
// Stateless endpoints (webhooks, calendar feeds, photos) define NO_SESSION before loading this file.
if (!defined('NO_SESSION')) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['httponly' => true, 'secure' => is_https() || !is_local(), 'samesite' => 'Lax', 'path' => '/']);
    session_start();
    // Signed-in sessions end after 8 idle hours or 7 days, and when the preview password changes
    if (!empty($_SESSION['preview_ok']) || !empty($_SESSION['auth'])) {
        $now = time();
        $pw = hash('sha256', 'pv:' . (string)($config['preview_password'] ?? ''));
        $stale = $now - (int)($_SESSION['seen'] ?? $now) > 8 * 3600 || $now - (int)($_SESSION['since'] ?? $now) > 7 * 86400
            || (!empty($_SESSION['preview_ok']) && ($_SESSION['pv'] ?? '') !== $pw);
        if ($stale) { $_SESSION = []; session_regenerate_id(true); }
        else $_SESSION['seen'] = $now;
    }
} else { $_SESSION = []; }

function greeting(): string {
    $h = (int)date('G');
    return $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
}
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(float $n, int $dec = 0): string { return ($n < 0 ? '−$' : '$') . number_format(abs($n), $dec); }
function pct(float $a, float $b): int { return $b > 0 ? max(0, (int)floor($a / $b * 100)) : 0; }
function initials(string $name): string {
    $parts = preg_split('/\s+/u', trim($name)) ?: [''];
    return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr((string)end($parts), 0, 1) : ''));
}
// Cache-busting for assets: the file's last change time
function asset(string $path): string { $f = dirname(__DIR__) . $path; return $path . '?v=' . (is_file($f) ? filemtime($f) : '1'); }

// ---------- Who is signed in ----------
// Staff: the preview password, or a Planning Center sign-in marked as staff. Everyone else signs in with Planning Center.
function is_staff_session(): bool { return !empty($_SESSION['preview_ok']) || !empty($_SESSION['auth']['staff']); }
// Staff looking at the traveler side as someone else: read-only
function impersonating(): bool { return is_staff_session() && ($_SESSION['view'] ?? 'staff') === 'traveler'; }
function signed_in(): bool { return !empty($_SESSION['preview_ok']) || !empty($_SESSION['auth']); }

function require_preview(?string $view = null): void {
    if (is_staff_session()) { if ($view) $_SESSION['view'] = $view; elseif (empty($_SESSION['view'])) $_SESSION['view'] = 'staff'; return; }
    if (!empty($_SESSION['auth'])) {
        $_SESSION['view'] = 'traveler'; $_SESSION['person_id'] = (int)$_SESSION['auth']['person_id'];
        if ($view === 'staff') { header('Location: /trip/'); exit; }
        return;
    }
    header('Location: /signin.php?next=' . urlencode(safe_path($_SERVER['REQUEST_URI'] ?? '/')));
    exit;
}
// Global admin pages (People, Giving, Settings…): staff only
function require_staff(): void {
    require_preview();
    if (!is_staff_session()) { header('Location: /trip/'); exit; }
    $_SESSION['view'] = 'staff';
}

require __DIR__ . '/db.php';
require __DIR__ . '/models.php';
require __DIR__ . '/roles.php';
require __DIR__ . '/apps.php';
require __DIR__ . '/money.php';
require __DIR__ . '/comms.php';
require __DIR__ . '/signing.php';
require __DIR__ . '/give.php';
require __DIR__ . '/pco.php';
require __DIR__ . '/privacy.php';
require __DIR__ . '/layout.php';
