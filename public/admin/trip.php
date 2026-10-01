<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
$slug = $_GET['t'] ?? 'belize';
$t = $trips[$slug] ?? $trips['belize'];
$is_belize = $t['slug'] === 'belize';
page_open($t['name']);
admin_header('trips');
?>
<main class="main" style="padding-top:32px">
  <section class="hero">
    <span class="ghost" style="right:-30px;bottom:-60px;font-size:240px"><?= e($t['ghost']) ?></span>
    <a href="/admin/" style="position:relative;color:var(--muted-dark);font-size:14px;text-decoration:none">‹ All trips</a>
    <div style="position:relative;display:flex;justify-content:space-between;align-items:flex-end;gap:24px;flex-wrap:wrap">
      <div style="display:flex;flex-direction:column;gap:8px">
        <div style="color:var(--muted-dark);font-size:15px;font-weight:600"><?= e($t['dates']) ?> · <?= $t['days_away'] ?> days away</div>
        <h1 class="disp" style="margin:0;font-size:64px"><?= e($t['name']) ?></h1>
        <div style="color:rgba(247,244,240,.85)"><?= e($t['city']) ?> · with <?= e($t['partner']) ?> · led by <?= e($t['leader']) ?></div>
      </div>
      <div style="display:flex;gap:12px">
        <a class="btn" style="background:rgba(247,244,240,.14);color:var(--cream)" href="#" data-say="Trip editing is coming next">Edit trip</a>
        <a class="btn btn-primary" href="/trip/messages.php">Email the team</a>
      </div>
    </div>
  </section>

  <div class="center"><nav class="seg" data-choice aria-label="Trip sections">
    <?php foreach (['Overview','Team','Tasks & goals','Meetings','Documents','Travel','Budget','Giving','Updates'] as $i => $s): ?>
      <button type="button" class="tab<?= $i === 0 ? ' on' : '' ?>"><?= e($s) ?></button>
    <?php endforeach; ?>
  </nav></div>

  <section class="g5">
    <div class="tile"><div class="k">Team</div><div class="disp" style="font-size:36px"><?= $t['travelers'] ?></div><div class="muted small"><?= $t['travelers'] ?> traveling · max <?= $t['max'] ?></div></div>
    <div class="tile"><div class="k">Ready to go</div><div class="disp" style="font-size:36px"><?= $t['ready'] ?> of <?= $t['travelers'] ?></div><div class="muted small">Every step done</div></div>
    <div class="tile"><div class="k">Tasks done</div><div class="disp" style="font-size:36px"><?= pct($t['tasks_done'], $t['tasks_total']) ?>%</div><div class="muted small"><?= $t['tasks_done'] ?> of <?= $t['tasks_total'] ?></div></div>
    <div class="tile"><div class="k">Budget</div><div class="disp" style="font-size:36px"><?= money($t['budget']) ?></div><div class="muted small"><?= money($t['spent']) ?> spent</div></div>
    <div class="tile"><div class="k">Raised</div><div class="disp" style="font-size:36px"><?= money($t['raised']) ?></div><div class="muted small">of <?= money($t['goal']) ?></div></div>
  </section>

  <div class="split">
    <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
      <section>
        <div class="gh"><span>Team readiness</span><span style="display:flex;gap:18px"><a href="/admin/people.php">Add people</a><a href="/admin/reports.php">Roster PDF</a><a href="/admin/reports.php">STEP export</a></span></div>
        <div class="group tbl">
          <table>
            <thead><tr><th>Person</th><th>Role</th><th>Passport</th><th>Forms</th><th>Contacts &amp; medical</th><th>Tasks</th><th class="num">Raised</th></tr></thead>
            <tbody>
            <?php foreach ($is_belize ? $team : [] as $p): ?>
              <tr>
                <td><div class="who"><span class="av"><?= initials($p['name']) ?></span><?= e($p['name']) ?></div></td>
                <td><?= e($p['role']) ?></td>
                <td><span class="pill<?= $p['passport_ok'] ? ' pill-ok' : '' ?>"><?= e($p['passport']) ?></span></td>
                <td><span class="pill"><?= e($p['forms']) ?></span></td>
                <td><span class="pill<?= $p['medical_ok'] ? ' pill-ok' : '' ?>"><?= e($p['medical']) ?></span></td>
                <td><?= e($p['tasks']) ?></td>
                <td class="num"><strong><?= money($p['raised']) ?></strong> <span class="muted small">/ <?= money($p['goal']) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$is_belize): ?><tr><td colspan="7" class="muted">[Sample trip. Team list comes from the database once it's connected.]</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="muted small" style="padding:8px 18px 0">Passports must be valid through December 25, 2027. Profiles sync from Planning Center.</div>
      </section>

      <section>
        <div class="gh">Coming up</div>
        <div class="group">
          <div class="cell"><?= date_box('NOV', '9') ?><div class="grow"><strong>Team meeting</strong><div class="muted small">12:30 PM · [Room]</div></div><a class="small" href="#" data-say="Attendance opens on the day of the meeting">Take attendance</a></div>
          <div class="cell"><?= date_box('NOV', '15') ?><div class="grow"><strong>Passport copies due</strong><div class="muted small">Upload task · 1 of 2 in</div></div><a class="small" href="#" data-say="Reminder sent to Corey (preview only)">Remind Corey</a></div>
          <div class="cell"><?= date_box('DEC', '19') ?><div class="grow"><strong>10% of fundraising due</strong><div class="muted small">Goal · both on track</div></div><span class="chev">›</span></div>
          <div class="cell"><?= date_box('FEB', '7') ?><div class="grow"><strong>50% of fundraising due</strong><div class="muted small">Goal</div></div><span class="chev">›</span></div>
        </div>
      </section>

      <?php if ($is_belize): ?>
      <section>
        <div class="gh"><span>Budget</span><a href="#" data-say="Budget editing is coming next">Edit budget</a></div>
        <div class="group">
        <?php foreach ($budget_lines as [$what, $vendor, $amt]): ?>
          <div class="cell"><div class="grow"><?= e($what) ?> <span class="muted">· <?= e($vendor) ?></span></div><strong><?= money($amt) ?></strong></div>
        <?php endforeach; ?>
          <div class="cell" style="background:var(--tint)"><div class="grow"><strong>Total · <?= money($t['budget'] / max(1, $t['travelers'])) ?> a person</strong></div><strong><?= money($t['budget']) ?></strong></div>
        </div>
      </section>
      <?php endif; ?>
    </div>

    <aside class="sticky" style="display:flex;flex-direction:column;gap:24px">
      <section>
        <div class="gh">Needs your attention</div>
        <div class="group">
          <a class="cell" href="#" data-say="Goal editing is coming next"><span class="dot"></span><div class="grow"><strong>Goal is <?= money($t['per_person']) ?> a person</strong><div class="muted small">The budget works out to <?= money($t['budget'] / max(1, $t['travelers'])) ?></div></div><span class="chev">›</span></a>
          <a class="cell" href="#" data-say="Page approval is coming next"><span class="dot"></span><div class="grow"><strong>2 pages to approve</strong><div class="muted small">Fundraising pages</div></div><span class="chev">›</span></a>
          <a class="cell" href="#" data-say="Guide sent (preview only)"><span class="dot"></span><div class="grow"><strong>Corey has no passport</strong><div class="muted small">Send the passport guide</div></div><span class="chev">›</span></a>
        </div>
      </section>
      <section>
        <div class="gh">Documents</div>
        <div class="group">
        <?php foreach ($documents as [$name, $type]): ?>
          <a class="cell" href="#" data-say="Files open once uploads are connected"><?= e($name) ?><span class="muted small" style="margin-left:auto"><?= e($type) ?></span></a>
        <?php endforeach; ?>
          <a class="cell" href="#" data-say="Files open once uploads are connected">Missions code of conduct<span class="muted small" style="margin-left:auto">Sign</span></a>
          <a class="cell" href="#" data-say="Uploads are coming next" style="font-weight:600;font-size:15px">Upload a document</a>
        </div>
      </section>
    </aside>
  </div>
</main>
<?php page_close(); ?>
