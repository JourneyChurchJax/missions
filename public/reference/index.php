<?php
// Public reference form. The ?t= token in the link is the only key.
require dirname(__DIR__) . '/inc/bootstrap.php';

$r = isset($_GET['t']) ? one('SELECT * FROM app_refs WHERE token = ?', [(string)$_GET['t']]) : null;
$app = $r ? application((int)$r['application_id']) : null;
$p = $app ? person((int)$app['person_id']) : null;
$f = $app ? app_form((int)$app['form_id']) : null;
$err = '';

if ($r && $_SERVER['REQUEST_METHOD'] === 'POST' && $r['status'] !== 'received') {
    check_csrf();
    $ans = [];
    foreach (REF_QUESTIONS as $k => [$label, $kind]) $ans[$k] = trim((string)($_POST['a'][$k] ?? ''));
    $ans['relationship'] = $r['ref_type'];
    if ($ans['known'] === '' || $ans['recommend'] === '') $err = 'Please answer how you know them and whether you recommend them.';
    else {
        update('app_refs', (int)$r['id'], ['status' => 'received', 'received_at' => now(), 'answers' => json_encode($ans)]);
        header('Location: /reference/?t=' . $r['token']); exit;
    }
}

$name = $p ? ($p['preferred_name'] ?: $p['first_name']) . ' ' . $p['last_name'] : '';
public_open('Reference');
?>
<main class="pub-main">
<?php if (!$r || !$p): ?>
  <section class="tile xl" style="padding:36px;text-align:center;gap:10px"><h1 class="disp">We couldn't find that reference.</h1><p class="muted" style="margin:0">Check the link in your email, or ask the person who sent it.</p></section>
<?php elseif ($r['status'] === 'received'): ?>
  <section class="tile xl" style="padding:36px;gap:12px"><h1 class="disp">Thank you, <?= e(strtok($r['name'], ' ')) ?>.</h1><p style="margin:0;font-size:17px;line-height:1.55">Your reference for <?= e($name) ?> is in. Only the Journey missions team will read it.</p></section>
<?php else: ?>
  <section style="display:flex;flex-direction:column;gap:10px">
    <div class="muted small">Reference · <?= e($f['name']) ?></div>
    <h1 class="disp"><?= e($name) ?> asked you for a reference.</h1>
    <p style="margin:0;font-size:17px;line-height:1.55">They're applying for a Journey Church mission trip and listed you as their <?= e(strtolower($r['ref_type'])) ?> reference. This takes about five minutes. Only the missions team will read your answers.</p>
  </section>
  <form class="form tile xl" method="post" style="padding:28px;gap:18px">
    <?= csrf() ?>
    <?php if ($err): ?><div class="note" style="box-shadow:inset 3px 0 0 var(--ember)"><?= e($err) ?></div><?php endif; ?>
    <?php foreach (REF_QUESTIONS as $k => [$label, $kind]): $val = e($_POST['a'][$k] ?? ''); ?>
      <div class="lab" style="gap:8px"><span><?= e($label) ?><?= in_array($k, ['known', 'recommend'], true) ? ' <span class="req">*</span>' : '' ?></span>
      <?php if (str_starts_with($kind, 'choice:')): ?><div class="opts"><?php foreach (explode('|', substr($kind, 7)) as $o): ?><label><input type="radio" name="a[<?= $k ?>]" value="<?= e($o) ?>"<?= ($_POST['a'][$k] ?? '') === $o ? ' checked' : '' ?>><?= e($o) ?></label><?php endforeach; ?></div>
      <?php else: ?><textarea name="a[<?= $k ?>]" rows="4"><?= $val ?></textarea><?php endif; ?></div>
    <?php endforeach; ?>
    <div class="actions"><button class="btn btn-primary" type="submit">Send reference</button></div>
  </form>
<?php endif; ?>
  <p class="muted small" style="text-align:center">Journey Church Missions</p>
</main>
<script src="/assets/app.js?v=4"></script>
</body>
</html>
