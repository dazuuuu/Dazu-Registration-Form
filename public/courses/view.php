<?php
// public/courses/view.php — Course detail + registration
$page_title = 'Course Details — Dazu Hub Institute';

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../app/init.php';
require_once __DIR__ . '/../../app/helpers/uploads.php';
require_once __DIR__ . '/../../app/models/Connection.php';
require_once __DIR__ . '/../../app/models/CourseModel.php';
require_once __DIR__ . '/../../app/models/RegistrationModel.php';
require_once __DIR__ . '/../../app/services/StudentNotifier.php';

use Models\Connection;
use Models\CourseModel;
use Models\RegistrationModel;

$pdo         = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);

$id     = (int)($_GET['id'] ?? 0);
$course = $id > 0 ? $courseModel->getById($id) : null;

if (!$course || !$course['is_active']) {
    header('Location: /Registration-System/public/');
    exit();
}

$page_title = htmlspecialchars($course['title']) . ' — Dazu Hub Institute';
$page_description = mb_strimwidth(strip_tags($course['overview'] ?? $course['description'] ?? ''), 0, 160, '…');

// Handle form submission
$reg_success = false;
$reg_error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dh_register'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $reg_error = 'Security check failed. Refresh and try again.';
    } else {
        $idDocPath = null;
        $file = $_FILES['id_document'] ?? null;

        if (!$file || empty($file['name'])) {
            $reg_error = 'Please upload your ID or result slip as a PDF.';
        } elseif (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            $reg_error = 'Upload failed (code ' . ($file['error']) . '). Try again.';
        } elseif (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            $reg_error = 'Document must be under 5 MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($mime !== 'application/pdf') {
                $reg_error = 'Only PDF files are accepted.';
            } else {
                $dest = dirname(__DIR__) . '/uploads/registrations/documents';
                if (!is_dir($dest)) mkdir($dest, 0775, true);
                $fname = time() . '_' . bin2hex(random_bytes(6)) . '.pdf';
                if (!move_uploaded_file($file['tmp_name'], $dest . '/' . $fname)) {
                    $reg_error = 'Could not save the document. Try again.';
                } else {
                    $idDocPath = '/Registration-System/public/uploads/registrations/documents/' . $fname;
                }
            }
        }

        if (!$reg_error) {
            $regModel = new RegistrationModel($pdo);
            $result   = $regModel->submit([
                'full_name'         => $_POST['full_name'] ?? '',
                'email'             => $_POST['email'] ?? '',
                'phone'             => $_POST['phone'] ?? '',
                'course_id'         => $course['id'],
                'study_mode'        => $_POST['study_mode'] ?? 'online',
                'payment_plan'      => $_POST['payment_plan'] ?? 'monthly',
                'id_document_path'  => $idDocPath,
                'payment_reference' => $_POST['payment_reference'] ?? '',
            ], $courseModel);

            if ($result['ok']) {
                $reg_success = true;
                $reg = $regModel->getById($result['id']);
                if ($reg) {
                    (new StudentNotifier($pdo))->sendRegistrationReceived($reg);
                }
            } else {
                $reg_error = $result['error'];
            }
        }
    }
}

// Breadcrumb
$breadcrumbs = [
    ['label' => 'Home',    'url' => '/Registration-System/public/'],
    ['label' => 'Courses', 'url' => '/Registration-System/public/#courses'],
    ['label' => $course['title'], 'active' => true],
];

// cover_image is stored as a full web path by upload_image():
// e.g. "/Registration-System/public/uploads/courses/covers/file.jpg"
// Prepending anything would double the path and break the URL.
$coverImg = !empty($course['cover_image']) ? $course['cover_image'] : null;

// Page CSS
$extra_css = <<<'CSS'
<style>
/* ── Course Detail Page ────────────────────────────────────── */
.cv-page { padding: 40px 0 80px; }

/* Breadcrumb */
.cv-breadcrumb {
    display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
    font-size: 0.82rem; color: var(--text-muted);
    margin-bottom: 32px;
}
.cv-breadcrumb a { color: var(--text-muted); text-decoration: none; }
.cv-breadcrumb a:hover { color: var(--dh-green); }
.cv-breadcrumb .sep { color: var(--border); }
.cv-breadcrumb .current { color: var(--text); font-weight: 500; }

/* Main layout: 60/40 split */
.cv-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 40px;
    align-items: start;
}

/* ── LEFT: course info ── */
.cv-info { min-width: 0; }

.cv-cover {
    width: 100%; aspect-ratio: 16/9;
    border-radius: 14px; overflow: hidden;
    background: var(--bg-alt);
    border: 1px solid var(--border);
    margin-bottom: 28px;
}
.cv-cover img {
    width: 100%; height: 100%; object-fit: cover;
    display: block;
}
.cv-cover-placeholder {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    font-size: 4rem;
    background: linear-gradient(135deg, var(--bg-alt), var(--bg-card));
}

.cv-meta-row {
    display: flex; align-items: center; flex-wrap: wrap; gap: 10px;
    margin-bottom: 14px;
}
.cv-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 99px;
    font-size: 0.75rem; font-weight: 600;
    background: var(--bg-alt); border: 1px solid var(--border);
    color: var(--text-sub);
}
.cv-badge i { color: var(--dh-green); font-size: 0.8rem; }
.cv-badge.green {
    background: rgba(46,125,50,0.08);
    border-color: rgba(46,125,50,0.2);
    color: var(--dh-green-dark);
}
[data-theme="dark"] .cv-badge.green { color: var(--dh-green-light); }

.cv-title {
    font-family: var(--font-display);
    font-size: clamp(1.6rem, 2.8vw, 2.2rem);
    font-weight: 800; color: var(--text);
    line-height: 1.15; margin-bottom: 16px;
}
.cv-share-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
.cv-wa-share {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 16px; border-radius: 8px;
    background: #25d366; color: #fff; text-decoration: none;
    font-size: 0.85rem; font-weight: 600;
    transition: opacity .2s, transform .15s;
}
.cv-wa-share:hover { opacity: .92; transform: translateY(-1px); color: #fff; }

.cv-fee-highlight {
    display: flex; align-items: baseline; gap: 12px;
    margin-bottom: 28px; padding: 16px 20px;
    background: var(--bg-alt); border: 1px solid var(--border);
    border-left: 3px solid var(--dh-green);
    border-radius: 10px;
}
.cv-fee-num {
    font-family: var(--font-display); font-size: 2rem; font-weight: 800;
    color: var(--dh-green);
}
.cv-fee-sub { font-size: 0.82rem; color: var(--text-muted); }
.cv-fee-sub strong { display: block; color: var(--text-sub); }

.cv-section-title {
    font-family: var(--font-display); font-size: 1.05rem; font-weight: 700;
    color: var(--text); margin: 28px 0 12px;
    padding-bottom: 8px; border-bottom: 1px solid var(--border);
}

.cv-overview {
    font-size: 0.95rem; color: var(--text-sub); line-height: 1.78;
    margin-bottom: 0;
}

.cv-description {
    font-size: 0.9rem; color: var(--text-sub); line-height: 1.8;
}
.cv-description p { margin-bottom: 12px; }

/* ── RIGHT: sticky form ── */
.cv-form-col {
    position: sticky; top: 88px;
}

.cv-form-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.cv-form-head {
    padding: 20px 24px 16px;
    background: var(--bg-alt);
    border-bottom: 1px solid var(--border);
}
.cv-form-head h2 {
    font-size: 1.1rem; font-weight: 800; margin-bottom: 3px;
}
.cv-form-head p { font-size: 0.78rem; color: var(--text-muted); }

.cv-form-body { padding: 20px 24px 24px; }

/* Compact form fields */
.cf-field { margin-bottom: 14px; }
.cf-field label {
    display: block; font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.09em; text-transform: uppercase;
    color: var(--text-muted); margin-bottom: 5px;
}
.cf-field input, .cf-field select {
    width: 100%; padding: 10px 12px;
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
    color: var(--text);
    font-family: var(--font-body); font-size: 0.875rem;
    transition: border-color var(--trans), box-shadow var(--trans);
    -webkit-appearance: none;
}
.cf-field input::placeholder { color: var(--text-muted); font-size: 0.82rem; }
.cf-field input:focus, .cf-field select:focus {
    outline: none;
    border-color: var(--dh-green);
    box-shadow: 0 0 0 3px rgba(46,125,50,0.1);
}

/* Two-col row */
.cf-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* Mode buttons */
.cf-mode { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.cf-mode-opt { position: relative; }
.cf-mode-opt input { position: absolute; opacity: 0; width:0; height:0; }
.cf-mode-opt label {
    display: flex; flex-direction: column; align-items: center;
    padding: 10px 8px; text-align: center;
    border: 1.5px solid var(--border);
    border-radius: var(--r-sm);
    background: var(--bg-input);
    cursor: pointer; font-size: 0.78rem; color: var(--text-sub);
    font-weight: 500; letter-spacing: 0; text-transform: none;
    transition: all var(--trans);
}
.cf-mode-opt label .em { font-size: 1.3rem; margin-bottom: 4px; }
.cf-mode-opt label strong { display: block; font-weight: 700; color: var(--text); font-size: 0.82rem; }
.cf-mode-opt label span { font-size: 0.68rem; color: var(--text-muted); }
.cf-mode-opt input:checked + label {
    border-color: var(--dh-green);
    background: rgba(46,125,50,0.06);
}

/* Plan buttons */
.cf-plan { display: grid; grid-template-columns: repeat(3,1fr); gap: 7px; }
.cf-plan-opt { position: relative; }
.cf-plan-opt input { position: absolute; opacity: 0; width:0; height:0; }
.cf-plan-opt label {
    display: block; text-align: center;
    padding: 9px 4px;
    border: 1.5px solid var(--border);
    border-radius: var(--r-sm);
    background: var(--bg-input);
    cursor: pointer;
    transition: all var(--trans);
}
.cf-plan-opt input:checked + label {
    border-color: var(--dh-green);
    background: rgba(46,125,50,0.06);
}
.cf-plan-opt label strong {
    display: block; font-size: 0.78rem; font-weight: 700; color: var(--text);
}
.cf-plan-opt label span { font-size: 0.66rem; color: var(--text-muted); }

/* Compact fee box */
.cf-fee {
    background: var(--bg-alt); border: 1px solid var(--border);
    border-radius: var(--r-sm); padding: 12px 14px; margin-bottom: 14px;
    font-size: 0.82rem;
}
.cf-fee-row {
    display: flex; justify-content: space-between;
    color: var(--text-muted); padding: 2px 0;
}
.cf-fee-row strong { color: var(--text); }
.cf-fee-hr { border: none; border-top: 1px solid var(--border); margin: 8px 0; }
.cf-fee-total {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 0.8rem; color: var(--text-muted);
}
.cf-fee-total .amt {
    font-family: var(--font-display); font-size: 1.3rem; font-weight: 800;
    color: var(--dh-green);
}

/* Payment note */
.cf-pay-note {
    background: rgba(46,125,50,0.05); border: 1px solid rgba(46,125,50,0.18);
    border-radius: var(--r-sm); padding: 10px 12px;
    font-size: 0.78rem; margin-bottom: 14px;
    color: var(--text-sub);
}
.cf-pay-note .label {
    font-size: 0.65rem; font-weight: 700; letter-spacing: 0.09em;
    text-transform: uppercase; color: var(--dh-green); margin-bottom: 5px;
}
.cf-pay-note b { color: var(--dh-green); }

/* Upload area */
.cf-upload {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 12px; border-radius: var(--r-sm);
    border: 1.5px dashed var(--border);
    cursor: pointer; color: var(--text-muted);
    font-size: 0.82rem;
    transition: border-color var(--trans), color var(--trans);
}
.cf-upload:hover { border-color: var(--dh-green); color: var(--dh-green); }
.cf-upload i { color: #DC2626; }
#cv_id_doc { display: none; }

/* Submit */
.cf-submit {
    width: 100%; padding: 13px;
    background: var(--dh-green); border: none;
    border-radius: var(--r-sm); color: #fff;
    font-family: var(--font-display); font-size: 0.95rem; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
    box-shadow: 0 4px 16px rgba(46,125,50,0.28);
    transition: background var(--trans), transform .15s, box-shadow var(--trans);
}
.cf-submit:hover { background: var(--dh-green-dark); transform: translateY(-1px); }
.cf-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.cf-alert {
    padding: 11px 14px; border-radius: var(--r-sm);
    font-size: 0.84rem; margin-bottom: 14px;
    display: flex; align-items: flex-start; gap: 8px;
}
.cf-alert-success { background: rgba(46,125,50,0.08); border: 1px solid rgba(46,125,50,0.22); color: var(--dh-green-dark); }
[data-theme="dark"] .cf-alert-success { color: var(--dh-green-light); }
.cf-alert-error { background: rgba(220,38,38,0.07); border: 1px solid rgba(220,38,38,0.2); color: #B91C1C; }
[data-theme="dark"] .cf-alert-error { color: #FCA5A5; }

/* ── Responsive ── */
@media (max-width: 900px) {
    .cv-grid { grid-template-columns: 1fr; }
    .cv-form-col { position: static; }
}
@media (max-width: 480px) {
    .cf-row { grid-template-columns: 1fr; }
    .cf-plan { grid-template-columns: 1fr; }
    .cf-mode { grid-template-columns: 1fr 1fr; }
}
</style>
CSS;

ob_start();
?>
<div class="dh-container cv-page">

    <!-- Breadcrumb -->
    <nav class="cv-breadcrumb" aria-label="Breadcrumb">
        <a href="/Registration-System/public/">Home</a>
        <span class="sep">/</span>
        <a href="/Registration-System/public/#courses">Courses</a>
        <span class="sep">/</span>
        <span class="current"><?php echo htmlspecialchars($course['title']); ?></span>
    </nav>

    <div class="cv-grid">

        <!-- ── LEFT: Course info ── -->
        <div class="cv-info">

            <!-- Cover image -->
            <div class="cv-cover">
                <?php if ($coverImg): ?>
                    <img src="<?php echo htmlspecialchars($coverImg); ?>"
                         alt="<?php echo htmlspecialchars($course['title']); ?>"
                         onerror="this.parentNode.innerHTML='<div class=\'cv-cover-placeholder\'>📚</div>'">
                <?php else: ?>
                    <div class="cv-cover-placeholder">
                        <?php
                        $icons = ['PY'=>'🐍','PHP'=>'🐘','ND'=>'⬡','FE'=>'⚡','FS'=>'🚀','AI'=>'🤖'];
                        echo $icons[strtoupper($course['abbreviation'])] ?? '📚';
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Meta badges -->
            <div class="cv-meta-row">
                <span class="cv-badge green">
                    <i class="fas fa-clock"></i>
                    <?php echo (int)$course['duration_months']; ?> month<?php echo $course['duration_months'] != 1 ? 's' : ''; ?>
                </span>
                <span class="cv-badge">
                    <i class="fas fa-laptop-code"></i> Online available
                </span>
                <span class="cv-badge">
                    <i class="fas fa-building-columns"></i> Physical available
                </span>
                <span class="cv-badge">
                    <i class="fas fa-certificate"></i> Certificate
                </span>
            </div>

            <!-- Title -->
            <h1 class="cv-title"><?php echo htmlspecialchars($course['title']); ?></h1>

            <div class="cv-share-row">
                <a href="<?php echo htmlspecialchars(course_whatsapp_share_url($course)); ?>"
                   class="cv-wa-share" target="_blank" rel="noopener">
                    <i class="fab fa-whatsapp"></i> Share / enquire on WhatsApp
                </a>
            </div>

            <!-- Fee highlight -->
            <div class="cv-fee-highlight">
                <div class="cv-fee-num">KSh <?php echo number_format($course['course_fee']); ?></div>
                <div class="cv-fee-sub">
                    <strong>Course fee</strong>
                    From KSh <?php echo number_format($course['course_fee'] / max(1, $course['duration_months'])); ?>/month
                    with monthly plan
                </div>
            </div>

            <!-- Overview -->
            <?php if (!empty($course['overview'])): ?>
            <h2 class="cv-section-title">Course Overview</h2>
            <p class="cv-overview"><?php echo nl2br(htmlspecialchars($course['overview'])); ?></p>
            <?php endif; ?>

            <!-- Full description -->
            <?php if (!empty($course['description'])): ?>
            <h2 class="cv-section-title">What You'll Learn</h2>
            <div class="cv-description">
                <?php
                // Render description — support basic HTML if stored, else treat as plain text
                $desc = $course['description'];
                // If it looks like it has HTML tags, output directly; otherwise nl2br
                if (preg_match('/<[a-z][\s\S]*>/i', $desc)) {
                    echo $desc;
                } else {
                    echo nl2br(htmlspecialchars($desc));
                }
                ?>
            </div>
            <?php endif; ?>

            <!-- Payment plans explained -->
            <h2 class="cv-section-title">Payment Options</h2>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
                <?php
                $fee = (float)$course['course_fee'];
                $mo  = (int)$course['duration_months'];
                $plans = [
                    ['Monthly', number_format($fee / max(1,$mo)), 'Spread over '.$mo.' months'],
                    ['3-Month',  number_format($fee / 3, 0), 'Three equal payments'],
                    ['Full Pay', number_format($fee), 'One-time, no split'],
                ];
                foreach ($plans as $p): ?>
                <div style="padding:14px 16px;background:var(--bg-alt);border:1px solid var(--border);border-radius:10px;">
                    <div style="font-family:var(--font-display);font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;"><?php echo $p[0]; ?></div>
                    <div style="font-family:var(--font-display);font-size:1.2rem;font-weight:800;color:var(--dh-green);">KSh <?php echo $p[1]; ?></div>
                    <div style="font-size:0.73rem;color:var(--text-muted);margin-top:3px;"><?php echo $p[2]; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── RIGHT: sticky registration form ── -->
        <div class="cv-form-col">
            <div class="cv-form-card">
                <div class="cv-form-head">
                    <h2>Apply for This Course</h2>
                    <p>Fill in your details below. Approval within 24 hours.</p>
                </div>
                <div class="cv-form-body">

                    <?php if ($reg_success): ?>
                    <div class="cf-alert cf-alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div><strong>Application received!</strong> Our team will review your payment and contact you within 24 hours.</div>
                    </div>
                    <?php else: ?>

                    <?php if ($reg_error): ?>
                    <div class="cf-alert cf-alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo htmlspecialchars($reg_error); ?></div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="cvForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="dh_register" value="1">
                        <input type="hidden" name="course_id" value="<?php echo (int)$course['id']; ?>">

                        <!-- Pre-selected course display -->
                        <div class="cf-field">
                            <label>Selected Course</label>
                            <div style="padding:10px 12px;background:rgba(46,125,50,0.06);border:1px solid rgba(46,125,50,0.2);border-radius:var(--r-sm);font-size:0.875rem;color:var(--text);font-weight:500;">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </div>
                        </div>

                        <div class="cf-row">
                            <div class="cf-field">
                                <label for="cv_name">Full Name *</label>
                                <input type="text" id="cv_name" name="full_name" required
                                       placeholder="Jane Wanjiku"
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                            </div>
                            <div class="cf-field">
                                <label for="cv_email">Email *</label>
                                <input type="email" id="cv_email" name="email" required
                                       placeholder="jane@email.com"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="cf-field">
                            <label for="cv_phone">Phone *</label>
                            <input type="tel" id="cv_phone" name="phone" required
                                   placeholder="07XX XXX XXX"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>

                        <div class="cf-field">
                            <label>Study Mode *</label>
                            <div class="cf-mode">
                                <div class="cf-mode-opt">
                                    <input type="radio" id="cv_online" name="study_mode" value="online" required
                                           <?php echo (!isset($_POST['study_mode']) || $_POST['study_mode']==='online') ? 'checked' : ''; ?>>
                                    <label for="cv_online">
                                        <span class="em">💻</span>
                                        <strong>Online</strong>
                                        <span>KSh 1,000 fee</span>
                                    </label>
                                </div>
                                <div class="cf-mode-opt">
                                    <input type="radio" id="cv_physical" name="study_mode" value="physical"
                                           <?php echo (isset($_POST['study_mode']) && $_POST['study_mode']==='physical') ? 'checked' : ''; ?>>
                                    <label for="cv_physical">
                                        <span class="em">🏫</span>
                                        <strong>Physical</strong>
                                        <span>KSh 1,500 + ID</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="cf-field">
                            <label>Payment Plan *</label>
                            <div class="cf-plan">
                                <div class="cf-plan-opt">
                                    <input type="radio" id="cv_monthly" name="payment_plan" value="monthly" checked>
                                    <label for="cv_monthly">
                                        <strong>Monthly</strong>
                                        <span id="cv_hint_mo">KSh <?php echo number_format($fee/$mo); ?>/mo</span>
                                    </label>
                                </div>
                                <div class="cf-plan-opt">
                                    <input type="radio" id="cv_3mo" name="payment_plan" value="3months">
                                    <label for="cv_3mo">
                                        <strong>3-Month</strong>
                                        <span id="cv_hint_3mo">KSh <?php echo number_format($fee/3); ?></span>
                                    </label>
                                </div>
                                <div class="cf-plan-opt">
                                    <input type="radio" id="cv_full" name="payment_plan" value="full">
                                    <label for="cv_full">
                                        <strong>Full</strong>
                                        <span id="cv_hint_full">KSh <?php echo number_format($fee); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Fee preview -->
                        <div class="cf-fee">
                            <div class="cf-fee-row">
                                <span>Registration fee</span>
                                <strong id="cv_rfee">KSh 1,000</strong>
                            </div>
                            <div class="cf-fee-row">
                                <span id="cv_ilabel">First installment</span>
                                <strong id="cv_iamt">KSh <?php echo number_format($fee/$mo); ?></strong>
                            </div>
                            <hr class="cf-fee-hr">
                            <div class="cf-fee-total">
                                <span>Pay now</span>
                                <span class="amt" id="cv_total">KSh <?php echo number_format(1000 + $fee/$mo); ?></span>
                            </div>
                        </div>

                        <!-- Payment details -->
                        <div class="cf-pay-note">
                            <div class="label"><i class="fas fa-mobile-screen-button"></i> NCBA Paybill</div>
                            Paybill: <b>[No.]</b> &nbsp;·&nbsp; Account: <b>[No.]</b>
                        </div>

                        <div class="cf-field">
                            <label for="cv_mpesa">M-Pesa Name / Code *</label>
                            <input type="text" id="cv_mpesa" name="payment_reference" required
                                   placeholder="Jane Wanjiku or QGH7XXXX"
                                   value="<?php echo htmlspecialchars($_POST['payment_reference'] ?? ''); ?>">
                        </div>

                        <div class="cf-field">
                            <label>ID / Result Slip (PDF, max 5 MB) *</label>
                            <label class="cf-upload" for="cv_id_doc" id="cvUploadLabel">
                                <i class="fas fa-file-pdf"></i>
                                <span id="cvUploadText">Upload PDF</span>
                            </label>
                            <input type="file" id="cv_id_doc" name="id_document" accept="application/pdf" required>
                        </div>

                        <button type="submit" class="cf-submit" id="cvSubmitBtn">
                            <i class="fas fa-paper-plane"></i> Submit Application
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>

<?php
$fee_js = (float)$course['course_fee'];
$mo_js  = (int)$course['duration_months'];

$extra_js = <<<JS
<script>
(function(){
    var fee = {$fee_js}, mo = {$mo_js};
    function fmt(n){ return 'KSh ' + Math.round(n).toLocaleString(); }

    function recalc(){
        var plan='monthly', mode='online';
        document.querySelectorAll('input[name="payment_plan"]').forEach(function(r){ if(r.checked) plan=r.value; });
        document.querySelectorAll('input[name="study_mode"]').forEach(function(r){ if(r.checked) mode=r.value; });

        var regFee = mode==='physical' ? 1500 : 1000;
        var count  = plan==='monthly' ? Math.max(1,mo) : (plan==='3months' ? 3 : 1);
        var inst   = fee / count;
        var label  = plan==='full' ? 'Full course fee' : 'First of '+count+' installment'+(count>1?'s':'');

        var s = function(id,v){ var e=document.getElementById(id); if(e) e.textContent=v; };
        s('cv_rfee',   fmt(regFee));
        s('cv_ilabel', label);
        s('cv_iamt',   fmt(inst));
        s('cv_total',  fmt(regFee + inst));
    }

    document.querySelectorAll('input[name="payment_plan"], input[name="study_mode"]')
            .forEach(function(r){ r.addEventListener('change', recalc); });
    recalc();

    var inp = document.getElementById('cv_id_doc');
    var txt = document.getElementById('cvUploadText');
    if (inp && txt) inp.addEventListener('change', function(){
        txt.textContent = inp.files[0] ? inp.files[0].name : 'Upload PDF';
    });

    var form = document.getElementById('cvForm');
    if (form) form.addEventListener('submit', function(){
        var b = document.getElementById('cvSubmitBtn');
        if (b){ b.disabled=true; b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Submitting…'; }
    });
})();
</script>
JS;

require __DIR__ . '/../templates/public/layout.php';