<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/member.php';
$posts = all('SELECT * FROM announcements WHERE trip_id = ? ORDER BY id', [$tid]);
$team = members($tid);
$mine = 'p' . $me_id;
$th = g('th') === 'leaders' ? $mine : 'team';
$last = fn(string $k) => one('SELECT * FROM chat WHERE trip_id = ? AND thread = ? ORDER BY id DESC LIMIT 1', [$tid, $k]);
$lt = $last('team'); $lp = $last($mine);
// Leader announcements show inside the team conversation
$extra = array_map(fn($p) => [$p['created_at'], '<div class="them announcement"><div class="small" style="font-weight:600;padding-bottom:2px">Announcement · ' . e($p['author']) . '</div>' . ($p['title'] ? '<strong>' . e($p['title']) . '</strong><br>' : '') . soft($p['body']) . '<div class="small" style="opacity:.6;padding-top:4px">' . date('M j, g:i A', strtotime($p['created_at'])) . '</div></div>'], $posts);
page_open('Messages');
member_header('messages');
?>
<main class="main m" id="main">
  <h1 class="disp" style="margin:0;font-size:clamp(1.875rem,3vw,2.375rem)">Messages</h1>
  <div class="msgs">
    <aside class="convs">
      <a class="conv<?= $th === 'team' ? ' on' : '' ?>" href="/trip/messages.php"><span class="av dark" style="width:44px;height:44px"><?= e(strtoupper(substr($t['name'], 0, 2))) ?></span><div class="grow"><div style="display:flex;justify-content:space-between"><strong><?= e($t['name']) ?> team</strong><span class="muted small"><?= $lt ? fdate($lt['created_at'], 'M j') : '' ?></span></div><div class="last"><?= $lt ? e($lt['author'] . ': ' . $lt['body']) : 'Everyone on the trip' ?></div></div></a>
      <a class="conv<?= $th === $mine ? ' on' : '' ?>" href="/trip/messages.php?th=leaders"><span class="av" style="width:44px;height:44px"><?= $leader ? initials(full_name($leader)) : 'L' ?></span><div class="grow"><div style="display:flex;justify-content:space-between"><strong>Your leaders</strong><span class="muted small"><?= $lp ? fdate($lp['created_at'], 'M j') : '' ?></span></div><div class="last"><?= $lp ? e($lp['author'] . ': ' . $lp['body']) : 'Private questions' ?></div></div></a>
      <div class="gh roster" style="padding:14px 10px 6px">Your team</div>
      <?php foreach ($team as $m): ?><div class="conv roster" style="min-height:52px"><span class="av" style="width:36px;height:36px;font-size:13px"><?= initials(full_name($m)) ?></span><div class="grow"><strong style="font-size:15px"><?= e(full_name($m)) ?></strong><div class="muted small"><?= e(ucfirst($m['role'])) ?></div></div></div><?php endforeach; ?>
    </aside>
    <section class="thread">
      <div style="padding:18px 24px;border-bottom:1px solid var(--sand);display:flex;justify-content:space-between;align-items:center;gap:12px">
        <div><strong style="font-size:18px"><?= $th === 'team' ? e($t['name']) . ' team' : 'Your leaders' ?></strong><div class="muted small"><?= $th === 'team' ? 'Everyone on the trip sees this' : 'Only you and the trip leaders see this' ?></div></div>
        <a href="/trip/schedule.php" style="font-size:15px">Schedule ›</a>
      </div>
      <?php chat_box($tid, $th, false, $me_id, $th === 'team' ? 'No messages yet. Say hi to your team.' : 'Ask your leaders anything. Only they will see it.', $th === 'team' ? $extra : []); ?>
    </section>
  </div>
</main>
<?php page_close(); ?>
