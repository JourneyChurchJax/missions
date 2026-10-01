<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/member.php';
$posts = all('SELECT * FROM announcements WHERE trip_id = ? ORDER BY id', [$tid]);
$team = members($tid);
page_open('Messages');
member_header('messages');
?>
<main class="main m">
  <h1 class="disp" style="margin:0;font-size:clamp(1.875rem,3vw,2.375rem)">Messages</h1>
  <div class="msgs">
    <aside class="convs">
      <a class="conv on" href="/trip/messages.php"><span class="av dark" style="width:44px;height:44px"><?= e(strtoupper(substr($t['name'], 0, 2))) ?></span><div class="grow"><div style="display:flex;justify-content:space-between"><strong><?= e($t['name']) ?> team</strong><span class="muted small"><?= $posts ? fdate(end($posts)['created_at'], 'M j') : '' ?></span></div><div class="last"><?= $posts ? e(end($posts)['body']) : 'Updates from your leaders' ?></div></div></a>
      <div class="gh" style="padding:14px 10px 6px">Your team</div>
      <?php foreach ($team as $m): ?><div class="conv" style="min-height:52px"><span class="av" style="width:36px;height:36px;font-size:13px"><?= initials(full_name($m)) ?></span><div class="grow"><strong style="font-size:15px"><?= e(full_name($m)) ?></strong><div class="muted small"><?= e(ucfirst($m['role'])) ?></div></div></div><?php endforeach; ?>
    </aside>
    <section class="thread">
      <div style="padding:18px 24px;border-bottom:1px solid var(--sand);display:flex;justify-content:space-between;align-items:center;gap:12px">
        <div><strong style="font-size:18px"><?= e($t['name']) ?> team</strong><div class="muted small">Updates from your leaders</div></div>
        <a href="/trip/schedule.php" style="font-size:15px">Schedule ›</a>
      </div>
      <div class="bubbles">
        <?php foreach ($posts as $p): ?>
          <div class="muted small" style="text-align:center;font-weight:600"><?= fdate($p['created_at'], 'l, M j') ?></div>
          <div class="them"><div class="small" style="font-weight:600;padding-bottom:2px"><?= e($p['author']) ?></div><?php if ($p['title']): ?><strong><?= e($p['title']) ?></strong><br><?php endif; ?><?= soft($p['body']) ?></div>
        <?php endforeach; ?>
        <?= $posts ? '' : '<div class="empty">No updates yet. Your leaders will post here.</div>' ?>
      </div>
      <div class="composer"><div class="muted small">Two-way chat with your team arrives in phase 4. For now, reply to your leader by text or email.</div></div>
    </section>
  </div>
</main>
<?php page_close(); ?>
