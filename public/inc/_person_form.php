<?php
// Full profile form. Expects $p (person array or []), $staff (bool), $back (url).
$v = fn($k) => e($p[$k] ?? '');
$rels = ['', 'Parent/Guardian', 'Spouse', 'Sibling', 'Friend', 'Child', 'Other'];
?>
<form class="form" method="post" action="/action.php" style="gap:24px">
  <?= csrf() ?><input type="hidden" name="action" value="person_save"><input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
  <section class="tile xl form"><strong style="font-size:17px">Basics</strong>
    <div class="r3"><label class="lab">First name<input type="text" name="first_name" value="<?= $v('first_name') ?>" required></label><label class="lab">Goes by<input type="text" name="preferred_name" value="<?= $v('preferred_name') ?>"></label><label class="lab">Last name<input type="text" name="last_name" value="<?= $v('last_name') ?>" required></label></div>
    <div class="r3"><label class="lab">Email<input type="email" name="email" value="<?= $v('email') ?>"></label><label class="lab">Mobile<input type="tel" name="phone" value="<?= $v('phone') ?>"></label><label class="lab">Birth date<input type="date" name="birth_date" value="<?= $v('birth_date') ?>"></label></div>
    <div class="r3"><label class="lab">Gender<select name="gender"><?php foreach (['' => 'Choose', 'female' => 'Female', 'male' => 'Male'] as $k => $l): ?><option value="<?= $k ?>"<?= ($p['gender'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label><label class="lab">T-shirt<input type="text" name="shirt" value="<?= $v('shirt') ?>" placeholder="Men's L"></label><label class="lab">Address<input type="text" name="address" value="<?= $v('address') ?>"></label></div>
    <div class="r3"><label class="lab">City<input type="text" name="city" value="<?= $v('city') ?>"></label><label class="lab">State<input type="text" name="state" value="<?= $v('state') ?>"></label><label class="lab">ZIP<input type="text" name="zip" value="<?= $v('zip') ?>"></label></div>
  </section>
  <section class="tile xl form"><strong style="font-size:17px">Passport</strong>
    <div class="r2"><label class="lab">Name exactly as on passport<input type="text" name="passport_name" value="<?= $v('passport_name') ?>"></label><label class="lab">Passport number<input type="text" name="passport_number" value="<?= $v('passport_number') ?>" autocomplete="off"></label></div>
    <div class="r3"><label class="lab">Issued<input type="date" name="passport_issued" value="<?= $v('passport_issued') ?>"></label><label class="lab">Expires<input type="date" name="passport_expires" value="<?= $v('passport_expires') ?>"></label><label class="lab">Issuing country<input type="text" name="passport_country" value="<?= $v('passport_country') ?>" placeholder="United States"></label></div>
  </section>
  <section class="tile xl form"><strong style="font-size:17px">Emergency contacts</strong>
    <div class="r3"><label class="lab">Name<input type="text" name="ec1_name" value="<?= $v('ec1_name') ?>"></label><label class="lab">Relationship<select name="ec1_rel"><?php foreach ($rels as $r): ?><option<?= ($p['ec1_rel'] ?? '') === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></label><label class="lab">Phone<input type="tel" name="ec1_phone" value="<?= $v('ec1_phone') ?>"></label></div>
    <div class="r3"><label class="lab">Second contact<input type="text" name="ec2_name" value="<?= $v('ec2_name') ?>"></label><label class="lab">Relationship<select name="ec2_rel"><?php foreach ($rels as $r): ?><option<?= ($p['ec2_rel'] ?? '') === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></label><label class="lab">Phone<input type="tel" name="ec2_phone" value="<?= $v('ec2_phone') ?>"></label></div>
  </section>
  <section class="tile xl form"><strong style="font-size:17px">Health</strong><div class="muted small">Only you, trip leaders and admins can see this.</div>
    <div class="r2"><label class="lab">Allergies<textarea name="allergies" rows="2"><?= $v('allergies') ?></textarea></label><label class="lab">Medications<textarea name="meds" rows="2"><?= $v('meds') ?></textarea></label></div>
    <div class="r2"><label class="lab">Dietary needs<textarea name="diet" rows="2"><?= $v('diet') ?></textarea></label><label class="lab">Health concerns<textarea name="health" rows="2"><?= $v('health') ?></textarea></label></div>
    <label class="lab">Anything else we should know<textarea name="other" rows="2"><?= $v('other') ?></textarea></label>
  </section>
  <?php if ($staff): ?>
  <section class="tile xl form"><strong style="font-size:17px">Staff notes</strong>
    <div class="r2"><label class="lab">Tags<input type="text" name="tags" value="<?= $v('tags') ?>" placeholder="Student, Medical team"></label><label class="lab">Planning Center ID<input type="text" name="pco_id" value="<?= $v('pco_id') ?>"></label></div>
    <label class="lab">Notes<textarea name="notes" rows="3"><?= $v('notes') ?></textarea></label>
  </section>
  <?php endif; ?>
  <div class="actions"><button class="btn btn-primary" type="submit">Save profile</button></div>
</form>
