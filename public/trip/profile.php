<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/member.php';
$p = $me; $staff = false; $back = '/trip/profile.php';
page_open('My profile');
member_header('');
?>
<main class="main m" style="max-width:900px">
  <div class="head"><div class="sub"><h1 class="disp">My profile</h1><div class="muted">Your leaders use this for flights, emergencies and meals. Only you and your trip leaders can see it.</div></div></div>
  <?php include dirname(__DIR__) . '/inc/_person_form.php'; ?>
</main>
<?php page_close(); ?>
