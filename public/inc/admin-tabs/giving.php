<?php
// Trip workspace · Giving: each traveler's goal, gifts, payments and what's left
$goals = all('SELECT * FROM goals WHERE trip_id = ? ORDER BY due_date', [$id]);
$next = null; foreach ($goals as $g) if ($g['due_date'] >= date('Y-m-d')) { $next = $g; break; }
$team_total = team_gifts($id);
$recent = gifts(['trip' => $id], 12);
?>
<section class="g4">
  <div class="tile dark" style="padding:24px"><div class="k">Raised</div><div class="disp" style="font-size:48px"><?= money(trip_raised($id)) ?></div><div style="color:rgba(247,244,240,.85)">of <?= money(trip_goal($t)) ?> · <?= pct(trip_raised($id), trip_goal($t)) ?>%</div></div>
  <div class="tile"><div class="k">Next milestone</div><div class="disp v"><?= $next ? ($next['kind'] === 'percent' ? (int)$next['amount'] . '%' : money((float)$next['amount'])) : '—' ?></div><div class="muted small"><?= $next ? 'by ' . fdate($next['due_date'], 'M j') : 'No upcoming goals' ?></div></div>
  <div class="tile"><div class="k">Behind</div><div class="disp v"><?php $behind = 0; if ($next) foreach ($trav as $m) { $need = $next['kind'] === 'percent' ? member_goal($t, $m) * $next['amount'] / 100 : (float)$next['amount']; if ((float)$m['raised'] < $need) $behind++; } echo $behind; ?></div><div class="muted small">for the next milestone</div></div>
  <div class="tile"><div class="k">Team gifts</div><div class="disp v"><?= money($team_total) ?></div><div class="muted small">not tied to one person</div></div>
</section>
<section>
  <div class="gh"><span>By traveler</span><a href="/admin/giving.php?trip=<?= $id ?>">All gifts for <?= e($t['name']) ?></a></div>
  <div class="group tbl"><table>
    <thead><tr><th>Traveler</th><th>Goal</th><th>Gifts</th><th>Paid</th><th>Progress</th><th class="num">Still needs</th></tr></thead>
    <tbody>
    <?php foreach ($trav as $m): $goal = member_goal($t, $m); $need = $next ? ($next['kind'] === 'percent' ? $goal * $next['amount'] / 100 : (float)$next['amount']) : 0; $pid = (int)$m['person_id']; ?>
      <tr><td><strong><?= e(full_name($m)) ?></strong><?php if ($next && (float)$m['raised'] < $need): ?><div class="muted small"><?= money($need - (float)$m['raised']) ?> behind the <?= fdate($next['due_date'], 'M j') ?> milestone</div><?php endif; ?></td>
      <td><?= money($goal) ?></td><td><?= money(member_gifts($id, $pid)) ?></td><td><?= money(member_paid($id, $pid)) ?></td>
      <td style="min-width:160px"><?= bar(pct((float)$m['raised'], $goal)) ?></td>
      <td class="num"><?= (float)$m['raised'] >= $goal ? '<span class="pill pill-ok">Fully funded</span>' : '<strong>' . money($goal - (float)$m['raised']) . '</strong>' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<div class="split">
  <section><div class="gh">Recent gifts</div><div class="group">
    <?php foreach ($recent as $g): ?><div class="cell"><div class="grow"><strong><?= e(donor_name($g['donor_id'] ? donor((int)$g['donor_id']) : null)) ?></strong><div class="muted small"><?= e(gift_for($g)) ?> · <?= fdate($g['gift_date'], 'M j') ?> · <?= e(GIFT_METHODS[$g['method']] ?? '') ?></div></div><strong><?= money((float)$g['amount'], 2) ?></strong></div><?php endforeach; ?>
    <?= $recent ? '' : empty_state('No gifts yet') ?>
  </div></section>
  <aside class="sticky"><details class="add" open><summary>Record a gift</summary><div class="body"><?php $g = []; $for_preset = 't' . $id; include dirname(__DIR__) . '/_gift_form.php'; ?></div></details></aside>
</div>
