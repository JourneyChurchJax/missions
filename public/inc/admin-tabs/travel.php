<?php
// Trip workspace · Flights & itinerary
$flights = all("SELECT * FROM flights WHERE trip_id = ? ORDER BY CASE direction WHEN 'out' THEN 0 ELSE 1 END, departs_at", [$id]);
$itin = all('SELECT * FROM itinerary WHERE trip_id = ? ORDER BY day, id', [$id]);
$days = [];
foreach ($itin as $i) $days[$i['day']][] = $i;
$dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
$flight_fields = function (array $f) use ($dt) { ?>
  <div class="r3">
    <label class="lab">Direction<select name="direction"><option value="out"<?= ($f['direction'] ?? '') === 'out' ? ' selected' : '' ?>>Going</option><option value="home"<?= ($f['direction'] ?? '') === 'home' ? ' selected' : '' ?>>Coming home</option></select></label>
    <label class="lab">Airline<input type="text" name="airline" value="<?= e($f['airline'] ?? '') ?>"></label>
    <label class="lab">Flight number<input type="text" name="flight_no" value="<?= e($f['flight_no'] ?? '') ?>" placeholder="AA 1234"></label>
  </div>
  <div class="r2"><label class="lab">From (airport code)<input type="text" name="from_code" value="<?= e($f['from_code'] ?? '') ?>" placeholder="JAX"></label><label class="lab">To<input type="text" name="to_code" value="<?= e($f['to_code'] ?? '') ?>" placeholder="BZE"></label></div>
  <div class="r2"><label class="lab">Departs<input type="datetime-local" name="departs_at" value="<?= $dt($f['departs_at'] ?? null) ?>"></label><label class="lab">Arrives<input type="datetime-local" name="arrives_at" value="<?= $dt($f['arrives_at'] ?? null) ?>"></label></div>
  <label class="lab">Notes<input type="text" name="notes" value="<?= e($f['notes'] ?? '') ?>" placeholder="Check one bag. Meet at the AA counter at 5:30 AM."></label>
<?php };
$preview = $_SESSION['flight_preview'][$id] ?? null;
?>
<?php if ($preview): ?>
<form class="tile xl" method="post" action="/action.php" style="gap:14px"><?= csrf() ?><input type="hidden" name="action" value="flight_import"><input type="hidden" name="trip_id" value="<?= $id ?>">
  <div><strong style="font-size:18px">Check these flights</strong><div class="muted small">Fix anything that looks off, uncheck any you don't want, then save.</div></div>
  <div class="group tbl" style="box-shadow:none;background:var(--cream)"><table>
    <thead><tr><th></th><th>Flight</th><th>Airline</th><th>From</th><th>To</th><th>Departs</th><th>Arrives</th><th>Direction</th></tr></thead><tbody>
    <?php foreach ($preview as $i => $r): $n = "f[$i]"; ?><tr>
      <td><input type="checkbox" name="<?= $n ?>[use]" value="1" checked aria-label="Save this flight"></td>
      <td><input class="pill" style="width:90px" name="<?= $n ?>[flight_no]" value="<?= e($r['flight_no']) ?>"></td>
      <td><input class="pill" style="width:150px" name="<?= $n ?>[airline]" value="<?= e($r['airline']) ?>"></td>
      <td><input class="pill" style="width:64px" name="<?= $n ?>[from_code]" value="<?= e($r['from_code']) ?>" maxlength="3"></td>
      <td><input class="pill" style="width:64px" name="<?= $n ?>[to_code]" value="<?= e($r['to_code']) ?>" maxlength="3"></td>
      <td><input class="pill" type="datetime-local" name="<?= $n ?>[departs_at]" value="<?= $dt($r['departs_at']) ?>"></td>
      <td><input class="pill" type="datetime-local" name="<?= $n ?>[arrives_at]" value="<?= $dt($r['arrives_at']) ?>"></td>
      <td><select class="pill" name="<?= $n ?>[direction]"><option value="out"<?= $r['direction'] === 'out' ? ' selected' : '' ?>>Going</option><option value="home"<?= $r['direction'] === 'home' ? ' selected' : '' ?>>Coming home</option></select></td>
    </tr><?php endforeach; ?></tbody></table></div>
  <div class="actions" style="justify-content:space-between;align-items:center"><label class="chk"><input type="checkbox" name="replace_tbd" value="1" checked> Remove the "to be booked" placeholder flights</label>
    <span style="display:flex;gap:10px"><button class="btn" type="submit" form="fpclear">Cancel</button><button class="btn btn-primary" type="submit">Save flights</button></span></div>
</form>
<form id="fpclear" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="flight_preview_clear"></form>
<?php endif; ?>
<div class="split">
  <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
    <section>
      <div class="gh"><span>Group flights</span><a href="?id=<?= $id ?>&tab=team">Per-person confirmation numbers are on the Team tab</a></div>
      <div class="group">
      <?php foreach ($flights as $f): ?>
        <div class="cell" style="flex-wrap:wrap;padding:16px 18px">
          <div class="flight" style="flex-grow:1">
            <span class="pill<?= $f['direction'] === 'out' ? ' pill-ok' : '' ?>"><?= $f['direction'] === 'out' ? 'Going' : 'Home' ?></span>
            <div><div class="codes"><?= e($f['from_code']) ?> → <?= e($f['to_code']) ?></div><div class="muted small"><?= e($f['airline']) ?> <?= soft($f['flight_no']) ?> · <?= $f['departs_at'] ? fdate($f['departs_at'], 'D, M j') . (date('H:i', strtotime($f['departs_at'])) !== '00:00' ? ' · ' . fdate($f['departs_at'], 'g:i A') : ' · time TBD') : 'date TBD' ?></div><?php if ($f['notes']): ?><div class="muted small"><?= soft($f['notes']) ?></div><?php endif; ?></div>
          </div>
          <details class="edit"><summary>Edit</summary>
            <form class="form" method="post" action="/action.php" style="padding-top:10px"><?= csrf() ?><input type="hidden" name="action" value="flight_save"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="trip_id" value="<?= $id ?>">
              <?php $flight_fields($f); ?><div class="actions"><button class="btn btn-dark" type="submit">Save</button></div></form>
            <form method="post" action="/action.php" onsubmit="return confirm('Remove this flight?')" style="text-align:right;padding-top:8px"><?= csrf() ?><input type="hidden" name="action" value="flight_delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="link-btn danger">Remove</button></form>
          </details>
        </div>
      <?php endforeach; ?>
      <?= $flights ? '' : '<div class="empty">No flights yet.</div>' ?>
      </div>
    </section>

    <section>
      <div class="gh">Itinerary · what travelers see day by day</div>
      <?php foreach ($days as $day => $items): ?>
        <div class="group" style="margin-bottom:14px">
          <div class="cell" style="background:var(--tint);min-height:44px"><strong><?= fdate($day, 'l, F j') ?></strong></div>
          <?php foreach ($items as $i): ?>
            <div class="cell" style="flex-wrap:wrap"><span class="muted small" style="width:90px"><?= soft($i['time']) ?></span><div class="grow"><strong><?= e($i['title']) ?></strong><?php if ($i['detail']): ?><div class="muted small"><?= soft($i['detail']) ?></div><?php endif; ?></div>
              <form method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="itin_delete"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="link-btn danger">Remove</button></form></div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <?= $days ? '' : '<div class="group"><div class="empty">No itinerary yet.</div></div>' ?>
    </section>
  </div>

  <aside class="sticky" style="display:flex;flex-direction:column;gap:20px">
    <details class="add" open><summary>Add to the itinerary</summary><div class="body">
      <form class="form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="itin_save"><input type="hidden" name="trip_id" value="<?= $id ?>">
        <div class="r2"><label class="lab">Day<input type="date" name="day" value="<?= e($t['start_date']) ?>" required></label><label class="lab">Time<input type="text" name="time" placeholder="9:00 AM or Morning"></label></div>
        <label class="lab">What<input type="text" name="title" required></label>
        <label class="lab">Details<input type="text" name="detail"></label>
        <button class="btn btn-dark" type="submit">Add</button></form>
    </div></details>
    <details class="add"<?= $preview ? '' : ' open' ?>><summary>Paste flights from an email</summary><div class="body">
      <form class="form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="flight_parse"><input type="hidden" name="trip_id" value="<?= $id ?>">
        <label class="lab">Paste the airline or travel agent confirmation<textarea name="text" rows="6" placeholder="American Airlines Flight 1820&#10;Sat, Jun 19 · JAX 6:00 AM → MIA 7:20 AM" required></textarea></label>
        <button class="btn btn-dark" type="submit">Find flights</button>
        <div class="muted small">Works with most airline and agency emails, including Adventures in Missions itineraries. You'll check them before anything saves.</div>
      </form></div></details>
    <details class="add"><summary>Add a flight</summary><div class="body">
      <form class="form" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="flight_save"><input type="hidden" name="trip_id" value="<?= $id ?>">
        <?php $flight_fields(['direction' => 'out']); ?><button class="btn btn-dark" type="submit">Add flight</button></form>
    </div></details>
    <section class="note"><strong>Got an itinerary email?</strong><div class="muted small">Paste it into "Paste flights from an email" and the flights fill in for you to check.</div></section>
  </aside>
</div>
