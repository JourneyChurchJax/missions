<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_preview('staff');
$q = trim((string)($_GET['q'] ?? '')); $found = []; $err = '';
if ($q !== '' && pco_api_ready()) { try { $found = pco_search($q); } catch (Throwable $e) { $err = $e->getMessage(); } }
page_open('Add from Planning Center');
admin_header('people');
?>
<main class="main" style="max-width:820px">
  <div class="bar-head"><div><a class="muted small" href="/admin/people.php" style="text-decoration:none">‹ People</a><h1>Add from Planning Center</h1><div class="muted small">Find someone in Planning Center and bring them in with their contact info, birthday and background check.</div></div></div>
  <?php if (!pco_api_ready()): ?>
    <section class="note"><strong>Planning Center isn't connected yet</strong><div class="muted small">See Settings → Planning Center for the two keys to add.</div><a href="/admin/settings.php?s=pco" style="font-weight:600">Open settings ›</a></section>
  <?php else: ?>
    <form method="get" class="form tile" style="grid-template-columns:1fr auto;align-items:end"><label class="lab">Name or email<input type="search" name="q" value="<?= e($q) ?>" autofocus required></label><button class="btn btn-dark" type="submit">Search</button></form>
    <?php if ($err): ?><div class="note"><?= e($err) ?></div><?php endif; ?>
    <?php if ($q !== '' && !$err): ?><div class="group">
      <?php foreach ($found as $r): $have = one('SELECT id FROM people WHERE pco_id = ?', [$r['id']]); $f = $r['fields']; ?>
        <form class="cell" method="post" action="/action.php"><?= csrf() ?><input type="hidden" name="action" value="pco_import"><input type="hidden" name="pco_id" value="<?= e($r['id']) ?>"><input type="hidden" name="first_name" value="<?= e($f['first_name'] ?? '') ?>"><input type="hidden" name="last_name" value="<?= e($f['last_name'] ?? '') ?>">
          <span class="av"><?= initials(trim(($f['first_name'] ?? '') . ' ' . ($f['last_name'] ?? ''))) ?></span>
          <div class="grow"><strong><?= e(trim(($f['first_name'] ?? '') . ' ' . ($f['last_name'] ?? ''))) ?></strong><div class="muted small"><?= e($f['email'] ?? 'No email') ?><?= !empty($f['phone']) ? ' · ' . e($f['phone']) : '' ?></div></div>
          <?php if ($have): ?><a class="btn" href="/admin/person.php?id=<?= (int)$have['id'] ?>" style="height:36px">Already here</a><?php else: ?><button class="btn btn-primary" type="submit" style="height:36px">Add</button><?php endif; ?></form>
      <?php endforeach; ?>
      <?= $found ? '' : '<div class="empty">No one found in Planning Center.</div>' ?>
    </div><?php endif; ?>
  <?php endif; ?>
</main>
<?php page_close(); ?>
