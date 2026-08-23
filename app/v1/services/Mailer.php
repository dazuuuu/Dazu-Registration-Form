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

    /** Locate PHPMailer from bundled lib first, then Composer vendor. */
    private static function loadPhpMailer(): void {
        if (self::$loaded) return;

        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            self::$loaded = true;
            return;
        }

        if (!defined('PROJECT_ROOT')) {
            require_once __DIR__ . '/../config/paths.php';
        }

        // Bundled copy — always preferred (works without Composer).
        $manual = __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
        if (is_file($manual)) {
            $dir = dirname($manual);
            require_once $dir . '/Exception.php';
            require_once $dir . '/PHPMailer.php';
            require_once $dir . '/SMTP.php';
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                self::$loaded = true;
                return;
            }
        }

        require_once __DIR__ . '/../helpers/vendor_autoload.php';
        $composerPaths = [
            PROJECT_ROOT . '/vendor/autoload.php',
            __DIR__ . '/../../../vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
        ];
        foreach ($composerPaths as $path) {
            if (!is_file($path)) {
                continue;
            }
            if (safe_composer_autoload() && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                self::$loaded = true;
                return;
            }
        }
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
            if (($this->cfg['transport'] ?? 'smtp') === 'smtp') {
                $mail->isSMTP();
                $mail->Host = $this->cfg['host'] ?? '';
                $mail->Port = (int) ($this->cfg['port'] ?? 587);
                $mail->SMTPAuth = true;
                $mail->Username = $this->cfg['username'] ?? '';
                $mail->Password = str_replace(' ', '', $this->cfg['password'] ?? '');
                $enc = $this->cfg['encryption'] ?? 'tls';
                if ($enc) $mail->SMTPSecure = $enc;
            }

            $mail->setFrom($this->cfg['from_email'] ?? 'no-reply@example.com', $this->cfg['from_name'] ?? 'Store');
            $mail->addAddress($toEmail, $toName);
            if ($replyToEmail !== null && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyToEmail, $replyToName ?? '');
            }

            // Embed the logo so it shows without a public image URL: reference cid:ismanlogo.
            $logo = $this->cfg['logo_path'] ?? '';
            if ($logo && is_file($logo)) {
                $mail->addEmbeddedImage($logo, 'ismanlogo', 'logo.png');
            }

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
            if (($this->cfg['transport'] ?? 'smtp') === 'smtp') {
                $mail->isSMTP();
                $mail->Host = $this->cfg['host'] ?? '';
                $mail->Port = (int) ($this->cfg['port'] ?? 587);
                $mail->SMTPAuth = true;
                $mail->Username = $this->cfg['username'] ?? '';
                $mail->Password = str_replace(' ', '', $this->cfg['password'] ?? '');
                $enc = $this->cfg['encryption'] ?? 'tls';
                if ($enc) $mail->SMTPSecure = $enc;
                $mail->SMTPKeepAlive = true;
            }
            $mail->setFrom($this->cfg['from_email'] ?? 'no-reply@example.com', $this->cfg['from_name'] ?? 'Store');
            $mail->isHTML(true);

            $logo = $this->cfg['logo_path'] ?? '';

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
                    if ($logo && is_file($logo)) {
                        $mail->addEmbeddedImage($logo, 'ismanlogo', 'logo.png');
                    }
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
     * @param array<int, array{path:string, name:string}> $attachments
     * @return array{ok:bool, message:string}
     */
    public function sendWithAttachments(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        array $attachments
    ): array {
        if (array_key_exists('enabled', $this->cfg) && empty($this->cfg['enabled'])) {
            return ['ok' => true, 'message' => 'skipped', 'skipped' => true];
        }

        self::loadPhpMailer();

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return ['ok' => false, 'message' => 'PHPMailer is not installed. Use app/v1/lib/PHPMailer or run composer install.'];
        }
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Invalid recipient email'];
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $this->configureSmtp($mail);

            $mail->setFrom($this->cfg['from_email'] ?? 'no-reply@example.com', $this->cfg['from_name'] ?? 'Dazu Hub Institute');
            $mail->addAddress($toEmail, $toName);

            $logo = $this->cfg['logo_path'] ?? '';
            if ($logo && is_file($logo)) {
                $mail->addEmbeddedImage($logo, 'ismanlogo', 'logo.png');
            }

            foreach ($attachments as $file) {
                $path = $file['path'] ?? '';
                $name = $file['name'] ?? basename($path);
                if ($path !== '' && is_file($path)) {
                    $mail->addAttachment($path, $name);
                }
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)));
            $mail->send();
            return ['ok' => true, 'message' => 'sent'];
        } catch (Throwable $e) {
            error_log('[mailer] sendWithAttachments: ' . $e->getMessage());
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

    private function configureSmtp(\PHPMailer\PHPMailer\PHPMailer $mail): void
    {
        if (($this->cfg['transport'] ?? 'smtp') === 'smtp') {
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
        }
    }
}
