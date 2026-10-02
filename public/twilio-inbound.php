<?php
// Twilio sends replies here. STOP (and similar) turns texts off for that number; START turns them back on; HELP explains.
// In Twilio, set the phone number's "A message comes in" webhook to https://missions.journeychurch.org/twilio-inbound.php
define('NO_SESSION', true);
define('REAL_DB', true);
define('ACTOR', 'Text reply');
require __DIR__ . '/inc/bootstrap.php';
global $config;
// Twilio signs each request with your auth token; reject anything that isn't really from Twilio
$sig = (string)($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '');
$url = site_url('/twilio-inbound.php');
$params = $_POST; ksort($params);
$data = $url; foreach ($params as $k => $v) $data .= $k . (is_string($v) ? $v : '');
if (!text_ready() || !hash_equals(base64_encode(hash_hmac('sha1', $data, (string)$config['twilio']['token'], true)), $sig)) { http_response_code(403); exit; }
$from = normalize_phone((string)($_POST['From'] ?? ''));
$word = strtoupper(trim((string)($_POST['Body'] ?? '')));
header('Content-Type: text/xml');
$reply = '';
if ($from && in_array($word, ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'], true)) {
    try { insert('sms_optout', ['id' => $from, 'at' => now()]); } catch (Throwable $e) {}
    audit('sms_optout', 'sms', null, null, $from);
} elseif ($from && in_array($word, ['START', 'UNSTOP', 'YES'], true)) {
    q('DELETE FROM sms_optout WHERE id = ?', [$from]);
    audit('sms_optin', 'sms', null, null, $from);
} elseif ($word === 'HELP' || $word === 'INFO') {
    $reply = church_name() . ' Missions trip texts. Reply STOP to stop. Questions: ' . ($config['mail_reply_to'] ?? $config['mail_from'] ?? 'contact the church office') . '.';
}
echo '<?xml version="1.0" encoding="UTF-8"?><Response>' . ($reply ? '<Message>' . htmlspecialchars($reply, ENT_XML1) . '</Message>' : '') . '</Response>';
