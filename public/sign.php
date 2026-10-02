<?php
// Sign a document. Travelers: ?task=ID (signed in). Parents: ?g=their private link&task=ID, then a code we send them.
if (!empty($_GET['g'])) { define('REAL_DB', true); define('ACTOR', 'Parent'); }
require __DIR__ . '/inc/bootstrap.php';

$task = one("SELECT * FROM tasks WHERE id = ? AND type = 'sign'", [gi('task')]);
$guardian = g('g') !== '' ? one('SELECT * FROM guardians WHERE token = ?', [g('g')]) : null;
if ($guardian && !guardian_link_valid($guardian)) $guardian = null;
if (g('g') !== '' && !$guardian) { $role = 'parent'; $pid = 0; $back = '/'; }
elseif ($guardian) {
    $role = 'parent'; $pid = (int)$guardian['person_id']; $back = '/parent/?t=' . $guardian['token'];
} else {
    require_preview();
    if (impersonating()) { flash("You're previewing as a traveler. Signing is turned off in preview.", 'error'); header('Location: /trip/'); exit; }
    if (is_staff_session()) { header('Location: /admin/'); exit; }
    $role = 'traveler'; $pid = (int)acting_person_id(); $back = '/trip/';
}
$kid = $pid ? person($pid) : null;
$ok = $task && $kid && member_of((int)$task['trip_id'], $pid) && ($role === 'traveler' || needs_parent_signature($task, $pid));
$doc = $ok && $task['file_id'] ? one('SELECT * FROM files WHERE id = ?', [(int)$task['file_id']]) : null;
$mine = $ok ? one('SELECT * FROM signatures WHERE task_id = ? AND person_id = ? AND signer_role IN (?, ?)', [$task['id'], $pid, $role, 'paper']) : null;
$verified = $role === 'traveler' || ($guardian && guardian_verified($guardian));
$err = ''; $note = '';
$self = '/sign.php?task=' . (int)($task['id'] ?? 0) . ($guardian ? '&g=' . urlencode($guardian['token']) : '');

if ($ok && !$mine && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)post('do');
    if ($role === 'parent' && $do === 'send_code') {
        $note = guardian_send_code($guardian) ? 'We sent a 6-digit code to ' . ($guardian['email'] && mail_ready() ? 'your email' : 'your phone') . '. It works for 15 minutes.' : '';
        if (!$note) $err = mail_ready() || text_ready() ? 'We couldn\'t send a code right now. Wait a few minutes and try again.' : 'Online signing for parents isn\'t ready yet. Ask the trip leader for a paper form.';
    } elseif ($role === 'parent' && $do === 'check_code') {
        if (guardian_check_code($guardian, ps('code', 10))) { header('Location: ' . $self); exit; }
        $err = 'That code didn\'t match or has expired. Send a new one.';
        $guardian = one('SELECT * FROM guardians WHERE id = ?', [$guardian['id']]);
    } elseif ($do === 'sign' && $verified) {
        $name = ps('signer_name', 120);
        $mode = post('mode') === 'typed' ? 'typed' : 'draw';
        $img = $mode === 'draw' ? clean_signature_image(is_string($_POST['sig_image'] ?? null) ? $_POST['sig_image'] : null) : null;
        if (mb_strlen($name) < 3) $err = 'Type your full name.';
        elseif ($mode === 'draw' && !$img) $err = 'Draw your signature in the box, or choose "Type my name instead".';
        elseif (empty($_POST['consent'])) $err = 'Check the box to agree to sign electronically.';
        elseif ($role === 'parent' && empty($_POST['is_guardian'])) $err = 'Confirm that you are this traveler\'s parent or legal guardian.';
        else {
            $shown = (string)$task['description'] ?: 'I have read and agree to this document.';
            record_signature($task, $pid, $role, $name, $img, $mode, $guardian, $shown);
            log_activity((int)$task['trip_id'], ($role === 'parent' ? 'A parent of ' . full_name($kid) : full_name($kid)) . ' signed ' . ($doc['title'] ?? $task['title']));
            flash('Signed. A copy is on its way to your email.');
            header('Location: ' . $back); exit;
        }
    }
}
[$me_signed, $parent_signed, $need_parent] = $ok ? signature_state($task, $pid) : [false, false, false];
$doc_link = $doc ? '/file.php?id=' . (int)$doc['id'] . ($guardian ? '&g=' . urlencode($guardian['token']) : '') : null;
public_open('Sign');
?>
<main class="pub-main" id="main">
<?php if (!$ok): ?>
  <section class="tile xl center-tile"><h1 class="disp">Nothing to sign here.</h1><p class="muted" style="margin:0">This link may be old, or it's already taken care of.</p><a class="btn" href="<?= e($back) ?>">Go back</a></section>
<?php elseif ($mine): ?>
  <section class="tile xl stack-16" style="padding:36px"><span class="pill pill-ok" style="align-self:flex-start">Signed</span><h1 class="disp" style="font-size:34px"><?= e($doc['title'] ?? $task['title']) ?></h1>
    <p style="margin:0"><?= $mine['signer_role'] === 'paper' ? 'Signed on paper. ' . e($mine['agreement']) : 'Signed by ' . e($mine['signer_name']) . ' on ' . fdate($mine['signed_at'], 'F j, Y \a\t g:i A') . '.' ?></p>
    <?php if ($mine['sig_image']): ?><img src="<?= e($mine['sig_image']) ?>" alt="Signature of <?= e($mine['signer_name']) ?>" class="sig-img"><?php elseif ($mine['sig_mode'] === 'typed'): ?><div class="sig-typed"><?= e($mine['signer_name']) ?></div><?php endif; ?>
    <?php if ($role === 'traveler' && $need_parent && !$parent_signed): ?><div class="note"><strong>A parent still needs to sign</strong><div class="muted small">Because you're under 18, a parent or guardian signs too. Your leader can send them a link.</div></div><?php endif; ?>
    <a class="btn" href="<?= e($back) ?>" style="align-self:flex-start">Done</a></section>
<?php elseif (!$verified): ?>
  <section class="stack-8"><div class="muted small"><?= e(trip((int)$task['trip_id'])['name']) ?> · Parent or guardian signature for <?= e(full_name($kid)) ?></div><h1 class="disp"><?= e($doc['title'] ?? $task['title']) ?></h1></section>
  <section class="tile xl stack-16 pad-28">
    <p style="margin:0">First, let's make sure it's you. We'll send a 6-digit code to <?= $guardian['email'] && mail_ready() ? 'the email' : 'the phone number' ?> we have for <?= e($guardian['name']) ?>.</p>
    <?php if ($err): ?><div class="note error" role="alert"><?= e($err) ?></div><?php endif; ?>
    <?php if ($note): ?><div class="note" role="status"><?= e($note) ?></div><?php endif; ?>
    <?php if ($note || ($guardian['otp_hash'] && (int)$guardian['otp_expires'] > time())): ?>
      <form class="form" method="post" action="<?= e($self) ?>"><?= csrf() ?><input type="hidden" name="do" value="check_code">
        <label class="lab">Code<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus></label>
        <div class="actions"><button class="btn btn-primary" type="submit">Continue</button></div></form>
    <?php endif; ?>
    <form method="post" action="<?= e($self) ?>"><?= csrf() ?><input type="hidden" name="do" value="send_code"><button class="btn<?= $note ? '' : ' btn-primary' ?>" type="submit"><?= $note ? 'Send a new code' : 'Send my code' ?></button></form>
  </section>
<?php else: ?>
  <section class="stack-8">
    <div class="muted small"><?= e(trip((int)$task['trip_id'])['name']) ?> · <?= $role === 'parent' ? 'Parent or guardian signature for ' . e(full_name($kid)) : 'Your signature' ?></div>
    <h1 class="disp"><?= e($doc['title'] ?? $task['title']) ?></h1>
  </section>
  <?php if ($doc_link): ?><a class="cell group" href="<?= e($doc_link) ?>" target="_blank" rel="noopener" style="min-height:64px"><span class="av" aria-hidden="true">Doc</span><span class="grow"><strong>Read the document first</strong><span class="muted small block"><?= e($doc['title']) ?> opens in a new tab</span></span><span class="chev" aria-hidden="true">›</span></a><?php endif; ?>
  <form class="form tile xl pad-28" method="post" action="<?= e($self) ?>" data-sign>
    <?= csrf() ?><input type="hidden" name="do" value="sign"><input type="hidden" name="sig_image" value=""><input type="hidden" name="mode" value="draw">
    <?php if ($err): ?><div class="note error" role="alert"><?= e($err) ?></div><?php endif; ?>
    <div class="note agreement"><?= nl2br(e($task['description'] ?: 'I have read and agree to this document.')) ?></div>
    <label class="lab">Your full name<input type="text" name="signer_name" value="<?= e(ps('signer_name', 120) ?: ($role === 'traveler' ? trim($kid['first_name'] . ' ' . $kid['last_name']) : $guardian['name'])) ?>" required autocomplete="name" maxlength="120" data-signer></label>
    <div class="lab" data-draw-area><span id="sig-label">Sign here with your finger, a pen or a mouse</span><canvas class="sigpad" width="640" height="180" role="img" aria-labelledby="sig-label"></canvas>
      <span class="row-between"><button type="button" class="linklike" data-type-instead>Type my name instead</button><button type="button" class="linklike" data-clear>Clear</button></span></div>
    <div class="lab" data-typed-area hidden><span>Your typed signature</span><div class="sig-typed" data-typed-preview></div><button type="button" class="linklike" data-draw-instead style="align-self:flex-start">Draw instead</button></div>
    <?php if ($role === 'parent'): ?><label class="chk"><input type="checkbox" name="is_guardian" value="1" required> I am <?= e(full_name($kid)) ?>'s parent or legal guardian.</label><?php endif; ?>
    <label class="chk"><input type="checkbox" name="consent" value="1" required> I agree to sign electronically. My name and signature count the same as signing on paper.</label>
    <div class="actions"><a class="btn" href="<?= e($back) ?>">Not now</a><button class="btn btn-primary" type="submit">Sign</button></div>
  </form>
  <p class="muted small center">We save your name, signature, the date and time, your device's internet address, and a fingerprint of the exact document. You'll get a copy by email.</p>
<?php endif; ?>
</main>
<?php public_close(); ?>
