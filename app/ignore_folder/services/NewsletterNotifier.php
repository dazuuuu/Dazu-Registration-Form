<?php
require_once __DIR__ . '/Mailer.php';

/** Sends a welcome/confirmation email to new newsletter subscribers. */
class NewsletterNotifier
{
    private Mailer $mailer;
    private string $companyName;

    public function __construct(?Mailer $mailer = null)
    {
        $cfg = require __DIR__ . '/../config/mail.php';
        // Newsletter sign-up is an explicit user action — always send the confirmation.
        $cfg['enabled'] = true;

        $this->mailer = $mailer ?? new Mailer($cfg);
        $this->companyName = $cfg['from_name'] ?? 'Dazu Training Institute';
    }

    public function sendWelcome(string $email): array
    {
        $esc = htmlspecialchars($this->companyName, ENT_QUOTES, 'UTF-8');
        $subject = "You're subscribed to {$this->companyName} updates";
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;color:#1a1a1a;">'
            . '<h2 style="color:#0D9488;">Thanks for subscribing!</h2>'
            . '<p>You\'ll now get occasional emails from <strong>' . $esc . '</strong> about new courses, '
            . 'campus events, and tech career tips.</p>'
            . '<p style="font-size:12px;color:#888;">Didn\'t sign up for this? Just ignore this email — no further messages will be sent unless you subscribe again.</p>'
            . '</div>';

        return $this->mailer->send($email, '', $subject, $html);
    }
}
