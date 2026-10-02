<?php
// Public trip application. Anyone with the link can apply. ?f=slug starts one, ?t=token resumes it (the token is the private link).
define('REAL_DB', true);
define('ACTOR', 'Applicant');
require dirname(__DIR__) . '/inc/bootstrap.php';

$staff_preview = is_staff_session();
$app = g('t') !== '' ? one('SELECT * FROM applications WHERE token = ?', [g('t')]) : null;
$f = $app ? app_form((int)$app['form_id']) : (g('f') !== '' ? app_form_by_slug(g('f')) : null);
$p = $app ? person((int)$app['person_id']) : null;

// Steps this form uses
$steps = APP_STEPS;
if ($f && $f['trip_mode'] === 'none') unset($steps['trip']);
if ($f && !app_questions((int)$f['id'])) unset($steps['questions']);
if ($f && !(int)$f['refs_required']) unset($steps['refs']);
$keys = array_keys($steps);
$step = $app ? (isset($steps[g('s')]) ? g('s') : ($app['step'] ?: 'you')) : 'you';
if (!isset($steps[$step])) $step = 'you';
$next_of = fn($s) => $keys[array_search($s, $keys) + 1] ?? 'review';
$prev_of = fn($s) => $keys[array_search($s, $keys) - 1] ?? null;

function go(array $app, string $s): never { header('Location: /apply/?t=' . $app['token'] . '&s=' . $s); exit; }
// Names can't carry links (keeps spammers from using the form to send email)
function clean_name(string $s): string { return trim(preg_replace('#(https?://|www\.|<|>)#i', '', mb_substr($s, 0, 80))); }
$open = $f && (form_is_open($f) || $staff_preview);

$you_fields = ['first_name', 'preferred_name', 'last_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'city', 'state', 'zip', 'shirt'];
$travel_fields = ['passport_name', 'passport_number', 'passport_country', 'passport_issued', 'passport_expires',
    'ec1_name', 'ec1_rel', 'ec1_phone', 'ec1_email', 'allergies', 'meds', 'diet', 'health', 'other'];
$err = '';

// The first trip they picked decides whether they're under 18 on the trip (then a parent must agree)
function app_trip_start(array $app, array $f): ?string {
    if ($app['choice1'] && ($t = trip((int)$app['choice1']))) return $t['start_date'];
    $ts = form_trips($f); return $ts[0]['start_date'] ?? null;
}
// Check every required answer; returns the first problem or ''
function app_missing(array $app, array $f, array $p): string {
    foreach (['first_name' => 'your first name', 'last_name' => 'your last name', 'email' => 'your email', 'phone' => 'your phone', 'birth_date' => 'your birth date'] as $k => $l)
        if (empty($p[$k])) return "Add $l on the About you step.";
    if (empty($p['ec1_name']) || empty($p['ec1_phone'])) return 'Add an emergency contact on the Travel info step.';
    if ($f['trip_mode'] !== 'none' && !$app['choice1']) return 'Pick a trip on the Trip step.';
    $ans = app_answers($app);
    foreach (app_questions((int)$f['id']) as $q) {
        $a = $ans[$q['id']] ?? '';
        if ($q['required'] && (is_array($a) ? !array_filter($a) : $a === '')) return 'Answer "' . $q['label'] . '" on the Questions step.';
    }
    $refs = json_decode((string)($ans['_refs'] ?? '[]'), true) ?: [];
    foreach (ref_types($f) as $i => $type) if (empty($refs[$i]['name']) || empty($refs[$i]['email'])) return "Add your $type reference on the References step.";
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $f) {
    check_csrf();
    $act = (string)post('do');
    if ($act === 'pay_deposit' && $app && $app['deposit_status'] === 'due' && stripe_ready()) {
        try { header('Location: ' . stripe_checkout('app_deposit', (float)$app['deposit_due'], 'Application deposit · ' . $f['name'], ['app' => $app['token']], site_url('/apply/?t=' . $app['token'] . '&paid=1'), site_url('/apply/?t=' . $app['token']), $p['email'] ?: null)); exit; }
        catch (Throwable $e) { app_log('Deposit checkout: ' . $e->getMessage(), 'errors'); $err = 'Could not start the payment. Try again in a minute.'; }
    }
    elseif (!$open) { $err = 'This application is closed.'; }
    elseif ($act === 'start') {
        if (ps('website')) { header('Location: /apply/?f=' . urlencode($f['slug'])); exit; } // bots fill the hidden field
        $first = clean_name(ps('first_name')); $last = clean_name(ps('last_name')); $email = strtolower(ps('email', 200));
        if (!$first || !$last || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Add your name and a working email.';
        elseif (rate_limited(client_key() . ':apply', 5, 3600) || rate_limited('apply:' . $email, 3, 86400)) $err = 'Too many applications started from here today. If you need help, contact the missions team.';
        elseif (!turnstile_ok()) $err = 'Please complete the check that you\'re a person.';
        else {
            $tok = new_token();
            tx(function () use ($first, $last, $email, $f, $tok) {
                $pid = insert('people', ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => nn(ps('phone', 40)), 'created_at' => now()]);
                insert('applications', ['form_id' => (int)$f['id'], 'person_id' => $pid, 'status' => 'draft', 'token' => $tok, 'step' => 'you',
                    'answers' => '{}', 'deposit_status' => 'none', 'deposit_due' => 0, 'created_at' => now(), 'updated_at' => now()]);
            });
            header("Location: /apply/?t=$tok&s=you&new=1"); exit;
        }
    }
    elseif ($app && $app['status'] === 'draft') {
        $save = function (array $fields) use ($p) {
            $row = [];
            foreach ($fields as $k) { $v = nn(ps($k, 2000)); if ($k === 'passport_number' && $v !== null && str_contains($v, '•')) continue; $row[$k] = in_array($k, ['first_name', 'last_name', 'preferred_name'], true) && $v ? clean_name($v) : $v; }
            update('people', (int)$p['id'], $row);
        };
        $touch = fn(array $extra = []) => update('applications', (int)$app['id'], $extra + ['updated_at' => now()]);
        if ($act === 'you') {
            if (!ps('first_name') || !ps('last_name') || !filter_var(ps('email'), FILTER_VALIDATE_EMAIL)) $err = 'Add your name and a working email.';
            elseif (!ps('phone') || !ps('birth_date')) $err = 'Add your mobile number and birth date.';
            else { $save($you_fields); $touch(['step' => $next_of('you')]); go($app, $next_of('you')); }
        } elseif ($act === 'travel') {
            if (!ps('ec1_name') || !ps('ec1_phone')) $err = 'Add an emergency contact with a phone number.';
            else { $save($travel_fields); $touch(['step' => $next_of('travel')]); go($app, $next_of('travel')); }
        } elseif ($act === 'trip') {
            $ok = array_map('intval', array_column(form_trips($f), 'id'));
            $c = array_values(array_unique(array_filter(array_map('intval', [post('choice1'), post('choice2'), post('choice3')]), fn($x) => in_array($x, $ok, true))));
            if (!$c) $err = 'Pick a trip.';
            else { $touch(['choice1' => $c[0] ?? null, 'choice2' => $c[1] ?? null, 'choice3' => $c[2] ?? null, 'step' => $next_of('trip')]); go($app, $next_of('trip')); }
        } elseif ($act === 'questions') {
            $ans = app_answers($app);
            foreach (app_questions((int)$f['id']) as $q) {
                $v = $_POST['q'][$q['id']] ?? '';
                $ans[$q['id']] = is_array($v) ? array_values(array_filter(array_map(fn($x) => mb_substr(trim((string)$x), 0, 300), $v), 'strlen')) : mb_substr(trim((string)$v), 0, 5000);
            }
            $touch(['answers' => json_encode($ans), 'step' => $next_of('questions')]); go($app, $next_of('questions'));
        } elseif ($act === 'refs') {
            $ans = app_answers($app); $refs = [];
            foreach (ref_types($f) as $i => $type) $refs[$i] = ['type' => $type, 'name' => clean_name((string)($_POST['ref'][$i]['name'] ?? '')),
                'email' => strtolower(mb_substr(trim((string)($_POST['ref'][$i]['email'] ?? '')), 0, 200)), 'phone' => mb_substr(trim((string)($_POST['ref'][$i]['phone'] ?? '')), 0, 40)];
            foreach ($refs as $r) {
                if (!$r['name'] || !filter_var($r['email'], FILTER_VALIDATE_EMAIL)) { $err = 'Add a name and a working email for each reference.'; break; }
                if ($r['email'] === strtolower((string)$p['email'])) { $err = "A reference can't be you."; break; }
            }
            if (!$err && count(array_unique(array_column($refs, 'email'))) < count($refs)) $err = 'Each reference needs a different email.';
            if (!$err) { $ans['_refs'] = json_encode($refs); $touch(['answers' => json_encode($ans), 'step' => 'review']); go($app, 'review'); }
        } elseif ($act === 'review') {
            $app = application((int)$app['id']); $p = person((int)$p['id']);
            $start = app_trip_start($app, $f);
            $minor = $start && $p['birth_date'] && is_minor($p, $start);
            $gname = clean_name(ps('guardian_name')); $gemail = strtolower(ps('guardian_email', 200)); $gphone = ps('guardian_phone', 40);
            if ($m = app_missing($app, $f, $p)) $err = $m;
            elseif ($minor && (!$gname || !filter_var($gemail, FILTER_VALIDATE_EMAIL) || !$gphone)) $err = 'Because you\'re under 18 on this trip, add a parent or guardian\'s name, email and phone.';
            elseif ($minor && empty($_POST['guardian_consent'])) $err = 'A parent or guardian needs to check the permission box.';
            elseif ($minor && $gemail === strtolower((string)$p['email'])) $err = "Use your parent's own email, not yours.";
            elseif (empty($_POST['agree'])) $err = 'Check the box to confirm your answers are true.';
            elseif (!turnstile_ok()) $err = 'Please complete the check that you\'re a person.';
            else {
                [$due, $label] = deposit_for($f, ps('code', 30));
                $dstatus = (float)$f['deposit'] <= 0 ? 'none' : ($due > 0 ? 'due' : 'waived');
                $ans = app_answers($app);
                tx(function () use ($app, $ans, $due, $dstatus, $label, $touch, $minor, $gname, $gemail, $gphone, $p) {
                    foreach (json_decode((string)($ans['_refs'] ?? '[]'), true) ?: [] as $r)
                        insert('app_refs', ['application_id' => (int)$app['id'], 'ref_type' => $r['type'], 'name' => $r['name'], 'email' => $r['email'], 'phone' => nn($r['phone']),
                            'token' => new_token(), 'status' => 'requested', 'answers' => null, 'requested_at' => now()]);
                    if ($minor && !one('SELECT id FROM guardians WHERE person_id = ? AND LOWER(email) = ?', [$p['id'], $gemail]))
                        insert('guardians', ['person_id' => (int)$p['id'], 'name' => $gname, 'rel' => 'Parent', 'email' => $gemail, 'phone' => $gphone, 'token' => new_token(), 'created_at' => now()]);
                    $touch(['status' => 'submitted', 'submitted_at' => now(), 'deposit_due' => $due, 'deposit_status' => $dstatus, 'discount_code' => $label, 'step' => 'review',
                        'guardian_consent_at' => $minor ? now() : null]);
                });
                if ($app['choice1']) log_activity((int)$app['choice1'], full_name($p) . ' applied');
                $first = $p['preferred_name'] ?: $p['first_name'];
                queue_email((string)$p['email'], 'We got your application', "Hi $first,\n\nThanks for applying for " . $f['name'] . ". The missions team will review it and be in touch.\n\nCheck on it anytime: " . site_url('/apply/?t=' . $app['token']), null, (int)$p['id']);
                if ($minor) queue_email($gemail, full_name($p) . ' applied for a mission trip', "Hi " . strtok($gname, ' ') . ",\n\n" . full_name($p) . " applied for " . $f['name'] . " with " . church_name() . " and listed you as their parent or guardian. If that's not right, please reply to this email.\n\nIf they're accepted, we'll send you a private page with the schedule, flights and everything you need, plus forms to sign.", null, (int)$p['id']);
                foreach (app_refs((int)$app['id']) as $rr)
                    queue_email((string)$rr['email'], 'Reference request from ' . church_name(), "Hi " . strtok((string)$rr['name'], ' ') . ",\n\n" . full_name($p) . " is applying for " . $f['name'] . " with " . church_name() . " and listed you as a reference. It takes about five minutes:\n\n" . ref_url($rr) . "\n\nOnly the missions team will read your answers. Thank you!", null, (int)$p['id']);
                header('Location: /apply/?t=' . $app['token'] . '&done=1'); exit;
            }
        }
        $app = application((int)$app['id']); $p = person((int)$p['id']);
    }
}

public_open($f ? $f['name'] : 'Apply');
$v = fn($k) => e($p[$k] ?? '');
$rels = ['', 'Parent/Guardian', 'Spouse', 'Sibling', 'Friend', 'Other'];
?>
<main class="pub-main" id="main">
<?php if (!$f): ?>
  <section class="tile xl center-tile"><h1 class="disp">We couldn't find that application.</h1><p class="muted" style="margin:0">Check the link, or ask the missions team for a new one.</p></section>

<?php elseif ($app && $app['status'] !== 'draft'): $refs = app_refs((int)$app['id']); ?>
  <section class="tile xl stack-16" style="padding:36px">
    <span class="pill<?= $app['status'] === 'approved' ? ' pill-ok' : '' ?>" style="align-self:flex-start"><?= $app['status'] === 'submitted' ? 'Sent' : e(APP_STATUS_PUBLIC[$app['status']] ?? APP_STATUS[$app['status']]) ?></span>
    <h1 class="disp"><?= $app['status'] === 'approved' ? "You're going." : 'Thanks, ' . e($p['preferred_name'] ?: $p['first_name']) . '.' ?></h1>
    <p class="lead-text"><?= nl2br(e($f['submitted_message'] ?: "We got your application. The missions team will review it and get back to you soon.")) ?></p>
    <?php if ($app['deposit_status'] === 'due'): ?><div class="note row-between"><div><strong>Deposit: <?= money((float)$app['deposit_due']) ?></strong><div class="muted small"><?= stripe_ready() ? 'Pay now to hold your spot while we review.' : 'The missions team will send you a way to pay. Your spot is held while we review.' ?></div></div>
      <?php if (stripe_ready()): ?><form method="post"><?= csrf() ?><input type="hidden" name="do" value="pay_deposit"><button class="btn btn-primary" type="submit">Pay deposit</button></form><?php endif; ?></div>
    <?php elseif ($app['deposit_status'] === 'paid'): ?><div class="note"><strong>Deposit paid. Thank you!</strong></div><?php endif; ?>
    <?php if ($err): ?><div class="note error" role="alert"><?= e($err) ?></div><?php endif; ?>
  </section>
  <?php if ($refs): ?>
  <section class="tile xl stack-12"><h2 class="card-title">Your references</h2>
    <div class="muted small">We emailed each person a private link. If someone didn't get it, ask the missions team to send it again.</div>
    <div class="group inset">
    <?php foreach ($refs as $r): ?><div class="cell"><div class="grow"><strong><?= e($r['name']) ?></strong><div class="muted small"><?= e($r['ref_type']) ?></div></div><span class="pill<?= $r['status'] === 'received' ? ' pill-ok' : '' ?>"><?= $r['status'] === 'received' ? 'Received' : 'Waiting' ?></span></div><?php endforeach; ?>
    </div></section>
  <?php endif; ?>
  <p class="muted small center">Keep this page's link to check on your application.</p>

<?php elseif (!$open): ?>
  <section class="tile xl center-tile"><h1 class="disp"><?= e($f['name']) ?></h1><p class="muted" style="margin:0">Applications are closed<?= $f['closes_on'] && $f['published'] ? ' as of ' . fdate($f['closes_on'], 'F j') : '' ?>. Questions? Reach out to the <?= e(church_name()) ?> missions team.</p></section>

<?php elseif (!$app): $trips = form_trips($f); ?>
  <?php if (!form_is_open($f)): ?><div class="note"><strong>Staff preview</strong><div class="muted small">This form isn't published, so only signed-in staff can see it.</div></div><?php endif; ?>
  <section class="stack-10">
    <h1 class="disp"><?= e($f['name']) ?></h1>
    <?php if ($f['intro']): ?><p class="lead-text"><?= nl2br(e($f['intro'])) ?></p><?php endif; ?>
    <div class="chips gap-8">
      <?php if ($f['closes_on']): ?><span class="pill tag">Apply by <?= fdate($f['closes_on'], 'F j') ?></span><?php endif; ?>
      <?php if ((float)$f['deposit'] > 0): ?><span class="pill tag"><?= money((float)$f['deposit']) ?> deposit</span><?php endif; ?>
      <?php if ((int)$f['refs_required']): ?><span class="pill tag"><?= (int)$f['refs_required'] ?> reference<?= (int)$f['refs_required'] > 1 ? 's' : '' ?></span><?php endif; ?>
      <span class="pill tag">About 15 minutes</span>
    </div>
  </section>
  <?php if ($trips): ?><section class="<?= count($trips) > 1 ? 'g2' : '' ?>"><?php foreach ($trips as $t): $cv = trip_cover((int)$t['id']); ?>
    <div class="tile flush"><?php if ($cv): ?><img src="<?= e($cv) ?>" alt="" class="cover-16x9"><?php endif; ?>
      <div class="pad-16"><strong><?= e($t['public_name'] ?: $t['name']) ?></strong><div class="muted small"><?= date_range($t['start_date'], $t['end_date']) ?><?= (float)$t['cost_per_person'] ? ' · ' . money((float)$t['cost_per_person']) : '' ?></div></div></div>
  <?php endforeach; ?></section><?php endif; ?>
  <form class="form tile xl pad-28" method="post">
    <?= csrf() ?><input type="hidden" name="do" value="start">
    <h2 class="card-title">Let's start</h2>
    <?php if ($err): ?><div class="note error" role="alert"><?= e($err) ?></div><?php endif; ?>
    <div class="r2"><label class="lab">First name<input type="text" name="first_name" required maxlength="80" autocomplete="given-name"></label><label class="lab">Last name<input type="text" name="last_name" required maxlength="80" autocomplete="family-name"></label></div>
    <div class="r2"><label class="lab">Email<input type="email" name="email" required autocomplete="email"></label><label class="lab">Mobile<input type="tel" name="phone" autocomplete="tel"></label></div>
    <label class="hp" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    <?= turnstile_widget() ?>
    <div class="muted small">You'll get a private link so you can finish later. <?= e(church_name()) ?> uses what you share only to plan and run the trip. Passport and medical details are encrypted, seen only by the missions team and your trip leaders, and erased a few months after your trip.</div>
    <div class="actions"><button class="btn btn-primary" type="submit">Start application</button></div>
  </form>

<?php else: $i = array_search($step, $keys); ?>
  <div class="stack-12">
    <div class="muted small"><?= e($f['name']) ?></div>
    <ol class="steps"><?php foreach ($steps as $k => $l): $j = array_search($k, $keys); ?><li class="<?= $j < $i ? 'done' : ($j === $i ? 'on' : '') ?>"><a href="/apply/?t=<?= e($app['token']) ?>&s=<?= $k ?>"<?= $j === $i ? ' aria-current="step"' : '' ?>><?= e($l) ?></a></li><?php endforeach; ?></ol>
  </div>
  <?php if (g('new') !== ''): ?><div class="note"><strong>Save your private link</strong><div class="muted small">Bookmark this page, or <button type="button" class="linklike" data-copy="<?= e(site_url('/apply/?t=' . $app['token'])) ?>">copy the link</button>, to come back and finish later. Don't share it: it shows what you've entered.</div></div><?php endif; ?>
  <?php if ($err): ?><div class="note error" role="alert"><?= e($err) ?></div><?php endif; ?>

  <form class="form tile xl pad-28" method="post" action="/apply/?t=<?= e($app['token']) ?>&s=<?= $step ?>">
    <?= csrf() ?><input type="hidden" name="do" value="<?= $step ?>">
    <h1 class="disp" style="font-size:32px"><?= e($steps[$step]) ?></h1>

  <?php if ($step === 'you'): ?>
    <div class="r3"><label class="lab">First name<input type="text" name="first_name" value="<?= $v('first_name') ?>" required maxlength="80" autocomplete="given-name"></label><label class="lab">Goes by<input type="text" name="preferred_name" value="<?= $v('preferred_name') ?>" maxlength="80" autocomplete="nickname"></label><label class="lab">Last name<input type="text" name="last_name" value="<?= $v('last_name') ?>" required maxlength="80" autocomplete="family-name"></label></div>
    <div class="r3"><label class="lab">Email<input type="email" name="email" value="<?= $v('email') ?>" required autocomplete="email"></label><label class="lab">Mobile <span class="req" aria-hidden="true">*</span><input type="tel" name="phone" value="<?= $v('phone') ?>" required autocomplete="tel"></label><label class="lab">Birth date <span class="req" aria-hidden="true">*</span><input type="date" name="birth_date" value="<?= $v('birth_date') ?>" required autocomplete="bday"></label></div>
    <div class="r3"><label class="lab">Gender<select name="gender"><?php foreach (['' => 'Choose', 'female' => 'Female', 'male' => 'Male'] as $k => $l): ?><option value="<?= $k ?>"<?= ($p['gender'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label class="lab">T-shirt size<input type="text" name="shirt" value="<?= $v('shirt') ?>" placeholder="Women's M" maxlength="30"></label><label class="lab">Street address<input type="text" name="address" value="<?= $v('address') ?>" autocomplete="street-address"></label></div>
    <div class="r3"><label class="lab">City<input type="text" name="city" value="<?= $v('city') ?>" autocomplete="address-level2"></label><label class="lab">State<input type="text" name="state" value="<?= $v('state') ?>" autocomplete="address-level1"></label><label class="lab">ZIP<input type="text" name="zip" value="<?= $v('zip') ?>" autocomplete="postal-code" inputmode="numeric"></label></div>

  <?php elseif ($step === 'travel'): ?>
    <div class="muted small">No passport yet? Skip that part. You can add it after you're accepted.</div>
    <div class="r2"><label class="lab">Name exactly as on passport<input type="text" name="passport_name" value="<?= $v('passport_name') ?>"></label><label class="lab">Passport number<input type="text" name="passport_number" value="<?= e(mask_passport($p['passport_number'] ?? '')) ?>" placeholder="<?= ($p['passport_number'] ?? '') ? 'Type a new number to change it' : '' ?>" autocomplete="off"></label></div>
    <div class="r3"><label class="lab">Issued<input type="date" name="passport_issued" value="<?= $v('passport_issued') ?>"></label><label class="lab">Expires<input type="date" name="passport_expires" value="<?= $v('passport_expires') ?>"></label><label class="lab">Issuing country<input type="text" name="passport_country" value="<?= $v('passport_country') ?>" placeholder="United States" autocomplete="country-name"></label></div>
    <h2 class="card-title" style="margin-top:8px">Emergency contact <span class="req" aria-hidden="true">*</span></h2>
    <div class="r2"><label class="lab">Name<input type="text" name="ec1_name" value="<?= $v('ec1_name') ?>" required></label><label class="lab">Relationship<select name="ec1_rel"><?php foreach ($rels as $r): ?><option<?= ($p['ec1_rel'] ?? '') === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></label></div>
    <div class="r2"><label class="lab">Phone<input type="tel" name="ec1_phone" value="<?= $v('ec1_phone') ?>" required></label><label class="lab">Email<input type="email" name="ec1_email" value="<?= $v('ec1_email') ?>"></label></div>
    <h2 class="card-title" style="margin-top:8px">Health</h2><div class="muted small">Only trip leaders and the missions team see this.</div>
    <div class="r2"><label class="lab">Allergies<textarea name="allergies" rows="2"><?= $v('allergies') ?></textarea></label><label class="lab">Medications<textarea name="meds" rows="2"><?= $v('meds') ?></textarea></label></div>
    <div class="r2"><label class="lab">Dietary needs<textarea name="diet" rows="2"><?= $v('diet') ?></textarea></label><label class="lab">Health concerns<textarea name="health" rows="2"><?= $v('health') ?></textarea></label></div>

  <?php elseif ($step === 'trip'): $trips = form_trips($f); $n = max(1, (int)$f['choices']); ?>
    <?php if (!$trips): ?><div class="muted">There are no trips open on this form right now.</div><?php endif; ?>
    <?php for ($c = 1; $c <= min($n, max(1, count($trips))); $c++): ?>
      <label class="lab"><?= $n > 1 ? ['', 'First choice', 'Second choice', 'Third choice'][$c] : 'Which trip?' ?><?= $c === 1 ? ' <span class="req" aria-hidden="true">*</span>' : '' ?>
        <select name="choice<?= $c ?>"<?= $c === 1 ? ' required' : '' ?>><option value=""><?= $c === 1 ? 'Choose a trip' : 'No other choice' ?></option><?php foreach ($trips as $t): ?><option value="<?= (int)$t['id'] ?>"<?= (int)$app["choice$c"] === (int)$t['id'] ? ' selected' : '' ?>><?= e($t['public_name'] ?: $t['name']) ?> · <?= date_range($t['start_date'], $t['end_date']) ?></option><?php endforeach; ?></select></label>
    <?php endfor; ?>

  <?php elseif ($step === 'questions'): $ans = app_answers($app); ?>
    <?php foreach (app_questions((int)$f['id']) as $q): $name = 'q[' . $q['id'] . ']'; $val = $ans[$q['id']] ?? ''; $opts = lines($q['options']); $qid = 'q' . (int)$q['id']; ?>
      <?php if (in_array($q['kind'], ['yesno', 'choice', 'checkboxes'], true)): ?>
      <fieldset class="lab"><legend><?= e($q['label']) ?><?= $q['required'] ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></legend><?php if ($q['help']): ?><span class="qhelp"><?= e($q['help']) ?></span><?php endif; ?>
        <?php if ($q['kind'] === 'checkboxes'): ?><input type="hidden" name="<?= $name ?>[]" value=""><?php endif; ?>
        <div class="opts"><?php foreach ($q['kind'] === 'yesno' ? ['Yes', 'No'] : $opts as $o): ?><label><input type="<?= $q['kind'] === 'checkboxes' ? 'checkbox' : 'radio' ?>" name="<?= $name ?><?= $q['kind'] === 'checkboxes' ? '[]' : '' ?>" value="<?= e($o) ?>"<?= ($q['kind'] === 'checkboxes' ? in_array($o, (array)$val, true) : $val === $o) ? ' checked' : '' ?><?= $q['required'] && $q['kind'] !== 'checkboxes' ? ' required' : '' ?>><?= e($o) ?></label><?php endforeach; ?></div></fieldset>
      <?php else: ?>
      <label class="lab" for="<?= $qid ?>"><span><?= e($q['label']) ?><?= $q['required'] ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></span><?php if ($q['help']): ?><span class="qhelp"><?= e($q['help']) ?></span><?php endif; ?>
      <?php if ($q['kind'] === 'long'): ?><textarea id="<?= $qid ?>" name="<?= $name ?>" rows="4" maxlength="5000"<?= $q['required'] ? ' required' : '' ?>><?= e(is_array($val) ? '' : $val) ?></textarea>
      <?php elseif ($q['kind'] === 'date'): ?><input id="<?= $qid ?>" type="date" name="<?= $name ?>" value="<?= e(is_array($val) ? '' : $val) ?>"<?= $q['required'] ? ' required' : '' ?>>
      <?php else: ?><input id="<?= $qid ?>" type="text" name="<?= $name ?>" value="<?= e(is_array($val) ? '' : $val) ?>" maxlength="1000"<?= $q['required'] ? ' required' : '' ?>><?php endif; ?></label>
      <?php endif; ?>
    <?php endforeach; ?>

  <?php elseif ($step === 'refs'): $refs = json_decode((string)(app_answers($app)['_refs'] ?? '[]'), true) ?: []; ?>
    <div class="muted small">We'll email each person a short form. Pick people who know you well, not family.</div>
    <?php foreach (ref_types($f) as $i => $type): $r = $refs[$i] ?? []; ?>
      <h2 class="card-title" style="margin-top:6px"><?= e($type) ?> <span class="req" aria-hidden="true">*</span></h2>
      <div class="r3"><label class="lab">Name<input type="text" name="ref[<?= $i ?>][name]" value="<?= e($r['name'] ?? '') ?>" required maxlength="80"></label><label class="lab">Email<input type="email" name="ref[<?= $i ?>][email]" value="<?= e($r['email'] ?? '') ?>" required></label><label class="lab">Phone<input type="tel" name="ref[<?= $i ?>][phone]" value="<?= e($r['phone'] ?? '') ?>"></label></div>
    <?php endforeach; ?>

  <?php else: $ans = app_answers($app); $refs = json_decode((string)($ans['_refs'] ?? '[]'), true) ?: []; [$due, $label] = deposit_for($f, null);
    $start = app_trip_start($app, $f); $minor = $start && $p['birth_date'] && is_minor($p, $start); ?>
    <dl class="sum">
      <dt>Name</dt><dd><?= e(full_name($p)) ?></dd>
      <dt>Email and phone</dt><dd><?= e($p['email']) ?><?= $p['phone'] ? ' · ' . e($p['phone']) : '' ?></dd>
      <dt>Birth date</dt><dd><?= $p['birth_date'] ? fdate($p['birth_date'], 'F j, Y') : '<span class="muted">Missing</span>' ?></dd>
      <dt>Passport</dt><dd><?= $p['passport_expires'] ? 'Expires ' . fdate($p['passport_expires'], 'F j, Y') : '<span class="muted">Not added yet</span>' ?></dd>
      <dt>Emergency contact</dt><dd><?= $p['ec1_name'] ? e($p['ec1_name'] . ' · ' . $p['ec1_phone']) : '<span class="muted">Missing</span>' ?></dd>
      <?php if (isset($steps['trip'])): ?><dt>Trip</dt><dd><?= e(implode(', ', array_map(fn($c) => trip((int)$c)['name'] ?? '', array_filter([$app['choice1'], $app['choice2'], $app['choice3']]))) ?: '—') ?></dd><?php endif; ?>
      <?php if ($refs): ?><dt>References</dt><dd><?= e(implode(', ', array_filter(array_column($refs, 'name')))) ?: '<span class="muted">Missing</span>' ?></dd><?php endif; ?>
    </dl>
    <?php if ($minor): ?>
      <section class="note stack-12"><strong>A parent or guardian needs to give permission</strong><div class="muted small">You'll be under 18 on this trip. Please fill this in together.</div>
        <div class="r3"><label class="lab">Parent or guardian's name<input type="text" name="guardian_name" value="<?= e(ps('guardian_name', 80)) ?>" required maxlength="80"></label><label class="lab">Their email<input type="email" name="guardian_email" value="<?= e(ps('guardian_email', 200)) ?>" required></label><label class="lab">Their phone<input type="tel" name="guardian_phone" value="<?= e(ps('guardian_phone', 40)) ?>" required></label></div>
        <label class="chk"><input type="checkbox" name="guardian_consent" value="1" required> I'm their parent or guardian, and I give permission for them to apply and to share this information with <?= e(church_name()) ?>.</label>
      </section>
    <?php endif; ?>
    <?php if ((float)$f['deposit'] > 0): ?>
      <div class="note"><strong>Deposit: <?= money($due) ?><?= $label ? ' (' . e($label) . ' price)' : '' ?></strong><div class="muted small">You won't pay today. If you have a discount code, add it here.</div></div>
      <label class="lab">Discount code (optional)<input type="text" name="code" value="<?= e(ps('code', 30)) ?>" autocomplete="off" class="upper"></label>
    <?php endif; ?>
    <label class="chk"><input type="checkbox" name="agree" value="1" required> Everything here is true to the best of my knowledge.</label>
    <?= turnstile_widget() ?>
  <?php endif; ?>

    <div class="actions" style="justify-content:space-between">
      <?php if ($pv = $prev_of($step)): ?><a class="btn" href="/apply/?t=<?= e($app['token']) ?>&s=<?= $pv ?>">Back</a><?php else: ?><span></span><?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= $step === 'review' ? 'Send application' : 'Save and continue' ?></button>
    </div>
  </form>
<?php endif; ?>
  <p class="muted small center"><?= e(church_name()) ?> Missions</p>
</main>
<?php public_close(); ?>
