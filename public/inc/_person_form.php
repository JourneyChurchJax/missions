<?php
// Full profile form. Expects $p (person array or []), $staff (bool), $back (url), optional $show_medical (bool) and $self (bool: the person editing themselves).
$v = fn($k) => e($p[$k] ?? '');
$rels = ['', 'Parent/Guardian', 'Spouse', 'Sibling', 'Friend', 'Child', 'Other'];
$show_medical = $show_medical ?? true;
$self = $self ?? false;
$locked = $self && !empty($p['id']) ? locked_person_fields((int)$p['id']) : [];
$lock_note = fn(string $k) => in_array($k, $locked, true) && !empty($p[$k]) ? ' <span class="muted small">(changes go to your leaders)</span>' : '';
?>
<form class="form stack-24" method="post" action="/action.php">
  <?= csrf() ?><input type="hidden" name="action" value="person_save"><input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
  <section class="tile xl form"><h2 class="card-title">Basics</h2>
    <div class="r3"><label class="lab"><span>First name<?= $lock_note('first_name') ?></span><input type="text" name="first_name" value="<?= $v('first_name') ?>" required maxlength="80" autocomplete="given-name"></label><label class="lab">Goes by<input type="text" name="preferred_name" value="<?= $v('preferred_name') ?>" maxlength="80" autocomplete="nickname"></label><label class="lab"><span>Last name<?= $lock_note('last_name') ?></span><input type="text" name="last_name" value="<?= $v('last_name') ?>" required maxlength="80" autocomplete="family-name"></label></div>
    <div class="r3"><label class="lab">Email<input type="email" name="email" value="<?= $v('email') ?>" autocomplete="email"></label><label class="lab">Mobile<input type="tel" name="phone" value="<?= $v('phone') ?>" autocomplete="tel"></label><label class="lab"><span>Birth date<?= $lock_note('birth_date') ?></span><input type="date" name="birth_date" value="<?= $v('birth_date') ?>" autocomplete="bday"></label></div>
    <div class="r3"><label class="lab">Gender<select name="gender"><?php foreach (['' => 'Choose', 'female' => 'Female', 'male' => 'Male'] as $k => $l): ?><option value="<?= $k ?>"<?= ($p['gender'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label><label class="lab">T-shirt<input type="text" name="shirt" value="<?= $v('shirt') ?>" placeholder="Men's L" maxlength="30"></label><label class="lab">Address<input type="text" name="address" value="<?= $v('address') ?>" autocomplete="street-address"></label></div>
    <div class="r3"><label class="lab">City<input type="text" name="city" value="<?= $v('city') ?>" autocomplete="address-level2"></label><label class="lab">State<input type="text" name="state" value="<?= $v('state') ?>" autocomplete="address-level1"></label><label class="lab">ZIP<input type="text" name="zip" value="<?= $v('zip') ?>" autocomplete="postal-code" inputmode="numeric"></label></div>
    <input type="hidden" name="sms_ok_set" value="1"><label class="chk"><input type="checkbox" name="sms_ok" value="1"<?= !empty($p['sms_ok']) ? ' checked' : '' ?>> OK to text this mobile number about the trip (reply STOP anytime)</label>
  </section>
  <section class="tile xl form"><h2 class="card-title">Passport</h2>
    <div class="r2"><label class="lab"><span>Name exactly as on passport<?= $lock_note('passport_name') ?></span><input type="text" name="passport_name" value="<?= $v('passport_name') ?>" maxlength="120"></label>
      <label class="lab">Passport number<input type="text" name="passport_number" value="<?= e(mask_passport($p['passport_number'] ?? '')) ?>" placeholder="<?= !empty($p['passport_number']) ? 'Type a new number to change it' : '' ?>" autocomplete="off" maxlength="30"></label></div>
    <div class="r3"><label class="lab">Issued<input type="date" name="passport_issued" value="<?= $v('passport_issued') ?>"></label><label class="lab">Expires<input type="date" name="passport_expires" value="<?= $v('passport_expires') ?>"></label><label class="lab">Issuing country<input type="text" name="passport_country" value="<?= $v('passport_country') ?>" placeholder="United States" autocomplete="country-name"></label></div>
    <div class="muted small">Passport numbers are encrypted and only the last 4 digits are shown.</div>
  </section>
  <section class="tile xl form"><h2 class="card-title">Emergency contacts</h2>
    <div class="r3"><label class="lab">Name<input type="text" name="ec1_name" value="<?= $v('ec1_name') ?>"></label><label class="lab">Relationship<select name="ec1_rel"><?php foreach ($rels as $r): ?><option<?= ($p['ec1_rel'] ?? '') === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></label><label class="lab">Phone<input type="tel" name="ec1_phone" value="<?= $v('ec1_phone') ?>"></label></div>
    <div class="r3"><label class="lab">Second contact<input type="text" name="ec2_name" value="<?= $v('ec2_name') ?>"></label><label class="lab">Relationship<select name="ec2_rel"><?php foreach ($rels as $r): ?><option<?= ($p['ec2_rel'] ?? '') === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></label><label class="lab">Phone<input type="tel" name="ec2_phone" value="<?= $v('ec2_phone') ?>"></label></div>
  </section>
  <?php if ($show_medical): ?>
  <section class="tile xl form"><h2 class="card-title">Health</h2><div class="muted small">Only you, trip leaders and the missions team can see this. It's encrypted and erased a few months after your trip.</div>
    <div class="r2"><label class="lab">Allergies<textarea name="allergies" rows="2"><?= $v('allergies') ?></textarea></label><label class="lab">Medications<textarea name="meds" rows="2"><?= $v('meds') ?></textarea></label></div>
    <div class="r2"><label class="lab">Dietary needs<textarea name="diet" rows="2"><?= $v('diet') ?></textarea></label><label class="lab">Health concerns<textarea name="health" rows="2"><?= $v('health') ?></textarea></label></div>
    <label class="lab">Anything else we should know<textarea name="other" rows="2"><?= $v('other') ?></textarea></label>
  </section>
  <?php endif; ?>
  <?php if ($staff): ?>
  <section class="tile xl form"><h2 class="card-title">Staff notes</h2>
    <div class="r2"><label class="lab">Tags<input type="text" name="tags" value="<?= $v('tags') ?>" placeholder="Student, Medical team"></label><label class="lab">Planning Center ID<input type="text" name="pco_id" value="<?= $v('pco_id') ?>" inputmode="numeric"></label></div>
    <label class="lab">Notes<textarea name="notes" rows="3"><?= $v('notes') ?></textarea></label>
    <?php if (!empty($p['id'])): ?><input type="hidden" name="is_staff_set" value="1"><label class="chk"><input type="checkbox" name="is_staff" value="1"<?= !empty($p['is_staff']) ? ' checked' : '' ?>> <strong>Staff access</strong>: when they sign in with Planning Center, they can see and change everything</label><?php endif; ?>
  </section>
  <?php endif; ?>
  <div class="actions"><button class="btn btn-primary" type="submit"><?= empty($p['id']) ? 'Add person' : 'Save profile' ?></button></div>
</form>
