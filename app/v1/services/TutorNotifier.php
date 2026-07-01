<?php
// app/services/TutorNotifier.php
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/TutorEmailTemplate.php';

class TutorNotifier {
    private $mailer;
    private $companyName = 'Dazu Hub Institute';

    public function __construct(?Mailer $mailer = null) {
        $cfg = require __DIR__ . '/../config/mail.php';
        $this->mailer = $mailer ?? new Mailer($cfg);
        $this->companyName = $cfg['from_name'] ?? $this->companyName;
    }

    private function url(string $path): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return ($scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) . $path;
    }

    private function send(string $email, string $name, string $event, array $data): array {
        [$subject, $html] = TutorEmailTemplate::render($event, $data);
        return $this->mailer->send($email, $name, $subject, $html);
    }

    public function sendCredentials(array $tutor, string $tempPassword, array $courseTitles = []): array {
        return $this->send($tutor['email'], $tutor['full_name'] ?? $tutor['username'], 'credentials_sent', [
            'full_name'    => $tutor['full_name'] ?? '',
            'username'     => $tutor['username'] ?? '',
            'email'        => $tutor['email'] ?? '',
            'temp_password'=> $tempPassword,
            'company_name' => $this->companyName,
            'login_url'    => $this->url(url('auth/login.php')),
            'courses'      => $courseTitles,
        ]);
    }

    public function sendSubmissionAlert(array $tutor, array $submission, string $studentName): array {
        $name = trim(($tutor['first_name'] ?? '') . ' ' . ($tutor['last_name'] ?? '')) ?: $tutor['username'];
        return $this->send($tutor['email'], $name, 'submission_received', [
            'tutor_name'       => $name,
            'student_name'     => $studentName,
            'submission_type'  => $submission['submission_type'],
            'item_title'       => $submission['item_title'] ?? '',
            'portal_url'       => $this->url(url('tutor/submissions/index.php')),
        ]);
    }

    public function sendGradedNotice(string $studentEmail, string $studentName, array $submission, float $maxMarks): array {
        return $this->send($studentEmail, $studentName, 'graded', [
            'student_name'     => $studentName,
            'submission_type'  => $submission['submission_type'],
            'item_title'       => $submission['item_title'] ?? '',
            'marks'            => $submission['marks'],
            'max_marks'        => $maxMarks,
            'feedback'         => $submission['feedback'] ?? '',
            'portal_url'       => $this->url(url('students/course/index.php')),
        ]);
    }
}
