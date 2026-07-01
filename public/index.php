<?php
// public/index.php — Dazu Hub Institute homepage
$page_title       = 'Dazu Hub Institute — Tech Courses in Nairobi, Kenya';
$page_description = 'Hands-on Python, PHP, Node.js, Frontend, Full-Stack and AI bootcamps in Nairobi. Flexible payment plans. Physical and online.';

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config/db_connect.php';
require_once __DIR__ . '/../app/helpers/uploads.php';
require_once __DIR__ . '/../app/models/Connection.php';
require_once __DIR__ . '/../app/models/CourseModel.php';
require_once __DIR__ . '/../app/models/RegistrationModel.php';
require_once __DIR__ . '/../app/services/StudentNotifier.php';

use Models\Connection;
use Models\CourseModel;
use Models\RegistrationModel;

$pdo         = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$liveCourses = $courseModel->getAll(true);

// ── Handle homepage quick-apply POST ─────────────────────
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
            $reg_error = 'Document upload failed. Try again.';
        } elseif (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            $reg_error = 'Document must be under 5 MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($mime !== 'application/pdf') {
                $reg_error = 'Only PDF files are accepted for the ID/result slip.';
            } else {
                $dest = __DIR__ . '/uploads/registrations/documents';
                if (!is_dir($dest)) mkdir($dest, 0775, true);
                $fname = time() . '_' . bin2hex(random_bytes(6)) . '.pdf';
                if (!move_uploaded_file($file['tmp_name'], $dest . '/' . $fname)) {
                    $reg_error = 'Could not save the file. Try again.';
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
                'course_id'         => $_POST['course_id'] ?? 0,
                'study_mode'        => $_POST['study_mode'] ?? 'online',
                'payment_plan'      => $_POST['payment_plan'] ?? 'monthly',
                'id_document_path'  => $idDocPath,
                'payment_reference' => $_POST['payment_reference'] ?? '',
            ], $courseModel);
            $reg_success = $result['ok'];
            if ($result['ok']) {
                $reg = $regModel->getById($result['id']);
                if ($reg) {
                    (new StudentNotifier($pdo))->sendRegistrationReceived($reg);
                }
            }
            if (!$result['ok']) $reg_error = $result['error'];
        }
    }
}

// ── Course data ───────────────────────────────────────────
$staticCourses = [
    ['id'=>0,'title'=>'Backend Dev — Python & Django','abbreviation'=>'PY',
     'overview'=>'Build scalable APIs and web apps with Python and the Django framework.',
     'description'=>'','duration_months'=>6,'course_fee'=>45000,'cover_image'=>null,
     'icon'=>'🐍','badge_color'=>'#3B82F6','tags'=>['Python','Django','REST API','PostgreSQL'],'is_active'=>1],
    ['id'=>0,'title'=>'Backend Dev — PHP','abbreviation'=>'PHP',
     'overview'=>'Master server-side development with PHP 8+, MySQL and MVC architecture.',
     'description'=>'','duration_months'=>5,'course_fee'=>35000,'cover_image'=>null,
     'icon'=>'🐘','badge_color'=>'#8B5CF6','tags'=>['PHP 8+','MySQL','MVC','REST APIs'],'is_active'=>1],
    ['id'=>0,'title'=>'Backend Dev — Node.js','abbreviation'=>'ND',
     'overview'=>'JavaScript on the server — build fast APIs and real-time apps with Node.js.',
     'description'=>'','duration_months'=>5,'course_fee'=>38000,'cover_image'=>null,
     'icon'=>'⬡','badge_color'=>'#10B981','tags'=>['Node.js','Express','MongoDB','REST'],'is_active'=>1],
    ['id'=>0,'title'=>'Frontend Development','abbreviation'=>'FE',
     'overview'=>'From HTML/CSS foundations to React — build responsive, professional UIs.',
     'description'=>'','duration_months'=>4,'course_fee'=>30000,'cover_image'=>null,
     'icon'=>'⚡','badge_color'=>'#F59E0B','tags'=>['HTML/CSS','JavaScript','React','Tailwind'],'is_active'=>1],
    ['id'=>0,'title'=>'Full-Stack Web Development','abbreviation'=>'FS',
     'overview'=>'Front-end, back-end and deployment — ship complete production applications.',
     'description'=>'','duration_months'=>8,'course_fee'=>55000,'cover_image'=>null,
     'icon'=>'🚀','badge_color'=>'#EF4444','tags'=>['React','Node.js','Databases','DevOps'],'is_active'=>1],
    ['id'=>0,'title'=>'AI & Machine Learning','abbreviation'=>'AI',
     'overview'=>'Practical AI/ML with Python — train real models and apply them to real problems.',
     'description'=>'','duration_months'=>6,'course_fee'=>50000,'cover_image'=>null,
     'icon'=>'🤖','badge_color'=>'#2E7D32','tags'=>['Python','TensorFlow','scikit-learn','NLP'],'is_active'=>1],
];

$iconMap = ['PY'=>'🐍','PHP'=>'🐘','ND'=>'⬡','FE'=>'⚡','FS'=>'🚀','AI'=>'🤖'];
$colorMap= ['PY'=>'#3B82F6','PHP'=>'#8B5CF6','ND'=>'#10B981','FE'=>'#F59E0B','FS'=>'#EF4444','AI'=>'#2E7D32'];

if (!empty($liveCourses)) {
    $displayCourses = [];
    foreach ($liveCourses as $c) {
        $abbr = strtoupper(trim($c['abbreviation']));
        $c['icon']        = $iconMap[$abbr]  ?? '📚';
        $c['badge_color'] = $colorMap[$abbr] ?? '#2E7D32';
        $c['tags']        = [];
        $displayCourses[] = $c;
    }
} else {
    $displayCourses = $staticCourses;
}

// ── Page-specific CSS ─────────────────────────────────────
$extra_css = <<<'CSS'
<style>
/* ============================================================
   HOMEPAGE — Dazu Hub Institute
   All colors via CSS variables from layout.php
============================================================ */

/* ── HERO: two-column, compact ───────────────────────────── */
.hp-hero {
    padding: 52px 0 48px;
    border-bottom: 1px solid var(--border);
    background: var(--bg);
    position: relative; overflow: hidden;
}
/* Subtle dot grid */
.hp-hero::before {
    content: '';
    position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background-image: radial-gradient(var(--border) 1px, transparent 1px);
    background-size: 28px 28px;
    opacity: 0.6;
}
/* Green glow top-right */
.hp-hero::after {
    content: '';
    position: absolute; top: -80px; right: -80px;
    width: 360px; height: 360px; border-radius: 50%;
    background: radial-gradient(circle, rgba(46,125,50,.10), transparent 70%);
    pointer-events: none; z-index: 0;
}
.hp-hero-inner {
    position: relative; z-index: 1;
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 36px; align-items: start;
}

/* Left: institute info */
.hp-info {}
.hp-info h1 {
    font-size: clamp(1.75rem, 3.5vw, 2.8rem);
    font-weight: 800; line-height: 1.12;
    color: var(--text); margin: 10px 0 12px;
}
.hp-info h1 span { color: var(--dh-green); }
.hp-info .hp-sub {
    font-size: 0.9rem; color: var(--text-sub);
    line-height: 1.7; max-width: 420px; margin-bottom: 20px;
}

.hp-tracks {
    list-style: none; display: flex; flex-direction: column; gap: 6px;
    margin-bottom: 22px;
}
.hp-tracks li {
    display: flex; align-items: center; gap: 10px;
    font-size: 0.875rem; color: var(--text-sub);
}
.hp-tracks li .track-icon {
    width: 30px; height: 30px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.hp-tracks li .track-name { font-weight: 500; color: var(--text); }
.hp-tracks li .track-dur  { font-size: 0.75rem; color: var(--text-muted); margin-left: auto; }

.hp-trust {
    display: flex; flex-wrap: wrap; gap: 10px 20px;
}
.hp-trust-item {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.78rem; color: var(--text-muted);
}
.hp-trust-item i { color: var(--dh-green); font-size: 0.72rem; }

/* Right: compact apply form */
.hp-form-col {}
.hp-form-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px; overflow: hidden;
    box-shadow: var(--shadow-md);
}
.hp-form-head {
    padding: 16px 20px 12px;
    background: var(--dh-green);
    color: #fff;
}
.hp-form-head h2 { font-size: 1rem; font-weight: 800; margin-bottom: 2px; color: #fff; }
.hp-form-head p  { font-size: 0.75rem; opacity: 0.85; color: #fff; }

.hp-form-body { padding: 16px 20px 20px; }

/* Compact field */
.hf-field { margin-bottom: 11px; }
.hf-field label {
    display: block; font-size: 0.64rem; font-weight: 700;
    letter-spacing: 0.09em; text-transform: uppercase;
    color: var(--text-muted); margin-bottom: 4px;
}
.hf-field input, .hf-field select {
    width: 100%; padding: 9px 11px;
    background: var(--bg-input); border: 1px solid var(--border);
    border-radius: 7px; color: var(--text);
    font-family: var(--font-body); font-size: 0.85rem;
    transition: border-color var(--trans), box-shadow var(--trans);
    -webkit-appearance: none;
}
.hf-field input::placeholder { color: var(--text-muted); font-size: 0.8rem; }
.hf-field input:focus, .hf-field select:focus {
    outline: none; border-color: var(--dh-green);
    box-shadow: 0 0 0 2px rgba(46,125,50,0.1);
}
.hf-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

/* Mode */
.hf-mode { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; }
.hf-mode-opt input { position: absolute; opacity:0; width:0; height:0; }
.hf-mode-opt { position: relative; }
.hf-mode-opt label {
    display: flex; align-items: center; gap: 7px;
    padding: 8px 10px; border: 1.5px solid var(--border);
    border-radius: 7px; background: var(--bg-input);
    cursor: pointer; font-size: 0.78rem; color: var(--text-sub);
    font-weight: 500; letter-spacing: 0; text-transform: none;
    transition: all var(--trans);
}
.hf-mode-opt label strong { font-size: 0.8rem; color: var(--text); display: block; }
.hf-mode-opt label span  { font-size: 0.66rem; color: var(--text-muted); }
.hf-mode-opt input:checked + label {
    border-color: var(--dh-green); background: rgba(46,125,50,0.06);
}

/* Plan */
.hf-plan { display: grid; grid-template-columns: repeat(3,1fr); gap: 6px; }
.hf-plan-opt { position: relative; }
.hf-plan-opt input { position: absolute; opacity:0; width:0; height:0; }
.hf-plan-opt label {
    display: block; text-align: center; padding: 8px 4px;
    border: 1.5px solid var(--border); border-radius: 7px;
    background: var(--bg-input); cursor: pointer; transition: all var(--trans);
}
.hf-plan-opt input:checked + label {
    border-color: var(--dh-green); background: rgba(46,125,50,0.06);
}
.hf-plan-opt label strong { display: block; font-size: 0.75rem; font-weight: 700; color: var(--text); }
.hf-plan-opt label span   { font-size: 0.63rem; color: var(--text-muted); }

/* Fee compact */
.hf-fee {
    background: var(--bg-alt); border: 1px solid var(--border);
    border-radius: 7px; padding: 10px 12px; margin-bottom: 11px;
    font-size: 0.78rem;
}
.hf-fee-row { display: flex; justify-content: space-between; color: var(--text-muted); padding: 1px 0; }
.hf-fee-row strong { color: var(--text); }
.hf-fee-hr { border: none; border-top: 1px solid var(--border); margin: 6px 0; }
.hf-fee-total { display: flex; justify-content: space-between; align-items: center; font-size: 0.77rem; color: var(--text-muted); }
.hf-fee-total .amt { font-family: var(--font-display); font-size: 1.15rem; font-weight: 800; color: var(--dh-green); }

/* Upload */
.hf-upload {
    display: flex; align-items: center; gap: 7px;
    padding: 8px 11px; border: 1.5px dashed var(--border);
    border-radius: 7px; cursor: pointer; font-size: 0.78rem;
    color: var(--text-muted);
    transition: border-color var(--trans), color var(--trans);
}
.hf-upload:hover { border-color: var(--dh-green); color: var(--dh-green); }
.hf-upload i { color: #DC2626; }
#hp_id_doc { display: none; }

/* Submit */
.hf-submit {
    width: 100%; padding: 12px; margin-top: 2px;
    background: var(--dh-green); border: none; border-radius: 7px;
    color: #fff; font-family: var(--font-display); font-size: 0.9rem; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 7px;
    transition: background var(--trans), transform .15s;
    box-shadow: 0 4px 14px rgba(46,125,50,0.28);
}
.hf-submit:hover { background: var(--dh-green-dark); transform: translateY(-1px); }
.hf-submit:disabled { opacity:.6; cursor:not-allowed; transform:none; }

.hf-alert {
    padding: 10px 12px; border-radius: 7px; font-size: 0.82rem;
    margin-bottom: 11px; display: flex; gap: 8px;
}
.hf-alert-success { background: rgba(46,125,50,0.08); border: 1px solid rgba(46,125,50,0.2); color: var(--dh-green-dark); }
[data-theme="dark"] .hf-alert-success { color: var(--dh-green-light); }
.hf-alert-error { background: rgba(220,38,38,0.07); border: 1px solid rgba(220,38,38,0.2); color: #B91C1C; }
[data-theme="dark"] .hf-alert-error { color: #FCA5A5; }

/* ── STATS ────────────────────────────────────────────────── */
.hp-stats {
    padding: 28px 0;
    border-bottom: 1px solid var(--border);
    background: var(--bg-alt);
}
.hp-stats-inner {
    display: grid; grid-template-columns: repeat(4,1fr);
    gap: 0; text-align: center;
}
.hp-stat {
    padding: 8px 16px;
    border-right: 1px solid var(--border);
}
.hp-stat:last-child { border-right: none; }
.hp-stat-n {
    font-family: var(--font-display); font-size: 2rem;
    font-weight: 800; color: var(--dh-green); line-height: 1;
}
.hp-stat-l {
    font-size: 0.7rem; font-weight: 600; letter-spacing: 0.09em;
    text-transform: uppercase; color: var(--text-muted); margin-top: 5px;
}

/* ── COURSES GRID ─────────────────────────────────────────── */
.hp-courses { padding: 72px 0; }
.hp-courses-head { margin-bottom: 36px; }
.hp-courses-head h2 { font-size: clamp(1.5rem, 2.5vw, 2rem); font-weight: 800; }
.hp-courses-head h2 span { color: var(--dh-green); }
.hp-courses-head p { font-size: 0.9rem; color: var(--text-muted); margin-top: 6px; }

.hp-courses-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

/* Course card */
.hp-course-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    display: flex; flex-direction: column;
    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
}
.hp-course-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: rgba(46,125,50,0.3);
}

/* Course image */
.hp-course-img {
    width: 100%; aspect-ratio: 16/10;
    overflow: hidden; position: relative;
    background: var(--bg-alt);
}
.hp-course-img img {
    width: 100%; height: 100%; object-fit: cover;
    display: block;
    transition: transform .4s ease;
}
.hp-course-card:hover .hp-course-img img { transform: scale(1.04); }

/* No-image placeholder with gradient + icon */
.hp-course-img-placeholder {
    width: 100%; height: 100%;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 8px;
    font-size: 2.5rem;
}
.hp-course-img-abbr {
    font-family: var(--font-mono); font-size: 0.7rem; font-weight: 700;
    letter-spacing: .12em; color: rgba(255,255,255,.65);
}

/* Duration pill on image */
.hp-course-dur {
    position: absolute; bottom: 10px; left: 10px;
    padding: 4px 10px; border-radius: 99px;
    background: rgba(0,0,0,0.55); color: #fff;
    font-size: 0.7rem; font-weight: 600;
    backdrop-filter: blur(4px);
}

/* Card body */
.hp-course-body {
    padding: 16px 18px 18px;
    display: flex; flex-direction: column; flex: 1;
}
.hp-course-title {
    font-family: var(--font-display); font-size: 0.98rem; font-weight: 700;
    color: var(--text); margin-bottom: 7px; line-height: 1.3;
}
.hp-course-overview {
    font-size: 0.82rem; color: var(--text-muted);
    line-height: 1.6; margin-bottom: 12px; flex: 1;
}
.hp-course-tags { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 14px; }
.hp-course-tag {
    font-size: 0.62rem; font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
    padding: 3px 8px; border-radius: 4px;
    background: var(--bg-alt); border: 1px solid var(--border);
    color: var(--text-muted);
}
.hp-course-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding-top: 12px; border-top: 1px solid var(--border);
}
.hp-course-price {
    font-family: var(--font-display); font-size: 1rem; font-weight: 800; color: var(--text);
}
.hp-course-price small { display: block; font-size: 0.68rem; font-weight: 400; color: var(--text-muted); }

.hp-view-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 14px; border-radius: 7px;
    font-family: var(--font-display); font-size: 0.78rem; font-weight: 700;
    color: #fff; text-decoration: none; border: none;
    transition: opacity .2s, transform .15s;
}
.hp-view-btn:hover { opacity: .87; transform: translateY(-1px); color: #fff; }

/* ── HOW IT WORKS ─────────────────────────────────────────── */
.hp-how {
    padding: 60px 0 72px;
    background: var(--bg-alt);
    border-top: 1px solid var(--border);
}
.hp-how-head { margin-bottom: 36px; }
.hp-how-head h2 { font-size: clamp(1.5rem,2.5vw,2rem); font-weight: 800; }
.hp-how-head h2 span { color: var(--dh-green); }
.hp-how-head p { font-size: 0.9rem; color: var(--text-muted); margin-top: 6px; }

.hp-how-grid {
    display: grid; grid-template-columns: repeat(4,1fr);
    gap: 24px; position: relative;
}
.hp-how-grid::before {
    content: '';
    position: absolute; top: 25px; left: calc(12.5% + 12px); right: calc(12.5% + 12px);
    height: 1px; background: linear-gradient(90deg, var(--dh-green), var(--dh-green-light));
    opacity: 0.3;
}
.hp-step { text-align: center; position: relative; z-index: 1; }
.hp-step-num {
    width: 50px; height: 50px; border-radius: 50%;
    background: var(--dh-green); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-display); font-size: 1.1rem; font-weight: 800;
    margin: 0 auto 14px;
    box-shadow: 0 0 0 5px rgba(46,125,50,0.1);
    transition: transform .2s, box-shadow .2s;
}
.hp-step:hover .hp-step-num {
    transform: scale(1.08);
    box-shadow: 0 0 0 8px rgba(46,125,50,0.12);
}
.hp-step h3 { font-size: 0.9rem; font-weight: 700; color: var(--text); margin-bottom: 6px; }
.hp-step p  { font-size: 0.8rem; color: var(--text-muted); line-height: 1.6; }

/* ── RESPONSIVE ───────────────────────────────────────────── */
@media (max-width: 960px) {
    .hp-hero-inner { grid-template-columns: 1fr; }
    .hp-form-col { max-width: 520px; }
    .hp-courses-grid { grid-template-columns: repeat(2,1fr); }
    .hp-how-grid { grid-template-columns: repeat(2,1fr); }
    .hp-how-grid::before { display: none; }
    .hp-stats-inner { grid-template-columns: repeat(2,1fr); }
    .hp-stat { border-right: none; border-bottom: 1px solid var(--border); }
    .hp-stat:nth-child(odd) { border-right: 1px solid var(--border); }
    .hp-stat:last-child, .hp-stat:nth-last-child(2):nth-child(odd) { border-bottom: none; }
}
@media (max-width: 640px) {
    .hp-hero { padding: 36px 0 32px; }
    .hp-courses-grid { grid-template-columns: 1fr; }
    .hp-how-grid { grid-template-columns: 1fr; }
    .hf-row { grid-template-columns: 1fr; }
    .hf-plan { grid-template-columns: 1fr; gap: 6px; }
    .hp-stats-inner { grid-template-columns: repeat(2,1fr); }
}
</style>
CSS;

ob_start(); ?>

<!-- ═══ HERO: Left = institute info, Right = compact form ══ -->
<section class="hp-hero" id="home">
    <div class="dh-container hp-hero-inner">

        <!-- LEFT: institute info + course list -->
        <div class="hp-info dh-reveal">
            <span class="dh-eyebrow">Nairobi, Kenya — Est. 2021</span>
            <h1>Dazu Hub <span>Institute</span></h1>
            <p class="hp-sub">
                Professional tech training built for career changers and first-timers alike.
                Learn by building — no theory-heavy lectures, just hands-on projects from day one.
            </p>

            <ul class="hp-tracks">
                <?php
                $previewCourses = array_slice($displayCourses, 0, 6);
                foreach ($previewCourses as $c):
                    $abbr  = strtoupper(trim($c['abbreviation']));
                    $icon  = $iconMap[$abbr]  ?? ($c['icon'] ?? '📚');
                    $color = $colorMap[$abbr] ?? ($c['badge_color'] ?? '#2E7D32');
                ?>
                <li>
                    <span class="track-icon" style="background:<?php echo htmlspecialchars($color); ?>18;border:1px solid <?php echo htmlspecialchars($color); ?>30;">
                        <?php echo $icon; ?>
                    </span>
                    <span class="track-name"><?php echo htmlspecialchars($c['title']); ?></span>
                    <span class="track-dur"><?php echo (int)$c['duration_months']; ?> months</span>
                </li>
                <?php endforeach; ?>
            </ul>

            <div class="hp-trust">
                <span class="hp-trust-item"><i class="fas fa-check-circle"></i> Flexible payment plans</span>
                <span class="hp-trust-item"><i class="fas fa-check-circle"></i> Physical &amp; online</span>
                <span class="hp-trust-item"><i class="fas fa-check-circle"></i> Certificate on completion</span>
                <span class="hp-trust-item"><i class="fas fa-check-circle"></i> Nairobi-based</span>
            </div>
        </div>

        <!-- RIGHT: compact registration form -->
        <div class="hp-form-col dh-reveal dh-delay-2">
            <div class="hp-form-card">
                <div class="hp-form-head">
                    <h2><i class="fas fa-graduation-cap" style="margin-right:6px;"></i>Apply Now — July 2026</h2>
                    <p>Limited slots per course. Confirmation within 24 hours.</p>
                </div>
                <div class="hp-form-body">

                    <?php if ($reg_success): ?>
                    <div class="hf-alert hf-alert-success">
                        <i class="fas fa-check-circle" style="flex-shrink:0;margin-top:2px;"></i>
                        <div><strong>Application received.</strong> Our team will verify your payment and contact you within 24 hours.</div>
                    </div>
                    <?php else: ?>

                    <?php if ($reg_error): ?>
                    <div class="hf-alert hf-alert-error">
                        <i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:2px;"></i>
                        <div><?php echo htmlspecialchars($reg_error); ?></div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="hpForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="dh_register" value="1">

                        <div class="hf-row">
                            <div class="hf-field">
                                <label for="hp_name">Full Name *</label>
                                <input type="text" id="hp_name" name="full_name" required placeholder="Jane Wanjiku"
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                            </div>
                            <div class="hf-field">
                                <label for="hp_phone">Phone *</label>
                                <input type="tel" id="hp_phone" name="phone" required placeholder="07XX XXX XXX"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="hf-field">
                            <label for="hp_email">Email *</label>
                            <input type="email" id="hp_email" name="email" required placeholder="jane@email.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="hf-field">
                            <label for="hp_course">Course *</label>
                            <select id="hp_course" name="course_id" required>
                                <option value="">— Select a course —</option>
                                <?php foreach ($displayCourses as $c): ?>
                                <option value="<?php echo (int)($c['id'] ?? 0); ?>"
                                        data-fee="<?php echo (float)$c['course_fee']; ?>"
                                        data-months="<?php echo (int)$c['duration_months']; ?>"
                                        <?php echo (isset($_POST['course_id']) && (int)$_POST['course_id']===(int)($c['id']??0)) ? 'selected':''; ?>>
                                    <?php echo htmlspecialchars($c['title']); ?> — KSh <?php echo number_format($c['course_fee']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="hf-field">
                            <label>Study Mode *</label>
                            <div class="hf-mode">
                                <div class="hf-mode-opt">
                                    <input type="radio" id="hp_online" name="study_mode" value="online" required
                                           <?php echo (!isset($_POST['study_mode'])||$_POST['study_mode']==='online')?'checked':''; ?>>
                                    <label for="hp_online">
                                        💻
                                        <span class="mode-txt">
                                            <strong>Online</strong>
                                            <span>KSh 1,000 fee</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="hf-mode-opt">
                                    <input type="radio" id="hp_physical" name="study_mode" value="physical"
                                           <?php echo (isset($_POST['study_mode'])&&$_POST['study_mode']==='physical')?'checked':''; ?>>
                                    <label for="hp_physical">
                                        🏫
                                        <span class="mode-txt">
                                            <strong>Physical</strong>
                                            <span>KSh 1,500 + ID</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="hf-field">
                            <label>Payment Plan *</label>
                            <div class="hf-plan">
                                <div class="hf-plan-opt">
                                    <input type="radio" id="hp_mo" name="payment_plan" value="monthly" checked>
                                    <label for="hp_mo"><strong>Monthly</strong><span id="hp_hint_mo">—</span></label>
                                </div>
                                <div class="hf-plan-opt">
                                    <input type="radio" id="hp_3mo" name="payment_plan" value="3months">
                                    <label for="hp_3mo"><strong>3-Month</strong><span id="hp_hint_3mo">—</span></label>
                                </div>
                                <div class="hf-plan-opt">
                                    <input type="radio" id="hp_full" name="payment_plan" value="full">
                                    <label for="hp_full"><strong>Full</strong><span id="hp_hint_full">—</span></label>
                                </div>
                            </div>
                        </div>

                        <div class="hf-fee" id="hpFeeBox">
                            <div class="hf-fee-row"><span>Reg. fee</span><strong id="hp_rfee">KSh 1,000</strong></div>
                            <div class="hf-fee-row"><span id="hp_ilabel">Select a course</span><strong id="hp_iamt">—</strong></div>
                            <hr class="hf-fee-hr">
                            <div class="hf-fee-total">
                                <span>Pay now</span>
                                <span class="amt" id="hp_total">KSh 1,000</span>
                            </div>
                        </div>

                        <div style="background:rgba(46,125,50,0.05);border:1px solid rgba(46,125,50,0.18);border-radius:7px;padding:9px 12px;margin-bottom:11px;font-size:0.76rem;color:var(--text-sub);">
                            <span style="font-size:0.63rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--dh-green);display:block;margin-bottom:4px;">
                                <i class="fas fa-mobile-screen-button"></i> NCBA Paybill
                            </span>
                            Paybill: <strong style="color:var(--dh-green);">[No.]</strong> &nbsp;&middot;&nbsp; Account: <strong style="color:var(--dh-green);">[No.]</strong>
                        </div>

                        <div class="hf-field">
                            <label for="hp_mpesa">M-Pesa Name / Code *</label>
                            <input type="text" id="hp_mpesa" name="payment_reference" required
                                   placeholder="Jane Wanjiku or QGH7XXXX"
                                   value="<?php echo htmlspecialchars($_POST['payment_reference'] ?? ''); ?>">
                        </div>

                        <div class="hf-field">
                            <label>ID / Result Slip (PDF, max 5 MB) *</label>
                            <label class="hf-upload" for="hp_id_doc" id="hpUploadLabel">
                                <i class="fas fa-file-pdf"></i>
                                <span id="hpUploadText">Upload PDF</span>
                            </label>
                            <input type="file" id="hp_id_doc" name="id_document" accept="application/pdf" required>
                        </div>

                        <button type="submit" class="hf-submit" id="hpSubmitBtn">
                            <i class="fas fa-paper-plane"></i> Submit Application
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ STATS ════════════════════════════════════════════════ -->
<div class="hp-stats">
    <div class="dh-container hp-stats-inner">
        <div class="hp-stat">
            <div class="hp-stat-n dh-count" data-target="300">0</div>
            <div class="hp-stat-l">Students Trained</div>
        </div>
        <div class="hp-stat">
            <div class="hp-stat-n dh-count" data-target="6">0</div>
            <div class="hp-stat-l">Course Tracks</div>
        </div>
        <div class="hp-stat">
            <div class="hp-stat-n dh-count" data-target="92">0</div>
            <div class="hp-stat-l">% Placement Rate</div>
        </div>
        <div class="hp-stat">
            <div class="hp-stat-n dh-count" data-target="4">0</div>
            <div class="hp-stat-l">Years Training</div>
        </div>
    </div>
</div>

<!-- ═══ COURSES ══════════════════════════════════════════════ -->
<section class="hp-courses" id="courses">
    <div class="dh-container">
        <div class="hp-courses-head dh-reveal">
            <span class="dh-eyebrow">What you'll learn</span>
            <h2>Our <span>Course Tracks</span></h2>
            <p>Click any course to see the full curriculum, payment plans, and apply directly.</p>
        </div>

        <div class="hp-courses-grid">
            <?php foreach ($displayCourses as $i => $course):
                $abbr    = strtoupper(trim($course['abbreviation']));
                $icon    = $iconMap[$abbr]  ?? ($course['icon'] ?? '📚');
                $color   = $colorMap[$abbr] ?? ($course['badge_color'] ?? '#2E7D32');
                // cover_image is stored as a full web path by upload_image(),
                // e.g. "/Registration-System/public/uploads/courses/covers/file.jpg"
                // Do NOT prepend anything — use it directly.
                $hasImg  = !empty($course['cover_image']);
                $imgSrc  = $hasImg ? $course['cover_image'] : null;
                $tags    = $course['tags'] ?? [];
                // Link: if real DB course (id > 0), go to detail page; else scroll to form
                $detailLink = ((int)($course['id'] ?? 0) > 0)
                    ? '/Registration-System/public/courses/view.php?id=' . (int)$course['id']
                    : '#home';
            ?>
            <div class="hp-course-card dh-reveal dh-delay-<?php echo ($i % 3) + 1; ?>">
                <!-- Image -->
                <div class="hp-course-img">
                    <?php if ($imgSrc): ?>
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                             alt="<?php echo htmlspecialchars($course['title']); ?>"
                             loading="lazy"
                             onerror="this.parentNode.innerHTML='<div class=\'hp-course-img-placeholder\' style=\'background:<?php echo addslashes($color); ?>18;\'><span><?php echo $icon; ?></span><span class=\'hp-course-img-abbr\'><?php echo $abbr; ?></span></div>'">
                    <?php else: ?>
                        <div class="hp-course-img-placeholder"
                             style="background:linear-gradient(135deg,<?php echo htmlspecialchars($color); ?>14,<?php echo htmlspecialchars($color); ?>28);">
                            <span><?php echo $icon; ?></span>
                            <span class="hp-course-img-abbr"><?php echo htmlspecialchars($abbr); ?></span>
                        </div>
                    <?php endif; ?>
                    <span class="hp-course-dur">
                        <i class="fas fa-clock" style="margin-right:4px;"></i>
                        <?php echo (int)$course['duration_months']; ?> months
                    </span>
                </div>

                <!-- Body -->
                <div class="hp-course-body">
                    <div class="hp-course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                    <div class="hp-course-overview">
                        <?php echo htmlspecialchars(mb_strimwidth($course['overview'] ?? '', 0, 95, '…')); ?>
                    </div>
                    <?php if (!empty($tags)): ?>
                    <div class="hp-course-tags">
                        <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                            <span class="hp-course-tag"><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="hp-course-footer">
                        <div class="hp-course-price">
                            KSh <?php echo number_format($course['course_fee']); ?>
                            <small>From KSh <?php echo number_format($course['course_fee'] / max(1,(int)$course['duration_months'])); ?>/mo</small>
                        </div>
                        <a href="<?php echo $detailLink; ?>"
                           class="hp-view-btn"
                           style="background:<?php echo htmlspecialchars($color); ?>;">
                            View Details <i class="fas fa-arrow-right" style="font-size:.68rem;"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══ HOW IT WORKS ════════════════════════════════════════ -->
<section class="hp-how" id="how-it-works">
    <div class="dh-container">
        <div class="hp-how-head dh-reveal">
            <span class="dh-eyebrow">Simple process</span>
            <h2>From Application <span>to Enrolled</span></h2>
            <p>Four steps from your first click to your first class.</p>
        </div>
        <div class="hp-how-grid">
            <?php
            $steps = [
                ['1','Pick a Course', 'Browse tracks above and click View Details to see the full curriculum and fees.'],
                ['2','Fill the Form', 'Submit your details, pick physical or online, and choose a payment plan.'],
                ['3','Pay & Submit',  'Pay via NCBA paybill, enter your M-Pesa reference, upload your ID as PDF.'],
                ['4','Get Approved',  'Admin verifies your payment and sends your Student ID and class start details.'],
            ];
            foreach ($steps as $i => $s): ?>
            <div class="hp-step dh-reveal dh-delay-<?php echo $i+1; ?>">
                <div class="hp-step-num"><?php echo $s[0]; ?></div>
                <h3><?php echo $s[1]; ?></h3>
                <p><?php echo $s[2]; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php $content = ob_get_clean(); ?>

<?php
$extra_js = <<<'JS'
<script>
(function(){
    'use strict';

    /* Counter */
    var cio = new IntersectionObserver(function(entries){
        entries.forEach(function(e){
            if (!e.isIntersecting) return;
            var el = e.target, t = parseInt(el.dataset.target,10);
            if (isNaN(t)) return;
            var s=null, d=1400;
            function step(ts){
                if(!s) s=ts;
                var p=Math.min((ts-s)/d,1), ease=1-Math.pow(1-p,3);
                el.textContent=Math.floor(ease*t);
                if(p<1) requestAnimationFrame(step); else el.textContent=t;
            }
            requestAnimationFrame(step);
            cio.unobserve(el);
        });
    },{threshold:.5});
    document.querySelectorAll('.dh-count').forEach(function(el){ cio.observe(el); });

    /* Fee calc */
    function fmt(n){ return 'KSh '+Math.round(n).toLocaleString(); }
    var sel = document.getElementById('hp_course');

    function recalc(){
        var opt = sel && sel.options[sel.selectedIndex];
        var fee = opt ? parseFloat(opt.dataset.fee)||0 : 0;
        var mo  = opt ? parseInt(opt.dataset.months)||1 : 1;
        var plan='monthly', mode='online';
        document.querySelectorAll('#hpForm input[name="payment_plan"]').forEach(function(r){ if(r.checked) plan=r.value; });
        document.querySelectorAll('#hpForm input[name="study_mode"]').forEach(function(r){ if(r.checked) mode=r.value; });

        var regFee = mode==='physical' ? 1500 : 1000;
        var count  = plan==='monthly' ? Math.max(1,mo) : (plan==='3months' ? 3 : 1);
        var inst   = fee > 0 ? fee/count : 0;
        var label  = fee===0 ? 'Select a course above' : (plan==='full' ? 'Full course fee' : 'First of '+count+' installment'+(count>1?'s':''));

        function s(id,v){ var e=document.getElementById(id); if(e) e.textContent=v; }
        s('hp_rfee',   fmt(regFee));
        s('hp_ilabel', label);
        s('hp_iamt',   fee>0 ? fmt(inst) : '—');
        s('hp_total',  fee>0 ? fmt(regFee+inst) : fmt(regFee));

        if (fee > 0) {
            s('hp_hint_mo',  mo>0 ? fmt(fee/Math.max(1,mo))+'/mo' : '—');
            s('hp_hint_3mo', fmt(fee/3));
            s('hp_hint_full',fmt(fee));
        }
    }

    if (sel) sel.addEventListener('change', recalc);
    document.querySelectorAll('#hpForm input[name="payment_plan"], #hpForm input[name="study_mode"]')
            .forEach(function(r){ r.addEventListener('change', recalc); });
    recalc();

    /* Upload label */
    var inp = document.getElementById('hp_id_doc');
    var txt = document.getElementById('hpUploadText');
    if (inp && txt) inp.addEventListener('change', function(){
        txt.textContent = inp.files[0] ? inp.files[0].name : 'Upload PDF';
    });

    /* Submit state */
    var form = document.getElementById('hpForm');
    if (form) form.addEventListener('submit', function(){
        var b=document.getElementById('hpSubmitBtn');
        if(b){ b.disabled=true; b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Submitting…'; }
    });
})();
</script>
JS;

require __DIR__ . '/templates/public/layout.php';