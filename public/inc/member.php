<?php
// Loaded by every traveler page: who you are, your trip, your membership.
require_preview('traveler');
$me_id = acting_person_id();
$me = $me_id ? person($me_id) : null;
$t = $me_id ? trip_for_person($me_id) : null;
$mem = ($t && $me_id) ? member_of((int)$t['id'], $me_id) : null;
if (!$me || !$t) {
    page_open('My trip'); ?>
    <main class="gate"><div class="card"><?= logo(240, false, '/') ?><h1 class="disp" style="margin:0;font-size:36px">No trip yet.</h1>
    <p class="muted" style="margin:0">Once you're added to a trip, everything you need will show up here.</p><a class="btn" href="/">Switch view</a></div></main>
    <?php page_close(); exit;
}
$tid = (int)$t['id'];
$goal = member_goal($t, $mem);
$raised = (float)$mem['raised'];
$leader = one("SELECT p.* FROM people p JOIN members m ON m.person_id = p.id WHERE m.trip_id = ? AND m.role IN ('leader','admin') ORDER BY CASE m.role WHEN 'leader' THEN 0 ELSE 1 END LIMIT 1", [$tid]);
$my_tasks = tasks_for($tid, $me_id);
$open_tasks = array_values(array_filter($my_tasks, fn($k) => !$k['done_at']));
$next_meeting = one('SELECT * FROM meetings WHERE trip_id = ? AND starts_at >= ? ORDER BY starts_at LIMIT 1', [$tid, date('Y-m-d')]);
$must_read = all("SELECT f.*, a.acked_at FROM files f LEFT JOIN file_acks a ON a.file_id = f.id AND a.person_id = ? WHERE f.trip_id = ? AND f.must_ack = 1 AND f.visible = 1 AND f.person_id IS NULL AND (f.path IS NOT NULL OR (f.url IS NOT NULL AND f.url <> ''))", [$me_id, $tid]);
$unread = array_values(array_filter($must_read, fn($f) => !$f['acked_at']));
