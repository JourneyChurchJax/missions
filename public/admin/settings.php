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
$sec = ['leaders' => 'Leader permissions', 'email' => 'Email and text'][$_GET['s'] ?? ''] ?? 'Demo data';
$sections = ['Demo data', 'Church profile', 'Admins', 'Leader permissions', 'Online giving', 'Planning Center', 'Fundraising pages', 'Applications', 'Email and text', 'Background checks', 'E-signatures', 'Data and exports'];
$links = ['Demo data' => 'demo', 'Leader permissions' => 'leaders', 'Email and text' => 'email'];
?>
<main class="main">
  <h1 class="disp" style="margin:0;font-size:clamp(1.875rem,3vw,2.375rem)">Settings</h1>
  <div class="split nav-rail">
    <nav aria-label="Settings sections" style="display:flex;flex-direction:column;gap:2px;position:sticky;top:140px;align-self:start">
    <?php foreach ($sections as $s): $live = isset($links[$s]); ?><a class="side<?= $s === $sec ? ' on' : '' ?>" href="<?= $live ? '/admin/settings.php?s=' . $links[$s] : '#' ?>"<?= $s === $sec ? ' aria-current="page"' : ($live ? '' : ' data-say="' . e($s) . ' settings are coming next"') ?>><?= e($s) ?></a><?php endforeach; ?>
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
    <?php elseif ($sec === 'Email and text'): $box = all('SELECT * FROM outbox ORDER BY id DESC LIMIT 100'); ?>
    <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
      <div style="display:flex;flex-direction:column;gap:4px"><h2 style="margin:0;font-size:28px;letter-spacing:-.02em">Email and text</h2><div class="muted">Announcements, reminders, application and reference emails, welcome emails, parent links and statements all go out from here.</div></div>
      <section class="group">
        <div class="cell"><div class="grow"><strong>Email</strong><div class="muted small"><?= mail_ready() ? 'Sending from ' . e($config['mail_from']) : 'Not set up. Add \'mail_from\' => \'missions@journeychurch.org\' to config.php on the server.' ?></div></div><span class="pill<?= mail_ready() ? ' pill-ok' : '' ?>"><?= mail_ready() ? 'On' : 'Off' ?></span></div>
        <div class="cell"><div class="grow"><strong>Text messages</strong><div class="muted small"><?= text_ready() ? 'Sending through Twilio from ' . e($config['twilio']['from']) : 'Not set up. Needs a Twilio account (about 1¢ a text). Add its keys to config.php.' ?></div></div><span class="pill<?= text_ready() ? ' pill-ok' : '' ?>"><?= text_ready() ? 'On' : 'Off' ?></span></div>
      </section>
      <form class="tile form" method="post" action="/action.php" style="grid-template-columns:1fr auto;align-items:end"><?= csrf() ?><input type="hidden" name="action" value="test_email">
        <label class="lab">Send a test email to<input type="email" name="to" required placeholder="you@journeychurch.org"></label><button class="btn btn-dark" type="submit">Send test</button></form>
      <section><div class="gh"><span>Recent messages</span><span class="muted small">Messages that didn't send are kept here</span></div>
        <div class="group tbl"><table><thead><tr><th>When</th><th>To</th><th>Message</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($box as $o): ?><tr><td class="muted"><?= fdate($o['created_at'], 'M j, g:i A') ?></td><td><?= $o['channel'] === 'text' ? '💬 ' : '✉️ ' ?><?= e($o['to_addr']) ?></td><td class="wrap"><strong><?= e($o['subject'] ?: mb_strimwidth((string)$o['body'], 0, 60, '…')) ?></strong><div class="muted small">by <?= e($o['created_by']) ?></div></td>
          <td><span class="pill<?= $o['status'] === 'sent' ? ' pill-ok' : '' ?>" title="<?= e($o['error']) ?>"><?= e(['sent' => 'Sent', 'not_sent' => 'Not sent', 'failed' => 'Failed', 'queued' => 'Sending'][$o['status']] ?? $o['status']) ?></span></td></tr><?php endforeach; ?>
        </tbody></table><?= $box ? '' : '<div class="empty">Nothing sent yet.</div>' ?></div></section>
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
