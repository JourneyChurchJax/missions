<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview('staff');
page_open('Applications');
admin_header('apps');
$sel = $applicants[0];
?>
<main class="main">
  <div class="head">
    <div class="sub"><h1 class="disp">Applications</h1><div class="muted">People apply online. Approve them and they land on the trip with their profile already filled in.</div></div>
    <a class="btn btn-primary" href="#" data-say="The form builder is coming next">New application form</a>
  </div>

  <section class="g3">
    <div class="tile xl">
      <div style="display:flex;justify-content:space-between;align-items:center"><strong style="font-size:18px">Belize 2027</strong><span class="pill pill-ok">Open</span></div>
      <div class="muted">Closes January 5, 2027 · Belize trip</div>
      <div style="display:flex;gap:28px"><div><div class="disp" style="font-size:32px">26</div><div class="muted small">Responses</div></div><div><div class="disp" style="font-size:32px">0</div><div class="muted small">To review</div></div></div>
      <div style="display:flex;gap:14px;font-size:14px"><a href="#" data-copy="https://missions.journeychurch.org/apply/belize-2027">Copy link</a><a href="#" data-say="The form builder is coming next">Edit</a><a href="#" data-say="Downloads are coming next">Download</a></div>
    </div>
    <div class="tile xl">
      <div style="display:flex;justify-content:space-between;align-items:center"><strong style="font-size:18px">Israel 2027</strong><span class="pill pill-ok">Open</span></div>
      <div class="muted">Closes [date] · Israel trip</div>
      <div style="display:flex;gap:28px"><div><div class="disp" style="font-size:32px">18</div><div class="muted small">Responses</div></div><div><div class="disp" style="font-size:32px"><?= count($applicants) ?></div><div class="muted small">To review</div></div></div>
      <div style="display:flex;gap:14px;font-size:14px"><a href="#" data-copy="https://missions.journeychurch.org/apply/israel-2027">Copy link</a><a href="#" data-say="The form builder is coming next">Edit</a><a href="#" data-say="Downloads are coming next">Download</a></div>
    </div>
    <a class="trip" href="#" data-say="Duplicating forms is coming next"><div class="new" style="aspect-ratio:auto;height:100%;min-height:180px"><span class="disp" style="font-size:24px;font-weight:700;letter-spacing:-.02em">Start from a copy</span><span class="muted">Duplicate last year's form and change the dates</span></div></a>
  </section>

  <div class="center"><div class="seg" data-choice aria-label="Status">
    <button type="button" class="tab on">To review · <?= count($applicants) ?></button><button type="button" class="tab">Started, not sent · 3</button><button type="button" class="tab">Approved · 40</button><button type="button" class="tab">Not this time · 2</button>
  </div></div>

  <div class="split left-rail">
    <section class="group">
    <?php foreach ($applicants as $i => $a): ?>
      <a class="cell" href="#"<?= $i === 0 ? ' style="background:var(--tint)"' : '' ?>><span class="av<?= $i === 0 ? ' dark' : '' ?>"><?= initials($a['name']) ?></span><div class="grow"><strong><?= e($a['name']) ?></strong><div class="muted small"><?= e($a['note']) ?></div></div><span class="muted small"><?= e($a['ago']) ?></span></a>
    <?php endforeach; ?>
    </section>

    <section class="tile xl" style="padding:28px;gap:20px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
        <div style="display:flex;gap:14px;align-items:center"><span class="av dark lg"><?= initials($sel['name']) ?></span><div><div style="font-weight:700;font-size:22px"><?= e($sel['name']) ?></div><div class="muted">Applied September 29 · in Planning Center</div></div></div>
        <div style="display:flex;gap:10px"><a class="btn" href="#" data-say="Marked not this time (preview only)">Not this time</a><a class="btn btn-primary" href="#" data-say="Approved for Israel. Welcome email sent (preview only)">Approve for Israel</a></div>
      </div>
      <div class="chips" style="gap:8px"><span class="pill">First choice: Israel</span><span class="pill pill-ok">2 of 2 references in</span><span class="pill pill-ok">Deposit paid</span><span class="pill">Passport on file</span></div>
      <div>
      <?php foreach ([['Tell us how you came to know Jesus', "[Applicant's answer]"], ['Why do you want to go on this trip?', "[Applicant's answer]"], ['Have you been on a mission trip before?', '[Answer]']] as [$qq, $ans]): ?>
        <div style="display:flex;flex-direction:column;gap:4px;padding:14px 0;box-shadow:inset 0 -1px 0 var(--sand)"><div class="muted small" style="font-weight:600"><?= e($qq) ?></div><div><?= e($ans) ?></div></div>
      <?php endforeach; ?>
        <div style="display:flex;flex-direction:column;gap:4px;padding:14px 0"><div class="muted small" style="font-weight:600">References</div><div>Pastor reference · received Sep 30<br>Friend reference · received Oct 1</div></div>
      </div>
      <div class="muted small">Approving adds them to the trip, sends the welcome email and starts their before-you-go checklist.</div>
    </section>
  </div>
</main>
<?php page_close(); ?>
