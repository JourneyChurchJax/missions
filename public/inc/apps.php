<?php
// Applications: forms, questions, applicants, references, deposits.

const Q_KINDS = ['short' => 'Short answer', 'long' => 'Paragraph', 'choice' => 'Pick one', 'checkboxes' => 'Pick any', 'yesno' => 'Yes or no', 'date' => 'Date'];
const APP_STATUS = ['draft' => 'Started, not sent', 'submitted' => 'To review', 'waitlist' => 'Waitlist', 'approved' => 'Approved', 'declined' => 'Declined'];
// What applicants see
const APP_STATUS_PUBLIC = ['submitted' => 'Sent', 'waitlist' => 'On the waitlist', 'approved' => 'Approved', 'declined' => 'Not this time'];
const DEPOSIT_STATUS = ['none' => 'No deposit', 'due' => 'Deposit due', 'paid' => 'Deposit paid', 'waived' => 'Deposit waived'];
const APP_STEPS = ['you' => 'About you', 'travel' => 'Travel info', 'trip' => 'Trip', 'questions' => 'Questions', 'refs' => 'References', 'review' => 'Review'];

function app_form(int $id): ?array { return one('SELECT * FROM app_forms WHERE id = ?', [$id]); }
function app_form_by_slug(string $slug): ?array { return one('SELECT * FROM app_forms WHERE slug = ?', [$slug]); }
function app_questions(int $form_id): array { return all('SELECT * FROM app_questions WHERE form_id = ? ORDER BY sort, id', [$form_id]); }
function app_discounts(int $form_id): array { return all('SELECT * FROM app_discounts WHERE form_id = ? ORDER BY id', [$form_id]); }
function application(int $id): ?array { return one('SELECT * FROM applications WHERE id = ?', [$id]); }
function app_refs(int $app_id): array { return all('SELECT * FROM app_refs WHERE application_id = ? ORDER BY id', [$app_id]); }
function app_answers(array $a): array { return json_decode((string)$a['answers'], true) ?: []; }
function new_token(): string { return bin2hex(random_bytes(16)); }
function form_is_open(array $f): bool { return $f['published'] && (!$f['closes_on'] || $f['closes_on'] >= date('Y-m-d')); }
function form_has_responses(int $form_id): bool { return (bool)val("SELECT COUNT(*) FROM applications WHERE form_id = ? AND status <> 'draft'", [$form_id]); }
function ref_types(array $f): array { $t = lines($f['ref_types']); while (count($t) < (int)$f['refs_required']) $t[] = 'Reference'; return array_slice($t, 0, (int)$f['refs_required']); }

// Trips an applicant can pick on this form
function form_trips(array $f): array {
    if ($f['trip_mode'] === 'none') return [];
    $all = all("SELECT * FROM trips WHERE status = 'active' AND start_date >= ? ORDER BY start_date", [date('Y-m-d')]);
    if ($f['trip_mode'] === 'specific') {
        $ids = array_map('intval', array_filter(explode(',', (string)$f['trip_ids'])));
        $all = array_values(array_filter($all, fn($t) => in_array((int)$t['id'], $ids, true)));
    }
    return $all;
}

// Deposit after any discount (early-bird discounts apply on their own; codes need to be typed)
function deposit_for(array $f, ?string $code, ?string $on = null): array {
    $base = (float)$f['deposit']; $on = $on ?: date('Y-m-d');
    if ($base <= 0) return [0.0, null];
    $best = 0.0; $label = null;
    foreach (app_discounts((int)$f['id']) as $d) {
        if ($d['expires_on'] && $d['expires_on'] < $on) continue;
        if (!$d['early_bird'] && ((string)$d['code'] === '' || strcasecmp(trim((string)$code), (string)$d['code']) !== 0)) continue;
        $off = $d['kind'] === 'percent' ? $base * (float)$d['amount'] / 100 : (float)$d['amount'];
        if ($off > $best) { $best = $off; $label = $d['early_bird'] ? 'Early bird' : strtoupper((string)$d['code']); }
    }
    return [max(0, round($base - $best, 2)), $label];
}

function app_ref_summary(array $a, array $f): string {
    $need = (int)$f['refs_required']; if (!$need) return '';
    $got = (int)val("SELECT COUNT(*) FROM app_refs WHERE application_id = ? AND status = 'received'", [$a['id']]);
    return $got >= $need ? "$need of $need references in" : "$got of $need references in";
}
function app_url(array $f): string { return site_url('/apply/?f=' . $f['slug']); }
function ref_url(array $r): string { return site_url('/reference/?t=' . $r['token']); }

// Standard reference questions every reference answers
const REF_QUESTIONS = [
    'known' => ['How do you know the applicant, and for how long?', 'long'],
    'faith' => ['How would you describe their walk with Jesus?', 'long'],
    'team' => ['How do they work with others, especially under stress?', 'long'],
    'concerns' => ['Is there anything that would keep them from serving well on this trip?', 'long'],
    'recommend' => ['Would you recommend them for this trip?', 'choice:Yes, without reservation|Yes, with some reservations|No'],
];
