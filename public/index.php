<?php
// public/index.php — Dazu Hub Institute homepage
$page_title       = 'Dazu Hub Tech Skills Institute — Upgrade Your Tech Skills';
$page_description = 'Practical, hands-on training in web development, cybersecurity, data analytics, and AI. Build your future with Dazu Hub in Nairobi, Kenya.';

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config/db_connect.php';
require_once __DIR__ . '/../app/helpers/uploads.php';
require_once __DIR__ . '/../app/models/Connection.php';
require_once __DIR__ . '/../app/models/CourseModel.php';
require_once __DIR__ . '/../app/models/RegistrationModel.php';

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
                // Admin follows up manually — no confirmation email.
            }
            if (!$result['ok']) $reg_error = $result['error'];
        }
    }
}

// ── Course data ───────────────────────────────────────────
$staticCourses = [
    ['id'=>0,'title'=>'Software / Web Development','abbreviation'=>'WD',
     'overview'=>'Learn to build modern, responsive websites and full-stack applications.',
     'description'=>'','duration_months'=>6,'course_fee'=>45000,'cover_image'=>null,
     'fa_icon'=>'fa-code','card_image'=>'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=700&q=80',
     'rating'=>'4.8','tags'=>['HTML/CSS','JavaScript','React'],'is_active'=>1],
    ['id'=>0,'title'=>'Cybersecurity','abbreviation'=>'CY',
     'overview'=>'Protect systems and networks with practical security skills and ethical hacking.',
     'description'=>'','duration_months'=>5,'course_fee'=>42000,'cover_image'=>null,
     'fa_icon'=>'fa-lock','card_image'=>'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=700&q=80',
     'rating'=>'4.9','tags'=>['Network Security','Ethical Hacking'],'is_active'=>1],
    ['id'=>0,'title'=>'Data Analytics','abbreviation'=>'DA',
     'overview'=>'Turn raw data into insights using Python, SQL, and visualization tools.',
     'description'=>'','duration_months'=>5,'course_fee'=>40000,'cover_image'=>null,
     'fa_icon'=>'fa-chart-column','card_image'=>'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=700&q=80',
     'rating'=>'4.7','tags'=>['Python','SQL','Power BI'],'is_active'=>1],
    ['id'=>0,'title'=>'AI & Machine Learning','abbreviation'=>'AI',
     'overview'=>'Build intelligent systems with practical AI and machine learning projects.',
     'description'=>'','duration_months'=>6,'course_fee'=>50000,'cover_image'=>null,
     'fa_icon'=>'fa-brain','card_image'=>'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=700&q=80',
     'rating'=>'4.8','tags'=>['Python','TensorFlow','NLP'],'is_active'=>1],
];

$faIconMap = ['PY'=>'fa-code','PHP'=>'fa-code','ND'=>'fa-server','FE'=>'fa-code','FS'=>'fa-laptop-code',
              'AI'=>'fa-brain','WD'=>'fa-code','CY'=>'fa-lock','DA'=>'fa-chart-column'];
$stockImages = [
    'WD'=>'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=700&q=80',
    'CY'=>'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=700&q=80',
    'DA'=>'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=700&q=80',
    'AI'=>'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=700&q=80',
    'PY'=>'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=700&q=80',
    'PHP'=>'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=700&q=80',
    'ND'=>'https://images.unsplash.com/photo-1627398242454-45a1465c2479?w=700&q=80',
    'FE'=>'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=700&q=80',
    'FS'=>'https://images.unsplash.com/photo-1517694712202-14dd9538aa7e?w=700&q=80',
];

if (!empty($liveCourses)) {
    $displayCourses = [];
    foreach ($liveCourses as $c) {
        $abbr = strtoupper(trim($c['abbreviation']));
        $c['fa_icon']     = $faIconMap[$abbr] ?? 'fa-book';
        $c['card_image']  = !empty($c['cover_image']) ? $c['cover_image'] : ($stockImages[$abbr] ?? $stockImages['WD']);
        $c['rating']      = $c['rating'] ?? '4.8';
        $c['tags']        = $c['tags'] ?? [];
        $displayCourses[] = $c;
    }
} else {
    $displayCourses = $staticCourses;
}

// ── Page assets & markup ──────────────────────────────────
$extra_css = '<link rel="stylesheet" href="/Registration-System/public/assets/css/homepage.css">';

ob_start();
include __DIR__ . '/components/public/homepage_markup.php';
$content = ob_get_clean();

$extra_js = <<<'JS'
<script>
(function(){
    'use strict';

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