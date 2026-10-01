<?php
// Public fundraising page. missions.journeychurch.org/thomas (rewritten to ?s=thomas) or /give/?trip=belize for the whole team.
require dirname(__DIR__) . '/inc/bootstrap.php';
global $config;

$slug = strtolower(preg_replace('/[^a-z0-9-]/i', '', (string)($_GET['s'] ?? '')));
$pg = $slug !== '' ? page_by_slug($slug) : null;
$staff_view = !empty($_SESSION['preview_ok']) || !empty($_SESSION['auth']['staff']);
$own = $pg && (int)($_SESSION['person_id'] ?? 0) === (int)$pg['person_id'] && ($_SESSION['view'] ?? '') === 'traveler';
if ($pg && $pg['page_status'] !== 'live' && !$staff_view && !$own) $pg = null;
$team = !$pg && !empty($_GET['trip']) ? one("SELECT * FROM trips WHERE slug = ? AND status = 'active'", [strtolower((string)$_GET['trip'])]) : null;
if (!$pg && !$team && $slug !== '') $team = null;
$t = $pg ? trip((int)$pg['trip_id']) : $team;
$err = '';

if ($t && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $amt = round((float)str_replace([',', '$'], '', (string)(post('amount') === 'other' ? post('other') : post('amount'))), 2);
    $email = strtolower(trim((string)post('email')));
    if (post('website')) { header('Location: ' . $_SERVER['REQUEST_URI']); exit; }
    if ($amt < 5 || $amt > 25000) $err = 'Gifts can be from $5 to $25,000.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Add your email so we can send your receipt.';
    elseif (!post('first_name') || !post('last_name')) $err = 'Add your name for your tax receipt.';
    elseif (!stripe_ready()) $err = 'Online giving opens soon. You can give by check today (see below).';
    else {
        $charge = post('cover') ? with_fee_covered($amt) : $amt;
        $back = site_url($pg ? '/' . $pg['page_slug'] : '/give/?trip=' . $t['slug']);
        try {
            $url = stripe_checkout('gift', $charge, ($pg ? 'Gift for ' . full_name($pg) . ' · ' : '') . $t['name'] . ' mission trip', [
                'trip_id' => $t['id'], 'person_id' => $pg['person_id'] ?? '', 'first' => post('first_name'), 'last' => post('last_name'),
                'anonymous' => post('anonymous') ? '1' : '', 'message' => mb_substr((string)post('message'), 0, 400)],
                site_url('/give/thanks.php?' . ($pg ? 's=' . $pg['page_slug'] : 'trip=' . $t['slug'])), $back, $email, (bool)post('monthly'));
            header('Location: ' . $url); exit;
        } catch (Throwable $e) { $err = 'We couldn\'t start the payment. Please try again in a minute.'; }
    }
}

if (!$t) { public_open('Give'); ?>
<main class="pub-main"><section class="tile xl" style="padding:36px;text-align:center;gap:10px"><h1 class="disp">This page isn't available.</h1><p class="muted" style="margin:0">Check the link, or give to Journey Church missions at journeychurch.org.</p></section></main></body></html>
<?php exit; }

$tid = (int)$t['id'];
if ($pg) {
    $goal = member_goal($t, $pg); $raised = (float)$pg['raised'];
    $count = (int)val("SELECT COUNT(*) FROM gifts WHERE trip_id = ? AND person_id = ? AND status = 'cleared'", [$tid, $pg['person_id']]);
    $photo = $pg['page_photo_id'] ? '/give/photo.php?s=' . urlencode($pg['page_slug']) : trip_cover($tid);
    $name = $pg['preferred_name'] ?: $pg['first_name'];
    $title = $name . ' is going to ' . ($t['city'] ?: $t['name']);
    $story = $pg['page_story'] ?: "I'm going on a mission trip to " . $t['name'] . " with Journey Church. Your gift and prayers help make it possible.";
} else {
    $goal = trip_goal($t); $raised = trip_raised($tid);
    $count = (int)val("SELECT COUNT(*) FROM gifts WHERE trip_id = ? AND status = 'cleared'", [$tid]);
    $photo = trip_cover($tid); $title = 'Send the ' . $t['name'] . ' team';
    $story = $t['description'] ?: 'Help send a team from Journey Church to serve in ' . $t['name'] . '.';
    $pages = all("SELECT m.*, p.first_name, p.preferred_name, p.last_name FROM members m JOIN people p ON p.id = m.person_id WHERE m.trip_id = ? AND m.page_status = 'live' ORDER BY p.first_name", [$tid]);
}
$p = pct($raised, $goal);
public_open($title);
?>
<main class="pub-main" style="max-width:1040px">
  <?php if ($pg && $pg['page_status'] !== 'live'): ?><div class="note"><strong>Preview: <?= e(PAGE_STATUS[$pg['page_status']] ?? '') ?></strong><div class="muted small">Only you and staff can see this until it's approved.</div></div><?php endif; ?>
  <div class="split" style="grid-template-columns:minmax(0,1.3fr) minmax(320px,1fr);gap:28px;align-items:start">
    <div style="display:flex;flex-direction:column;gap:20px;min-width:0">
      <?php if ($photo): ?><img src="<?= e($photo) ?>" alt="" style="width:100%;aspect-ratio:3/2;object-fit:cover;border-radius:var(--r-xl);box-shadow:var(--sh-md)"><?php endif; ?>
      <div><div class="muted" style="font-weight:600"><?= e($t['public_name'] ?: $t['name']) ?> · <?= e(date_range($t['start_date'], $t['end_date'])) ?></div><h1 class="disp" style="margin:6px 0 0"><?= e($title) ?></h1></div>
      <p style="margin:0;font-size:18px;line-height:1.6"><?= nl2br(e($story)) ?></p>
      <?php if (!$pg && $pages): ?><section><div class="gh">Give to someone on the team</div><div class="group"><?php foreach ($pages as $m): ?><a class="cell" href="/give/?s=<?= e($m['page_slug']) ?>"><span class="av"><?= initials(full_name($m)) ?></span><span class="grow"><strong><?= e(full_name($m)) ?></strong><span class="muted small" style="display:block"><?= pct((float)$m['raised'], member_goal($t, $m)) ?>% of <?= money(member_goal($t, $m)) ?></span></span><span class="chev">›</span></a><?php endforeach; ?></div></section><?php endif; ?>
      <section class="note"><strong>Prefer to give by check?</strong><div class="muted small">Make it out to Journey Church and write "<?= e($t['name']) ?> mission<?= $pg ? ' · ' . e(full_name($pg)) : '' ?>" in the memo. Drop it in the offering or mail it to the church office.</div></section>
    </div>

    <aside class="panel sticky" style="border-radius:var(--r-xl);padding:28px;gap:16px">
      <div><div class="disp" style="font-size:40px"><?= money($raised) ?></div><div class="muted">raised of <?= money($goal) ?> · <?= $count ?> gift<?= $count === 1 ? '' : 's' ?></div></div>
      <?= bar(min(100, $p)) ?>
      <form class="form" method="post" style="gap:14px">
        <?= csrf() ?>
        <?php if ($err): ?><div class="note" style="box-shadow:inset 3px 0 0 var(--ember)"><?= e($err) ?></div><?php endif; ?>
        <div class="seg" role="radiogroup" aria-label="Amount" style="display:grid;grid-template-columns:repeat(4,1fr)">
          <?php foreach (['25', '50', '100', '250'] as $i => $a): ?><label class="opt" style="text-align:center"><input class="sr" type="radio" name="amount" value="<?= $a ?>"<?= (post('amount') ?? '50') === $a ? ' checked' : '' ?>>$<?= $a ?></label><?php endforeach; ?>
        </div>
        <label class="lab">Or another amount<span style="display:flex;gap:8px;align-items:center"><input type="radio" name="amount" value="other" aria-label="Another amount"<?= post('amount') === 'other' ? ' checked' : '' ?>><input type="number" name="other" min="5" step="1" placeholder="$" value="<?= e(post('other') ?? '') ?>" data-other></span></label>
        <label class="chk"><input type="checkbox" name="monthly" value="1"<?= post('monthly') ? ' checked' : '' ?>> Make it monthly until the trip</label>
        <label class="chk"><input type="checkbox" name="cover" value="1" checked> Add 2.9% + 30¢ so the card fee doesn't come out of the gift</label>
        <div class="r2"><label class="lab">First name<input type="text" name="first_name" value="<?= e(post('first_name') ?? '') ?>" required autocomplete="given-name"></label><label class="lab">Last name<input type="text" name="last_name" value="<?= e(post('last_name') ?? '') ?>" required autocomplete="family-name"></label></div>
        <label class="lab">Email (for your receipt)<input type="email" name="email" value="<?= e(post('email') ?? '') ?>" required autocomplete="email"></label>
        <label class="lab">A note<?= $pg ? ' for ' . e($name) : '' ?> (optional)<input type="text" name="message" maxlength="400" value="<?= e(post('message') ?? '') ?>"></label>
        <label class="chk"><input type="checkbox" name="anonymous" value="1"<?= post('anonymous') ? ' checked' : '' ?>> Don't show my name to <?= $pg ? e($name) : 'the team' ?></label>
        <label class="hp" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        <button class="btn btn-primary btn-wide" type="submit"<?= stripe_ready() ? '' : ' disabled' ?>><?= stripe_ready() ? 'Give securely' : 'Online giving opens soon' ?></button>
        <div class="muted small" style="text-align:center"><?= stripe_ready() ? 'Card, Apple Pay, Google Pay or bank. Payments are handled by Stripe.' . (stripe_test_mode() ? ' (Test mode: no real charges.)' : '') : 'Until then, please give by check.' ?> Gifts go to Journey Church and are tax-deductible.</div>
      </form>
    </aside>
  </div>
</main>
<script src="/assets/app.js?v=6"></script>
</body>
</html>
