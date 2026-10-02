<?php
// Every form on the site posts here. Each action checks who is acting and what they're allowed to do,
// saves (all-or-nothing), records it in the audit log, and sends you back.
require __DIR__ . '/inc/bootstrap.php';
require_preview();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /'); exit; }
check_csrf();

$a = (string)post('action');
$staff = is_staff_session() && !impersonating();
$me = acting_person_id();
$id = (int)post('id', 0);
$trip_id = (int)post('trip_id', 0);

// Previewing the traveler side is look-only: nothing gets signed, sent, paid or saved as them
if (impersonating()) { flash("You're previewing as a traveler, so nothing was saved. Go back to staff to make changes.", 'error'); back(); }
if (!check_once()) { flash('Already saved. (That was a double click.)'); back(); }

function need_staff(bool $staff): void { if (!$staff) { http_response_code(403); exit('Staff only.'); } }
// Staff, or a leader of this trip whose permissions allow changing this area
function need(string $area, int $trip_id, int $level = 2): void { if (!$trip_id || !can($area, $trip_id, $level)) { http_response_code(403); exit("You don't have permission to do that."); } }
function trip_of(string $table, int $id): int { return (int)val("SELECT trip_id FROM $table WHERE id = ?", [$id]); }

// Record the action (without passwords, signatures or long text) before doing it
$detail = array_diff_key($_POST, array_flip(['csrf', 'once', 'action', 'password', 'sig_image', 'body', 'page_story', 'description', 'text', 'intro', 'notes', 'health', 'allergies', 'meds', 'diet', 'other', 'passport_number']));
foreach ($detail as $k => $v) if (is_string($v) && mb_strlen($v) > 200) $detail[$k] = mb_substr($v, 0, 200) . '…';
audit($a, '', $id ?: null, $trip_id ?: null, $detail ?: null);

// Save an uploaded file outside the website. Photos are shrunk to a sensible size.
function save_upload(string $field, ?int $trip_id, ?int $person_id, string $title, string $kind, array $extra = [], array $allow = []): ?int {
    if (empty($_FILES[$field]) || is_array($_FILES[$field]['error']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) fail('That upload did not go through. Try again.');
    if ($f['size'] > 20 * 1024 * 1024) fail('Files must be 20 MB or smaller.');
    $mime = mime_content_type($f['tmp_name']) ?: 'application/octet-stream';
    $types = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/heic' => 'heic', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'text/plain' => 'txt', 'text/csv' => 'csv'];
    if ($allow) $types = array_intersect_key($types, array_flip($allow));
    if (!isset($types[$mime])) fail($allow === ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic'] ? 'Use a photo (JPG, PNG or HEIC).' : 'Use a PDF, photo, Word or Excel file.');
    if ($person_id && val('SELECT COALESCE(SUM(size),0) FROM files WHERE person_id = ?', [$person_id]) > 200 * 1024 * 1024) fail('This person has used their 200 MB of uploads. Remove an old file first.');
    $dir = upload_dir();
    $name = bin2hex(random_bytes(16)) . '.' . $types[$mime];   // the extension comes from the real file type, never the uploaded name
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) fail('Could not save the file.');
    if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) shrink_image("$dir/$name", $mime, 2000);
    clearstatcache();
    return insert('files', ['trip_id' => $trip_id, 'person_id' => $person_id, 'title' => mb_substr($title ?: $f['name'], 0, 200), 'original' => mb_substr((string)$f['name'], 0, 200),
        'mime' => $mime, 'size' => (int)filesize("$dir/$name"), 'path' => $name, 'kind' => $kind, 'sha256' => hash_file('sha256', "$dir/$name"), 'created_at' => now()] + $extra);
}
function shrink_image(string $path, string $mime, int $max): void {
    if (!function_exists('imagecreatefromjpeg') || !($info = @getimagesize($path)) || max($info[0], $info[1]) <= $max) return;
    $src = match ($mime) { 'image/jpeg' => @imagecreatefromjpeg($path), 'image/png' => @imagecreatefrompng($path), 'image/webp' => @imagecreatefromwebp($path), default => false };
    if (!$src) return;
    $scale = $max / max($info[0], $info[1]); $w = (int)round($info[0] * $scale); $h = (int)round($info[1] * $scale);
    $dst = imagecreatetruecolor($w, $h); imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
    match ($mime) { 'image/jpeg' => imagejpeg($dst, $path, 85), 'image/png' => imagepng($dst, $path, 6), 'image/webp' => imagewebp($dst, $path, 85) };
}
function copy_physical(string $path): ?string {
    $src = upload_dir() . '/' . basename($path);
    if (!is_file($src)) return null;
    $new = bin2hex(random_bytes(16)) . '.' . pathinfo($path, PATHINFO_EXTENSION);
    return copy($src, upload_dir() . '/' . $new) ? $new : null;
}
// Page addresses: letters and numbers, 3–40 long, not a word the site already uses
function valid_page_slug(string $slug, int $except_member = 0): ?string {
    if (!preg_match('/^[a-z0-9]{3,40}$/', $slug)) return 'Use 3 to 40 letters or numbers.';
    if (in_array($slug, RESERVED_SLUGS, true)) return 'That address is used by the site. Try another.';
    if (val('SELECT COUNT(*) FROM members WHERE page_slug = ? AND id <> ?', [$slug, $except_member])) return 'That address is taken. Try another.';
    return null;
}
$person_fields = ['first_name', 'preferred_name', 'last_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'city', 'state', 'zip', 'shirt',
    'passport_name', 'passport_number', 'passport_country', 'passport_issued', 'passport_expires', 'nationality',
    'ec1_name', 'ec1_rel', 'ec1_phone', 'ec1_email', 'ec2_name', 'ec2_rel', 'ec2_phone', 'health', 'diet', 'allergies', 'meds', 'other'];
$medical_fields = ['health', 'diet', 'allergies', 'meds', 'other'];

switch ($a) {
    // ---------------- Trips ----------------
    case 'trip_save':
        need_staff($staff);
        $row = [];
        foreach (['name', 'public_name', 'city', 'country', 'partner', 'start_date', 'end_date', 'description', 'qualifications',
                  'cost_per_person', 'max_team', 'app_deadline', 'group_name', 'passport_valid_through', 'timezone'] as $k) $row[$k] = nn(ps($k, 5000));
        $row['bg_required'] = array_key_exists((string)post('bg_required'), BG_RULES) ? post('bg_required') : 'leaders';
        if (!$row['timezone'] || !in_array($row['timezone'], timezone_identifiers_list(), true)) { require_once __DIR__ . '/inc/seed.php'; $row['timezone'] = guess_timezone($row['country']); }
        if (!$row['name'] || !$row['start_date'] || !$row['end_date']) fail('A trip needs a name and dates.');
        if ($row['end_date'] < $row['start_date']) fail('The return date is before the start date.');
        if ($id) { update('trips', $id, $row); log_activity($id, 'Updated trip details'); flash('Trip saved'); header("Location: /admin/trip.php?id=$id"); exit; }
        $row += ['slug' => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $row['name']), '-')) . '-' . substr(bin2hex(random_bytes(2)), 0, 4), 'status' => 'active', 'created_at' => now()];
        $new = tx(function () use ($row) {
            $new = insert('trips', $row);
            if ($from = (int)post('copy_from', 0)) {
                // Move every date by whole days (so daylight saving can't shift anything by a day)
                $days = (int)(new DateTime((string)val('SELECT start_date FROM trips WHERE id = ?', [$from])))->diff(new DateTime($row['start_date']))->format('%r%a');
                $mv = fn($d) => $d ? (new DateTime(substr($d, 0, 10)))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d') : null;
                $fileMap = [];
                foreach (all("SELECT * FROM files WHERE trip_id = ? AND person_id IS NULL AND kind IN ('doc', 'link', 'photo')", [$from]) as $t) {
                    $old = (int)$t['id']; unset($t['id']); $t['trip_id'] = $new; $t['created_at'] = now();
                    if ($t['path']) { $t['path'] = copy_physical($t['path']); if (!$t['path']) continue; }
                    $fileMap[$old] = insert('files', $t);
                }
                foreach (all('SELECT * FROM tasks WHERE trip_id = ?', [$from]) as $t) { unset($t['id']); $t['trip_id'] = $new; $t['due_date'] = $mv($t['due_date']); $t['file_id'] = $t['file_id'] ? ($fileMap[(int)$t['file_id']] ?? null) : null; $t['created_at'] = now(); insert('tasks', $t); }
                foreach (all('SELECT * FROM goals WHERE trip_id = ?', [$from]) as $t) { unset($t['id']); $t['trip_id'] = $new; $t['due_date'] = $mv($t['due_date']); insert('goals', $t); }
                foreach (all('SELECT * FROM budget WHERE trip_id = ?', [$from]) as $t) { unset($t['id']); $t['trip_id'] = $new; $t['est_date'] = $mv($t['est_date']); insert('budget', $t); }
                foreach (all('SELECT * FROM guide WHERE trip_id = ?', [$from]) as $t) { unset($t['id']); $t['trip_id'] = $new; insert('guide', $t); }
            }
            return $new;
        });
        log_activity($new, 'Created the trip');
        flash('Trip created'); header("Location: /admin/trip.php?id=$new"); exit;

    case 'trip_status':
        need_staff($staff);
        $s = in_array(post('status'), ['active', 'cancelled', 'postponed'], true) ? post('status') : 'active';
        update('trips', $id, ['status' => $s]); log_activity($id, "Marked the trip $s");
        $c = $s === 'cancelled' ? cancel_trip_subscriptions($id) : 0;
        flash('Trip marked ' . $s . ($c ? ". Stopped $c monthly gift" . ($c === 1 ? '' : 's') . '.' : '')); back();

    // ---------------- Team and people ----------------
    case 'member_add':
        need('team', $trip_id);
        $pid = (int)post('person_id', 0);
        if (!$pid) {
            if (!ps('first_name') || !ps('last_name')) fail('Add a first and last name.');
            $pid = insert('people', ['first_name' => ps('first_name', 80), 'last_name' => ps('last_name', 80), 'email' => nn(strtolower(ps('email', 200))), 'created_at' => now()]);
        }
        if (member_of($trip_id, $pid)) fail('They are already on this trip.');
        $role = in_array(post('role'), ['traveler', 'leader', 'admin'], true) ? post('role') : 'traveler';
        if ($role !== 'traveler' && !$staff) fail('Only staff can add leaders.');
        $p = person($pid);
        insert('members', ['trip_id' => $trip_id, 'person_id' => $pid, 'role' => $role, 'traveling' => post('traveling') ? 1 : 0, 'raised' => 0, 'page_slug' => unique_page_slug($p), 'page_status' => 'draft', 'created_at' => now()]);
        sync_raised($trip_id, $pid);
        log_activity($trip_id, 'Added ' . full_name($p) . ' to the team');
        flash('Added to the team'); back();

    case 'member_update':
        $m = one('SELECT * FROM members WHERE id = ?', [$id]); need('team', (int)($m['trip_id'] ?? 0));
        $row = ['traveling' => post('traveling') ? 1 : 0, 'goal' => nn(post('goal')), 'confirmation' => nn(ps('confirmation', 40)), 'room' => nn(ps('room', 60)), 'seat' => nn(ps('seat', 60))];
        if ($staff && in_array(post('role'), ['traveler', 'leader', 'admin'], true)) $row['role'] = post('role');
        update('members', $id, $row); flash('Saved'); back();

    case 'member_remove':
        $m = one('SELECT * FROM members WHERE id = ?', [$id]); if (!$m) back(); need('team', (int)$m['trip_id']);
        $money = member_gifts((int)$m['trip_id'], (int)$m['person_id']) + member_paid((int)$m['trip_id'], (int)$m['person_id']);
        if ($money > 0 && !post('move_gifts')) fail(full_name(person((int)$m['person_id'])) . ' has ' . money($money, 2) . " in gifts and payments. Check \"Move their gifts to the team\" and remove again.");
        tx(function () use ($m, $id) {
            foreach (all('SELECT id, note FROM gifts WHERE trip_id = ? AND person_id = ?', [$m['trip_id'], $m['person_id']]) as $gg) update('gifts', (int)$gg['id'], ['person_id' => null, 'note' => trim(($gg['note'] ?? '') . ' (moved to the team when the traveler left)')]);
            delete_row('members', $id);
        });
        log_activity((int)$m['trip_id'], 'Removed ' . full_name(person((int)$m['person_id'])) . ' from the team');
        flash('Removed from the team' . ($money > 0 ? '. Their gifts now count for the whole team.' : '')); back();

    case 'person_save':
        $pid = $id;
        $self = $pid && !$staff && $pid === $me;
        $leaderOf = !$staff && $pid ? array_values(array_filter(array_column(all('SELECT trip_id FROM members WHERE person_id = ?', [$pid]), 'trip_id'), fn($t) => can('team', (int)$t, 2))) : [];
        if (!$staff && !$self && !$leaderOf) { http_response_code(403); exit('You can only edit your own profile.'); }
        $row = []; foreach ($person_fields as $k) if (array_key_exists($k, $_POST)) $row[$k] = nn(ps($k, 2000));
        if (isset($row['email'])) $row['email'] = strtolower((string)$row['email']);
        if (!$staff && !$self) { $canMed = (bool)array_filter($leaderOf, fn($t) => can('medical', (int)$t, 2)); if (!$canMed) foreach ($medical_fields as $k) unset($row[$k]); }
        $requested = [];
        if (!$staff && $pid) {
            // Legal name and birth date are locked once someone is on a trip. Changes go to staff to review.
            $cur = person($pid);
            foreach (locked_person_fields($pid) as $k) if (array_key_exists($k, $row) && (string)$row[$k] !== (string)$cur[$k] && (string)$cur[$k] !== '') { $requested[$k] = $row[$k]; unset($row[$k]); }
            if ($row['passport_number'] ?? null) { if (str_contains((string)$row['passport_number'], '•')) unset($row['passport_number']); }
        }
        if ($staff) {
            foreach (['notes', 'tags', 'pco_id'] as $k) if (array_key_exists($k, $_POST)) $row[$k] = nn(ps($k, 2000));
            if (array_key_exists('is_staff_set', $_POST)) $row['is_staff'] = post('is_staff') ? 1 : 0;
            if (str_contains((string)($row['passport_number'] ?? ''), '•')) unset($row['passport_number']);
        }
        if (array_key_exists('sms_ok', $_POST) || array_key_exists('sms_ok_set', $_POST)) $row['sms_ok'] = post('sms_ok') ? 1 : 0;
        if ($pid) update('people', $pid, $row);
        else { if (!$staff) fail('Only staff can add people.'); if (empty($row['first_name']) || empty($row['last_name'])) fail('Add a first and last name.'); $row['created_at'] = now(); $pid = insert('people', $row); flash('Person added'); header("Location: /admin/person.php?id=$pid"); exit; }
        if ($requested) {
            $p = person($pid);
            foreach (all('SELECT trip_id FROM members WHERE person_id = ?', [$pid]) as $mt) {
                insert('chat', ['trip_id' => $mt['trip_id'], 'thread' => 'p' . $pid, 'person_id' => $pid, 'author' => full_name($p), 'staff' => 0,
                    'body' => 'Change request (needs staff to approve): ' . implode(', ', array_map(fn($k, $v) => str_replace('_', ' ', $k) . ' → ' . ($v ?? 'blank'), array_keys($requested), $requested)), 'created_at' => now()]);
            }
            audit('change_request', 'people', $pid, null, $requested);
            flash('Saved. Your leaders will review the change to your ' . implode(' and ', array_map(fn($k) => str_replace('_', ' ', $k), array_keys($requested))) . '.');
        } else flash('Profile saved');
        back();

    case 'person_delete':
        need_staff($staff);
        if (ps('confirm') !== 'DELETE') fail('Type DELETE to confirm.');
        $res = tx(fn() => delete_person($id));
        flash($res === 'anonymized' ? 'Their personal information was erased. Their gifts, payments and signatures are kept as anonymous records.' : 'Person deleted');
        header('Location: /admin/people.php'); exit;

    case 'person_export':
        need_staff($staff);
        $data = export_person($id);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="person-' . $id . '-' . date('Y-m-d') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;

    // ---------------- Tasks and goals ----------------
    case 'task_save':
        need('tasks', $trip_id);
        $row = ['trip_id' => $trip_id, 'title' => ps('title', 200), 'description' => nn(ps('description', 3000)), 'type' => array_key_exists((string)post('type'), TASK_TYPES) ? post('type') : 'traveler',
                'due_date' => nn(post('due_date')), 'minors_only' => post('minors_only') ? 1 : 0, 'allow_self' => post('allow_self') ? 1 : 0,
                'file_id' => (int)post('file_id') ?: null, 'parent_sign' => post('parent_sign') ? 1 : 0];
        if (!$row['title']) fail('Give the task a name.');
        if ($row['file_id'] && trip_of('files', (int)$row['file_id']) !== $trip_id) $row['file_id'] = null;
        if ($row['type'] === 'sign' && !$row['description']) $row['description'] = 'I have read this document and agree to it.';
        if ($id) { if (trip_of('tasks', $id) !== $trip_id) back(); update('tasks', $id, $row); }
        else { $row['created_at'] = now(); insert('tasks', $row); log_activity($trip_id, 'Added task: ' . $row['title']); }
        flash('Task saved'); back();

    case 'task_delete':
        need('tasks', trip_of('tasks', $id));
        if (val('SELECT COUNT(*) FROM signatures WHERE task_id = ?', [$id])) fail('People have signed this. It stays so the signatures stay on record.');
        tx(function () use ($id) { q('DELETE FROM task_done WHERE task_id = ?', [$id]); delete_row('tasks', $id); });
        flash('Task deleted'); back();

    case 'task_toggle':
        $task = one('SELECT * FROM tasks WHERE id = ?', [$id]);
        if (!$task) back();
        if ($staff || can('tasks', (int)$task['trip_id'], 2)) {
            $pid = (int)post('person_id');
            if (!member_of((int)$task['trip_id'], $pid)) back();
            if ($task['type'] === 'sign') {
                // A leader marking a signature task done records a paper form; marking it again removes that record
                $paper = one("SELECT * FROM signatures WHERE task_id = ? AND person_id = ? AND signer_role = 'paper'", [$id, $pid]);
                if ($paper) { delete_row('signatures', (int)$paper['id']); flash('Paper form record removed'); }
                elseif (signature_state($task, $pid)[0] && (!needs_parent_signature($task, $pid) || signature_state($task, $pid)[1])) flash('They already signed online.');
                else { record_signature($task, $pid, 'paper', 'Paper form', null, 'paper', null, 'Signed on paper. Recorded by ' . current_actor_name() . '.'); flash('Recorded as signed on paper'); }
                back();
            }
        } else {
            $pid = (int)$me;
            $mine = array_column(tasks_for((int)$task['trip_id'], $pid), null, 'id');
            if (!isset($mine[$id]) || $task['type'] !== 'traveler') fail('Use the button on the task to finish it.');
            if (!$task['allow_self']) fail('Your leader marks this one done.');
        }
        $done = one('SELECT * FROM task_done WHERE task_id = ? AND person_id = ?', [$id, $pid]);
        if ($done) { delete_row('task_done', (int)$done['id']); flash('Marked not done'); }
        else { insert('task_done', ['task_id' => $id, 'person_id' => $pid, 'done_at' => now()]); flash('Done'); }
        back();

    case 'task_upload':
        $task = one("SELECT * FROM tasks WHERE id = ? AND type = 'upload'", [$id]);
        if (!$task) fail("That task doesn't take an upload.");
        $pid = ($staff || can('tasks', (int)$task['trip_id'], 2)) && post('person_id') ? (int)post('person_id') : (int)$me;
        if (!in_array($id, array_map('intval', array_column(tasks_for((int)$task['trip_id'], $pid), 'id')), true)) fail("That task isn't on this person's checklist.");
        $fid = save_upload('file', (int)$task['trip_id'], $pid, $task['title'], 'upload', ['visible' => 0]);
        if (!$fid) fail('Choose a file first.');
        tx(function () use ($id, $pid, $fid) { q('DELETE FROM task_done WHERE task_id = ? AND person_id = ?', [$id, $pid]); insert('task_done', ['task_id' => $id, 'person_id' => $pid, 'done_at' => now(), 'file_id' => $fid]); });
        log_activity((int)$task['trip_id'], full_name(person_basic($pid)) . ' uploaded: ' . $task['title']);
        flash('Uploaded. Thank you'); back();

    case 'verify_info':
        $pid = (int)$me;
        $task = one("SELECT * FROM tasks WHERE id = ? AND type = 'verify'", [$id]);
        if (!$task || !in_array($id, array_map('intval', array_column(tasks_for((int)$task['trip_id'], $pid), 'id')), true)) fail("That isn't on your checklist.");
        $cur = person($pid); $changes = [];
        foreach (['first_name', 'last_name', 'birth_date'] as $k) { $v = nn(ps($k, 80)); if ($v !== null && (string)$v !== (string)$cur[$k]) $changes[$k] = $v; }
        $fill = array_filter($changes, fn($v, $k) => (string)$cur[$k] === '', ARRAY_FILTER_USE_BOTH);   // empty fields can be filled in
        $review = array_diff_key($changes, $fill);                                                 // changes to existing ones go to staff
        update('people', $pid, $fill + ['verified_at' => now()]);
        if ($review) { insert('chat', ['trip_id' => $task['trip_id'], 'thread' => 'p' . $pid, 'person_id' => $pid, 'author' => full_name($cur), 'staff' => 0, 'body' => 'Change request from "Confirm your info": ' . implode(', ', array_map(fn($k, $v) => str_replace('_', ' ', $k) . ' → ' . $v, array_keys($review), $review)), 'created_at' => now()]); audit('change_request', 'people', $pid, (int)$task['trip_id'], $review); }
        if (!one('SELECT id FROM task_done WHERE task_id = ? AND person_id = ?', [$id, $pid])) insert('task_done', ['task_id' => $id, 'person_id' => $pid, 'done_at' => now()]);
        flash($review ? 'Thanks. Your leaders will fix the details you changed.' : 'Confirmed. Thank you'); back();

    case 'goal_save':
        need('tasks', $trip_id);
        if (!post('due_date')) fail('Pick a date.');
        $row = ['trip_id' => $trip_id, 'due_date' => post('due_date'), 'kind' => post('kind') === 'amount' ? 'amount' : 'percent', 'amount' => max(0, (float)post('amount'))];
        if ($id) update('goals', $id, $row); else insert('goals', $row);
        flash('Goal saved'); back();
    case 'goal_delete': need('tasks', trip_of('goals', $id)); delete_row('goals', $id); flash('Goal deleted'); back();

    // ---------------- Meetings ----------------
    case 'meeting_save':
        need('meetings', $trip_id);
        if (!post('starts_at')) fail('Pick a start time.');
        $row = ['trip_id' => $trip_id, 'title' => ps('title', 200) ?: 'Team meeting', 'starts_at' => str_replace('T', ' ', (string)post('starts_at')),
                'ends_at' => nn(str_replace('T', ' ', (string)post('ends_at'))), 'location' => nn(ps('location', 200)), 'address' => nn(ps('address', 200)), 'notes' => nn(ps('notes', 3000))];
        if ($id) update('meetings', $id, $row); else { insert('meetings', $row); log_activity($trip_id, 'Scheduled ' . $row['title']); }
        flash('Meeting saved'); back();
    case 'meeting_delete': need('meetings', trip_of('meetings', $id)); tx(function () use ($id) { q('DELETE FROM attendance WHERE meeting_id = ?', [$id]); delete_row('meetings', $id); }); flash('Meeting deleted'); back();
    case 'attendance_save':
        need('meetings', trip_of('meetings', $id));
        tx(function () use ($id) {
            q('DELETE FROM attendance WHERE meeting_id = ?', [$id]);
            foreach ((array)($_POST['present'] ?? []) as $pid) insert('attendance', ['meeting_id' => $id, 'person_id' => (int)$pid, 'present' => 1]);
        });
        flash('Attendance saved'); back();

    // ---------------- Documents and links ----------------
    case 'file_upload':
        need('documents', $trip_id);
        $fid = save_upload('file', $trip_id, null, ps('title', 200), 'doc', ['note' => nn(ps('note', 300)), 'visible' => post('visible') ? 1 : 0, 'must_ack' => post('must_ack') ? 1 : 0]);
        if (!$fid) {
            $url = ps('url', 1000);
            if (!$url) fail('Choose a file or paste a link.');
            if (!preg_match('#^https?://#i', $url)) fail('Links must start with https://');
            insert('files', ['trip_id' => $trip_id, 'title' => ps('title', 200) ?: $url, 'note' => nn(ps('note', 300)), 'url' => $url, 'visible' => post('visible') ? 1 : 0, 'must_ack' => post('must_ack') ? 1 : 0, 'kind' => 'link', 'created_at' => now()]);
        }
        log_activity($trip_id, 'Posted ' . (ps('title') ?: 'a document'));
        flash('Posted to the trip'); back();
    case 'file_update':
        $tid = trip_of('files', $id); need('documents', $tid);
        $url = nn(ps('url', 1000)); if ($url && !preg_match('#^https?://#i', $url)) fail('Links must start with https://');
        $signed = document_has_signatures($id);
        $f = one('SELECT * FROM files WHERE id = ?', [$id]);
        if ($signed && $url !== $f['url'] && $f['kind'] === 'link') fail('People have signed this document, so its link can\'t change. Post the new version as a new document.');
        update('files', $id, ['title' => ps('title', 200), 'note' => nn(ps('note', 300)), 'url' => $url, 'visible' => post('visible') ? 1 : 0, 'must_ack' => post('must_ack') ? 1 : 0]);
        if (!empty($_FILES['file']['name']) && is_string($_FILES['file']['name'])) {
            if ($signed) fail('People have signed this document, so it can\'t be replaced. Post the new version as a new document and point the signing task at it.');
            $tmp = save_upload('file', $tid, null, ps('title', 200), 'doc');
            if ($tmp) {
                $nf = one('SELECT * FROM files WHERE id = ?', [$tmp]);
                $oldPath = $f['path'];
                update('files', $id, ['path' => $nf['path'], 'original' => $nf['original'], 'mime' => $nf['mime'], 'size' => $nf['size'], 'sha256' => $nf['sha256']]);
                delete_row('files', $tmp);
                if ($oldPath && !val('SELECT COUNT(*) FROM files WHERE path = ?', [$oldPath])) @unlink(upload_dir() . '/' . basename($oldPath));
            }
        }
        flash('Saved'); back();
    case 'file_delete':
        $f = one('SELECT * FROM files WHERE id = ?', [$id]); if (!$f) back();
        if ($f['kind'] === 'photo') need_staff($staff); else need('documents', (int)$f['trip_id']);
        if (document_has_signatures($id)) fail('People have signed this document, so it stays on record. Hide it from travelers instead.');
        tx(function () use ($id) { q('DELETE FROM file_acks WHERE file_id = ?', [$id]); delete_file_record($id); });
        flash('Deleted'); back();
    case 'file_ack':
        $f = one('SELECT * FROM files WHERE id = ? AND visible = 1 AND person_id IS NULL', [$id]);
        if (!$f || !member_of((int)$f['trip_id'], (int)$me)) fail("That document isn't on your trip.");
        $ack = one('SELECT * FROM file_acks WHERE file_id = ? AND person_id = ?', [$id, $me]);
        if ($ack) update('file_acks', (int)$ack['id'], ['acked_at' => now()]);
        else insert('file_acks', ['file_id' => $id, 'person_id' => $me, 'opened_at' => now(), 'acked_at' => now()]);
        flash('Thanks. Marked as read'); back();

    // ---------------- Guide, itinerary, flights ----------------
    case 'guide_save':
        need('travel', $trip_id);
        tx(function () use ($trip_id) {
            foreach (GUIDE_SECTIONS as $k => $label) {
                if (!array_key_exists($k, $_POST)) continue;
                $row = one('SELECT id FROM guide WHERE trip_id = ? AND section = ?', [$trip_id, $k]);
                if ($row) update('guide', (int)$row['id'], ['body' => ps($k, 5000), 'updated_at' => now()]);
                else insert('guide', ['trip_id' => $trip_id, 'section' => $k, 'body' => ps($k, 5000), 'updated_at' => now()]);
            }
        });
        log_activity($trip_id, 'Updated the trip guide');
        flash('Guide saved'); back();
    case 'itin_save':
        need('travel', $trip_id);
        if (!post('day') || !ps('title')) fail('Add a day and what happens.');
        $row = ['trip_id' => $trip_id, 'day' => post('day'), 'time' => nn(ps('time', 40)), 'title' => ps('title', 200), 'detail' => nn(ps('detail', 300))];
        if ($id) update('itinerary', $id, $row); else insert('itinerary', $row);
        flash('Itinerary saved'); back();
    case 'itin_delete': need('travel', trip_of('itinerary', $id)); delete_row('itinerary', $id); flash('Removed'); back();
    case 'flight_save':
        need('travel', $trip_id);
        $row = ['trip_id' => $trip_id, 'direction' => post('direction') === 'home' ? 'home' : 'out', 'airline' => ps('airline', 80), 'flight_no' => strtoupper(ps('flight_no', 20)),
                'from_code' => strtoupper(substr(ps('from_code'), 0, 3)), 'to_code' => strtoupper(substr(ps('to_code'), 0, 3)),
                'departs_at' => nn(str_replace('T', ' ', ps('departs_at'))), 'arrives_at' => nn(str_replace('T', ' ', ps('arrives_at'))), 'notes' => nn(ps('notes', 300))];
        if ($id) update('flights', $id, $row); else insert('flights', $row);
        log_activity($trip_id, 'Updated flights');
        flash('Flight saved'); back();
    case 'flight_parse':
        need('travel', $trip_id);
        require_once __DIR__ . '/inc/flightparse.php';
        $t = trip($trip_id); if (!$t) back();
        $found = parse_flights(ps('text', 50000), $t);
        $_SESSION['flight_preview'] = [$trip_id => $found];
        flash($found ? 'Found ' . count($found) . ' flight' . (count($found) === 1 ? '' : 's') . '. Check them, then save.' : "Couldn't find any flight numbers. Try pasting more of the email, or add flights by hand.", $found ? 'ok' : 'error');
        back();
    case 'flight_import':
        need('travel', $trip_id);
        $n = tx(function () use ($trip_id) {
            $n = 0;
            foreach ((array)($_POST['f'] ?? []) as $r) {
                if (!is_array($r) || empty($r['use']) || empty($r['flight_no'])) continue;
                insert('flights', ['trip_id' => $trip_id, 'direction' => ($r['direction'] ?? '') === 'home' ? 'home' : 'out', 'airline' => mb_substr(trim((string)($r['airline'] ?? '')), 0, 80), 'flight_no' => strtoupper(mb_substr(trim((string)$r['flight_no']), 0, 20)),
                    'from_code' => strtoupper(substr(trim((string)($r['from_code'] ?? '')), 0, 3)), 'to_code' => strtoupper(substr(trim((string)($r['to_code'] ?? '')), 0, 3)),
                    'departs_at' => nn(str_replace('T', ' ', (string)($r['departs_at'] ?? ''))), 'arrives_at' => nn(str_replace('T', ' ', (string)($r['arrives_at'] ?? '')))]);
                $n++;
            }
            if ($n && post('replace_tbd')) q("DELETE FROM flights WHERE trip_id = ? AND (flight_no IS NULL OR flight_no = '' OR flight_no LIKE '[%')", [$trip_id]);
            return $n;
        });
        unset($_SESSION['flight_preview']);
        if ($n) log_activity($trip_id, "Imported $n flights");
        flash($n ? "Saved $n flight" . ($n === 1 ? '' : 's') : 'Nothing saved'); back();
    case 'flight_preview_clear': unset($_SESSION['flight_preview']); back();
    case 'flight_delete': need('travel', trip_of('flights', $id)); delete_row('flights', $id); flash('Flight removed'); back();

    // ---------------- Announcements ----------------
    case 'announce_save':
        need('messages', $trip_id);
        $body = ps('body', 5000); $title = ps('title', 150);
        if (!$body) fail('Write something first.');
        insert('announcements', ['trip_id' => $trip_id, 'title' => nn($title), 'body' => $body, 'author' => current_actor_name(), 'created_at' => now()]);
        log_activity($trip_id, 'Posted an announcement');
        $em = (bool)post('email'); $tx = (bool)post('text');
        $r = ($em || $tx) ? broadcast($trip_id, team_recipients($trip_id, (bool)post('parents')), $title ?: trip($trip_id)['name'] . ' team announcement', $body, $em, $tx) : [0, 0, 0];
        flash('Posted to the team.' . sent_note($r, $em, $tx)); back();
    case 'announce_delete': need('messages', trip_of('announcements', $id)); delete_row('announcements', $id); flash('Deleted'); back();

    // ---------------- Budget and expenses ----------------
    case 'budget_save':
        need('budget', $trip_id);
        if (!ps('description')) fail('Say what the budget line is for.');
        $row = ['trip_id' => $trip_id, 'description' => ps('description', 200), 'type' => in_array(post('type'), EXPENSE_TYPES, true) ? post('type') : 'Other', 'vendor' => nn(ps('vendor', 120)), 'unit_cost' => max(0, (float)post('unit_cost')),
                'qty' => max(1, (int)post('qty', 1)), 'per_traveler' => post('per_traveler') ? 1 : 0, 'est_date' => nn(post('est_date'))];
        if ($id) update('budget', $id, $row); else insert('budget', $row);
        flash('Budget saved'); back();
    case 'budget_delete': need('budget', trip_of('budget', $id)); delete_row('budget', $id); flash('Removed'); back();
    case 'goal_sync':
        need('budget', $trip_id);
        $n = max(1, count(travelers($trip_id)));
        update('trips', $trip_id, ['cost_per_person' => round(trip_budget($trip_id) / $n, 2)]);
        flash('Goal per person now matches the budget'); back();
    case 'expense_save':
        need('budget', $trip_id);
        $amt = round((float)post('amount'), 2); $cur = strtoupper(substr(ps('currency') ?: 'USD', 0, 3));
        $rate = $cur === 'USD' ? 1.0 : max(0.0001, (float)(post('rate') ?: 0));
        if ($amt <= 0 || !ps('description')) fail('Add what it was and the amount.');
        if ($cur !== 'USD' && !(float)post('rate')) fail("Add the exchange rate for $cur (how many U.S. dollars one $cur is worth).");
        $row = ['trip_id' => $trip_id, 'type' => in_array(post('type'), EXPENSE_TYPES, true) ? post('type') : 'Other', 'description' => ps('description', 200), 'vendor' => nn(ps('vendor', 120)),
                'amount' => $amt, 'currency' => $cur, 'rate' => $rate, 'usd' => round($amt * $rate, 2), 'spent_on' => post('spent_on') ?: date('Y-m-d'), 'paid_by' => nn(ps('paid_by', 120)), 'reimburse' => post('reimburse') ? 1 : 0];
        if ($fid = save_upload('receipt', $trip_id, null, 'Receipt: ' . ps('description', 150), 'receipt', ['visible' => 0])) $row['receipt_file_id'] = $fid;
        if ($id) update('expenses', $id, $row); else { insert('expenses', $row + ['status' => 'ok', 'created_by' => current_actor_name(), 'created_at' => now()]); log_activity($trip_id, 'Logged an expense: ' . ps('description', 150)); }
        flash('Expense saved'); back();
    case 'expense_delete':
        need('budget', trip_of('expenses', $id));
        update('expenses', $id, ['status' => 'void', 'void_reason' => ps('reason', 200) ?: 'Removed by ' . current_actor_name()]);
        flash('Expense removed (kept in the history)'); back();
    case 'expense_reimbursed':
        need('budget', trip_of('expenses', $id));
        $x = one('SELECT * FROM expenses WHERE id = ?', [$id]); update('expenses', $id, ['reimbursed_at' => $x['reimbursed_at'] ? null : now()]);
        flash($x['reimbursed_at'] ? 'Marked not paid back' : 'Marked paid back'); back();

    // ---------------- Applications (staff) ----------------
    case 'form_save':
        need_staff($staff);
        $mode = in_array(post('trip_mode'), ['all', 'specific', 'none'], true) ? post('trip_mode') : 'specific';
        $row = ['name' => ps('name', 150), 'intro' => nn(ps('intro', 5000)), 'closes_on' => nn(post('closes_on')), 'trip_mode' => $mode,
                'trip_ids' => implode(',', array_map('intval', (array)($_POST['trip_ids'] ?? []))), 'choices' => post('choices') === '3' ? 3 : 1,
                'refs_required' => max(0, min(3, (int)post('refs_required'))), 'ref_types' => nn(ps('ref_types', 500)), 'deposit' => max(0, (float)post('deposit')),
                'deposit_tax' => 0, 'photo_required' => 0, 'submitted_message' => nn(ps('submitted_message', 2000))];
        if (!$row['name']) fail('Give the form a name.');
        if ($id) { update('app_forms', $id, $row); flash('Form saved'); header("Location: /admin/app-form.php?id=$id"); exit; }
        $row += ['slug' => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $row['name']), '-')) . '-' . substr(new_token(), 0, 4), 'published' => 0, 'created_at' => now()];
        $new = tx(function () use ($row) { $new = insert('app_forms', $row); foreach (all('SELECT * FROM app_questions WHERE form_id = (SELECT MIN(id) FROM app_forms)') as $qq) { unset($qq['id']); $qq['form_id'] = $new; insert('app_questions', $qq); } return $new; });
        flash('Form created with starter questions'); header("Location: /admin/app-form.php?id=$new"); exit;

    case 'form_publish':
        need_staff($staff);
        $f = app_form($id); if (!$f) back(); update('app_forms', $id, ['published' => $f['published'] ? 0 : 1]);
        flash($f['published'] ? 'Form unpublished. No one can apply until you publish it again.' : 'Form published. Share the link.'); back();

    case 'form_duplicate':
        need_staff($staff);
        $f = app_form($id); if (!$f) back(); unset($f['id']);
        $f['name'] .= ' (copy)'; $f['slug'] = preg_replace('/-[a-f0-9]{4}$/', '', $f['slug']) . '-' . substr(new_token(), 0, 4); $f['published'] = 0; $f['created_at'] = now();
        $new = tx(function () use ($f, $id) {
            $new = insert('app_forms', $f);
            foreach (app_questions($id) as $qq) { unset($qq['id']); $qq['form_id'] = $new; insert('app_questions', $qq); }
            foreach (app_discounts($id) as $d) { unset($d['id']); $d['form_id'] = $new; insert('app_discounts', $d); }
            return $new;
        });
        flash('Copied. Change what you need, then publish.'); header("Location: /admin/app-form.php?id=$new"); exit;

    case 'q_save':
        need_staff($staff);
        $form = (int)post('form_id');
        if (form_has_responses($form) && !$id) fail('This form has responses, so questions are locked. Duplicate it to change questions.');
        $row = ['form_id' => $form, 'kind' => array_key_exists((string)post('kind'), Q_KINDS) ? post('kind') : 'short', 'label' => ps('label', 300), 'help' => nn(ps('help', 300)),
                'options' => nn(ps('options', 2000)), 'required' => post('required') ? 1 : 0];
        if (!$row['label']) fail('Write the question first.');
        if ($id && form_has_responses($form)) unset($row['kind']);
        if ($id) update('app_questions', $id, $row);
        else { $row['sort'] = (int)val('SELECT COALESCE(MAX(sort),0)+1 FROM app_questions WHERE form_id = ?', [$form]); insert('app_questions', $row); }
        flash('Question saved'); back();
    case 'q_delete':
        need_staff($staff);
        $qq = one('SELECT * FROM app_questions WHERE id = ?', [$id]);
        if ($qq && form_has_responses((int)$qq['form_id'])) fail('This form has responses, so questions are locked.');
        delete_row('app_questions', $id); flash('Question removed'); back();
    case 'q_move':
        need_staff($staff);
        $qq = one('SELECT * FROM app_questions WHERE id = ?', [$id]); if (!$qq) back();
        $ids = array_column(app_questions((int)$qq['form_id']), 'id'); $i = array_search($id, $ids); $j = post('dir') === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && $j >= 0 && $j < count($ids)) { [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; tx(function () use ($ids) { foreach ($ids as $k => $qid) update('app_questions', (int)$qid, ['sort' => $k]); }); }
        back();
    case 'disc_save':
        need_staff($staff);
        $early = post('early_bird') ? 1 : 0;
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', ps('code', 30)));
        if (!$early && $code === '') fail('Add a code, or check "Early bird" for a discount everyone gets until a date.');
        if ($early && !post('expires_on')) fail('An early-bird discount needs an end date.');
        insert('app_discounts', ['form_id' => (int)post('form_id'), 'code' => $early ? '' : $code, 'kind' => post('kind') === 'percent' ? 'percent' : 'amount',
            'amount' => max(0, (float)post('amount')), 'early_bird' => $early, 'expires_on' => nn(post('expires_on'))]);
        flash('Discount added'); back();
    case 'disc_delete': need_staff($staff); delete_row('app_discounts', $id); flash('Discount removed'); back();

    case 'app_decide':
        need_staff($staff);
        $app = application($id); $decision = post('decision');
        if (!$app || !in_array($decision, ['approved', 'waitlist', 'declined', 'submitted'], true)) back();
        $row = ['status' => $decision, 'decided_at' => now(), 'decided_by' => current_actor_name(), 'decision_note' => nn(ps('note', 1000)) ?? $app['decision_note']];
        $ap = person((int)$app['person_id']);
        if ($decision === 'approved') {
            $trip = (int)post('trip_id') ?: (int)$app['choice1'];
            if (!$trip || !($tt = trip($trip))) fail('Pick a trip to approve them for.');
            // Someone already in the system: use their existing record so they don't end up with two
            $merge = (int)post('merge_into');
            if ($merge && $merge !== (int)$app['person_id'] && ($old = person($merge))) {
                $fill = []; foreach ($ap as $k => $v) if (!in_array($k, ['id', 'created_at', 'is_staff', 'pco_id', 'cal_token'], true) && $v !== null && $v !== '' && in_array($old[$k] ?? null, [null, ''], true)) $fill[$k] = $v;
                tx(function () use ($merge, $fill, $app, $id) { update('people', $merge, $fill); update('applications', $id, ['person_id' => $merge]); q('UPDATE guardians SET person_id = ? WHERE person_id = ?', [$merge, $app['person_id']]); if (!val('SELECT COUNT(*) FROM members WHERE person_id = ?', [$app['person_id']])) q('DELETE FROM people WHERE id = ?', [$app['person_id']]); });
                $app = application($id); $ap = person($merge);
            }
            if (!member_of($trip, (int)$app['person_id'])) insert('members', ['trip_id' => $trip, 'person_id' => (int)$app['person_id'], 'role' => 'traveler', 'traveling' => 1, 'raised' => 0, 'page_slug' => unique_page_slug($ap), 'page_status' => 'draft', 'created_at' => now()]);
            $row['assigned_trip_id'] = $trip;
            update('applications', $id, $row);
            ensure_deposit_payment(application($id));
            log_activity($trip, 'Approved ' . full_name($ap) . ' from their application');
            $sent = post('notify') && $ap['email'] ? send_email($ap['email'], "You're going to " . $tt['name'] . '!', "Hi " . ($ap['preferred_name'] ?: $ap['first_name']) . ",\n\nGreat news: you're on the " . $tt['name'] . " team (" . date_range($tt['start_date'], $tt['end_date']) . ").\n\nSign in at " . site_url('/') . " with your Planning Center account to see your checklist, schedule, documents and fundraising page.\n\nWe're so glad you're going.", $trip, (int)$app['person_id']) : false;
            flash('Approved and added to ' . $tt['name'] . '.' . ($sent ? ' Welcome email sent.' : ''));
            back();
        }
        update('applications', $id, $row);
        ensure_deposit_payment(application($id));
        if ($decision === 'declined' && post('notify') && $ap['email']) send_email($ap['email'], 'About your application', "Hi " . ($ap['preferred_name'] ?: $ap['first_name']) . ",\n\nThank you for applying for " . (app_form((int)$app['form_id'])['name'] ?? 'the trip') . ". After praying and talking it through, we aren't able to place you on this trip.\n\n" . (ps('note') ? ps('note', 1000) . "\n\n" : '') . "We'd love to talk about other ways to serve. Just reply to this email.", null, (int)$app['person_id']);
        flash(['waitlist' => 'Moved to the waitlist', 'declined' => 'Declined' . (post('notify') ? ' and emailed' : ''), 'submitted' => 'Moved back to review'][$decision]);
        back();

    case 'app_deposit':
        need_staff($staff);
        $st = array_key_exists((string)post('status'), DEPOSIT_STATUS) ? post('status') : 'due';
        update('applications', $id, ['deposit_status' => $st]);
        if ($app = application($id)) ensure_deposit_payment($app);
        flash(DEPOSIT_STATUS[$st]); back();

    case 'ref_received':
        need_staff($staff);
        update('app_refs', $id, ['status' => 'received', 'received_at' => now(), 'answers' => json_encode(['known' => ps('note', 1000) ?: 'Received outside the app (by phone, email or paper)'])]);
        flash('Marked received'); back();
    case 'ref_resend':
        need_staff($staff);
        $r = one('SELECT * FROM app_refs WHERE id = ?', [$id]); $app = $r ? application((int)$r['application_id']) : null;
        if (!$r || !$app || $r['status'] === 'received') back();
        $p = person((int)$app['person_id']); $f = app_form((int)$app['form_id']);
        $ok = send_email((string)$r['email'], 'Reference for ' . full_name($p), "Hi " . strtok((string)$r['name'], ' ') . ",\n\n" . full_name($p) . " listed you as a reference for " . $f['name'] . " with " . church_name() . ". It takes about five minutes:\n\n" . ref_url($r) . "\n\nOnly the missions team will read your answers. Thank you!", null, (int)$app['person_id']);
        flash($ok ? 'Reference email sent to ' . $r['name'] : 'Email isn\'t set up yet. Call or text them instead.', $ok ? 'ok' : 'error'); back();

    // ---------------- Money ----------------
    case 'gift_save':
        $old = $id ? one('SELECT * FROM gifts WHERE id = ?', [$id]) : null;
        $amt = round((float)post('amount'), 2);
        $for = (string)post('for'); $gt = null; $gp = null;
        if (preg_match('/^t(\d+)$/', $for, $mm)) $gt = (int)$mm[1];
        elseif (preg_match('/^m(\d+)-(\d+)$/', $for, $mm)) { $gt = (int)$mm[1]; $gp = (int)$mm[2]; }
        if (!$staff && !can('giving', (int)($gt ?: ($old['trip_id'] ?? 0)), 2)) { http_response_code(403); exit("You don't have permission to do that."); }
        if ($old && ($why = gift_locked($old))) fail($why);
        if ($amt <= 0) fail('Enter the gift amount.');
        if ($gp && !member_of((int)$gt, $gp) && !($old && (int)$old['person_id'] === $gp && (int)$old['trip_id'] === $gt)) fail('That traveler is not on that trip.');
        $donor_id = (int)post('donor_id') ?: find_or_make_donor(ps('donor_first', 80), ps('donor_last', 80), ps('donor_email', 200), ps('donor_org', 120));
        $method = array_key_exists((string)post('method'), GIFT_METHODS) ? post('method') : 'check';
        $bid = (int)post('batch_id'); if ($bid && (!($b = batch($bid)) || $b['status'] !== 'open') && !($old && (int)$old['batch_id'] === $bid)) $bid = 0;
        $date = post('gift_date') ?: date('Y-m-d');
        if (in_array((int)substr($date, 0, 4), locked_years(), true)) fail('Statements for ' . substr($date, 0, 4) . ' have already gone out. Use a date in an open year.');
        $row = ['donor_id' => $donor_id, 'trip_id' => $gt, 'person_id' => $gp, 'amount' => $amt, 'fee' => max(0, round((float)post('fee'), 2)), 'method' => $method,
                'check_no' => nn(ps('check_no', 30)), 'batch_id' => $bid ?: null, 'gift_date' => $date, 'anonymous' => post('anonymous') ? 1 : 0, 'note' => nn(ps('note', 500))];
        tx(function () use ($old, $id, $row) {
            if ($old) update('gifts', $id, $row);
            else insert('gifts', $row + ['source' => 'manual', 'status' => 'cleared', 'refunded' => 0, 'covered_fee' => 0, 'created_by' => current_actor_name(), 'created_at' => now()]);
            if ($old) sync_raised($old['trip_id'] ? (int)$old['trip_id'] : null, $old['person_id'] ? (int)$old['person_id'] : null);
            sync_raised($row['trip_id'], $row['person_id']);
        });
        if (!$old) log_activity($gt, 'Recorded a ' . money($amt, 2) . ' gift for ' . gift_for($row));
        flash($old ? 'Gift updated' : 'Gift recorded: ' . money($amt, 2));
        if ($old) { header('Location: ' . safe_path(ps('back'), '/admin/giving.php')); exit; }
        back();
    case 'gift_void':
        $g = one('SELECT * FROM gifts WHERE id = ?', [$id]); if (!$g) back();
        if (!$staff && !can('giving', (int)$g['trip_id'], 2)) { http_response_code(403); exit("You don't have permission to do that."); }
        if ($why = gift_locked($g)) fail($why);
        if (!ps('reason')) fail('Say why you\'re voiding it (for example "Entered twice" or "Check bounced").');
        tx(function () use ($g, $id) {
            update('gifts', $id, ['status' => 'void', 'void_reason' => ps('reason', 200), 'voided_by' => current_actor_name(), 'voided_at' => now()]);
            sync_raised($g['trip_id'] ? (int)$g['trip_id'] : null, $g['person_id'] ? (int)$g['person_id'] : null);
        });
        flash('Gift voided. It stays in the history but no longer counts.'); header('Location: /admin/giving.php'); exit;
    case 'gift_thank':
        $g = one('SELECT * FROM gifts WHERE id = ?', [$id]);
        if (!$g || (!$staff && (int)$g['person_id'] !== (int)$me)) { http_response_code(403); exit('Not yours.'); }
        update('gifts', $id, ['thanked_at' => $g['thanked_at'] ? null : now()]); flash($g['thanked_at'] ? 'Marked not thanked' : 'Marked thanked'); back();
    case 'batch_save':
        need_staff($staff);
        $bid = insert('batches', ['name' => ps('name', 120) ?: 'Deposit ' . date('M j'), 'deposit_date' => post('deposit_date') ?: date('Y-m-d'), 'status' => 'open', 'created_by' => current_actor_name(), 'created_at' => now()]);
        flash('Batch started. Add each check and cash gift.'); header("Location: /admin/giving.php?v=batches&batch=$bid"); exit;
    case 'batch_close':
        need_staff($staff);
        $b = batch($id); if (!$b) back();
        if ($b['status'] === 'closed' && !ps('reason')) fail('Say why you\'re reopening this batch. It\'s kept in the history.');
        update('batches', $id, $b['status'] === 'open' ? ['status' => 'closed', 'closed_at' => now()] : ['status' => 'open', 'closed_at' => null]);
        if ($b['status'] === 'closed') audit('batch_reopened', 'batches', $id, null, ps('reason', 200));
        flash($b['status'] === 'open' ? 'Batch closed. Gifts in it are now locked.' : 'Batch reopened'); back();
    case 'donor_save':
        need_staff($staff);
        $row = []; foreach (['first_name', 'last_name', 'org', 'email', 'phone', 'address', 'city', 'state', 'zip', 'notes'] as $k) $row[$k] = nn(ps($k, 300));
        if ($row['email']) $row['email'] = strtolower($row['email']);
        if (!$row['first_name'] && !$row['last_name'] && !$row['org']) fail('Add a name.');
        if ($id) update('donors', $id, $row); else $id = insert('donors', $row + ['created_at' => now()]);
        flash('Donor saved'); header("Location: /admin/donor.php?id=$id"); exit;
    case 'payment_save':
        if (preg_match('/^m(\d+)-(\d+)$/', (string)post('for'), $mm)) { $trip_id = (int)$mm[1]; $_POST['person_id'] = $mm[2]; }
        if (!$staff) need('giving', $trip_id);
        $pp = (int)post('person_id'); $amt = round((float)post('amount'), 2);
        if (!member_of($trip_id, $pp) || $amt <= 0) fail('Pick a traveler and an amount.');
        tx(function () use ($trip_id, $pp, $amt) {
            insert('payments', ['trip_id' => $trip_id, 'person_id' => $pp, 'amount' => $amt, 'method' => array_key_exists((string)post('method'), GIFT_METHODS) ? post('method') : 'check',
                'kind' => array_key_exists((string)post('kind'), PAYMENT_KINDS) ? post('kind') : 'payment', 'paid_on' => post('paid_on') ?: date('Y-m-d'), 'note' => nn(ps('note', 300)), 'status' => 'ok', 'created_by' => current_actor_name(), 'created_at' => now()]);
            sync_raised($trip_id, $pp);
        });
        flash((PAYMENT_KINDS[post('kind')] ?? 'Payment') . ' recorded'); back();
    case 'payment_void':
        $pm = one('SELECT * FROM payments WHERE id = ?', [$id]); if (!$pm) back();
        if (!$staff) need('giving', (int)$pm['trip_id']);
        if ($pm['stripe_id']) fail('This was paid online. Refund it in Stripe and it updates here by itself.');
        if (!ps('reason')) fail('Say why you\'re voiding this payment.');
        tx(function () use ($pm, $id) { update('payments', $id, ['status' => 'void', 'void_reason' => ps('reason', 200), 'voided_by' => current_actor_name(), 'voided_at' => now()]); sync_raised((int)$pm['trip_id'], (int)$pm['person_id']); });
        flash('Payment voided. It stays in the history but no longer counts.'); back();

    // ---------------- Communication and trip tools ----------------
    case 'task_remind':
        $tk = one('SELECT * FROM tasks WHERE id = ?', [$id]); if (!$tk) back();
        need('tasks', (int)$tk['trip_id']);
        $who = array_values(array_filter(travelers((int)$tk['trip_id']), function ($m) use ($id, $tk) {
            foreach (tasks_for((int)$tk['trip_id'], (int)$m['person_id']) as $t) if ((int)$t['id'] === $id) return !$t['done_at'];
            return false;
        }));
        $people = array_map(fn($m) => ['person_id' => (int)$m['person_id'], 'name' => full_name($m), 'email' => $m['email'], 'phone' => $m['phone'], 'sms_ok' => (bool)val('SELECT sms_ok FROM people WHERE id = ?', [$m['person_id']])], $who);
        $msg = "Quick reminder: \"" . $tk['title'] . "\"" . ($tk['due_date'] ? ' is due ' . fdate($tk['due_date'], 'F j') : ' is still open') . ". You can do it from your trip page: " . site_url('/trip/');
        $em = (bool)post('email', '1'); $tx = (bool)post('text');
        $r = broadcast((int)$tk['trip_id'], $people, trip((int)$tk['trip_id'])['name'] . ': ' . $tk['title'], $msg, $em, $tx);
        log_activity((int)$tk['trip_id'], 'Sent a reminder about ' . $tk['title']);
        flash('Reminder for ' . count($people) . ' ' . (count($people) === 1 ? 'person' : 'people') . '.' . sent_note($r, $em, $tx)); back();
    case 'guardian_save':
        need_staff($staff);
        $pp = (int)post('person_id');
        if (!ps('name')) fail('Add their name.');
        $em = strtolower(ps('email', 200)); if ($em && !filter_var($em, FILTER_VALIDATE_EMAIL)) fail('Check the email address.');
        insert('guardians', ['person_id' => $pp, 'name' => ps('name', 120), 'rel' => nn(ps('rel', 40)) ?? 'Parent', 'email' => nn($em), 'phone' => nn(ps('phone', 40)), 'sms_ok' => post('sms_ok') ? 1 : 0, 'token' => new_token(), 'created_at' => now()]);
        flash('Parent added. Send them their private link.'); back();
    case 'guardian_delete': need_staff($staff); delete_row('guardians', $id); flash('Parent removed. Their link no longer works.'); back();
    case 'guardian_rotate':
        need_staff($staff);
        update('guardians', $id, ['token' => new_token()]); flash('New private link made. The old one no longer works.'); back();
    case 'guardian_send':
        need_staff($staff);
        $g = one('SELECT * FROM guardians WHERE id = ?', [$id]); if (!$g) back(); $kid = person((int)$g['person_id']);
        $ok = $g['email'] && send_email($g['email'], 'Follow ' . ($kid['preferred_name'] ?: $kid['first_name']) . "'s mission trip", "Hi " . strtok($g['name'], ' ') . ",\n\nHere's your private page for " . full_name($kid) . "'s trip. It has the schedule, flights, packing list, who to call, and updates from the leaders:\n\n" . parent_url($g) . "\n\nPlease don't share this link.", null, (int)$g['person_id']);
        flash($ok ? 'Link emailed to ' . $g['name'] : ($g['email'] ? "Saved the email. Email isn't set up yet, so copy the link and text it." : 'Add an email for them first, or copy the link.'), $ok ? 'ok' : 'error'); back();
    case 'checkin_start':
        need('ontrip', $trip_id);
        $cid = insert('checkins', ['trip_id' => $trip_id, 'label' => ps('label', 80) ?: 'Headcount ' . date('g:i A'), 'created_by' => current_actor_name(), 'created_at' => now()]);
        header("Location: /admin/trip.php?id=$trip_id&tab=ontrip&c=$cid"); exit;
    case 'checkin_mark':
        $cid = (int)post('checkin_id'); $tid = trip_of('checkins', $cid); need('ontrip', $tid);
        $pp = (int)post('person_id'); $st = in_array(post('status'), ['here', 'missing'], true) ? post('status') : null;
        if (!member_of($tid, $pp)) { http_response_code(400); exit; }
        tx(function () use ($cid, $pp, $st) { q('DELETE FROM checkin_marks WHERE checkin_id = ? AND person_id = ?', [$cid, $pp]); if ($st) insert('checkin_marks', ['checkin_id' => $cid, 'person_id' => $pp, 'status' => $st, 'marked_at' => now()]); });
        if (post('ajax')) { http_response_code(204); exit; }
        back();
    case 'incident_save':
        need('ontrip', $trip_id);
        if (!ps('description')) fail('Describe what happened.');
        $row = ['trip_id' => $trip_id, 'person_id' => (int)post('person_id') ?: null, 'happened_at' => (post('happened_at') ? str_replace('T', ' ', ps('happened_at')) : now()),
                'kind' => in_array(post('kind'), ['medical', 'safety', 'behavior', 'lost', 'other'], true) ? post('kind') : 'other', 'severity' => in_array(post('severity'), ['low', 'medium', 'high'], true) ? post('severity') : 'low',
                'description' => ps('description', 5000), 'action_taken' => nn(ps('action_taken', 5000)), 'parent_notified' => post('parent_notified') ? 1 : 0, 'followup' => nn(ps('followup', 2000))];
        if ($id) { if (trip_of('incidents', $id) !== $trip_id) back(); update('incidents', $id, $row); }
        else { insert('incidents', $row + ['resolved' => 0, 'reported_by' => current_actor_name(), 'created_at' => now()]); log_activity($trip_id, 'Logged an incident report'); if ($row['severity'] === 'high') alert_staff('High-severity incident on ' . (trip($trip_id)['name'] ?? 'a trip'), 'Logged by ' . current_actor_name() . '. Open the trip\'s "On the trip" tab for details.'); }
        flash('Incident saved'); back();
    case 'incident_resolve':
        need('ontrip', trip_of('incidents', $id));
        $x = one('SELECT * FROM incidents WHERE id = ?', [$id]); update('incidents', $id, ['resolved' => $x['resolved'] ? 0 : 1]); flash($x['resolved'] ? 'Reopened' : 'Marked resolved'); back();
    case 'chat_send':
        $thread = (string)post('thread'); $body = ps('body', 4000);
        $leader = !$staff && can('messages', $trip_id, 2);
        if (!chat_thread_ok($thread, $trip_id, $staff, $me)) { http_response_code(403); exit('Not your conversation.'); }
        if ($body !== '' && !rate_limited('chat:' . session_id(), 30, 60)) {
            $actor = $staff ? null : person_basic((int)$me);
            insert('chat', ['trip_id' => $trip_id, 'thread' => $thread, 'person_id' => $staff ? null : $me, 'author' => $staff ? current_actor_name() : full_name($actor),
                'staff' => $staff || $leader || in_array(member_of($trip_id, (int)$me)['role'] ?? '', ['leader', 'admin'], true) ? 1 : 0, 'body' => $body, 'created_at' => now()]);
        }
        if (post('ajax')) { http_response_code(204); exit; }
        back();
    case 'statements_email':
        need_staff($staff);
        $yr = (int)post('year') ?: (int)date('Y') - 1; $sent = 0; $skipped = 0;
        foreach (all("SELECT d.*, SUM(g.amount - COALESCE(g.refunded,0)) AS total FROM donors d JOIN gifts g ON g.donor_id = d.id WHERE " . str_replace('status', 'g.status', GIFT_COUNTS) . " AND g.gift_date BETWEEN ? AND ? GROUP BY d.id", ["$yr-01-01", "$yr-12-31"]) as $d) {
            if (!$d['email']) { $skipped++; continue; }
            if (queue_email($d['email'], "Your $yr giving statement from " . church_name(), statement_text($d, $yr))) $sent++;
        }
        if (mail_ready()) save_site_settings(['locked_years' => array_values(array_unique(array_merge(locked_years(), [$yr])))]);
        flash(mail_ready() ? "$sent statements are on their way. $yr gifts are now locked so statements can't change." . ($skipped ? " $skipped donors have no email; print theirs." : '') : "Email isn't set up yet, so statements were saved, not sent. Print them instead.", mail_ready() ? 'ok' : 'error'); back();
    case 'year_unlock':
        need_staff($staff);
        $yr = (int)post('year');
        save_site_settings(['locked_years' => array_values(array_diff(locked_years(), [$yr]))]);
        audit('year_unlocked', 'settings', $yr, null, ps('reason', 200));
        flash("$yr gifts can be edited again. If you change anything, send corrected statements."); back();
    case 'bg_save':
        need_staff($staff);
        $pp = (int)post('person_id'); $st = array_key_exists((string)post('status'), BG_STATUS) ? post('status') : 'requested';
        $row = ['person_id' => $pp, 'provider' => nn(ps('provider', 80)), 'status' => $st,
                'completed_at' => nn(post('completed_at')) ?? ($st === 'clear' ? date('Y-m-d') : null), 'expires_on' => nn(post('expires_on')), 'note' => nn(ps('note', 500))];
        if ($st === 'clear' && !$row['expires_on']) $row['expires_on'] = date('Y-m-d', strtotime(($row['completed_at'] ?: date('Y-m-d')) . ' +3 years'));
        if ($id) update('background_checks', $id, $row);
        else insert('background_checks', $row + ['requested_at' => post('requested_at') ?: date('Y-m-d'), 'created_by' => current_actor_name(), 'created_at' => now()]);
        flash('Background check saved'); back();
    case 'pco_link':
        need_staff($staff);
        $pp = (int)post('person_id'); $pid_pco = preg_replace('/\D+/', '', (string)post('pco_id'));
        if (!$pid_pco) fail('Pick a Planning Center person.');
        if (one('SELECT id FROM people WHERE pco_id = ? AND id <> ?', [$pid_pco, $pp])) fail('That Planning Center person is already linked to someone else here.');
        update('people', $pp, ['pco_id' => $pid_pco]);
        try { [$n, $bg] = pco_pull($pp); flash("Linked. Filled in $n field" . ($n === 1 ? '' : 's') . ($bg ? ' and their background check' : '') . ' from Planning Center.'); }
        catch (Throwable $e) { app_log('PCO pull: ' . $e->getMessage(), 'errors'); flash("Linked, but Planning Center didn't answer. Try \"Pull latest\" in a minute.", 'error'); }
        header('Location: /admin/person.php?id=' . $pp); exit;
    case 'pco_pull':
        need_staff($staff);
        try { [$n, $bg] = pco_pull((int)post('person_id'), (bool)post('overwrite')); flash("Updated $n field" . ($n === 1 ? '' : 's') . ($bg ? ', plus their background check' : '') . '.'); }
        catch (Throwable $e) { app_log('PCO pull: ' . $e->getMessage(), 'errors'); flash("Planning Center didn't answer. Try again in a minute.", 'error'); }
        back();
    case 'pco_unlink': need_staff($staff); update('people', (int)post('person_id'), ['pco_id' => null, 'pco_synced_at' => null]); flash('Unlinked from Planning Center'); back();
    case 'pco_import':
        need_staff($staff);
        $pid_pco = preg_replace('/\D+/', '', (string)post('pco_id'));
        if ($have = one('SELECT id FROM people WHERE pco_id = ?', [$pid_pco])) { header('Location: /admin/person.php?id=' . $have['id']); exit; }
        $new = insert('people', ['first_name' => ps('first_name', 80) ?: 'New', 'last_name' => ps('last_name', 80) ?: 'Person', 'pco_id' => $pid_pco, 'created_at' => now()]);
        try { pco_pull($new, true); flash('Added from Planning Center'); } catch (Throwable $e) { app_log('PCO pull: ' . $e->getMessage(), 'errors'); flash("Added, but Planning Center didn't answer. Try \"Pull latest\" in a minute.", 'error'); }
        header('Location: /admin/person.php?id=' . $new); exit;
    case 'page_save':
        $pp = ($staff || can('giving', $trip_id, 2)) && post('person_id') ? (int)post('person_id') : (int)$me;
        $mem = member_of($trip_id, $pp);
        if (!$mem) { http_response_code(403); exit('Not your page.'); }
        $row = ['page_story' => ps('page_story', 3000)];
        $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', ps('page_slug', 60)));
        if ($slug && $slug !== $mem['page_slug']) { if ($why = valid_page_slug($slug, (int)$mem['id'])) fail($why); $row['page_slug'] = $slug; }
        if ($fid = save_upload('page_photo', $trip_id, $pp, 'Fundraising page photo', 'pagephoto', ['visible' => 0], ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic'])) $row['page_photo_id'] = $fid;
        $changed = $row['page_story'] !== (string)$mem['page_story'] || isset($row['page_slug']) || isset($row['page_photo_id']);
        $reviewer = $staff || can('giving', $trip_id, 2);
        if (post('publish') && in_array($mem['page_status'], ['draft', null, ''], true)) $row['page_status'] = approve_pages() && !$reviewer ? 'pending' : 'live';
        elseif ($changed && $mem['page_status'] === 'live' && approve_pages() && !$reviewer) $row['page_status'] = 'pending';   // edits to a live page get a fresh look
        update('members', (int)$mem['id'], $row);
        flash(isset($row['page_status']) ? ($row['page_status'] === 'pending' ? ($mem['page_status'] === 'live' ? 'Saved. Your leader will take a quick look at the changes, then it goes live again.' : 'Sent to your leader for a quick look. It goes live once approved.') : 'Your page is live!') : 'Page saved'); back();
    case 'page_review':
        $mem = one('SELECT * FROM members WHERE id = ?', [$id]); if (!$mem) back();
        if (!$staff) need('giving', (int)$mem['trip_id']);
        $st = in_array(post('status'), ['live', 'hidden', 'draft'], true) ? post('status') : 'live';
        update('members', $id, ['page_status' => $st]);
        if ($st === 'live' && ($pp = person((int)$mem['person_id'])) && $pp['email']) queue_email($pp['email'], 'Your fundraising page is live', "Your page is ready to share:\n\n" . page_url($mem) . "\n\nText it to a few people today with one line about why you're going.", (int)$mem['trip_id'], (int)$mem['person_id']);
        flash(['live' => 'Page approved and live', 'hidden' => 'Page hidden', 'draft' => 'Page set back to not shared'][$st]); back();
    case 'stripe_pay':
        if ($staff || !$me) fail('Only travelers pay from here.');
        $amt = round((float)post('amount'), 2); $mem = member_of($trip_id, (int)$me);
        if (!$mem || $amt < 5 || $amt > 10000) fail('Enter an amount from $5 to $10,000.');
        if (!stripe_ready()) fail('Online payments open once Stripe is connected. For now, pay by check at the church office.');
        try { header('Location: ' . stripe_checkout('payment', $amt, 'Trip payment · ' . trip($trip_id)['name'], ['trip_id' => $trip_id, 'person_id' => $me], site_url('/trip/fundraising.php?paid=1'), site_url('/trip/fundraising.php'), person((int)$me)['email'] ?: null)); exit; }
        catch (Throwable $e) { app_log('Stripe checkout: ' . $e->getMessage(), 'errors'); fail('Could not start the payment. Try again in a minute.'); }
    case 'site_setting':
        need_staff($staff);
        if (post('key') === 'approve_pages') save_site_settings(['approve_pages' => (bool)post('value')]);
        if (post('key') === 'leader_perms') { $p = []; foreach (PERM_AREAS as $k => $_) $p[$k] = max(0, min(2, (int)($_POST['perm'][$k] ?? 0))); save_site_settings(['leader_perms' => $p]); }
        flash('Saved'); back();
    case 'cal_rotate':
        need_staff($staff);
        if (post('kind') === 'trip') update('trips', $id, ['cal_token' => new_token()]); else update('people', $id, ['cal_token' => new_token()]);
        flash('New calendar link made. The old one stops working.'); back();
    case 'test_email':
        need_staff($staff);
        $ok = send_email(ps('to', 200), 'Journey Missions test email', 'If you can read this, email from Journey Missions is working.');
        flash($ok ? 'Test email sent. Check the inbox (and the spam folder).' : (mail_ready() ? 'The mail server refused it. Settings → Email and text shows the reason.' : 'Email is not set up yet.'), $ok ? 'ok' : 'error'); back();
    case 'outbox_retry':
        need_staff($staff);
        q("UPDATE outbox SET status = 'queued', attempts = 0, send_after = NULL WHERE status IN ('failed', 'not_sent') AND id = ?", [$id]);
        flash(deliver($id) ? 'Sent' : 'Still not sending. Check the email settings.'); back();
    case 'backup_download':
        need_staff($staff);
        $path = backup_now('download');
        if (!$path) fail("Couldn't make a backup.");
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="journey-missions-' . date('Y-m-d') . '.sqlite"');
        header('Content-Length: ' . filesize($path));
        readfile($path); exit;

    // ---------------- Demo data and photos ----------------
    case 'demo_toggle':
        need_staff($staff);
        $_SESSION['demo'] = post('demo') === '1';
        unset($_SESSION['person_id']);
        flash($_SESSION['demo'] ? 'Demo data is on, only for you. Everyone else, and every public page, still uses the real data.' : 'Demo data is off. You are back to your real data.');
        header('Location: /admin/settings.php?s=demo'); exit;
    case 'demo_reset':
        need_staff($staff);
        if (!demo_on()) fail('Turn demo data on first.');
        foreach (['', '-wal', '-shm'] as $suffix) if (is_file(demo_db_path() . $suffix)) unlink(demo_db_path() . $suffix);
        foreach (glob(data_dir() . '/uploads-demo/*') ?: [] as $f) @unlink($f);
        unset($_SESSION['person_id']);
        flash('Demo data reset to the original sample');
        header('Location: /admin/settings.php?s=demo'); exit;
    case 'photo_upload':
        need_staff($staff);
        $n = 0;
        $files = $_FILES['photos'] ?? null;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $_FILES['one'] = ['name' => $name, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                if (save_upload('one', $trip_id, null, pathinfo($name, PATHINFO_FILENAME), 'photo', ['visible' => 1, 'sort' => 100 + $i], ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic'])) $n++;
            }
        }
        if ($n) log_activity($trip_id, "Added $n trip photo" . ($n > 1 ? 's' : ''));
        flash($n ? "Added $n photo" . ($n > 1 ? 's' : '') : 'Choose one or more photos first.', $n ? 'ok' : 'error'); back();
    case 'photo_first':
        need_staff($staff);
        $f = one("SELECT * FROM files WHERE id = ? AND kind = 'photo'", [$id]);
        if ($f) tx(function () use ($f, $id) { q("UPDATE files SET sort = COALESCE(sort, 100) + 1 WHERE trip_id = ? AND kind = 'photo'", [$f['trip_id']]); update('files', $id, ['sort' => 0]); });
        flash('Cover photo set'); back();

    default:
        fail('Unknown action');
}
