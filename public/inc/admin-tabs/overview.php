<?php
// Trip workspace · Overview. Variables from admin/trip.php: $t, $id, $team, $trav, $here
[$tdone, $ttotal] = trip_task_pct($id);
$budget = trip_budget($id); $goal = trip_goal($t); $raised = trip_raised($id);
$next_meeting = one('SELECT * FROM meetings WHERE trip_id = ? AND starts_at >= ? ORDER BY starts_at LIMIT 1', [$id, date('Y-m-d')]);
$next_tasks = all('SELECT * FROM tasks WHERE trip_id = ? AND due_date >= ? ORDER BY due_date LIMIT 3', [$id, date('Y-m-d')]);
$alerts = trip_alerts($t);
$must = all('SELECT * FROM files WHERE trip_id = ? AND must_ack = 1 AND person_id IS NULL', [$id]);
?>
<section class="g5">
  <a class="tile" href="?id=<?= $id ?>&tab=team" style="text-decoration:none"><div class="k">Team</div><div class="disp" style="font-size:34px"><?= count($trav) ?></div><div class="muted small"><?= count($trav) ?> traveling<?= $t['max_team'] ? ' · max ' . (int)$t['max_team'] : '' ?></div></a>
  <a class="tile" href="?id=<?= $id ?>&tab=team" style="text-decoration:none"><div class="k">Ready to go</div><div class="disp" style="font-size:34px"><?= trip_ready_count($id) ?> of <?= count($trav) ?></div><div class="muted small">Every step done</div></a>
  <a class="tile" href="?id=<?= $id ?>&tab=tasks" style="text-decoration:none"><div class="k">Tasks done</div><div class="disp" style="font-size:34px"><?= pct($tdone, max(1, $ttotal)) ?>%</div><div class="muted small"><?= $tdone ?> of <?= $ttotal ?></div></a>
  <a class="tile" href="?id=<?= $id ?>&tab=budget" style="text-decoration:none"><div class="k">Budget</div><div class="disp" style="font-size:34px"><?= money($budget) ?></div><div class="muted small"><?= money($budget / max(1, count($trav))) ?> a person</div></a>
  <a class="tile" href="?id=<?= $id ?>&tab=giving" style="text-decoration:none"><div class="k">Raised</div><div class="disp" style="font-size:34px"><?= money($raised) ?></div><div class="muted small">of <?= money($goal) ?></div></a>
</section>

<div class="split">
  <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
    <section>
      <div class="gh"><span>Team readiness</span><span style="display:flex;gap:18px"><a href="?id=<?= $id ?>&tab=team">Manage team</a><a href="/admin/reports.php?trip=<?= $id ?>">Roster and exports</a></span></div>
      <div class="group tbl"><table>
        <thead><tr><th>Person</th><th>Role</th><th>Passport</th><th>Emergency</th><th>Before the trip</th><th class="num">Raised</th></tr></thead>
        <tbody>
        <?php foreach ($team as $m): [$d, $n] = readiness($id, (int)$m['person_id']); ?>
          <tr>
            <td><a class="who" href="/admin/person.php?id=<?= (int)$m['person_id'] ?>&trip=<?= $id ?>" style="text-decoration:none"><span class="av"><?= initials(full_name($m)) ?></span><?= e(full_name($m)) ?></a></td>
            <td><?= e(ucfirst($m['role'])) ?><?= $m['traveling'] ? '' : ' <span class="muted small">· not traveling</span>' ?></td>
            <td><span class="pill<?= passport_ok($m, $t) ? ' pill-ok' : '' ?>"><?= passport_ok($m, $t) ? 'Valid to ' . fdate($m['passport_expires'], 'Y') : (empty($m['passport_expires']) ? 'Missing' : 'Expires too soon') ?></span></td>
            <td><span class="pill<?= $m['ec1_name'] ? ' pill-ok' : '' ?>"><?= $m['ec1_name'] ? 'On file' : 'Missing' ?></span></td>
            <td><div style="display:flex;align-items:center;gap:10px;min-width:140px"><div style="flex-grow:1"><?= bar(pct($d, max(1, $n))) ?></div><span class="small"><?= $d ?>/<?= $n ?></span></div></td>
            <td class="num"><strong><?= money((float)$m['raised']) ?></strong> <span class="muted small">/ <?= money(member_goal($t, $m)) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$team): ?><tr><td colspan="6" class="empty">No one on this trip yet. <a href="?id=<?= $id ?>&tab=team">Add people</a></td></tr><?php endif; ?>
        </tbody>
      </table></div>
      <?php if ($t['passport_valid_through']): ?><div class="muted small" style="padding:8px 18px 0">Passports must be valid through <?= fdate($t['passport_valid_through'], 'F j, Y') ?>.</div><?php endif; ?>
    </section>

    <section>
      <div class="gh">Coming up</div>
      <div class="group">
        <?php if ($next_meeting): ?><a class="cell" href="?id=<?= $id ?>&tab=meetings"><?= date_box_for($next_meeting['starts_at']) ?><div class="grow"><strong><?= e($next_meeting['title']) ?></strong><div class="muted small"><?= fdate($next_meeting['starts_at'], 'l · g:i A') ?><?= $next_meeting['location'] ? ' · ' . e($next_meeting['location']) : '' ?></div></div><span class="pill">Meeting</span></a><?php endif; ?>
        <?php foreach ($next_tasks as $k): ?><a class="cell" href="?id=<?= $id ?>&tab=tasks"><?= date_box_for($k['due_date']) ?><div class="grow"><strong><?= e($k['title']) ?></strong><div class="muted small"><?= e(TASK_TYPES[$k['type']] ?? '') ?></div></div><span class="pill">Task</span></a><?php endforeach; ?>
        <?php foreach (all('SELECT * FROM goals WHERE trip_id = ? AND due_date >= ? ORDER BY due_date LIMIT 2', [$id, date('Y-m-d')]) as $g): ?><a class="cell" href="?id=<?= $id ?>&tab=tasks"><?= date_box_for($g['due_date']) ?><div class="grow"><strong><?= $g['kind'] === 'percent' ? (int)$g['amount'] . '% of fundraising due' : money((float)$g['amount']) . ' raised' ?></strong><div class="muted small">Fundraising goal</div></div><span class="pill">Goal</span></a><?php endforeach; ?>
      </div>
    </section>
  </div>

  <aside class="sticky" style="display:flex;flex-direction:column;gap:24px">
    <section>
      <div class="gh">Needs your attention</div>
      <div class="group">
        <?php foreach ($alerts as [$title, $sub, $link]): ?><a class="cell" href="<?= e($link) ?>"><span class="dot"></span><div class="grow"><strong><?= e($title) ?></strong><div class="muted small"><?= e($sub) ?></div></div><span class="chev">›</span></a><?php endforeach; ?>
        <?= $alerts ? '' : empty_state('All clear') ?>
      </div>
    </section>
    <?php if ($must): ?>
    <section>
      <div class="gh">Must-read documents</div>
      <div class="group">
      <?php foreach ($must as $f): $acked = (int)val('SELECT COUNT(*) FROM file_acks WHERE file_id = ? AND acked_at IS NOT NULL', [$f['id']]); ?>
        <a class="cell" href="?id=<?= $id ?>&tab=documents"><div class="grow"><strong><?= e($f['title']) ?></strong><div class="muted small"><?= $acked ?> of <?= count($trav) ?> have read and agreed</div></div><span class="chev">›</span></a>
      <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </aside>
</div>
