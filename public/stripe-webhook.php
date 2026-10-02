<?php
// Stripe calls this after payments. Point a Stripe webhook at https://missions.journeychurch.org/stripe-webhook.php
// with these events: checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed,
// invoice.paid, customer.subscription.deleted, charge.refunded, charge.dispute.created, charge.dispute.closed
define('NO_SESSION', true);
define('REAL_DB', true);
define('ACTOR', 'Stripe');
require __DIR__ . '/inc/bootstrap.php';
global $config;

$payload = (string)file_get_contents('php://input');
$secret = (string)($config['stripe']['webhook_secret'] ?? '');
if ($secret === '' || !stripe_verify($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $secret)) { http_response_code(400); exit('Bad signature'); }
$ev = json_decode($payload, true);
if (!is_array($ev) || empty($ev['id']) || empty($ev['type'])) { http_response_code(400); exit('Bad payload'); }

$o = $ev['data']['object'] ?? [];
$meta = $o['metadata'] ?? [];
// Newer Stripe versions moved some invoice fields; read both shapes
$inv_sub = fn(array $i) => $i['subscription'] ?? ($i['parent']['subscription_details']['subscription'] ?? null);
$inv_meta = fn(array $i) => $i['subscription_details']['metadata'] ?? ($i['parent']['subscription_details']['metadata'] ?? ($i['lines']['data'][0]['metadata'] ?? []));
$inv_pi = fn(array $i) => $i['payment_intent'] ?? ($i['payments']['data'][0]['payment']['payment_intent'] ?? null);

try {
    // Claim the event first, inside one transaction with the work: a retry or a duplicate delivery can't double count
    $done = tx(function () use ($ev, $o, $meta, $inv_sub, $inv_meta, $inv_pi) {
        try { insert('stripe_events', ['id' => $ev['id'], 'type' => (string)$ev['type'], 'received_at' => now()]); }
        catch (PDOException $e) { return false; }   // already handled
        $created = (int)($o['created'] ?? $ev['created'] ?? time());
        switch ($ev['type']) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                // Bank payments finish later: the first event says "unpaid", the async_payment_succeeded event records it
                if (($o['payment_status'] ?? '') === 'unpaid') break;
                $amount = round(($o['amount_total'] ?? 0) / 100, 2);
                $cust = $o['customer_details'] ?? [];
                $kind = $meta['kind'] ?? '';
                $pi = isset($o['payment_intent']) && is_string($o['payment_intent']) ? $o['payment_intent'] : null;
                if ($kind === 'gift' && ($o['mode'] ?? '') === 'subscription') {
                    $parts = preg_split('/\s+/', trim((string)($cust['name'] ?? '')), 2);
                    $donor = find_or_make_donor($meta['first'] ?? ($parts[0] ?? ''), $meta['last'] ?? ($parts[1] ?? ''), (string)($cust['email'] ?? ''), '', $o['customer'] ?? null);
                    if (!one('SELECT id FROM recurring WHERE stripe_sub_id = ?', [(string)$o['subscription']]))
                        insert('recurring', ['donor_id' => $donor, 'trip_id' => (int)($meta['trip_id'] ?? 0) ?: null, 'person_id' => (int)($meta['person_id'] ?? 0) ?: null,
                            'amount' => $amount, 'stripe_sub_id' => (string)$o['subscription'], 'status' => 'active', 'created_at' => now()]);
                    else q('UPDATE recurring SET donor_id = COALESCE(donor_id, ?) WHERE stripe_sub_id = ?', [$donor, (string)$o['subscription']]);
                } elseif ($kind === 'gift') {
                    record_stripe_gift($meta, $amount, (string)($pi ?? $o['id']), $pi, $cust, null, $created, $o['customer'] ?? null);
                } elseif ($kind === 'payment') {
                    $trip = (int)($meta['trip_id'] ?? 0); $person = (int)($meta['person_id'] ?? 0);
                    $sid = (string)($pi ?? $o['id']);
                    if ($trip && $person && !one('SELECT id FROM payments WHERE stripe_id = ?', [$sid])) {
                        // Record it even if they left the trip in the meantime: it's real money
                        insert('payments', ['trip_id' => $trip, 'person_id' => $person, 'amount' => $amount, 'method' => 'card', 'kind' => 'payment', 'paid_on' => date('Y-m-d', $created),
                            'note' => 'Paid online', 'stripe_id' => $sid, 'status' => 'ok', 'created_by' => 'Stripe', 'created_at' => now()]);
                        sync_raised($trip, $person);
                        insert('activity', ['trip_id' => $trip, 'who' => 'Stripe', 'what' => full_name(person_basic($person)) . ' paid ' . money($amount, 2) . ' online', 'created_at' => now()]);
                        if (!member_of($trip, $person)) alert_staff('Online payment from someone not on the trip', full_name(person_basic($person)) . ' paid ' . money($amount, 2) . ' for trip #' . $trip . ' but is no longer on the team. Decide whether to refund it in Stripe.');
                    }
                } elseif ($kind === 'app_deposit') {
                    $app = !empty($meta['app']) ? one('SELECT * FROM applications WHERE token = ?', [(string)$meta['app']]) : null;
                    if ($app) {
                        if ($app['deposit_status'] === 'paid' && $app['stripe_pi'] && $app['stripe_pi'] !== $pi) alert_staff('A deposit was paid twice', 'Application #' . $app['id'] . ' paid a second deposit (' . $pi . '). Refund one of them in Stripe.');
                        else { update('applications', (int)$app['id'], ['deposit_status' => 'paid', 'deposit_due' => $amount, 'stripe_pi' => $pi]); ensure_deposit_payment(application((int)$app['id'])); }
                    }
                }
                break;

            case 'checkout.session.async_payment_failed':
                alert_staff('A bank payment failed', 'Stripe checkout ' . ($o['id'] ?? '') . ' for ' . ($o['customer_details']['email'] ?? 'someone') . ' failed. Nothing was recorded.');
                break;

            case 'invoice.paid': // every monthly gift, including the first
                $sub = $inv_sub($o);
                if (!$sub || ($o['amount_paid'] ?? 0) <= 0) break;
                $rec = one('SELECT * FROM recurring WHERE stripe_sub_id = ?', [(string)$sub]);
                $m = $inv_meta($o);
                if (($m['kind'] ?? 'gift') !== 'gift') break;
                if ($rec) $m += ['trip_id' => $rec['trip_id'], 'person_id' => $rec['person_id']];
                else {
                    $rid = insert('recurring', ['trip_id' => (int)($m['trip_id'] ?? 0) ?: null, 'person_id' => (int)($m['person_id'] ?? 0) ?: null, 'amount' => round($o['amount_paid'] / 100, 2),
                        'stripe_sub_id' => (string)$sub, 'status' => 'active', 'created_at' => now()]);
                    $rec = one('SELECT * FROM recurring WHERE id = ?', [$rid]);
                }
                $paid_at = (int)($o['status_transitions']['paid_at'] ?? $o['created'] ?? time());
                $gid = record_stripe_gift($m, round($o['amount_paid'] / 100, 2), (string)$o['id'], $inv_pi($o), ['email' => $o['customer_email'] ?? null, 'name' => $o['customer_name'] ?? null, 'address' => $o['customer_address'] ?? null], (int)$rec['id'], $paid_at, $o['customer'] ?? null);
                if (!$rec['donor_id'] && ($dg = one('SELECT donor_id FROM gifts WHERE id = ?', [$gid])) && $dg['donor_id']) update('recurring', (int)$rec['id'], ['donor_id' => (int)$dg['donor_id']]);
                break;

            case 'customer.subscription.deleted':
                q("UPDATE recurring SET status = 'canceled', canceled_at = ? WHERE stripe_sub_id = ?", [now(), (string)$o['id']]);
                break;

            case 'charge.refunded':
                // amount_refunded is the running total, so a second partial refund simply updates the number
                $pi = (string)($o['payment_intent'] ?? '');
                $inv = (string)($o['invoice'] ?? '');
                $refunded = round(($o['amount_refunded'] ?? 0) / 100, 2);
                $full = ($o['amount_refunded'] ?? 0) >= ($o['amount'] ?? 0);
                $g = ($pi ? one('SELECT * FROM gifts WHERE stripe_pi = ? OR stripe_id = ?', [$pi, $pi]) : null) ?? ($inv ? one('SELECT * FROM gifts WHERE stripe_id = ?', [$inv]) : null);
                if ($g) {
                    update('gifts', (int)$g['id'], ['refunded' => $refunded, 'status' => $full ? 'refunded' : 'cleared']);
                    sync_raised($g['trip_id'] ? (int)$g['trip_id'] : null, $g['person_id'] ? (int)$g['person_id'] : null);
                } elseif ($pi && ($pm = one("SELECT * FROM payments WHERE stripe_id = ? AND kind <> 'refund'", [$pi]))) {
                    $r = one("SELECT * FROM payments WHERE stripe_id = ?", ['re_' . $pi]);
                    if ($r) update('payments', (int)$r['id'], ['amount' => $refunded, 'paid_on' => date('Y-m-d')]);
                    else insert('payments', ['trip_id' => $pm['trip_id'], 'person_id' => $pm['person_id'], 'amount' => $refunded, 'method' => 'card', 'kind' => 'refund',
                        'paid_on' => date('Y-m-d'), 'note' => 'Refunded in Stripe', 'stripe_id' => 're_' . $pi, 'status' => 'ok', 'application_id' => $pm['application_id'], 'created_by' => 'Stripe', 'created_at' => now()]);
                    sync_raised((int)$pm['trip_id'], (int)$pm['person_id']);
                }
                break;

            case 'charge.dispute.created':
                $pi = (string)($o['payment_intent'] ?? '');
                if ($pi && ($g = one('SELECT * FROM gifts WHERE stripe_pi = ? OR stripe_id = ?', [$pi, $pi]))) update('gifts', (int)$g['id'], ['status' => 'disputed', 'note' => trim(($g['note'] ?? '') . ' Disputed in Stripe ' . date('M j, Y') . '.')]);
                alert_staff('A card payment was disputed', 'Stripe dispute ' . ($o['id'] ?? '') . ' for ' . money(($o['amount'] ?? 0) / 100, 2) . '. Respond in your Stripe dashboard.');
                break;
            case 'charge.dispute.closed':
                $pi = (string)($o['payment_intent'] ?? '');
                if ($pi && ($g = one('SELECT * FROM gifts WHERE stripe_pi = ? OR stripe_id = ?', [$pi, $pi]))) {
                    $lost = ($o['status'] ?? '') === 'lost';
                    update('gifts', (int)$g['id'], ['status' => $lost ? 'refunded' : 'cleared', 'refunded' => $lost ? $g['amount'] : $g['refunded']]);
                    sync_raised($g['trip_id'] ? (int)$g['trip_id'] : null, $g['person_id'] ? (int)$g['person_id'] : null);
                }
                break;
        }
        return true;
    });
    http_response_code(200); echo $done ? 'ok' : 'Already handled';
} catch (Throwable $e) {
    // Nothing was saved (the transaction rolled back), so Stripe will retry this event
    app_log('Stripe webhook ' . $ev['type'] . ' ' . $ev['id'] . ': ' . $e->getMessage(), 'errors');
    alert_staff('Stripe webhook failed', $ev['type'] . ' ' . $ev['id'] . ': ' . $e->getMessage());
    http_response_code(500); echo 'error';
}
