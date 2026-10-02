<?php
// Public reference form. The ?t= token in the link is the only key.
define('REAL_DB', true);
define('ACTOR', 'Reference');
require dirname(__DIR__) . '/inc/bootstrap.php';

$r = g('t') !== '' ? one('SELECT * FROM app_refs WHERE token = ?', [g('t')]) : null;
$app = $r ? application((int)$r['application_id']) : null;
$p = $app ? person((int)$app['person_id']) : null;
$f = $app ? app_form((int)$app['form_id']) : null;
$err = '';

if ($r && $_SERVER['REQUEST_METHOD'] === 'POST' && $r['status'] !== 'received') {
    check_csrf();
    $ans = [];
    foreach (REF_QUESTIONS as $k => [$label, $kind]) { $v = $_POST['a'][$k] ?? ''; $ans[$k] = is_string($v) ? mb_substr(trim($v), 0, 5000) : ''; }
    $ans['relationship'] = $r['ref_type'];
    if (rate_limited(client_key() . ':ref', 10, 3600)) $err = 'Too many tries. Please wait a bit and try again.';
    elseif ($ans['known'] === '' || $ans['recommend'] === '') $err = 'Please answer how you know them and whether you recommend them.';
    else {
        update('app_refs', (int)$r['id'], ['status' => 'received', 'received_at' => now(), 'answers' => json_encode($ans)]);
        header('Location: /reference/?t=' . $r['token']); exit;
    }
}

$name = $p ? ($p['preferred_name'] ?: $p['first_name']) . ' ' . $p['last_name'] : '';
public_open('Reference');
?>
<main class="pub-main" id="main">
<?php if (!$r || !$p): ?>
  <section class="tile xl" style="padding:36px;text-align:center;gap:10px"><h1 class="disp">We couldn't find that reference.</h1><p class="muted" style="margin:0">Check the link in your email, or ask the person who sent it.</p></section>
<?php elseif ($r['status'] === 'received'): ?>
  <section class="tile xl" style="padding:36px;gap:12px"><h1 class="disp">Thank you, <?= e(strtok($r['name'], ' ')) ?>.</h1><p style="margin:0;font-size:17px;line-height:1.55">Your reference for <?= e($name) ?> is in. Only the missions team will read it.</p></section>
<?php else: ?>
  <section style="display:flex;flex-direction:column;gap:10px">
    <div class="muted small">Reference · <?= e($f['name']) ?></div>
    <h1 class="disp"><?= e($name) ?> asked you for a reference.</h1>
    <p style="margin:0;font-size:17px;line-height:1.55">They're applying for a <?= e(church_name()) ?> mission trip and listed you as their <?= e(strtolower($r['ref_type'])) ?> reference. This takes about five minutes. Only the missions team will read your answers.</p>
  </section>
  <form class="form tile xl" method="post" style="padding:28px;gap:18px">
    <?= csrf() ?>
    <?php if ($err): ?><div class="note error" role="alert"><?= e($err) ?></div><?php endif; ?>
    <?php foreach (REF_QUESTIONS as $k => [$label, $kind]): $raw = is_string($_POST['a'][$k] ?? null) ? $_POST['a'][$k] : ''; $req = in_array($k, ['known', 'recommend'], true); ?>
      <?php if (str_starts_with($kind, 'choice:')): ?>
      <fieldset class="lab"><legend><?= e($label) ?><?= $req ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></legend><div class="opts"><?php foreach (explode('|', substr($kind, 7)) as $o): ?><label><input type="radio" name="a[<?= $k ?>]" value="<?= e($o) ?>"<?= $raw === $o ? ' checked' : '' ?><?= $req ? ' required' : '' ?>><?= e($o) ?></label><?php endforeach; ?></div></fieldset>
      <?php else: ?>
      <label class="lab" for="ref-<?= $k ?>"><span><?= e($label) ?><?= $req ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></span><textarea id="ref-<?= $k ?>" name="a[<?= $k ?>]" rows="4" maxlength="5000"<?= $req ? ' required' : '' ?>><?= e($raw) ?></textarea></label>
      <?php endif; ?>
    <?php endforeach; ?>
    <div class="actions"><button class="btn btn-primary" type="submit">Send reference</button></div>
  </form>
<?php endif; ?>
  <p class="muted small center"><?= e(church_name()) ?> Missions</p>
</main>
<?php public_close(); ?>
