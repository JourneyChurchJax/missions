<?php
require __DIR__ . '/inc/bootstrap.php';
require_preview();
page_open('Choose a view');
?>
<main class="gate">
  <div class="card" style="max-width:560px">
    <?= logo(280, false, '/') ?>
    <h1 class="disp" style="margin:8px 0 0;font-size:44px">Pick a <em style="font-weight:700;letter-spacing:-.03em">view</em>.</h1>
    <p class="muted" style="margin:0">See the app the way staff see it, or the way a traveler sees it.</p>
    <a class="cell group" href="/admin/" style="min-height:76px"><span class="av dark">AH</span><span class="grow"><strong style="display:block">Staff</strong><span class="muted small">Trips, people, applications, giving, reports, settings</span></span><span class="chev">›</span></a>
    <a class="cell group" href="/trip/" style="min-height:76px"><span class="av">TS</span><span class="grow"><strong style="display:block">Traveler</strong><span class="muted small">Thomas's Belize trip: schedule, documents, fundraising, messages</span></span><span class="chev">›</span></a>
  </div>
</main>
<?php page_close(); ?>
