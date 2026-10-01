<?php
// Trip workspace · Giving (amounts are sample until gifts are built in phase 3)
$goals = all('SELECT * FROM goals WHERE trip_id = ? ORDER BY due_date', [$id]);
$next = null; foreach ($goals as $g) if ($g['due_date'] >= date('Y-m-d')) { $next = $g; break; }
?>
<section class="g4">
  <div class="tile dark" style="padding:24px"><div class="k">Raised</div><div class="disp" style="font-size:48px"><?= money(trip_raised($id)) ?></div><div style="color:rgba(247,244,240,.85)">of <?= money(trip_goal($t)) ?></div></div>
  <div class="tile"><div class="k">Next milestone</div><div class="disp v"><?= $next ? ($next['kind'] === 'percent' ? (int)$next['amount'] . '%' : money((float)$next['amount'])) : '—' ?></div><div class="muted small"><?= $next ? 'by ' . fdate($next['due_date'], 'M j') : 'No upcoming goals' ?></div></div>
  <div class="tile"><div class="k">Behind</div><div class="disp v"><?php $behind = 0; if ($next) foreach ($trav as $m) { $need = $next['kind'] === 'percent' ? member_goal($t, $m) * $next['amount'] / 100 : (float)$next['amount']; if ((float)$m['raised'] < $need) $behind++; } echo $behind; ?></div><div class="muted small">for the next milestone</div></div>
  <div class="tile"><div class="k">Goal per person</div><div class="disp v"><?= money((float)$t['cost_per_person']) ?></div></div>
</section>
<section>
  <div class="gh"><span>By traveler</span><a href="/admin/giving.php">All giving</a></div>
  <div class="group tbl"><table>
    <thead><tr><th>Traveler</th><th>Goal</th><th>Raised</th><th>Progress</th><th class="num">Next milestone</th></tr></thead>
    <tbody>
    <?php foreach ($trav as $m): $goal = member_goal($t, $m); $need = $next ? ($next['kind'] === 'percent' ? $goal * $next['amount'] / 100 : (float)$next['amount']) : 0; ?>
      <tr><td><strong><?= e(full_name($m)) ?></strong></td><td><?= money($goal) ?></td><td><?= money((float)$m['raised']) ?></td>
      <td style="min-width:180px"><?= bar(pct((float)$m['raised'], $goal)) ?></td>
      <td class="num"><?= $next ? ((float)$m['raised'] >= $need ? '<span class="pill pill-ok">On track</span>' : money($need - (float)$m['raised']) . ' to go') : '—' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="muted small" style="padding:8px 18px 0">Sample amounts. Real gifts, checks and Stripe arrive in phases 3 and 5.</div>
</section>
