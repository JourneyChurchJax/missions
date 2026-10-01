<?php
// Shared page shell: <head>, the two headers, the footer, and small view helpers.

function logo(int $w = 200, bool $dark = false, string $href = '/'): string {
    $img = $dark ? 'horizontal-ember_cream.png' : 'horizontal-ember_ink.png';
    $letters = implode('', array_map(fn($c) => "<span>$c</span>", str_split('MISSIONS')));
    return '<a class="jm-logo' . ($dark ? ' on-dark' : '') . '" href="' . e($href) . '" style="--w:' . $w . 'px" aria-label="Journey Missions home">'
        . '<img src="/assets/logo/' . $img . '" alt="Journey Church">'
        . '<span class="under" aria-hidden="true">' . $letters . '</span></a>';
}

function page_open(string $title): void {
    $who = ($_SESSION['view'] ?? 'staff') === 'staff' ? 'Staff view' : 'Traveler view · ' . e(current_actor_name()); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Journey Missions</title>
<link rel="icon" href="/assets/logo/mark-ember.png">
<link rel="stylesheet" href="/assets/app.css?v=7">
</head>
<body>
<div class="preview"><?= demo_on() ? '<strong>Demo data on</strong> · ' : '' ?>Preview · <?= $who ?> · <a href="/">Switch view</a><?= ($_SESSION['view'] ?? 'staff') === 'staff' ? ' · <a href="/admin/settings.php?s=demo">Demo data</a>' : '' ?> · <a href="/signout.php">Sign out</a></div>
<?php }

function page_close(): void { $f = flash(); ?>
<div class="toast<?= $f ? ' show' : '' ?>" role="status" aria-live="polite"><?= e($f) ?></div>
<script src="/assets/app.js?v=5"></script>
</body>
</html>
<?php }

// Public pages (apply, reference): no preview banner, no app nav.
function public_open(string $title): void { ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Journey Missions</title>
<link rel="icon" href="/assets/logo/mark-ember.png">
<link rel="stylesheet" href="/assets/app.css?v=7">
</head>
<body class="pub">
<header class="pub-top"><?= logo(210, false, 'https://journeychurch.org') ?></header>
<?php }

function admin_header(string $active): void {
    $tabs = ['trips' => ['Trips', '/admin/'], 'people' => ['People', '/admin/people.php'], 'apps' => ['Applications', '/admin/applications.php'],
             'giving' => ['Giving', '/admin/giving.php'], 'reports' => ['Reports', '/admin/reports.php'], 'settings' => ['Settings', '/admin/settings.php']]; ?>
<header class="top">
  <div class="topin">
    <?= logo(230, false, '/admin/') ?>
    <form class="search" role="search" action="/admin/people.php">
      <label class="sr" for="q">Find</label><span style="font-size:15px;font-weight:600">Find</span>
      <input id="q" name="q" type="search" placeholder="A trip, a person, a donor" value="<?= e($_GET['q'] ?? '') ?>">
      <button class="sbtn" type="submit">Search</button>
    </form>
    <div class="right"><a class="me" href="/admin/settings.php" aria-label="Account">AH</a></div>
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
    $me = person(acting_person_id() ?? 0); ?>
<header class="top">
  <div class="topin m">
    <?= logo(220, false, '/trip/') ?>
    <nav class="seg sm" aria-label="Trip sections">
    <?php foreach ($tabs as $k => [$label, $href]): ?>
      <a class="tab<?= $k === $active ? ' on' : '' ?>" href="<?= $href ?>"<?= $k === $active ? ' aria-current="page"' : '' ?>><?= $label ?></a>
    <?php endforeach; ?>
    </nav>
    <div class="right"><a class="me" href="/trip/profile.php" aria-label="My profile"><?= $me ? initials(full_name($me)) : '?' ?></a></div>
  </div>
</header>
<?php }

function date_box(string $mon, string $day): string {
    return '<div class="date"><small>' . e($mon) . '</small><b>' . e($day) . '</b></div>';
}
function date_box_for(?string $d): string { return $d ? date_box(strtoupper(date('M', strtotime($d))), date('j', strtotime($d))) : date_box('—', '—'); }
function bar(int $pct, bool $dark = false): string {
    return '<div class="bar' . ($dark ? ' on-dark' : '') . '" role="progressbar" aria-valuenow="' . $pct . '" aria-valuemin="0" aria-valuemax="100"><span style="width:' . max(0, min(100, $pct)) . '%"></span></div>';
}
function ring(int $done, int $total, int $size = 88, bool $dark = false, ?string $label = null): string {
    $label = $label ?? ($done . '/' . $total);
    $c = 238.8; $len = $total ? round($c * min($done, $total) / $total, 1) : 0;
    $track = $dark ? 'rgba(247,244,240,0.16)' : '#ece7df'; $fill = $dark ? '#f7f4f0' : '#0a0a0a';
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 88 88" role="img" aria-label="' . $done . ' of ' . $total . ' done" style="flex-shrink:0">'
        . '<circle cx="44" cy="44" r="38" fill="none" stroke="' . $track . '" stroke-width="10"/>'
        . '<circle cx="44" cy="44" r="38" fill="none" stroke="' . $fill . '" stroke-width="10" stroke-linecap="round" stroke-dasharray="' . $len . ' ' . $c . '" transform="rotate(-90 44 44)"/>'
        . '<text x="44" y="50" text-anchor="middle" font-family="Inter, sans-serif" font-size="18" font-weight="700" fill="' . $fill . '">' . e($label) . '</text></svg>';
}
function empty_state(string $text): string { return '<div class="cell muted">' . e($text) . '</div>'; }
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
