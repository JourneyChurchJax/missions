<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview();
page_open('Messages');
member_header('messages');
?>
<main class="main m">
  <h1 class="disp" style="margin:0;font-size:clamp(1.875rem,3vw,2.375rem)">Messages</h1>
  <div class="msgs">
    <aside class="convs">
      <label class="sr" for="ms">Search messages</label>
      <input id="ms" type="search" placeholder="Search messages" style="height:40px;border:0;border-radius:var(--r-sm);background:var(--sand);padding:0 14px;font:inherit;font-size:15px;margin-bottom:8px">
      <a class="conv on" href="#"><span class="av dark" style="width:44px;height:44px">BZ</span><div class="grow"><div style="display:flex;justify-content:space-between"><strong>Belize team</strong><span class="muted small">9:41 AM</span></div><div class="last">Corey: Bring your passport Sunday</div></div></a>
      <a class="conv" href="#"><span class="av" style="width:44px;height:44px">CR</span><div class="grow"><div style="display:flex;justify-content:space-between"><strong>Corey Rees</strong><span class="muted small">Mon</span></div><div class="last">Glad you're on the team</div></div></a>
      <a class="conv" href="#"><span class="av" style="width:44px;height:44px">JM</span><div class="grow"><div style="display:flex;justify-content:space-between"><strong>Journey Missions</strong><span class="muted small">Sep 12</span></div><div class="last">Welcome to the Belize trip</div></div></a>
    </aside>
    <section class="thread">
      <div style="padding:18px 24px;border-bottom:1px solid var(--sand);display:flex;justify-content:space-between;align-items:center;gap:12px">
        <div><strong style="font-size:18px">Belize team</strong><div class="muted small">Corey Rees, Thomas Sereno</div></div>
        <a href="/trip/schedule.php" style="font-size:15px">Next meeting: Nov 9 ›</a>
      </div>
      <div class="bubbles">
        <div class="muted small" style="text-align:center;font-weight:600">Today</div>
        <div class="them"><div class="small" style="font-weight:600;padding-bottom:2px">Corey</div>Bring your passport Sunday if you haven't uploaded it yet. We'll scan them after the meeting.</div>
        <div class="them">Also, the ministry schedule is updated in Documents.</div>
        <div class="mine">Got it. I'll have mine with me.</div>
      </div>
      <form class="composer" data-composer>
        <label class="sr" for="msg">Message</label>
        <input id="msg" type="text" placeholder="Message the Belize team" autocomplete="off">
        <button class="btn btn-primary" type="submit" style="height:52px">Send</button>
      </form>
    </section>
  </div>
</main>
<?php page_close(); ?>
