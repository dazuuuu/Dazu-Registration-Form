<?php
// app/services/StudentNotifier.php
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/StudentEmailTemplate.php';

class StudentNotifier {
    private $pdo;
    private $mailer;
    private $mailCfg;
    private $enabled;
    private $companyName;
    private $loginUrl;

    public function __construct($pdo, ?Mailer $mailer = null) {
        if (!function_exists('url')) {
            require_once __DIR__ . '/../config/paths.php';
        }
        $this->loginUrl = url('auth/login.php');
        $this->pdo = $pdo;
        $this->mailCfg = require __DIR__ . '/../config/mail.php';
        $this->enabled = !empty($this->mailCfg['enabled']);
        $this->mailer = $this->enabled ? ($mailer ?? null) : null;
        $this->companyName = $this->mailCfg['from_name'] ?? 'Dazu Hub Institute';

        try {
            require_once __DIR__ . '/../models/SettingModel.php';
            $settings = new SettingModel($pdo);
            $siteName = $settings->get('site_name');
            if ($siteName) {
                $this->companyName = $siteName;
            }
        } catch (Throwable $e) {
            // settings table may not exist in all environments
        }
    }

    private function baseData(array $reg): array {
        return array_merge($reg, [
            'company_name' => $this->companyName,
            'login_url'    => $this->fullUrl($this->loginUrl),
        ]);
    }

    private function fullUrl(string $path): string {
        if (strpos($path, 'http') === 0) return $path;
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $path;
    }

    private function mailer(): Mailer {
        if ($this->mailer === null) {
            $this->mailer = new Mailer($this->mailCfg);
        }
        return $this->mailer;
    }

    private function send(string $email, string $name, string $event, array $data): array {
        if (!$this->enabled) {
            return ['ok' => true, 'message' => 'skipped', 'skipped' => true];
        }
        [$subject, $html] = StudentEmailTemplate::render($event, $data);
        $result = $this->mailer()->send($email, $name, $subject, $html);
        if (!$result['ok']) {
            error_log('[StudentNotifier] ' . $event . ' to ' . $email . ': ' . $result['message']);
        }
        return $result;
    }

    public function sendRegistrationReceived(array $reg): array {
        return $this->send($reg['email'], $reg['full_name'], 'registration_received', $this->baseData($reg));
    }

    public function sendRegistrationApproved(array $reg, string $tempPassword): array {
        $data = $this->baseData($reg);
        $data['temp_password'] = $tempPassword;
        $data['username'] = $reg['registration_no'] ?? '';
        return $this->send($reg['email'], $reg['full_name'], 'registration_approved', $data);
    }

    public function sendRegistrationRejected(array $reg, string $reason): array {
        $data = $this->baseData($reg);
        $data['rejection_reason'] = $reason;
        return $this->send($reg['email'], $reg['full_name'], 'registration_rejected', $data);
    }

    public function sendCredentials(array $reg, string $tempPassword): array {
        $data = $this->baseData($reg);
        $data['temp_password'] = $tempPassword;
        $data['username'] = $reg['registration_no'] ?? $reg['username'] ?? '';
        return $this->send($reg['email'], $reg['full_name'], 'credentials_sent', $data);
    }

    public function sendPaymentSubmitted(array $reg, array $payment): array {
        $data = $this->baseData(array_merge($reg, $payment));
        return $this->send($reg['email'], $reg['full_name'], 'payment_submitted', $data);
    }

    public function sendPaymentApproved(array $reg, array $payment, string $receiptHtml): array {
        $data = $this->baseData($reg);
        $data['receipt_html'] = $receiptHtml;
        $data = array_merge($data, $payment);
        return $this->send($reg['email'], $reg['full_name'], 'payment_approved', $data);
    }

    public function sendPaymentRejected(array $reg, array $payment, string $reason): array {
        $data = $this->baseData(array_merge($reg, $payment));
        $data['rejection_reason'] = $reason;
        return $this->send($reg['email'], $reg['full_name'], 'payment_rejected', $data);
    }

    public function sendPortalLocked(array $reg, float $amountDue): array {
        $data = $this->baseData($reg);
        $data['amount_due'] = $amountDue;
        $data['due_date'] = $reg['next_payment_due'] ?? '';
        return $this->send($reg['email'], $reg['full_name'], 'portal_locked', $data);
    }
}
