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
$sec = ['leaders' => 'Leader permissions', 'email' => 'Email and text', 'giving' => 'Online giving', 'pco' => 'Planning Center', 'pages' => 'Fundraising pages'][$_GET['s'] ?? ''] ?? 'Demo data';
$sections = ['Demo data', 'Church profile', 'Admins', 'Leader permissions', 'Online giving', 'Planning Center', 'Fundraising pages', 'Applications', 'Email and text', 'Background checks', 'E-signatures', 'Data and exports'];
$links = ['Demo data' => 'demo', 'Leader permissions' => 'leaders', 'Email and text' => 'email', 'Online giving' => 'giving', 'Planning Center' => 'pco', 'Fundraising pages' => 'pages'];
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
    <?php elseif ($sec === 'Online giving'): $evs = all('SELECT * FROM stripe_events ORDER BY received_at DESC LIMIT 8'); $monthly = all("SELECT r.*, d.first_name, d.last_name, d.org FROM recurring r LEFT JOIN donors d ON d.id = r.donor_id WHERE r.status = 'active' ORDER BY r.id DESC"); ?>
    <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
      <div style="display:flex;flex-direction:column;gap:4px"><h2 style="margin:0;font-size:28px;letter-spacing:-.02em">Online giving</h2><div class="muted">Card, Apple Pay, Google Pay and bank gifts through Stripe. Gifts land on the Giving page and the traveler's total by themselves.</div></div>
      <section class="group">
        <div class="cell"><div class="grow"><strong>Stripe</strong><div class="muted small"><?= stripe_ready() ? (stripe_test_mode() ? 'Connected in test mode. No real money moves.' : 'Connected. Real gifts are on.') : 'Not connected yet' ?></div></div><span class="pill<?= stripe_ready() ? ' pill-ok' : '' ?>"><?= stripe_ready() ? (stripe_test_mode() ? 'Test mode' : 'On') : 'Off' ?></span></div>
        <div class="cell"><div class="grow"><strong>Webhook</strong><div class="muted small"><?= !empty($config['stripe']['webhook_secret']) ? 'Secret saved' . ($evs ? ' · last heard from Stripe ' . fdate($evs[0]['received_at'], 'M j, g:i A') : ' · nothing received yet') : 'Not set up yet' ?></div></div><span class="pill<?= $evs ? ' pill-ok' : '' ?>"><?= $evs ? 'Working' : 'Waiting' ?></span></div>
      </section>
      <?php if (!stripe_ready() || empty($config['stripe']['webhook_secret'])): ?>
      <section class="tile xl" style="gap:12px"><strong style="font-size:18px">How to connect Stripe</strong>
        <ol style="margin:0;padding-left:20px;line-height:1.7">
          <li>In Stripe, go to <strong>Developers → API keys</strong> and copy the <strong>Secret key</strong> (starts with sk_). Use the test key first if you want to try it.</li>
          <li>Go to <strong>Developers → Webhooks → Add endpoint</strong>. Paste this address: <code><?= e(site_url('/stripe-webhook.php')) ?></code> <button class="link-btn" type="button" data-copy="<?= e(site_url('/stripe-webhook.php')) ?>">Copy</button></li>
          <li>Choose these events: checkout.session.completed, invoice.paid, customer.subscription.deleted, charge.refunded. Save, then copy the <strong>Signing secret</strong> (starts with whsec_).</li>
          <li>Add both to config.php on the server: <code>'stripe' => ['secret' => 'sk_...', 'webhook_secret' => 'whsec_...'],</code></li>
        </ol></section>
      <?php endif; ?>
      <section><div class="gh">Monthly givers</div><div class="group">
        <?php foreach ($monthly as $r): ?><div class="cell"><div class="grow"><strong><?= e(donor_name($r['donor_id'] ? $r : null)) ?></strong><div class="muted small"><?= e(gift_for($r)) ?> · since <?= fdate($r['created_at'], 'M j, Y') ?></div></div><strong><?= money((float)$r['amount'], 2) ?>/mo</strong></div><?php endforeach; ?>
        <?= $monthly ? '' : '<div class="empty">No monthly gifts yet.</div>' ?>
      </div><div class="muted small" style="padding-top:6px">Donors can cancel from the link in their Stripe receipt, or you can cancel in Stripe.</div></section>
    </div>
    <?php elseif ($sec === 'Planning Center'): ?>
    <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
      <div style="display:flex;flex-direction:column;gap:4px"><h2 style="margin:0;font-size:28px;letter-spacing:-.02em">Planning Center</h2><div class="muted">People sign in with their Planning Center account, and staff can pull contact info, birthdays and background checks.</div></div>
      <section class="group">
        <div class="cell"><div class="grow"><strong>Sign in with Planning Center</strong><div class="muted small"><?= pco_login_ready() ? 'On. Travelers and staff use their Planning Center login.' : 'Not connected yet' ?></div></div><span class="pill<?= pco_login_ready() ? ' pill-ok' : '' ?>"><?= pco_login_ready() ? 'On' : 'Off' ?></span></div>
        <div class="cell"><div class="grow"><strong>People and background checks</strong><div class="muted small"><?= pco_api_ready() ? 'On. Link people from their profile, or add them from People.' : 'Not connected yet' ?></div></div><span class="pill<?= pco_api_ready() ? ' pill-ok' : '' ?>"><?= pco_api_ready() ? 'On' : 'Off' ?></span></div>
        <div class="cell"><div class="grow"><strong>Who counts as staff</strong><div class="muted small">Planning Center administrators, anyone tagged Staff here, plus these emails in config.php: <?= e(implode(', ', (array)($config['staff_emails'] ?? [])) ?: 'none yet') ?></div></div></div>
      </section>
      <?php if (!pco_login_ready() || !pco_api_ready()): ?>
      <section class="tile xl" style="gap:12px"><strong style="font-size:18px">How to connect Planning Center</strong>
        <ol style="margin:0;padding-left:20px;line-height:1.7">
          <li>Go to <strong>api.planningcenteronline.com</strong> and sign in as a Planning Center administrator.</li>
          <li>For sign-in: under <strong>OAuth applications</strong>, click <strong>New application</strong>. Name it Journey Missions. Set the callback URL to <code><?= e(site_url('/auth/pco.php')) ?></code> <button class="link-btn" type="button" data-copy="<?= e(site_url('/auth/pco.php')) ?>">Copy</button>. Copy the <strong>Client ID</strong> and <strong>Secret</strong>.</li>
          <li>For people sync: under <strong>Personal access tokens</strong>, click <strong>New token</strong>. Copy the <strong>Application ID</strong> and <strong>Secret</strong>.</li>
          <li>Add them to config.php: <code>'pco' => ['client_id' => '...', 'client_secret' => '...', 'app_id' => '...', 'secret' => '...'],</code> and <code>'staff_emails' => ['you@journeychurch.org'],</code></li>
        </ol></section>
      <?php endif; ?>
    </div>
    <?php elseif ($sec === 'Fundraising pages'): $ap = approve_pages(); ?>
    <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
      <div style="display:flex;flex-direction:column;gap:4px"><h2 style="margin:0;font-size:28px;letter-spacing:-.02em">Fundraising pages</h2><div class="muted">Every traveler gets a page like missions.journeychurch.org/thomas, and every trip gets a team page.</div></div>
      <section class="group">
        <div class="cell"><div class="grow"><strong>Approve pages before they go public</strong><div class="muted small">A leader or admin takes a quick look first. Approve them on each trip's Giving tab.</div></div>
          <form method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="site_setting"><input type="hidden" name="key" value="approve_pages"><input type="hidden" name="value" value="<?= $ap ? '0' : '1' ?>"><button class="sw" type="submit" role="switch" aria-checked="<?= $ap ? 'true' : 'false' ?>" aria-label="Approve pages" style="width:58px;height:34px"></button></form></div>
      </section>
      <?php $waiting = all("SELECT m.*, p.first_name, p.preferred_name, p.last_name, t.name AS trip_name FROM members m JOIN people p ON p.id = m.person_id JOIN trips t ON t.id = m.trip_id WHERE m.page_status = 'pending'"); ?>
      <section><div class="gh">Waiting for approval</div><div class="group">
        <?php foreach ($waiting as $m): ?><div class="cell"><div class="grow"><strong><?= e(full_name($m)) ?></strong><div class="muted small"><?= e($m['trip_name']) ?></div></div><a class="small" href="/give/?s=<?= e($m['page_slug']) ?>" target="_blank">View</a>
          <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="page_review"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-primary" name="status" value="live" style="height:34px">Approve</button></form></div><?php endforeach; ?>
        <?= $waiting ? '' : '<div class="empty">Nothing waiting.</div>' ?>
      </div></section>
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
