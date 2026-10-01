<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/member.php';
$p = pct($raised, $goal);
$slug = strtolower(preg_replace('/[^a-z]+/i', '', $me['preferred_name'] ?: $me['first_name']));
$link = 'https://missions.journeychurch.org/' . $slug;
$goals = all('SELECT * FROM goals WHERE trip_id = ? ORDER BY due_date', [$tid]);
$supporters = gifts(['trip' => $tid, 'person' => $me_id], 500);
$to_thank = count(array_filter($supporters, fn($g) => !$g['thanked_at'] && !$g['anonymous']));
$payments = all('SELECT * FROM payments WHERE trip_id = ? AND person_id = ? ORDER BY paid_on', [$tid, $me_id]);
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
    <section><div class="gh"><span>Your supporters</span><span class="muted small"><?= count($supporters) ?> gift<?= count($supporters) === 1 ? '' : 's' ?> · <?= $to_thank ?> to thank</span></div><div class="group">
      <?php foreach ($supporters as $g): $d = $g['donor_id'] && !$g['anonymous'] ? donor((int)$g['donor_id']) : null; ?>
        <div class="cell"><span class="av"><?= $d ? initials(donor_name($d)) : '♥' ?></span>
          <div class="grow"><strong><?= e(gift_from($g)) ?></strong><div class="muted small"><?= fdate($g['gift_date'], 'M j') ?> · <?= money((float)$g['amount']) ?><?= $g['thanked_at'] ? ' · thanked' : '' ?></div></div>
          <?php if ($d && $d['email'] && !$g['thanked_at']): ?><a class="small" href="mailto:<?= e($d['email']) ?>?subject=<?= rawurlencode('Thank you!') ?>&body=<?= rawurlencode('Thank you so much for supporting my trip to ' . $t['name'] . '. It means a lot!') ?>">Email</a><?php endif; ?>
          <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="gift_thank"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="link-btn"><?= $g['thanked_at'] ? 'Undo' : 'Thanked' ?></button></form>
        </div>
      <?php endforeach; ?>
      <?= $supporters ? '' : '<div class="empty">No gifts yet. Share your page to get started.</div>' ?>
    </div></section>
    <div style="display:flex;flex-direction:column;gap:20px">
      <section><div class="gh">Your trip cost</div><div class="group">
        <div class="cell"><span class="grow">Trip cost</span><strong><?= money($goal) ?></strong></div>
        <div class="cell"><span class="grow">Gifts from supporters</span><strong><?= money(member_gifts($tid, $me_id)) ?></strong></div>
        <?php foreach ($payments as $y): ?><div class="cell"><span class="grow">You paid · <?= e(PAYMENT_KINDS[$y['kind']] ?? '') ?> · <?= fdate($y['paid_on'], 'M j') ?></span><strong><?= $y['kind'] === 'refund' ? '−' : '' ?><?= money((float)$y['amount']) ?></strong></div><?php endforeach; ?>
        <div class="cell" style="background:var(--tint)"><span class="grow"><strong>Still needed</strong></span><strong><?= money(max(0, $goal - $raised)) ?></strong></div>
      </div></section>
      <section class="note"><strong>A tip from your leader</strong><div class="muted">Text your page to five people this week with one line about why you're going. Personal asks raise the most.</div></section>
    </div>
  </div>
</main>
<?php page_close(); ?>
