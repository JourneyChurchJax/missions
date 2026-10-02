<?php
// Start page. Travelers go straight to their trip. Staff pick the staff side, or preview the app as a traveler (read-only).
require __DIR__ . '/inc/bootstrap.php';
require_preview();
if (!is_staff_session()) { header('Location: /trip/'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $as = (string)post('as');
    if ($as === 'staff') { $_SESSION['view'] = 'staff'; unset($_SESSION['person_id']); header('Location: /admin/'); exit; }
    $pid = (int)$as;
    if ($pid && val('SELECT COUNT(*) FROM members WHERE person_id = ?', [$pid])) {
        $_SESSION['view'] = 'traveler'; $_SESSION['person_id'] = $pid;
        audit('preview_as_traveler', 'people', $pid);
        header('Location: /trip/'); exit;
    }
    header('Location: /'); exit;
}
$people_on_trips = all("SELECT DISTINCT p.id, p.first_name, p.preferred_name, p.last_name, t.name AS trip FROM people p JOIN members m ON m.person_id = p.id JOIN trips t ON t.id = m.trip_id WHERE t.status = 'active' ORDER BY t.start_date DESC, p.last_name");
page_open('Choose a view');
?>
<main class="gate">
  <div class="card" style="max-width:600px">
    <?= logo(280, false, '/') ?>
    <h1 class="disp" style="margin:8px 0 0;font-size:40px">Where to?</h1>
    <form method="post"><?= csrf() ?><input type="hidden" name="as" value="staff">
      <button class="cell group" type="submit" style="min-height:76px;width:100%;border:0;text-align:left;cursor:pointer;font:inherit"><span class="av dark"><?= e(current_actor_initials()) ?></span><span class="grow"><strong style="display:block">Staff</strong><span class="muted small">Trips, people, applications, giving, reports, settings</span></span><span class="chev">›</span></button></form>
    <div class="gh" role="heading" aria-level="2" style="padding:8px 4px 0">Preview the app as a traveler</div>
    <p class="muted small" style="margin:0">Read-only: you can look around, but nothing is saved, signed or sent as them.</p>
    <div class="group" style="max-height:340px;overflow:auto">
    <?php foreach ($people_on_trips as $p): ?>
      <form method="post"><?= csrf() ?><input type="hidden" name="as" value="<?= (int)$p['id'] ?>">
        <button class="cell" type="submit" style="width:100%;border:0;background:none;text-align:left;cursor:pointer;font:inherit"><span class="av"><?= e(initials(full_name($p))) ?></span><span class="grow"><strong><?= e(full_name($p)) ?></strong> <span class="muted small">· <?= e($p['trip']) ?></span></span><span class="chev">›</span></button></form>
    <?php endforeach; ?>
    <?= $people_on_trips ? '' : empty_state('No travelers yet') ?>
    </div>
  </div>
</main>
<?php page_close(); ?>
