<?php
// Trip workspace · Trip guide (everything a traveler needs to feel at ease)
$g = guide($id);
$filled = count(array_filter(GUIDE_SECTIONS, fn($l, $k) => !is_placeholder($g[$k]['body'] ?? null) && !preg_match('/\[/', $g[$k]['body'] ?? '['), ARRAY_FILTER_USE_BOTH));
?>
<form class="form" method="post" action="/action.php" style="gap:24px">
  <?= csrf() ?><input type="hidden" name="action" value="guide_save"><input type="hidden" name="trip_id" value="<?= $id ?>">
  <div class="bar-head">
    <div><strong style="font-size:18px">Trip guide</strong><div class="muted small"><?= $filled ?> of <?= count(GUIDE_SECTIONS) ?> sections finished · anything in [brackets] shows travelers it's still coming · one item per line becomes a list</div></div>
    <div style="display:flex;gap:10px"><a class="btn" href="/trip/guide.php" target="_blank">See what travelers see</a><button class="btn btn-primary" type="submit">Save guide</button></div>
  </div>
  <div class="guide-grid">
  <?php foreach (GUIDE_SECTIONS as $k => $label): $body = $g[$k]['body'] ?? ''; ?>
    <label class="guide-card lab" style="color:var(--ink)"><span style="display:flex;justify-content:space-between;gap:8px;font-size:15px"><?= e($label) ?><span class="muted small" style="font-weight:500"><?= str_contains($body, '[') || trim($body) === '' ? 'Needs info' : 'Done' ?></span></span>
      <textarea name="<?= $k ?>" rows="6"><?= e($body) ?></textarea></label>
  <?php endforeach; ?>
  </div>
  <div class="actions"><button class="btn btn-primary" type="submit">Save guide</button></div>
</form>
