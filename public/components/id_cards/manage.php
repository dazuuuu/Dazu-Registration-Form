<?php
// Student ID cards — management page shared by admin/id_cards/index.php and superadmin/id_cards/index.php.
// Expects $idcArea = 'admin' | 'superadmin' (already authorised). Handles its own POSTs, then renders into $content.
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/IdCardService.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';
require_once __DIR__ . '/../../../app/v1/services/ManualIdService.php';

$db = \Models\Connection::getInstance()->getConnection();
$idcSelf = url($idcArea . '/id_cards/index.php');
$page_title = 'Student ID Cards';

// Manual card form: look up an existing student / tutor / intern / staff member by name to prefill the card.
if (isset($_GET['midc_lookup'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['results' => midc_people_search($db, (string) $_GET['midc_lookup'])]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $idcSelf . (isset($_GET['status']) ? '?status=' . urlencode((string) $_GET['status']) : '');
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
        header('Location: ' . $back);
        exit;
    }
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['idc_action'] ?? '') {
        case 'generate':
            $res = idc_generate($db, (int) ($_POST['registration_id'] ?? 0), !empty($_POST['waive']), (int) ($_POST['valid_until'] ?? 0) ?: null);
            if ($res['ok']) {
                // Approving a request (or generating directly) also emails the card to the student.
                require_once __DIR__ . '/../../../app/v1/services/StaffIdService.php';
                $mail = idc_email_card($db, (int) $res['id']);
                $_SESSION['success_msg'] = 'ID card generated. The student can download it from My Documents'
                    . ($mail['ok'] && empty($mail['skipped']) ? ' and it was emailed to them.'
                        : (!empty($mail['skipped']) ? '. Email is turned off on this site, so it was not emailed.' : '. (The email could not be sent: ' . ($mail['message'] ?? 'unknown error') . '.)'));
            } else {
                $_SESSION['error_msg'] = 'Cannot generate the ID card: ' . $res['error'];
            }
            break;
        case 'ready':
            $res = idc_set_status($db, $id, 'ready', (string) ($_POST['location'] ?? ''));
            $_SESSION[$res['ok'] ? 'success_msg' : 'error_msg'] = $res['ok'] ? 'Marked ready for pickup — the student has been notified.' : $res['error'];
            break;
        case 'collected':
        case 'generated':
            $res = idc_set_status($db, $id, $_POST['idc_action']);
            $_SESSION[$res['ok'] ? 'success_msg' : 'error_msg'] = $res['ok'] ? 'Status updated.' : $res['error'];
            break;
        case 'decline':
            $res = idc_set_status($db, $id, 'declined', null, trim((string) ($_POST['note'] ?? '')));
            $_SESSION[$res['ok'] ? 'success_msg' : 'error_msg'] = $res['ok'] ? 'Request declined — the student has been notified.' : $res['error'];
            break;
        case 'manual_create':
            try {
                $in = $_POST;
                if (!empty($in['person']) && ($p = midc_person($db, (string) $in['person']))) {
                    // Name, role and number come from the database; the form may still adjust title, number and validity.
                    $in['full_name'] = $p['full_name'];
                    $in['role'] = $p['role'];
                    if (trim((string) ($in['title'] ?? '')) === '') $in['title'] = (string) $p['title'];
                } else {
                    unset($in['person']);
                }
                $res = midc_create($db, $in, $_FILES['photo'] ?? [], (int) ($_SESSION['user_id'] ?? 0));
            } catch (\PDOException $ex) {
                $res = ['ok' => false, 'error' => 'the manual ID cards table is not installed yet. Run System Updates (migration 071).'];
            }
            if ($res['ok']) {
                $_SESSION['success_msg'] = 'ID card created. Use the eye or print button to download it.';
                $back = $idcSelf . '?status=manual';
            } else {
                $_SESSION['error_msg'] = 'Cannot create the ID card: ' . $res['error'];
            }
            break;
        case 'manual_delete':
            midc_delete($db, $id);
            $_SESSION['success_msg'] = 'ID card removed. Its QR code no longer verifies.';
            break;
        case 'delete':
            idc_delete($db, $id);
            $_SESSION['success_msg'] = 'ID card removed.';
            break;
        case 'photo':
            $card = idc_get($db, $id);
            $res = $card ? cert_save_student_photo($db, (int) ($card['user_id'] ?: $card['reg_user_id']), $_FILES['photo'] ?? []) : ['ok' => false, 'error' => 'ID card not found.'];
            $_SESSION[$res['ok'] ? 'success_msg' : 'error_msg'] = $res['ok'] ? 'Photo saved — it is on the card now.' : $res['error'];
            break;
    }
    header('Location: ' . $back);
    exit;
}

$filter = (string) ($_GET['status'] ?? '');
$manualCards = [];
try {
    $manualCards = midc_list($db);
} catch (\PDOException $ex) {
    if ($filter === 'manual') $_SESSION['error_msg'] = 'The manual ID cards table is not installed yet. Run System Updates (migration 071) and reload this page.';
}
try {
    $cards = $filter === 'manual' ? [] : idc_list($db, $filter);
    $counts = idc_counts($db);
} catch (\PDOException $e) {
    $cards = [];
    $counts = array_fill_keys(array_keys(IDC_STATUS_LABELS), 0);
    $_SESSION['error_msg'] = 'The ID cards table is not installed yet. Run System Updates (migration 064) and reload this page.';
}
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pdfUrl = fn(int $id, string $q = '') => url('id_cards/pdf.php?id=' . $id . $q);

ob_start();
?>
<style>
.idc-tabs{display:flex;gap:.4rem;flex-wrap:wrap}
.idc-tabs a{display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .85rem;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#334155;font-size:.85rem;font-weight:600;text-decoration:none}
.idc-tabs a.on{background:#44A024;border-color:#44A024;color:#fff}
.idc-tabs a .n{background:rgba(0,0,0,.08);border-radius:999px;padding:0 .45rem;font-size:.75rem}
.idc-tabs a.on .n{background:rgba(255,255,255,.25)}
.idc-row{display:grid;grid-template-columns:52px 1fr auto;gap:.9rem;align-items:center;padding:.9rem 1rem;border-bottom:1px solid #f1f5f9}
.idc-row:last-child{border-bottom:0}
.idc-ava{width:52px;height:64px;border-radius:8px;object-fit:cover;background:#eef2ec;display:flex;align-items:center;justify-content:center;color:#94a3b8;border:2px solid #44A024}
.idc-meta{font-size:.82rem;color:#64748b}
.idc-actions{display:flex;gap:.35rem;flex-wrap:wrap;justify-content:flex-end}
@media(max-width:767px){.idc-row{grid-template-columns:44px 1fr}.idc-actions{grid-column:1/-1;justify-content:flex-start}.idc-ava{width:44px;height:54px}}
</style>
<div class="container-fluid py-4">
    <?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $key => $cls): if (isset($_SESSION[$key])): ?>
        <div class="alert alert-<?php echo $cls; ?> alert-dismissible fade show"><?php echo $e($_SESSION[$key]); unset($_SESSION[$key]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; endforeach; ?>

    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1"><i class="fas fa-id-card text-success me-2"></i>Student ID Cards</h2>
            <div class="text-muted small">For physical students. Online students need a waiver. Generated cards appear in the student's <em>My Documents</em>.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo url('verify/id.php'); ?>" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-qrcode me-1"></i>Verify page</a>
            <div class="dropdown">
                <button class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-plus me-1"></i>Generate ID card</button>
                <div class="dropdown-menu dropdown-menu-end p-2" style="min-width:280px">
                    <button type="button" class="dropdown-item rounded py-2" data-bs-toggle="modal" data-bs-target="#idcGenModal">
                        <i class="fas fa-user-graduate text-success me-2"></i><strong>From student records</strong>
                        <div class="small text-muted ms-4 text-wrap">Search an enrolled student</div></button>
                    <button type="button" class="dropdown-item rounded py-2" data-bs-toggle="modal" data-bs-target="#idcManualModal">
                        <i class="fas fa-pen-to-square text-success me-2"></i><strong>Manual entry</strong>
                        <div class="small text-muted ms-4 text-wrap">Type the name, pick a role, upload a photo</div></button>
                </div>
            </div>
        </div>
    </div>

    <div class="idc-tabs mb-3">
        <a href="<?php echo $e($idcSelf); ?>" class="<?php echo $filter === '' ? 'on' : ''; ?>">All <span class="n"><?php echo array_sum($counts); ?></span></a>
        <?php foreach (IDC_STATUS_LABELS as $k => $label): ?>
            <a href="<?php echo $e($idcSelf . '?status=' . $k); ?>" class="<?php echo $filter === $k ? 'on' : ''; ?>">
                <?php echo $e($k === 'requested' ? 'Requests' : $label); ?> <span class="n"><?php echo (int) $counts[$k]; ?></span></a>
        <?php endforeach; ?>
        <a href="<?php echo $e($idcSelf . '?status=manual'); ?>" class="<?php echo $filter === 'manual' ? 'on' : ''; ?>">Manual entries <span class="n"><?php echo count($manualCards); ?></span></a>
    </div>

    <?php if ($filter === 'manual'): ?>
    <div class="card border-0 shadow-sm rounded-4">
        <?php if (!$manualCards): ?>
            <div class="text-center py-5 text-muted"><i class="fas fa-pen-to-square fa-3x mb-3 opacity-25"></i><p class="mb-0">No manual ID cards yet. Use <strong>Generate ID card → Manual entry</strong>.</p></div>
        <?php else: foreach ($manualCards as $m): $mPhoto = midc_photo_file($m); ?>
            <div class="idc-row">
                <?php if ($mPhoto): ?><img class="idc-ava" src="<?php echo $e(url($m['photo_path'])); ?>" alt="">
                <?php else: ?><div class="idc-ava"><i class="fas fa-user"></i></div><?php endif; ?>
                <div class="min-w-0">
                    <div class="fw-semibold"><?php echo $e($m['full_name']); ?> <span class="text-muted small ms-1"><?php echo $e($m['id_no']); ?></span></div>
                    <div class="small"><?php echo $e(midc_card($m)['snapshot_course']); ?></div>
                    <div class="idc-meta mt-1 d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge bg-success-subtle text-success-emphasis"><?php echo $e(MIDC_ROLES[$m['role']][0] ?? $m['role']); ?></span>
                        <span>Valid until <?php echo (int) $m['valid_until']; ?></span>
                        <span>Created <?php echo $e(date('j M Y', strtotime($m['created_at']))); ?></span>
                    </div>
                </div>
                <div class="idc-actions">
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo $e(url('id_cards/pdf.php?manual=' . (int) $m['id'])); ?>" title="View card"><i class="fas fa-eye"></i></a>
                    <a class="btn btn-sm btn-outline-dark" href="<?php echo $e(url('id_cards/pdf.php?manual=' . (int) $m['id'] . '&print=1&dl=1')); ?>" title="Print file (bleed + crop marks)"><i class="fas fa-print"></i></a>
                    <form method="POST" class="m-0" onsubmit="return confirm('Remove this ID card? Its QR code will stop verifying.')"><?php echo csrf_field(); ?>
                        <input type="hidden" name="idc_action" value="manual_delete"><input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Remove"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <?php if (!$cards): ?>
            <div class="text-center py-5 text-muted"><i class="fas fa-id-card fa-3x mb-3 opacity-25"></i><p class="mb-0">No ID cards here yet.</p></div>
        <?php else: foreach ($cards as $c):
            $photo = cert_student_photo($db, (int) $c['user_id'] ?: null);
            $online = $c['study_mode'] !== 'physical';
        ?>
            <div class="idc-row">
                <?php if ($photo): ?><img class="idc-ava" src="<?php echo $e(settings_asset_url($photo)); ?>" alt="">
                <?php else: ?><div class="idc-ava"><i class="fas fa-user"></i></div><?php endif; ?>
                <div class="min-w-0">
                    <div class="fw-semibold"><?php echo $e($c['full_name']); ?>
                        <span class="text-muted small ms-1"><?php echo $e($c['registration_no'] ?: 'no reg. no.'); ?></span></div>
                    <div class="small"><?php echo $e($c['course_name']); ?></div>
                    <div class="idc-meta mt-1 d-flex flex-wrap gap-2 align-items-center">
                        <?php echo idc_status_badge($c['status']); ?>
                        <?php if ($c['status'] === 'ready' && $c['pickup_location']): ?><span><i class="fas fa-location-dot text-success me-1"></i><?php echo $e(IDC_LOCATIONS[$c['pickup_location']]); ?></span><?php endif; ?>
                        <span class="badge <?php echo $online ? 'bg-light text-dark border' : 'bg-success-subtle text-success-emphasis'; ?>"><?php echo $online ? 'Online' : 'Physical'; ?><?php echo $c['mode_waived'] ? ' · waived' : ''; ?></span>
                        <?php if ($c['valid_until']): ?><span>Valid until <?php echo (int) $c['valid_until']; ?></span><?php endif; ?>
                        <?php if ($c['status'] === 'requested'): ?><span>Requested <?php echo $e(date('j M Y', strtotime($c['requested_at'] ?: $c['created_at']))); ?></span><?php endif; ?>
                        <?php if ($c['request_note']): ?><span class="fst-italic">“<?php echo $e($c['request_note']); ?>”</span><?php endif; ?>
                        <?php if (!empty($c['emailed_at'])): ?><span><i class="fas fa-envelope-circle-check text-success me-1"></i>Emailed <?php echo $e(date('j M', strtotime($c['emailed_at']))); ?></span><?php endif; ?>
                        <?php if (!$photo): ?><span class="text-warning"><i class="fas fa-triangle-exclamation me-1"></i>No passport photo</span><?php endif; ?>
                    </div>
                </div>
                <div class="idc-actions">
                    <?php if ($c['status'] === 'requested'): ?>
                        <form method="POST" class="m-0 d-flex gap-1 align-items-center">
                            <?php echo csrf_field(); ?><input type="hidden" name="idc_action" value="generate">
                            <input type="hidden" name="registration_id" value="<?php echo (int) $c['registration_id']; ?>">
                            <?php if ($online): ?><label class="small text-muted d-flex align-items-center gap-1"><input type="checkbox" name="waive" value="1" class="form-check-input m-0" required> Waive (online)</label><?php endif; ?>
                            <button class="btn btn-sm btn-success" <?php echo $photo ? '' : 'disabled title="The student has no passport photo yet"'; ?>><i class="fas fa-check me-1"></i>Approve &amp; email</button>
                        </form>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#idcDecline<?php echo (int) $c['id']; ?>">Decline</button>
                    <?php elseif ($c['status'] !== 'declined'): ?>
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo $e($pdfUrl((int) $c['id'])); ?>" title="View card"><i class="fas fa-eye"></i></a>
                        <a class="btn btn-sm btn-outline-dark" href="<?php echo $e($pdfUrl((int) $c['id'], '&print=1&dl=1')); ?>" title="Print file (bleed + crop marks)"><i class="fas fa-print"></i></a>
                        <?php if ($c['status'] === 'generated' || $c['status'] === 'ready'): ?>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-success dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-location-dot me-1"></i><?php echo $c['status'] === 'ready' ? 'Change pickup' : 'Ready for pickup'; ?></button>
                                <div class="dropdown-menu dropdown-menu-end p-2">
                                    <?php foreach (IDC_LOCATIONS as $lk => $ll): ?>
                                        <form method="POST" class="m-0"><?php echo csrf_field(); ?>
                                            <input type="hidden" name="idc_action" value="ready"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                            <input type="hidden" name="location" value="<?php echo $lk; ?>">
                                            <button class="dropdown-item rounded"><i class="fas fa-building me-2 text-success"></i>Pick up at <?php echo $e($ll); ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <form method="POST" class="m-0"><?php echo csrf_field(); ?>
                                <input type="hidden" name="idc_action" value="collected"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                <button class="btn btn-sm btn-outline-primary"><i class="fas fa-hand-holding me-1"></i>Collected</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="m-0"><?php echo csrf_field(); ?>
                                <input type="hidden" name="idc_action" value="generated"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                <button class="btn btn-sm btn-outline-secondary" title="Undo collected"><i class="fas fa-rotate-left"></i></button>
                            </form>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#idcPhoto<?php echo (int) $c['id']; ?>" title="Upload photo"><i class="fas fa-camera"></i></button>
                    <?php endif; ?>
                    <form method="POST" class="m-0" onsubmit="return confirm('Remove this ID card record? The student will no longer see it.')"><?php echo csrf_field(); ?>
                        <input type="hidden" name="idc_action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Remove"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>

            <?php if ($c['status'] === 'requested'): ?>
            <div class="modal fade" id="idcDecline<?php echo (int) $c['id']; ?>" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content">
                <?php echo csrf_field(); ?><input type="hidden" name="idc_action" value="decline"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                <div class="modal-header"><h5 class="modal-title">Decline ID card request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><label class="form-label">Reason shown to <?php echo $e($c['full_name']); ?> (optional)</label>
                    <textarea name="note" class="form-control" rows="3" maxlength="255" placeholder="e.g. ID cards are issued to physical students only."></textarea></div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Decline request</button></div>
            </form></div></div>
            <?php else: ?>
            <div class="modal fade" id="idcPhoto<?php echo (int) $c['id']; ?>" tabindex="-1"><div class="modal-dialog"><form method="POST" enctype="multipart/form-data" class="modal-content">
                <?php echo csrf_field(); ?><input type="hidden" name="idc_action" value="photo"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                <div class="modal-header"><h5 class="modal-title">Passport photo — <?php echo $e($c['full_name']); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                    <div class="form-text">Passport-style, JPG/PNG, max 5 MB. It replaces the photo on the student's profile and card.</div></div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save photo</button></div>
            </form></div></div>
            <?php endif; ?>
        <?php endforeach; endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="idcManualModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="idc_action" value="manual_create">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pen-to-square text-success me-2"></i>Manual ID card</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8 position-relative"><label class="form-label fw-semibold">Full name</label>
                        <input type="hidden" name="person" id="midcPerson">
                        <input type="text" name="full_name" id="midcName" class="form-control" maxlength="150" required autocomplete="off" placeholder="Start typing a student's, tutor's or staff member's name…">
                        <div id="midcHits" class="list-group position-absolute w-100 shadow d-none" style="z-index:1060;max-height:260px;overflow:auto"></div>
                        <div class="form-text" id="midcPicked">Pick someone from the list to fill in their details from the system, or type a new name.</div></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Role</label>
                        <select name="role" id="midcRole" class="form-select" required>
                            <option value="" disabled selected>Choose…</option>
                            <?php foreach (MIDC_ROLES as $rk => [$rl]): ?><option value="<?php echo $rk; ?>"><?php echo $rl; ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-8"><label class="form-label fw-semibold" id="midcTitleLabel">Job title / department <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="title" id="midcTitle" class="form-control" maxlength="150" placeholder="e.g. Finance, ICT Department"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Valid until (year)</label>
                        <input type="number" name="valid_until" class="form-control" min="<?php echo date('Y'); ?>" max="2100" value="<?php echo (int) date('Y') + 1; ?>" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">ID number <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="id_no" id="midcIdNo" class="form-control text-uppercase" maxlength="50" pattern="[A-Za-z0-9/\-]{2,50}" placeholder="Leave blank to auto-number, e.g. DTI-DIR-0007"></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Passport photo</label>
                        <input type="file" name="photo" id="midcPhoto" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                        <div class="form-text text-success d-none" id="midcHasPhoto"><i class="fas fa-circle-check me-1"></i>A photo is already on file and will be used unless you upload a new one.</div>
                        <div class="form-text">Passport-style, JPG/PNG/WebP, max 5 MB.</div></div>
                    <div class="col-12 text-center"><img id="midcPreview" alt="" class="d-none rounded border" style="width:100px;height:125px;object-fit:cover;border-color:#44A024!important;border-width:2px!important"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-id-card me-1"></i>Create ID card</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    const role = document.getElementById('midcRole'), title = document.getElementById('midcTitle'),
          label = document.getElementById('midcTitleLabel'), photo = document.getElementById('midcPhoto'),
          preview = document.getElementById('midcPreview');
    role.addEventListener('change', () => {
        const student = role.value === 'student';
        title.required = student;
        label.innerHTML = student ? 'Course' : 'Job title / department <span class="text-muted fw-normal">(optional)</span>';
        title.placeholder = student ? 'e.g. Certificate in Computer Packages' : 'e.g. Finance, ICT Department';
    });
    const name = document.getElementById('midcName'), person = document.getElementById('midcPerson'), hits = document.getElementById('midcHits'),
          picked = document.getElementById('midcPicked'), idNo = document.getElementById('midcIdNo'), hasPhoto = document.getElementById('midcHasPhoto');
    const esc = s => { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
    let timer, rows = [];
    const unpick = () => { person.value = ''; photo.required = true; hasPhoto.classList.add('d-none');
        picked.textContent = 'Pick someone from the list to fill in their details from the system, or type a new name.'; };
    name.addEventListener('input', () => {
        unpick(); clearTimeout(timer);
        const q = name.value.trim();
        if (q.length < 2) { hits.classList.add('d-none'); return; }
        timer = setTimeout(async () => {
            try {
                const u = new URL(location.href); u.search = ''; u.searchParams.set('midc_lookup', q);
                rows = (await (await fetch(u, {headers: {Accept: 'application/json'}, credentials: 'same-origin'})).json()).results || [];
            } catch (e) { rows = []; }
            if (q !== name.value.trim()) return;
            hits.innerHTML = rows.length ? rows.map((r, i) => '<button type="button" class="list-group-item list-group-item-action" data-i="' + i + '"><strong>' + esc(r.full_name) +
                '</strong> <span class="badge bg-success-subtle text-success ms-1">' + esc(r.label) + '</span><div class="small text-muted">' +
                esc([r.id_no, r.title].filter(Boolean).join(' · ')) + (r.has_photo ? ' · <i class="fas fa-camera"></i> photo on file' : '') + '</div></button>').join('')
                : '<div class="list-group-item small text-muted">No one in the system matches. You can still create a card for a new name.</div>';
            hits.classList.remove('d-none');
        }, 250);
    });
    hits.addEventListener('click', e => {
        const b = e.target.closest('[data-i]'); if (!b) return;
        const r = rows[+b.dataset.i];
        person.value = r.key; name.value = r.full_name; role.value = r.role; role.dispatchEvent(new Event('change'));
        title.value = r.title || ''; idNo.value = r.id_no || '';
        photo.required = !r.has_photo; hasPhoto.classList.toggle('d-none', !r.has_photo);
        picked.innerHTML = '<i class="fas fa-circle-check text-success me-1"></i>Filled in from the system: <strong>' + esc(r.full_name) + '</strong> (' + esc(r.label) + ')' + (r.has_photo ? '' : '. Upload their passport photo.');
        hits.classList.add('d-none');
    });
    document.addEventListener('click', e => { if (!hits.contains(e.target) && e.target !== name) hits.classList.add('d-none'); });
    photo.addEventListener('change', () => {
        const f = photo.files[0];
        if (!f) { preview.classList.add('d-none'); return; }
        preview.src = URL.createObjectURL(f);
        preview.classList.remove('d-none');
    });
})();
</script>

<div class="modal fade" id="idcGenModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" class="modal-content" id="idcGenForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="idc_action" value="generate">
            <input type="hidden" name="registration_id" id="idcRegId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-id-card text-success me-2"></i>Generate student ID card</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-2">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="search" id="idcSearch" class="form-control" autocomplete="off" placeholder="Search by name, registration no, email, phone or course…">
                </div>
                <div class="small text-muted mb-3">ID cards are for <strong>physical</strong> students. For an online student, tick <em>Waive</em> to generate one anyway.</div>
                <div id="idcResults" class="list-group"><div class="text-center text-muted py-4 small">Type at least 2 characters to search.</div></div>
                <div id="idcOpts" class="border rounded-3 p-3 mt-3 d-none bg-light">
                    <div class="row g-3 align-items-end">
                        <div class="col-sm-4"><label class="form-label small fw-semibold mb-1">Valid until (year)</label>
                            <input type="number" name="valid_until" id="idcValid" class="form-control" min="2000" max="2100"></div>
                        <div class="col-sm-8" id="idcWaiveWrap">
                            <label class="form-check mb-0"><input type="checkbox" name="waive" value="1" id="idcWaive" class="form-check-input">
                                <span class="form-check-label">Waive — this student studies <strong>online</strong>; generate an ID card anyway</span></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="me-auto small text-muted" id="idcPicked">No student selected.</span>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" id="idcGenBtn" disabled>Generate ID card</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    const input = document.getElementById('idcSearch'), box = document.getElementById('idcResults'),
          regId = document.getElementById('idcRegId'), picked = document.getElementById('idcPicked'),
          btn = document.getElementById('idcGenBtn'), opts = document.getElementById('idcOpts'),
          valid = document.getElementById('idcValid'), waive = document.getElementById('idcWaive'),
          waiveWrap = document.getElementById('idcWaiveWrap');
    let timer = null, seq = 0, rows = [], cur = null;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const labels = {requested: 'Requested', generated: 'Has ID card', ready: 'Ready for pickup', collected: 'Collected'};
    const blocked = r => !r.reg_no || (r.card_status && r.card_status !== 'requested');

    function sync() {
        btn.disabled = !cur || (cur.mode !== 'physical' && !waive.checked);
    }
    function render() {
        const q = input.value.trim();
        if (q.length < 2) { box.innerHTML = '<div class="text-center text-muted py-4 small">Type at least 2 characters to search.</div>'; return; }
        if (!rows.length) { box.innerHTML = '<div class="text-center text-muted py-4 small">No approved students match “' + esc(q) + '”.</div>'; return; }
        box.innerHTML = rows.map(r => {
            const mode = r.mode === 'physical'
                ? '<span class="badge bg-success-subtle text-success-emphasis">Physical' + (r.location ? ' · ' + esc(r.location[0].toUpperCase() + r.location.slice(1)) : '') + '</span>'
                : '<span class="badge bg-light text-dark border">Online · needs waiver</span>';
            const st = !r.reg_no ? '<span class="badge bg-danger">No reg. no.</span>'
                : r.card_status ? '<span class="badge bg-secondary">' + labels[r.card_status] + '</span>' : '';
            return '<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2' + (blocked(r) ? ' opacity-75' : '') + '" data-id="' + r.id + '">'
                + '<div class="text-start"><div class="fw-semibold">' + esc(r.name) + ' <span class="text-muted small">' + esc(r.reg_no || '') + '</span></div>'
                + '<div class="small">' + esc(r.course) + '</div><div class="small text-muted">' + esc(r.email) + ' · ' + esc(r.phone) + '</div></div>'
                + '<div class="text-end d-flex flex-column gap-1 align-items-end">' + mode + st + '</div></button>';
        }).join('');
    }
    async function search() {
        const q = input.value.trim(), my = ++seq;
        if (q.length < 2) { rows = []; render(); return; }
        box.innerHTML = '<div class="text-center text-muted py-4 small"><span class="spinner-border spinner-border-sm me-2"></span>Searching…</div>';
        try {
            const data = await (await fetch('search.php?q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json'}})).json();
            if (my !== seq) return;
            rows = data.results || [];
        } catch (e) { rows = []; }
        render();
    }
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 250); });
    waive.addEventListener('change', sync);
    box.addEventListener('click', e => {
        const b = e.target.closest('[data-id]');
        if (!b) return;
        const r = rows.find(x => String(x.id) === b.dataset.id);
        box.querySelectorAll('.active').forEach(x => x.classList.remove('active'));
        cur = null; regId.value = ''; opts.classList.add('d-none');
        if (blocked(r)) {
            picked.innerHTML = '<span class="text-danger">' + esc(r.name) + ': ' + (!r.reg_no ? 'has no registration number yet.' : 'already has an ID card.') + '</span>';
            sync(); return;
        }
        b.classList.add('active');
        cur = r; regId.value = r.id; valid.value = r.valid_until;
        waive.checked = false; waiveWrap.classList.toggle('d-none', r.mode === 'physical');
        opts.classList.remove('d-none');
        picked.innerHTML = 'Selected: <strong>' + esc(r.name) + '</strong> — ' + esc(r.course);
        sync();
    });
    document.getElementById('idcGenModal').addEventListener('shown.bs.modal', () => input.focus());
})();
</script>
<?php
$content = ob_get_clean();
