<?php

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/../models/SettingModel.php';

/** Sends newly submitted public enquiries to the configured admin inbox. */
class EnquiryNotifier
{
    private Mailer $mailer;
    private PDO $pdo;
    private string $companyName;

    public function __construct(PDO $pdo, ?Mailer $mailer = null)
    {
        $cfg = require __DIR__ . '/../config/mail.php';
        // Contact submissions are explicitly configured to send an admin alert.
        $cfg['enabled'] = true;

        $this->pdo = $pdo;
        $this->mailer = $mailer ?? new Mailer($cfg);
        $this->companyName = $cfg['from_name'] ?? 'Dazu Hub Institute';
    }

    public function notifyAdmin(array $enquiry, int $enquiryId): array
    {
        $settings = new SettingModel($this->pdo);
        $recipient = trim((string) $settings->get('contact_email', 'info@dazuaihub.com'));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            error_log("[enquiry] Notification skipped for enquiry #{$enquiryId}: invalid contact_email setting.");
            return ['ok' => false, 'message' => 'Invalid admin notification email'];
        }

        $name = trim((string) ($enquiry['name'] ?? ''));
        $email = trim((string) ($enquiry['email'] ?? ''));
        $replyToEmail = trim((string) ($enquiry['_reply_to_email'] ?? ''));
        $phone = trim((string) ($enquiry['phone'] ?? ''));
        $service = trim((string) ($enquiry['service'] ?? '')) ?: 'General inquiry';
        $message = trim((string) ($enquiry['message'] ?? ''));
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $subject = "New enquiry #{$enquiryId}: {$service}";
        $html = '<h2>New contact enquiry</h2>'
            . '<p>A visitor has submitted a new enquiry to ' . $esc($this->companyName) . '.</p>'
            . '<table cellpadding="7" cellspacing="0" border="0">'
            . '<tr><th align="left">Enquiry ID</th><td>#' . $enquiryId . '</td></tr>'
            . '<tr><th align="left">Name</th><td>' . $esc($name) . '</td></tr>'
            . '<tr><th align="left">Email</th><td>' . $esc($replyToEmail ?: 'Not provided') . '</td></tr>'
            . '<tr><th align="left">Phone</th><td>' . $esc($phone) . '</td></tr>'
            . '<tr><th align="left">Subject</th><td>' . $esc($service) . '</td></tr>'
            . '</table>'
            . '<h3>Message</h3><p>' . nl2br($esc($message)) . '</p>';

        return $this->mailer->send($recipient, $this->companyName, $subject, $html, $replyToEmail ?: null, $name);
    }
}
