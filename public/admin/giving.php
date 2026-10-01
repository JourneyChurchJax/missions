<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
page_open('Giving');
admin_header('giving');
?>
<main class="main">
  <div class="head">
    <div class="sub"><h1 class="disp">Giving</h1><div class="muted">Online gifts through Stripe, plus checks and cash you enter by hand</div></div>
    <div style="display:flex;gap:12px"><a class="btn" href="#" data-say="Exports are coming next">Export</a><a class="btn btn-primary" href="#" data-say="Manual gift entry is coming next">Add a check or cash gift</a></div>
  </div>

  <section class="g4 lead">
    <div class="tile dark" style="padding:26px"><div class="k">Raised this season</div><div class="disp" style="font-size:64px"><?= money(array_sum(array_column($trips, 'raised'))) ?></div><div style="color:rgba(247,244,240,.85);font-size:15px">Across Israel and Belize</div></div>
    <div class="tile"><div class="k">This week</div><div class="disp v">$1,420</div><div class="muted small">14 gifts</div></div>
    <div class="tile"><div class="k">Monthly givers</div><div class="disp v">11</div><div class="muted small">$640 a month</div></div>
    <div class="tile"><div class="k">Fees donors covered</div><div class="disp v">82%</div><div class="muted small">of card gifts</div></div>
  </section>

  <div class="seg" data-choice aria-label="View" style="align-self:flex-start">
    <button type="button" class="tab on">All gifts</button><button type="button" class="tab">By trip</button><button type="button" class="tab">By person</button><button type="button" class="tab">Monthly</button><button type="button" class="tab">Deposits</button><button type="button" class="tab">Donors</button>
  </div>

  <div class="split" style="grid-template-columns:minmax(0,1fr) 360px">
    <section class="group tbl">
      <table>
        <thead><tr><th>Date</th><th>Donor</th><th>For</th><th>Method</th><th>Fee</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($gifts as [$d, $who, $for, $how, $fee, $amt]): ?>
          <tr><td><?= e($d) ?></td><td><strong><?= e($who) ?></strong></td><td><?= e($for) ?></td><td class="muted"><?= e($how) ?></td><td class="muted"><?= e($fee) ?></td><td class="num"><strong><?= money($amt, 2) ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="muted small" style="padding:14px 18px;box-shadow:inset 0 1px 0 var(--sand)">Sample gifts · each gift is credited to a person or the whole team the moment it clears</div>
    </section>
    <aside style="display:flex;flex-direction:column;gap:24px">
      <section><div class="gh">Connected</div><div class="group">
        <div class="cell"><div class="grow"><strong>Stripe</strong><div class="muted small">Cards, Apple Pay, bank · USD</div></div><span class="pill">Not yet</span></div>
        <div class="cell"><div class="grow"><strong>Planning Center Giving</strong><div class="muted small">Gifts will post nightly to a Missions fund</div></div><span class="pill">Not yet</span></div>
      </div></section>
      <section><div class="gh">Recent Stripe payouts</div><div class="group">
        <div class="cell"><span class="grow">[Payout date]</span><strong>[Amount]</strong></div>
        <div class="cell"><span class="grow">[Payout date]</span><strong>[Amount]</strong></div>
        <a class="cell" href="#" data-say="Payouts show once Stripe is connected" style="font-weight:600;font-size:15px">All payouts</a>
      </div></section>
      <section class="note"><strong>Year-end statements</strong><div class="muted">Statements go out in January by email, with printed copies for anyone without one.</div><a href="#" data-say="Statements are coming next" style="font-weight:600">Prepare 2026 statements ›</a></section>
    </aside>
  </div>
</main>
<?php page_close(); ?>
