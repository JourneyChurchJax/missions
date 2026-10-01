<?php
// Email and text. Every message is written to the outbox table first, then sent.
// Email sends when config.php has 'mail_from' (uses the server's mail). Texts send when config.php has
// 'twilio' => ['sid' => ..., 'token' => ..., 'from' => '+1...']. Without those, messages are saved as "Not sent" so nothing is lost.

function mail_ready(): bool { global $config; return !empty($config['mail_from']); }
function text_ready(): bool { global $config; return !empty($config['twilio']['sid']) && !empty($config['twilio']['token']) && !empty($config['twilio']['from']); }
function site_url(string $path = '/'): string { return 'https://missions.journeychurch.org' . $path; }

function send_email(string $to, string $subject, string $body, ?int $trip_id = null, ?int $person_id = null): bool {
    global $config;
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $id = insert('outbox', ['trip_id' => $trip_id, 'person_id' => $person_id, 'channel' => 'email', 'to_addr' => $to, 'subject' => $subject,
        'body' => $body, 'status' => 'queued', 'created_by' => function_exists('current_actor_name') ? current_actor_name() : 'System', 'created_at' => now()]);
    if (!mail_ready()) { update('outbox', $id, ['status' => 'not_sent', 'error' => 'Email is not set up yet']); return false; }
    $from = $config['mail_from'];
    $name = $config['mail_name'] ?? 'Journey Church Missions';
    $headers = "From: " . mb_encode_mimeheader($name) . " <$from>\r\nReply-To: " . ($config['mail_reply_to'] ?? $from) . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
    $text = $body . "\n\n—\nJourney Church Missions\n" . site_url('/');
    $ok = @mail($to, mb_encode_mimeheader($subject), $text, $headers, '-f' . $from);
    update('outbox', $id, ['status' => $ok ? 'sent' : 'failed', 'error' => $ok ? null : 'The mail server refused it']);
    return $ok;
}

function send_text(string $to, string $body, ?int $trip_id = null, ?int $person_id = null): bool {
    global $config;
    $digits = preg_replace('/\D+/', '', $to);
    if (strlen($digits) === 10) $digits = '1' . $digits;
    if (strlen($digits) < 11) return false;
    $to = '+' . $digits;
    $id = insert('outbox', ['trip_id' => $trip_id, 'person_id' => $person_id, 'channel' => 'text', 'to_addr' => $to, 'subject' => null,
        'body' => $body, 'status' => 'queued', 'created_by' => function_exists('current_actor_name') ? current_actor_name() : 'System', 'created_at' => now()]);
    if (!text_ready()) { update('outbox', $id, ['status' => 'not_sent', 'error' => 'Texting is not set up yet']); return false; }
    $tw = $config['twilio'];
    $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($tw['sid']) . '/Messages.json');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => $tw['sid'] . ':' . $tw['token'], CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => $tw['from'], 'Body' => $body])]);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $ok = $code >= 200 && $code < 300;
    update('outbox', $id, ['status' => $ok ? 'sent' : 'failed', 'error' => $ok ? null : substr((string)$res, 0, 500)]);
    return $ok;
}

// Who gets a team message: travelers (and leaders), optionally their parents
function team_recipients(int $trip_id, bool $parents = false): array {
    $out = [];
    foreach (members($trip_id) as $m) {
        $out[] = ['person_id' => (int)$m['person_id'], 'name' => full_name($m), 'email' => $m['email'], 'phone' => $m['phone']];
        if ($parents) foreach (guardians((int)$m['person_id']) as $g) $out[] = ['person_id' => (int)$m['person_id'], 'name' => $g['name'], 'email' => $g['email'], 'phone' => $g['phone']];
    }
    return $out;
}
// Send one message to many people. Returns [emails sent, texts sent, skipped].
function broadcast(int $trip_id, array $people, string $subject, string $body, bool $email, bool $text): array {
    $e = 0; $t = 0; $skip = 0; $seenE = []; $seenT = [];
    foreach ($people as $r) {
        $did = false;
        if ($email && $r['email'] && !isset($seenE[strtolower($r['email'])])) { $seenE[strtolower($r['email'])] = 1; if (send_email($r['email'], $subject, $body, $trip_id, $r['person_id'])) $e++; $did = true; }
        if ($text && $r['phone'] && !isset($seenT[$r['phone']])) { $seenT[$r['phone']] = 1; if (send_text($r['phone'], $subject . ': ' . $body, $trip_id, $r['person_id'])) $t++; $did = true; }
        if (!$did) $skip++;
    }
    return [$e, $t, $skip];
}
// Plain sentence for a flash message after sending
function sent_note(array $r, bool $email, bool $text): string {
    [$e, $t, $skip] = $r;
    if (!$email && !$text) return '';
    if (!mail_ready() && !text_ready()) return ' Email and text aren\'t set up yet, so messages were saved in Settings → Email and text.';
    $bits = [];
    if ($email) $bits[] = mail_ready() ? "$e email" . ($e === 1 ? '' : 's') : 'emails saved (email not set up)';
    if ($text) $bits[] = text_ready() ? "$t text" . ($t === 1 ? '' : 's') : 'texts saved (texting not set up)';
    return ' Sent ' . implode(' and ', $bits) . '.' . ($skip ? " $skip had no contact info." : '');
}

// ---------- Parents and guardians ----------
function guardians(int $person_id): array { return all('SELECT * FROM guardians WHERE person_id = ? ORDER BY id', [$person_id]); }
function parent_url(array $g): string { return site_url('/parent/?t=' . $g['token']); }

// ---------- Calendar ----------
function cal_token_for_person(int $person_id): string {
    $p = person($person_id);
    if (!empty($p['cal_token'])) return $p['cal_token'];
    $tok = new_token(); update('people', $person_id, ['cal_token' => $tok]); return $tok;
}
function cal_token_for_trip(int $trip_id): string {
    $t = trip($trip_id);
    if (!empty($t['cal_token'])) return $t['cal_token'];
    $tok = new_token(); update('trips', $trip_id, ['cal_token' => $tok]); return $tok;
}
function cal_url(string $kind, string $tok): string { return 'webcal://missions.journeychurch.org/calendar.php?' . $kind . '=' . $tok; }

// ---------- Chat ----------
// Threads: 'team' (whole team plus leaders) and 'p{person_id}' (one traveler with the leaders and staff)
function chat_thread_ok(string $thread, int $trip_id, bool $staff, ?int $me): bool {
    if ($thread === 'team') return $staff || ($me && member_of($trip_id, $me));
    if (preg_match('/^p(\d+)$/', $thread, $m)) return $staff || ($me && (int)$m[1] === $me && member_of($trip_id, $me)) || ($me && in_array(member_of($trip_id, $me)['role'] ?? '', ['leader', 'admin'], true));
    return false;
}
function chat_messages(int $trip_id, string $thread, int $after = 0): array {
    return all('SELECT * FROM chat WHERE trip_id = ? AND thread = ? AND id > ? ORDER BY id', [$trip_id, $thread, $after]);
}
