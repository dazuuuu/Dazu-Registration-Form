<?php
// app/v1/services/EnquiryNotifier.php

require_once __DIR__ . '/Mailer.php';

class EnquiryNotifier
{
    private Mailer $mailer;
    private string $companyName;
    private bool $enabled;

    public function __construct(?PDO $pdo = null, ?Mailer $mailer = null)
    {
        $cfg = require __DIR__ . '/../config/mail.php';
        $this->companyName = $cfg['from_name'] ?? 'Dazu Hub Institute';
        $this->enabled = !empty($cfg['enquiry_mail_enabled']);

        if ($mailer !== null) {
            $this->mailer = $mailer;
            return;
        }

        $sendCfg = $cfg;
        if ($this->enabled) {
            $sendCfg['enabled'] = true;
        }
        $this->mailer = new Mailer($sendCfg);
    }

    /**
     * @return array<int, array{email: string, name: string}>
     */
    public static function adminRecipients(PDO $pdo): array
    {
        $recipients = [];

        try {
            $stmt = $pdo->query(
                "SELECT u.email, COALESCE(NULLIF(TRIM(u.full_name), ''), u.username, 'Admin') AS display_name
                 FROM users u
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE r.role_name IN ('superadmin', 'admin')
                   AND u.is_active = 1
                   AND u.email IS NOT NULL
                   AND TRIM(u.email) != ''
                 ORDER BY u.id ASC"
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $email = trim((string) ($row['email'] ?? ''));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[$email] = [
                        'email' => $email,
                        'name' => (string) ($row['display_name'] ?? 'Admin'),
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log('[enquiry] admin lookup failed: ' . $e->getMessage());
        }

        if (empty($recipients)) {
            require_once __DIR__ . '/../helpers/site_contact.php';
            $fallback = trim(site_contact_settings()['contact_email'] ?? '');
            if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
                $recipients[$fallback] = ['email' => $fallback, 'name' => 'Admin'];
            }
        }

        return array_values($recipients);
    }

    public function notifyAdmins(PDO $pdo, array $enquiry, int $enquiryId): array
    {
        if (!$this->enabled) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Enquiry mail disabled'];
        }

        $admins = self::adminRecipients($pdo);
        if (empty($admins)) {
            return ['ok' => false, 'message' => 'No admin email addresses configured'];
        }

        $subject = 'New enquiry #' . $enquiryId . ' — ' . ($enquiry['name'] ?? 'Contact form');
        $html = $this->adminTemplate($enquiry, $enquiryId);
        $sent = 0;
        $errors = [];

        foreach ($admins as $admin) {
            $result = $this->mailer->send($admin['email'], $admin['name'], $subject, $html);
            if (!empty($result['ok'])) {
                $sent++;
            } else {
                $errors[] = $result['message'] ?? 'send failed';
            }
        }

        return [
            'ok' => $sent > 0,
            'sent' => $sent,
            'message' => $sent > 0 ? 'sent' : implode('; ', $errors),
        ];
    }

    public function sendConfirmation(string $email, string $name): array
    {
        if (!$this->enabled) {
            return ['ok' => true, 'skipped' => true];
        }

        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => true, 'skipped' => true];
        }
        if (str_ends_with(strtolower($email), '@dazuaihub.local')) {
            return ['ok' => true, 'skipped' => true];
        }

        $subject = 'Thank you for contacting ' . $this->companyName;
        $html = $this->confirmationTemplate($name);

        return $this->mailer->send($email, $name, $subject, $html);
    }

    private function adminTemplate(array $enquiry, int $enquiryId): string
    {
        $rows = [
            'Reference' => '#' . $enquiryId,
            'Name' => $enquiry['name'] ?? '',
            'Email' => $enquiry['email'] ?? '',
            'Phone' => $enquiry['phone'] ?? '',
            'Subject' => $enquiry['service'] ?? 'General Inquiry',
            'Message' => nl2br(htmlspecialchars((string) ($enquiry['message'] ?? ''), ENT_QUOTES, 'UTF-8')),
        ];

        $body = '<p>A new enquiry was submitted via the contact form.</p><table style="width:100%;border-collapse:collapse;margin:16px 0;">';
        foreach ($rows as $label => $value) {
            $body .= '<tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;width:120px;background:#f8fafc;">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                . '</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . $value . '</td></tr>';
        }
        $body .= '</table><p style="color:#64748b;font-size:13px;">View and respond in the admin enquiries panel.</p>';

        return $this->layout('New Contact Enquiry', $body);
    }

    private function confirmationTemplate(string $name): string
    {
        $greeting = $name !== '' ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : 'there';
        $body = '<p>Hi ' . $greeting . ',</p>'
            . '<p>Thank you for contacting <strong>' . htmlspecialchars($this->companyName, ENT_QUOTES, 'UTF-8') . '</strong>. '
            . 'We have received your enquiry and our admissions team will get back to you soon.</p>'
            . '<p style="color:#64748b;font-size:14px;">If your matter is urgent, call us on <strong>+254 750 116600</strong>.</p>';

        return $this->layout('Thanks for contacting us', $body);
    }

    private function layout(string $heading, string $body): string
    {
        return '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">'
            . '<div style="text-align:center;padding:24px 0;border-bottom:1px solid #eee;">'
            . '<strong style="font-size:20px;color:#15A24A;">' . htmlspecialchars($this->companyName, ENT_QUOTES, 'UTF-8') . '</strong></div>'
            . '<div style="padding:24px;"><h2 style="color:#15A24A;margin-top:0;">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h2>' . $body . '</div>'
            . '<div style="padding:16px 24px;border-top:1px solid #eee;color:#888;font-size:12px;text-align:center;">'
            . htmlspecialchars($this->companyName, ENT_QUOTES, 'UTF-8') . '</div></div>';
    }
}
