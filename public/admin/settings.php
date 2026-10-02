<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_staff();
global $config;
$sections = ['church' => 'Church', 'leaders' => 'Leader permissions', 'email' => 'Email and text', 'giving' => 'Online giving', 'pco' => 'Planning Center',
             'pages' => 'Fundraising pages', 'privacy' => 'Privacy and backups', 'audit' => 'Activity log', 'demo' => 'Demo data'];
$s = isset($sections[g('s')]) ? g('s') : 'church';
page_open('Settings · ' . $sections[$s]);
admin_header('settings');
$status = fn(bool $on, string $yes = 'On', string $no = 'Off') => '<span class="pill' . ($on ? ' pill-ok' : '') . '">' . ($on ? $yes : $no) . '</span>';
?>
<main class="main" id="main">
  <h1 class="page-title">Settings</h1>
  <div class="split nav-rail">
    <nav class="side-nav" aria-label="Settings sections">
      <?php foreach ($sections as $k => $label): ?><a class="side<?= $k === $s ? ' on' : '' ?>" href="/admin/settings.php?s=<?= $k ?>"<?= $k === $s ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
    </nav>
    <div class="stack-28" style="min-width:0">
      <div class="stack-4"><h2 class="section-title"><?= e($sections[$s]) ?></h2></div>

    <?php if ($s === 'church'): $l = church_legal(); ?>
      <div class="muted">These come from the server settings file (config.php), so they can't be changed by mistake. Ask whoever manages the server to update them.</div>
      <section class="group">
        <div class="cell"><div class="grow"><strong>Legal name</strong><div class="muted small">Printed on giving statements and receipts</div></div><span><?= e($l['name']) ?></span></div>
        <div class="cell"><div class="grow"><strong>Mailing address</strong><div class="muted small">Required on tax statements</div></div><span><?= $l['address'] ? e($l['address']) : '<span class="pill">Missing</span>' ?></span></div>
        <div class="cell"><div class="grow"><strong>EIN</strong><div class="muted small">Optional, shown on statements</div></div><span><?= $l['ein'] ? e($l['ein']) : '<span class="muted">Not set</span>' ?></span></div>
        <div class="cell"><div class="grow"><strong>Website address</strong></div><span><?= e(site_url('/')) ?></span></div>
        <div class="cell"><div class="grow"><strong>Alerts go to</strong><div class="muted small">Site errors, failed messages, disputes and serious incidents</div></div><span><?= !empty($config['alert_email']) ? e($config['alert_email']) : '<span class="pill">Not set</span>' ?></span></div>
      </section>
      <?php if (($config['_path'] ?? '') && str_contains((string)$config['_path'], 'public_html')): ?><div class="note error"><strong>Move config.php</strong><div class="small">The server settings file is inside public_html. Move it up one folder (next to public_html) so it can never be downloaded.</div></div><?php endif; ?>

    <?php elseif ($s === 'leaders'): $lp = leader_perms(); ?>
      <div class="muted">Trip admins can do everything on their own trip. Choose what trip leaders can see and change on the trips they lead. Travelers only ever see their own information. Staff see everything.</div>
      <form method="post" action="/action.php" class="stack-20"><?= csrf() ?><input type="hidden" name="action" value="site_setting"><input type="hidden" name="key" value="leader_perms">
        <div class="group">
        <?php foreach (PERM_AREAS as $k => [$label, $hint]): ?>
          <fieldset class="cell perm-row"><legend class="grow"><strong><?= e($label) ?></strong><?php if ($hint): ?><span class="muted small block"><?= e($hint) ?></span><?php endif; ?></legend>
            <div class="seg" role="radiogroup" aria-label="<?= e($label) ?>"><?php foreach (PERM_LEVELS as $i => $o): ?><label class="opt"><input class="sr" type="radio" name="perm[<?= $k ?>]" value="<?= $i ?>"<?= $lp[$k] === $i ? ' checked' : '' ?>><?= $o ?></label><?php endforeach; ?></div></fieldset>
        <?php endforeach; ?>
        </div>
        <div class="actions"><button class="btn btn-primary" type="submit">Save permissions</button></div>
      </form>

    <?php elseif ($s === 'email'): $box = all('SELECT * FROM outbox ORDER BY id DESC LIMIT 100'); $queued = (int)val("SELECT COUNT(*) FROM outbox WHERE status = 'queued'"); ?>
      <div class="muted">Announcements, reminders, application and reference emails, welcome emails, parent links, receipts and statements all go out from here.</div>
      <section class="group">
        <div class="cell"><div class="grow"><strong>Email</strong><div class="muted small"><?= mail_ready() ? 'Sending from ' . e($config['mail_from']) . (smtp_ready() ? ' through ' . e($config['smtp']['host']) : ' through the web server (may land in spam; a mail service is better)') : 'Not set up yet' ?></div></div><?= $status(mail_ready()) ?></div>
        <div class="cell"><div class="grow"><strong>Text messages</strong><div class="muted small"><?= text_ready() ? 'Sending through Twilio from ' . e($config['twilio']['from']) . '. Only to people who said yes, never after STOP, and only 8am to 9pm.' : 'Not set up yet. Needs a Twilio account with carrier registration.' ?></div></div><?= $status(text_ready()) ?></div>
        <div class="cell"><div class="grow"><strong>Waiting to send</strong><div class="muted small">Messages go out a few at a time in the background</div></div><span><?= $queued ?></span></div>
      </section>
      <form class="tile form row-form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="test_email">
        <label class="lab">Send a test email to<input type="email" name="to" required placeholder="you@journeychurch.org"></label><button class="btn btn-dark" type="submit">Send test</button></form>
      <section><h3 class="gh">Recent messages</h3>
        <div class="group tbl"><table><thead><tr><th>When</th><th>To</th><th>Message</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($box as $o): ?><tr><td class="muted"><?= fdate($o['created_at'], 'M j, g:i A') ?></td><td><?= $o['channel'] === 'text' ? 'Text · ' : '' ?><?= e($o['to_addr']) ?></td><td class="wrap"><strong><?= e($o['subject'] ?: mb_strimwidth((string)$o['body'], 0, 60, '…')) ?></strong><div class="muted small">by <?= e($o['created_by']) ?><?= $o['error'] ? ' · ' . e($o['error']) : '' ?></div></td>
          <td><span class="pill<?= $o['status'] === 'sent' ? ' pill-ok' : '' ?>"><?= e(['sent' => 'Sent', 'not_sent' => 'Not sent', 'failed' => 'Failed', 'queued' => 'Waiting'][$o['status']] ?? $o['status']) ?></span></td>
          <td><?php if (in_array($o['status'], ['failed', 'not_sent'], true) && (mail_ready() || text_ready())): ?><form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="outbox_retry"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button class="link-btn">Try again</button></form><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table><?= $box ? '' : '<div class="empty">Nothing sent yet.</div>' ?></div></section>
      <section class="note"><strong>Getting set up</strong><div class="muted small">Email: a mail service (Postmark, Amazon SES or your Google Workspace) keeps messages out of spam. Texts: Twilio requires carrier registration (called A2P 10DLC) before texts will deliver. Add the details to config.php; see the setup guide in the README.</div></section>

    <?php elseif ($s === 'giving'): $evs = all('SELECT * FROM stripe_events ORDER BY received_at DESC LIMIT 8'); $monthly = all("SELECT r.*, d.first_name, d.last_name, d.org FROM recurring r LEFT JOIN donors d ON d.id = r.donor_id WHERE r.status = 'active' ORDER BY r.id DESC"); ?>
      <div class="muted">Card, Apple Pay, Google Pay and bank gifts through Stripe. Gifts land on the Giving page and the traveler's total by themselves.</div>
      <section class="group">
        <div class="cell"><div class="grow"><strong>Stripe</strong><div class="muted small"><?= stripe_ready() ? (stripe_test_mode() ? 'Connected in test mode. No real money moves.' : 'Connected. Real gifts are on.') : 'Not connected yet' ?></div></div><?= $status(stripe_ready(), stripe_test_mode() ? 'Test mode' : 'On') ?></div>
        <div class="cell"><div class="grow"><strong>Webhook</strong><div class="muted small"><?= !empty($config['stripe']['webhook_secret']) ? 'Secret saved' . ($evs ? ' · last heard from Stripe ' . fdate($evs[0]['received_at'], 'M j, g:i A') : ' · nothing received yet') : 'Not set up yet' ?></div></div><?= $status((bool)$evs, 'Working', 'Waiting') ?></div>
      </section>
      <?php if (!stripe_ready() || empty($config['stripe']['webhook_secret'])): ?>
      <section class="tile xl stack-12"><h3 class="card-title">How to connect Stripe</h3>
        <ol class="steps-list">
          <li>In Stripe, go to <strong>Developers → API keys</strong> and copy the <strong>Secret key</strong> (starts with sk_). Use the test key first to try it.</li>
          <li>Go to <strong>Developers → Webhooks → Add endpoint</strong>. Paste this address: <code><?= e(site_url('/stripe-webhook.php')) ?></code> <button class="link-btn" type="button" data-copy="<?= e(site_url('/stripe-webhook.php')) ?>">Copy</button></li>
          <li>Pick these events: checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, invoice.paid, customer.subscription.deleted, charge.refunded, charge.dispute.created, charge.dispute.closed. Save, then copy the <strong>Signing secret</strong> (starts with whsec_).</li>
          <li>Send both to whoever manages the server to add to config.php.</li>
        </ol></section>
      <?php endif; ?>
      <section><h3 class="gh">Monthly givers</h3><div class="group">
        <?php foreach ($monthly as $r): ?><div class="cell"><div class="grow"><strong><?= e(donor_name($r['donor_id'] ? $r : null)) ?></strong><div class="muted small"><?= e(gift_for($r)) ?> · since <?= fdate($r['created_at'], 'M j, Y') ?></div></div><strong><?= money((float)$r['amount'], 2) ?>/mo</strong></div><?php endforeach; ?>
        <?= $monthly ? '' : '<div class="empty">No monthly gifts yet.</div>' ?>
      </div><div class="muted small" style="padding-top:6px">Monthly gifts stop on their own on the trip date, and right away if a trip is cancelled.</div></section>

    <?php elseif ($s === 'pco'): ?>
      <div class="muted">People sign in with their Planning Center account, and staff can pull contact info, birthdays and background checks.</div>
      <section class="group">
        <div class="cell"><div class="grow"><strong>Sign in with Planning Center</strong><div class="muted small"><?= pco_login_ready() ? 'On. Travelers and staff use their Planning Center login.' : 'Not connected yet' ?></div></div><?= $status(pco_login_ready()) ?></div>
        <div class="cell"><div class="grow"><strong>People and background checks</strong><div class="muted small"><?= pco_api_ready() ? 'On. Link people from their profile, or add them from People.' : 'Not connected yet' ?></div></div><?= $status(pco_api_ready()) ?></div>
        <div class="cell"><div class="grow"><strong>Who gets staff access</strong><div class="muted small">People with "Staff access" turned on (on their person page) and these Planning Center IDs in config.php: <?= e(implode(', ', (array)($config['staff_pco_ids'] ?? [])) ?: 'none yet') ?>. Email addresses never grant staff access.</div></div></div>
      </section>
      <?php if (!pco_login_ready() || !pco_api_ready()): ?>
      <section class="tile xl stack-12"><h3 class="card-title">How to connect Planning Center</h3>
        <ol class="steps-list">
          <li>Go to <strong>api.planningcenteronline.com</strong> and sign in as a Planning Center administrator.</li>
          <li>For sign-in: under <strong>OAuth applications</strong>, make a new application named Journey Missions. Set the callback URL to <code><?= e(site_url('/auth/pco.php')) ?></code> <button class="link-btn" type="button" data-copy="<?= e(site_url('/auth/pco.php')) ?>">Copy</button>. Copy the Client ID and Secret.</li>
          <li>For people sync: under <strong>Personal access tokens</strong>, make a new token. Copy the Application ID and Secret.</li>
          <li>Send those to whoever manages the server to add to config.php.</li>
        </ol></section>
      <?php endif; ?>

    <?php elseif ($s === 'pages'): $ap = approve_pages(); $waiting = all("SELECT m.*, p.first_name, p.preferred_name, p.last_name, t.name AS trip_name FROM members m JOIN people p ON p.id = m.person_id JOIN trips t ON t.id = m.trip_id WHERE m.page_status = 'pending'"); ?>
      <div class="muted">Every traveler gets a page like <?= e(str_replace('https://', '', site_url('/thomas'))) ?>, and every trip gets a team page.</div>
      <section class="group">
        <div class="cell"><div class="grow"><strong>Approve pages before they go public</strong><div class="muted small">A leader or staff member takes a quick look first, including after any edit to a live page.</div></div>
          <form method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="site_setting"><input type="hidden" name="key" value="approve_pages"><input type="hidden" name="value" value="<?= $ap ? '0' : '1' ?>"><button class="sw" type="submit" role="switch" aria-checked="<?= $ap ? 'true' : 'false' ?>" aria-label="Approve pages before they go public"></button></form></div>
      </section>
      <section><h3 class="gh">Waiting for approval</h3><div class="group">
        <?php foreach ($waiting as $m): ?><div class="cell"><div class="grow"><strong><?= e(full_name($m)) ?></strong><div class="muted small"><?= e($m['trip_name']) ?></div></div><a class="small" href="/give/?s=<?= e($m['page_slug']) ?>" target="_blank" rel="noopener">View</a>
          <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="page_review"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-dark btn-sm" name="status" value="live">Approve</button></form></div><?php endforeach; ?>
        <?= $waiting ? '' : '<div class="empty">Nothing waiting.</div>' ?>
      </div></section>

    <?php elseif ($s === 'privacy'): $backups = glob(data_dir() . '/backups/journey-*.sqlite') ?: []; rsort($backups); ?>
      <div class="muted">How the site protects travelers' information, and copies of your data.</div>
      <section class="group">
        <div class="cell"><div class="grow"><strong>Encryption</strong><div class="muted small">Passport numbers and medical details are encrypted in the database.</div></div><?= $status((bool)data_key()) ?></div>
        <div class="cell"><div class="grow"><strong>Automatic cleanup</strong><div class="muted small">Passport numbers, medical details and passport scans are erased <?= retention_days() ?> days after someone's last trip. Unsent applications are removed after 60 days.</div></div><?= $status(true) ?></div>
        <div class="cell"><div class="grow"><strong>Parent links</strong><div class="muted small">Stop working 60 days after the trip. Staff can make a new link anytime.</div></div></div>
      </section>
      <section><h3 class="gh">Backups</h3><div class="group">
        <div class="cell"><div class="grow"><strong>Daily copy of the database</strong><div class="muted small">Kept on the server for 30 days. <?= $backups ? 'Latest: ' . date('M j, g:i A', filemtime($backups[0])) : 'None yet (the first runs within a day).' ?> For safety, also download a copy now and then and keep it somewhere else.</div></div>
          <form method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="backup_download"><button class="btn btn-sm" type="submit">Download a copy</button></form></div>
      </div></section>
      <?php $ly = locked_years(); if ($ly): ?>
      <section><h3 class="gh">Locked years</h3><div class="group">
        <?php foreach ($ly as $y): ?><div class="cell"><div class="grow"><strong><?= $y ?></strong><div class="muted small">Statements went out, so gifts are locked.</div></div>
          <form method="post" action="/action.php" class="inline" data-confirm="Unlock <?= $y ?>? If you change anything, send corrected statements."><?= csrf() ?><input type="hidden" name="action" value="year_unlock"><input type="hidden" name="year" value="<?= $y ?>"><input type="hidden" name="reason" value="Unlocked from settings"><button class="link-btn">Unlock</button></form></div><?php endforeach; ?>
      </div></section>
      <?php endif; ?>

    <?php elseif ($s === 'audit'): $who = g('who'); $rows = all('SELECT * FROM audit' . ($who !== '' ? ' WHERE actor LIKE ?' : '') . ' ORDER BY id DESC LIMIT 300', $who !== '' ? ["%$who%"] : []); ?>
      <div class="muted">Every change, and every look at private information (medical, passports, signatures), with who did it and when.</div>
      <form method="get" class="chips gap-8"><input type="hidden" name="s" value="audit"><label class="sr" for="aw">Person</label><input id="aw" class="pill-input" type="search" name="who" value="<?= e($who) ?>" placeholder="Filter by person"><button class="btn btn-sm" type="submit">Filter</button></form>
      <section class="group tbl"><table><thead><tr><th>When</th><th>Who</th><th>What</th><th>Details</th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?><tr><td class="muted"><?= fdate($r['at'], 'M j, g:i A') ?></td><td class="wrap"><strong><?= e($r['actor']) ?></strong><div class="muted small"><?= e($r['via']) ?></div></td><td><?= e(str_replace('_', ' ', $r['action'])) ?><?= $r['entity_id'] ? ' <span class="muted small">#' . (int)$r['entity_id'] . '</span>' : '' ?></td><td class="wrap muted small"><?= e(mb_strimwidth((string)$r['detail'], 0, 160, '…')) ?></td></tr><?php endforeach; ?>
      </tbody></table><?= $rows ? '' : '<div class="empty">Nothing yet.</div>' ?></section>

    <?php else: $on = demo_on(); ?>
      <div class="muted">Fill the site with sample trips, travelers, photos and progress, so you can show it to your team or try things. Only you see it, and only while it's on. Public pages, applications and online giving always use your real data.</div>
      <section class="tile xl stack-16">
        <div class="row-between">
          <div><strong class="card-title"><?= $on ? 'Demo data is on for you' : 'Demo data is off' ?></strong><div class="muted small"><?= $on ? 'You are looking at sample data. Your real data is untouched.' : 'You are seeing your real trips and people.' ?></div></div>
          <form method="post" action="/action.php" data-confirm="<?= $on ? 'Go back to your real data?' : 'Switch to sample data? Only you will see it.' ?>"><?= csrf() ?><input type="hidden" name="action" value="demo_toggle"><input type="hidden" name="demo" value="<?= $on ? '0' : '1' ?>">
            <button class="sw" type="submit" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="Demo data"></button></form>
        </div>
        <?php if ($on): ?>
        <form method="post" action="/action.php" data-confirm="Put the demo back the way it started? Changes made in the demo will be cleared." class="row-between"><?= csrf() ?><input type="hidden" name="action" value="demo_reset"><span class="muted small">Changes you make while demo data is on only affect the demo.</span><button class="btn" type="submit">Reset demo data</button></form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    </div>
  </div>
</main>
<?php page_close(); ?>
