<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_staff();
$f = gi('id') ? app_form(gi('id')) : null;
$trips = all("SELECT * FROM trips WHERE status = 'active' AND end_date >= ? ORDER BY start_date", [date('Y-m-d')]);
$picked = $f ? array_map('intval', array_filter(explode(',', (string)$f['trip_ids']))) : [];
$locked = $f && form_has_responses((int)$f['id']);
$v = fn($k) => e($f[$k] ?? '');
page_open($f ? $f['name'] : 'New application form');
admin_header('apps');
?>
<main class="main" style="max-width:920px">
  <div class="bar-head"><div><a class="muted small" href="/admin/applications.php<?= $f ? '?f=' . (int)$f['id'] : '' ?>" style="text-decoration:none">‹ Applications</a><h1><?= $f ? e($f['name']) : 'New application form' ?></h1></div>
  <?php if ($f): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn" href="/apply/?f=<?= e($f['slug']) ?>" target="_blank">Preview</a>
      <button class="btn" type="button" data-copy="<?= e(app_url($f)) ?>">Copy link</button>
      <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="form_duplicate"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn" type="submit">Duplicate</button></form>
      <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="form_publish"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn <?= $f['published'] ? 'btn-dark' : 'btn-primary' ?>" type="submit"><?= $f['published'] ? 'Unpublish' : 'Publish' ?></button></form>
    </div>
  <?php endif; ?></div>

  <?php if ($f): ?><div class="note"><strong><?= form_is_open($f) ? 'Open. Anyone with the link can apply.' : ($f['published'] ? 'Published, but the closing date has passed.' : 'Not published. Only staff can see it.') ?></strong><div class="muted small"><?= e(app_url($f)) ?></div></div><?php endif; ?>

  <form class="form tile xl" method="post" action="/action.php" style="padding:28px;gap:18px">
    <?= csrf() ?><input type="hidden" name="action" value="form_save"><input type="hidden" name="id" value="<?= (int)($f['id'] ?? 0) ?>">
    <strong style="font-size:18px">Settings</strong>
    <div class="r2"><label class="lab">Form name<input type="text" name="name" value="<?= $v('name') ?>" placeholder="Belize 2027" required></label><label class="lab">Applications close<input type="date" name="closes_on" value="<?= $v('closes_on') ?>"></label></div>
    <label class="lab">Welcome text at the top<textarea name="intro" rows="3" placeholder="What the trip is, who should apply, and what happens next."><?= $v('intro') ?></textarea></label>
    <div class="r2">
      <label class="lab">Which trips can they pick?<select name="trip_mode">
        <?php foreach (['specific' => 'Only the trips I check below', 'all' => 'Any upcoming trip', 'none' => "Don't ask (general interest)"] as $k => $l): ?><option value="<?= $k ?>"<?= ($f['trip_mode'] ?? 'specific') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label class="lab">Trip choices<select name="choices"><option value="1">One trip</option><option value="3"<?= (int)($f['choices'] ?? 1) === 3 ? ' selected' : '' ?>>First, second and third choice</option></select></label>
    </div>
    <div class="opts" style="flex-direction:row;flex-wrap:wrap"><?php foreach ($trips as $t): ?><label style="flex:1;min-width:200px"><input type="checkbox" name="trip_ids[]" value="<?= (int)$t['id'] ?>"<?= in_array((int)$t['id'], $picked, true) ? ' checked' : '' ?>> <?= e($t['name']) ?> · <?= fdate($t['start_date'], 'M Y') ?></label><?php endforeach; ?><?= $trips ? '' : '<span class="muted small">No upcoming trips yet.</span>' ?></div>
    <div class="r3">
      <label class="lab">References needed<select name="refs_required"><?php for ($n = 0; $n <= 3; $n++): ?><option<?= (int)($f['refs_required'] ?? 0) === $n ? ' selected' : '' ?>><?= $n ?></option><?php endfor; ?></select></label>
      <label class="lab">Deposit<input type="number" step="0.01" min="0" name="deposit" value="<?= $v('deposit') ?>" placeholder="0"></label>
      <div class="muted small" style="align-self:end;padding-bottom:12px">A deposit toward someone's own trip isn't a tax-deductible gift.</div>
    </div>
    <label class="lab">Reference types, one per line<textarea name="ref_types" rows="2" placeholder="Pastor or small group leader&#10;Employer, teacher or coach"><?= $v('ref_types') ?></textarea></label>
    <label class="lab">Thank-you message after they apply<textarea name="submitted_message" rows="2" placeholder="We got your application. We'll be in touch within two weeks."><?= $v('submitted_message') ?></textarea></label>
    <div class="actions"><button class="btn btn-primary" type="submit"><?= $f ? 'Save settings' : 'Create form' ?></button></div>
  </form>

  <?php if ($f): $qs = app_questions((int)$f['id']); ?>
  <section class="tile xl" style="gap:14px">
    <div><strong style="font-size:18px">Questions</strong><div class="muted small">Everyone also answers name, contact, birth date, passport, emergency contact and health. These are your extra questions.</div></div>
    <?php if ($locked): ?><div class="note"><strong>Questions are locked</strong><div class="muted small">People have already applied, so changing questions would mix up answers. You can still fix wording. To add or remove questions, duplicate the form.</div></div><?php endif; ?>
    <div class="group" style="box-shadow:none;background:var(--cream)">
    <?php foreach ($qs as $n => $q): ?>
      <div class="cell" style="flex-wrap:wrap;align-items:flex-start">
        <span class="muted small" style="width:22px;padding-top:2px"><?= $n + 1 ?></span>
        <div class="grow"><strong><?= e($q['label']) ?></strong><?= $q['required'] ? ' <span class="req">*</span>' : '' ?><div class="muted small"><?= e(Q_KINDS[$q['kind']] ?? $q['kind']) ?><?= $q['options'] ? ' · ' . e(implode(', ', lines($q['options']))) : '' ?></div></div>
        <?php if (!$locked): ?>
          <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="q_move"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><button class="link-btn" name="dir" value="up" aria-label="Move up"<?= $n === 0 ? ' disabled' : '' ?>>↑</button><button class="link-btn" name="dir" value="down" aria-label="Move down"<?= $n === count($qs) - 1 ? ' disabled' : '' ?>>↓</button></form>
        <?php endif; ?>
        <details class="edit" style="width:100%"><summary>Edit</summary>
          <form class="form" method="post" action="/action.php" style="padding-top:10px"><?= csrf() ?><input type="hidden" name="action" value="q_save"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><input type="hidden" name="form_id" value="<?= (int)$f['id'] ?>">
            <?php $qq = $q; include dirname(__DIR__) . '/inc/_question_fields.php'; ?>
            <div class="actions"><?php if (!$locked): ?><button class="link-btn" type="submit" form="qd<?= (int)$q['id'] ?>">Remove question</button><?php endif; ?><button class="btn btn-dark" type="submit">Save</button></div>
          </form>
          <?php if (!$locked): ?><form id="qd<?= (int)$q['id'] ?>" method="post" action="/action.php" onsubmit="return confirm('Remove this question?')"><?= csrf() ?><input type="hidden" name="action" value="q_delete"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"></form><?php endif; ?>
        </details>
      </div>
    <?php endforeach; ?>
    <?= $qs ? '' : '<div class="empty">No extra questions yet.</div>' ?>
    </div>
    <?php if (!$locked): ?>
    <details class="add"><summary>Add a question</summary>
      <form class="form" method="post" action="/action.php" style="padding-top:12px"><?= csrf() ?><input type="hidden" name="action" value="q_save"><input type="hidden" name="form_id" value="<?= (int)$f['id'] ?>">
        <?php $qq = []; include dirname(__DIR__) . '/inc/_question_fields.php'; ?>
        <div class="actions"><button class="btn btn-dark" type="submit">Add question</button></div>
      </form></details>
    <?php endif; ?>
  </section>

  <?php if ((float)$f['deposit'] > 0): $ds = app_discounts((int)$f['id']); ?>
  <section class="tile xl" style="gap:14px">
    <div><strong style="font-size:18px">Deposit discounts</strong><div class="muted small">Early-bird discounts apply on their own until the date you set. Codes only apply when someone types them.</div></div>
    <div class="group" style="box-shadow:none;background:var(--cream)">
    <?php foreach ($ds as $d): $off = $d['kind'] === 'percent' ? rtrim(rtrim(number_format((float)$d['amount'], 2), '0'), '.') . '% off' : money((float)$d['amount']) . ' off'; ?>
      <div class="cell"><div class="grow"><strong><?= $d['early_bird'] ? 'Early bird' : e($d['code']) ?></strong><div class="muted small"><?= $off ?><?= $d['expires_on'] ? ' · until ' . fdate($d['expires_on'], 'M j, Y') : '' ?></div></div>
        <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="disc_delete"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="link-btn">Remove</button></form></div>
    <?php endforeach; ?>
    <?= $ds ? '' : '<div class="empty">No discounts.</div>' ?>
    </div>
    <details class="add"><summary>Add a discount</summary>
      <form class="form" method="post" action="/action.php" style="padding-top:12px"><?= csrf() ?><input type="hidden" name="action" value="disc_save"><input type="hidden" name="form_id" value="<?= (int)$f['id'] ?>">
        <div class="r3"><label class="lab">Code<input type="text" name="code" placeholder="FAMILY" style="text-transform:uppercase"></label>
          <label class="lab">Amount<input type="number" step="0.01" min="0" name="amount" required></label>
          <label class="lab">Type<select name="kind"><option value="amount">Dollars off</option><option value="percent">Percent off</option></select></label></div>
        <div class="r2"><label class="lab">Ends on<input type="date" name="expires_on"></label>
          <label class="lab">&nbsp;<span class="chk" style="display:flex;gap:8px;align-items:center;font-weight:400;color:var(--ink);height:44px"><input type="checkbox" name="early_bird" value="1"> Early bird (no code needed)</span></label></div>
        <div class="actions"><button class="btn btn-dark" type="submit">Add discount</button></div>
      </form></details>
  </section>
  <?php endif; ?>
  <?php endif; ?>
</main>
<?php page_close(); ?>
