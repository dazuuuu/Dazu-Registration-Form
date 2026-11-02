<?php
declare(strict_types=1);

if (!class_exists('CourseBrochureGenerator')) {

class CourseBrochureGenerator
{
    public static function fileName(array $course): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower(trim((string) ($course['abbreviation'] ?? $course['title'] ?? 'course')))) ?: 'course';
        return strtoupper($slug) . '-Course-Brochure.pdf';
    }

    public static function writeTempPdf(array $course): ?string
    {
        if (function_exists('course_brochure_absolute_path')) {
            $existing = course_brochure_absolute_path($course);
            if ($existing && is_file($existing)) {
                return $existing;
            }
        }

        $pdf = self::pdf($course);
        if (!$pdf) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'dti_brochure_') . '.pdf';
        if (file_put_contents($tmp, $pdf) === false) {
            return null;
        }
        return $tmp;
    }

    public static function pdf(array $course): ?string
    {
        self::loadDompdf();
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return null;
        }
        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled'      => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont'          => 'Helvetica',
        ]);
        $dompdf->loadHtml(self::html($course));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    public static function html(array $course): string
    {
        $title = trim((string) ($course['title'] ?? 'Professional Training Programme'));
        $abbr = strtoupper(trim((string) ($course['abbreviation'] ?? 'DTI')));
        $months = (int) ($course['duration_months'] ?? 0);
        if ($months <= 0) {
            $rawDur = (string) ($course['duration'] ?? '');
            if (preg_match('/(\d+)\s*month/i', $rawDur, $m)) {
                $months = (int) $m[1];
            }
        }
        if ($months <= 0) $months = 3;
        $durationFormatted = $months . ' month' . ($months === 1 ? '' : 's');

        $desc = trim((string) ($course['overview'] ?? $course['description'] ?? ''));
        if ($desc === '') {
            $desc = 'This programme is designed to equip learners with industry-standard practical skills, hands-on experience, and modern technology problem-solving competencies required in the global digital economy.';
        }

        $reqs = trim((string) ($course['requirements'] ?? ''));
        if ($reqs === '') {
            $reqs = 'Open to high school graduates, continuing college/university students, and working professionals seeking to upskill or transition into the technology and digital sectors. Basic computer literacy is recommended.';
        }

        $studySchedule = trim((string) ($course['study_schedule'] ?? 'Weekdays (Morning, Afternoon & Evening batches) and Weekend options available.'));
        $onlineSchedule = trim((string) ($course['class_schedule_online'] ?? '8:00 AM, 12:00 PM, 6:00 PM (EAT)'));
        $physicalSchedule = trim((string) ($course['class_schedule_physical'] ?? 'Morning and Evening sessions'));

        $company = 'Dazu Training Institute';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Course Brochure — {$title}</title>
<style>
  @page {
    size: A4 portrait;
    margin: 12mm 14mm 12mm 14mm;
  }
  body {
    font-family: Helvetica, Arial, sans-serif;
    color: #1e293b;
    font-size: 11px;
    line-height: 1.45;
    margin: 0;
    padding: 0;
  }
  .header-band {
    background: #0f766e;
    color: #ffffff;
    padding: 16px 20px;
    border-radius: 6px;
    margin-bottom: 16px;
  }
  .institute-title {
    font-size: 18px;
    font-weight: 800;
    letter-spacing: 0.5px;
    margin: 0;
    text-transform: uppercase;
  }
  .sub-tag {
    font-size: 9px;
    color: #ccfbf1;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-top: 4px;
  }
  .title-block {
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 2px solid #0f766e;
  }
  .course-title {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px 0;
  }
  .course-badge {
    display: inline-block;
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 9.5px;
    font-weight: 700;
    text-transform: uppercase;
  }
  .facts-grid {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 14px;
  }
  .facts-grid td {
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    width: 25%;
    vertical-align: top;
  }
  .fact-label {
    font-size: 8.5px;
    color: #64748b;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
  }
  .fact-val {
    font-size: 11px;
    font-weight: 800;
    color: #0f766e;
  }
  .section-heading {
    font-size: 11px;
    font-weight: 800;
    color: #0f766e;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 12px 0 6px 0;
    padding-bottom: 3px;
    border-bottom: 1px solid #cbd5e1;
  }
  .section-text {
    font-size: 10.5px;
    line-height: 1.5;
    color: #334155;
    margin: 0 0 10px 0;
  }
  .feature-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #0f766e;
    padding: 10px 14px;
    margin-bottom: 12px;
    border-radius: 4px;
  }
  .footer-contact {
    margin-top: 14px;
    padding: 10px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    text-align: center;
    font-size: 9.5px;
    color: #64748b;
  }
  .footer-contact strong {
    color: #0f172a;
  }
</style>
</head>
<body>

<div class="header-band">
  <table style="width:100%; border-collapse:collapse;">
    <tr>
      <td style="vertical-align:middle;">
        <div class="institute-title">{$company}</div>
        <div class="sub-tag">Academic Programmes &bull; Course Brochure &amp; Curriculum</div>
      </td>
      <td style="vertical-align:middle; text-align:right;">
        <div style="font-size:14px; font-weight:800; color:#ffffff; letter-spacing:1px;">{$abbr}</div>
        <div style="font-size:8.5px; color:#ccfbf1;">Official Syllabus</div>
      </td>
    </tr>
  </table>
</div>

<div class="title-block">
  <div class="course-badge">Verified Academic Curriculum</div>
  <h1 class="course-title">{$title}</h1>
  <div style="font-size:10px; color:#64748b; margin-top:2px;">Awarding Body: <strong>Dazu Training Institute</strong> &bull; Mode: Online &amp; Physical</div>
</div>

<table class="facts-grid">
  <tr>
    <td>
      <div class="fact-label">Duration</div>
      <div class="fact-val">{$durationFormatted}</div>
    </td>
    <td>
      <div class="fact-label">Study Modes</div>
      <div class="fact-val">Online / Physical</div>
    </td>
    <td>
      <div class="fact-label">Online Schedule</div>
      <div class="fact-val">{$onlineSchedule}</div>
    </td>
    <td>
      <div class="fact-label">Award</div>
      <div class="fact-val">Certificate / Diploma</div>
    </td>
  </tr>
</table>

<div class="section-heading">1. Programme Overview &amp; Objective</div>
<div class="section-text">
  {$desc}
</div>

<div class="feature-box">
  <div style="font-size:10.5px; font-weight:800; color:#0f172a; margin-bottom:4px;">Core Learning Pillars:</div>
  <table style="width:100%; border-collapse:collapse; font-size:10px; color:#334155;">
    <tr>
      <td style="width:33%; padding:3px 0;">&bull; Practical Hands-on Labs</td>
      <td style="width:33%; padding:3px 0;">&bull; Industry Mentor Guidance</td>
      <td style="width:33%; padding:3px 0;">&bull; Capstone Portfolio Project</td>
    </tr>
    <tr>
      <td style="width:33%; padding:3px 0;">&bull; Real-World Case Studies</td>
      <td style="width:33%; padding:3px 0;">&bull; Professional Certification</td>
      <td style="width:33%; padding:3px 0;">&bull; Career &amp; Job Placement Support</td>
    </tr>
  </table>
</div>

<div class="section-heading">2. Entry &amp; Technical Requirements</div>
<div class="section-text">
  {$reqs}
</div>

<div class="section-heading">3. Learning Methodology &amp; Student Portal</div>
<div class="section-text">
  Training is delivered through interactive live sessions, hands-on lab exercises, and continuous evaluation via the Dazu Training Institute Student Portal. Learners receive full access to course material, lecture notes, recorded webinars, assignments, and direct instructor messaging.
</div>

<div class="section-heading">4. Study Schedule &amp; Cohorts</div>
<div class="section-text">
  <strong>Class Timing:</strong> {$studySchedule}<br>
  <strong>Physical Sessions:</strong> {$physicalSchedule} at Dazu Training Institute training labs.<br>
  <strong>Online Cohorts:</strong> Live interactive classes via Google Meet / Zoom with dedicated student breakout rooms.
</div>

<div class="footer-contact">
  <strong>{$company}</strong> &bull; admissions@dazuhub.ac.ke &bull; +254 722 922 454 &bull; Web: <strong>dazuhub.ac.ke</strong><br>
  <span style="font-size:8.5px; color:#94a3b8;">This official course brochure is issued for admissions guidance. Detailed module breakdown is available via student orientation.</span>
</div>

</body>
</html>
HTML;
    }

    private static function loadDompdf(): void
    {
        if (class_exists(\Dompdf\Dompdf::class)) {
            return;
        }
        $autoload = __DIR__ . '/../../../vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }
}

}
