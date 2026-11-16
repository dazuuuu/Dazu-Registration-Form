<?php
// Admin: add a secretary (login, automatic ID number, switches, temporary password by email), or edit one with ?id=<secretary id> (the same form with the saved values).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryService.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryAdminService.php';

use Models\Connection;

secretary_admin_require();   // the normal admin only: everyone else, the super admin included, gets a plain 403

$pdo = Connection::getInstance()->getConnection();
$service = new SecretaryService($pdo);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$defs = secretary_switch_defs();
$addedBy = (int) ($_SESSION['user_id'] ?? 0);
$addedByName = secretary_person_name($pdo, $addedBy);   // the name only, never the role
$errors = [];
$admin = new SecretaryAdminService($pdo);
$editId = (int) ($_GET['id'] ?? 0);
$row = $editId > 0 ? $admin->find($editId) : null;
if ($editId > 0 && !$row) { http_response_code(404); exit('Secretary not found.'); }
$in = $_POST ?: ($row ? [
    'first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'email' => $row['email'], 'phone' => $row['phone'], 'branch' => $row['branch'], 'job_title' => $row['job_title'],
    'birth_day' => $row['birth_day'], 'birth_month' => $row['birth_month'], 'birth_year' => $row['birth_year'], 'start_date' => $row['start_date'],
    'emergency_name' => $row['emergency_name'], 'emergency_phone' => $row['emergency_phone'], 'status' => $row['status'], 'sw' => $row['switches'],
] : ['branch' => 'nairobi', 'job_title' => 'Secretary']);
if ($row) $addedByName = $row['added_by_name'];   // who added her, the name only

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'The security check failed. Please reload the page and try again.';
    } else {
        [$data, $errors] = $service->validate($_POST, $row ? (int) $row['user_id'] : null);
        $photoPath = null;
        if (!$errors && !empty($_FILES['photo']['name'])) {
            $up = upload_image($_FILES['photo'], __DIR__ . '/../../uploads/secretaries', url('uploads/secretaries'));
            if (empty($up['ok'])) $errors[] = $up['error'] ?? 'The photo could not be saved.';
            else $photoPath = $up['path'];
        }
        if (!$errors && $row) {
            $status = (string) ($_POST['status'] ?? $row['status']);
            if (!isset(secretary_statuses()[$status])) $errors[] = 'Choose Active, On leave or Left.';
            else {
                $res = $admin->update((int) $row['id'], $data, $photoPath, $status, $addedBy);
                if (!empty($res['ok'])) { SessionModel::setFlash('success', 'Changes saved.'); header('Location: index.php'); exit(); }
                $errors[] = $res['error'];
            }
        } elseif (!$errors) {
            $res = $service->create($data, $photoPath, $addedBy);
            if (!empty($res['ok'])) {
                $msg = 'Secretary created. Her ID number is ' . $h($res['staff_no']) . '.';
                if (!empty($res['email_sent'])) $msg .= ' Her temporary password was emailed to her.';
                else $msg .= ' Warning: the email was not sent (' . $h($res['email_error'] ?? 'unknown reason') . '). Use "Send login again" on the list.';
                SessionModel::setFlash(!empty($res['email_sent']) ? 'success' : 'warning', $msg);
                header('Location: index.php');
                exit();
            }
            $errors[] = $res['error'];
        }
    }
}

$months = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$val = static fn(string $k, string $d = '') => (string) ($GLOBALS['in'][$k] ?? $d);

$page_title = $row ? 'Edit secretary' : 'Add secretary';
$breadcrumbs = [['label' => 'Secretaries', 'url' => 'index.php'], ['label' => $page_title, 'active' => true]];
$extra_css = <<<'CSS'
<style>
.sec-form { --g:#048C44; --gd:#037A3B; --gl:#E3F3EA; max-width: 980px; }
.sec-card { background:#fff; border:1px solid #d9e6de; border-radius:16px; padding:20px; margin-bottom:18px; }
.sec-card h2 { font-size:1rem; font-weight:800; color:var(--gd); margin:0 0 14px; }
.sec-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
.sec-grid .full { grid-column:1 / -1; }
.sec-form label.lbl { display:block; font-size:.78rem; font-weight:700; color:#374151; margin-bottom:5px; }
.sec-form .form-control, .sec-form .form-select { min-height:44px; border-radius:10px; }
.sec-form .form-control:focus, .sec-form .form-select:focus { border-color:var(--g); box-shadow:0 0 0 .2rem rgba(4,140,68,.18); }
.sec-form .locked { background:var(--gl); color:#1f2937; font-weight:600; }
.sec-dob { display:grid; grid-template-columns:90px 1fr 110px; gap:8px; }
.sw-list { display:grid; gap:10px; }
.sw-row { display:flex; align-items:center; justify-content:space-between; gap:14px; min-height:60px; padding:10px 14px; border:1px solid #d9e6de; border-radius:12px; background:#fff; cursor:pointer; }
.sw-row:has(input:checked) { background:var(--gl); border-color:var(--g); }
.sw-row .t { font-weight:700; color:#111827; }
.sw-row .d { font-size:.8rem; color:#6b7280; }
.sw-row input[type=checkbox] { appearance:none; -webkit-appearance:none; flex:0 0 auto; width:56px; height:32px; border-radius:16px; background:#cbd5d0; position:relative; cursor:pointer; transition:background .15s; margin:0; }
.sw-row input[type=checkbox]::after { content:''; position:absolute; top:4px; left:4px; width:24px; height:24px; border-radius:50%; background:#fff; transition:transform .15s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
.sw-row input[type=checkbox]:checked { background:var(--g); }
.sw-row input[type=checkbox]:checked::after { transform:translateX(24px); }
.sw-row input[type=checkbox]:focus-visible, .sw-seg input:focus-visible + span { outline:3px solid rgba(4,140,68,.4); outline-offset:2px; }
.sw-choice { padding:10px 14px; border:1px solid #d9e6de; border-radius:12px; background:#fff; }
.sw-choice:has(input[value=view]:checked), .sw-choice:has(input[value=edit]:checked) { background:var(--gl); border-color:var(--g); }
.sw-seg { display:flex; gap:8px; margin-top:8px; flex-wrap:wrap; }
.sw-seg label { flex:1 1 90px; margin:0; cursor:pointer; }
.sw-seg input { position:absolute; opacity:0; pointer-events:none; }
.sw-seg span { display:flex; align-items:center; justify-content:center; min-height:44px; padding:6px 10px; border:1px solid #cbd5d0; border-radius:10px; font-weight:600; font-size:.88rem; background:#fff; text-align:center; }
.sw-seg input:checked + span { background:var(--g); border-color:var(--g); color:#fff; }
.sec-btn { background:var(--g); border:0; color:#fff; font-weight:800; border-radius:12px; padding:.8rem 1.6rem; min-height:48px; }
.sec-btn:hover { background:var(--gd); color:#fff; }
@media (max-width: 767.98px) {
  .sec-grid { grid-template-columns:1fr; }
  .sec-card { padding:16px; }
  .sec-form .actions { display:flex; flex-direction:column; gap:10px; }
  .sec-btn, .sec-form .actions .btn { width:100%; }
}
</style>
CSS;
ob_start();
?>
<div class="sec-form">
<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert"><strong>Please fix this:</strong>
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo $h($e); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<form method="POST" enctype="multipart/form-data" autocomplete="off" novalidate>
    <?php echo csrf_field(); ?>

    <div class="sec-card">
        <h2>Who she is</h2>
        <div class="sec-grid">
            <div><label class="lbl" for="first_name">First name *</label><input class="form-control" id="first_name" name="first_name" maxlength="80" required value="<?php echo $h($val('first_name')); ?>"></div>
            <div><label class="lbl" for="last_name">Last name *</label><input class="form-control" id="last_name" name="last_name" maxlength="80" required value="<?php echo $h($val('last_name')); ?>"></div>
            <div><label class="lbl" for="email">Email (she signs in with it) *</label><input class="form-control" type="email" id="email" name="email" maxlength="150" required value="<?php echo $h($val('email')); ?>"></div>
            <div><label class="lbl" for="phone">Phone *</label><input class="form-control" type="tel" id="phone" name="phone" maxlength="20" required value="<?php echo $h($val('phone')); ?>"></div>
            <div>
                <label class="lbl" for="branch">Branch *</label>
                <select class="form-select" id="branch" name="branch" required>
                    <?php foreach (secretary_branches() as $k => $label): ?>
                        <option value="<?php echo $h($k); ?>" <?php echo $val('branch', 'nairobi') === $k ? 'selected' : ''; ?>><?php echo $h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="lbl" for="job_title">Job title</label><input class="form-control" id="job_title" name="job_title" maxlength="80" value="<?php echo $h($val('job_title', 'Secretary')); ?>"></div>
            <div>
                <label class="lbl" for="birth_day">Date of birth (day and month are enough) *</label>
                <div class="sec-dob">
                    <select class="form-select" id="birth_day" name="birth_day" aria-label="Day" required>
                        <option value="">Day</option>
                        <?php for ($i = 1; $i <= 31; $i++): ?><option value="<?php echo $i; ?>" <?php echo (int) $val('birth_day') === $i ? 'selected' : ''; ?>><?php echo $i; ?></option><?php endfor; ?>
                    </select>
                    <select class="form-select" name="birth_month" aria-label="Month" required>
                        <option value="">Month</option>
                        <?php foreach ($months as $i => $m): ?><option value="<?php echo $i; ?>" <?php echo (int) $val('birth_month') === $i ? 'selected' : ''; ?>><?php echo $h($m); ?></option><?php endforeach; ?>
                    </select>
                    <input class="form-control" name="birth_year" inputmode="numeric" maxlength="4" placeholder="Year" aria-label="Year (optional)" value="<?php echo $h($val('birth_year')); ?>">
                </div>
            </div>
            <div><label class="lbl" for="start_date">Start date *</label><input class="form-control" type="date" id="start_date" name="start_date" required value="<?php echo $h($val('start_date')); ?>"></div>
            <div><label class="lbl" for="emergency_name">Emergency contact name</label><input class="form-control" id="emergency_name" name="emergency_name" maxlength="120" value="<?php echo $h($val('emergency_name')); ?>"></div>
            <div><label class="lbl" for="emergency_phone">Emergency contact phone</label><input class="form-control" type="tel" id="emergency_phone" name="emergency_phone" maxlength="20" value="<?php echo $h($val('emergency_phone')); ?>"></div>
            <div><label class="lbl" for="photo">Photo</label><input class="form-control" type="file" id="photo" name="photo" accept="image/*"></div>
            <div>
                <label class="lbl" for="staff_no">ID number</label>
                <input class="form-control locked" id="staff_no" value="<?php echo $h($row ? $row['staff_no'] : 'Made automatically when you save (DTI/SEC/…)'); ?>" disabled>
            </div>
            <?php if ($row): ?>
            <div>
                <label class="lbl" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <?php foreach (secretary_statuses() as $k => $label): ?><option value="<?php echo $h($k); ?>" <?php echo $val('status', 'active') === $k ? 'selected' : ''; ?>><?php echo $h($label); ?></option><?php endforeach; ?>
                </select>
                <div class="form-text">On leave: she still signs in and uses everything her switches allow. Left: her login is off at once. She is never deleted.</div>
            </div>
            <?php endif; ?>
            <div class="full">
                <label class="lbl" for="added_by">Added by</label>
                <input class="form-control locked" id="added_by" value="<?php echo $h($addedByName); ?>" disabled>
            </div>
        </div>
    </div>

    <div class="sec-card">
        <h2>What she can use</h2>
        <div class="sw-list">
        <?php foreach ($defs as $key => [$label, $help, $opts, $def]):
            $cur = $in['sw'][$key] ?? $def;
            if (!is_string($cur) || !isset($opts[$cur])) $cur = $def;
            if (count($opts) === 2): ?>
            <label class="sw-row">
                <span><span class="t d-block"><?php echo $h($label); ?></span><span class="d"><?php echo $h($help); ?></span></span>
                <input type="hidden" name="sw[<?php echo $h($key); ?>]" value="off">
                <input type="checkbox" role="switch" name="sw[<?php echo $h($key); ?>]" value="on" <?php echo $cur === 'on' ? 'checked' : ''; ?> aria-label="<?php echo $h($label); ?>">
            </label>
            <?php else: ?>
            <div class="sw-choice" role="radiogroup" aria-label="<?php echo $h($label); ?>">
                <span class="t fw-bold d-block"><?php echo $h($label); ?></span><span class="small text-muted"><?php echo $h($help); ?></span>
                <div class="sw-seg">
                    <?php foreach ($opts as $ov => $ol): ?>
                        <label><input type="radio" name="sw[<?php echo $h($key); ?>]" value="<?php echo $h($ov); ?>" <?php echo $cur === $ov ? 'checked' : ''; ?>><span><?php echo $h($ol); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif;
        endforeach; ?>
        </div>
    </div>

    <div class="actions">
        <button type="submit" class="sec-btn"><i class="fas fa-<?php echo $row ? 'floppy-disk' : 'user-plus'; ?> me-1"></i> <?php echo $row ? 'Save changes' : 'Create secretary'; ?></button>
        <a href="index.php" class="btn btn-link">Cancel</a>
    </div>
</form>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
