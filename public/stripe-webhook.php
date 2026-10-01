<?php
// Stripe calls this after payments. Point a Stripe webhook at https://missions.journeychurch.org/stripe-webhook.php
// with these events: checkout.session.completed, invoice.paid, customer.subscription.deleted, charge.refunded
require __DIR__ . '/inc/bootstrap.php';
global $config;

$payload = (string)file_get_contents('php://input');
$secret = (string)($config['stripe']['webhook_secret'] ?? '');
if ($secret === '' || !stripe_verify($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $secret)) { http_response_code(400); exit('Bad signature'); }
$ev = json_decode($payload, true);
if (!is_array($ev) || empty($ev['id'])) { http_response_code(400); exit('Bad payload'); }
if (val('SELECT COUNT(*) FROM stripe_events WHERE id = ?', [$ev['id']])) { http_response_code(200); exit('Already handled'); }

$o = $ev['data']['object'] ?? [];
$meta = $o['metadata'] ?? [];
try {
    switch ($ev['type']) {
        case 'checkout.session.completed':
            if (($o['payment_status'] ?? '') === 'unpaid') break; // bank payments that haven't cleared yet arrive as a later event
            $amount = round(($o['amount_total'] ?? 0) / 100, 2);
            $cust = $o['customer_details'] ?? [];
            $kind = $meta['kind'] ?? '';
            if ($kind === 'gift' && ($o['mode'] ?? '') === 'subscription') {
                $parts = preg_split('/\s+/', trim((string)($cust['name'] ?? '')), 2);
                $donor = find_or_make_donor($meta['first'] ?? ($parts[0] ?? ''), $meta['last'] ?? ($parts[1] ?? ''), (string)($cust['email'] ?? ''));
                if (!one('SELECT id FROM recurring WHERE stripe_sub_id = ?', [(string)$o['subscription']]))
                    insert('recurring', ['donor_id' => $donor, 'trip_id' => (int)($meta['trip_id'] ?? 0) ?: null, 'person_id' => (int)($meta['person_id'] ?? 0) ?: null,
                        'amount' => $amount, 'stripe_sub_id' => (string)$o['subscription'], 'status' => 'active', 'created_at' => now()]);
            } elseif ($kind === 'gift') {
                record_stripe_gift($meta, $amount, (string)($o['payment_intent'] ?? $o['id']), $o['payment_intent'] ?? null, $cust);
            } elseif ($kind === 'payment') {
                $trip = (int)($meta['trip_id'] ?? 0); $person = (int)($meta['person_id'] ?? 0);
                $sid = (string)($o['payment_intent'] ?? $o['id']);
                if ($trip && $person && member_of($trip, $person) && !one('SELECT id FROM payments WHERE stripe_id = ?', [$sid])) {
                    insert('payments', ['trip_id' => $trip, 'person_id' => $person, 'amount' => $amount, 'method' => 'card', 'kind' => 'payment', 'paid_on' => date('Y-m-d'),
                        'note' => 'Paid online', 'stripe_id' => $sid, 'created_by' => 'Stripe', 'created_at' => now()]);
                    sync_raised($trip, $person);
                    insert('activity', ['trip_id' => $trip, 'who' => 'Stripe', 'what' => full_name(person($person)) . ' paid ' . money($amount, 2) . ' online', 'created_at' => now()]);
                }
            } elseif ($kind === 'app_deposit') {
                $app = !empty($meta['app']) ? one('SELECT * FROM applications WHERE token = ?', [(string)$meta['app']]) : null;
                if ($app) {
                    update('applications', (int)$app['id'], ['deposit_status' => 'paid', 'deposit_due' => $amount]);
                    if ($app['status'] === 'approved' && $app['assigned_trip_id'] && !val("SELECT COUNT(*) FROM payments WHERE trip_id = ? AND person_id = ? AND kind = 'deposit'", [$app['assigned_trip_id'], $app['person_id']])) {
                        insert('payments', ['trip_id' => $app['assigned_trip_id'], 'person_id' => $app['person_id'], 'amount' => $amount, 'method' => 'card', 'kind' => 'deposit', 'paid_on' => date('Y-m-d'),
                            'note' => 'Application deposit, paid online', 'stripe_id' => (string)($o['payment_intent'] ?? $o['id']), 'created_by' => 'Stripe', 'created_at' => now()]);
                        sync_raised((int)$app['assigned_trip_id'], (int)$app['person_id']);
                    }
                }
            }
            break;

        case 'invoice.paid': // every monthly gift, including the first
            if (empty($o['subscription']) || ($o['amount_paid'] ?? 0) <= 0) break;
            $rec = one('SELECT * FROM recurring WHERE stripe_sub_id = ?', [(string)$o['subscription']]);
            $m = $o['subscription_details']['metadata'] ?? ($o['lines']['data'][0]['metadata'] ?? []);
            if ($rec) $m += ['trip_id' => $rec['trip_id'], 'person_id' => $rec['person_id']];
            if (($m['kind'] ?? 'gift') !== 'gift') break;
            if (!$rec) { // the invoice can arrive before checkout finishes
                $rid = insert('recurring', ['trip_id' => (int)($m['trip_id'] ?? 0) ?: null, 'person_id' => (int)($m['person_id'] ?? 0) ?: null, 'amount' => round($o['amount_paid'] / 100, 2),
                    'stripe_sub_id' => (string)$o['subscription'], 'status' => 'active', 'created_at' => now()]);
                $rec = one('SELECT * FROM recurring WHERE id = ?', [$rid]);
            }
            $gid = record_stripe_gift($m, round($o['amount_paid'] / 100, 2), (string)$o['id'], $o['payment_intent'] ?? null,
                ['email' => $o['customer_email'] ?? null, 'name' => $o['customer_name'] ?? null, 'address' => $o['customer_address'] ?? null], (int)$rec['id']);
            if (!$rec['donor_id'] && ($dg = one('SELECT donor_id FROM gifts WHERE id = ?', [$gid])) && $dg['donor_id']) update('recurring', (int)$rec['id'], ['donor_id' => (int)$dg['donor_id']]);
            break;

        case 'customer.subscription.deleted':
            q("UPDATE recurring SET status = 'canceled', canceled_at = ? WHERE stripe_sub_id = ?", [now(), (string)$o['id']]);
            break;

        case 'charge.refunded':
            $pi = (string)($o['payment_intent'] ?? '');
            $full = ($o['amount_refunded'] ?? 0) >= ($o['amount'] ?? 0);
            if ($pi && ($g = one('SELECT * FROM gifts WHERE stripe_id = ?', [$pi]))) {
                if ($full) update('gifts', (int)$g['id'], ['status' => 'refunded']);
                else update('gifts', (int)$g['id'], ['amount' => round(((int)$o['amount'] - (int)$o['amount_refunded']) / 100, 2)]);
                sync_raised($g['trip_id'] ? (int)$g['trip_id'] : null, $g['person_id'] ? (int)$g['person_id'] : null);
            } elseif ($pi && ($pm = one("SELECT * FROM payments WHERE stripe_id = ? AND kind <> 'refund'", [$pi])) && !one("SELECT id FROM payments WHERE stripe_id = ? AND kind = 'refund'", ['re_' . $pi])) {
                insert('payments', ['trip_id' => $pm['trip_id'], 'person_id' => $pm['person_id'], 'amount' => round(($o['amount_refunded'] ?? 0) / 100, 2), 'method' => 'card', 'kind' => 'refund',
                    'paid_on' => date('Y-m-d'), 'note' => 'Refunded in Stripe', 'stripe_id' => 're_' . $pi, 'created_by' => 'Stripe', 'created_at' => now()]);
                sync_raised((int)$pm['trip_id'], (int)$pm['person_id']);
            }
            break;
    }
    insert('stripe_events', ['id' => $ev['id'], 'type' => (string)$ev['type'], 'received_at' => now()]);
    http_response_code(200); echo 'ok';
} catch (Throwable $e) {
    error_log('Stripe webhook: ' . $e->getMessage());
    http_response_code(500); echo 'error';
}
