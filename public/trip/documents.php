<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
$t = $trips[$me['trip']];
page_open('Documents');
member_header('docs');
?>
<main class="main m">
  <div class="head">
    <div class="sub"><div class="muted" style="font-size:15px;font-weight:600"><?= e($t['name']) ?> · <?= e($t['dates']) ?></div><h1 class="disp">Documents</h1></div>
    <div class="seg sm" data-choice aria-label="Filter"><button type="button" class="tab on">All</button><button type="button" class="tab">Needs you · 2</button><button type="button" class="tab">Guides</button><button type="button" class="tab">My uploads</button></div>
  </div>

  <div class="split" style="grid-template-columns:minmax(0,1fr) 360px;gap:40px">
    <div style="display:flex;flex-direction:column;gap:28px">
      <section><div class="gh">Needs you</div><div class="group">
        <div class="cell" style="min-height:68px"><span class="doc">DOC</span><div class="grow"><strong>Missions code of conduct</strong><div class="muted small">Read and sign · due December 1</div></div><a class="btn btn-primary" style="height:40px;padding:0 20px;font-size:15px" href="#" data-say="E-signature is coming next">Sign</a></div>
        <div class="cell" style="min-height:68px"><span class="doc">ID</span><div class="grow"><strong>Passport copy</strong><div class="muted small">A clear photo of the photo page · due November 15</div></div><a class="pill" style="height:32px;padding:0 14px" href="#upload">Upload</a></div>
      </div></section>

      <section><div class="gh">Trip documents</div><div class="group">
      <?php foreach ($documents as [$name, $type, $sub]): ?>
        <a class="cell" style="min-height:68px" href="#" data-say="Files open once uploads are connected"><span class="doc"><?= e($type) ?></span><div class="grow"><strong><?= e($name) ?></strong><div class="muted small"><?= e($sub) ?></div></div><span class="chev">›</span></a>
      <?php endforeach; ?>
        <div class="cell" style="min-height:68px"><span class="doc">DOC</span><div class="grow"><strong>Missions trip liability waiver</strong><div class="muted small">You signed this September 12</div></div><span class="pill pill-ok">Signed</span></div>
      </div></section>

      <section><div class="gh">Guides</div><div class="group">
      <?php foreach ($guides as [$name, $sub]): ?>
        <a class="cell" style="min-height:64px" href="#" data-say="Guide links open once they're added"><span class="doc">LINK</span><div class="grow"><strong><?= e($name) ?></strong><?php if ($sub): ?><div class="muted small"><?= e($sub) ?></div><?php endif; ?></div><span class="chev">›</span></a>
      <?php endforeach; ?>
      </div></section>
    </div>

    <aside class="sticky" style="top:96px;display:flex;flex-direction:column;gap:20px" id="upload">
      <section class="panel" style="gap:14px">
        <strong style="font-size:18px">Upload a document</strong>
        <label class="drop"><strong>Drop a file here</strong><span class="muted small">or choose one · photo or PDF up to 20 MB</span><input type="file" accept="image/*,application/pdf" style="font-size:14px;margin-top:8px" onchange="document.querySelector('.toast').textContent='Uploads save once storage is connected';document.querySelector('.toast').classList.add('show');setTimeout(()=>document.querySelector('.toast').classList.remove('show'),2200)"></label>
        <div class="muted small">Only you and your trip leaders can see what you upload.</div>
      </section>
      <section class="note"><strong>2 of 4 done</strong><?= bar(50) ?><div class="muted">Sign the code of conduct and upload your passport to finish this part.</div></section>
    </aside>
  </div>
</main>
<?php page_close(); ?>
