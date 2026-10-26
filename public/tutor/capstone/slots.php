<?php
// Tutor: presentation slots for a capstone. Each slot is also a class on the Class Calendar; a student books one from the capstone page.
require __DIR__ . '/../../components/capstone/tutor_boot.php';

use Models\AssignmentModel;

$id = (int) ($_GET['id'] ?? 0);
$cap = cap_get_for_tutor($pdo, $id, $tutorId);
if (!$cap) asg_back('index.php', null, 'Capstone not found.');
$self = 'slots.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back($self);
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $made = 0;
        $err = null;
        $start = AssignmentModel::toUtc((string) ($_POST['start'] ?? ''));
        if (!$start) asg_back($self, null, 'Choose the date and time of the first slot.');
        $count = max(1, min(20, (int) ($_POST['count'] ?? 1)));
        $minutes = max(5, min(240, (int) ($_POST['minutes'] ?? 20)));
        $gap = max(0, min(60, (int) ($_POST['gap'] ?? 5)));
        for ($i = 0; $i < $count; $i++) {
            $at = gmdate('Y-m-d H:i:s', strtotime($start . ' UTC') + $i * ($minutes + $gap) * 60);
            $r = cap_slot_create($pdo, $cap, $me, $at, $minutes, (string) ($_POST['mode'] ?? 'online'), (string) ($_POST['place'] ?? ''), (int) ($_POST['capacity'] ?? 1), $me);
            if ($r['ok']) $made++; else { $err = $r['error']; break; }
        }
        asg_back($self, $made ? $made . ' slot' . ($made === 1 ? '' : 's') . ' added. They are on your Class Calendar.' : null, $made ? null : $err);
    }
    if ($action === 'cancel') {
        asg_back($self, cap_slot_cancel($pdo, $cap, (int) ($_POST['slot'] ?? 0), $me) ? 'Slot removed. Anyone who booked it must book again.' : null, 'Slot not found.');
    }
    asg_back($self);
}

$slots = cap_slots($pdo, $id);
$bookings = [];
foreach ($slots as $s) {
    $st = $pdo->prepare('SELECT b.*, a.student_user_id FROM capstone_slot_bookings b JOIN capstone_attempts a ON a.id = b.attempt_id WHERE b.slot_id = ? AND b.cancelled_at IS NULL');
    $st->execute([(int) $s['id']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) {
        $names = [];
        foreach (cap_group_attempts($pdo, cap_attempt($pdo, (int) $b['attempt_id'])) as $g) $names[] = $am->student((int) $g['student_user_id'])['full_name'];
        $bookings[(int) $s['id']][] = $b + ['names' => implode(' & ', $names)];
    }
}
$page_title = 'Presentation slots';
ob_start();
?>
<a href="track.php?id=<?php echo $id; ?>" class="btn btn-link btn-sm ps-0 mb-2">← Track: <?php echo asg_e($cap['title']); ?></a>
<h4 class="mb-1">Presentation slots</h4>
<div class="text-muted small mb-3">Students book one slot from their capstone page once the capstone is open. Each slot also appears on your <a href="../calendar/index.php">Class Calendar</a>, and on the student's calendar once they book it.</div>
<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" class="card-tutor p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="create">
            <h6 class="fw-bold">Add slots</h6>
            <label class="form-label small fw-semibold">First slot starts (EAT)</label><input type="datetime-local" name="start" class="form-control form-control-sm" required>
            <div class="row g-2 mt-1">
                <div class="col-4"><label class="form-label small fw-semibold">How many</label><input type="number" name="count" min="1" max="20" value="1" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="form-label small fw-semibold">Minutes each</label><input type="number" name="minutes" min="5" max="240" value="20" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="form-label small fw-semibold">Gap (min)</label><input type="number" name="gap" min="0" max="60" value="5" class="form-control form-control-sm"></div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-6"><label class="form-label small fw-semibold">How</label><select name="mode" class="form-select form-select-sm"><option value="online">Online</option><option value="in_person">In person</option></select></div>
                <div class="col-6"><label class="form-label small fw-semibold">Students per slot</label><input type="number" name="capacity" min="1" max="50" value="1" class="form-control form-control-sm"></div>
            </div>
            <label class="form-label small fw-semibold mt-1">Meeting link (https://…) or room</label><input name="place" class="form-control form-control-sm" placeholder="https://meet.google.com/… or Room 529">
            <button class="btn btn-brand btn-sm mt-3">Add to the calendar</button>
        </form>
    </div>
    <div class="col-lg-7">
        <div class="card-tutor p-3">
            <h6 class="fw-bold">Slots (<?php echo count($slots); ?>)</h6>
            <?php if (!$slots): ?><div class="small text-muted">No slots yet.</div><?php endif; ?>
            <?php foreach ($slots as $s): ?>
                <div class="d-flex flex-wrap justify-content-between gap-2 border-bottom py-2">
                    <div><div class="fw-semibold"><?php echo asg_e(cap_local($s['starts_at'], 'D j M Y, H:i')); ?> EAT <span class="small text-muted">· <?php echo (int) $s['minutes']; ?> min · <?php echo $s['mode'] === 'online' ? 'Online' : 'In person'; ?><?php echo $s['place'] ? ' · ' . asg_e($s['place']) : ''; ?></span></div>
                        <?php foreach ($bookings[(int) $s['id']] ?? [] as $b): ?><div class="small"><i class="fas fa-user-check text-success me-1"></i><?php echo asg_e($b['names']); ?><?php echo $b['done_at'] ? ' · Done' : ''; ?></div><?php endforeach; ?>
                        <?php if (empty($bookings[(int) $s['id']])): ?><div class="small text-muted">Free (<?php echo (int) $s['capacity']; ?> place<?php echo (int) $s['capacity'] === 1 ? '' : 's'; ?>)</div><?php endif; ?></div>
                    <form method="POST" onsubmit="return confirm('Remove this slot?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="slot" value="<?php echo (int) $s['id']; ?>"><button class="btn btn-outline-danger btn-sm">Remove</button></form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
