<?php
// public/components/staffcalendar/settings_messages.php — the birthday message library (list, tag filter, add, edit, switch off, approve or reject suggestions), shown in
// Settings · Staff Calendar · Birthday messages. It is the ONE copy of this screen. The actions are handled in settings.php (super admin only, checked again in the service).
// Needs $db, $e, $self (the settings page address), $tag, $status and $back from settings.php.
$list = sbd_msg_list($db, $tag, $status);
$counts = sbd_msg_counts($db);
$mtab = fn(string $label, array $q, bool $on, string $badge = '') => '<a class="bm-tab' . ($on ? ' on' : '') . '" href="' . $e($self . '?' . http_build_query(['tab' => 'messages'] + $q)) . '">' . $e($label) . ($badge !== '' ? ' <b>' . $e($badge) . '</b>' : '') . '</a>';
$post = fn(string $op, int $id, string $label, string $cls = '') => '<form method="post" action="' . $e($back()) . '" style="display:inline">' . csrf_field() . '<input type="hidden" name="tab" value="messages"><input type="hidden" name="op" value="' . $e($op) . '"><input type="hidden" name="id" value="' . $id . '"><button class="bm-btn ' . $cls . '">' . $e($label) . '</button></form>';

?>
<style>
.bm{--g:#048C44;--gd:#037A3B;--gl:#E3F3EA;--pink:#d6457f;--mut:#6b7067;--line:#e6e9e4;max-width:980px;color:#1d1b1c}
.bm h1{font-size:1.5rem;font-weight:800;margin:0 0 2px}.bm .sub{color:var(--mut);font-size:.9rem;margin:0 0 16px}
.bm a.back{color:var(--gd);font-weight:700;text-decoration:none;font-size:.88rem}
.bm-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0}
.bm-tab{background:#fff;border:1px solid var(--line);border-radius:999px;padding:6px 14px;font-size:.84rem;font-weight:600;color:#1d1b1c;text-decoration:none}
.bm-tab b{color:var(--mut);font-weight:700}.bm-tab.on{background:var(--g);border-color:var(--g);color:#fff}.bm-tab.on b{color:#fff}
.bm-card{background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(29,27,28,.04),0 6px 20px rgba(29,27,28,.05);padding:16px 18px;margin-bottom:12px}
.bm-card.pending{border-left:4px solid var(--pink)}.bm-card.rejected,.bm-card.off{opacity:.65}
.bm-text{font-size:1rem;line-height:1.5;margin:6px 0;word-break:break-word}
.bm-meta{display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-size:.8rem;color:var(--mut)}
.bm-pill{border-radius:999px;padding:2px 10px;font-weight:700;font-size:.74rem;background:var(--gl);color:var(--gd)}
.bm-pill.p{background:#fbe3ec;color:var(--pink)}.bm-pill.x{background:#eee;color:#555}
.bm-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;align-items:flex-start}
.bm-btn{border:1px solid #d5ddd3;background:#fff;color:var(--gd);border-radius:10px;padding:6px 14px;font-weight:700;font-size:.84rem;cursor:pointer;font-family:inherit}
.bm-btn.go{background:var(--g);border-color:var(--g);color:#fff}.bm-btn.no{color:#b42318;border-color:#f1c9c4}
.bm details{display:inline-block}.bm summary{list-style:none}.bm summary::-webkit-details-marker{display:none}
.bm details[open]{display:block;width:100%}
.bm form.edit,.bm form.add{display:grid;gap:10px;margin-top:10px}
.bm textarea,.bm select{width:100%;border:1px solid #d8ddd5;border-radius:12px;padding:10px 12px;font-family:inherit;font-size:.95rem}
.bm .row2{display:grid;grid-template-columns:180px 1fr;gap:10px}
@media (max-width:767.98px){.bm .row2{grid-template-columns:1fr}.bm h1{font-size:1.25rem}.bm-btn{padding:8px 14px}}
</style>
<div class="bm">
        <p class="sub"><?php echo (int) $counts['all']; ?> messages in use<?php echo $counts['pending'] ? ' · ' . (int) $counts['pending'] . ' waiting for you' : ''; ?>. Each person gets one on their birthday, one they have not had before. Use {first_name} where the name goes.</p>

    <div class="bm-card">
        <strong>Add a message</strong>
        <form class="add" method="post" action="<?php echo $e($back()); ?>"><?php echo csrf_field(); ?><input type="hidden" name="tab" value="messages"><input type="hidden" name="op" value="add">
            <div class="row2"><select name="tag" aria-label="Tag"><?php foreach (SBD_TAGS as $t): ?><option<?php echo $t === $tag ? ' selected' : ''; ?>><?php echo $e($t); ?></option><?php endforeach; ?></select>
                <textarea name="body" rows="2" maxlength="400" placeholder="Short, warm and a little witty" required aria-label="Message"></textarea></div>
            <div><button class="bm-btn go">Add message</button></div>
        </form>
    </div>

    <div class="bm-tabs">
        <?php echo $mtab('All', [], $tag === '' && $status === '', (string) $counts['all']); ?>
        <?php foreach (SBD_TAGS as $t) echo $mtab($t, ['tag' => $t], $tag === $t && $status === '', (string) $counts['tags'][$t]); ?>
        <?php echo $mtab('Waiting', ['status' => 'pending'], $status === 'pending', (string) $counts['pending']); ?>
        <?php echo $mtab('Switched off', ['status' => 'off'], $status === 'off'); ?>
        <?php echo $mtab('Rejected', ['status' => 'rejected'], $status === 'rejected'); ?>
    </div>

    <?php if (!$list): ?><div class="bm-card" style="color:var(--mut)">No messages here.</div><?php endif; ?>
    <?php foreach ($list as $m): $cls = $m['status'] === 'pending' ? 'pending' : ($m['status'] === 'rejected' ? 'rejected' : ($m['active'] ? '' : 'off')); ?>
    <div class="bm-card <?php echo $cls; ?>">
        <div class="bm-meta"><span class="bm-pill"><?php echo $e($m['tag']); ?></span><?php if ($m['status'] === 'approved'): ?><span><?php echo $m['used'] > 0 ? 'Used ' . (int) $m['used'] . ($m['used'] === 1 ? ' time' : ' times') : 'Not used yet'; ?></span><?php endif; ?>
            <?php if ($m['status'] === 'pending'): ?><span class="bm-pill p">Waiting for approval</span><?php elseif ($m['status'] === 'rejected'): ?><span class="bm-pill x">Rejected</span><?php elseif (!$m['active']): ?><span class="bm-pill x">Switched off</span><?php endif; ?>
            <?php if ($m['by'] !== ''): ?><span>Suggested by <?php echo $e($m['by']); ?></span><?php endif; ?></div>
        <p class="bm-text"><?php echo $e($m['body']); ?></p>
        <div class="bm-actions">
            <?php if ($m['status'] === 'pending'): echo $post('approve', $m['id'], 'Approve', 'go') . $post('reject', $m['id'], 'Reject', 'no');
            elseif ($m['status'] === 'approved'): echo $post($m['active'] ? 'off' : 'on', $m['id'], $m['active'] ? 'Switch off' : 'Switch on'); endif; ?>
            <?php if ($m['status'] !== 'rejected'): ?>
            <details><summary class="bm-btn">Edit</summary>
                <form class="edit" method="post" action="<?php echo $e($back()); ?>"><?php echo csrf_field(); ?><input type="hidden" name="tab" value="messages"><input type="hidden" name="op" value="edit"><input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                    <div class="row2"><select name="tag" aria-label="Tag"><?php foreach (SBD_TAGS as $t): ?><option<?php echo $t === $m['tag'] ? ' selected' : ''; ?>><?php echo $e($t); ?></option><?php endforeach; ?></select>
                        <textarea name="body" rows="3" maxlength="400" required aria-label="Message"><?php echo $e($m['body']); ?></textarea></div>
                    <div><button class="bm-btn go">Save</button></div>
                </form></details>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
