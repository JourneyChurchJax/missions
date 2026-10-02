<?php
// Money: gifts, donors, deposit batches, traveler payments, expenses.
// A traveler's "raised" (members.raised) = gifts credited to them + what they paid themselves. Team gifts count toward the trip only.
// Gifts and payments are never deleted: they're voided with a reason, so the books always add up.

const GIFT_METHODS = ['card' => 'Card', 'bank' => 'Bank', 'check' => 'Check', 'cash' => 'Cash', 'other' => 'Other'];
const PAYMENT_KINDS = ['payment' => 'Payment', 'deposit' => 'Deposit', 'refund' => 'Refund'];
const EXPENSE_TYPES = ['Airfare', 'Lodging', 'Food', 'Ground transportation', 'Taxes and visas', 'Insurance', 'Supplies', 'Ministry', 'Debrief and tourism', 'Other'];
// What counts toward a total: the gift minus refunds and minus a fee the donor chose to cover
const GIFT_CREDIT_SQL = "COALESCE(SUM(amount - COALESCE(refunded,0) - COALESCE(covered_fee,0)),0)";
const GIFT_COUNTS = "status IN ('cleared','refunded','disputed')";

function donor(int $id): ?array { return one('SELECT * FROM donors WHERE id = ?', [$id]); }
function donor_name(?array $d): string {
    if (!$d) return 'Anonymous';
    $n = trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
    return !empty($d['org']) ? ($n ? $d['org'] . ' (' . $n . ')' : $d['org']) : ($n ?: 'Unnamed donor');
}
// What a traveler sees: no name for anonymous gifts
function gift_from(array $g): string { return $g['anonymous'] ? 'Anonymous' : donor_name($g['donor_id'] ? donor((int)$g['donor_id']) : null); }
function gift_for(array $g): string {
    $t = !empty($g['trip_id']) ? trip((int)$g['trip_id']) : null;
    if (!$t) return 'General missions';
    if (empty($g['person_id'])) return $t['name'] . ' team';
    $p = person_basic((int)$g['person_id']);
    return ($p ? full_name($p) : 'Traveler') . ' · ' . $t['name'];
}
function gift_credit(array $g): float { return round((float)$g['amount'] - (float)($g['refunded'] ?? 0) - (float)($g['covered_fee'] ?? 0), 2); }

// Recount one traveler's total after any gift or payment change
function sync_raised(?int $trip_id, ?int $person_id): void {
    if (!$trip_id || !$person_id) return;
    q('UPDATE members SET raised = ? WHERE trip_id = ? AND person_id = ?', [round(member_gifts($trip_id, $person_id) + member_paid($trip_id, $person_id), 2), $trip_id, $person_id]);
}
function member_gifts(int $trip_id, int $person_id): float {
    return (float)val('SELECT ' . GIFT_CREDIT_SQL . ' FROM gifts WHERE trip_id = ? AND person_id = ? AND ' . GIFT_COUNTS, [$trip_id, $person_id]);
}
function member_paid(int $trip_id, int $person_id): float {
    return (float)val("SELECT COALESCE(SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END),0) FROM payments WHERE trip_id = ? AND person_id = ? AND COALESCE(status,'ok') <> 'void'", [$trip_id, $person_id]);
}
function team_gifts(int $trip_id): float {
    return (float)val('SELECT ' . GIFT_CREDIT_SQL . ' FROM gifts WHERE trip_id = ? AND person_id IS NULL AND ' . GIFT_COUNTS, [$trip_id]);
}
function trip_spent(int $trip_id): float { return (float)val("SELECT COALESCE(SUM(usd),0) FROM expenses WHERE trip_id = ? AND COALESCE(status,'ok') <> 'void'", [$trip_id]); }
// Raised across every upcoming trip (the same number on the home page and the Giving page)
function season_raised(): float { $n = 0; foreach (trips('upcoming') as $t) $n += trip_raised((int)$t['id']); return $n; }

function gifts_where(array $f): array {
    $w = [!empty($f['include_void']) ? '1=1' : "g.status <> 'void'"]; $p = [];
    if (!empty($f['trip'])) { $w[] = 'g.trip_id = ?'; $p[] = (int)$f['trip']; }
    if (!empty($f['person'])) { $w[] = 'g.person_id = ?'; $p[] = (int)$f['person']; }
    if (!empty($f['donor'])) { $w[] = 'g.donor_id = ?'; $p[] = (int)$f['donor']; }
    if (!empty($f['batch'])) { $w[] = 'g.batch_id = ?'; $p[] = (int)$f['batch']; }
    if (!empty($f['method'])) { $w[] = 'g.method = ?'; $p[] = $f['method']; }
    if (!empty($f['year'])) { $w[] = 'g.gift_date BETWEEN ? AND ?'; $p[] = $f['year'] . '-01-01'; $p[] = $f['year'] . '-12-31'; }
    return [implode(' AND ', $w), $p];
}
function gifts(array $f = [], int $limit = 500, int $offset = 0): array {
    [$w, $p] = gifts_where($f);
    return all("SELECT g.* FROM gifts g WHERE $w ORDER BY g.gift_date DESC, g.id DESC LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset), $p);
}
function gifts_count(array $f = []): int { [$w, $p] = gifts_where($f); return (int)val("SELECT COUNT(*) FROM gifts g WHERE $w", $p); }
function gifts_total(array $f = []): float { [$w, $p] = gifts_where($f); return (float)val("SELECT COALESCE(SUM(g.amount - COALESCE(g.refunded,0)),0) FROM gifts g WHERE $w", $p); }

function batch(int $id): ?array { return one('SELECT * FROM batches WHERE id = ?', [$id]); }
function open_batches(): array { return all("SELECT * FROM batches WHERE status = 'open' ORDER BY deposit_date DESC, id DESC"); }

// Years whose statements have gone out are locked, so totals on a donor's tax statement can't change afterward
function locked_years(): array { return array_map('intval', site_settings()['locked_years'] ?? []); }
// Why a gift can't be edited, or null if it can
function gift_locked(array $g): ?string {
    if ($g['status'] === 'void') return 'This gift was voided.';
    if ($g['batch_id'] && ($b = batch((int)$g['batch_id'])) && $b['status'] === 'closed') return 'This gift is in a closed deposit batch. Reopen the batch first.';
    if (in_array((int)substr((string)$g['gift_date'], 0, 4), locked_years(), true)) return 'Statements for ' . substr((string)$g['gift_date'], 0, 4) . ' have gone out, so this gift is locked.';
    if ($g['source'] === 'stripe') return 'Online gifts come from Stripe. Refund them in Stripe and the change shows up here.';
    return null;
}

// Find a donor, or make one. Matches by Stripe customer first, then by email only when the last name also matches,
// so someone giving with another person's email can't land on that person's tax statement.
function find_or_make_donor(string $first, string $last, string $email, string $org = '', ?string $stripe_customer = null): ?int {
    $email = strtolower(trim($email)); $first = trim($first); $last = trim($last); $org = trim($org);
    if ($stripe_customer && ($d = one('SELECT id FROM donors WHERE stripe_customer = ?', [$stripe_customer]))) return (int)$d['id'];
    if (!$first && !$last && !$org && !$email) return null;
    if ($email) foreach (all('SELECT * FROM donors WHERE LOWER(email) = ?', [$email]) as $d) {
        if (!$last || strcasecmp((string)$d['last_name'], $last) === 0 || ($org && strcasecmp((string)$d['org'], $org) === 0)) {
            if ($stripe_customer && !$d['stripe_customer']) update('donors', (int)$d['id'], ['stripe_customer' => $stripe_customer]);
            return (int)$d['id'];
        }
    }
    if (!$email && ($d = one("SELECT id FROM donors WHERE first_name = ? AND last_name = ? AND COALESCE(org, '') = ? AND (email IS NULL OR email = '')", [$first, $last, $org]))) return (int)$d['id'];
    $dupe = $email ? one('SELECT id FROM donors WHERE LOWER(email) = ?', [$email]) : null;
    return insert('donors', ['first_name' => nn($first), 'last_name' => nn($last), 'org' => nn($org), 'email' => nn($email), 'stripe_customer' => $stripe_customer,
        'notes' => $dupe ? 'Same email as donor #' . $dupe['id'] . ' but a different name. Check if they are the same person.' : null, 'created_at' => now()]);
}

// Exactly one deposit payment per application, on the trip they were approved for
function ensure_deposit_payment(array $app): void {
    $existing = one("SELECT * FROM payments WHERE application_id = ? AND kind = 'deposit' AND COALESCE(status,'ok') <> 'void'", [$app['id']]);
    $should = $app['status'] === 'approved' && $app['assigned_trip_id'] && $app['deposit_status'] === 'paid' && (float)$app['deposit_due'] > 0;
    if (!$should) {
        if ($existing) { update('payments', (int)$existing['id'], ['status' => 'void', 'void_reason' => 'Deposit no longer marked paid', 'voided_at' => now(), 'voided_by' => current_actor_name()]); sync_raised((int)$existing['trip_id'], (int)$existing['person_id']); }
        return;
    }
    $trip = (int)$app['assigned_trip_id'];
    if ($existing && (int)$existing['trip_id'] === $trip) return;
    if ($existing) { update('payments', (int)$existing['id'], ['trip_id' => $trip]); sync_raised((int)$existing['trip_id'], (int)$app['person_id']); }
    else insert('payments', ['trip_id' => $trip, 'person_id' => (int)$app['person_id'], 'amount' => (float)$app['deposit_due'], 'method' => $app['stripe_pi'] ? 'card' : 'other',
        'kind' => 'deposit', 'paid_on' => date('Y-m-d'), 'note' => 'Application deposit', 'stripe_id' => $app['stripe_pi'] ?: null, 'status' => 'ok',
        'application_id' => (int)$app['id'], 'created_by' => current_actor_name(), 'created_at' => now()]);
    sync_raised($trip, (int)$app['person_id']);
}

// When a trip is cancelled, stop monthly gifts to it
function cancel_trip_subscriptions(int $trip_id): int {
    $n = 0;
    foreach (all("SELECT * FROM recurring WHERE trip_id = ? AND status = 'active'", [$trip_id]) as $r) {
        if (stripe_ready() && $r['stripe_sub_id'] && str_starts_with((string)$r['stripe_sub_id'], 'sub_') && $r['stripe_sub_id'] !== 'sub_sample') {
            try { stripe_api('DELETE', '/v1/subscriptions/' . rawurlencode((string)$r['stripe_sub_id'])); } catch (Throwable $e) { app_log('Cancel subscription ' . $r['stripe_sub_id'] . ': ' . $e->getMessage(), 'errors'); continue; }
        }
        update('recurring', (int)$r['id'], ['status' => 'canceled', 'canceled_at' => now()]); $n++;
    }
    return $n;
}

// Spreadsheet-safe cell: a value starting with = + - @ would run as a formula in Excel
// A cell that starts like a formula gets a ' in front so a spreadsheet won't run it. Plain numbers (like -25.00 for a refund) stay numbers.
function csv_cell($v): string { $v = (string)$v; return $v !== '' && !is_numeric($v) && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v; }
function csv_out($out, array $row): void { fputcsv($out, array_map('csv_cell', $row), ',', '"', ''); }
function csv_start(string $filename): mixed {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) . '"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF"); return $out;
}
