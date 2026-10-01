<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
page_open('Reports');
admin_header('reports');
$groups = [
  'Before the trip' => [
    ['Team readiness', 'Who is ready to go and what each person still owes', ['View', 'PDF']],
    ['Travel roster', 'Passport names, numbers, dates and birthdays for the airline', ['PDF', 'CSV']],
    ['STEP export', 'Enroll the team with the State Department in one upload', ['CSV']],
    ['Medical and diet', 'Allergies, medications and diets. Leaders and admins only.', ['View', 'PDF']],
    ['Emergency contacts', "Two contacts per traveler, printable for the leader's bag", ['PDF']],
    ['T-shirt sizes', 'Counts by size, ready to send to the printer', ['View', 'CSV']],
  ],
  'Money' => [
    ['Fundraising by person', 'Raised, goal and next milestone for every traveler', ['View', 'CSV']],
    ['Budget vs spent', 'Each budget line next to what has actually gone out', ['View', 'CSV']],
    ['Gifts and deposits', 'Every gift with fees and the deposit it landed in', ['CSV']],
  ],
];
?>
<main class="main">
  <div class="head">
    <div class="sub"><h1 class="disp">Reports</h1><div class="muted">Everything you need before a trip, a board meeting or tax season</div></div>
    <div class="seg" data-choice aria-label="Trip"><button type="button" class="tab on">All trips</button><button type="button" class="tab">Israel</button><button type="button" class="tab">Belize</button></div>
  </div>

  <?php foreach ($groups as $title => $reports): ?>
  <section>
    <div class="gh"><?= e($title) ?></div>
    <div class="g3" style="gap:20px">
    <?php foreach ($reports as [$name, $desc, $formats]): ?>
      <div class="tile" style="gap:8px">
        <strong style="font-size:18px"><?= e($name) ?></strong>
        <span class="muted"><?= e($desc) ?></span>
        <span style="display:flex;gap:8px;margin-top:auto;padding-top:8px"><?php foreach ($formats as $f): ?><a class="pill" style="height:30px;padding:0 14px" href="#" data-say="<?= e($name) ?> <?= e($f) ?> is coming next"><?= e($f) ?></a><?php endforeach; ?></span>
      </div>
    <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <section>
    <div class="gh">Preview · Fundraising by person · Belize</div>
    <div class="group tbl"><table>
      <thead><tr><th>Traveler</th><th>Goal</th><th>Raised</th><th>Next milestone</th><th class="num">On track</th></tr></thead>
      <tbody>
      <?php foreach ($team as $p): ?><tr><td><strong><?= e($p['name']) ?></strong></td><td><?= money($p['goal']) ?></td><td><?= money($p['raised']) ?></td><td><?= $p['raised'] >= 950 ? 'Fully funded by Mar 14' : ($p['raised'] >= 190 ? '50% by Feb 7' : '10% by Dec 19') ?></td><td class="num">Yes</td></tr><?php endforeach; ?>
      <tr style="background:var(--tint)"><td><strong>Team</strong></td><td><strong><?= money($trips['belize']['goal']) ?></strong></td><td><strong><?= money($trips['belize']['raised']) ?></strong></td><td></td><td class="num"><strong><?= pct($trips['belize']['raised'], $trips['belize']['goal']) ?>%</strong></td></tr>
      </tbody>
    </table></div>
  </section>
</main>
<?php page_close(); ?>
