<?php
// Signed documents: everyone on one task (?task=ID) or everything one person signed (?person=ID). Prints as a record.
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
$task = gi('task') ? one('SELECT * FROM tasks WHERE id = ?', [gi('task')]) : null;
$person = gi('person') ? person(gi('person')) : null;
if ($task) require_trip((int)$task['trip_id'], 'tasks'); else require_staff();
audit('signatures_view', $task ? 'tasks' : 'people', $task ? (int)$task['id'] : (int)($person['id'] ?? 0));
if ($task) {
    $t = trip((int)$task['trip_id']);
    $rows = [];
    foreach (travelers((int)$t['id']) as $m) {
        $pid = (int)$m['person_id'];
        if ($task['minors_only'] && !is_minor(person($pid), $t['start_date'])) continue;
        $rows[] = [$m, task_signatures((int)$task['id'], $pid), needs_parent_signature($task, $pid)];
    }
    $title = $task['title']; $sub = $t['name'] . ' · ' . count(array_filter($rows, fn($r) => $r[1])) . ' of ' . count($rows) . ' signed';
} elseif ($person) {
    $sigs = all('SELECT s.*, t.title AS task_title, tr.name AS trip_name FROM signatures s JOIN tasks t ON t.id = s.task_id JOIN trips tr ON tr.id = t.trip_id WHERE s.person_id = ? ORDER BY s.signed_at DESC', [$person['id']]);
    $title = full_name($person) . ': signatures'; $sub = count($sigs) . ' signed';
} else { header('Location: /admin/'); exit; }
$sigcard = function (array $s) { ?>
  <div class="tile" style="gap:6px;background:var(--cream);box-shadow:none">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><strong><?= e($s['signer_name']) ?></strong><span class="pill<?= $s['signer_role'] === 'parent' ? '' : ' pill-ok' ?>"><?= $s['signer_role'] === 'parent' ? 'Parent' : 'Traveler' ?></span></div>
    <?php if ($s['sig_image']): ?><img src="<?= e($s['sig_image']) ?>" alt="Signature of <?= e($s['signer_name']) ?>" style="max-width:280px;background:#fff;border-radius:var(--r-sm)"><?php else: ?><div class="muted small">Typed agreement (recorded before drawn signatures)</div><?php endif; ?>
    <div class="muted small"><?= e($s['doc_title']) ?> · <?= fdate($s['signed_at'], 'F j, Y g:i A') ?> · IP <?= e($s['ip']) ?></div>
    <details><summary class="muted small" style="cursor:pointer">What they agreed to</summary><div class="small" style="padding-top:6px"><?= nl2br(e($s['agreement'])) ?></div></details>
  </div>
<?php };
page_open($title);
admin_header($task ? 'trips' : 'people');
?>
<main class="main" style="max-width:1000px">
  <div class="bar-head"><div><a class="muted small" href="<?= $task ? '/admin/trip.php?id=' . (int)$t['id'] . '&tab=tasks' : '/admin/person.php?id=' . (int)$person['id'] ?>" style="text-decoration:none">‹ Back</a><h1><?= e($title) ?></h1><div class="muted small"><?= e($sub) ?></div></div>
    <button class="btn" type="button" onclick="window.print()">Print</button></div>
  <?php if ($task): ?>
    <div class="group">
    <?php foreach ($rows as [$m, $sigs, $needp]): $roles = array_column($sigs, 'signer_role'); ?>
      <div class="cell" style="flex-direction:column;align-items:stretch;gap:10px">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><strong><?= e(full_name($m)) ?></strong>
          <span class="pill<?= in_array('traveler', $roles, true) && (!$needp || in_array('parent', $roles, true)) ? ' pill-ok' : '' ?>"><?= !$sigs ? 'Not signed' : (in_array('traveler', $roles, true) && (!$needp || in_array('parent', $roles, true)) ? 'Complete' : ($needp && !in_array('parent', $roles, true) ? 'Waiting for a parent' : 'Waiting for the traveler')) ?></span></div>
        <?php if ($sigs): ?><div class="g2" style="gap:12px"><?php foreach ($sigs as $s) $sigcard($s); ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?= $rows ? '' : '<div class="empty">No travelers yet.</div>' ?>
    </div>
  <?php else: ?>
    <div class="g2" style="gap:12px"><?php foreach ($sigs as $s) $sigcard($s); ?></div>
    <?= $sigs ? '' : '<div class="empty">Nothing signed yet.</div>' ?>
  <?php endif; ?>
</main>
<?php page_close(); ?>
