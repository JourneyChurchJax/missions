<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
page_open('People');
admin_header('people');
$q = trim((string)($_GET['q'] ?? ''));
$list = $q === '' ? $people : array_values(array_filter($people, fn($p) => stripos($p[0] . ' ' . $p[1] . ' ' . $p[2], $q) !== false));
?>
<main class="main">
  <div class="head">
    <div class="sub"><h1 class="disp">People</h1><div class="muted">Everyone who has applied, traveled, led or given · synced with Planning Center</div></div>
    <div style="display:flex;gap:12px"><a class="btn" href="#" data-say="Synced with Planning Center (preview only)">Sync now</a><a class="btn btn-primary" href="#" data-say="Adding people is coming next">Add a person</a></div>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
    <div class="seg" data-choice aria-label="Filter">
      <button type="button" class="tab on">Everyone</button><button type="button" class="tab">Travelers</button><button type="button" class="tab">Leaders</button><button type="button" class="tab">Applicants</button><button type="button" class="tab">Donors</button>
    </div>
    <?php if ($q !== ''): ?><div class="muted">Results for “<?= e($q) ?>” · <a href="/admin/people.php">Clear</a></div><?php endif; ?>
  </div>

  <div class="split">
    <section class="group tbl">
      <table>
        <thead><tr><th>Name</th><th>Trip</th><th>Role</th><th>Ready</th><th>Raised</th><th>Planning Center</th></tr></thead>
        <tbody>
        <?php foreach ($list as $i => [$name, $trip, $role, $ready, $raised, $pco]):
          $data = json_encode(['name' => $name, 'trip' => $trip, 'role' => $role, 'ready' => $ready, 'raised' => $raised ? money($raised) . ' raised' : '—', 'initials' => initials($name)]); ?>
          <tr class="clickable<?= $name === 'Thomas Sereno' ? ' sel' : '' ?>" data-person='<?= e($data) ?>' tabindex="0">
            <td><div class="who"><span class="av"><?= initials($name) ?></span><?= e($name) ?></div></td>
            <td><?= e($trip) ?></td><td><?= e($role) ?></td>
            <td><span class="pill<?= $ready === 'Ready' ? ' pill-ok' : '' ?>"><?= e($ready) ?></span></td>
            <td><?= $raised ? money($raised) : '<span class="muted">—</span>' ?></td>
            <td class="muted"><?= e($pco) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="6" class="muted">No one matches that search.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <div class="muted small" style="padding:14px 18px;box-shadow:inset 0 1px 0 var(--sand)">Sample people, except Corey and Thomas · click a row</div>
    </section>

    <aside class="panel sticky" data-person-panel>
      <div style="display:flex;gap:14px;align-items:center"><span class="av dark lg" data-f="av">TS</span><div><div style="font-weight:700;font-size:20px" data-f="name">Thomas Sereno</div><div class="muted" data-f="role">Traveler · Belize 2027</div></div></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn" style="height:40px;font-size:14px" href="/trip/messages.php">Message</a><a class="btn" style="height:40px;font-size:14px" href="#" data-say="Opens their Planning Center profile">Open in Planning Center</a></div>
      <div class="group" style="box-shadow:none;background:var(--cream)">
        <div class="cell"><span class="muted small" style="width:110px">Passport</span><span>Valid to Feb 2034</span></div>
        <div class="cell"><span class="muted small" style="width:110px">Emergency</span><span>On file</span></div>
        <div class="cell"><span class="muted small" style="width:110px">Medical</span><span>On file · leaders only</span></div>
      </div>
      <div><div style="display:flex;justify-content:space-between;font-size:14px;padding-bottom:6px"><strong>Before the trip</strong><span class="muted" data-f="ready">2 of 5</span></div><?= bar(40) ?></div>
      <div><div style="display:flex;justify-content:space-between;font-size:14px;padding-bottom:6px"><strong>Fundraising</strong><span class="muted" data-f="raised">$640 raised</span></div><?= bar(34) ?></div>
      <a href="#" data-say="Full profiles are coming next" style="font-size:15px;font-weight:600">Open full profile ›</a>
    </aside>
  </div>
</main>
<?php page_close(); ?>
