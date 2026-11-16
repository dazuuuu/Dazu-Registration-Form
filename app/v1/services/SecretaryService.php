<?php
/**
 * Adding a secretary: validates the form, makes her login (temporary password, must change it at first sign-in), her secretary record with the
 * automatic ID number DTI/SEC/001/26, and her switches, then emails her the temporary password with the existing Mailer (only called, never edited).
 */
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/../helpers/secretary.php';

class SecretaryService
{
    public const PHONE_RE = '/^[0-9+()\-\s]{7,20}$/';

    private PDO $db;
    /** @var callable|null  fn(string $to, string $name, string $subject, string $html): array  (for tests; the default is the real Mailer) */
    private $mailFn;

    public function __construct(PDO $db, ?callable $mailFn = null)
    {
        $this->db = $db;
        $this->mailFn = $mailFn;
    }

    /** Clean and check the form. Returns [data, errors]. */
    public function validate(array $in, ?int $ignoreUserId = null): array
    {
        $e = [];
        $t = static fn($k) => trim((string) ($in[$k] ?? ''));
        $d = [
            'first_name' => $t('first_name'), 'last_name' => $t('last_name'), 'email' => strtolower($t('email')), 'phone' => $t('phone'),
            'branch' => $t('branch'), 'job_title' => $t('job_title') ?: 'Secretary',
            'emergency_name' => $t('emergency_name'), 'emergency_phone' => $t('emergency_phone'),
        ];
        if ($d['first_name'] === '' || mb_strlen($d['first_name']) > 80) $e[] = 'Enter her first name (up to 80 letters).';
        if ($d['last_name'] === '' || mb_strlen($d['last_name']) > 80) $e[] = 'Enter her last name (up to 80 letters).';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 150) $e[] = 'Enter a valid email address. She uses it to sign in.';
        if (!preg_match(self::PHONE_RE, $d['phone'])) $e[] = 'Enter a valid phone number.';
        if (!isset(secretary_branches()[$d['branch']])) $e[] = 'Choose a branch: Nairobi, Thika or Online.';
        if (mb_strlen($d['job_title']) > 80) $e[] = 'The job title is too long (up to 80 letters).';
        if (mb_strlen($d['emergency_name']) > 120) $e[] = 'The emergency contact name is too long.';
        if ($d['emergency_phone'] !== '' && !preg_match(self::PHONE_RE, $d['emergency_phone'])) $e[] = 'Enter a valid emergency contact phone number, or leave it empty.';

        $day = (int) ($in['birth_day'] ?? 0);
        $month = (int) ($in['birth_month'] ?? 0);
        if ($month < 1 || $month > 12 || $day < 1 || !checkdate($month, $day, 2000)) $e[] = 'Choose her birthday (day and month).';
        $d['birth_day'] = $day;
        $d['birth_month'] = $month;
        $yearRaw = $t('birth_year');
        $d['birth_year'] = null;
        if ($yearRaw !== '') {
            $y = (int) $yearRaw;
            if (!ctype_digit($yearRaw) || $y < 1940 || $y > (int) date('Y') - 15) $e[] = 'The birth year looks wrong. You can leave it empty.';
            else $d['birth_year'] = $y;
        }
        $sd = $t('start_date');
        $dt = DateTime::createFromFormat('!Y-m-d', $sd);
        if (!$dt || $dt->format('Y-m-d') !== $sd) $e[] = 'Choose her start date.';
        $d['start_date'] = $sd;

        // Switches: only known keys and known values; anything else falls back to the form default.
        $sw = [];
        foreach (secretary_switch_defs() as $k => [$label, $help, $opts, $def]) {
            $v = $in['sw'][$k] ?? $def;
            $v = is_string($v) ? $v : $def;
            $sw[$k] = isset($opts[$v]) ? $v : $def;
        }
        $d['switches'] = $sw;

        if (!$e) {
            $st = $this->db->prepare('SELECT id FROM users WHERE LOWER(email) = ?');
            $st->execute([$d['email']]);
            $other = $st->fetchColumn();
            if ($other !== false && (int) $other !== (int) $ignoreUserId) $e[] = 'A user with this email already exists.';
        }
        return [$d, $e];
    }

    /**
     * Save her. Returns ['ok', 'secretary_id', 'user_id', 'staff_no', 'email_sent', 'email_error'?] or ['ok' => false, 'error'].
     * $photoPath is a path already stored by the upload helper (or null).
     */
    public function create(array $d, ?string $photoPath, int $addedBy): array
    {
        $temp = bin2hex(random_bytes(8));
        $db = $this->db;
        $roleId = (int) $db->query("SELECT id FROM roles WHERE role_name = 'secretary'")->fetchColumn();
        if ($roleId <= 0) return ['ok' => false, 'error' => 'The Secretary role is not set up yet. Run the database update (migrations 122 and 123) first.'];

        // The ID number is made inside the transaction from the numbers already saved. Two secretaries created at the same moment can pick the same number: the UNIQUE key on staff_no lets only one
        // insert win. A loser (or a deadlock between two creators, which MySQL answers by rolling the whole transaction back) is retried as a WHOLE NEW transaction, so it reads the numbers the winner
        // has just saved (MySQL transactions read a frozen snapshot, so a retry inside the same transaction would keep choosing the same number).
        $secId = 0; $userId = 0; $no = '';
        for ($attempt = 0; $attempt < 12 && !$secId; $attempt++) {
            try {
                $db->beginTransaction();
                $username = $this->username($d['email']);
                $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status, must_change_password)
                              VALUES (?, ?, ?, ?, 1, 1, 'active', 1)")
                   ->execute([$username, $d['email'], password_hash($temp, PASSWORD_DEFAULT), $roleId]);
                $userId = (int) $db->lastInsertId();
                $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)')
                   ->execute([$userId, $d['first_name'], $d['last_name'], $d['phone']]);
                $no = $this->nextNo();
                $db->prepare("INSERT INTO secretaries (user_id, staff_no, job_title, phone, branch, birth_day, birth_month, birth_year, start_date, emergency_name, emergency_phone, photo_path, status, added_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)")
                   ->execute([$userId, $no, $d['job_title'], $d['phone'], $d['branch'], $d['birth_day'], $d['birth_month'], $d['birth_year'], $d['start_date'],
                              $d['emergency_name'] ?: null, $d['emergency_phone'] ?: null, $photoPath, $addedBy ?: null]);
                $secId = (int) $db->lastInsertId();
                $ins = $db->prepare('INSERT INTO secretary_switches (secretary_id, switch_key, switch_value) VALUES (?, ?, ?)');
                foreach ($d['switches'] as $k => $v) $ins->execute([$secId, $k, $v]);
                $db->commit();
            } catch (Throwable $ex) {
                $secId = 0;
                try { if ($db->inTransaction()) $db->rollBack(); } catch (Throwable $e2) { /* MySQL already rolled a deadlock back */ }
                $msg = $ex->getMessage();
                $retry = $ex instanceof PDOException && (strpos($msg, 'staff_no') !== false || preg_match('/deadlock|40001|1213|1205|lock wait/i', $msg . ' ' . $ex->getCode()));
                if ($retry && $attempt < 11) { usleep(random_int(2000, 40000)); continue; }   // try again as a new transaction
                error_log('[SecretaryService::create] ' . $msg);
                return ['ok' => false, 'error' => $this->isDuplicate($ex) && strpos($msg, 'staff_no') === false ? 'A user with this email already exists.' : 'Could not save the secretary. Nothing was created. Please try again.'];
            }
        }
        if (!$secId) return ['ok' => false, 'error' => 'Could not save the secretary. Nothing was created. Please try again.'];

        try {   // she is told about her blogs (approved, sent back) from the start: the existing notification code, "Everything else" switched on; she can change it on her Notifications page
            require_once __DIR__ . '/PortalNotifications.php';
            PortalNotifications::saveChoices($db, $userId, ['other']);
        } catch (Throwable $e) {
            error_log('[SecretaryService] notification choices: ' . $e->getMessage());
        }

        return ['ok' => true, 'secretary_id' => $secId, 'user_id' => $userId, 'staff_no' => $no]
            + $this->emailCredentials($secId, $d['first_name'] . ' ' . $d['last_name'], $d['email'], $temp);
    }

    /** New temporary password, emailed; she must choose her own at the next sign-in. */
    public function resendCredentials(int $secretaryId): array
    {
        $st = $this->db->prepare("SELECT s.id, s.user_id, s.status, u.email, TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))) AS n
                                  FROM secretaries s JOIN users u ON u.id = s.user_id LEFT JOIN user_profiles up ON up.user_id = u.id WHERE s.id = ?");
        $st->execute([$secretaryId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['ok' => false, 'error' => 'Secretary not found.'];
        if ($r['status'] === 'left') return ['ok' => false, 'error' => 'She has left, so no login is sent.'];
        $temp = bin2hex(random_bytes(8));
        $this->db->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?')->execute([password_hash($temp, PASSWORD_DEFAULT), (int) $r['user_id']]);
        return ['ok' => true] + $this->emailCredentials($secretaryId, (string) $r['n'], (string) $r['email'], $temp);
    }

    private function emailCredentials(int $secId, string $name, string $email, string $temp): array
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $loginUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('auth/login.php');
        $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $p = 'font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#111827;';
        $html = '<p style="' . $p . '">Hi ' . $h(explode(' ', trim($name))[0]) . ',</p>'
              . '<p style="' . $p . '">Your Dazu Training Institute staff account is ready. Sign in with these details:</p>'
              . '<p style="' . $p . 'background:#E3F3EA;border-radius:8px;padding:12px 16px;">Email: <strong>' . $h($email) . '</strong><br>Temporary password: <strong>' . $h($temp) . '</strong></p>'
              . '<p style="' . $p . '"><a href="' . $h($loginUrl) . '" style="background:#048C44;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;display:inline-block;">Sign in</a></p>'
              . '<p style="' . $p . '">You will be asked to choose your own password the first time you sign in.</p>'
              . '<p style="' . $p . '">Dazu Training Institute</p>';
        $subject = 'Your Dazu staff account — Dazu Training Institute';
        try {
            if ($this->mailFn) {
                $r = ($this->mailFn)($email, $name, $subject, $html);
            } else {
                $r = (new Mailer(require __DIR__ . '/../config/mail.php'))->send($email, $name, $subject, $html);
            }
        } catch (Throwable $e) {
            $r = ['ok' => false, 'message' => $e->getMessage()];
        }
        $sent = !empty($r['ok']) && empty($r['skipped']);
        if ($sent) $this->db->prepare('UPDATE secretaries SET credentials_sent_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$secId]);
        return ['email_sent' => $sent]
            + ($sent ? [] : ['email_error' => !empty($r['skipped']) ? 'Email sending is switched off on this server.' : (string) ($r['message'] ?? 'Email failed.')]);
    }

    /** The next free number for this year: DTI/SEC/001/26. */
    public function nextNo(?int $year = null): string
    {
        $yy = date('y', $year ? mktime(0, 0, 0, 1, 1, $year) : time());
        $st = $this->db->prepare('SELECT staff_no FROM secretaries WHERE staff_no LIKE ?');
        $st->execute(['DTI/SEC/%/' . $yy]);
        $max = 0;
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $no) {
            if (preg_match('#^DTI/SEC/(\d+)/' . $yy . '$#', (string) $no, $m)) $max = max($max, (int) $m[1]);
        }
        return sprintf('DTI/SEC/%03d/%s', $max + 1, $yy);
    }

    private function username(string $email): string
    {
        $base = substr(preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0])), 0, 20) ?: 'secretary';
        $st = $this->db->prepare('SELECT 1 FROM users WHERE username = ?');
        for ($n = 0; $n < 1000; $n++) {
            $u = 'SEC-' . $base . ($n ?: '');
            $st->execute([$u]);
            if (!$st->fetchColumn()) return $u;
        }
        return 'SEC-' . $base . bin2hex(random_bytes(3));
    }

    private function isDuplicate(Throwable $e): bool
    {
        $c = (string) $e->getCode();
        return $e instanceof PDOException && ($c === '23000' || $c === '23505' || stripos($e->getMessage(), 'duplicate') !== false);
    }
}
