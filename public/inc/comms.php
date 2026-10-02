<?php
// Email and text. Every message goes into the outbox table first and is sent from there, a few at a time,
// so a big announcement can't time out halfway or go out twice.
//
// Email: config 'mail_from' turns it on. Add 'smtp' => ['host' => ..., 'port' => 587, 'user' => ..., 'pass' => ...]
// to send through a mail service (Postmark, Amazon SES, Google Workspace) so messages don't land in spam.
// Texts: config 'twilio' => ['sid' => ..., 'token' => ..., 'from' => '+1...']. Texts only go to people who said yes,
// never to numbers that replied STOP, and only between 8am and 9pm.

function mail_ready(): bool { global $config; return !empty($config['mail_from']); }
function smtp_ready(): bool { global $config; return mail_ready() && !empty($config['smtp']['host']); }
function text_ready(): bool { global $config; return !empty($config['twilio']['sid']) && !empty($config['twilio']['token']) && !empty($config['twilio']['from']); }
function site_url(string $path = '/'): string { global $config; return rtrim((string)($config['site_url'] ?? 'https://missions.journeychurch.org'), '/') . $path; }
function church_name(): string { global $config; return (string)($config['church_name'] ?? 'Journey Church'); }

// Put an email in the queue. With $now it also tries to send it right away (for one-off messages like a test).
function send_email(string $to, string $subject, string $body, ?int $trip_id = null, ?int $person_id = null, bool $now = true): bool {
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $id = insert('outbox', ['trip_id' => $trip_id, 'person_id' => $person_id, 'channel' => 'email', 'to_addr' => $to, 'subject' => mb_substr(str_replace(["\r", "\n"], ' ', $subject), 0, 200),
        'body' => $body, 'status' => mail_ready() ? 'queued' : 'not_sent', 'error' => mail_ready() ? null : 'Email is not set up yet', 'attempts' => 0, 'created_by' => current_actor_name(), 'created_at' => now()]);
    return $now && mail_ready() ? deliver((int)$id) : false;
}
function queue_email(string $to, string $subject, string $body, ?int $trip_id = null, ?int $person_id = null): bool { return send_email($to, $subject, $body, $trip_id, $person_id, false) || mail_ready(); }

// Texts wait until 8am if it's late, and never go to numbers that opted out
function send_text(string $to, string $body, ?int $trip_id = null, ?int $person_id = null, bool $now = false): bool {
    $num = normalize_phone($to);
    if (!$num || val('SELECT COUNT(*) FROM sms_optout WHERE id = ?', [$num])) return false;
    $h = (int)date('G');
    $after = $h >= 21 ? date('Y-m-d 08:00:00', strtotime('tomorrow')) : ($h < 8 ? date('Y-m-d 08:00:00') : null);
    $body = mb_substr($body, 0, 600) . "\nReply STOP to stop texts.";
    $id = insert('outbox', ['trip_id' => $trip_id, 'person_id' => $person_id, 'channel' => 'text', 'to_addr' => $num, 'subject' => null, 'body' => $body,
        'status' => text_ready() ? 'queued' : 'not_sent', 'error' => text_ready() ? null : 'Texting is not set up yet', 'send_after' => $after, 'attempts' => 0, 'created_by' => current_actor_name(), 'created_at' => now()]);
    return $now && text_ready() && !$after ? deliver((int)$id) : text_ready();
}
function normalize_phone(string $p): ?string {
    $d = preg_replace('/\D+/', '', $p);
    if (strlen($d) === 10) $d = '1' . $d;
    return strlen($d) >= 11 && strlen($d) <= 15 ? '+' . $d : null;
}

// Send one queued message. The saved copy has private links blanked out afterward.
function deliver(int $id): bool {
    global $config;
    $m = one('SELECT * FROM outbox WHERE id = ?', [$id]);
    if (!$m || $m['status'] !== 'queued') return false;
    update('outbox', $id, ['attempts' => (int)$m['attempts'] + 1]);
    try {
        if ($m['channel'] === 'email') {
            $text = $m['body'] . "\n\n—\n" . church_name() . " Missions\n" . site_url('/');
            $ok = smtp_ready() ? smtp_send($m['to_addr'], (string)$m['subject'], $text) : mail_raw($m['to_addr'], (string)$m['subject'], $text);
        } else {
            $tw = $config['twilio'];
            $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($tw['sid']) . '/Messages.json');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                CURLOPT_USERPWD => $tw['sid'] . ':' . $tw['token'], CURLOPT_POSTFIELDS => http_build_query(['To' => $m['to_addr'], 'From' => $tw['from'], 'Body' => $m['body']])]);
            $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ok = $code >= 200 && $code < 300;
            if (!$ok) throw new RuntimeException(substr((string)$res, 0, 300));
        }
    } catch (Throwable $e) { $ok = false; $err = $e->getMessage(); }
    $tries = (int)$m['attempts'] + 1;
    // Once it's sent (or given up on), private links and one-time codes are blanked out of the saved copy. A retry still needs the real text.
    $final = $ok || $tries >= 3;
    update('outbox', $id, ['status' => $ok ? 'sent' : ($tries >= 3 ? 'failed' : 'queued'), 'sent_at' => $ok ? now() : null,
        'error' => $ok ? null : mb_substr($err ?? 'The mail server refused it', 0, 500),
        'body' => $final ? redact_tokens((string)$m['body']) : $m['body'], 'subject' => $final && $m['subject'] !== null ? redact_tokens((string)$m['subject']) : $m['subject'],
        'send_after' => $ok ? null : date('Y-m-d H:i:s', time() + 300 * $tries)]);
    if (!$ok && $tries >= 3) alert_staff('Messages are failing to send', 'Message #' . $id . ' to ' . $m['to_addr'] . ' failed: ' . ($err ?? ''));
    return $ok;
}
// Send up to $limit queued messages (runs after page loads, and from cron.php)
function process_outbox(int $limit = 15): int {
    $n = 0;
    foreach (all("SELECT id FROM outbox WHERE status = 'queued' AND (send_after IS NULL OR send_after <= ?) ORDER BY id LIMIT " . max(1, $limit), [now()]) as $r) if (deliver((int)$r['id'])) $n++;
    return $n;
}

function mail_headers(): array {
    global $config;
    $from = (string)$config['mail_from']; $name = (string)($config['mail_name'] ?? church_name() . ' Missions');
    return [$from, $name, (string)($config['mail_reply_to'] ?? $from)];
}
function mail_raw(string $to, string $subject, string $text): bool {
    [$from, $name, $reply] = mail_headers();
    $headers = "From: " . mb_encode_mimeheader($name) . " <$from>\r\nReply-To: $reply\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64";
    if (!@mail($to, mb_encode_mimeheader($subject), chunk_split(base64_encode($text)), $headers, '-f' . $from)) throw new RuntimeException('The server mail system refused it');
    return true;
}
// Plain SMTP with STARTTLS and login, enough for Postmark, SES, SendGrid or Google Workspace
function smtp_send(string $to, string $subject, string $text): bool {
    global $config;
    $c = $config['smtp']; [$from, $name, $reply] = mail_headers();
    $port = (int)($c['port'] ?? 587); $host = (string)$c['host'];
    $fp = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
    if (!$fp) throw new RuntimeException("Can't reach the mail server: $errstr");
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) { $out = ''; while (($line = fgets($fp, 515)) !== false) { $out .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; } return $out; };
    $cmd = function (string $c, array $ok) use ($fp, $read) { if ($c !== '') fwrite($fp, $c . "\r\n"); $r = $read(); if (!in_array((int)substr($r, 0, 3), $ok, true)) throw new RuntimeException('Mail server said: ' . trim($r)); return $r; };
    $cmd('', [220]);
    $ehlo = 'EHLO ' . (parse_url(site_url(), PHP_URL_HOST) ?: 'localhost');
    $cmd($ehlo, [250]);
    if ($port !== 465) { $cmd('STARTTLS', [220]); if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('Secure connection failed'); $cmd($ehlo, [250]); }
    if (!empty($c['user'])) { $cmd('AUTH LOGIN', [334]); $cmd(base64_encode((string)$c['user']), [334]); $cmd(base64_encode((string)$c['pass']), [235]); }
    $cmd("MAIL FROM:<$from>", [250]); $cmd("RCPT TO:<$to>", [250, 251]); $cmd('DATA', [354]);
    $msg = "From: " . mb_encode_mimeheader($name) . " <$from>\r\nTo: <$to>\r\nReply-To: $reply\r\nSubject: " . mb_encode_mimeheader($subject) . "\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(12)) . '@' . (parse_url(site_url(), PHP_URL_HOST) ?: 'localhost') . ">\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text));
    $cmd($msg . "\r\n.", [250]); $cmd('QUIT', [221]); fclose($fp);
    return true;
}

// Who gets a team message: travelers (and leaders), optionally their parents. Texts need the person's yes.
function team_recipients(int $trip_id, bool $parents = false): array {
    $out = [];
    foreach (members($trip_id) as $m) {
        $out[] = ['person_id' => (int)$m['person_id'], 'name' => full_name($m), 'email' => $m['email'], 'phone' => $m['phone'], 'sms_ok' => (bool)val('SELECT sms_ok FROM people WHERE id = ?', [$m['person_id']])];
        if ($parents) foreach (guardians((int)$m['person_id']) as $g) $out[] = ['person_id' => (int)$m['person_id'], 'name' => $g['name'], 'email' => $g['email'], 'phone' => $g['phone'], 'sms_ok' => (bool)$g['sms_ok']];
    }
    return $out;
}
// How many emails and texts a message would send, for the "are you sure" step
function recipient_counts(array $people): array {
    $e = []; $t = [];
    foreach ($people as $r) { if ($r['email']) $e[strtolower($r['email'])] = 1; if ($r['phone'] && !empty($r['sms_ok']) && ($n = normalize_phone((string)$r['phone']))) $t[$n] = 1; }
    return [count($e), count($t)];
}
// Queue one message to many people. Returns [emails queued, texts queued, people skipped].
function broadcast(int $trip_id, array $people, string $subject, string $body, bool $email, bool $text): array {
    $e = 0; $t = 0; $skip = 0; $seenE = []; $seenT = [];
    foreach ($people as $r) {
        $did = false;
        if ($email && $r['email'] && !isset($seenE[strtolower($r['email'])])) { $seenE[strtolower($r['email'])] = 1; if (queue_email($r['email'], $subject, $body, $trip_id, $r['person_id'])) $e++; $did = true; }
        $n = $r['phone'] ? normalize_phone((string)$r['phone']) : null;
        if ($text && $n && !empty($r['sms_ok']) && !isset($seenT[$n])) { $seenT[$n] = 1; if (send_text($n, $subject . ': ' . $body, $trip_id, $r['person_id'])) $t++; $did = true; }
        if (!$did) $skip++;
    }
    return [$e, $t, $skip];
}
// Plain sentence for a flash message after sending
function sent_note(array $r, bool $email, bool $text): string {
    [$e, $t, $skip] = $r;
    if (!$email && !$text) return '';
    $bits = [];
    if ($email) $bits[] = mail_ready() ? "$e email" . ($e === 1 ? '' : 's') . ' on the way' : 'emails saved (email isn\'t set up yet)';
    if ($text) $bits[] = text_ready() ? "$t text" . ($t === 1 ? '' : 's') . ' on the way' : 'texts saved (texting isn\'t set up yet)';
    return ' ' . ucfirst(implode(' and ', $bits)) . '.' . ($skip ? " $skip had no way to reach them." : '');
}

// ---------- Parents and guardians ----------
function guardians(int $person_id): array { return all('SELECT * FROM guardians WHERE person_id = ? ORDER BY id', [$person_id]); }
function parent_url(array $g): string { return site_url('/parent/?t=' . $g['token']); }
// A parent link works until 60 days after the child's last trip ends; then staff send a new one
function guardian_link_valid(array $g): bool {
    $end = val('SELECT MAX(t.end_date) FROM trips t JOIN members m ON m.trip_id = t.id WHERE m.person_id = ?', [$g['person_id']]);
    return !$end || $end >= date('Y-m-d', strtotime('-60 days'));
}

// ---------- Calendar ----------
function cal_token_for_person(int $person_id): string {
    $t = val('SELECT cal_token FROM people WHERE id = ?', [$person_id]);
    if ($t) return (string)$t;
    $tok = new_token(); update('people', $person_id, ['cal_token' => $tok]); return $tok;
}
function cal_token_for_trip(int $trip_id): string {
    $t = val('SELECT cal_token FROM trips WHERE id = ?', [$trip_id]);
    if ($t) return (string)$t;
    $tok = new_token(); update('trips', $trip_id, ['cal_token' => $tok]); return $tok;
}
// Always https, so the private link is never sent unencrypted
function cal_url(string $kind, string $tok): string { return site_url('/calendar.php?' . $kind . '=' . $tok); }

// ---------- Chat ----------
// Threads: 'team' (whole team plus leaders) and 'p{person_id}' (one traveler with the leaders and staff)
function chat_thread_ok(string $thread, int $trip_id, bool $staff, ?int $me): bool {
    if ($staff) return $thread === 'team' || (bool)preg_match('/^p\d+$/', $thread);
    $mem = $me ? member_of($trip_id, $me) : null;
    if (!$mem) return false;
    if ($thread === 'team') return true;
    if (preg_match('/^p(\d+)$/', $thread, $m)) return (int)$m[1] === $me || (in_array($mem['role'], ['leader', 'admin'], true) && member_of($trip_id, (int)$m[1]));
    return false;
}
function chat_messages(int $trip_id, string $thread, int $after = 0): array {
    return all('SELECT * FROM chat WHERE trip_id = ? AND thread = ? AND id > ? ORDER BY id LIMIT 300', [$trip_id, $thread, $after]);
}
