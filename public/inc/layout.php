<?php
// Shared page shell: <head>, the two headers, the footer, and small view helpers.

function logo(int $w = 200, bool $dark = false, string $href = '/'): string {
    $img = $dark ? 'horizontal-ember_cream.png' : 'horizontal-ember_ink.png';
    $letters = implode('', array_map(fn($c) => "<span>$c</span>", str_split('MISSIONS')));
    return '<a class="jm-logo' . ($dark ? ' on-dark' : '') . '" href="' . e($href) . '" style="--w:' . $w . 'px" aria-label="Journey Missions home">'
        . '<img src="/assets/logo/' . $img . '" alt="Journey Church">'
        . '<span class="under" aria-hidden="true">' . $letters . '</span></a>';
}

// <head> contents shared by every page. $og adds link previews for pages people share (title, description, image).
function head_tags(string $title, array $og = []): void { ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#f7f4f0">
<title><?= e($title) ?> · Journey Missions</title>
<link rel="icon" href="/assets/logo/mark-ember.png">
<link rel="apple-touch-icon" href="/assets/logo/mark-ember.png">
<link rel="manifest" href="/assets/manifest.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Inter+Tight:wght@700;800&family=Montserrat:wght@700&display=swap">
<link rel="stylesheet" href="<?= asset('/assets/app.css') ?>">
<?php if ($og): ?><meta property="og:type" content="website"><meta property="og:title" content="<?= e($og['title'] ?? $title) ?>"><?php if (!empty($og['description'])): ?><meta property="og:description" content="<?= e($og['description']) ?>"><meta name="description" content="<?= e($og['description']) ?>"><?php endif; ?><?php if (!empty($og['image'])): ?><meta property="og:image" content="<?= e($og['image']) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?><?php if (!empty($og['url'])): ?><meta property="og:url" content="<?= e($og['url']) ?>"><?php endif; ?><?php endif; ?>
<?php }

function page_open(string $title): void { ?>
<!doctype html>
<html lang="en">
<head>
<?php head_tags($title); ?>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<?php if (demo_on()): ?><div class="preview"><strong>Demo data</strong> · only you see it · <a href="/admin/settings.php?s=demo">Turn off</a></div><?php endif; ?>
<?php if (impersonating()): ?><div class="preview warn">Previewing as <?= e(full_name(person(acting_person_id() ?? 0))) ?> · read-only · <form method="post" action="/" class="inline"><?= csrf() ?><input type="hidden" name="as" value="staff"><button class="linklike" type="submit">Back to staff</button></form></div><?php endif; ?>
<?php }

function page_close(): void { $f = flash(); $type = flash_type(); ?>
<div class="toast<?= $f ? ' show' : '' ?><?= $type === 'error' ? ' error' : '' ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>" aria-live="polite"><?= e($f) ?></div>
<script src="<?= asset('/assets/app.js') ?>"></script>
</body>
</html>
<?php }

// Public pages (apply, reference, parent, give, sign): no app navigation.
function public_open(string $title, array $og = []): void { ?>
<!doctype html>
<html lang="en">
<head>
<?php head_tags($title, $og); ?>
</head>
<body class="pub">
<header class="pub-top"><?= logo(210, false, 'https://journeychurch.org') ?></header>
<?php }
function public_close(): void { ?>
<script src="<?= asset('/assets/app.js') ?>"></script>
</body>
</html>
<?php }

// The round initials button in the top right, with profile, switch view and sign out
function account_menu(string $initials, string $label, array $links): string {
    $h = '<details class="acct"><summary class="me" aria-label="' . e($label) . '">' . e($initials) . '</summary><div class="acct-menu" role="menu">';
    foreach ($links as [$text, $href]) $h .= '<a role="menuitem" href="' . e($href) . '">' . e($text) . '</a>';
    $h .= '<form method="post" action="/signout.php">' . csrf() . '<button type="submit" role="menuitem">Sign out</button></form></div></details>';
    return $h;
}

function admin_header(string $active): void {
    $tabs = ['trips' => ['Trips', '/admin/'], 'people' => ['People', '/admin/people.php'], 'apps' => ['Applications', '/admin/applications.php'],
             'giving' => ['Giving', '/admin/giving.php'], 'reports' => ['Reports', '/admin/reports.php'], 'settings' => ['Settings', '/admin/settings.php']];
    if (!is_staff_session()) $tabs = array_intersect_key($tabs, ['trips' => 1]); ?>
<header class="top">
  <div class="topin">
    <?= logo(230, false, is_staff_session() ? '/admin/' : '/trip/') ?>
    <?php if (is_staff_session()): ?>
    <form class="search" role="search" action="/admin/search.php">
      <label class="sr" for="q">Search</label>
      <input id="q" name="q" type="search" placeholder="Search trips, people, donors" value="<?= e(g('q')) ?>">
      <button class="sbtn" type="submit">Search</button>
    </form>
    <?php endif; ?>
    <div class="right"><?= account_menu(current_actor_initials(), 'Account: ' . current_actor_name(), is_staff_session() ? [['Settings', '/admin/settings.php'], ['Preview as a traveler', '/']] : [['My trip', '/trip/']]) ?></div>
  </div>
  <div class="navrow"><nav class="seg" aria-label="Admin">
  <?php foreach ($tabs as $k => [$label, $href]): ?>
    <a class="tab<?= $k === $active ? ' on' : '' ?>" href="<?= $href ?>"<?= $k === $active ? ' aria-current="page"' : '' ?>><?= $label ?></a>
  <?php endforeach; ?>
  </nav></div>
</header>
<?php }

function member_header(string $active): void {
    $tabs = ['trip' => ['Trip', '/trip/'], 'schedule' => ['Schedule', '/trip/schedule.php'], 'guide' => ['Guide', '/trip/guide.php'],
             'docs' => ['Documents', '/trip/documents.php'], 'fund' => ['Fundraising', '/trip/fundraising.php'], 'messages' => ['Messages', '/trip/messages.php']];
    $me = person(acting_person_id() ?? 0);
    $links = [['My profile', '/trip/profile.php']];
    foreach (led_trips() as $lt) $links[] = ['Lead ' . $lt['name'], '/admin/trip.php?id=' . $lt['id']];
    if (is_staff_session()) $links[] = ['Back to staff', '/']; ?>
<header class="top">
  <div class="topin m">
    <?= logo(220, false, '/trip/') ?>
    <nav class="seg sm" aria-label="Trip sections">
    <?php foreach ($tabs as $k => [$label, $href]): ?>
      <a class="tab<?= $k === $active ? ' on' : '' ?>" href="<?= $href ?>"<?= $k === $active ? ' aria-current="page"' : '' ?>><?= $label ?></a>
    <?php endforeach; ?>
    </nav>
    <div class="right"><?= account_menu($me ? initials(full_name($me)) : '?', 'My account', $links) ?></div>
  </div>
</header>
<nav class="tabbar" aria-label="Trip sections">
  <?php foreach (['trip' => ['Trip', '/trip/', '⌂'], 'schedule' => ['Schedule', '/trip/schedule.php', '▦'], 'messages' => ['Messages', '/trip/messages.php', '✉'], 'fund' => ['Giving', '/trip/fundraising.php', '♥'], 'docs' => ['More', '/trip/documents.php', '…']] as $k => [$label, $href, $icon]): ?>
    <a href="<?= $href ?>"<?= $k === $active || ($k === 'docs' && $active === 'guide') ? ' aria-current="page" class="on"' : '' ?>><span aria-hidden="true"><?= $icon ?></span><?= $label ?></a>
  <?php endforeach; ?>
</nav>
<?php }

function date_box(string $mon, string $day): string {
    return '<div class="date"><small>' . e($mon) . '</small><b>' . e($day) . '</b></div>';
}
function date_box_for(?string $d): string { return $d ? date_box(strtoupper(date('M', strtotime($d))), date('j', strtotime($d))) : date_box('—', '—'); }
function bar(int $pct, bool $dark = false, string $label = 'Progress'): string {
    return '<div class="bar' . ($dark ? ' on-dark' : '') . '" role="progressbar" aria-label="' . e($label) . '" aria-valuenow="' . max(0, min(100, $pct)) . '" aria-valuemin="0" aria-valuemax="100"><span style="width:' . max(0, min(100, $pct)) . '%"></span></div>';
}
function ring(int $done, int $total, int $size = 88, bool $dark = false, ?string $label = null, ?string $aria = null): string {
    $label = $label ?? ($done . '/' . $total);
    $c = 238.8; $len = $total ? round($c * max(0, min($done, $total)) / $total, 1) : 0;
    $track = $dark ? 'rgba(247,244,240,0.16)' : 'var(--sand)'; $fill = $dark ? 'var(--cream)' : 'var(--ink)';
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 88 88" role="img" aria-label="' . e($aria ?? ($done . ' of ' . $total . ' done')) . '" style="flex-shrink:0">'
        . '<circle cx="44" cy="44" r="38" fill="none" stroke="' . $track . '" stroke-width="10"/>'
        . '<circle cx="44" cy="44" r="38" fill="none" stroke="' . $fill . '" stroke-width="10" stroke-linecap="round" stroke-dasharray="' . $len . ' ' . $c . '" transform="rotate(-90 44 44)"/>'
        . '<text x="44" y="50" text-anchor="middle" font-family="Inter, sans-serif" font-size="18" font-weight="700" fill="' . $fill . '">' . e($label) . '</text></svg>';
}
function empty_state(string $text): string { return '<div class="empty">' . e($text) . '</div>'; }
// Text a leader typed, with [placeholders] shown softly
function soft(?string $text): string {
    $h = nl2br(e($text));
    return preg_replace('/\[([^\]]+)\]/', '<span class="ph">[$1]</span>', $h);
}

// ---------- Chat ----------
function chat_is_mine(array $m, bool $staff, ?int $me): bool { return $staff ? ((int)$m['staff'] === 1 && !$m['person_id']) : ((int)$m['person_id'] === (int)$me); }
function chat_bubble(array $m, bool $mine): string {
    return '<div class="' . ($mine ? 'mine' : 'them') . '" data-id="' . (int)$m['id'] . '">' . ($mine ? '' : '<div class="small" style="font-weight:600;padding-bottom:2px">' . e($m['author']) . ($m['staff'] ? ' · Leader' : '') . '</div>')
        . nl2br(e($m['body'])) . '<div class="small" style="opacity:.6;padding-top:4px">' . date('M j, g:i A', strtotime($m['created_at'])) . '</div></div>';
}
// Messages plus a composer that sends without reloading and checks for new messages every few seconds
function chat_box(int $trip_id, string $thread, bool $staff, ?int $me, string $empty, array $extra = []): void {
    $msgs = chat_messages($trip_id, $thread);
    $items = [];
    foreach ($msgs as $m) $items[] = [$m['created_at'], chat_bubble($m, chat_is_mine($m, $staff, $me))];
    foreach ($extra as $x) $items[] = $x; // [created_at, html] for announcements shown in the team thread
    usort($items, fn($a, $b) => strcmp($a[0], $b[0]));
    $last = $msgs ? (int)end($msgs)['id'] : 0; ?>
    <div class="bubbles" data-chat="<?= e("/chat.php?trip=$trip_id&thread=$thread") ?>" data-after="<?= $last ?>" style="max-height:560px;overflow-y:auto">
      <?php foreach ($items as [, $html]) echo $html; ?>
      <?= $items ? '' : '<div class="empty" data-empty>' . e($empty) . '</div>' ?>
    </div>
    <form class="composer" method="post" action="/action.php" data-chat-form>
      <?= csrf() ?><input type="hidden" name="action" value="chat_send"><input type="hidden" name="trip_id" value="<?= $trip_id ?>"><input type="hidden" name="thread" value="<?= e($thread) ?>">
      <input type="text" name="body" placeholder="Write a message" autocomplete="off" required maxlength="4000" aria-label="Message">
      <button class="btn btn-primary" type="submit">Send</button>
    </form>
<?php }
