<?php
// Public fundraising page. missions.journeychurch.org/thomas (rewritten to ?s=thomas) or /give/?trip=belize for the whole team.
define('REAL_DB', true);
define('ACTOR', 'Donor');
require dirname(__DIR__) . '/inc/bootstrap.php';

$slug = strtolower(preg_replace('/[^a-z0-9-]/i', '', g('s')));
$pg = $slug !== '' ? page_by_slug($slug) : null;
$own = $pg && !empty($_SESSION['auth']) && (int)$_SESSION['auth']['person_id'] === (int)$pg['person_id'];
if ($pg && $pg['page_status'] !== 'live' && !is_staff_session() && !$own) $pg = null;
if ($pg && $pg['trip_status'] !== 'active' && !is_staff_session()) $pg = null;
$team = !$pg && g('trip') !== '' ? one("SELECT * FROM trips WHERE slug = ? AND status = 'active'", [strtolower(g('trip'))]) : null;
$t = $pg ? trip((int)$pg['trip_id']) : $team;
$err = '';

if ($t && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $raw = post('amount') === 'other' ? ps('other', 20) : ps('amount', 20);
    $amt = round((float)str_replace([',', '$'], '', $raw), 2);
    $email = strtolower(ps('email', 200));
    if (ps('website')) { header('Location: ' . safe_path($_SERVER['REQUEST_URI'] ?? '/')); exit; }
    if (rate_limited(client_key() . ':give', 8, 3600)) $err = 'Too many tries from this connection. Please wait a bit and try again.';
    elseif (!turnstile_ok()) $err = 'Please complete the check that you\'re a person.';
    elseif ($amt < 5 || $amt > 25000) $err = 'Gifts can be from $5 to $25,000.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Add your email so we can send your receipt.';
    elseif (!ps('first_name') || !ps('last_name')) $err = 'Add your name for your tax receipt.';
    elseif (!stripe_ready()) $err = 'Online giving opens soon. You can give by check today (see below).';
    elseif (post('monthly') && $t['start_date'] <= date('Y-m-d', strtotime('+20 days'))) $err = 'The trip is too close for monthly gifts. Please make a one-time gift.';
    else {
        $cover = (bool)post('cover');
        $charge = $cover ? with_fee_covered($amt) : $amt;
        $back = site_url($pg ? '/' . $pg['page_slug'] : '/give/?trip=' . $t['slug']);
        try {
            $url = stripe_checkout('gift', $charge, ($pg ? 'Gift toward ' . public_name($pg, $t['start_date']) . "'s trip · " : '') . $t['name'] . ' mission trip', [
                'trip_id' => $t['id'], 'person_id' => $pg['person_id'] ?? '', 'first' => ps('first_name', 80), 'last' => ps('last_name', 80),
                'anonymous' => post('anonymous') ? '1' : '', 'message' => ps('message', 400), 'covered_fee' => $cover ? number_format($charge - $amt, 2, '.', '') : '0'],
                site_url('/give/thanks.php?' . ($pg ? 's=' . $pg['page_slug'] : 'trip=' . $t['slug'])), $back, $email, (bool)post('monthly'), $t['start_date']);
            header('Location: ' . $url); exit;
        } catch (Throwable $e) { app_log('Give checkout: ' . $e->getMessage(), 'errors'); $err = 'We couldn\'t start the payment. Please try again in a minute.'; }
    }
}

if (!$t) { http_response_code(404); public_open('Give'); ?>
<main class="pub-main" id="main"><section class="tile xl" style="padding:36px;text-align:center;gap:10px"><h1 class="disp">This page isn't available.</h1><p class="muted" style="margin:0">Check the link, or give to <?= e(church_name()) ?> missions at journeychurch.org.</p></section></main>
<?php public_close(); exit; }

$tid = (int)$t['id'];
if ($pg) {
    $goal = member_goal($t, $pg); $raised = (float)$pg['raised'];
    $count = (int)val("SELECT COUNT(*) FROM gifts WHERE trip_id = ? AND person_id = ? AND " . GIFT_COUNTS, [$tid, $pg['person_id']]);
    $photo = $pg['page_photo_id'] ? '/give/photo.php?s=' . urlencode($pg['page_slug']) : trip_cover($tid);
    $name = ($pg['preferred_name'] ?: $pg['first_name']);
    $title = $name . ' is going to ' . ($t['city'] ?: $t['name']);
    $story = $pg['page_story'] ?: "I'm going on a mission trip to " . $t['name'] . " with " . church_name() . ". Your gift and prayers help make it possible.";
} else {
    $goal = trip_goal($t); $raised = trip_raised($tid);
    $count = (int)val("SELECT COUNT(*) FROM gifts WHERE trip_id = ? AND " . GIFT_COUNTS, [$tid]);
    $photo = trip_cover($tid); $title = 'Send the ' . $t['name'] . ' team';
    $story = $t['description'] ?: 'Help send a team from ' . church_name() . ' to serve in ' . $t['name'] . '.';
    $pages = all("SELECT m.*, p.first_name, p.preferred_name, p.last_name, p.birth_date FROM members m JOIN people p ON p.id = m.person_id WHERE m.trip_id = ? AND m.page_status = 'live' ORDER BY p.first_name", [$tid]);
}
$p = pct($raised, $goal);
// Minors: show the month, not exact travel dates
$when = $pg && !empty($pg['birth_date']) && is_minor($pg, $t['start_date']) ? date('F Y', strtotime($t['start_date'])) : date_range($t['start_date'], $t['end_date']);
public_open($title, ['title' => $title, 'description' => mb_strimwidth(str_replace("\n", ' ', $story), 0, 180, '…'), 'image' => $photo ? (str_starts_with($photo, 'http') ? $photo : site_url($photo)) : null, 'url' => site_url($pg ? '/' . $pg['page_slug'] : '/give/?trip=' . $t['slug'])]);
?>
<main class="pub-main wide" id="main">
  <?php if ($pg && $pg['page_status'] !== 'live'): ?><div class="note"><strong>Preview: <?= e(PAGE_STATUS[$pg['page_status']] ?? '') ?></strong><div class="muted small">Only you and staff can see this until it's approved.</div></div><?php endif; ?>
  <div class="split give">
    <div class="stack-20">
      <?php if ($photo): ?><img src="<?= e($photo) ?>" alt="" class="give-photo" fetchpriority="high"><?php endif; ?>
      <div><div class="muted strong"><?= e($t['public_name'] ?: $t['name']) ?> · <?= e($when) ?></div><h1 class="disp" style="margin:6px 0 0"><?= e($title) ?></h1></div>
      <p class="lead-text"><?= nl2br(e($story)) ?></p>
      <?php if (!$pg && $pages): ?><section><h2 class="gh">Give toward someone's trip</h2><div class="group"><?php foreach ($pages as $m): ?><a class="cell" href="/give/?s=<?= e($m['page_slug']) ?>"><span class="av" aria-hidden="true"><?= e(initials(public_name($m, $t['start_date']))) ?></span><span class="grow"><strong><?= e(public_name($m, $t['start_date'])) ?></strong><span class="muted small block"><?= pct((float)$m['raised'], member_goal($t, $m)) ?>% of <?= money(member_goal($t, $m)) ?></span></span><span class="chev" aria-hidden="true">›</span></a><?php endforeach; ?></div></section><?php endif; ?>
      <section class="note"><strong>Prefer to give by check?</strong><div class="muted small">Make it out to <?= e(church_legal()['name']) ?> and write "<?= e($t['name']) ?> mission" in the memo<?= $pg ? ', with ' . e($name) . "'s name as a preference" : '' ?>. Put it in the offering or mail it to the church office.</div></section>
      <p class="muted small"><?= e(discretion_text()) ?></p>
    </div>

    <aside class="panel sticky give-panel" aria-label="Give">
      <div><div class="disp" style="font-size:40px"><?= money($raised) ?></div><div class="muted">raised of <?= money($goal) ?> · <?= $count ?> gift<?= $count === 1 ? '' : 's' ?></div></div>
      <?= bar(min(100, $p), false, 'Raised so far') ?>
      <form class="form" method="post" data-give>
        <?= csrf() ?>
        <?php if ($err): ?><div class="note error" role="alert"><?= e($err) ?></div><?php endif; ?>
        <fieldset class="amounts"><legend class="lab">Amount</legend>
          <?php foreach (['25', '50', '100', '250'] as $a): ?><label class="opt"><input class="sr" type="radio" name="amount" value="<?= $a ?>"<?= (post('amount') ?? '50') === $a ? ' checked' : '' ?>>$<?= $a ?></label><?php endforeach; ?>
        </fieldset>
        <div class="other-row"><input type="radio" name="amount" value="other" id="amt-other"<?= post('amount') === 'other' ? ' checked' : '' ?>><label class="lab" for="other-amt" style="flex:1">Another amount<input type="number" id="other-amt" name="other" min="5" max="25000" step="1" inputmode="decimal" placeholder="$" value="<?= e(ps('other', 20)) ?>" data-other></label></div>
        <?php if ($t['start_date'] > date('Y-m-d', strtotime('+20 days'))): ?><label class="chk"><input type="checkbox" name="monthly" value="1"<?= post('monthly') ? ' checked' : '' ?>> Give monthly until <?= fdate($t['start_date'], 'F j') ?> (then it stops)</label><?php endif; ?>
        <label class="chk"><input type="checkbox" name="cover" value="1"<?= $_SERVER['REQUEST_METHOD'] === 'POST' && !post('cover') ? '' : ' checked' ?> data-cover> Add the card fee (2.9% + 30¢) so the full gift goes to the trip</label>
        <div class="r2"><label class="lab">First name<input type="text" name="first_name" value="<?= e(ps('first_name', 80)) ?>" required maxlength="80" autocomplete="given-name"></label><label class="lab">Last name<input type="text" name="last_name" value="<?= e(ps('last_name', 80)) ?>" required maxlength="80" autocomplete="family-name"></label></div>
        <label class="lab">Email (for your receipt)<input type="email" name="email" value="<?= e(ps('email', 200)) ?>" required autocomplete="email"></label>
        <label class="lab">A note<?= $pg ? ' for ' . e($name) : '' ?> (optional)<input type="text" name="message" maxlength="400" value="<?= e(ps('message', 400)) ?>"></label>
        <label class="chk"><input type="checkbox" name="anonymous" value="1"<?= post('anonymous') ? ' checked' : '' ?>> Don't show my name to <?= $pg ? e($name) : 'the team' ?></label>
        <label class="hp" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        <?= turnstile_widget() ?>
        <button class="btn btn-primary btn-wide" type="submit" data-give-btn<?= stripe_ready() ? '' : ' disabled' ?>><?= stripe_ready() ? 'Give securely' : 'Online giving opens soon' ?></button>
        <div class="muted small center"><?= stripe_ready() ? 'Card, Apple Pay, Google Pay or bank, through Stripe.' . (stripe_test_mode() ? ' Test mode: no real charges.' : '') : 'Until then, please give by check.' ?> Gifts are tax-deductible as allowed by law.</div>
      </form>
    </aside>
  </div>
</main>
<?php public_close(); ?>
