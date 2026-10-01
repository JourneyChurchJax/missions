<?php
// Sign a document. Travelers: ?task=ID (signed in). Parents: ?g=their private token&task=ID (no sign-in).
require __DIR__ . '/inc/bootstrap.php';

$task = one("SELECT * FROM tasks WHERE id = ? AND type = 'sign'", [(int)($_GET['task'] ?? 0)]);
$guardian = !empty($_GET['g']) ? one('SELECT * FROM guardians WHERE token = ?', [(string)$_GET['g']]) : null;
if ($guardian) {
    $role = 'parent'; $pid = (int)$guardian['person_id']; $back = '/parent/?t=' . $guardian['token'];
} else {
    require_preview();
    if (($_SESSION['view'] ?? 'staff') === 'staff') { header('Location: /admin/'); exit; }
    $role = 'traveler'; $pid = (int)acting_person_id(); $back = '/trip/';
}
$kid = person($pid);
$ok = $task && $kid && member_of((int)$task['trip_id'], $pid) && ($role === 'traveler' || needs_parent_signature($task, $pid));
$doc = $ok && $task['file_id'] ? one('SELECT * FROM files WHERE id = ?', [(int)$task['file_id']]) : null;
$mine = $ok ? one('SELECT * FROM signatures WHERE task_id = ? AND person_id = ? AND signer_role = ?', [$task['id'], $pid, $role]) : null;
$err = '';

if ($ok && !$mine && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim((string)post('signer_name'));
    $img = clean_signature_image((string)post('sig_image'));
    if (mb_strlen($name) < 3) $err = 'Type your full name.';
    elseif (!$img) $err = 'Draw your signature in the box.';
    elseif (empty($_POST['consent'])) $err = 'Check the box to agree to sign electronically.';
    else {
        insert('signatures', ['task_id' => $task['id'], 'person_id' => $pid, 'guardian_id' => $guardian['id'] ?? null, 'signer_role' => $role, 'signer_name' => mb_substr($name, 0, 120),
            'sig_image' => $img, 'agreement' => (string)$task['description'], 'doc_title' => $doc['title'] ?? $task['title'], 'ip' => client_ip(),
            'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), 'signed_at' => now()]);
        sync_signature_task($task, $pid);
        log_activity((int)$task['trip_id'], ($role === 'parent' ? 'A parent of ' . full_name($kid) : full_name($kid)) . ' signed ' . ($doc['title'] ?? $task['title']));
        flash('Signed. Thank you!');
        header('Location: ' . $back); exit;
    }
}
[$me_signed, $parent_signed, $need_parent] = $ok ? signature_state($task, $pid) : [false, false, false];
$doc_link = $doc ? '/file.php?id=' . (int)$doc['id'] . ($guardian ? '&g=' . urlencode($guardian['token']) : '') : null;
public_open('Sign');
?>
<main class="pub-main">
<?php if (!$ok): ?>
  <section class="tile xl" style="padding:36px;text-align:center;gap:10px"><h1 class="disp">Nothing to sign here.</h1><p class="muted" style="margin:0">This link may be old, or it's already taken care of.</p><a class="btn" href="<?= e($back) ?>" style="align-self:center">Go back</a></section>
<?php elseif ($mine): ?>
  <section class="tile xl" style="padding:36px;gap:14px"><span class="pill pill-ok" style="align-self:flex-start">Signed</span><h1 class="disp" style="font-size:34px"><?= e($doc['title'] ?? $task['title']) ?></h1>
    <p style="margin:0">Signed by <?= e($mine['signer_name']) ?> on <?= fdate($mine['signed_at'], 'F j, Y \a\t g:i A') ?>.</p>
    <?php if ($mine['sig_image']): ?><img src="<?= e($mine['sig_image']) ?>" alt="Signature" style="max-width:320px;background:#fff;border-radius:var(--r-sm)"><?php endif; ?>
    <?php if ($role === 'traveler' && $need_parent && !$parent_signed): ?><div class="note"><strong>A parent still needs to sign</strong><div class="muted small">Because you're under 18, a parent or guardian signs too. Your leader can send them a link.</div></div><?php endif; ?>
    <a class="btn" href="<?= e($back) ?>" style="align-self:flex-start">Done</a></section>
<?php else: ?>
  <section style="display:flex;flex-direction:column;gap:8px">
    <div class="muted small"><?= e(trip((int)$task['trip_id'])['name']) ?> · <?= $role === 'parent' ? 'Parent or guardian signature for ' . e(full_name($kid)) : 'Your signature' ?></div>
    <h1 class="disp"><?= e($doc['title'] ?? $task['title']) ?></h1>
  </section>
  <?php if ($doc_link): ?><a class="cell group" href="<?= e($doc_link) ?>" target="_blank" style="min-height:64px"><span class="av">📄</span><span class="grow"><strong>Read the document first</strong><span class="muted small" style="display:block"><?= e($doc['title']) ?> opens in a new tab</span></span><span class="chev">›</span></a><?php endif; ?>
  <form class="form tile xl" method="post" style="padding:28px;gap:18px" data-sign>
    <?= csrf() ?><input type="hidden" name="sig_image" value="">
    <?php if ($err): ?><div class="note" style="box-shadow:inset 3px 0 0 var(--ember)"><?= e($err) ?></div><?php endif; ?>
    <div class="note" style="font-size:16px;line-height:1.55"><?= nl2br(e($task['description'] ?: 'I have read and agree to this document.')) ?></div>
    <label class="lab">Your full name<input type="text" name="signer_name" value="<?= e(post('signer_name') ?? ($role === 'traveler' ? trim($kid['first_name'] . ' ' . $kid['last_name']) : $guardian['name'])) ?>" required autocomplete="name"></label>
    <div class="lab">Sign here<canvas class="sigpad" width="640" height="180" aria-label="Signature pad: draw your signature"></canvas><button type="button" class="link-btn" data-clear style="align-self:flex-end">Clear</button></div>
    <label class="chk" style="display:flex;gap:10px;align-items:flex-start;font-size:15px"><input type="checkbox" name="consent" value="1" style="margin-top:4px"> I agree to sign electronically. My typed name and drawn signature count the same as signing on paper.</label>
    <div class="actions"><a class="btn" href="<?= e($back) ?>">Not now</a><button class="btn btn-primary" type="submit">Sign</button></div>
  </form>
  <p class="muted small" style="text-align:center">We save your name, signature, the date and time, and your device's internet address with this signature.</p>
<?php endif; ?>
</main>
<script src="/assets/app.js?v=6"></script>
</body>
</html>
