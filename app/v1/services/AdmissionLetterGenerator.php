<?php
// app/v1/services/AdmissionLetterGenerator.php
// Official 2-Page Admission Letter and Fee Structure document matching Dazu Training Institute design.

require_once __DIR__ . '/../helpers/currency.php';
require_once __DIR__ . '/../helpers/site_logo.php';
require_once __DIR__ . '/../helpers/course_duration.php';
require_once __DIR__ . '/../helpers/course_fee_structure.php';

if (!class_exists('AdmissionLetterGenerator')) {
class AdmissionLetterGenerator
{
    public static function fileName(array $reg): string
    {
        $regNo = trim((string) ($reg['registration_no'] ?? $reg['admission_no'] ?? $reg['id'] ?? 'admission'));
        $clean = preg_replace('/[^A-Za-z0-9_-]+/', '-', $regNo);
        return 'Admission-Letter-and-Fee-Structure-' . trim($clean, '-') . '.pdf';
    }

    public static function html(array $reg, array $course = []): string
    {
        // 1. Resolve Course Details
        if ((empty($course['title']) || empty($course['course_fee'])) && !empty($reg['course_id'])) {
            try {
                if (function_exists('app_pdo')) {
                    $pdo = app_pdo();
                } elseif (class_exists('Models\\Connection')) {
                    $pdo = \Models\Connection::getInstance()->getConnection();
                }
                if (isset($pdo) && $pdo) {
                    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
                    $stmt->execute([':id' => (int) $reg['course_id']]);
                    $found = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($found) {
                        $course = array_merge($found, $course);
                    }
                }
            } catch (\Throwable $e) {
                // non-fatal
            }
        }

        // 2. Resolve Student & Admission Details
        $studentName = trim((string) ($reg['full_name'] ?? $reg['name'] ?? 'Student'));
        $studentPhone = trim((string) ($reg['phone'] ?? '—'));
        $studentEmail = trim((string) ($reg['email'] ?? $reg['login_email'] ?? $reg['student_email'] ?? ''));
        if ($studentEmail === '' || $studentEmail === '—') {
            try {
                if (!isset($pdo)) {
                    if (function_exists('app_pdo')) {
                        $pdo = app_pdo();
                    } elseif (class_exists('Models\\Connection')) {
                        $pdo = \Models\Connection::getInstance()->getConnection();
                    }
                }
                if (isset($pdo) && $pdo) {
                    if (!empty($reg['id'])) {
                        $stmt = $pdo->prepare('SELECT email FROM registrations WHERE id = :id LIMIT 1');
                        $stmt->execute([':id' => (int) $reg['id']]);
                        $f = $stmt->fetchColumn();
                        if ($f) $studentEmail = trim((string) $f);
                    }
                    if (($studentEmail === '' || $studentEmail === '—') && !empty($reg['user_id'])) {
                        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
                        $stmt->execute([':id' => (int) $reg['user_id']]);
                        $f = $stmt->fetchColumn();
                        if ($f) $studentEmail = trim((string) $f);
                    }
                }
            } catch (\Throwable $e) {}
        }
        if ($studentEmail === '') {
            $studentEmail = '—';
        }
        $courseTitle = trim((string) ($course['title'] ?? $reg['course_title'] ?? 'Technical Programme'));
        $abbr = strtoupper(trim((string) ($course['abbreviation'] ?? $reg['abbreviation'] ?? '')));
        $programmeTitle = $courseTitle . ($abbr !== '' ? ' (' . $abbr . ')' : '');

        // Registration / Admission Number
        $regNo = trim((string) ($reg['registration_no'] ?? $reg['admission_no'] ?? $reg['admission_number'] ?? $reg['username'] ?? ''));
        if ($regNo === '' && !empty($reg['id'])) {
            try {
                if (isset($pdo) && $pdo) {
                    $stmt = $pdo->prepare('SELECT registration_no FROM registrations WHERE id = :id LIMIT 1');
                    $stmt->execute([':id' => (int) $reg['id']]);
                    $f = $stmt->fetchColumn();
                    if ($f) $regNo = trim((string) $f);
                }
            } catch (\Throwable $e) {}
        }
        $admNo = $regNo !== '' ? $regNo : 'DH' . str_pad((string)($reg['id'] ?? 1), 3, '0', STR_PAD_LEFT) . '/' . date('y');

        // Credentials
        $tempPassword = trim((string) ($reg['temp_password'] ?? ''));
        $username = trim((string) ($reg['username'] ?? $admNo));

        // Intake & Dates
        $rawCreated = $reg['created_at'] ?? $reg['reviewed_at'] ?? date('Y-m-d');
        $intake = !empty($reg['intake']) ? $reg['intake'] : date('F Y', strtotime($rawCreated));
        $dateIssued = !empty($reg['reviewed_at']) ? date('d F Y', strtotime($reg['reviewed_at'])) : date('d F Y');

        // Course Overview
        $overview = trim((string) ($course['overview'] ?? $course['description'] ?? ''));
        if ($overview === '') {
            $overview = 'The programme is designed to equip you with practical and professional knowledge in modern technology, problem solving, and industry skills. We look forward to supporting your academic and professional development throughout your studies.';
        }

        // 3. Fee Structure Computations
        $months = (int) ($course['duration_months'] ?? ($reg['duration_months'] ?? 0));
        if ($months <= 0) {
            $rawDur = (string) ($course['duration'] ?? ($reg['duration'] ?? ''));
            if (preg_match('/(\d+)\s*month/i', $rawDur, $m)) {
                $months = (int) $m[1];
            }
        }
        if ($months <= 0) {
            $months = 18;
        }
        $course['duration_months'] = $months;
        $isOneMonth = course_is_short_course($course);
        $semesters = course_semesters_count($course);
        $durationFormatted = $months > 0 ? ($months . ' month' . ($months === 1 ? '' : 's')) : '18 months';
        $semesterLengthText = $isOneMonth ? 'Full course (1 month)' : '3 months per semester';

        $modes = function_exists('course_fee_structure_modes_decode')
            ? course_fee_structure_modes_decode($course['fee_structure'] ?? [])
            : [];
        $studyMode = ($reg['study_mode'] ?? '') === 'physical' ? 'physical' : 'online';
        $activeMode = $modes[$studyMode] ?? $modes['online'] ?? $modes['physical'] ?? [];
        if (function_exists('course_fee_mode_total') && course_fee_mode_total($activeMode) <= 0) {
            $activeMode = ['tuition_total' => (float) ($course['course_fee'] ?? 0), 'per_semester' => 0, 'additional' => []];
        }

        $perSemester = course_fee_mode_per_semester($course, $activeMode);
        $totalFee = course_fee_mode_total($activeMode);
        if ($totalFee <= 0) {
            $totalFee = (float) ($course['course_fee'] ?? ($isOneMonth ? 20000.0 : ($perSemester * $semesters)));
        }

        // Fee rows
        $feeRowsHtml = '';
        if ($isOneMonth) {
            $feeRowsHtml .= '<tr style="border-bottom:1px solid #e2e8f0;">'
                . '<td style="padding:10px 14px; font-size:12px; color:#0f172a;">Course Tuition (1 Month)</td>'
                . '<td style="padding:10px 14px; font-size:12px; font-weight:800; text-align:right; color:#0f172a;">' . htmlspecialchars(format_price($totalFee)) . '</td>'
                . '</tr>';
        } else {
            for ($i = 1; $i <= $semesters; $i++) {
                $ord = match ($i) {
                    1 => '1st Semester',
                    2 => '2nd Semester',
                    3 => '3rd Semester',
                    4 => '4th Semester',
                    5 => '5th Semester',
                    6 => '6th Semester',
                    default => "Semester {$i}",
                };
                $feeRowsHtml .= '<tr style="border-bottom:1px solid #e2e8f0;">'
                    . '<td style="padding:10px 14px; font-size:12px; color:#0f172a;">' . htmlspecialchars($ord) . '</td>'
                    . '<td style="padding:10px 14px; font-size:12px; font-weight:800; text-align:right; color:#0f172a;">' . htmlspecialchars(format_price($perSemester)) . '</td>'
                    . '</tr>';
            }
        }
        foreach ($activeMode['additional'] ?? [] as $add) {
            $amt = (float) ($add['amount'] ?? 0);
            if ($amt > 0) {
                $feeRowsHtml .= '<tr style="border-bottom:1px solid #e2e8f0;">'
                    . '<td style="padding:10px 14px; font-size:12px; color:#0f172a;">' . htmlspecialchars((string) $add['label']) . '</td>'
                    . '<td style="padding:10px 14px; font-size:12px; font-weight:800; text-align:right; color:#0f172a;">' . htmlspecialchars(format_price($amt)) . '</td>'
                    . '</tr>';
            }
        }

        // Monthly option
        $monthlyAmount = $isOneMonth ? 0 : round($totalFee / max(1, $months), 2);

        // Payment details
        $payment = function_exists('payment_credentials')
            ? payment_credentials()
            : ['business_short_code' => '880100', 'account_number' => '681158', 'phone' => '0722 922 454'];
        $paybill = (string) ($payment['business_short_code'] ?? $payment['paybill'] ?? '880100');
        $account = (string) ($payment['account_number'] ?? $payment['account'] ?? '681158');
        $phoneContact = (string) ($payment['phone'] ?? '0722 922 454');

        ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page {
    margin: 34px 45px 34px 45px;
  }
  body {
    font-family: Helvetica, Arial, sans-serif;
    color: #0f172a;
    font-size: 11.5px;
    line-height: 1.5;
    margin: 0;
    padding: 0;
  }
  .page {
    position: relative;
    box-sizing: border-box;
    min-height: 980px;
  }
  .page-break {
    page-break-before: always;
  }
  table {
    width: 100%;
    border-collapse: collapse;
  }
  .header-table {
    margin-bottom: 22px;
  }
  .brand-title {
    font-size: 18px;
    font-weight: 900;
    color: #0f172a;
    letter-spacing: 0.5px;
  }
  .admissions-office {
    font-size: 9.5px;
    font-weight: 700;
    color: #0d9488;
    letter-spacing: 3px;
    margin-top: 3px;
  }
  .dti-logo {
    font-size: 26px;
    font-weight: 900;
    letter-spacing: -0.5px;
  }
  .main-title {
    font-size: 24px;
    font-weight: 900;
    color: #0f172a;
    letter-spacing: 1px;
    margin: 0 0 6px 0;
  }
  .intake-subtitle {
    font-size: 11px;
    font-weight: 800;
    color: #0d9488;
    letter-spacing: 2px;
    text-transform: uppercase;
  }
  .meta-label {
    font-size: 9px;
    font-weight: 700;
    color: #94a3b8;
    letter-spacing: 1px;
    text-transform: uppercase;
  }
  .meta-val-large {
    font-size: 16px;
    font-weight: 900;
    color: #0f172a;
    margin-top: 3px;
  }
  .meta-val-date {
    font-size: 13.5px;
    font-weight: 800;
    color: #0f172a;
    margin-top: 3px;
  }
  .details-box {
    background: #f8fafc;
    border-radius: 8px;
    padding: 13px 18px;
    margin: 18px 0;
  }
  .field-label {
    font-size: 9px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
  }
  .field-val {
    font-size: 12px;
    font-weight: 800;
    color: #0f172a;
  }
  .footer-bar {
    position: absolute;
    bottom: 0px;
    left: 0px;
    right: 0px;
    border-top: 1px solid #e2e8f0;
    padding-top: 8px;
    font-size: 10px;
    color: #94a3b8;
  }
</style>
</head>
<body>

<!-- ==================== PAGE 1: ADMISSION LETTER ==================== -->
<div class="page">
  <!-- Top Header -->
  <table class="header-table">
    <tr>
      <td style="vertical-align:top;">
        <div class="brand-title">DAZU TRAINING INSTITUTE</div>
        <div class="admissions-office">A D M I S S I O N S &nbsp; O F F I C E</div>
      </td>
      <td style="vertical-align:top; text-align:right;">
        <div class="dti-logo"><span style="color:#0284c7;">D</span><span style="color:#0d9488;">TI</span></div>
      </td>
    </tr>
  </table>

  <!-- Centered Title -->
  <div style="text-align:center; margin: 18px 0 22px;">
    <h1 class="main-title">ADMISSION LETTER</h1>
    <div class="intake-subtitle"><?= htmlspecialchars(strtoupper($intake)) ?> INTAKE</div>
  </div>

  <!-- Meta Row: Admission Number & Date -->
  <table style="margin-bottom: 20px;">
    <tr>
      <td style="vertical-align:top; width:50%;">
        <div class="meta-label">ADMISSION NUMBER</div>
        <div class="meta-val-large"><?= htmlspecialchars($admNo) ?></div>
      </td>
      <td style="vertical-align:top; width:50%; text-align:right;">
        <div class="meta-label">DATE ISSUED</div>
        <div class="meta-val-date"><?= htmlspecialchars($dateIssued) ?></div>
      </td>
    </tr>
  </table>

  <!-- Dear Student -->
  <p style="font-size:12px; font-weight:700; color:#0f172a; margin:0 0 12px;">Dear <?= htmlspecialchars($studentName) ?>,</p>

  <!-- Body paragraphs -->
  <p style="font-size:11.5px; line-height:1.65; color:#334155; margin:0 0 10px;">
    We are pleased to offer you admission to <strong>Dazu Training Institute</strong> for the <strong><?= htmlspecialchars($intake) ?></strong> intake. Your application has been successful, and you are hereby admitted to the <strong><?= htmlspecialchars($programmeTitle) ?></strong> programme.
  </p>
  <p style="font-size:11.5px; line-height:1.65; color:#334155; margin:0 0 16px;">
    <?= htmlspecialchars($overview) ?>
  </p>

  <!-- Student Details & Credentials Card -->
  <div class="details-box">
    <table>
      <tr>
        <td style="width:50%; padding:5px 10px 5px 0; vertical-align:top;">
          <div class="field-label">STUDENT NAME</div>
          <div class="field-val"><?= htmlspecialchars($studentName) ?></div>
        </td>
        <td style="width:50%; padding:5px 0 5px 10px; vertical-align:top;">
          <div class="field-label">STUDENT PHONE</div>
          <div class="field-val"><?= htmlspecialchars($studentPhone) ?></div>
        </td>
      </tr>
      <tr>
        <td style="padding:5px 10px 5px 0; vertical-align:top;">
          <div class="field-label">COURSE</div>
          <div class="field-val"><?= htmlspecialchars($courseTitle) ?></div>
        </td>
        <td style="padding:5px 0 5px 10px; vertical-align:top;">
          <div class="field-label">INTAKE</div>
          <div class="field-val"><?= htmlspecialchars($intake) ?></div>
        </td>
      </tr>
      <tr>
        <td style="padding:5px 10px 5px 0; vertical-align:top;">
          <div class="field-label">STUDENT EMAIL / LOGIN</div>
          <div class="field-val"><?= htmlspecialchars($studentEmail) ?></div>
        </td>
        <td style="padding:5px 0 5px 10px; vertical-align:top;">
          <div class="field-label">REGISTRATION NUMBER</div>
          <div class="field-val"><?= htmlspecialchars($admNo) ?></div>
        </td>
      </tr>
      <?php if ($tempPassword !== ''): ?>
      <tr>
        <td style="padding:5px 10px 5px 0; vertical-align:top;">
          <div class="field-label">PORTAL USERNAME</div>
          <div class="field-val"><?= htmlspecialchars($username) ?></div>
        </td>
        <td style="padding:5px 0 5px 10px; vertical-align:top;">
          <div class="field-label">TEMPORARY PASSWORD</div>
          <div class="field-val" style="color:#0d9488; font-family:monospace;"><?= htmlspecialchars($tempPassword) ?></div>
        </td>
      </tr>
      <?php endif; ?>
    </table>
  </div>

  <!-- Enrolment and commencement -->
  <div style="font-size:12px; font-weight:800; color:#0d9488; margin:0 0 6px;">Enrolment and commencement</div>
  <p style="font-size:11.5px; line-height:1.65; color:#334155; margin:0 0 8px;">
    Please retain this letter as your official admission confirmation. You are expected to complete the enrolment process and comply with the Institute’s academic and administrative requirements before commencement of classes. The available class schedules are <strong>8:00 a.m., 12:00 noon, and 6:00 p.m.</strong>
  </p>
  <p style="font-size:11.5px; line-height:1.65; color:#334155; margin:0 0 16px;">
    We congratulate you on your admission and wish you every success in your studies.
  </p>

  <p style="font-size:12px; color:#0f172a; margin:0 0 14px;">Yours faithfully,</p>
  <p style="font-size:12.5px; font-weight:800; color:#0f172a; margin:0;">
    Admissions Office <span style="font-weight:normal; color:#475569;">Dazu Training Institute</span>
  </p>

  <!-- Footer Page 1 -->
  <div class="footer-bar">
    <table>
      <tr>
        <td style="text-align:left;">dazuhub.ac.ke</td>
        <td style="text-align:right;">Dazu Training Institute &bull; <?= htmlspecialchars($intake) ?></td>
      </tr>
    </table>
  </div>
</div>

<!-- ==================== PAGE BREAK ==================== -->
<div class="page-break"></div>

<!-- ==================== PAGE 2: FEE STRUCTURE ==================== -->
<div class="page">
  <!-- Top Header -->
  <table class="header-table">
    <tr>
      <td style="vertical-align:top;">
        <div class="brand-title">DAZU TRAINING INSTITUTE</div>
        <div class="admissions-office">A D M I S S I O N S &nbsp; O F F I C E</div>
      </td>
      <td style="vertical-align:top; text-align:right;">
        <div class="dti-logo"><span style="color:#0284c7;">D</span><span style="color:#0d9488;">TI</span></div>
      </td>
    </tr>
  </table>

  <!-- Centered Title -->
  <div style="text-align:center; margin: 18px 0 18px;">
    <h1 class="main-title">FEE STRUCTURE</h1>
    <div class="intake-subtitle"><?= htmlspecialchars(strtoupper($programmeTitle)) ?></div>
  </div>

  <p style="font-size:11px; line-height:1.6; color:#475569; text-align:center; max-width:580px; margin:0 auto 18px;">
    <?= htmlspecialchars($overview) ?>
  </p>

  <!-- Programme duration & Semester length -->
  <div class="details-box" style="margin: 0 0 18px 0;">
    <table>
      <tr>
        <td style="width:50%; vertical-align:top;">
          <div class="field-label">PROGRAMME DURATION</div>
          <div style="font-size:13px; font-weight:900; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($durationFormatted) ?></div>
        </td>
        <td style="width:50%; vertical-align:top;">
          <div class="field-label">SEMESTER LENGTH</div>
          <div style="font-size:13px; font-weight:900; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($semesterLengthText) ?></div>
        </td>
      </tr>
    </table>
  </div>

  <!-- Course fees table -->
  <div style="font-size:13px; font-weight:800; color:#0f172a; margin:0 0 3px;">Course fees</div>
  <div style="font-size:10.5px; color:#64748b; margin-bottom:8px;">Class schedule: 8:00 a.m. &bull; 12:00 noon &bull; 6:00 p.m.</div>

  <table style="width:100%; border-collapse:collapse; margin-bottom:14px;">
    <thead>
      <tr style="background:#0f172a; color:#ffffff;">
        <th style="padding:10px 14px; text-align:left; font-size:10px; font-weight:800; letter-spacing:0.5px; text-transform:uppercase;">FEE ITEM</th>
        <th style="padding:10px 14px; text-align:right; font-size:10px; font-weight:800; letter-spacing:0.5px; text-transform:uppercase;">AMOUNT</th>
      </tr>
    </thead>
    <tbody>
      <?= $feeRowsHtml ?>
      <tr style="background:#0f172a; color:#ffffff;">
        <td style="padding:11px 14px; font-size:11px; font-weight:800; text-transform:uppercase;">TOTAL PROGRAMME FEES</td>
        <td style="padding:11px 14px; font-size:11px; font-weight:800; text-align:right;"><?= htmlspecialchars(format_price($totalFee)) ?></td>
      </tr>
    </tbody>
  </table>

  <!-- Monthly payment option -->
  <?php if (!$isOneMonth && $monthlyAmount > 0): ?>
  <div style="margin-bottom:18px;">
    <div style="font-size:12px; font-weight:800; color:#0f172a; margin-bottom:3px;">Monthly payment option</div>
    <div style="font-size:15px; font-weight:900; color:#0d9488;"><?= htmlspecialchars(format_price($monthlyAmount)) ?> per month &times; <?= $months ?> months</div>
    <div style="font-size:10.5px; font-style:italic; color:#64748b; margin-top:3px;">Each semester runs for 3 months.</div>
  </div>
  <?php endif; ?>

  <!-- Payment Details Box -->
  <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 18px; margin-bottom:12px;">
    <div style="font-size:10px; font-weight:800; color:#0d9488; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">PAYMENT DETAILS</div>
    <table>
      <tr>
        <td style="width:33%; vertical-align:top;">
          <div style="font-size:9px; font-weight:700; color:#64748b; text-transform:uppercase;">M-PESA PAYBILL</div>
          <div style="font-size:13px; font-weight:900; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($paybill) ?></div>
        </td>
        <td style="width:33%; vertical-align:top;">
          <div style="font-size:9px; font-weight:700; color:#64748b; text-transform:uppercase;">ACCOUNT</div>
          <div style="font-size:13px; font-weight:900; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($account) ?></div>
        </td>
        <td style="width:34%; vertical-align:top;">
          <div style="font-size:9px; font-weight:700; color:#64748b; text-transform:uppercase;">PHONE</div>
          <div style="font-size:13px; font-weight:900; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($phoneContact) ?></div>
        </td>
      </tr>
    </table>
  </div>

  <div style="font-size:10px; color:#64748b; margin-bottom:10px;">Issued <?= htmlspecialchars($dateIssued) ?> &bull; dazuhub.ac.ke</div>

  <!-- Footer Page 2 -->
  <div class="footer-bar">
    <table>
      <tr>
        <td style="text-align:left;">dazuhub.ac.ke</td>
        <td style="text-align:right;">Dazu Training Institute &bull; <?= htmlspecialchars($intake) ?></td>
      </tr>
    </table>
  </div>
</div>

</body>
</html>
<?php
        return (string) ob_get_clean();
    }

    public static function pdf(array $reg, array $course = []): ?string
    {
        self::loadDompdf();
        if (!class_exists('Dompdf\\Dompdf')) {
            return null;
        }
        try {
            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ]);
            $dompdf->loadHtml(self::html($reg, $course));
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return $dompdf->output();
        } catch (\Throwable $e) {
            error_log('[AdmissionLetterGenerator] PDF render failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function writeTempPdf(array $reg, array $course = []): ?string
    {
        $pdf = self::pdf($reg, $course);
        if ($pdf === null) {
            return null;
        }
        $path = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . uniqid('admission-letter-', true) . '.pdf';
        return file_put_contents($path, $pdf) !== false ? $path : null;
    }

    private static function loadDompdf(): void
    {
        require_once __DIR__ . '/../helpers/dompdf.php';
        dazu_load_dompdf();
    }

    private static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
}

