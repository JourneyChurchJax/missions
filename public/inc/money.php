<?php
// Money: gifts, donors, deposit batches, traveler payments, expenses.
// A traveler's "raised" (members.raised) = gifts credited to them + what they paid themselves. Team gifts count toward the trip only.

const GIFT_METHODS = ['card' => 'Card', 'bank' => 'Bank', 'check' => 'Check', 'cash' => 'Cash', 'other' => 'Other'];
const PAYMENT_KINDS = ['payment' => 'Payment', 'deposit' => 'Deposit', 'refund' => 'Refund'];
const EXPENSE_TYPES = ['Airfare', 'Lodging', 'Meals/Food', 'Transportation-Other', 'Taxes/Visas', 'Insurance', 'Supplies', 'Ministry', 'Debrief/Tourism', 'MISC'];

function donor(int $id): ?array { return one('SELECT * FROM donors WHERE id = ?', [$id]); }
function donor_name(?array $d): string {
    if (!$d) return 'Anonymous';
    $n = trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
    return $d['org'] ? ($n ? $d['org'] . ' (' . $n . ')' : $d['org']) : ($n ?: 'Unnamed donor');
}
// What a traveler sees: no name for anonymous gifts
function gift_from(array $g): string { return $g['anonymous'] ? 'Anonymous' : donor_name($g['donor_id'] ? donor((int)$g['donor_id']) : null); }
function gift_for(array $g): string {
    $t = $g['trip_id'] ? trip((int)$g['trip_id']) : null;
    if (!$t) return 'General missions';
    if (!$g['person_id']) return $t['name'] . ' team';
    $p = person((int)$g['person_id']);
    return ($p ? full_name($p) : 'Traveler') . ' · ' . $t['name'];
}

// Recount one traveler's total after any gift or payment change
function sync_raised(?int $trip_id, ?int $person_id): void {
    if (!$trip_id || !$person_id) return;
    $gifts = (float)val("SELECT COALESCE(SUM(amount),0) FROM gifts WHERE trip_id = ? AND person_id = ? AND status = 'cleared'", [$trip_id, $person_id]);
    $paid = (float)val("SELECT COALESCE(SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END),0) FROM payments WHERE trip_id = ? AND person_id = ?", [$trip_id, $person_id]);
    q('UPDATE members SET raised = ? WHERE trip_id = ? AND person_id = ?', [round($gifts + $paid, 2), $trip_id, $person_id]);
}
function member_gifts(int $trip_id, int $person_id): float {
    return (float)val("SELECT COALESCE(SUM(amount),0) FROM gifts WHERE trip_id = ? AND person_id = ? AND status = 'cleared'", [$trip_id, $person_id]);
}
function member_paid(int $trip_id, int $person_id): float {
    return (float)val("SELECT COALESCE(SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END),0) FROM payments WHERE trip_id = ? AND person_id = ?", [$trip_id, $person_id]);
}
function team_gifts(int $trip_id): float {
    return (float)val("SELECT COALESCE(SUM(amount),0) FROM gifts WHERE trip_id = ? AND person_id IS NULL AND status = 'cleared'", [$trip_id]);
}
function trip_spent(int $trip_id): float { return (float)val('SELECT COALESCE(SUM(usd),0) FROM expenses WHERE trip_id = ?', [$trip_id]); }

function gifts_where(array $f): array {
    $w = ["g.status = 'cleared'"]; $p = [];
    if (!empty($f['trip'])) { $w[] = 'g.trip_id = ?'; $p[] = (int)$f['trip']; }
    if (!empty($f['person'])) { $w[] = 'g.person_id = ?'; $p[] = (int)$f['person']; }
    if (!empty($f['donor'])) { $w[] = 'g.donor_id = ?'; $p[] = (int)$f['donor']; }
    if (!empty($f['batch'])) { $w[] = 'g.batch_id = ?'; $p[] = (int)$f['batch']; }
    if (!empty($f['method'])) { $w[] = 'g.method = ?'; $p[] = $f['method']; }
    if (!empty($f['year'])) { $w[] = 'g.gift_date BETWEEN ? AND ?'; $p[] = $f['year'] . '-01-01'; $p[] = $f['year'] . '-12-31'; }
    return [implode(' AND ', $w), $p];
}
function gifts(array $f = [], int $limit = 500): array {
    [$w, $p] = gifts_where($f);
    return all("SELECT g.* FROM gifts g WHERE $w ORDER BY g.gift_date DESC, g.id DESC LIMIT $limit", $p);
}
function gifts_total(array $f = []): float { [$w, $p] = gifts_where($f); return (float)val("SELECT COALESCE(SUM(g.amount),0) FROM gifts g WHERE $w", $p); }

function batch(int $id): ?array { return one('SELECT * FROM batches WHERE id = ?', [$id]); }
function open_batches(): array { return all("SELECT * FROM batches WHERE status = 'open' ORDER BY deposit_date DESC, id DESC"); }

// Find a donor by email, or make one. Returns null when no name or email was given (an anonymous cash gift, say).
function find_or_make_donor(string $first, string $last, string $email, string $org = ''): ?int {
    $email = strtolower(trim($email));
    if ($email && ($d = one('SELECT id FROM donors WHERE LOWER(email) = ?', [$email]))) return (int)$d['id'];
    if (!$first && !$last && !$org && !$email) return null;
    if (!$email && ($d = one('SELECT id FROM donors WHERE first_name = ? AND last_name = ? AND COALESCE(org, \'\') = ?', [$first, $last, $org]))) return (int)$d['id'];
    return insert('donors', ['first_name' => nn($first), 'last_name' => nn($last), 'org' => nn($org), 'email' => nn($email), 'created_at' => now()]);
}
