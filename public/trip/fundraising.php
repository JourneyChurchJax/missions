<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/member.php';
$p = pct($raised, $goal);
$slug = strtolower(preg_replace('/[^a-z]+/i', '', $me['preferred_name'] ?: $me['first_name']));
$link = 'https://missions.journeychurch.org/' . $slug;
$goals = all('SELECT * FROM goals WHERE trip_id = ? ORDER BY due_date', [$tid]);
page_open('Fundraising');
member_header('fund');
?>
<main class="main m">
  <div class="head"><div class="sub"><div class="muted" style="font-size:15px;font-weight:600"><?= e($t['name']) ?> · <?= e(date_range($t['start_date'], $t['end_date'])) ?></div><h1 class="disp">Fundraising</h1></div></div>

  <section class="split" style="grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:20px">
    <div class="tile dark" style="padding:32px;flex-direction:row;gap:32px;align-items:center;flex-wrap:wrap">
      <?= ring($p, 100, 160, true, $p . '%') ?>
      <div style="display:flex;flex-direction:column;gap:6px"><div class="k">You've raised</div><div class="disp" style="font-size:68px"><?= money($raised) ?></div><div style="color:rgba(247,244,240,.85)">of <?= money($goal) ?> · <?= money(max(0, $goal - $raised)) ?> to go</div></div>
    </div>
    <div class="panel" style="border-radius:var(--r-xl);padding:28px;gap:14px">
      <strong style="font-size:18px">Share your page</strong>
      <div style="background:var(--sand);border-radius:var(--r-sm);padding:12px 14px;display:flex;justify-content:space-between;gap:12px;font-size:15px"><span><?= e(str_replace('https://', '', $link)) ?></span><a href="#" data-copy="<?= e($link) ?>" style="font-weight:600">Copy</a></div>
      <div class="chips" style="gap:8px"><a class="pill" style="height:32px;padding:0 14px" href="sms:?&body=<?= rawurlencode("I'm going to " . $t['name'] . ' with Journey Church. Here is my page: ' . $link) ?>">Text it</a><a class="pill" style="height:32px;padding:0 14px" href="mailto:?subject=<?= rawurlencode('My ' . $t['name'] . ' mission trip') ?>&body=<?= rawurlencode($link) ?>">Email</a><a class="pill" style="height:32px;padding:0 14px" href="#" data-say="QR codes arrive with fundraising pages in phase 5">QR code</a></div>
      <a class="btn btn-primary btn-wide" href="#" data-copy="<?= e($link) ?>">Share my page</a>
      <div class="muted small">Your public page and online giving go live in phase 5 with Stripe.</div>
    </div>
  </section>

  <section><div class="gh">Milestones</div>
    <div class="group g3" style="gap:0">
    <?php foreach ($goals as $i => $g): $need = $g['kind'] === 'percent' ? $goal * $g['amount'] / 100 : (float)$g['amount']; ?>
      <div style="padding:20px 18px;display:flex;flex-direction:column;gap:6px;<?= $i ? 'box-shadow:inset 1px 0 0 var(--sand)' : '' ?>">
        <div class="muted small" style="font-weight:600"><?= fdate($g['due_date'], 'F j') ?></div><strong style="font-size:18px"><?= $g['kind'] === 'percent' ? (int)$g['amount'] . '% · ' : '' ?><?= money($need) ?></strong>
        <?= bar(min(100, pct($raised, $need))) ?><div class="muted small"><?= $raised >= $need ? 'Done' : money($need - $raised) . ' to go' ?></div>
      </div>
    <?php endforeach; ?>
    </div>
  </section>

  <div class="g2" style="gap:28px;align-items:start">
    <section><div class="gh">Your supporters</div><div class="group">
      <div class="empty">Gifts show here once online giving is connected. You'll be able to thank each person with one tap.</div>
    </div></section>
    <section class="note"><strong>A tip from your leader</strong><div class="muted">Text your page to five people this week with one line about why you're going. Personal asks raise the most.</div></section>
  </div>
</main>
<?php page_close(); ?>
