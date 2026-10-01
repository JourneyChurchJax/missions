<?php
require __DIR__ . '/inc/bootstrap.php';
require_preview();
if (isset($_GET['as'])) {
    if ($_GET['as'] === 'staff') { $_SESSION['view'] = 'staff'; header('Location: /admin/'); exit; }
    $_SESSION['view'] = 'traveler'; $_SESSION['person_id'] = (int)$_GET['as']; header('Location: /trip/'); exit;
}
$people_on_trips = all("SELECT DISTINCT p.id, p.first_name, p.preferred_name, p.last_name, t.name AS trip FROM people p JOIN members m ON m.person_id = p.id JOIN trips t ON t.id = m.trip_id ORDER BY t.start_date DESC, p.last_name");
page_open('Choose a view');
?>
<main class="gate">
  <div class="card" style="max-width:600px">
    <?= logo(280, false, '/') ?>
    <h1 class="disp" style="margin:8px 0 0;font-size:40px">Pick a <em style="font-weight:700;letter-spacing:-.03em">view</em>.</h1>
    <p class="muted" style="margin:0">See the app the way staff see it, or the way any traveler sees it.</p>
    <a class="cell group" href="/?as=staff" style="min-height:76px"><span class="av dark">AH</span><span class="grow"><strong style="display:block">Staff</strong><span class="muted small">Trips, people, applications, giving, reports, settings</span></span><span class="chev">›</span></a>
    <div class="gh" style="padding:8px 4px 0">Or see it as a traveler</div>
    <div class="group" style="max-height:340px;overflow:auto">
    <?php foreach ($people_on_trips as $p): ?>
      <a class="cell" href="/?as=<?= (int)$p['id'] ?>"><span class="av"><?= initials(full_name($p)) ?></span><span class="grow"><strong><?= e(full_name($p)) ?></strong> <span class="muted small">· <?= e($p['trip']) ?></span></span><span class="chev">›</span></a>
    <?php endforeach; ?>
    </div>
  </div>
</main>
<?php page_close(); ?>
