<?php
// app/services/Mailer.php
// Thin PHPMailer wrapper. Works whether PHPMailer was installed via Composer
// (vendor/autoload.php) or dropped in manually at app/lib/PHPMailer/src/.

class Mailer {
    private $cfg;
    private static $loaded = false;

    public function __construct(array $cfg) {
        $this->cfg = $cfg;
    }

    /** Locate PHPMailer from Composer vendor dir or a manual lib folder. */
    private static function loadPhpMailer(): void {
        if (self::$loaded) return;

        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            self::$loaded = true;
            return;
        }

        if (!defined('PROJECT_ROOT')) {
            require_once __DIR__ . '/../config/paths.php';
        }

        $candidates = [
            PROJECT_ROOT . '/vendor/autoload.php',
            __DIR__ . '/../../../vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
            __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php',
        ];
        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }
            if (substr($path, -12) === 'autoload.php') {
                require_once $path;
            } else {
                $dir = dirname($path);
                require_once $dir . '/Exception.php';
                require_once $dir . '/PHPMailer.php';
                require_once $dir . '/SMTP.php';
            }
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                self::$loaded = true;
                return;
            }
        }
        // Not found — send() will report this rather than fatal.
    }

    /**
     * @return array ['ok'=>bool, 'message'=>string]
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ): array {
        if (array_key_exists('enabled', $this->cfg) && empty($this->cfg['enabled'])) {
            return ['ok' => true, 'message' => 'skipped', 'skipped' => true];
        }

        self::loadPhpMailer();

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return ['ok' => false, 'message' => 'PHPMailer is not installed. Upload app/v1/lib/PHPMailer/ or run composer install in the project root.'];
        }
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Invalid recipient email'];
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $this->applyTransport($mail);
            $this->applyIdentity($mail, $replyToEmail, $replyToName);
            $mail->addAddress($toEmail, $toName);

            // Embed the admin branding logo (Settings → Branding). cid:ismanlogo
            $this->embedLogo($mail);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)));

            $mail->send();
            return ['ok' => true, 'message' => 'sent'];
        } catch (Throwable $e) {
            error_log('[mailer] ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send the same shared logo/branding to many recipients over ONE SMTP connection
     * (SMTPKeepAlive) instead of send()'s one-handshake-per-call. A broadcast to hundreds
     * of students would otherwise mean hundreds of sequential TCP+TLS+AUTH round trips —
     * this is the cheap fix for that without standing up a queue.
     *
     * One bad address never aborts the batch — each recipient is wrapped in its own
     * try/catch and just counted as failed.
     *
     * @param array<int, array{to_email:string, to_name?:string, subject:string, html:string}> $messages
     * @return array{sent:int, failed:int}
     */
    public function sendBatch(array $messages): array {
        if (array_key_exists('enabled', $this->cfg) && empty($this->cfg['enabled'])) {
            return ['sent' => 0, 'failed' => 0];
        }
        if (empty($messages)) {
            return ['sent' => 0, 'failed' => 0];
        }

        self::loadPhpMailer();
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            error_log('[mailer:batch] PHPMailer is not installed.');
            return ['sent' => 0, 'failed' => count($messages)];
        }

        $sent = 0;
        $failed = 0;
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $this->applyTransport($mail);
            $mail->SMTPKeepAlive = true;
            $this->applyIdentity($mail);
            $mail->isHTML(true);

            foreach ($messages as $msg) {
                $toEmail = $msg['to_email'] ?? '';
                if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                    $failed++;
                    continue;
                }
                try {
                    $mail->clearAddresses();
                    $mail->clearAttachments(); // also clears embedded images, so the logo below doesn't duplicate/collide across iterations
                    $mail->addAddress($toEmail, $msg['to_name'] ?? '');
                    $this->embedLogo($mail);
                    $mail->Subject = $msg['subject'] ?? '';
                    $mail->Body = $msg['html'] ?? '';
                    $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags($msg['html'] ?? '')));
                    $mail->send();
                    $sent++;
                } catch (Throwable $e) {
                    error_log('[mailer:batch] ' . $toEmail . ': ' . $e->getMessage());
                    $failed++;
                }
            }
        } finally {
            if (!empty($mail->SMTPKeepAlive)) {
                $mail->smtpClose();
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @param list<array{path:string,name?:string}> $attachments
     * @return array{ok:bool, message:string, skipped?:bool}
     */
    public function sendWithAttachments(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        array $attachments = [],
        bool $embedLogo = true
    ): array {
        if (array_key_exists('enabled', $this->cfg) && empty($this->cfg['enabled'])) {
            return ['ok' => true, 'message' => 'skipped', 'skipped' => true];
        }

        self::loadPhpMailer();

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return ['ok' => false, 'message' => 'PHPMailer is not installed.'];
        }
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Invalid recipient email'];
        }

        $files = [];
        foreach ($attachments as $item) {
            $path = is_array($item) ? (string) ($item['path'] ?? '') : (string) $item;
            $name = is_array($item) ? (string) ($item['name'] ?? basename($path)) : basename($path);
            if ($path !== '' && is_file($path)) {
                $files[] = ['path' => $path, 'name' => $name !== '' ? $name : basename($path)];
            }
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $this->applyTransport($mail);
            $this->applyIdentity($mail);
            $mail->addAddress($toEmail, $toName);
            if ($embedLogo) {
                $this->embedLogo($mail);
            }
            foreach ($files as $file) {
                $mail->addAttachment($file['path'], $file['name']);
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)));
            $mail->send();
            return ['ok' => true, 'message' => 'sent'];
        } catch (Throwable $e) {
            error_log('[mailer] ' . $e->getMessage());
            if ($files !== []) {
                try {
                    return $this->sendWithAttachments($toEmail, $toName, $subject, $htmlBody, [], $embedLogo);
                } catch (Throwable $retry) {
                    return ['ok' => false, 'message' => $e->getMessage()];
                }
            }
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function sendWithAttachment(string $toEmail, string $toName, string $subject, string $htmlBody, string $filePath, string $fileName): array {
        return $this->sendWithAttachments($toEmail, $toName, $subject, $htmlBody, [
            ['path' => $filePath, 'name' => $fileName],
        ]);
    }

    private function applyTransport(\PHPMailer\PHPMailer\PHPMailer $mail): void
    {
        if (($this->cfg['transport'] ?? 'smtp') !== 'smtp') {
            return;
        }
        $mail->isSMTP();
        $mail->Host = $this->cfg['host'] ?? '';
        $mail->Port = (int) ($this->cfg['port'] ?? 587);
        $mail->SMTPAuth = true;
        $mail->Username = $this->cfg['username'] ?? '';
        $mail->Password = str_replace(' ', '', $this->cfg['password'] ?? '');
        $enc = $this->cfg['encryption'] ?? 'tls';
        if ($enc) {
            $mail->SMTPSecure = $enc;
        }
        $mail->Timeout = 25;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
    }

    private function applyIdentity(
        \PHPMailer\PHPMailer\PHPMailer $mail,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ): void {
        $mail->CharSet = 'UTF-8';
        $fromName = (string) ($this->cfg['from_name'] ?? 'Dazu Hub Institute');
        $configuredFrom = trim((string) ($this->cfg['from_email'] ?? ''));
        $username = trim((string) ($this->cfg['username'] ?? ''));
        $host = strtolower((string) ($this->cfg['host'] ?? ''));
        $from = $configuredFrom;
        // Gmail only accepts the authenticated account as From — a custom domain here is dropped or rejected.
        if ($username !== '' && filter_var($username, FILTER_VALIDATE_EMAIL)
            && ($from === '' || str_contains($host, 'gmail.com'))) {
            $from = $username;
        }
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $from = $username !== '' && filter_var($username, FILTER_VALIDATE_EMAIL)
                ? $username
                : 'no-reply@dazuhub.co.ke';
        }
        $mail->setFrom($from, $fromName);
        $mail->Sender = $from;
        if ($replyToEmail && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyToEmail, $replyToName ?? '');
        } elseif ($configuredFrom !== '' && strcasecmp($configuredFrom, $from) !== 0
            && filter_var($configuredFrom, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($configuredFrom, $fromName);
        }
    }

    private function resolveLogoPath(): string
    {
        require_once __DIR__ . '/../helpers/site_logo.php';
        $fromSettings = site_logo_absolute_path();
        if ($fromSettings && is_file($fromSettings)) {
            return $fromSettings;
        }
        $configured = (string) ($this->cfg['logo_path'] ?? '');
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }
        return '';
    }

    private function embedLogo(\PHPMailer\PHPMailer\PHPMailer $mail): void
    {
        $logo = $this->resolveLogoPath();
        if (!$logo || !is_file($logo)) {
            return;
        }
        $name = basename($logo) ?: 'logo.png';
        $mime = 'image/png';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($logo);
            if (is_string($detected) && str_starts_with($detected, 'image/')) {
                $mime = $detected;
            }
        }
        $mail->addEmbeddedImage($logo, 'ismanlogo', $name, 'base64', $mime);
    }
}
