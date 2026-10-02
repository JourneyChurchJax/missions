<?php
// Everything on a traveler's checklist, with the button to do each one
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/member.php';
[$done, $total] = readiness($tid, $me_id);
page_open('My checklist');
member_header('trip');
?>
<main class="main m narrow" id="main">
  <div class="head"><div class="sub"><div class="muted strong"><?= e($t['name']) ?></div><h1 class="disp">My checklist</h1><div class="muted"><?= $done ?> of <?= $total ?> done</div></div></div>
  <section><h2 class="gh">To do</h2><div class="group">
    <?php foreach ($open_tasks as $k) include dirname(__DIR__) . '/inc/_my_task.php'; ?>
    <?php foreach ($unread as $f): ?><a class="cell" href="/trip/documents.php"><span class="check" aria-hidden="true">✓</span><div class="grow"><strong>Read: <?= e($f['title']) ?></strong><div class="muted small">Everyone on the team reads and agrees</div></div><span class="chev" aria-hidden="true">›</span></a><?php endforeach; ?>
    <?= $open_tasks || $unread ? '' : empty_state('All done. Nice work.') ?>
  </div></section>
  <?php $finished = array_values(array_filter($my_tasks, fn($k) => $k['done_at'])); if ($finished): ?>
  <section><h2 class="gh">Done</h2><div class="group"><?php foreach ($finished as $k) include dirname(__DIR__) . '/inc/_my_task.php'; ?></div></section>
  <?php endif; ?>
</main>
<?php page_close(); ?>
