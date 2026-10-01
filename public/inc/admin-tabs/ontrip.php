<?php
// Trip workspace · On the trip: headcounts and the incident log. Built for a leader's phone.
$checks = all('SELECT * FROM checkins WHERE trip_id = ? ORDER BY id DESC LIMIT 20', [$id]);
$cid = (int)($_GET['c'] ?? ($checks[0]['id'] ?? 0));
$cur = $cid ? one('SELECT * FROM checkins WHERE id = ? AND trip_id = ?', [$cid, $id]) : null;
$marks = []; if ($cur) foreach (all('SELECT * FROM checkin_marks WHERE checkin_id = ?', [$cid]) as $mk) $marks[(int)$mk['person_id']] = $mk['status'];
$incidents = all('SELECT * FROM incidents WHERE trip_id = ? ORDER BY happened_at DESC', [$id]);
$kinds = ['medical' => 'Medical', 'safety' => 'Safety', 'behavior' => 'Behavior', 'lost' => 'Lost item or passport', 'other' => 'Other'];
$here_n = count(array_filter($marks, fn($s) => $s === 'here')); $miss_n = count(array_filter($marks, fn($s) => $s === 'missing'));
?>
<div class="split">
  <section class="tile xl" style="gap:16px" data-checkin>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
      <div><strong style="font-size:20px">Headcount</strong><div class="muted small"><?= $cur ? e($cur['label']) . ' · started ' . fdate($cur['created_at'], 'M j, g:i A') . ' by ' . e($cur['created_by']) : 'Count everyone at the airport, on the bus, or after an outing.' ?></div></div>
      <form class="chips" method="post" action="/action.php" style="gap:8px"><?= csrf() ?><input type="hidden" name="action" value="checkin_start"><input type="hidden" name="trip_id" value="<?= $id ?>">
        <input class="pill" type="text" name="label" placeholder="Airport, bus, dinner…" style="min-width:180px"><button class="btn btn-primary" type="submit" style="height:36px">New headcount</button></form>
    </div>
    <?php if ($cur): ?>
      <div style="display:flex;gap:28px"><div><div class="disp" style="font-size:40px"><span data-here><?= $here_n ?></span><span class="muted" style="font-size:22px"> / <?= count($team) ?></span></div><div class="muted small">Here</div></div><div><div class="disp" style="font-size:40px;color:var(--ember-small)" data-missing><?= $miss_n ?></div><div class="muted small">Missing</div></div></div>
      <div class="group" style="box-shadow:none;background:var(--cream)">
      <?php foreach ($team as $m): $pid = (int)$m['person_id']; $st = $marks[$pid] ?? ''; ?>
        <div class="cell" data-row data-state="<?= $st ?>"><span class="av"><?= initials(full_name($m)) ?></span><div class="grow"><strong><?= e(full_name($m)) ?></strong><div class="muted small"><?= e(ucfirst($m['role'])) ?><?= $m['phone'] ? ' · <a href="tel:' . e($m['phone']) . '">' . e($m['phone']) . '</a>' : '' ?></div></div>
          <form method="post" action="/action.php" data-mark style="display:flex;gap:6px"><?= csrf() ?><input type="hidden" name="action" value="checkin_mark"><input type="hidden" name="checkin_id" value="<?= $cid ?>"><input type="hidden" name="person_id" value="<?= $pid ?>">
            <button class="btn<?= $st === 'here' ? ' btn-dark' : '' ?>" name="status" value="here" style="height:38px">Here</button><button class="btn<?= $st === 'missing' ? ' btn-dark' : '' ?>" name="status" value="missing" style="height:38px">Missing</button></form></div>
      <?php endforeach; ?>
      </div>
      <?php if (count($checks) > 1): ?><div class="muted small">Earlier: <?php foreach (array_slice($checks, 0, 6) as $c) if ((int)$c['id'] !== $cid) echo '<a href="' . e($here) . '&c=' . (int)$c['id'] . '">' . e($c['label']) . '</a> · '; ?></div><?php endif; ?>
    <?php endif; ?>
  </section>

  <aside style="display:flex;flex-direction:column;gap:20px">
    <section><div class="gh"><span>Incident log</span><span class="muted small">Staff and leaders only</span></div><div class="group">
      <?php foreach ($incidents as $x): $who = $x['person_id'] ? person((int)$x['person_id']) : null; ?>
        <div class="cell" style="flex-wrap:wrap;align-items:flex-start"><div class="grow"><strong><?= e($kinds[$x['kind']] ?? 'Other') ?><?= $who ? ' · ' . e(full_name($who)) : '' ?></strong>
          <div class="muted small"><?= fdate($x['happened_at'], 'M j, g:i A') ?> · <?= e(ucfirst($x['severity'])) ?> · by <?= e($x['reported_by']) ?><?= $x['parent_notified'] ? ' · parent told' : '' ?></div>
          <div style="padding-top:4px"><?= nl2br(e($x['description'])) ?></div><?php if ($x['action_taken']): ?><div class="muted small" style="padding-top:4px">What we did: <?= e($x['action_taken']) ?></div><?php endif; ?></div>
          <span class="pill<?= $x['resolved'] ? ' pill-ok' : '' ?>"><?= $x['resolved'] ? 'Resolved' : 'Open' ?></span>
          <form method="post" action="/action.php" style="width:100%;text-align:right"><?= csrf() ?><input type="hidden" name="action" value="incident_resolve"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="link-btn"><?= $x['resolved'] ? 'Reopen' : 'Mark resolved' ?></button></form></div>
      <?php endforeach; ?>
      <?= $incidents ? '' : empty_state('Nothing logged. Good.') ?>
    </div></section>
    <details class="add"><summary>Log an incident</summary><div class="body">
      <form class="form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="incident_save"><input type="hidden" name="trip_id" value="<?= $id ?>">
        <div class="r2"><label class="lab">What kind<select name="kind"><?php foreach ($kinds as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></label><label class="lab">How serious<select name="severity"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></label></div>
        <div class="r2"><label class="lab">Who<select name="person_id"><option value="0">No one person</option><?php foreach ($team as $m): ?><option value="<?= (int)$m['person_id'] ?>"><?= e(full_name($m)) ?></option><?php endforeach; ?></select></label><label class="lab">When<input type="datetime-local" name="happened_at" value="<?= date('Y-m-d\TH:i') ?>"></label></div>
        <label class="lab">What happened<textarea name="description" rows="3" required></textarea></label>
        <label class="lab">What we did<textarea name="action_taken" rows="2"></textarea></label>
        <label class="chk"><input type="checkbox" name="parent_notified" value="1"> A parent or emergency contact was told</label>
        <button class="btn btn-dark" type="submit">Save</button>
      </form></div></details>
    <section class="note"><strong>Emergency numbers</strong><div class="muted small"><?php $g = one("SELECT body FROM guide WHERE trip_id = ? AND section = 'contacts'", [$id]); echo $g ? nl2br(e($g['body'])) : 'Add contacts in the Trip guide.'; ?></div></section>
  </aside>
</div>
