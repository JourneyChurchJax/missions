<?php
// Every form on the site posts here. Each action checks who is acting, saves, and sends you back.
require __DIR__ . '/inc/bootstrap.php';
require_preview();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /'); exit; }
check_csrf();

$a = (string)post('action');
$staff = ($_SESSION['view'] ?? 'staff') === 'staff';
$me = acting_person_id();
$id = (int)post('id', 0);
$trip_id = (int)post('trip_id', 0);

function need_staff(bool $staff): void { if (!$staff) { http_response_code(403); exit('Staff only.'); } }

function save_upload(string $field, ?int $trip_id, ?int $person_id, string $title, string $kind, array $extra = []): ?int {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) { flash('That upload did not go through. Try again.'); back(); }
    if ($f['size'] > 20 * 1024 * 1024) { flash('Files must be 20 MB or smaller.'); back(); }
    $mime = mime_content_type($f['tmp_name']) ?: 'application/octet-stream';
    $ok = ['application/pdf', 'image/jpeg', 'image/png', 'image/heic', 'image/webp', 'image/gif',
           'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
           'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'text/csv'];
    if (!in_array($mime, $ok, true)) { flash('Use a PDF, photo, Word or Excel file.'); back(); }
    $dir = data_dir() . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $name = bin2hex(random_bytes(16)) . ($ext && preg_match('/^[a-z0-9]{1,5}$/', $ext) ? ".$ext" : '');
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) { flash('Could not save the file.'); back(); }
    return insert('files', ['trip_id' => $trip_id, 'person_id' => $person_id, 'title' => $title ?: $f['name'], 'original' => $f['name'],
        'mime' => $mime, 'size' => (int)$f['size'], 'path' => $name, 'kind' => $kind, 'created_at' => now()] + $extra);
}

$person_fields = ['first_name', 'preferred_name', 'last_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'city', 'state', 'zip', 'shirt',
    'passport_name', 'passport_number', 'passport_country', 'passport_issued', 'passport_expires', 'nationality',
    'ec1_name', 'ec1_rel', 'ec1_phone', 'ec1_email', 'ec2_name', 'ec2_rel', 'ec2_phone', 'health', 'diet', 'allergies', 'meds', 'other'];

switch ($a) {
    // ---------------- Trips ----------------
    case 'trip_save':
        need_staff($staff);
        $row = [];
        foreach (['name', 'public_name', 'city', 'country', 'partner', 'start_date', 'end_date', 'description', 'qualifications',
                  'cost_per_person', 'max_team', 'app_deadline', 'group_name', 'passport_valid_through'] as $k) $row[$k] = nn(post($k));
        if (!$row['name'] || !$row['start_date'] || !$row['end_date']) { flash('A trip needs a name and dates.'); back(); }
        if ($id) { update('trips', $id, $row); log_activity($id, 'Updated trip details'); flash('Trip saved'); header("Location: /admin/trip.php?id=$id"); exit; }
        $row += ['slug' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', $row['name'])) . '-' . substr(bin2hex(random_bytes(2)), 0, 4), 'status' => 'active', 'created_at' => now()];
        $new = insert('trips', $row);
        if ($from = (int)post('copy_from', 0)) {
            $shift = (strtotime($row['start_date']) - strtotime((string)val('SELECT start_date FROM trips WHERE id = ?', [$from]))) ;
            $mv = fn($d) => $d ? date('Y-m-d', strtotime($d) + $shift) : null;
            foreach (all('SELECT * FROM tasks WHERE trip_id = ?', [$from]) as $t) { unset($t['id']); $t['trip_id'] = $new; $t['due_date'] = $mv($t['due_date']); $t['created_at'] = now(); insert('tasks', $t); }
            foreach (all('SELECT * FROM goals WHERE trip_id = ?', [$from]) as $t) { unset($t['id']); $t['trip_id'] = $new; $t['due_date'] = $mv($t['due_date']); insert('goals', $t); }
            foreach (all('SELECT * FROM budget WHERE trip_id = ?', [$from]) as $t) { unset($t['id']); $t['trip_id'] = $new; $t['est_date'] = $mv($t['est_date']); insert('budget', $t); }
            foreach (all('SELECT * FROM guide WHERE trip_id = ?', [$from]) as $t) { unset($t['id']); $t['trip_id'] = $new; insert('guide', $t); }
            foreach (all("SELECT * FROM files WHERE trip_id = ? AND person_id IS NULL", [$from]) as $t) { unset($t['id']); $t['trip_id'] = $new; insert('files', $t); }
        }
        log_activity($new, 'Created the trip');
        flash('Trip created'); header("Location: /admin/trip.php?id=$new"); exit;

    case 'trip_status':
        need_staff($staff);
        $s = in_array(post('status'), ['active', 'cancelled', 'postponed'], true) ? post('status') : 'active';
        update('trips', $id, ['status' => $s]); log_activity($id, "Marked the trip $s"); flash('Trip marked ' . $s); back();

    // ---------------- Team and people ----------------
    case 'member_add':
        need_staff($staff);
        $pid = (int)post('person_id', 0);
        if (!$pid) {
            if (!post('first_name') || !post('last_name')) { flash('Add a first and last name.'); back(); }
            $pid = insert('people', ['first_name' => post('first_name'), 'last_name' => post('last_name'), 'email' => nn(post('email')), 'created_at' => now()]);
        }
        if (member_of($trip_id, $pid)) { flash('They are already on this trip.'); back(); }
        insert('members', ['trip_id' => $trip_id, 'person_id' => $pid, 'role' => post('role', 'traveler'), 'traveling' => post('traveling') ? 1 : 0, 'raised' => 0, 'created_at' => now()]);
        log_activity($trip_id, 'Added ' . full_name(person($pid)) . ' to the team');
        flash('Added to the team'); back();

    case 'member_update':
        need_staff($staff);
        $row = ['role' => post('role'), 'traveling' => post('traveling') ? 1 : 0, 'goal' => nn(post('goal')), 'confirmation' => nn(post('confirmation')), 'room' => nn(post('room')), 'seat' => nn(post('seat'))];
        update('members', $id, $row); flash('Saved'); back();

    case 'member_remove':
        need_staff($staff);
        $m = one('SELECT * FROM members WHERE id = ?', [$id]);
        if ($m) { delete_row('members', $id); log_activity((int)$m['trip_id'], 'Removed ' . full_name(person((int)$m['person_id'])) . ' from the team'); }
        flash('Removed from the team'); back();

    case 'person_save':
        $pid = $id ?: 0;
        if (!$staff && $pid !== $me) { http_response_code(403); exit('You can only edit your own profile.'); }
        $row = []; foreach ($person_fields as $k) if (array_key_exists($k, $_POST)) $row[$k] = nn(post($k));
        if ($staff) foreach (['notes', 'tags', 'pco_id'] as $k) if (array_key_exists($k, $_POST)) $row[$k] = nn(post($k));
        if ($pid) update('people', $pid, $row); else { $row['created_at'] = now(); $pid = insert('people', $row); }
        flash('Profile saved');
        if (!$staff || post('back')) back();
        header("Location: /admin/person.php?id=$pid"); exit;

    // ---------------- Tasks and goals ----------------
    case 'task_save':
        need_staff($staff);
        $row = ['trip_id' => $trip_id, 'title' => post('title'), 'description' => nn(post('description')), 'type' => array_key_exists(post('type'), TASK_TYPES) ? post('type') : 'traveler',
                'due_date' => nn(post('due_date')), 'minors_only' => post('minors_only') ? 1 : 0, 'allow_self' => post('allow_self') ? 1 : 0];
        if (!$row['title']) { flash('Give the task a name.'); back(); }
        if ($id) update('tasks', $id, $row); else { $row['created_at'] = now(); insert('tasks', $row); log_activity($trip_id, 'Added task: ' . $row['title']); }
        flash('Task saved'); back();

    case 'task_delete':
        need_staff($staff); q('DELETE FROM task_done WHERE task_id = ?', [$id]); delete_row('tasks', $id); flash('Task deleted'); back();

    case 'task_toggle':
        $task = one('SELECT * FROM tasks WHERE id = ?', [$id]);
        $pid = $staff ? (int)post('person_id') : (int)$me;
        if (!$task || (!$staff && !$task['allow_self'])) { flash('Your leader marks this one done.'); back(); }
        $done = one('SELECT * FROM task_done WHERE task_id = ? AND person_id = ?', [$id, $pid]);
        if ($done) { delete_row('task_done', (int)$done['id']); flash('Marked not done'); }
        else { insert('task_done', ['task_id' => $id, 'person_id' => $pid, 'done_at' => now()]); flash('Done'); }
        back();

    case 'task_upload':
        $task = one('SELECT * FROM tasks WHERE id = ?', [$id]);
        $pid = $staff ? (int)post('person_id') : (int)$me;
        if (!$task) back();
        $fid = save_upload('file', (int)$task['trip_id'], $pid, $task['title'], 'upload', ['visible' => 0]);
        if (!$fid) { flash('Choose a file first.'); back(); }
        q('DELETE FROM task_done WHERE task_id = ? AND person_id = ?', [$id, $pid]);
        insert('task_done', ['task_id' => $id, 'person_id' => $pid, 'done_at' => now(), 'file_id' => $fid]);
        log_activity((int)$task['trip_id'], full_name(person($pid)) . ' uploaded: ' . $task['title']);
        flash('Uploaded. Thank you'); back();

    case 'verify_info':
        $pid = (int)$me;
        $task = one('SELECT * FROM tasks WHERE id = ?', [$id]);
        update('people', $pid, ['first_name' => post('first_name'), 'last_name' => post('last_name'), 'birth_date' => nn(post('birth_date')), 'verified_at' => now()]);
        if ($task && !one('SELECT id FROM task_done WHERE task_id = ? AND person_id = ?', [$id, $pid])) insert('task_done', ['task_id' => $id, 'person_id' => $pid, 'done_at' => now()]);
        flash('Confirmed. Thank you'); back();

    case 'goal_save':
        need_staff($staff);
        $row = ['trip_id' => $trip_id, 'due_date' => post('due_date'), 'kind' => post('kind') === 'amount' ? 'amount' : 'percent', 'amount' => (float)post('amount')];
        if ($id) update('goals', $id, $row); else insert('goals', $row);
        flash('Goal saved'); back();
    case 'goal_delete': need_staff($staff); delete_row('goals', $id); flash('Goal deleted'); back();

    // ---------------- Meetings ----------------
    case 'meeting_save':
        need_staff($staff);
        $row = ['trip_id' => $trip_id, 'title' => post('title') ?: 'Team meeting', 'starts_at' => str_replace('T', ' ', (string)post('starts_at')) ?: null,
                'ends_at' => nn(str_replace('T', ' ', (string)post('ends_at'))), 'location' => nn(post('location')), 'address' => nn(post('address')), 'notes' => nn(post('notes'))];
        if ($id) update('meetings', $id, $row); else { insert('meetings', $row); log_activity($trip_id, 'Scheduled ' . $row['title']); }
        flash('Meeting saved'); back();
    case 'meeting_delete': need_staff($staff); q('DELETE FROM attendance WHERE meeting_id = ?', [$id]); delete_row('meetings', $id); flash('Meeting deleted'); back();
    case 'attendance_save':
        need_staff($staff);
        q('DELETE FROM attendance WHERE meeting_id = ?', [$id]);
        foreach ((array)($_POST['present'] ?? []) as $pid) insert('attendance', ['meeting_id' => $id, 'person_id' => (int)$pid, 'present' => 1]);
        flash('Attendance saved'); back();

    // ---------------- Documents and links ----------------
    case 'file_upload':
        need_staff($staff);
        $fid = save_upload('file', $trip_id, null, (string)post('title'), 'doc', ['note' => nn(post('note')), 'visible' => post('visible') ? 1 : 0, 'must_ack' => post('must_ack') ? 1 : 0]);
        if (!$fid) {
            if (!post('url') && !$id) { flash('Choose a file or paste a link.'); back(); }
        }
        if (post('url') && !$fid) insert('files', ['trip_id' => $trip_id, 'title' => post('title') ?: post('url'), 'note' => nn(post('note')), 'url' => post('url'), 'visible' => post('visible') ? 1 : 0, 'must_ack' => post('must_ack') ? 1 : 0, 'kind' => 'link', 'created_at' => now()]);
        log_activity($trip_id, 'Posted ' . (post('title') ?: 'a document'));
        flash('Posted to the trip'); back();
    case 'file_update':
        need_staff($staff);
        update('files', $id, ['title' => post('title'), 'note' => nn(post('note')), 'url' => nn(post('url')), 'visible' => post('visible') ? 1 : 0, 'must_ack' => post('must_ack') ? 1 : 0]);
        if (!empty($_FILES['file']['name'])) {
            $tmp = save_upload('file', $trip_id, null, (string)post('title'), 'doc');
            if ($tmp) { $nf = one('SELECT * FROM files WHERE id = ?', [$tmp]); update('files', $id, ['path' => $nf['path'], 'original' => $nf['original'], 'mime' => $nf['mime'], 'size' => $nf['size']]); delete_row('files', $tmp); }
        }
        flash('Saved'); back();
    case 'file_delete':
        need_staff($staff);
        $f = one('SELECT * FROM files WHERE id = ?', [$id]);
        if ($f && $f['path'] && is_file(data_dir() . '/uploads/' . $f['path'])) unlink(data_dir() . '/uploads/' . $f['path']);
        q('DELETE FROM file_acks WHERE file_id = ?', [$id]); delete_row('files', $id); flash('Deleted'); back();
    case 'file_ack':
        $ack = one('SELECT * FROM file_acks WHERE file_id = ? AND person_id = ?', [$id, $me]);
        if ($ack) update('file_acks', (int)$ack['id'], ['acked_at' => now()]);
        else insert('file_acks', ['file_id' => $id, 'person_id' => $me, 'opened_at' => now(), 'acked_at' => now()]);
        flash('Thanks. Marked as read'); back();

    // ---------------- Guide, itinerary, flights ----------------
    case 'guide_save':
        need_staff($staff);
        foreach (GUIDE_SECTIONS as $k => $label) {
            if (!array_key_exists($k, $_POST)) continue;
            $row = one('SELECT id FROM guide WHERE trip_id = ? AND section = ?', [$trip_id, $k]);
            if ($row) update('guide', (int)$row['id'], ['body' => post($k), 'updated_at' => now()]);
            else insert('guide', ['trip_id' => $trip_id, 'section' => $k, 'body' => post($k), 'updated_at' => now()]);
        }
        log_activity($trip_id, 'Updated the trip guide');
        flash('Guide saved'); back();
    case 'itin_save':
        need_staff($staff);
        $row = ['trip_id' => $trip_id, 'day' => post('day'), 'time' => nn(post('time')), 'title' => post('title'), 'detail' => nn(post('detail'))];
        if ($id) update('itinerary', $id, $row); else insert('itinerary', $row);
        flash('Itinerary saved'); back();
    case 'itin_delete': need_staff($staff); delete_row('itinerary', $id); flash('Removed'); back();
    case 'flight_save':
        need_staff($staff);
        $row = ['trip_id' => $trip_id, 'direction' => post('direction') === 'home' ? 'home' : 'out', 'airline' => post('airline'), 'flight_no' => post('flight_no'),
                'from_code' => strtoupper((string)post('from_code')), 'to_code' => strtoupper((string)post('to_code')),
                'departs_at' => nn(str_replace('T', ' ', (string)post('departs_at'))), 'arrives_at' => nn(str_replace('T', ' ', (string)post('arrives_at'))), 'notes' => nn(post('notes'))];
        if ($id) update('flights', $id, $row); else insert('flights', $row);
        log_activity($trip_id, 'Updated flights');
        flash('Flight saved'); back();
    case 'flight_delete': need_staff($staff); delete_row('flights', $id); flash('Flight removed'); back();

    // ---------------- Announcements ----------------
    case 'announce_save':
        need_staff($staff);
        if (!post('body')) { flash('Write something first.'); back(); }
        insert('announcements', ['trip_id' => $trip_id, 'title' => nn(post('title')), 'body' => post('body'), 'author' => current_actor_name(), 'created_at' => now()]);
        log_activity($trip_id, 'Posted an announcement');
        flash('Posted. Email and text delivery comes in phase 4'); back();
    case 'announce_delete': need_staff($staff); delete_row('announcements', $id); flash('Deleted'); back();

    // ---------------- Budget ----------------
    case 'budget_save':
        need_staff($staff);
        $row = ['trip_id' => $trip_id, 'description' => post('description'), 'type' => post('type'), 'vendor' => nn(post('vendor')), 'unit_cost' => (float)post('unit_cost'),
                'qty' => max(1, (int)post('qty', 1)), 'per_traveler' => post('per_traveler') ? 1 : 0, 'est_date' => nn(post('est_date'))];
        if ($id) update('budget', $id, $row); else insert('budget', $row);
        flash('Budget saved'); back();
    case 'budget_delete': need_staff($staff); delete_row('budget', $id); flash('Removed'); back();
    case 'goal_sync':
        need_staff($staff);
        $n = max(1, count(travelers($trip_id)));
        update('trips', $trip_id, ['cost_per_person' => round(trip_budget($trip_id) / $n, 2)]);
        flash('Goal per person now matches the budget'); back();

    default:
        flash('Unknown action'); back();
}
