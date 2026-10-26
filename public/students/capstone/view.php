<?php
// Student: one capstone. Not open yet (only the date), open (brief, example, milestones, my work, submit), returned, marked, retake.
// Everything is checked on the server: the student can only see this when the tutor opened it, and the rules (return count,
// resubmitting only after a return) are enforced by CapstoneService, not by this page.
require __DIR__ . '/../../components/capstone/student_boot.php';

use Models\AssignmentModel;

require_once __DIR__ . '/../../../app/v1/services/AssignmentAverage.php';

$id = (int) ($_GET['id'] ?? 0);
$cap = cap_for_student($pdo, $id, $uid);
if (!$cap) asg_back('index.php', null, 'Capstone not found.');
$self = 'view.php?id=' . $id;
$open = cap_is_open_for($cap);
$tutor = $am->tutorById((int) $cap['tutor_id']);
$att = $open ? cap_ensure_attempt($pdo, $cap, $uid) : null;
$owner = $att ? cap_owner($pdo, $att) : null;
$isAjax = !empty($_POST['ajax']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fail = function (string $msg) use ($isAjax, $self) {
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'errors' => [$msg]]); exit; }
        asg_back($self, null, $msg);
    };
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $fail('Security check failed. Please refresh and try again.');
    if (!$open || !$att) $fail('This capstone is not open.');
    $action = $_POST['action'] ?? '';

    if ($action === 'ack_example') {
        if (empty($_POST['ack'])) $fail('Tick the box to open the example.');
        cap_ack_example($pdo, $id, $uid);
        asg_back($self . '#example', 'The example is open. Remember: it is only an example. Do not copy it.');
    }
    if ($action === 'save_draft' || $action === 'submit') {
        $files = [];
        foreach (cap_deliverables($pdo, $id) as $d) if (!empty($_FILES['file_' . $d['id']]['name'])) $files[(int) $d['id']] = $_FILES['file_' . $d['id']];
        $links = [];
        foreach ((array) ($_POST['link'] ?? []) as $did => $u) $links[(int) $did] = (string) $u;
        $r = cap_save_work($pdo, $cap, $att, $uid, $links, $files, !empty($_POST['own_work']), (string) ($_POST['ai_note'] ?? ''), $tutor);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => $r['ok'], 'errors' => $r['errors'], 'saved' => cap_local(cap_now(), 'H:i') . ' EAT']); exit; }
        if (!$r['ok']) asg_back($self . '#work', null, implode(' ', $r['errors']));
        if ($action === 'save_draft') asg_back($self . '#work', 'Draft saved. Your tutor cannot see it until you send it.');
        $s = cap_submit($pdo, $cap, cap_latest_attempt($pdo, $id, $uid), $uid, !empty($_POST['confirm_rules']));
        AssignmentNotifier::flush();
        $s['ok'] ? asg_back($self, 'Your capstone was sent to your tutor.' . (!empty($s['final']) ? ' This was your last send, so the mark will be final.' : ' If your tutor returns it, you will be told.'))
                 : asg_back($self . '#work', null, $s['error']);
    }
    if ($action === 'remove_item') {
        $item = cap_item($pdo, (int) ($_POST['item'] ?? 0));
        $work = cap_work($pdo, $owner, false);
        if ($item && $work && $work['status'] === 'draft' && (int) $item['submission_id'] === (int) $work['id'] && cap_can_edit($cap, $att)) {
            $pdo->prepare('DELETE FROM capstone_submission_items WHERE id = ?')->execute([(int) $item['id']]);
            asg_back($self . '#work', 'Removed from your draft.');
        }
        asg_back($self . '#work');
    }
    if ($action === 'send_milestone') {
        $r = cap_milestone_send($pdo, $cap, $att, (int) ($_POST['milestone'] ?? 0), $uid, (string) ($_POST['note'] ?? ''), (string) ($_POST['url'] ?? ''), $_FILES['file'] ?? null);
        asg_back($self . '#milestones', $r['ok'] ? 'Sent. Your tutor can see it.' : null, $r['ok'] ? null : $r['error']);
    }
    if ($action === 'book_slot') {
        $r = cap_slot_book($pdo, $cap, $att, (int) ($_POST['slot'] ?? 0), $uid);
        asg_back($self . '#slots', $r['ok'] ? 'Your presentation slot is booked. It is on your Class Calendar.' : null, $r['ok'] ? null : $r['error']);
    }
    if ($action === 'unbook_slot') {
        asg_back($self . '#slots', cap_slot_unbook($pdo, $cap, $att, $uid) ? 'Slot cancelled. You can book another.' : null, 'This slot cannot be cancelled now.');
    }
    asg_back($self);
}

// ---------- Not open yet: the date and the rules, nothing else ----------
if (!$open) {
    $page_title = 'Capstone';
    ob_start();
    ?>
    <a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Capstone</a>
    <div class="card-tutor p-4">
        <div class="fw-bold fs-4 mb-1"><i class="fas fa-lock me-2 text-muted"></i>Your capstone is not open yet</div>
        <p class="mb-2"><?php echo $cap['status'] === 'closed' ? 'This capstone is closed.' : ($cap['opens_at'] ? 'Your capstone opens on <strong>' . asg_e(cap_local($cap['opens_at'], 'l j F Y, H:i')) . ' EAT</strong>.' : 'Your tutor will open it for you.'); ?></p>
        <div class="cap-rules"><strong>The rules</strong><ul class="mb-0 ps-3"><li>Marked out of 100. The pass mark is 50.</li><li>Once you send it, it is final unless your tutor returns it. You can only improve it twice.</li><li>If you do not pass, you can retake it up to 5 times, and you pay again each time.</li></ul></div>
    </div>
    <?php
    $content = ob_get_clean();
    require __DIR__ . '/../../templates/students/layout.php';
    exit;
}

// ---------- Open ----------
$state = cap_state($cap, $att);
$closed = $cap['status'] === 'closed';
$dels = cap_deliverables($pdo, $id);
$milestones = cap_milestones($pdo, $id);
$work = cap_work($pdo, $owner, false);
$draft = $work && $work['status'] === 'draft' ? $work : null;
$shown = $draft ?: $work;                       // the draft being edited, or the version that was sent
$items = $shown ? cap_items($pdo, (int) $shown['id']) : [];
$itemBy = [];
foreach ($items as $it) if ($it['deliverable_id']) $itemBy[(int) $it['deliverable_id']] = $it;
$returns = cap_returns($pdo, (int) $owner['id']);
$lastReturn = $returns ? end($returns) : null;
$mark = cap_mark($pdo, (int) $att['id']);
$released = cap_mark_released($mark);
$canEdit = cap_can_edit($cap, $att);
$evidence = cap_time_evidence($pdo, $cap, $owner);
$evBy = [];
foreach ($evidence['rows'] as $r) $evBy[(int) $r['id']] = $r;
$msSent = [];
$q = $pdo->prepare('SELECT * FROM capstone_milestone_submissions WHERE attempt_id = ?');
$q->execute([(int) $owner['id']]);
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $msSent[(int) $r['milestone_id']] = $r;
$booking = cap_booking($pdo, $owner);
$slots = $canEdit ? array_filter(cap_slots($pdo, $id), fn($s) => $s['starts_at'] > cap_now() && (int) $s['booked'] < (int) $s['capacity']) : [];
$history = cap_history($pdo, $id, $uid);
$group = cap_group_attempts($pdo, $owner);
$teamNames = [];
foreach ($group as $g) if ((int) $g['student_user_id'] !== $uid) $teamNames[] = $am->student((int) $g['student_user_id'])['full_name'];
$ownTick = false;
$ownNote = '';
if ($draft) {
    $st = $pdo->prepare('SELECT * FROM capstone_own_work WHERE attempt_id = ? AND student_user_id = ? AND version = ?');
    $st->execute([(int) $owner['id'], $uid, (int) $draft['version']]);
    if ($t = $st->fetch(PDO::FETCH_ASSOC)) { $ownTick = true; $ownNote = (string) $t['ai_note']; }
}
$acked = cap_example_acked($pdo, $id, $uid);
$hasExample = $cap['example_link'] || $cap['example_file'];
$due = cap_due_for($cap);
$daysLeft = $due ? (int) ceil((strtotime($due . ' UTC') - time()) / 86400) : null;
$returnsLeft = CAP_MAX_RETURNS - (int) $owner['returns_used'];
$awaitingFee = $att['status'] === 'awaiting_payment';
$retakeNo = (int) $att['attempt_no'] - 1;
$problems = $canEdit && $draft ? cap_submit_problems($pdo, $cap, $owner) : [];
$requirements = array_filter(array_map('trim', preg_split('/\r?\n/', (string) $cap['requirements'])));
$fromTutor = $tutor['full_name'] ?? 'your tutor';
$page_title = 'Capstone';
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Capstone</a>

<div class="card-tutor p-3 p-md-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <div class="small fw-bold text-success">CAPSTONE<?php echo $retakeNo > 0 ? ' · RETAKE ' . $retakeNo . ' OF ' . CAP_MAX_RETAKES : ''; ?></div>
            <h4 class="mb-1"><?php echo asg_e($cap['title']); ?></h4>
            <div class="small text-muted">From <?php echo asg_e($fromTutor); ?> · <?php echo asg_e($cap['course_title']); ?><?php echo $teamNames ? ' · Team with ' . asg_e(implode(', ', $teamNames)) : ''; ?></div>
        </div>
        <div class="text-end">
            <?php echo cap_chip($closed && $state !== 'marked' ? 'closed' : $state, $state === 'returned' ? 'Returned to you, ' . (int) $owner['returns_used'] . ' of 2' : null); ?>
            <?php if ($due): ?><div class="small mt-1">Final due <strong><?php echo asg_e(cap_local($due, 'D j M, H:i')); ?> EAT</strong>
                <?php if (!in_array($state, ['to_mark', 'marked'], true) && !$closed): ?><br><span class="<?php echo $daysLeft < 0 ? 'text-danger' : 'text-muted'; ?>"><?php echo $daysLeft >= 0 ? $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left' : 'Past the due date'; ?></span><?php endif; ?></div><?php endif; ?>
        </div>
    </div>
</div>

<?php if ($awaitingFee): ?>
    <div class="alert alert-warning"><i class="fas fa-wallet me-1"></i><strong>Pay the retake fee first.</strong> A capstone retake needs a new payment of <strong>KES <?php echo number_format((float) $att['retake_fee']); ?></strong>. Pay at the office or through Finance; your retake opens after the office confirms your payment.</div>
<?php endif; ?>

<?php if ($state === 'returned' || $state === 'final_try'): ?>
    <div class="card-tutor p-3 p-md-4 mb-3" style="border:2px solid #f3c48b">
        <div class="fw-bold mb-1"><i class="fas fa-rotate-left me-1"></i>Returned to you, <?php echo (int) $owner['returns_used']; ?> of 2</div>
        <?php if ($lastReturn): ?>
            <div class="asg-fb"><div class="h">Instructions from <?php echo asg_e($fromTutor); ?></div><?php echo nl2br(asg_e($lastReturn['instructions'])); ?>
                <?php if ($lastReturn['send_by']): ?><div class="small mt-2"><strong>Send it again by <?php echo asg_e(cap_local($lastReturn['send_by'], 'D j M, H:i')); ?> EAT.</strong></div><?php endif; ?></div>
        <?php endif; ?>
        <div class="cap-rules"><strong><?php echo $returnsLeft > 0 ? 'You can improve your work only ' . ($returnsLeft === 1 ? 'one more time' : $returnsLeft . ' more times') . '.' : 'This is your last send.'; ?></strong>
            <?php echo $returnsLeft > 0 ? 'If it is below 50, your tutor can return it again.' : 'After this, the mark is final.'; ?> The marks stay <em>Held</em> until your tutor releases them.</div>
        <?php if ($canEdit): ?><a href="#work" class="btn btn-brand mt-3">Improve and send again</a><?php endif; ?>
    </div>
<?php elseif ($canEdit): ?>
    <div class="cap-rules mb-3"><strong>You can only improve your work twice.</strong> Once you send it, it is final unless your tutor returns it. If it is below 50, your tutor can return it up to 2 times. After that, the mark is final.</div>
<?php endif; ?>

<?php if ($state === 'to_mark'): ?>
    <div class="alert alert-success"><i class="fas fa-circle-check me-1"></i><strong>Sent.</strong> Your tutor has your capstone and will mark it. You cannot change it unless your tutor returns it.</div>
<?php endif; ?>

<?php if ($state === 'marked' && $mark): ?>
    <div class="card-tutor p-3 p-md-4 mb-3" style="border:2px solid #44A024">
        <div class="fw-bold mb-2">Your result</div>
        <?php if ($released): ?>
            <div class="d-flex flex-wrap align-items-center gap-3"><div class="asg-ring" style="width:120px;height:120px"><b><?php echo asg_e(cap_pretty_marks($mark['total'])); ?></b><span class="small text-muted">out of 100</span></div>
                <div><div class="fs-4 fw-bold"><?php echo asg_e($mark['result']); ?></div><div class="small"><?php echo (float) $mark['total'] >= CAP_PASS ? 'You passed your capstone.' : 'Your capstone was not passed.'; ?></div></div></div>
            <div class="row g-2 mt-2">
                <?php foreach (CAP_PARTS as $k => $label): ?><div class="col-md-6"><div class="asg-box"><div class="d-flex justify-content-between"><strong class="small"><?php echo $label; ?></strong><span><?php echo asg_e(cap_pretty_marks($mark['p_' . $k])); ?> / <?php echo (int) $cap['w_' . $k]; ?></span></div>
                    <?php if ($mark['c_' . $k]): ?><div class="small text-muted"><?php echo nl2br(asg_e($mark['c_' . $k])); ?></div><?php endif; ?></div></div><?php endforeach; ?>
            </div>
            <?php if ($mark['feedback']): ?><div class="asg-fb mt-3"><div class="h">Feedback from <?php echo asg_e($fromTutor); ?></div><?php echo nl2br(asg_e($mark['feedback'])); ?></div><?php endif; ?>
            <?php if ((float) $mark['total'] < CAP_PASS): ?>
                <div class="alert alert-info small mt-3 mb-0">Your tutor will tell you about a retake. A retake can be done up to <?php echo CAP_MAX_RETAKES; ?> times and you pay the retake fee again each time.</div>
            <?php endif; ?>
        <?php else: ?>
            <span class="asg-chip st-locked"><i class="fas fa-lock"></i>Held</span> <span class="small">Your tutor marked your capstone. The result shows on <strong><?php echo asg_e(cap_local($mark['held_until'], 'D j M, H:i')); ?> EAT</strong>.</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
<div class="col-lg-7">
    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold">Project brief</h6>
        <div><?php echo $cap['brief'] ? nl2br(asg_e($cap['brief'])) : '<span class="text-muted">No brief.</span>'; ?></div>
        <?php if ($cap['instructions']): ?><h6 class="fw-bold mt-3">Instructions</h6><div><?php echo nl2br(asg_e($cap['instructions'])); ?></div><?php endif; ?>
        <?php if ($requirements): ?><h6 class="fw-bold mt-3">Requirements</h6><ul class="mb-0"><?php foreach ($requirements as $rq): ?><li><?php echo asg_e($rq); ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>

    <?php if ($hasExample): ?>
    <div class="cap-example mb-3" id="example">
        <div class="cap-banner">EXAMPLE ONLY. DO NOT COPY THIS.</div>
        <div class="body">
            <?php if (!$acked): ?>
                <p class="small text-muted mb-2">Your tutor added an example project to show you the kind of work that is expected.</p>
                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="ack_example">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="ack" value="1" id="ack" required>
                        <label class="form-check-label" for="ack">I understand this is only an example and I will not copy it.</label></div>
                    <button class="btn btn-dark btn-sm">Open the example</button></form>
            <?php else: ?>
                <?php if ($cap['example_link']): ?><div class="mb-1"><i class="fas fa-link me-1"></i><a href="<?php echo asg_e($cap['example_link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo asg_e($cap['example_link']); ?></a></div><?php endif; ?>
                <?php if ($cap['example_file']): ?><div><i class="fas fa-file me-1"></i><a href="<?php echo asg_e(cap_file_url('example', $id)); ?>" target="_blank"><?php echo asg_e($cap['example_file_name']); ?></a></div><?php endif; ?>
                <div class="small text-muted mt-2">Use it to see the standard, then make your own. Work that is the same as the example is flagged to your tutor.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card-tutor p-3 p-md-4 mb-3" id="milestones">
        <h6 class="fw-bold">Milestones</h6>
        <div class="small text-muted mb-2">Time keeping is <strong><?php echo (int) $cap['w_time']; ?> of your 100 marks</strong>. A milestone sent after its due time loses its marks.</div>
        <?php foreach ($milestones as $m): $ev = $evBy[(int) $m['id']] ?? null; $sent = $msSent[(int) $m['id']] ?? null; ?>
            <div class="border rounded-3 p-2 mb-2">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div><strong><?php echo asg_e($m['title']); ?></strong> <span class="small text-muted">· <?php echo cap_pretty_marks($m['marks']); ?> mark<?php echo (float) $m['marks'] === 1.0 ? '' : 's'; ?></span>
                        <div class="small text-muted">Due <?php echo asg_e(cap_local($m['due_at'], 'D j M, H:i')); ?> EAT<?php echo $m['kind'] === 'final' ? ' (sent when you send the capstone)' : ($m['kind'] === 'presentation' ? ' (on the booked day)' : ''); ?></div></div>
                    <div><?php if ($ev): echo cap_ms_chip(['on_time' => !empty($sent['approved_at']) ? 'Approved' : 'On time', 'late' => 'Late', 'missing' => 'Missing', 'not_yet' => 'Not yet'][$ev['status']]); endif; ?></div>
                </div>
                <?php if ($sent): ?>
                    <div class="small mt-1">Sent <?php echo asg_e(cap_local($sent['submitted_at'], 'D j M, H:i')); ?> EAT<?php echo $sent['note'] ? ': ' . asg_e(mb_strimwidth($sent['note'], 0, 120, '…')) : ''; ?>
                        <?php if ($sent['tutor_comment']): ?><div class="text-muted">Tutor: <?php echo asg_e($sent['tutor_comment']); ?></div><?php endif; ?></div>
                <?php elseif ($m['kind'] === 'work' && $canEdit): ?>
                    <form method="POST" enctype="multipart/form-data" class="row g-2 mt-1"><?php echo csrf_field(); ?><input type="hidden" name="action" value="send_milestone"><input type="hidden" name="milestone" value="<?php echo (int) $m['id']; ?>">
                        <div class="col-12"><textarea name="note" class="form-control form-control-sm" rows="2" placeholder="A short note about what you did"></textarea></div>
                        <div class="col-md-6"><input name="url" class="form-control form-control-sm" placeholder="Link (optional), https://…"></div>
                        <div class="col-md-6"><input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.docx,.pptx,.zip"></div>
                        <div class="col-12"><button class="btn btn-outline-dark btn-sm">Send this milestone</button> <span class="small text-muted">Its sent time is saved and cannot change.</span></div></form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$milestones): ?><div class="small text-muted">No milestones.</div><?php endif; ?>
    </div>

    <?php if ($canEdit || $items): ?>
    <div class="card-tutor p-3 p-md-4 mb-3" id="work">
        <div class="d-flex justify-content-between align-items-start"><h6 class="fw-bold">Your work</h6><span class="small text-muted" id="saveState"></span></div>
        <?php if ($canEdit): ?>
        <form method="POST" enctype="multipart/form-data" id="workForm"><?php echo csrf_field(); ?>
            <?php foreach ($dels as $d): $it = $itemBy[(int) $d['id']] ?? null; $isLink = $d['item_type'] !== 'file'; $isFile = $d['item_type'] !== 'link'; ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold mb-1"><?php echo asg_e($d['label']); ?> <?php echo $d['is_required'] ? '<span class="text-danger">*</span>' : '<span class="text-muted fw-normal">(optional)</span>'; ?></label>
                    <?php if ($isLink): ?><input name="link[<?php echo (int) $d['id']; ?>]" data-del="<?php echo (int) $d['id']; ?>" class="form-control form-control-sm link-in" placeholder="https://…" value="<?php echo asg_e($it['url'] ?? ''); ?>"><?php endif; ?>
                    <div class="small mt-1 lc" id="lc<?php echo (int) $d['id']; ?>"><?php if ($it && $it['url']): echo cap_link_chip($it['link_status']); echo $it['link_message'] && LinkRules::isBad($it['link_status']) ? ' <span class="text-muted">' . asg_e($it['link_message']) . '</span>' : ''; endif; ?></div>
                    <?php if ($isFile): ?><input type="file" name="file_<?php echo (int) $d['id']; ?>" class="form-control form-control-sm mt-1" accept="<?php echo $d['kind'] === 'slides' ? '.pdf,.pptx' : '.pdf'; ?>">
                        <?php if ($it && $it['file_path']): ?><div class="small mt-1"><i class="fas fa-file-pdf me-1"></i><?php echo asg_e($it['file_name']); ?>
                            <button type="submit" name="action" value="remove_item" formnovalidate class="btn btn-link btn-sm p-0 text-danger ms-2" onclick="document.getElementById('removeItem').value=<?php echo (int) $it['id']; ?>">remove</button></div><?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <input type="hidden" name="item" id="removeItem" value="0">
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="own_work" value="1" id="own" <?php echo $ownTick ? 'checked' : ''; ?>>
                <label class="form-check-label" for="own"><strong>This is my own work.</strong><?php echo $teamNames ? ' (Every team member ticks this on their own page.)' : ''; ?></label></div>
            <label class="form-label small fw-semibold mb-1">Did you use AI? <span class="text-muted fw-normal">(optional, one sentence)</span></label>
            <input name="ai_note" class="form-control form-control-sm mb-3" maxlength="500" value="<?php echo asg_e($ownNote); ?>" placeholder="For example: I asked an AI to explain an error message.">
            <?php if ($problems && $draft): ?><div class="alert alert-light border small py-2">Before you can send: <?php echo asg_e(implode(' · ', $problems)); ?></div><?php endif; ?>
            <?php if ((int) $owner['returns_used'] === 0 && !$owner['confirmed_final_rules_at']): ?>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_rules" value="1" id="conf">
                    <label class="form-check-label small" for="conf"><strong>Once you send, you can only improve your work if your tutor returns it, and only twice.</strong> I understand.</label></div>
            <?php elseif ((int) $owner['returns_used'] >= CAP_MAX_RETURNS): ?><div class="alert alert-warning small py-2">This is your last send. After this, the mark is final.</div><?php endif; ?>
            <div class="cap-sticky d-flex gap-2"><button class="btn btn-outline-dark flex-fill" name="action" value="save_draft">Save draft</button>
                <button class="btn btn-brand flex-fill" name="action" value="submit" onclick="return confirm('Send your capstone to your tutor now?')"><i class="fas fa-paper-plane me-1"></i><?php echo $state === 'returned' || $state === 'final_try' ? 'Improve and send again' : 'Submit capstone'; ?></button></div>
        </form>
        <?php else: ?>
            <?php foreach ($items as $it): ?>
                <div class="border-bottom py-2 small"><strong><?php echo asg_e($it['label']); ?></strong><br>
                    <?php if ($it['url']): ?><a href="<?php echo asg_e($it['url']); ?>" target="_blank" rel="noopener noreferrer" class="text-break"><?php echo asg_e($it['url']); ?></a> <?php echo cap_link_chip($it['link_status']); ?>
                    <?php elseif ($it['file_path']): ?><a href="<?php echo asg_e(cap_file_url('item', (int) $it['id'])); ?>" target="_blank"><i class="fas fa-file-pdf me-1"></i><?php echo asg_e($it['file_name']); ?></a><?php endif; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="col-lg-5">
    <div class="card-tutor p-3 p-md-4 mb-3" id="slots">
        <h6 class="fw-bold">Presentation</h6>
        <?php if ($booking): ?>
            <div class="mb-2"><i class="fas fa-calendar-day me-1 text-success"></i><strong><?php echo asg_e(cap_local($booking['starts_at'], 'D j M Y, H:i')); ?> EAT</strong> · <?php echo (int) $booking['minutes']; ?> min<br>
                <span class="small"><?php echo $booking['mode'] === 'online' ? 'Online' : 'In person'; ?><?php echo $booking['place'] ? ': ' . ($booking['mode'] === 'online' && preg_match('#^https://#', $booking['place']) ? '<a href="' . asg_e($booking['place']) . '" target="_blank" rel="noopener noreferrer">meeting link</a>' : asg_e($booking['place'])) : ''; ?></span>
                <?php echo $booking['done_at'] ? ' <span class="asg-chip st-graded"><i class="fas fa-check"></i>Done</span>' : ''; ?></div>
            <?php if (!$booking['done_at'] && $booking['starts_at'] > cap_now() && $canEdit): ?>
                <form method="POST" onsubmit="return confirm('Cancel this slot?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="unbook_slot"><button class="btn btn-outline-danger btn-sm">Cancel slot</button></form>
            <?php endif; ?>
            <a class="btn btn-outline-dark btn-sm mt-2" href="<?php echo asg_e(url('students/classes/index.php')); ?>">See it on my Class Calendar</a>
        <?php elseif ($canEdit): ?>
            <p class="small text-muted">Book a time to present your project to your tutor.</p>
            <?php foreach ($slots as $s): ?>
                <form method="POST" class="d-flex justify-content-between align-items-center border-bottom py-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="book_slot"><input type="hidden" name="slot" value="<?php echo (int) $s['id']; ?>">
                    <div class="small"><strong><?php echo asg_e(cap_local($s['starts_at'], 'D j M, H:i')); ?></strong> · <?php echo (int) $s['minutes']; ?> min · <?php echo $s['mode'] === 'online' ? 'Online' : 'In person'; ?></div>
                    <button class="btn btn-brand btn-sm">Book a slot</button></form>
            <?php endforeach; ?>
            <?php if (!$slots): ?><div class="small text-muted">Your tutor has not added free slots yet. Check again later.</div><?php endif; ?>
        <?php else: ?><div class="small text-muted">No slot booked.</div><?php endif; ?>
    </div>

    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold">History</h6>
        <ul class="cap-hist small mb-0 ps-3">
            <?php foreach ($history as $h): ?>
                <li><strong><?php echo (int) $h['attempt_no'] > 1 ? 'Retake ' . ((int) $h['attempt_no'] - 1) : 'First try'; ?></strong> · returns <?php echo (int) $h['returns_used']; ?> of 2 ·
                    <?php $hs = cap_state($cap, $h); echo $hs === 'marked' && $h['mark'] && !cap_mark_released($h['mark']) ? 'Marked (held)' : cap_e(['not_started' => 'Not started', 'in_progress' => 'In progress', 'behind' => 'In progress', 'to_mark' => 'With your tutor', 'returned' => 'Returned', 'final_try' => 'Returned', 'marked' => 'Marked', 'awaiting_payment' => 'Waiting for the retake fee'][$hs] ?? $hs);
                    echo $h['mark'] && cap_mark_released($h['mark']) ? ' · ' . cap_pretty_marks($h['mark']['total']) . ' / 100 (' . cap_e($h['mark']['result']) . ')' : ''; ?></li>
            <?php endforeach; ?>
            <?php foreach ($returns as $r): ?><li>Returned (<?php echo (int) $r['return_no']; ?> of 2) on <?php echo asg_e(cap_local($r['returned_at'], 'D j M, H:i')); ?></li><?php endforeach; ?>
            <?php foreach (cap_versions($pdo, (int) $owner['id']) as $v): ?><li>You sent version <?php echo (int) $v['version']; ?> on <?php echo asg_e(cap_local($v['submitted_at'], 'D j M, H:i')); ?></li><?php endforeach; ?>
        </ul>
    </div>
</div>
</div>

<script>
(function () {
    var form = document.getElementById('workForm'); if (!form) return;
    var csrf = form.querySelector('input[name=csrf_token]').value, state = document.getElementById('saveState');
    // Check a link as soon as it is typed.
    document.querySelectorAll('.link-in').forEach(function (inp) {
        inp.addEventListener('change', function () {
            var box = document.getElementById('lc' + inp.dataset.del); if (!inp.value.trim()) { box.innerHTML = ''; return; }
            box.innerHTML = '<span class="text-muted">Checking…</span>';
            var fd = new FormData(); fd.append('csrf_token', csrf); fd.append('id', '<?php echo $id; ?>'); fd.append('del', inp.dataset.del); fd.append('url', inp.value);
            fetch('link_check.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
                if (!j.ok) { box.innerHTML = ''; return; }
                box.innerHTML = '<span class="asg-chip ' + (j.bad ? 'lc-bad' : 'st-graded') + '"><i class="fas ' + (j.bad ? 'fa-triangle-exclamation' : 'fa-link') + '"></i>' + (j.bad ? 'Needs access' : 'Link works') + '</span> <span class="text-muted">' + (j.bad ? j.message : '') + '</span>';
            }).catch(function () { box.innerHTML = ''; });
        });
    });
    // Auto-save the draft (links, own-work tick, AI note) a moment after the student stops typing. Files are saved with "Save draft".
    var timer = null;
    function autosave() {
        var fd = new FormData(); fd.append('csrf_token', csrf); fd.append('action', 'save_draft'); fd.append('ajax', '1');
        form.querySelectorAll('.link-in').forEach(function (i) { fd.append('link[' + i.dataset.del + ']', i.value); });
        if (document.getElementById('own').checked) fd.append('own_work', '1'); fd.append('ai_note', form.querySelector('[name=ai_note]').value);
        state.textContent = 'Saving…';
        fetch(location.pathname + location.search, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
            .then(function (j) { state.textContent = j.ok ? 'Draft saved ' + j.saved : (j.errors && j.errors[0] ? j.errors[0] : ''); }).catch(function () { state.textContent = ''; });
    }
    form.addEventListener('input', function (e) { if (e.target.type === 'file') return; clearTimeout(timer); timer = setTimeout(autosave, 1500); });
    document.getElementById('own').addEventListener('change', autosave);
    form.addEventListener('submit', function (e) { var b = e.submitter; if (b && b.value === 'remove_item') return; });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
