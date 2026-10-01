<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
$t = $trips[$me['trip']];
page_open('Fundraising');
member_header('fund');
$p = pct($me['raised'], $me['goal']);
$link = 'https://missions.journeychurch.org/thomas';
?>
<main class="main m">
  <div class="head"><div class="sub"><div class="muted" style="font-size:15px;font-weight:600"><?= e($t['name']) ?> · <?= e($t['dates']) ?></div><h1 class="disp">Fundraising</h1></div></div>

  <section class="split" style="grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:20px">
    <div class="tile dark" style="padding:32px;flex-direction:row;gap:32px;align-items:center;flex-wrap:wrap">
      <?= ring($p, 100, 168, true, $p . '%') ?>
      <div style="display:flex;flex-direction:column;gap:6px"><div class="k">You've raised</div><div class="disp" style="font-size:72px"><?= money($me['raised']) ?></div><div style="color:rgba(247,244,240,.85)">of <?= money($me['goal']) ?> · 12 people have given</div></div>
    </div>
    <div class="panel" style="border-radius:var(--r-xl);padding:28px;gap:14px">
      <strong style="font-size:18px">Share your page</strong>
      <div style="background:var(--sand);border-radius:var(--r-sm);padding:12px 14px;display:flex;justify-content:space-between;gap:12px;font-size:15px"><span>missions.journeychurch.org/thomas</span><a href="#" data-copy="<?= e($link) ?>" style="font-weight:600">Copy</a></div>
      <div class="chips" style="gap:8px"><a class="pill" style="height:32px;padding:0 14px" href="sms:?&body=<?= rawurlencode('I\'m going to Belize with Journey Church. Here\'s my page: ' . $link) ?>">Text it</a><a class="pill" style="height:32px;padding:0 14px" href="mailto:?subject=<?= rawurlencode('My Belize mission trip') ?>&body=<?= rawurlencode($link) ?>">Email</a><a class="pill" style="height:32px;padding:0 14px" href="#" data-say="Facebook sharing is coming next">Facebook</a><a class="pill" style="height:32px;padding:0 14px" href="#" data-say="QR codes are coming next">QR code</a></div>
      <a class="btn btn-primary btn-wide" href="#" data-copy="<?= e($link) ?>">Share my page</a>
      <a href="#" data-say="Page editing is coming next" style="text-align:center;font-weight:600;font-size:15px">Edit my page</a>
    </div>
  </section>

  <section><div class="gh">Milestones</div>
    <div class="group g3" style="gap:0">
    <?php foreach ($milestones as $i => [$when, $mp, $amt]):
      $done = $me['raised'] >= $amt; $fill = min(100, pct($me['raised'], $amt)); ?>
      <div style="padding:20px 18px;display:flex;flex-direction:column;gap:6px;<?= $i ? 'box-shadow:inset 1px 0 0 var(--sand)' : '' ?>">
        <div class="muted small" style="font-weight:600"><?= e($when) ?></div><strong style="font-size:18px"><?= $mp ?>% · <?= money($amt) ?></strong>
        <?= bar($fill) ?><div class="muted small"><?= $done ? 'Done' : money($amt - $me['raised']) . ' to go' ?></div>
      </div>
    <?php endforeach; ?>
    </div>
  </section>

  <div class="g2" style="gap:28px;align-items:start">
    <section><div class="gh">Your supporters</div><div class="group">
      <div class="cell"><span class="av">ML</span><div class="grow"><strong>Maria L.</strong><div class="muted small">$100 · September 30</div></div><a class="pill" style="height:32px;padding:0 14px" href="#" data-say="Thank-you note sent (preview only)">Say thanks</a></div>
      <div class="cell"><span class="av">TD</span><div class="grow"><strong>The Daltons</strong><div class="muted small">$25 a month · since August</div></div><span class="muted small">Thanked</span></div>
      <div class="cell"><span class="av">—</span><div class="grow"><strong>Anonymous</strong><div class="muted small">$50 · September 25</div></div><span class="muted small">Private</span></div>
      <a class="cell" href="#" data-say="Full supporter list is coming next" style="font-weight:600;font-size:15px">See all 12</a>
    </div></section>
    <section style="display:flex;flex-direction:column;gap:20px">
      <div><div class="gh">Post an update</div>
        <form class="tile" style="gap:12px" onsubmit="event.preventDefault();this.querySelector('textarea').value='';var t=document.querySelector('.toast');t.textContent='Posted (preview only)';t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2200)">
          <label class="muted small" for="upd">Supporters get it by email and see it on your page</label>
          <textarea id="upd" class="field" rows="3" placeholder="Passport is renewed. One thing off the list."></textarea>
          <div style="display:flex;justify-content:space-between;align-items:center"><a href="#" data-say="Photos are coming next">Add a photo</a><button class="btn btn-dark" style="height:36px" type="submit">Post</button></div>
        </form>
      </div>
      <div class="note"><strong>A tip from your leader</strong><div class="muted">Text your page to five people this week with one line about why you're going. Personal asks raise the most.</div></div>
    </section>
  </div>
</main>
<?php page_close(); ?>
