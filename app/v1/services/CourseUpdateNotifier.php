<?php
// app/v1/services/CourseUpdateNotifier.php
// Broadcasts a "course updated" email to every student account, triggered from
// admin/courses/edit.php on every successful save. Global namespace, matching its
// siblings Mailer/StudentNotifier/StudentEmailTemplate in this same directory.
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/StudentEmailTemplate.php';
require_once __DIR__ . '/../models/StudentModel.php';
require_once __DIR__ . '/../helpers/dazu_theme.php';
require_once __DIR__ . '/../helpers/course_share.php';

class CourseUpdateNotifier {
    private $pdo;
    private $mailCfg;
    private $enabled;
    private $companyName;

    public function __construct($pdo, bool $forceEnabled = false) {
        if (!function_exists('url')) {
            require_once __DIR__ . '/../config/paths.php';
        }
        $this->pdo = $pdo;
        $this->mailCfg = require __DIR__ . '/../config/mail.php';
        if ($forceEnabled) {
            $this->mailCfg['enabled'] = true;
        }
        $this->enabled = !empty($this->mailCfg['enabled']) || $forceEnabled;
        $this->companyName = $this->mailCfg['from_name'] ?? 'Dazu Hub Institute';

        try {
            require_once __DIR__ . '/../models/SettingModel.php';
            $settings = new \SettingModel($pdo);
            $siteName = $settings->get('site_name');
            if ($siteName) {
                $this->companyName = $siteName;
            }
        } catch (\Throwable $e) {
            // settings table may not exist in all environments
        }
    }

    /**
     * @return array{sent:int, failed:int, total:int}
     */
    public function notifyStudentsOfUpdate(array $course): array {
        if (!$this->enabled) {
            error_log('[CourseUpdateNotifier] mail skipped — SMTP not enabled');
            return ['sent' => 0, 'failed' => 0, 'total' => 0];
        }

        $studentModel = new \Models\StudentModel($this->pdo);
        $students = $studentModel->listAllStudentAccounts();
        if (empty($students)) {
            return ['sent' => 0, 'failed' => 0, 'total' => 0];
        }

        $courseId = (int) ($course['id'] ?? 0);
        $data = [
            'company_name'     => $this->companyName,
            'course_title'     => $course['title'] ?? '',
            'course_overview'  => $course['overview'] ?? '',
            'cover_image_url'  => \dazu_course_image_url($course),
            'course_url'       => $courseId > 0
                ? \course_share_absolute_url(url('courses/view.php?id=' . $courseId))
                : '',
        ];

        $sendCfg = $this->mailCfg;
        $sendCfg['enabled'] = true;
        $mailer = new Mailer($sendCfg);

        // Body is identical for every recipient — render once, not per-recipient.
        $messages = [];
        foreach ($students as $student) {
            $email = $student['email'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $name = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
            if ($name === '') {
                $name = $student['username'] ?? 'Student';
            }
            $studentData = array_merge($data, ['full_name' => $name]);
            [$subject, $html] = StudentEmailTemplate::render('course_updated', $studentData);
            $messages[] = ['to_email' => $email, 'to_name' => $name, 'subject' => $subject, 'html' => $html];
        }

        $result = $mailer->sendBatch($messages);
        $result['total'] = count($students);
        return $result;
    }
}
