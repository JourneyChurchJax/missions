<?php
// Public trip application. Anyone with the link can apply; no preview password.
// ?f=slug starts one, ?t=token resumes it (the token is the private link).
require dirname(__DIR__) . '/inc/bootstrap.php';

$staff_preview = !empty($_SESSION['preview_ok']);
$app = isset($_GET['t']) ? one('SELECT * FROM applications WHERE token = ?', [(string)$_GET['t']]) : null;
$f = $app ? app_form((int)$app['form_id']) : app_form_by_slug((string)($_GET['f'] ?? ''));
$p = $app ? person((int)$app['person_id']) : null;

// Steps this form uses
$steps = APP_STEPS;
if ($f && $f['trip_mode'] === 'none') unset($steps['trip']);
if ($f && !app_questions((int)$f['id'])) unset($steps['questions']);
if ($f && !(int)$f['refs_required']) unset($steps['refs']);
$keys = array_keys($steps);
$step = $app ? (array_key_exists($_GET['s'] ?? '', $steps) ? $_GET['s'] : ($app['step'] ?: 'you')) : 'you';
if (!isset($steps[$step])) $step = 'you';
$next_of = fn($s) => $keys[array_search($s, $keys) + 1] ?? 'review';
$prev_of = fn($s) => $keys[array_search($s, $keys) - 1] ?? null;

function go(array $app, string $s): never { header('Location: /apply/?t=' . $app['token'] . '&s=' . $s); exit; }
$open = $f && (form_is_open($f) || $staff_preview);

$you_fields = ['first_name', 'preferred_name', 'last_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'city', 'state', 'zip', 'shirt'];
$travel_fields = ['passport_name', 'passport_number', 'passport_country', 'passport_issued', 'passport_expires',
    'ec1_name', 'ec1_rel', 'ec1_phone', 'ec1_email', 'allergies', 'meds', 'diet', 'health', 'other'];
$err = '';

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
    if (!$open) { $err = 'This application is closed.'; }
    elseif ($act === 'start') {
        if (post('website')) { header('Location: /apply/?f=' . urlencode($f['slug'])); exit; } // bots fill the hidden field
        $first = post('first_name'); $last = post('last_name'); $email = strtolower((string)post('email'));
        if (!$first || !$last || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Add your name and a working email.';
        else {
            $pid = insert('people', ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => nn(post('phone')), 'created_at' => now()]);
            $tok = new_token();
            insert('applications', ['form_id' => (int)$f['id'], 'person_id' => $pid, 'status' => 'draft', 'token' => $tok, 'step' => 'you',
                'answers' => '{}', 'deposit_status' => 'none', 'deposit_due' => 0, 'created_at' => now(), 'updated_at' => now()]);
            header("Location: /apply/?t=$tok&s=you&new=1"); exit;
        }
    }
    elseif ($app && $app['status'] === 'draft') {
        $save = fn(array $fields) => update('people', (int)$p['id'], array_combine($fields, array_map(fn($k) => nn(post($k)), $fields)));
        $touch = fn(array $extra = []) => update('applications', (int)$app['id'], $extra + ['updated_at' => now()]);
        if ($act === 'you') {
            if (!post('first_name') || !post('last_name') || !filter_var(post('email'), FILTER_VALIDATE_EMAIL)) $err = 'Add your name and a working email.';
            else { $save($you_fields); $touch(['step' => $next_of('you')]); go($app, $next_of('you')); }
        } elseif ($act === 'travel') {
            $save($travel_fields); $touch(['step' => $next_of('travel')]); go($app, $next_of('travel'));
        } elseif ($act === 'trip') {
            $ok = array_column(form_trips($f), 'id');
            $c = array_values(array_unique(array_filter(array_map('intval', [post('choice1'), post('choice2'), post('choice3')]), fn($x) => in_array($x, array_map('intval', $ok), true))));
            $touch(['choice1' => $c[0] ?? null, 'choice2' => $c[1] ?? null, 'choice3' => $c[2] ?? null, 'step' => $next_of('trip')]);
            go($app, $next_of('trip'));
        } elseif ($act === 'questions') {
            $ans = app_answers($app);
            foreach (app_questions((int)$f['id']) as $q) {
                $v = $_POST['q'][$q['id']] ?? '';
                $ans[$q['id']] = is_array($v) ? array_values(array_filter(array_map(fn($x) => trim((string)$x), $v), 'strlen')) : trim((string)$v);
            }
            $touch(['answers' => json_encode($ans), 'step' => $next_of('questions')]); go($app, $next_of('questions'));
        } elseif ($act === 'refs') {
            $ans = app_answers($app); $refs = [];
            foreach (ref_types($f) as $i => $type) $refs[$i] = ['type' => $type, 'name' => trim((string)($_POST['ref'][$i]['name'] ?? '')),
                'email' => strtolower(trim((string)($_POST['ref'][$i]['email'] ?? ''))), 'phone' => trim((string)($_POST['ref'][$i]['phone'] ?? ''))];
            foreach ($refs as $r) if ($r['email'] && !filter_var($r['email'], FILTER_VALIDATE_EMAIL)) { $err = "Check the email for {$r['name']}."; break; }
            if (!$err) { $ans['_refs'] = json_encode($refs); $touch(['answers' => json_encode($ans), 'step' => 'review']); go($app, 'review'); }
        } elseif ($act === 'review') {
            $app = application((int)$app['id']); $p = person((int)$p['id']);
            if ($m = app_missing($app, $f, $p)) $err = $m;
            elseif (empty($_POST['agree'])) $err = 'Check the box to confirm your answers are true.';
            else {
                [$due, $label] = deposit_for($f, post('code'));
                $dstatus = (float)$f['deposit'] <= 0 ? 'none' : ($due > 0 ? 'due' : 'waived');
                $ans = app_answers($app);
                foreach (json_decode((string)($ans['_refs'] ?? '[]'), true) ?: [] as $r)
                    insert('app_refs', ['application_id' => (int)$app['id'], 'ref_type' => $r['type'], 'name' => $r['name'], 'email' => $r['email'], 'phone' => nn($r['phone']),
                        'token' => new_token(), 'status' => 'requested', 'answers' => null, 'requested_at' => now()]);
                $touch(['status' => 'submitted', 'submitted_at' => now(), 'deposit_due' => $due, 'deposit_status' => $dstatus,
                    'discount_code' => $label, 'step' => 'review']);
                if ($app['choice1']) log_activity((int)$app['choice1'], full_name($p) . ' applied');
                $first = $p['preferred_name'] ?: $p['first_name'];
                send_email((string)$p['email'], 'We got your application', "Hi $first,\n\nThanks for applying for " . $f['name'] . ". The missions team will review it and be in touch.\n\nCheck on it anytime: " . site_url('/apply/?t=' . $app['token']), null, (int)$p['id']);
                foreach (app_refs((int)$app['id']) as $rr)
                    send_email((string)$rr['email'], 'Would you be a reference for ' . full_name($p) . '?', "Hi " . strtok((string)$rr['name'], ' ') . ",\n\n" . full_name($p) . " is applying for " . $f['name'] . " with Journey Church and listed you as a reference. It takes about five minutes:\n\n" . ref_url($rr) . "\n\nOnly the missions team will read your answers. Thank you!", null, (int)$p['id']);
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
<main class="pub-main">
<?php if (!$f): ?>
  <section class="tile xl" style="padding:36px;text-align:center;gap:10px"><h1 class="disp">We couldn't find that application.</h1><p class="muted" style="margin:0">Check the link, or ask the missions team for a new one.</p></section>

<?php elseif ($app && $app['status'] !== 'draft'): $refs = app_refs((int)$app['id']); ?>
  <section class="tile xl" style="padding:36px;gap:16px">
    <span class="pill<?= $app['status'] === 'approved' ? ' pill-ok' : '' ?>" style="align-self:flex-start"><?= $app['status'] === 'submitted' ? 'Sent' : e(APP_STATUS[$app['status']]) ?></span>
    <h1 class="disp"><?= $app['status'] === 'approved' ? "You're going." : 'Thanks, ' . e($p['preferred_name'] ?: $p['first_name']) . '.' ?></h1>
    <p style="margin:0;font-size:17px;line-height:1.55"><?= nl2br(e($f['submitted_message'] ?: "We got your application. The missions team will review it and get back to you soon.")) ?></p>
    <?php if ($app['deposit_status'] === 'due'): ?><div class="note"><strong>Deposit: <?= money((float)$app['deposit_due']) ?></strong><div class="muted small">The missions team will send you a way to pay. Your spot is held while we review.</div></div><?php endif; ?>
  </section>
  <?php if ($refs): ?>
  <section class="tile xl" style="gap:12px"><strong style="font-size:18px">Your references</strong>
    <div class="muted small">Send each person their link. It takes them about five minutes. We'll also email them.</div>
    <div class="group" style="box-shadow:none;background:var(--cream)">
    <?php foreach ($refs as $r): ?>
      <div class="cell"><div class="grow"><strong><?= e($r['name']) ?></strong><div class="muted small"><?= e($r['ref_type']) ?> · <?= $r['status'] === 'received' ? 'Done, thank you' : 'Waiting' ?></div></div>
      <?php if ($r['status'] !== 'received'): ?><a class="small" href="#" data-copy="<?= e(ref_url($r)) ?>">Copy link</a><a class="small" href="mailto:<?= e($r['email']) ?>?subject=<?= rawurlencode('Would you be a reference for me?') ?>&body=<?= rawurlencode("I'm applying for " . $f['name'] . " with Journey Church. Would you fill out a short reference for me?\n" . ref_url($r)) ?>">Email</a><?php endif; ?></div>
    <?php endforeach; ?>
    </div></section>
  <?php endif; ?>
  <p class="muted small" style="text-align:center">Keep this page's link to check on your application.</p>

<?php elseif (!$open): ?>
  <section class="tile xl" style="padding:36px;text-align:center;gap:10px"><h1 class="disp"><?= e($f['name']) ?></h1><p class="muted" style="margin:0">Applications are closed<?= $f['closes_on'] && $f['published'] ? ' as of ' . fdate($f['closes_on'], 'F j') : '' ?>. Questions? Reach out to the Journey missions team.</p></section>

<?php elseif (!$app): $trips = form_trips($f); ?>
  <?php if (!form_is_open($f)): ?><div class="note"><strong>Staff preview</strong><div class="muted small">This form isn't published, so only signed-in staff can see it.</div></div><?php endif; ?>
  <section style="display:flex;flex-direction:column;gap:10px">
    <h1 class="disp"><?= e($f['name']) ?></h1>
    <?php if ($f['intro']): ?><p style="margin:0;font-size:17px;line-height:1.55"><?= nl2br(e($f['intro'])) ?></p><?php endif; ?>
    <div class="chips" style="gap:8px">
      <?php if ($f['closes_on']): ?><span class="pill">Apply by <?= fdate($f['closes_on'], 'F j') ?></span><?php endif; ?>
      <?php if ((float)$f['deposit'] > 0): ?><span class="pill"><?= money((float)$f['deposit']) ?> deposit</span><?php endif; ?>
      <?php if ((int)$f['refs_required']): ?><span class="pill"><?= (int)$f['refs_required'] ?> reference<?= (int)$f['refs_required'] > 1 ? 's' : '' ?></span><?php endif; ?>
      <span class="pill">About 15 minutes</span>
    </div>
  </section>
  <?php if ($trips): ?><section class="<?= count($trips) > 1 ? 'g2' : '' ?>"><?php foreach ($trips as $t): $cv = trip_cover((int)$t['id']); ?>
    <div class="tile" style="padding:0;overflow:hidden;gap:0"><?php if ($cv): ?><img src="<?= e($cv) ?>" alt="" style="width:100%;aspect-ratio:16/9;object-fit:cover"><?php endif; ?>
      <div style="padding:16px 18px"><strong><?= e($t['public_name'] ?: $t['name']) ?></strong><div class="muted small"><?= date_range($t['start_date'], $t['end_date']) ?><?= (float)$t['cost_per_person'] ? ' · ' . money((float)$t['cost_per_person']) : '' ?></div></div></div>
  <?php endforeach; ?></section><?php endif; ?>
  <form class="form tile xl" method="post" style="padding:28px">
    <?= csrf() ?><input type="hidden" name="do" value="start">
    <strong style="font-size:18px">Let's start</strong>
    <?php if ($err): ?><div class="note"><?= e($err) ?></div><?php endif; ?>
    <div class="r2"><label class="lab">First name<input type="text" name="first_name" required autocomplete="given-name"></label><label class="lab">Last name<input type="text" name="last_name" required autocomplete="family-name"></label></div>
    <div class="r2"><label class="lab">Email<input type="email" name="email" required autocomplete="email"></label><label class="lab">Mobile<input type="tel" name="phone" autocomplete="tel"></label></div>
    <label class="hp" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    <div class="muted small">You'll get a private link so you can finish later.</div>
    <div class="actions"><button class="btn btn-primary" type="submit">Start application</button></div>
  </form>

<?php else: $i = array_search($step, $keys); ?>
  <div style="display:flex;flex-direction:column;gap:14px">
    <div class="muted small"><?= e($f['name']) ?></div>
    <ol class="steps"><?php foreach ($steps as $k => $l): $j = array_search($k, $keys); ?><li class="<?= $j < $i ? 'done' : ($j === $i ? 'on' : '') ?>"><a href="/apply/?t=<?= e($app['token']) ?>&s=<?= $k ?>"><?= e($l) ?></a></li><?php endforeach; ?></ol>
  </div>
  <?php if (isset($_GET['new'])): ?><div class="note"><strong>Save your private link</strong><div class="muted small">Bookmark this page, or <a href="#" data-copy="https://missions.journeychurch.org/apply/?t=<?= e($app['token']) ?>">copy the link</a>, to come back and finish later.</div></div><?php endif; ?>
  <?php if ($err): ?><div class="note" style="box-shadow:inset 3px 0 0 var(--ember)"><?= e($err) ?></div><?php endif; ?>

  <form class="form tile xl" method="post" action="/apply/?t=<?= e($app['token']) ?>&s=<?= $step ?>" style="padding:28px;gap:16px">
    <?= csrf() ?><input type="hidden" name="do" value="<?= $step ?>">
    <h1 class="disp" style="font-size:32px"><?= e($steps[$step]) ?></h1>

  <?php if ($step === 'you'): ?>
    <div class="r3"><label class="lab">First name<input type="text" name="first_name" value="<?= $v('first_name') ?>" required></label><label class="lab">Goes by<input type="text" name="preferred_name" value="<?= $v('preferred_name') ?>"></label><label class="lab">Last name<input type="text" name="last_name" value="<?= $v('last_name') ?>" required></label></div>
    <div class="r3"><label class="lab">Email<input type="email" name="email" value="<?= $v('email') ?>" required></label><label class="lab">Mobile <span class="req">*</span><input type="tel" name="phone" value="<?= $v('phone') ?>"></label><label class="lab">Birth date <span class="req">*</span><input type="date" name="birth_date" value="<?= $v('birth_date') ?>"></label></div>
    <div class="r3"><label class="lab">Gender<select name="gender"><?php foreach (['' => 'Choose', 'female' => 'Female', 'male' => 'Male'] as $k => $l): ?><option value="<?= $k ?>"<?= ($p['gender'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label class="lab">T-shirt size<input type="text" name="shirt" value="<?= $v('shirt') ?>" placeholder="Women's M"></label><label class="lab">Street address<input type="text" name="address" value="<?= $v('address') ?>" autocomplete="street-address"></label></div>
    <div class="r3"><label class="lab">City<input type="text" name="city" value="<?= $v('city') ?>"></label><label class="lab">State<input type="text" name="state" value="<?= $v('state') ?>"></label><label class="lab">ZIP<input type="text" name="zip" value="<?= $v('zip') ?>"></label></div>

  <?php elseif ($step === 'travel'): ?>
    <div class="muted small">No passport yet? Skip that part. You can add it after you're accepted.</div>
    <div class="r2"><label class="lab">Name exactly as on passport<input type="text" name="passport_name" value="<?= $v('passport_name') ?>"></label><label class="lab">Passport number<input type="text" name="passport_number" value="<?= $v('passport_number') ?>" autocomplete="off"></label></div>
    <div class="r3"><label class="lab">Issued<input type="date" name="passport_issued" value="<?= $v('passport_issued') ?>"></label><label class="lab">Expires<input type="date" name="passport_expires" value="<?= $v('passport_expires') ?>"></label><label class="lab">Issuing country<input type="text" name="passport_country" value="<?= $v('passport_country') ?>" placeholder="United States"></label></div>
    <strong style="margin-top:8px">Emergency contact <span class="req">*</span></strong>
    <div class="r2"><label class="lab">Name<input type="text" name="ec1_name" value="<?= $v('ec1_name') ?>"></label><label class="lab">Relationship<select name="ec1_rel"><?php foreach ($rels as $r): ?><option<?= ($p['ec1_rel'] ?? '') === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></label></div>
    <div class="r2"><label class="lab">Phone<input type="tel" name="ec1_phone" value="<?= $v('ec1_phone') ?>"></label><label class="lab">Email<input type="email" name="ec1_email" value="<?= $v('ec1_email') ?>"></label></div>
    <strong style="margin-top:8px">Health</strong><div class="muted small">Only trip leaders and the missions team see this.</div>
    <div class="r2"><label class="lab">Allergies<textarea name="allergies" rows="2"><?= $v('allergies') ?></textarea></label><label class="lab">Medications<textarea name="meds" rows="2"><?= $v('meds') ?></textarea></label></div>
    <div class="r2"><label class="lab">Dietary needs<textarea name="diet" rows="2"><?= $v('diet') ?></textarea></label><label class="lab">Health concerns<textarea name="health" rows="2"><?= $v('health') ?></textarea></label></div>

  <?php elseif ($step === 'trip'): $trips = form_trips($f); $n = max(1, (int)$f['choices']); ?>
    <?php if (!$trips): ?><div class="muted">There are no trips open on this form right now.</div><?php endif; ?>
    <?php for ($c = 1; $c <= min($n, max(1, count($trips))); $c++): ?>
      <label class="lab"><?= $n > 1 ? ['', 'First choice', 'Second choice', 'Third choice'][$c] : 'Which trip?' ?><?= $c === 1 ? ' <span class="req">*</span>' : '' ?>
        <select name="choice<?= $c ?>"><option value=""><?= $c === 1 ? 'Choose a trip' : 'No other choice' ?></option><?php foreach ($trips as $t): ?><option value="<?= (int)$t['id'] ?>"<?= (int)$app["choice$c"] === (int)$t['id'] ? ' selected' : '' ?>><?= e($t['public_name'] ?: $t['name']) ?> · <?= date_range($t['start_date'], $t['end_date']) ?></option><?php endforeach; ?></select></label>
    <?php endfor; ?>

  <?php elseif ($step === 'questions'): $ans = app_answers($app); ?>
    <?php foreach (app_questions((int)$f['id']) as $q): $name = 'q[' . $q['id'] . ']'; $val = $ans[$q['id']] ?? ''; $opts = lines($q['options']); ?>
      <div class="lab" style="gap:8px"><span><?= e($q['label']) ?><?= $q['required'] ? ' <span class="req">*</span>' : '' ?></span><?php if ($q['help']): ?><span class="qhelp"><?= e($q['help']) ?></span><?php endif; ?>
      <?php if ($q['kind'] === 'long'): ?><textarea name="<?= $name ?>" rows="4"><?= e(is_array($val) ? '' : $val) ?></textarea>
      <?php elseif ($q['kind'] === 'date'): ?><input type="date" name="<?= $name ?>" value="<?= e(is_array($val) ? '' : $val) ?>">
      <?php elseif ($q['kind'] === 'yesno' || $q['kind'] === 'choice'): ?><div class="opts"><?php foreach ($q['kind'] === 'yesno' ? ['Yes', 'No'] : $opts as $o): ?><label><input type="radio" name="<?= $name ?>" value="<?= e($o) ?>"<?= $val === $o ? ' checked' : '' ?>><?= e($o) ?></label><?php endforeach; ?></div>
      <?php elseif ($q['kind'] === 'checkboxes'): ?><input type="hidden" name="<?= $name ?>[]" value=""><div class="opts"><?php foreach ($opts as $o): ?><label><input type="checkbox" name="<?= $name ?>[]" value="<?= e($o) ?>"<?= in_array($o, (array)$val, true) ? ' checked' : '' ?>><?= e($o) ?></label><?php endforeach; ?></div>
      <?php else: ?><input type="text" name="<?= $name ?>" value="<?= e(is_array($val) ? '' : $val) ?>"><?php endif; ?>
      </div>
    <?php endforeach; ?>

  <?php elseif ($step === 'refs'): $refs = json_decode((string)(app_answers($app)['_refs'] ?? '[]'), true) ?: []; ?>
    <div class="muted small">We'll send each person a short form. Pick people who know you well, not family.</div>
    <?php foreach (ref_types($f) as $i => $type): $r = $refs[$i] ?? []; ?>
      <strong style="margin-top:6px"><?= e($type) ?> <span class="req">*</span></strong>
      <div class="r3"><label class="lab">Name<input type="text" name="ref[<?= $i ?>][name]" value="<?= e($r['name'] ?? '') ?>"></label><label class="lab">Email<input type="email" name="ref[<?= $i ?>][email]" value="<?= e($r['email'] ?? '') ?>"></label><label class="lab">Phone<input type="tel" name="ref[<?= $i ?>][phone]" value="<?= e($r['phone'] ?? '') ?>"></label></div>
    <?php endforeach; ?>

  <?php else: $ans = app_answers($app); $refs = json_decode((string)($ans['_refs'] ?? '[]'), true) ?: []; [$due, $label] = deposit_for($f, null); ?>
    <dl class="sum">
      <dt>Name</dt><dd><?= e(full_name($p)) ?></dd>
      <dt>Email and phone</dt><dd><?= e($p['email']) ?><?= $p['phone'] ? ' · ' . e($p['phone']) : '' ?></dd>
      <dt>Birth date</dt><dd><?= $p['birth_date'] ? fdate($p['birth_date'], 'F j, Y') : '<span class="muted">Missing</span>' ?></dd>
      <dt>Passport</dt><dd><?= $p['passport_expires'] ? 'Expires ' . fdate($p['passport_expires'], 'F j, Y') : '<span class="muted">Not added yet</span>' ?></dd>
      <dt>Emergency contact</dt><dd><?= $p['ec1_name'] ? e($p['ec1_name'] . ' · ' . $p['ec1_phone']) : '<span class="muted">Missing</span>' ?></dd>
      <?php if (isset($steps['trip'])): ?><dt>Trip</dt><dd><?= e(implode(', ', array_map(fn($c) => trip((int)$c)['name'] ?? '', array_filter([$app['choice1'], $app['choice2'], $app['choice3']]))) ?: '—') ?></dd><?php endif; ?>
      <?php if ($refs): ?><dt>References</dt><dd><?= e(implode(', ', array_filter(array_column($refs, 'name')))) ?: '<span class="muted">Missing</span>' ?></dd><?php endif; ?>
    </dl>
    <?php if ((float)$f['deposit'] > 0): ?>
      <div class="note"><strong>Deposit: <?= money($due) ?><?= $label ? ' (' . e($label) . ' price)' : '' ?></strong><div class="muted small">You won't pay today. If you have a discount code, add it here.</div></div>
      <label class="lab">Discount code (optional)<input type="text" name="code" value="<?= e(post('code') ?? '') ?>" autocomplete="off" style="text-transform:uppercase"></label>
    <?php endif; ?>
    <label class="chk" style="display:flex;gap:10px;align-items:flex-start;font-size:15px"><input type="checkbox" name="agree" value="1" style="margin-top:4px"> Everything here is true to the best of my knowledge.</label>
  <?php endif; ?>

    <div class="actions" style="justify-content:space-between">
      <?php if ($pv = $prev_of($step)): ?><a class="btn" href="/apply/?t=<?= e($app['token']) ?>&s=<?= $pv ?>">Back</a><?php else: ?><span></span><?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= $step === 'review' ? 'Send application' : 'Save and continue' ?></button>
    </div>
  </form>
<?php endif; ?>
  <p class="muted small" style="text-align:center">Journey Church Missions</p>
</main>
<script src="/assets/app.js?v=5"></script>
</body>
</html>
