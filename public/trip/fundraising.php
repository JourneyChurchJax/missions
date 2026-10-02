<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/member.php';
$p = pct($raised, $goal);
if (!$mem['page_slug']) { update('members', (int)$mem['id'], ['page_slug' => unique_page_slug($me), 'page_status' => 'draft']); $mem = member_of($tid, $me_id); }
$link = page_url($mem);
$live = $mem['page_status'] === 'live';
$goals = all('SELECT * FROM goals WHERE trip_id = ? ORDER BY due_date', [$tid]);
$supporters = gifts(['trip' => $tid, 'person' => $me_id], 500);
$to_thank = count(array_filter($supporters, fn($g) => !$g['thanked_at'] && !$g['anonymous']));
$payments = all('SELECT * FROM payments WHERE trip_id = ? AND person_id = ? ORDER BY paid_on', [$tid, $me_id]);
page_open('Fundraising');
member_header('fund');
?>
<main class="main m" id="main">
  <div class="head"><div class="sub"><div class="muted" style="font-size:15px;font-weight:600"><?= e($t['name']) ?> · <?= e(date_range($t['start_date'], $t['end_date'])) ?></div><h1 class="disp">Fundraising</h1></div></div>

  <section class="split wide-left">
    <div class="tile dark" style="padding:32px;flex-direction:row;gap:32px;align-items:center;flex-wrap:wrap">
      <?= ring($p, 100, 160, true, $p . '%') ?>
      <div style="display:flex;flex-direction:column;gap:6px"><div class="k">You've raised</div><div class="disp" style="font-size:68px"><?= money($raised) ?></div><div style="color:rgba(247,244,240,.85)">of <?= money($goal) ?> · <?= money(max(0, $goal - $raised)) ?> to go</div></div>
    </div>
    <div class="panel" style="border-radius:var(--r-xl);padding:28px;gap:14px">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><strong style="font-size:18px">Your fundraising page</strong><span class="pill<?= $live ? ' pill-ok' : '' ?>"><?= e(PAGE_STATUS[$mem['page_status']] ?? 'Not shared yet') ?></span></div>
      <div style="background:var(--sand);border-radius:var(--r-sm);padding:12px 14px;display:flex;justify-content:space-between;gap:12px;font-size:15px"><span><?= e(str_replace('https://', '', $link)) ?></span><a href="/give/?s=<?= e($mem['page_slug']) ?>" target="_blank" style="font-weight:600">Preview</a></div>
      <?php if ($live): ?>
        <div class="chips" style="gap:8px"><a class="pill" style="height:32px;padding:0 14px" href="sms:?&body=<?= rawurlencode("I'm going to " . $t['name'] . ' with Journey Church. Here is my page: ' . $link) ?>">Text it</a><a class="pill" style="height:32px;padding:0 14px" href="mailto:?subject=<?= rawurlencode('My ' . $t['name'] . ' mission trip') ?>&body=<?= rawurlencode($link) ?>">Email</a><button type="button" class="pill" style="height:32px;padding:0 14px;border:0;cursor:pointer" data-qr="<?= e($link) ?>">QR code</button></div>
        <a class="btn btn-primary btn-wide" href="#" data-copy="<?= e($link) ?>">Copy my link</a>
      <?php else: ?>
        <div class="muted small"><?= $mem['page_status'] === 'pending' ? 'Your leader will take a quick look, then it goes live.' : ($mem['page_status'] === 'hidden' ? 'Your leader hid this page. Talk with them about it.' : 'Write a few lines about why you\'re going, add a photo, then share it.') ?></div>
      <?php endif; ?>
      <div class="muted small"><?= stripe_ready() ? 'People can give by card, Apple Pay or bank.' : 'Online giving opens once the church connects Stripe. Until then your page shows how to give by check.' ?></div>
    </div>
  </section>

  <details class="add"<?= $mem['page_status'] === 'draft' ? ' open' : '' ?>><summary>Edit your page</summary><div class="body">
    <form class="form" method="post" action="/action.php" enctype="multipart/form-data"><?= csrf() ?><input type="hidden" name="action" value="page_save"><input type="hidden" name="trip_id" value="<?= $tid ?>">
      <label class="lab">Why you're going<textarea name="page_story" rows="5" maxlength="3000" placeholder="I'm going to <?= e($t['name']) ?> to..."><?= e($mem['page_story']) ?></textarea></label>
      <div class="r2"><label class="lab">Page address<span style="display:flex;align-items:center;gap:6px"><span class="muted small">missions.journeychurch.org/</span><input type="text" name="page_slug" value="<?= e($mem['page_slug']) ?>" pattern="[A-Za-z0-9]{3,40}"></span></label>
        <label class="lab">Photo of you (optional)<input type="file" name="page_photo" accept="image/*"></label></div>
      <div class="actions"><button class="btn" type="submit">Save</button><?php if ($mem['page_status'] === 'draft'): ?><button class="btn btn-primary" type="submit" name="publish" value="1">Save and share it</button><?php endif; ?></div>
    </form></div></details>

  <section class="tile" style="flex-direction:row;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div><strong>Pay toward your trip</strong><div class="muted small"><?= isset($_GET['paid']) ? 'Thanks! Your payment shows here once the bank confirms it, usually within a minute.' : 'Money you pay yourself counts toward your total. It is not a tax-deductible gift.' ?></div></div>
    <form method="post" action="/action.php" style="display:flex;gap:8px;align-items:center"><?= csrf() ?><input type="hidden" name="action" value="stripe_pay"><input type="hidden" name="trip_id" value="<?= $tid ?>">
      <input class="pill" type="number" name="amount" min="5" step="1" value="<?= (int)min(max(5, $goal - $raised), 500) ?>" style="width:110px" aria-label="Amount"><button class="btn btn-dark" type="submit"<?= stripe_ready() ? '' : ' disabled title="Online payments open once Stripe is connected"' ?>>Pay online</button></form>
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
        <div class="cell"><span class="av" aria-hidden="true"><?= $d ? e(initials(donor_name($d))) : '♥' ?></span>
          <div class="grow"><strong><?= e(gift_from($g)) ?></strong><div class="muted small"><?= fdate($g['gift_date'], 'M j') ?> · <?= money((float)$g['amount']) ?><?= $g['thanked_at'] ? ' · thanked' : '' ?></div></div>
          <?php if ($d && $d['email'] && !$g['thanked_at']): ?><a class="small" href="mailto:<?= e($d['email']) ?>?subject=<?= rawurlencode('Thank you!') ?>&body=<?= rawurlencode('Thank you so much for supporting my trip to ' . $t['name'] . '. It means a lot!') ?>">Email</a><?php endif; ?>
          <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="gift_thank"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="link-btn"><?= $g['thanked_at'] ? 'Undo' : 'Mark thanked' ?></button></form>
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
<dialog id="qr" style="border:0;border-radius:var(--r-xl);padding:28px;box-shadow:var(--sh-xl);text-align:center"><div id="qrbox"></div><p class="muted small">Print it or put it on a slide.</p><button class="btn" onclick="this.closest('dialog').close()">Close</button></dialog>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js" integrity="sha512-ZDSPMa/JM1D+7kdg2x3BsruQ6T/JpJo3jWDWkCZsP+5yVyp1KfESqLI+7RqB5k24F7p2cV7i2YHh/890y6P6Sw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>document.querySelectorAll('[data-qr]').forEach(b => b.addEventListener('click', () => { const q = qrcode(0, 'M'); q.addData(b.dataset.qr); q.make(); document.getElementById('qrbox').innerHTML = q.createSvgTag({ cellSize: 6, margin: 2 }); document.getElementById('qr').showModal(); }));</script>
<?php page_close(); ?>
