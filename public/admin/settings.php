<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview('staff');
page_open('Settings');
admin_header('settings');
$perms = [
  ['Team and profiles', '', 2], ['Tasks and goals', '', 2], ['Meetings and attendance', '', 2], ['Documents and links', '', 2],
  ['Flights and travel', '', 1], ['Medical information', 'Allergies, medications, health concerns', 1],
  ['Budget and expenses', '', 1], ['Gifts and donors', '', 1], ['Applications for their trip', '', 1],
];
$switches = [
  ['Approve fundraising pages', 'Pages go public once a leader or admin approves them', true],
  ['Approve applicants for their own trip', 'Only for applicants who chose their trip first', false],
  ['Waive an application deposit', '', false],
  ['Email the whole team', '', true],
];
$sec = ($_GET['s'] ?? 'demo') === 'leaders' ? 'Leader permissions' : 'Demo data';
$sections = ['Demo data', 'Church profile', 'Admins', 'Leader permissions', 'Online giving', 'Planning Center', 'Fundraising pages', 'Applications', 'Emails', 'Background checks', 'E-signatures', 'Data and exports'];
?>
<main class="main">
  <h1 class="disp" style="margin:0;font-size:clamp(1.875rem,3vw,2.375rem)">Settings</h1>
  <div class="split nav-rail">
    <nav aria-label="Settings sections" style="display:flex;flex-direction:column;gap:2px;position:sticky;top:140px;align-self:start">
    <?php foreach ($sections as $s): $live = in_array($s, ['Demo data', 'Leader permissions'], true); ?><a class="side<?= $s === $sec ? ' on' : '' ?>" href="<?= $s === 'Demo data' ? '/admin/settings.php?s=demo' : ($s === 'Leader permissions' ? '/admin/settings.php?s=leaders' : '#') ?>"<?= $s === $sec ? ' aria-current="page"' : ($live ? '' : ' data-say="' . e($s) . ' settings are coming next"') ?>><?= e($s) ?></a><?php endforeach; ?>
    </nav>

    <?php if ($sec === 'Demo data'): $on = demo_on(); ?>
    <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
      <div style="display:flex;flex-direction:column;gap:4px"><h2 style="margin:0;font-size:28px;letter-spacing:-.02em">Demo data</h2><div class="muted">Fill the whole site with sample trips, travelers, photos and progress, so you can show it to your team or try things without touching real information.</div></div>
      <section class="tile xl" style="gap:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap">
          <div><strong style="font-size:18px"><?= $on ? 'Demo data is on' : 'Demo data is off' ?></strong><div class="muted small"><?= $on ? 'Everyone signed in sees the sample Belize and Israel trips. Your real data is set aside, untouched.' : 'You are seeing your real trips and people.' ?></div></div>
          <form method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="demo_toggle"><input type="hidden" name="demo" value="<?= $on ? '0' : '1' ?>">
            <button class="sw" type="submit" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="Demo data" style="width:58px;height:34px"></button></form>
        </div>
        <div class="group" style="box-shadow:none;background:var(--cream)">
          <div class="cell"><span class="grow">Belize and Israel with photos, guides, flights and day-by-day plans</span></div>
          <div class="cell"><span class="grow">18 sample travelers with tasks, uploads, attendance and fundraising progress</span></div>
          <div class="cell"><span class="grow">Updates, meetings, budgets and reports filled in</span></div>
        </div>
        <?php if ($on): ?>
        <form method="post" action="/action.php" onsubmit="return confirm('Put the demo back the way it started? Changes made while demo data was on will be cleared.')" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
          <?= csrf() ?><input type="hidden" name="action" value="demo_reset"><span class="muted small">Changes you make while demo data is on only affect the demo.</span><button class="btn" type="submit">Reset demo data</button></form>
        <?php endif; ?>
      </section>
      <div class="muted small">Demo photos are free Unsplash photos of Belize and Israel with no people in them.</div>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
      <div style="display:flex;flex-direction:column;gap:4px"><h2 style="margin:0;font-size:28px;letter-spacing:-.02em">Leader permissions</h2><div class="muted">Trip admins see everything on their trip. Choose what trip leaders can see and change. Travelers only ever see their own information.</div></div>

      <section>
        <div class="gh">Trip leaders can</div>
        <div class="group">
        <?php foreach ($perms as [$label, $hint, $level]): ?>
          <div class="cell">
            <div class="grow"><strong><?= e($label) ?></strong><?php if ($hint): ?><div class="muted small"><?= e($hint) ?></div><?php endif; ?></div>
            <div class="seg" data-choice role="group" aria-label="<?= e($label) ?>">
              <?php foreach (['Hidden', 'View', 'Edit'] as $i => $o): ?><button type="button" class="opt<?= $i === $level ? ' on' : '' ?>" aria-pressed="<?= $i === $level ? 'true' : 'false' ?>"><?= $o ?></button><?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </section>

      <section>
        <div class="gh">Leaders may also</div>
        <div class="group">
        <?php foreach ($switches as [$label, $hint, $on]): ?>
          <div class="cell"><div class="grow"><strong><?= e($label) ?></strong><?php if ($hint): ?><div class="muted small"><?= e($hint) ?></div><?php endif; ?></div><button type="button" class="sw" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="<?= e($label) ?>"></button></div>
        <?php endforeach; ?>
        </div>
      </section>

      <div style="display:flex;justify-content:flex-end;gap:12px"><a class="btn" href="/admin/settings.php?s=leaders">Cancel</a><a class="btn btn-primary" href="#" data-say="Saved (preview only)">Save changes</a></div>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php page_close(); ?>
