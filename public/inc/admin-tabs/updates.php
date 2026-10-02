<?php
// Trip workspace · Messages: announcements (with email and text), team chat, private conversations, history
$posts = all('SELECT * FROM announcements WHERE trip_id = ? ORDER BY id DESC', [$id]);
$log = all('SELECT * FROM activity WHERE trip_id = ? ORDER BY id DESC LIMIT 30', [$id]);
$threads = ['team' => $t['name'] . ' team chat'];
foreach ($team as $m) $threads['p' . $m['person_id']] = full_name($m);
$th = isset($threads[g('th')]) ? g('th') : 'team';
$last = fn(string $k) => one('SELECT * FROM chat WHERE trip_id = ? AND thread = ? ORDER BY id DESC LIMIT 1', [$id, $k]);
$sent = all('SELECT * FROM outbox WHERE trip_id = ? ORDER BY id DESC LIMIT 8', [$id]);
?>
<div class="split">
  <div style="display:flex;flex-direction:column;gap:28px;min-width:0">
    <?php [$ce, $ct] = recipient_counts(team_recipients($id, false)); [$cpe, $cpt] = recipient_counts(team_recipients($id, true)); ?>
    <form class="tile xl form" method="post" action="/action.php" data-announce data-counts="<?= e(json_encode(['e' => $ce, 't' => $ct, 'pe' => $cpe, 'pt' => $cpt])) ?>">
      <?= csrf() ?><?= once() ?><input type="hidden" name="action" value="announce_save"><input type="hidden" name="trip_id" value="<?= $id ?>">
      <h2 class="card-title">Post an announcement to the <?= e($t['name']) ?> team</h2>
      <label class="lab">Headline (optional)<input type="text" name="title" placeholder="Bring your passport Sunday" maxlength="150"></label>
      <label class="lab">Message<textarea name="body" rows="4" required maxlength="5000"></textarea></label>
      <fieldset class="chips gap-8"><legend class="sr">Also send it</legend>
        <label class="chk"><input type="checkbox" name="email" value="1"> Email it</label>
        <label class="chk"><input type="checkbox" name="text" value="1"> Text it<?= text_ready() ? '' : ' <span class="muted small">(not set up)</span>' ?></label>
        <label class="chk"><input type="checkbox" name="parents" value="1"> Include parents</label>
      </fieldset>
      <div class="actions"><span class="muted small" style="margin-right:auto;align-self:center" data-reach>Shows in every traveler's Messages.<?= mail_ready() ? '' : ' Email isn\'t set up yet, so emails would be saved, not sent.' ?> Texts only go to people who said yes.</span><button class="btn btn-primary" type="submit">Post</button></div>
    </form>

    <section class="msgs" style="min-height:520px">
      <aside class="convs" style="max-height:640px;overflow-y:auto">
        <?php foreach ($threads as $k => $label): $lm = $last($k); if ($k !== 'team' && !$lm) continue; ?>
          <a class="conv<?= $th === $k ? ' on' : '' ?>" href="<?= e($here) ?>&th=<?= $k ?>"><span class="av<?= $k === 'team' ? ' dark' : '' ?>" style="width:40px;height:40px"><?= $k === 'team' ? e(strtoupper(substr($t['name'], 0, 2))) : initials($label) ?></span>
            <div class="grow"><div style="display:flex;justify-content:space-between;gap:8px"><strong><?= e($label) ?></strong><span class="muted small"><?= $lm ? fdate($lm['created_at'], 'M j') : '' ?></span></div><div class="last"><?= $lm ? e($lm['author'] . ': ' . $lm['body']) : 'Everyone on the team' ?></div></div></a>
        <?php endforeach; ?>
        <details style="padding:8px 10px"><summary class="muted small" style="cursor:pointer">Message one traveler</summary>
          <div style="display:flex;flex-direction:column;gap:2px;padding-top:6px"><?php foreach ($team as $m): ?><a class="small" style="padding:6px 4px" href="<?= e($here) ?>&th=p<?= (int)$m['person_id'] ?>"><?= e(full_name($m)) ?></a><?php endforeach; ?></div></details>
      </aside>
      <section class="thread">
        <div style="padding:16px 24px;border-bottom:1px solid var(--sand)"><strong style="font-size:17px"><?= e($threads[$th]) ?></strong><div class="muted small"><?= $th === 'team' ? 'The whole team and leaders see this' : 'Private: just this traveler and the leaders' ?></div></div>
        <?php chat_box($id, $th, is_staff_session(), is_staff_session() ? null : (int)($_SESSION['auth']['person_id'] ?? 0), $th === 'team' ? 'No messages yet. Say hi to the team.' : 'Start a private conversation.'); ?>
      </section>
    </section>

    <section>
      <h2 class="gh">Posted announcements</h2>
      <div class="group">
      <?php foreach ($posts as $p): ?>
        <div class="post"><div style="display:flex;justify-content:space-between;gap:12px"><strong><?= e($p['title'] ?: 'Announcement') ?></strong><span class="muted small"><?= e($p['author']) ?> · <?= fdate($p['created_at'], 'M j, g:i A') ?></span></div><div><?= soft($p['body']) ?></div>
          <form method="post" action="/action.php" data-confirm="Delete this announcement? It disappears from travelers' Messages (emails already sent can't be recalled)."><?= csrf() ?><input type="hidden" name="action" value="announce_delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="link-btn danger">Delete</button></form></div>
      <?php endforeach; ?>
      <?= $posts ? '' : '<div class="empty">No updates yet.</div>' ?>
      </div>
    </section>
  </div>
  <aside class="sticky" style="display:flex;flex-direction:column;gap:20px">
    <section><div class="gh"><span>Sent</span><a href="/admin/settings.php?s=email">All</a></div><div class="group">
      <?php foreach ($sent as $o): ?><div class="cell"><div class="grow"><strong><?= e($o['channel'] === 'text' ? 'Text' : ($o['subject'] ?: 'Email')) ?></strong><div class="muted small"><?= e($o['to_addr']) ?> · <?= fdate($o['created_at'], 'M j') ?></div></div><span class="pill<?= $o['status'] === 'sent' ? ' pill-ok' : '' ?>"><?= e(['sent' => 'Sent', 'not_sent' => 'Not sent', 'failed' => 'Failed', 'queued' => 'Sending'][$o['status']] ?? $o['status']) ?></span></div><?php endforeach; ?>
      <?= $sent ? '' : empty_state('Nothing sent yet') ?>
    </div></section>
    <section><div class="gh">History</div>
    <div class="group">
    <?php foreach ($log as $x): ?><div class="cell"><div class="grow"><strong><?= e($x['who']) ?></strong> <span class="muted"><?= e(lcfirst($x['what'])) ?></span><div class="muted small"><?= fdate($x['created_at'], 'M j, g:i A') ?></div></div></div><?php endforeach; ?>
    <?= $log ? '' : empty_state('Nothing yet') ?>
    </div></section>
  </aside>
</div>
