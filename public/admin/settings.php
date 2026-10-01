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
$sections = ['Church profile', 'Admins', 'Leader permissions', 'Online giving', 'Planning Center', 'Fundraising pages', 'Applications', 'Emails', 'Background checks', 'E-signatures', 'Data and exports'];
?>
<main class="main">
  <h1 class="disp" style="margin:0;font-size:clamp(1.875rem,3vw,2.375rem)">Settings</h1>
  <div class="split nav-rail">
    <nav aria-label="Settings sections" style="display:flex;flex-direction:column;gap:2px;position:sticky;top:140px;align-self:start">
    <?php foreach ($sections as $s): ?><a class="side<?= $s === 'Leader permissions' ? ' on' : '' ?>" href="#"<?= $s === 'Leader permissions' ? ' aria-current="page"' : ' data-say="' . e($s) . ' settings are coming next"' ?>><?= e($s) ?></a><?php endforeach; ?>
    </nav>

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

      <div style="display:flex;justify-content:flex-end;gap:12px"><a class="btn" href="/admin/settings.php">Cancel</a><a class="btn btn-primary" href="#" data-say="Saved (preview only)">Save changes</a></div>
    </div>
  </div>
</main>
<?php page_close(); ?>
