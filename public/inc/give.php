<?php
// Online giving through Stripe, and public fundraising pages.
// Stripe turns on when config.php has 'stripe' => ['secret' => 'sk_...', 'webhook_secret' => 'whsec_...'].
// No Stripe library: the few calls we need go straight to the Stripe API.

function stripe_ready(): bool { global $config; return !empty($config['stripe']['secret']); }
function stripe_test_mode(): bool { global $config; return str_starts_with((string)($config['stripe']['secret'] ?? ''), 'sk_test_'); }

// Stripe's form encoding: nested arrays become key[sub][0]=value
function stripe_encode(array $params, string $prefix = ''): array {
    $out = [];
    foreach ($params as $k => $v) {
        if ($v === null) continue;
        $key = $prefix === '' ? (string)$k : $prefix . '[' . $k . ']';
        if (is_array($v)) $out += stripe_encode($v, $key);
        else $out[$key] = is_bool($v) ? ($v ? 'true' : 'false') : (string)$v;
    }
    return $out;
}
function stripe_api(string $method, string $path, array $params = []): array {
    global $config;
    $base = rtrim($config['stripe']['base'] ?? 'https://api.stripe.com', '/');
    $url = $base . $path . ($method === 'GET' && $params ? '?' . http_build_query(stripe_encode($params)) : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_USERPWD => $config['stripe']['secret'] . ':',
        CURLOPT_HTTPHEADER => ['Stripe-Version: 2024-06-20']]);
    if ($method !== 'GET') { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(stripe_encode($params))); }
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $data = json_decode((string)$res, true) ?: [];
    if ($code < 200 || $code >= 300) throw new RuntimeException($data['error']['message'] ?? "Stripe said no ($code)");
    return $data;
}

// Card fee estimate (2.9% + 30¢) and what to charge so the church still gets $amount when a donor covers it
function stripe_fee(float $amount): float { return round($amount * 0.029 + 0.30, 2); }
function with_fee_covered(float $amount): float { return round(($amount + 0.30) / (1 - 0.029), 2); }

// Start a Stripe Checkout page. $kind: gift | payment | app_deposit. Returns the URL to send the person to.
function stripe_checkout(string $kind, float $amount, string $label, array $meta, string $success, string $cancel, ?string $email = null, bool $monthly = false): string {
    $cents = (int)round($amount * 100);
    $meta = array_map(fn($v) => mb_substr((string)$v, 0, 480), $meta + ['kind' => $kind]);
    $price = ['currency' => 'usd', 'unit_amount' => $cents, 'product_data' => ['name' => mb_substr($label, 0, 120)]];
    if ($monthly) $price['recurring'] = ['interval' => 'month'];
    $params = ['mode' => $monthly ? 'subscription' : 'payment', 'line_items' => [['quantity' => 1, 'price_data' => $price]],
        'success_url' => $success, 'cancel_url' => $cancel, 'customer_email' => $email ?: null, 'metadata' => $meta,
        'billing_address_collection' => $kind === 'gift' ? 'required' : 'auto'];
    if ($monthly) $params['subscription_data'] = ['metadata' => $meta];
    else { $params['payment_intent_data'] = ['metadata' => $meta, 'description' => mb_substr($label, 0, 200)]; $params['submit_type'] = $kind === 'gift' ? 'donate' : 'pay'; }
    $s = stripe_api('POST', '/v1/checkout/sessions', $params);
    return $s['url'];
}

// Webhook signature check (Stripe-Signature: t=...,v1=...)
function stripe_verify(string $payload, string $header, string $secret, int $tolerance = 300): bool {
    $t = null; $sigs = [];
    foreach (explode(',', $header) as $part) { [$k, $v] = array_pad(explode('=', trim($part), 2), 2, ''); if ($k === 't') $t = (int)$v; if ($k === 'v1') $sigs[] = $v; }
    if (!$t || !$sigs || abs(time() - $t) > $tolerance) return false;
    $want = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($sigs as $s) if (hash_equals($want, $s)) return true;
    return false;
}

// Real fee from Stripe when we can get it; otherwise the estimate
function stripe_actual_fee(?string $payment_intent, float $amount): float {
    if (!$payment_intent) return stripe_fee($amount);
    try {
        $pi = stripe_api('GET', '/v1/payment_intents/' . rawurlencode($payment_intent), ['expand' => ['latest_charge.balance_transaction']]);
        $bt = $pi['latest_charge']['balance_transaction'] ?? null;
        if (is_array($bt) && isset($bt['fee'])) return round($bt['fee'] / 100, 2);
    } catch (Throwable $e) {}
    return stripe_fee($amount);
}

// Save one online gift (from checkout or a monthly renewal). Safe to call twice for the same payment.
function record_stripe_gift(array $meta, float $amount, string $stripe_id, ?string $payment_intent, array $customer, ?int $recurring_id = null): int {
    if ($g = one('SELECT id FROM gifts WHERE stripe_id = ?', [$stripe_id])) return (int)$g['id'];
    $name = trim((string)($customer['name'] ?? ''));
    $parts = preg_split('/\s+/', $name, 2);
    $donor = find_or_make_donor($meta['first'] ?? ($parts[0] ?? ''), $meta['last'] ?? ($parts[1] ?? ''), (string)($customer['email'] ?? ''));
    if ($donor && !empty($customer['address']['line1'])) {
        $d = donor($donor);
        if (!$d['address']) update('donors', $donor, ['address' => $customer['address']['line1'], 'city' => $customer['address']['city'] ?? null, 'state' => $customer['address']['state'] ?? null, 'zip' => $customer['address']['postal_code'] ?? null]);
    }
    $trip = (int)($meta['trip_id'] ?? 0) ?: null; $person = (int)($meta['person_id'] ?? 0) ?: null;
    if ($trip && $person && !member_of($trip, $person)) $person = null;
    $id = insert('gifts', ['donor_id' => $donor, 'trip_id' => $trip, 'person_id' => $person, 'amount' => $amount, 'fee' => stripe_actual_fee($payment_intent, $amount),
        'method' => 'card', 'gift_date' => date('Y-m-d'), 'anonymous' => !empty($meta['anonymous']) ? 1 : 0, 'message' => nn($meta['message'] ?? null),
        'source' => 'stripe', 'status' => 'cleared', 'stripe_id' => $stripe_id, 'recurring_id' => $recurring_id, 'created_by' => 'Stripe', 'created_at' => now()]);
    sync_raised($trip, $person);
    if ($trip) insert('activity', ['trip_id' => $trip, 'who' => 'Stripe', 'what' => 'Online gift of ' . money($amount, 2) . ' for ' . gift_for(['trip_id' => $trip, 'person_id' => $person]), 'created_at' => now()]);
    // Let the traveler know right away
    if ($person && ($p = person($person)) && $p['email']) send_email($p['email'], 'You got a gift!', "Someone just gave " . money($amount, 2) . " toward your " . (trip($trip)['name'] ?? '') . " trip" . (!empty($meta['anonymous']) ? '' : ' from ' . donor_name($donor ? donor($donor) : null)) . ".\n\nSee it and say thanks: " . site_url('/trip/fundraising.php'), $trip, $person);
    if (!empty($customer['email'])) send_email((string)$customer['email'], 'Thank you for your gift', "Thank you for giving " . money($amount, 2) . " to Journey Church missions" . ($person ? ' for ' . full_name(person($person)) : '') . ".\n\nYou'll get a year-end statement for your taxes. No goods or services were provided in exchange for this gift.", $trip, null);
    return $id;
}

// ---------- Fundraising pages ----------
const PAGE_STATUS = ['draft' => 'Not shared yet', 'pending' => 'Waiting for approval', 'live' => 'Live', 'hidden' => 'Hidden by staff'];
function unique_page_slug(array $p): string {
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)($p['preferred_name'] ?: $p['first_name']))) ?: 'traveler';
    $reserved = ['admin', 'trip', 'apply', 'reference', 'parent', 'give', 'assets', 'inc', 'auth', 'signin', 'signout', 'index', 'calendar', 'chat', 'packet', 'sign', 'file', 'action', 'stripe'];
    $slug = $base; $i = 1;
    $lastInitial = strtolower(substr((string)$p['last_name'], 0, 1));
    while (in_array($slug, $reserved, true) || val('SELECT COUNT(*) FROM members WHERE page_slug = ?', [$slug])) {
        $slug = $i === 1 && $lastInitial ? $base . $lastInitial : $base . ($lastInitial ?: '') . $i;
        $i++;
    }
    return $slug;
}
function page_url(array $m): string { return site_url('/' . $m['page_slug']); }
function approve_pages(): bool { return site_settings()['approve_pages'] ?? true; }
// A traveler's page row with person and trip, by slug
function page_by_slug(string $slug): ?array {
    return one("SELECT m.*, p.first_name, p.preferred_name, p.last_name, t.name AS trip_name, t.public_name, t.start_date, t.end_date, t.city, t.country, t.cost_per_person, t.status AS trip_status
                FROM members m JOIN people p ON p.id = m.person_id JOIN trips t ON t.id = m.trip_id WHERE m.page_slug = ?", [strtolower($slug)]);
}
