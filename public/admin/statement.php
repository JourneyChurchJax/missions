<?php
// Printable year-end giving statements: one donor, or every donor (one per page).
require dirname(__DIR__) . '/inc/bootstrap.php';
require_staff();
$year = gi('year') ?: (int)date('Y') - 1;
$ids = gi('donor') ? [gi('donor')]
    : array_map('intval', array_column(all("SELECT DISTINCT d.id, d.last_name, d.first_name FROM donors d JOIN gifts g ON g.donor_id = d.id WHERE g.status IN ('cleared','refunded','disputed') AND g.gift_date BETWEEN ? AND ? ORDER BY d.last_name, d.first_name", ["$year-01-01", "$year-12-31"]), 'id'));
$legal = church_legal();
audit('statements_view', 'donors', count($ids) === 1 ? $ids[0] : null, null, $year);
?>
<!doctype html>
<html lang="en">
<head>
<?php head_tags($year . ' giving statements'); ?>
<style>
body{background:var(--sand)}
.sheet{background:#fff;max-width:760px;margin:24px auto;padding:56px 64px;border-radius:var(--r-md);box-shadow:var(--sh-md);font-size:15px;line-height:1.55}
.sheet table{width:100%;border-collapse:collapse;margin:18px 0}.sheet td,.sheet th{padding:8px 0;border-bottom:1px solid var(--sand);text-align:left}.sheet .num{text-align:right}
.bar-print{max-width:760px;margin:24px auto 0;display:flex;justify-content:space-between;align-items:center;gap:12px}
@media print{body{background:#fff}.bar-print{display:none}.sheet{box-shadow:none;margin:0;max-width:none;padding:24px 8px;page-break-after:always;border-radius:0}}
</style>
</head>
<body>
<div class="bar-print"><a href="/admin/giving.php?v=statements&year=<?= $year ?>">‹ Back to statements</a><button class="btn btn-primary" onclick="window.print()">Print or save as PDF</button></div>
<?php foreach ($ids as $did): $d = donor($did); if (!$d) continue; $list = array_values(array_filter(array_reverse(gifts(['donor' => $did, 'year' => $year], 5000)), fn($g) => in_array($g['status'], ['cleared', 'refunded', 'disputed'], true) && (float)$g['amount'] - (float)$g['refunded'] > 0)); $total = array_sum(array_map(fn($g) => (float)$g['amount'] - (float)$g['refunded'], $list)); ?>
<section class="sheet">
  <div class="row-between"><?= logo(220, false, '#') ?><div class="small" style="text-align:right"><strong><?= e($legal['name']) ?></strong><br><?= nl2br(e($legal['address'])) ?><?= $legal['ein'] ? '<br>EIN ' . e($legal['ein']) : '' ?></div></div>
  <p style="margin:28px 0 0"><?= date('F j, Y') ?></p>
  <p style="margin:16px 0 0"><?= e(donor_name($d)) ?><br><?= e($d['address']) ?><?= $d['address'] ? '<br>' : '' ?><?= e(trim($d['city'] . ($d['city'] ? ', ' : '') . $d['state'] . ' ' . $d['zip'])) ?></p>
  <h1 class="disp" style="font-size:30px;margin:28px 0 8px"><?= $year ?> giving statement</h1>
  <p style="margin:0">Dear <?= e($d['first_name'] ?: donor_name($d)) ?>, thank you for supporting <?= e($legal['name']) ?> missions. Your generosity sends people to serve and share the love of Jesus.</p>
  <table><thead><tr><th>Date</th><th>Method</th><th class="num">Amount</th></tr></thead>
    <tbody><?php foreach ($list as $g): ?><tr><td><?= fdate($g['gift_date'], 'F j, Y') ?></td><td><?= e(GIFT_METHODS[$g['method']] ?? '') ?><?= $g['check_no'] ? ' #' . e($g['check_no']) : '' ?></td><td class="num"><?= money((float)$g['amount'] - (float)$g['refunded'], 2) ?></td></tr><?php endforeach; ?>
    <tr><td colspan="2"><strong>Total</strong></td><td class="num"><strong><?= money($total, 2) ?></strong></td></tr></tbody></table>
  <p class="small" style="color:var(--muted)">No goods or services were provided in exchange for these contributions, other than intangible religious benefits. <?= e(discretion_text()) ?> Please keep this statement for your tax records.</p>
</section>
<?php endforeach; ?>
<?php if (!$ids): ?><section class="sheet"><p>No gifts with a donor name in <?= $year ?>.</p></section><?php endif; ?>
</body>
</html>
