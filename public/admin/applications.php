<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview('staff');
$forms = all('SELECT * FROM app_forms ORDER BY created_at DESC, id DESC');
$fid = (int)($_GET['f'] ?? 0);
$status = array_key_exists($_GET['s'] ?? '', APP_STATUS) ? $_GET['s'] : 'submitted';

// CSV of every response on a form
if (isset($_GET['csv']) && ($f = app_form((int)$_GET['csv']))) {
    $qs = app_questions((int)$f['id']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $f['slug'] . '-responses.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_merge(['First name', 'Last name', 'Email', 'Phone', 'Status', 'Submitted', 'Trip choice', 'Deposit', 'References'], array_column($qs, 'label')), ',', '"', '\\');
    foreach (all('SELECT a.*, p.first_name, p.last_name, p.email, p.phone FROM applications a JOIN people p ON p.id = a.person_id WHERE a.form_id = ? AND a.status <> \'draft\' ORDER BY a.submitted_at', [$f['id']]) as $a) {
        $ans = app_answers($a); $trip = $a['choice1'] ? trip((int)$a['choice1']) : null;
        fputcsv($out, array_merge([$a['first_name'], $a['last_name'], $a['email'], $a['phone'], APP_STATUS[$a['status']] ?? $a['status'], $a['submitted_at'], $trip['name'] ?? '', DEPOSIT_STATUS[$a['deposit_status']] ?? '', app_ref_summary($a, $f)],
            array_map(fn($q) => is_array($ans[$q['id']] ?? null) ? implode('; ', $ans[$q['id']]) : ($ans[$q['id']] ?? ''), $qs)), ',', '"', '\\');
    }
    exit;
}

$where = 'a.status = ?'; $params = [$status];
if ($fid) { $where .= ' AND a.form_id = ?'; $params[] = $fid; }
$list = all("SELECT a.*, p.first_name, p.preferred_name, p.last_name, p.email, f.name AS form_name FROM applications a JOIN people p ON p.id = a.person_id JOIN app_forms f ON f.id = a.form_id WHERE $where ORDER BY COALESCE(a.submitted_at, a.created_at) DESC", $params);
$counts = [];
foreach (APP_STATUS as $k => $l) $counts[$k] = (int)val('SELECT COUNT(*) FROM applications WHERE status = ?' . ($fid ? ' AND form_id = ?' : ''), $fid ? [$k, $fid] : [$k]);
$sel = isset($_GET['a']) ? application((int)$_GET['a']) : ($list ? application((int)$list[0]['id']) : null);
$base = '/admin/applications.php?' . ($fid ? "f=$fid&" : '');
page_open('Applications');
admin_header('apps');
?>
<main class="main">
  <div class="head">
    <div class="sub"><h1 class="disp">Applications</h1><div class="muted">People apply online. Approve them and they land on the trip with their profile already filled in.</div></div>
    <a class="btn btn-primary" href="/admin/app-form.php">New application form</a>
  </div>

  <section class="g3">
  <?php foreach ($forms as $f): $resp = (int)val("SELECT COUNT(*) FROM applications WHERE form_id = ? AND status <> 'draft'", [$f['id']]); $rev = (int)val("SELECT COUNT(*) FROM applications WHERE form_id = ? AND status = 'submitted'", [$f['id']]); ?>
    <div class="tile xl"<?= $fid === (int)$f['id'] ? ' style="box-shadow:0 0 0 2px var(--ink),var(--sh-md)"' : '' ?>>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><a href="/admin/applications.php?f=<?= (int)$f['id'] ?>" style="text-decoration:none;font-weight:700;font-size:18px"><?= e($f['name']) ?></a><span class="pill<?= form_is_open($f) ? ' pill-ok' : '' ?>"><?= form_is_open($f) ? 'Open' : ($f['published'] ? 'Closed' : 'Not published') ?></span></div>
      <div class="muted"><?= $f['closes_on'] ? 'Closes ' . fdate($f['closes_on'], 'F j, Y') : 'No closing date' ?><?= (float)$f['deposit'] > 0 ? ' · ' . money((float)$f['deposit']) . ' deposit' : '' ?></div>
      <div style="display:flex;gap:28px"><div><div class="disp" style="font-size:32px"><?= $resp ?></div><div class="muted small">Responses</div></div><div><div class="disp" style="font-size:32px"><?= $rev ?></div><div class="muted small">To review</div></div></div>
      <div style="display:flex;gap:14px;font-size:14px;flex-wrap:wrap"><a href="#" data-copy="<?= e(app_url($f)) ?>">Copy link</a><a href="/apply/?f=<?= e($f['slug']) ?>" target="_blank">Preview</a><a href="/admin/app-form.php?id=<?= (int)$f['id'] ?>">Edit</a><a href="/admin/applications.php?csv=<?= (int)$f['id'] ?>">Download</a></div>
    </div>
  <?php endforeach; ?>
    <a class="trip" href="/admin/app-form.php"><div class="new" style="aspect-ratio:auto;height:100%;min-height:180px"><span class="disp" style="font-size:24px;font-weight:700;letter-spacing:-.02em">New form</span><span class="muted">Starts with the standard questions</span></div></a>
  </section>

  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <nav class="seg" aria-label="Status">
      <?php foreach (APP_STATUS as $k => $l): ?><a class="tab<?= $status === $k ? ' on' : '' ?>" href="<?= $base ?>s=<?= $k ?>"><?= e($l) ?> · <?= $counts[$k] ?></a><?php endforeach; ?>
    </nav>
    <?php if ($fid): ?><a class="small" href="/admin/applications.php?s=<?= $status ?>">Show every form</a><?php endif; ?>
  </div>

  <div class="split left-rail">
    <section class="group">
    <?php foreach ($list as $a): $f = app_form((int)$a['form_id']); $trip = $a['choice1'] ? trip((int)$a['choice1']) : null; $on = $sel && (int)$sel['id'] === (int)$a['id']; ?>
      <a class="cell" href="<?= $base ?>s=<?= $status ?>&a=<?= (int)$a['id'] ?>"<?= $on ? ' style="background:var(--tint)"' : '' ?>><span class="av<?= $on ? ' dark' : '' ?>"><?= initials(full_name($a)) ?></span>
        <div class="grow"><strong><?= e(full_name($a)) ?></strong><div class="muted small"><?= e($trip['name'] ?? $a['form_name']) ?><?= ($r = app_ref_summary($a, $f)) ? ' · ' . e($r) : '' ?><?= $a['deposit_status'] === 'paid' ? ' · deposit paid' : '' ?></div></div>
        <span class="muted small"><?= fdate($a['submitted_at'] ?: $a['created_at'], 'M j') ?></span></a>
    <?php endforeach; ?>
    <?= $list ? '' : '<div class="empty">Nothing here.</div>' ?>
    </section>

    <?php if ($sel): $p = person((int)$sel['person_id']); $f = app_form((int)$sel['form_id']); $qs = app_questions((int)$f['id']); $ans = app_answers($sel); $refs = app_refs((int)$sel['id']); $choices = array_filter([$sel['choice1'], $sel['choice2'], $sel['choice3']]); ?>
    <section class="tile xl" style="padding:28px;gap:20px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
        <div style="display:flex;gap:14px;align-items:center"><span class="av dark lg"><?= initials(full_name($p)) ?></span>
          <div><div style="font-weight:700;font-size:22px"><?= e(full_name($p)) ?></div><div class="muted small"><?= e($f['name']) ?> · <?= $sel['submitted_at'] ? 'applied ' . fdate($sel['submitted_at'], 'F j') : 'started ' . fdate($sel['created_at'], 'F j') . ', not sent yet' ?><?= $p['email'] ? ' · ' . e($p['email']) : '' ?><?= $p['phone'] ? ' · ' . e($p['phone']) : '' ?></div>
          <a class="small" href="/admin/person.php?id=<?= (int)$p['id'] ?>">Full profile</a></div></div>
        <span class="pill<?= $sel['status'] === 'approved' ? ' pill-ok' : '' ?>"><?= e(APP_STATUS[$sel['status']]) ?></span>
      </div>

      <div class="chips" style="gap:8px">
        <?php foreach (array_values($choices) as $i => $c): ?><span class="pill"><?= count($choices) > 1 ? 'Choice ' . ($i + 1) . ': ' : 'Trip: ' ?><?= e(trip((int)$c)['name'] ?? '') ?></span><?php endforeach; ?>
        <?php if ($r = app_ref_summary($sel, $f)): ?><span class="pill<?= str_starts_with($r, $f['refs_required'] . ' of') ? ' pill-ok' : '' ?>"><?= e($r) ?></span><?php endif; ?>
        <?php if ((float)$sel['deposit_due'] > 0 || $sel['deposit_status'] !== 'none'): ?><span class="pill<?= in_array($sel['deposit_status'], ['paid', 'waived'], true) ? ' pill-ok' : '' ?>"><?= e(DEPOSIT_STATUS[$sel['deposit_status']] ?? '') ?> · <?= money((float)$sel['deposit_due']) ?><?= $sel['discount_code'] ? ' (' . e($sel['discount_code']) . ')' : '' ?></span><?php endif; ?>
        <span class="pill<?= $p['passport_expires'] ? ' pill-ok' : '' ?>"><?= $p['passport_expires'] ? 'Passport on file' : 'No passport yet' ?></span>
        <?php if ($p['birth_date'] && is_minor($p, date('Y-m-d'))): ?><span class="pill">Under 18</span><?php endif; ?>
      </div>

      <?php if ($sel['status'] !== 'draft'): ?>
      <form class="form" method="post" action="/action.php" style="background:var(--cream);border-radius:var(--r-md);padding:16px">
        <?= csrf() ?><input type="hidden" name="action" value="app_decide"><input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
        <div class="r2"><label class="lab">Approve for<select name="trip_id"><?php foreach (array_unique(array_merge(array_values($choices), array_column(form_trips($f), 'id'))) as $c): $tt = trip((int)$c); if (!$tt) continue; ?><option value="<?= (int)$c ?>"<?= (int)$c === (int)($sel['assigned_trip_id'] ?: $sel['choice1']) ? ' selected' : '' ?>><?= e($tt['name']) ?></option><?php endforeach; ?></select></label>
          <label class="lab">Note for the team (optional)<input type="text" name="note" value="<?= e($sel['decision_note']) ?>"></label></div>
        <div class="actions">
          <button class="btn" name="decision" value="declined" type="submit">Not this time</button>
          <button class="btn" name="decision" value="waitlist" type="submit">Waitlist</button>
          <button class="btn btn-primary" name="decision" value="approved" type="submit"><?= $sel['status'] === 'approved' ? 'Approved' : 'Approve' ?></button>
        </div>
        <?php if ($sel['decided_by']): ?><div class="muted small"><?= e(APP_STATUS[$sel['status']]) ?> by <?= e($sel['decided_by']) ?> on <?= fdate($sel['decided_at'], 'M j') ?></div><?php endif; ?>
      </form>
      <?php else: ?><div class="note"><strong>Still in progress</strong><div class="muted small">They've started but haven't sent it. Their resume link: <a href="#" data-copy="https://missions.journeychurch.org/apply/?t=<?= e($sel['token']) ?>">copy</a></div></div><?php endif; ?>

      <div>
      <?php foreach ($qs as $q): $v = $ans[$q['id']] ?? null; ?>
        <div style="display:flex;flex-direction:column;gap:4px;padding:14px 0;box-shadow:inset 0 -1px 0 var(--sand)"><div class="muted small" style="font-weight:600"><?= e($q['label']) ?></div>
          <div><?= $v === null || $v === '' || $v === [] ? '<span class="muted">No answer</span>' : (is_array($v) ? e(implode(', ', $v)) : nl2br(e((string)$v))) ?></div></div>
      <?php endforeach; ?>
      </div>

      <?php if ($refs || (int)$f['refs_required']): ?>
      <section><div class="gh" style="padding-left:0">References</div><div class="group" style="box-shadow:none;background:var(--cream)">
        <?php foreach ($refs as $r): $ra = json_decode((string)$r['answers'], true) ?: []; ?>
          <div class="cell" style="flex-wrap:wrap"><div class="grow"><strong><?= e($r['name']) ?></strong> <span class="muted small">· <?= e($r['ref_type']) ?> · <?= e($r['email']) ?></span>
            <div class="muted small"><?= $r['status'] === 'received' ? 'Received ' . fdate($r['received_at'], 'M j') : 'Requested ' . fdate($r['requested_at'], 'M j') . ', not back yet' ?></div></div>
            <?php if ($r['status'] === 'received'): ?>
              <details class="edit"><summary>Read</summary><div style="padding-top:8px;display:flex;flex-direction:column;gap:10px"><?php foreach (REF_QUESTIONS as $k => [$label]): if (empty($ra[$k])) continue; ?><div><div class="muted small" style="font-weight:600"><?= e($label) ?></div><div><?= nl2br(e($ra[$k])) ?></div></div><?php endforeach; ?></div></details>
            <?php else: ?>
              <a class="small" href="mailto:<?= e($r['email']) ?>?subject=<?= rawurlencode('Reference for ' . full_name($p)) ?>&body=<?= rawurlencode(full_name($p) . ' listed you as a reference for ' . $f['name'] . ". It takes about 5 minutes:\n" . ref_url($r)) ?>">Email again</a>
              <a class="small" href="#" data-copy="<?= e(ref_url($r)) ?>">Copy link</a>
              <form method="post" action="/action.php" class="inline"><?= csrf() ?><input type="hidden" name="action" value="ref_received"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="link-btn">Got it another way</button></form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?= $refs ? '' : '<div class="empty">No references listed yet.</div>' ?>
      </div></section>
      <?php endif; ?>

      <?php if ($sel['deposit_status'] !== 'none'): ?>
      <form method="post" action="/action.php" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><?= csrf() ?><input type="hidden" name="action" value="app_deposit"><input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
        <span class="muted small">Deposit: <?= money((float)$sel['deposit_due']) ?> · online payment arrives with Stripe in phase 5</span>
        <span style="display:flex;gap:8px"><button class="btn" name="status" value="paid" style="height:38px">Mark paid</button><button class="btn" name="status" value="waived" style="height:38px">Waive</button><button class="btn" name="status" value="due" style="height:38px">Due</button></span></form>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>
</main>
<?php page_close(); ?>
