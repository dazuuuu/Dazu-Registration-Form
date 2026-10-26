<?php
// app/v1/services/EmployeeNotifier.php
// Emails an employee their sign-in details.
require_once __DIR__ . '/Mailer.php';

class EmployeeNotifier {
    private $mailer;
    private $companyName = 'Dazu Training Institute';

    public function __construct(?Mailer $mailer = null) {
        $cfg = require __DIR__ . '/../config/mail.php';
        $this->mailer = $mailer ?? new Mailer($cfg);
        $this->companyName = $cfg['from_name'] ?? $this->companyName;
    }

    public function sendCredentials(array $employee, string $tempPassword): array {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $loginUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('auth/login.php');
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;color:#0f172a">'
            . '<h2 style="color:#15a24a">Welcome to ' . $e($this->companyName) . '</h2>'
            . '<p>Hello ' . $e($employee['full_name']) . ', an account has been created for you as <strong>' . $e($employee['job_title']) . '</strong>.</p>'
            . '<p>Sign in on the same page students use. You will be taken to your staff dashboard, where you can complete your profile, add a photo and follow your salary.</p>'
            . '<table style="border-collapse:collapse;margin:16px 0">'
            . '<tr><td style="padding:6px 14px 6px 0;color:#64748b">Sign in with</td><td><strong>' . $e($employee['email']) . '</strong></td></tr>'
            . '<tr><td style="padding:6px 14px 6px 0;color:#64748b">Temporary password</td><td><strong>' . $e($tempPassword) . '</strong></td></tr></table>'
            . '<p><a href="' . $e($loginUrl) . '" style="background:#15a24a;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none">Sign in</a></p>'
            . '<p style="color:#64748b;font-size:13px">You will be asked to choose a new password the first time you sign in.</p></div>';
        return $this->mailer->send($employee['email'], $employee['full_name'], 'Your ' . $this->companyName . ' staff account', $html);
    }

    /** Sign-in details for a new or reset admin account. */
    public function sendAdminCredentials(array $admin, string $tempPassword): array {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $loginUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('admin/portal.php');
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;color:#0f172a">'
            . '<h2 style="color:#15a24a">Welcome to ' . $e($this->companyName) . '</h2>'
            . '<p>Hello ' . $e($admin['full_name']) . ', an administrator account has been created for you.</p>'
            . '<table style="border-collapse:collapse;margin:16px 0">'
            . '<tr><td style="padding:6px 14px 6px 0;color:#64748b">Email</td><td><strong>' . $e($admin['email']) . '</strong></td></tr>'
            . '<tr><td style="padding:6px 14px 6px 0;color:#64748b">Temporary password</td><td><strong>' . $e($tempPassword) . '</strong></td></tr></table>'
            . '<p><a href="' . $e($loginUrl) . '" style="background:#15a24a;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none">Open admin portal</a></p>'
            . '<p style="color:#64748b;font-size:13px">You will be asked to choose a new password the first time you sign in.</p></div>';
        return $this->mailer->send($admin['email'], $admin['full_name'], 'Your ' . $this->companyName . ' admin account', $html);
    }
}
