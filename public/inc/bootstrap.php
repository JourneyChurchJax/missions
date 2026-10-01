<?php
// Journey Missions — loaded first by every page.
declare(strict_types=1);

date_default_timezone_set('America/New_York');

session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Lax']);
session_start();

// Server-only settings. config.php lives one folder ABOVE public_html so it can never be downloaded,
// and it is never committed to git. See README → "Server config".
$config = [];
foreach ([dirname(__DIR__, 2) . '/config.php', dirname(__DIR__) . '/config.php'] as $path) {
    if (is_file($path)) { $config = require $path; break; }
}

function greeting(): string {
    $h = (int)date('G');
    return $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
}
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money(float $n, int $dec = 0): string { return '$' . number_format($n, $dec); }
function pct(float $a, float $b): int { return $b > 0 ? (int)round($a / $b * 100) : 0; }
function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper(substr($parts[0] ?? '', 0, 1) . substr(end($parts) ?: '', 0, 1));
}

// Preview gate. Until real sign-in with Planning Center is built, the whole site sits behind one
// preview password set in config.php ('preview_password' => '...').
function require_preview(): void {
    global $config;
    if (!empty($_SESSION['preview_ok'])) return;
    header('Location: /signin.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
}

require __DIR__ . '/data.php';
require __DIR__ . '/layout.php';
