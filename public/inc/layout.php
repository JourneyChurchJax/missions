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
<link rel="stylesheet" href="/assets/app.css?v=4">
</head>
<body>
<div class="preview">Preview · <?= $who ?> · <a href="/">Switch view</a> · <a href="/signout.php">Sign out</a></div>
<?php }

function page_close(): void { $f = flash(); ?>
<div class="toast<?= $f ? ' show' : '' ?>" role="status" aria-live="polite"><?= e($f) ?></div>
<script src="/assets/app.js?v=4"></script>
</body>
</html>
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
