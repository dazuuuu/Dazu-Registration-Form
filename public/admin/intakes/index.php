<?php
// Admin: Intakes. One set of intakes for all courses: create, edit, extend, close now, reopen, rules, audit log.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/services/IntakeService.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/intakes/index.php');
$page_title = 'Intakes';
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

if (!ik_installed($db)) {
    $content = '<div class="alert alert-warning">Intake Booking is not installed yet. Run <a href="' . $e(url('admin/system/index.php')) . '">System Updates</a> (migration 079).</div>';
    require __DIR__ . '/../../templates/admin/layout.php';
    exit;
}

$back = function (string $q = '') use ($self): void { header('Location: ' . $self . $q); exit; };
$formError = null;
$formWarnings = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
        $back();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $intake = $id ? ik_intake($db, $id) : null;
    $reason = trim((string) ($_POST['reason'] ?? ''));
    switch ($_POST['action'] ?? '') {
        case 'save':
            $r = ik_save_intake($db, $_POST, $intake);
            if ($r['ok']) {
                $_SESSION['success_msg'] = ($intake ? 'Intake saved.' : 'Intake created.') . ' Course pages changed at once.';
                $back('?id=' . $r['id']);
            }
            $formError = $r['error'];
            $formWarnings = $r['warnings'] ?? [];
            break;
        case 'extend':
            if (!$intake) $back();
            $r = ik_extend($db, $intake, (string) ($_POST['how'] ?? ''), (string) ($_POST['custom'] ?? ''), $reason);
            $_SESSION[$r['ok'] ? 'success_msg' : 'error_msg'] = $r['ok']
                ? 'Enrolment extended to ' . ik_fmt($db, $r['closes'], 'j M Y, g:i A') . ' ' . ik_tz_label($db) . '. Course pages show Enrol and Book again.' : $r['error'];
            $back('?id=' . $id);
        case 'close':
        case 'reopen':
            if (!$intake) $back();
            $isClose = $_POST['action'] === 'close';
            $r = ik_set_override($db, $intake, $isClose ? 'closed' : 'auto', $reason, $isClose ? 'closed_now' : 'reopened');
            $_SESSION[$r['ok'] ? 'success_msg' : 'error_msg'] = $r['ok'] ? ($isClose ? 'Enrolment closed. Course pages show Book only.' : 'Enrolment reopened.') : $r['error'];
            $back('?id=' . $id);
        case 'rules':
            $r = ik_save_rules($db, (int) ($_POST['early_access_hours'] ?? 48), (int) ($_POST['hold_days'] ?? 3), (string) ($_POST['timezone'] ?? 'Africa/Nairobi'));
            $_SESSION[$r['ok'] ? 'success_msg' : 'error_msg'] = $r['ok'] ? 'Rules saved for all intakes.' : $r['error'];
            $back($id ? '?id=' . $id : '');
    }
}

$intakes = ik_intakes($db, true);
$settings = ik_settings($db);
$tz = ik_tz_label($db);
$courses = (new \Models\CourseModel($db))->getAll(true);
$selId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isNew = isset($_GET['new']) || (($_POST['action'] ?? '') === 'save' && !$selId);
$sel = $selId ? ($intakes[$selId] ?? null) : null;
if (!$sel && !$isNew && $intakes) {
    foreach ($intakes as $i) if (in_array($i['status'], ['enrol_open', 'early_access', 'booking_open'], true)) { $sel = $i; break; }
    $sel = $sel ?: end($intakes);
}
$f = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') ? $_POST : null;   // keep typed values after an error
$val = function (string $k, bool $dt = true) use ($f, $sel, $isNew, $db) {
    if ($f !== null) return (string) ($f[$k] ?? '');
    if ($isNew || !$sel) return '';
    return $dt ? ik_input_value($db, $sel[$k]) : (string) $sel[$k];
};
$openNow = array_filter($intakes, fn($i) => $i['status'] === 'enrol_open');
$pill = ['upcoming' => 'bg-secondary', 'booking_open' => 'bg-primary', 'early_access' => 'bg-warning text-dark', 'enrol_open' => 'bg-success', 'closed' => 'bg-danger'];   // Bootstrap 5.1
$seatsText = function (array $i) use ($db) {
    $st = $db->prepare("SELECT COUNT(*) FROM intake_bookings WHERE intake_id = ? AND status IN ('booked','contacted','invited','converted')");
    $st->execute([(int) $i['id']]);
    $n = (int) $st->fetchColumn();
    if ($i['status'] === 'upcoming' && !$n) return 'Not open yet';
    return $n . ($i['seats_default'] ? ' of ' . (int) $i['seats_default'] . ' per course' : '') . ' booked';
};
$log = $db->query('SELECT l.*, i.name AS intake_name, u.username FROM intake_log l LEFT JOIN intakes i ON i.id = l.intake_id LEFT JOIN users u ON u.id = l.admin_id ORDER BY l.id DESC LIMIT 40')->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<style>
.ik-card{background:#fff;border:1px solid #e7e9e4;border-radius:14px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.ik-card .hd{padding:14px 18px;border-bottom:1px solid #eef0ec;font-weight:700}
.ik-table th{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#7b8078;font-weight:700;border-bottom-width:1px}
.ik-table td{vertical-align:middle;font-size:.88rem}
.ik-table tr.on td{background:#f3faef}
.ik-banner{border-radius:10px;padding:10px 14px;font-weight:600;font-size:.9rem}
.ik-chip{border:1px solid #d9dcd5;background:#f6f7f4;border-radius:999px;padding:5px 12px;font-size:.8rem;font-weight:600;cursor:pointer}
.ik-chip.on{background:#44A024;border-color:#44A024;color:#fff}
.ik-rule{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f0f1ee;font-size:.88rem}
.ik-rule:last-child{border:0}
.ik-preview{background:#eef8e9;border:1px solid #cfe9c2;color:#2f7a14;border-radius:10px;padding:9px 12px;font-size:.85rem}
</style>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div><h1 class="h4 mb-0">Intakes</h1><div class="text-muted small">One set of intakes for all courses. All times are <?php echo $e($tz); ?>.</div></div>
    <div class="d-flex gap-2">
        <a href="<?php echo $e(url('admin/intakes/bookings.php')); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-bookmark me-1"></i>Intake Bookings</a>
        <a href="<?php echo $e($self . '?new=1'); ?>" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>New intake</a>
    </div>
</div>

<?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $k => $c): if (!empty($_SESSION[$k])): ?>
    <div class="alert alert-<?php echo $c; ?> py-2"><?php echo $e($_SESSION[$k]); unset($_SESSION[$k]); ?></div>
<?php endif; endforeach; ?>

<?php if (!$intakes): ?>
    <div class="ik-banner mb-3" style="background:#f1f3ef;color:#4b5048"><i class="fas fa-circle-info me-1"></i>No intakes yet: the website works exactly as before (Enrol always open, no booking). Create the first intake to switch Intake Booking on.</div>
<?php elseif ($openNow): ?>
    <div class="ik-banner mb-3" style="background:#eaf6e4;color:#2f7a14"><i class="fas fa-lock-open me-1"></i>Right now: enrolment is <strong>OPEN</strong> (<?php echo $e(implode(', ', array_column($openNow, 'name'))); ?>). Course pages show Enrol and Book.</div>
<?php else: ?>
    <div class="ik-banner mb-3" style="background:#fdf3dc;color:#8a5a00"><i class="fas fa-lock me-1"></i>Right now: enrolment is <strong>CLOSED</strong>. Course pages show Book only.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="ik-card mb-3">
            <div class="table-responsive"><table class="table ik-table mb-0">
                <thead><tr><th class="ps-3">Intake</th><th>Status</th><th>Key dates</th><th>Seats</th><th class="text-end pe-3"></th></tr></thead>
                <tbody>
                <?php if (!$intakes): ?><tr><td colspan="5" class="text-center text-muted py-4">No intakes yet.</td></tr><?php endif; ?>
                <?php foreach ($intakes as $i): $on = $sel && (int) $sel['id'] === (int) $i['id']; ?>
                    <tr class="<?php echo $on ? 'on' : ''; ?>">
                        <td class="ps-3"><div class="fw-semibold"><?php echo $e($i['name']); ?></div><div class="small text-muted"><?php
                            $ex = (int) $db->query('SELECT COUNT(*) FROM intake_course_seats WHERE excluded = 1 AND intake_id = ' . (int) $i['id'])->fetchColumn();
                            echo $ex ? 'All courses except ' . $ex : 'Applies to all courses'; ?></div></td>
                        <td><span class="badge rounded-pill <?php echo $pill[$i['status']]; ?>"><?php echo IK_INTAKE_LABELS[$i['status']]; ?></span>
                            <?php if ($i['override_mode'] !== 'auto'): ?><div class="small text-danger mt-1">Forced <?php echo $e($i['override_mode']); ?></div><?php endif; ?></td>
                        <td class="small">
                            <?php if ($i['status'] === 'upcoming'): ?>Bookings open <strong><?php echo $e(ik_fmt($db, ik_ts($i['bookings_open_at']), 'j M Y')); ?></strong><br><?php endif; ?>
                            <?php if (in_array($i['status'], ['upcoming', 'booking_open', 'early_access'], true)): ?>Early access <strong><?php echo $e(ik_fmt($db, ik_early_ts($db, $i), 'j M, g:i A')); ?></strong><br>Enrolment opens <strong><?php echo $e(ik_fmt($db, ik_ts($i['enrol_opens_at']), 'j M Y')); ?></strong><br><?php endif; ?>
                            <?php if (in_array($i['status'], ['enrol_open', 'closed'], true)): ?>Enrolment <?php echo $i['status'] === 'closed' ? 'closed' : 'closes'; ?> <strong><?php echo $e(ik_fmt($db, ik_ts($i['enrol_closes_at']), 'j M Y')); ?></strong><br><?php endif; ?>
                            <?php if ($i['classes_start_on']): ?>Classes start <?php echo $e(ik_intake_start($db, $i)); ?><?php endif; ?>
                        </td>
                        <td class="small fw-semibold"><?php echo $e($seatsText($i)); ?></td>
                        <td class="text-end pe-3 text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($self . '?id=' . (int) $i['id'] . '#extend'); ?>">Extend</a>
                            <a class="btn btn-sm <?php echo $on ? 'btn-success' : 'btn-outline-secondary'; ?>" href="<?php echo $e($self . '?id=' . (int) $i['id']); ?>">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>

        <div class="ik-card">
            <div class="hd"><i class="fas fa-clock-rotate-left me-1 text-success"></i>Audit log</div>
            <div class="table-responsive" style="max-height:360px"><table class="table table-sm ik-table mb-0">
                <thead><tr><th class="ps-3">When</th><th>Who</th><th>What</th><th>Change</th><th>Reason</th></tr></thead>
                <tbody>
                <?php if (!$log): ?><tr><td colspan="5" class="text-center text-muted py-3">No changes yet.</td></tr><?php endif; ?>
                <?php foreach ($log as $l):
                    $fmtv = function ($json) use ($db) {
                        $v = json_decode((string) $json, true);
                        if (!is_array($v)) return (string) $json;
                        $out = [];
                        // Keep it short: the dates, seats and override (other fields are in the edit form).
                        $keep = ['name', 'bookings_open_at', 'early_access_at', 'enrol_opens_at', 'enrol_closes_at', 'classes_start_on', 'seats_default', 'override_mode', 'early_access_hours', 'hold_days', 'timezone'];
                        foreach ($v as $k => $x) {
                            if (!in_array($k, $keep, true) || $x === null || $x === '') continue;
                            if (is_string($x) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $x)) $x = ik_fmt($db, ik_ts($x), 'j M Y, g:i A');
                            $out[] = str_replace(['_at', '_on', '_'], ['', '', ' '], $k) . ': ' . $x;
                        }
                        return implode('; ', $out);
                    }; ?>
                    <tr><td class="ps-3 text-nowrap small"><?php echo $e(ik_fmt($db, ik_db_ts($db, $l['created_at']), 'j M, g:i A')); ?></td>
                        <td class="small"><?php echo $e($l['username'] ?? '—'); ?></td>
                        <td class="small"><strong><?php echo $e(str_replace('_', ' ', $l['action'])); ?></strong><div class="text-muted"><?php echo $e($l['intake_name'] ?? 'All intakes'); ?></div></td>
                        <td class="small" style="max-width:260px"><?php if ($l['old_value']): ?><span class="text-muted">Was:</span> <?php echo $e($fmtv($l['old_value'])); ?><br><?php endif; ?><span class="text-muted">Now:</span> <?php echo $e($fmtv($l['new_value'])); ?></td>
                        <td class="small"><?php echo $e($l['reason'] ?? ''); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="col-xl-5">
        <?php if ($sel && !$isNew): $st = $sel['status']; ?>
        <div class="ik-card mb-3" id="extend">
            <div class="p-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div><div class="fw-bold"><?php echo $e($sel['name']); ?> intake</div><div class="small text-muted">Closing enrolment ends the intake. Course pages switch to Book only.</div></div>
                    <span class="badge rounded-pill <?php echo $pill[$st]; ?>"><?php echo IK_INTAKE_LABELS[$st]; ?></span>
                </div>
                <div class="small mt-2">Enrolment closes <strong><?php echo $e(ik_fmt($db, ik_ts($sel['enrol_closes_at']), 'j M Y, g:i A') . ' ' . $tz); ?></strong></div>
                <form method="POST" class="mt-3" id="ikExtendForm">
                    <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $sel['id']; ?>"><input type="hidden" name="action" value="extend">
                    <input type="hidden" name="how" id="ikHow" value="month">
                    <label class="form-label small fw-semibold mb-1">Extend enrolment</label>
                    <div class="d-flex gap-2 flex-wrap mb-2">
                        <span class="ik-chip" data-how="week">+1 week</span><span class="ik-chip on" data-how="month">+1 month</span><span class="ik-chip" data-how="custom">Custom date</span>
                    </div>
                    <input type="datetime-local" name="custom" id="ikCustom" class="form-control form-control-sm mb-2 d-none">
                    <div class="ik-preview mb-2" id="ikPreview"></div>
                    <label class="form-label small fw-semibold mb-1">Reason (saved in the log)</label>
                    <input type="text" name="reason" class="form-control form-control-sm mb-2" maxlength="255" required placeholder="e.g. Many students asked for more time">
                    <div class="d-flex gap-2">
                        <?php if ($sel['override_mode'] === 'closed' || $st === 'closed'): ?>
                            <button class="btn btn-outline-secondary btn-sm flex-fill" formaction="<?php echo $e($self); ?>" name="action" value="reopen" onclick="return this.form.reason.value.trim() !== '' || (alert('Give a reason first.'), false)">Reopen</button>
                        <?php else: ?>
                            <button class="btn btn-outline-danger btn-sm flex-fill" name="action" value="close" onclick="return this.form.reason.value.trim() === '' ? (alert('Give a reason first.'), false) : confirm('Close enrolment now? Course pages will show Book only.')">Close now</button>
                        <?php endif; ?>
                        <button class="btn btn-success btn-sm flex-fill" id="ikExtendBtn" name="action" value="extend">Extend by 1 month</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="ik-card mb-3">
            <div class="hd"><?php echo $isNew || !$sel ? '<i class="fas fa-plus me-1 text-success"></i>New intake' : '<i class="fas fa-pen me-1 text-success"></i>Edit ' . $e($sel['name']); ?></div>
            <form method="POST" class="p-3">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo $isNew || !$sel ? 0 : (int) $sel['id']; ?>">
                <?php if ($formError): ?><div class="alert alert-danger py-2 small"><?php echo $e($formError); ?>
                    <?php if ($formWarnings): ?><ul class="mb-1 mt-1"><?php foreach ($formWarnings as $w): ?><li><?php echo $e($w); ?></li><?php endforeach; ?></ul>
                    <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="confirm_warnings" value="1"> Save anyway</label><?php endif; ?></div><?php endif; ?>
                <div class="row g-2">
                    <div class="col-8"><label class="form-label small fw-semibold mb-1">Name</label><input name="name" class="form-control form-control-sm" required maxlength="100" placeholder="January 2027" value="<?php echo $e($val('name', false)); ?>"></div>
                    <div class="col-4"><label class="form-label small fw-semibold mb-1">Short label</label><input name="short_label" class="form-control form-control-sm" maxlength="30" placeholder="Jan 2027" value="<?php echo $e($val('short_label', false)); ?>"></div>
                    <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Bookings open</label><input type="datetime-local" name="bookings_open_at" class="form-control form-control-sm" required value="<?php echo $e($val('bookings_open_at')); ?>"></div>
                    <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Early access starts <span class="text-muted fw-normal">(blank = <?php echo (int) $settings['early_access_hours']; ?> h before)</span></label><input type="datetime-local" name="early_access_at" class="form-control form-control-sm" value="<?php echo $e($val('early_access_at')); ?>"></div>
                    <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Enrolment opens</label><input type="datetime-local" name="enrol_opens_at" class="form-control form-control-sm" required value="<?php echo $e($val('enrol_opens_at')); ?>"></div>
                    <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Enrolment closes</label><input type="datetime-local" name="enrol_closes_at" class="form-control form-control-sm" required value="<?php echo $e($val('enrol_closes_at')); ?>"><div class="form-text">e.g. 31 Oct, 11:59 PM — counts until 11:59:59 PM.</div></div>
                    <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Classes start</label><input type="date" name="classes_start_on" class="form-control form-control-sm" value="<?php echo $e($val('classes_start_on', false)); ?>"></div>
                    <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Seats per course <span class="text-muted fw-normal">(blank = no limit)</span></label><input type="number" min="1" name="seats_default" class="form-control form-control-sm" value="<?php echo $e($val('seats_default', false)); ?>"></div>
                    <div class="col-12"><label class="form-label small fw-semibold mb-1">Override <span class="text-muted fw-normal">(emergencies only)</span></label>
                        <select name="override_mode" class="form-select form-select-sm"><?php $ov = $val('override_mode', false) ?: 'auto'; foreach (['auto' => 'Automatic (by the dates)', 'open' => 'Force open', 'closed' => 'Force closed'] as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $ov === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
                    <div class="col-12">
                        <details><summary class="small fw-semibold text-success" style="cursor:pointer">Courses: different seats or exclude a course</summary>
                            <div class="table-responsive mt-2" style="max-height:240px"><table class="table table-sm small mb-0"><thead><tr><th>Course</th><th style="width:90px">Seats</th><th style="width:70px">Exclude</th></tr></thead><tbody>
                            <?php foreach ($courses as $c): $cid = (int) $c['id']; $rule = $sel && !$isNew ? ik_course_rule($db, (int) $sel['id'], $cid) : null;
                                $cs = $f !== null ? ($f['course_seats'][$cid] ?? '') : ($rule['seats'] ?? ''); $cx = $f !== null ? !empty($f['course_excluded'][$cid]) : !empty($rule['excluded']); ?>
                                <tr><td><?php echo $e($c['title']); ?></td><td><input type="number" min="0" name="course_seats[<?php echo $cid; ?>]" class="form-control form-control-sm" value="<?php echo $e($cs); ?>" placeholder="default"></td>
                                    <td class="text-center"><input type="checkbox" class="form-check-input" name="course_excluded[<?php echo $cid; ?>]" value="1" <?php echo $cx ? 'checked' : ''; ?>></td></tr>
                            <?php endforeach; ?>
                            </tbody></table></div>
                        </details>
                    </div>
                    <div class="col-12"><label class="form-label small fw-semibold mb-1">Reason <span class="text-muted fw-normal">(optional, saved in the log)</span></label><input name="reason" class="form-control form-control-sm" maxlength="255"></div>
                </div>
                <button class="btn btn-success btn-sm mt-3 w-100"><i class="fas fa-save me-1"></i><?php echo $isNew || !$sel ? 'Create intake' : 'Save changes'; ?></button>
            </form>
        </div>

        <div class="ik-card">
            <div class="hd">Rules for all intakes</div>
            <form method="POST" class="p-3">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="rules"><input type="hidden" name="id" value="<?php echo (int) ($sel['id'] ?? 0); ?>">
                <div class="ik-rule"><span>Early access for people who booked</span><span><input type="number" name="early_access_hours" min="1" max="720" value="<?php echo (int) $settings['early_access_hours']; ?>" class="form-control form-control-sm d-inline-block text-end" style="width:80px"> hours</span></div>
                <div class="ik-rule"><span>Seat held after the invite</span><span><input type="number" name="hold_days" min="1" max="60" value="<?php echo (int) $settings['hold_days']; ?>" class="form-control form-control-sm d-inline-block text-end" style="width:80px"> days</span></div>
                <div class="ik-rule"><span>Time zone for all dates</span><select name="timezone" class="form-select form-select-sm" style="width:auto"><?php foreach (['Africa/Nairobi' => 'East Africa Time', 'Africa/Kampala' => 'Kampala (EAT)', 'Africa/Dar_es_Salaam' => 'Dar es Salaam (EAT)', 'UTC' => 'UTC'] as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $settings['timezone'] === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
                <div class="small text-muted mt-2">The hold must be longer than early access. Override is set per intake, in its edit form.</div>
                <button class="btn btn-outline-success btn-sm mt-2">Save rules</button>
            </form>
        </div>
    </div>
</div>

<?php if ($sel && !$isNew): ?>
<script>
(function () {
    var closes = <?php echo (int) ik_ts($sel['enrol_closes_at']); ?> * 1000, how = document.getElementById('ikHow'), custom = document.getElementById('ikCustom'),
        prev = document.getElementById('ikPreview'), btn = document.getElementById('ikExtendBtn');
    var fmt = function (d) { return d.toLocaleString('en-GB', { timeZone: <?php echo json_encode($settings['timezone']); ?>, day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }); };
    function show() {
        var d = null, h = how.value;
        if (h === 'week') d = new Date(closes + 7 * 864e5);
        else if (h === 'month') { d = new Date(closes); d.setMonth(d.getMonth() + 1); }
        else if (custom.value) d = new Date(custom.value);   // shown roughly; the server uses <?php echo $e($tz); ?>
        custom.classList.toggle('d-none', h !== 'custom');
        btn.textContent = h === 'week' ? 'Extend by 1 week' : h === 'month' ? 'Extend by 1 month' : 'Extend to this date';
        prev.innerHTML = d ? 'New closing date: <strong>' + fmt(d) + ' <?php echo $e($tz); ?></strong>. Course pages show Enrol and Book again.' : 'Choose the new closing date.';
    }
    document.querySelectorAll('.ik-chip').forEach(function (c) { c.addEventListener('click', function () {
        document.querySelectorAll('.ik-chip').forEach(function (x) { x.classList.remove('on'); }); c.classList.add('on'); how.value = c.dataset.how; show(); }); });
    custom.addEventListener('input', show);
    show();
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
