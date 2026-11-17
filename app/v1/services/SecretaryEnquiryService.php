<?php
/**
 * The secretary team's enquiries log (people who ASKED about a course). Tables 127 and 128 only.
 *
 * PROSPECTS ONLY: nothing in this file reads, shows or links to a student record. It never touches the registrations, students, payments or users-as-students tables. The only existing data it
 * reads are (a) the existing course names, to offer them in the form (SELECT title FROM courses, read only), and (b) the secretary's own name (staff), for "added by" and the history.
 * "Registered" is a status set by hand; it is not linked to any account.
 *
 * Rules, all checked here on the server:
 *  - Every active secretary with the Enquiries switch on sees every enquiry (the page calls secretary_require('enquiries'); On leave does not block her).
 *  - She can change the status, add a note, change the follow-up date and take an enquiry over. She can NEVER delete one: there is no delete function here and the secretary pages refuse the request.
 *  - Every note shows who wrote it and when; every change goes to secretary_enquiry_history with the name and the time (the record the activity page reads).
 *  - Follow-up reminders (reminders() / the cron script): at 8:00 am Nairobi time on the follow-up date, to the secretary who added it (and to the one who took it over), once, through the existing
 *    notification (in-app and push) and mail code, claimed first in secretary_enquiry_reminder_log.
 */
require_once __DIR__ . '/../helpers/secretary.php';
require_once __DIR__ . '/PortalNotifications.php';
require_once __DIR__ . '/../helpers/secretary_activity.php';

class SecretaryEnquiryService
{
    public const STATUSES = ['pending' => 'Pending', 'called_back' => 'Called back', 'registered' => 'Registered', 'not_interested' => 'Not interested'];
    public const SOURCES = ['phone' => 'Phone call', 'walk_in' => 'Walk-in', 'website' => 'Website form', 'social' => 'Social media', 'other' => 'Other'];
    public const PHONE_RE = '/^[0-9+()\-\s]{7,20}$/';
    /** The follow-up reminder goes out from 8:00 am and is still sent up to this many hours late (a late cron run), never later. */
    public const LATE_HOURS = 4;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function tz(): DateTimeZone { return new DateTimeZone('Africa/Nairobi'); }

    public static function today(?DateTime $now = null): string { return ($now ? (clone $now)->setTimezone(self::tz()) : new DateTime('now', self::tz()))->format('Y-m-d'); }

    private function stamp(): string { return (new DateTime('now', self::tz()))->format('Y-m-d H:i:s'); }

    /** The existing course names, read only, for the form's suggestions. */
    public function courseNames(): array
    {
        try {
            return array_map('strval', $this->db->query('SELECT title FROM courses WHERE is_active = 1 ORDER BY title')->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            return [];
        }
    }

    // ------------------------------------------------------------------ the form

    private static function validDate(string $d): bool
    {
        $x = DateTime::createFromFormat('!Y-m-d', $d);
        return $x && $x->format('Y-m-d') === $d;
    }

    /** Check the add form. Returns [data, errors]. */
    public function validate(array $in): array
    {
        $e = [];
        $t = static fn($k) => trim((string) ($in[$k] ?? ''));
        $d = ['name' => $t('name'), 'phone' => $t('phone'), 'email' => strtolower($t('email')), 'course' => $t('course'), 'source' => $t('source'),
              'enquiry_date' => $t('enquiry_date') ?: self::today(), 'follow_up_date' => $t('follow_up_date'), 'note' => $t('note')];
        if ($d['name'] === '' || mb_strlen($d['name']) > 120) $e[] = 'Enter the person’s name (up to 120 letters).';
        if (!preg_match(self::PHONE_RE, $d['phone'])) $e[] = 'Enter a valid phone number.';
        if ($d['email'] !== '' && (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 150)) $e[] = 'The email address does not look right. You can leave it empty.';
        if ($d['course'] === '' || mb_strlen($d['course']) > 150) $e[] = 'Enter the course they asked about.';
        if (!isset(self::SOURCES[$d['source']])) $e[] = 'Choose how they asked: Phone call, Walk-in, Website form, Social media or Other.';
        if (!self::validDate($d['enquiry_date'])) $e[] = 'Choose the date of the enquiry.';
        if ($d['follow_up_date'] !== '' && !self::validDate($d['follow_up_date'])) $e[] = 'Choose a valid follow-up date, or leave it empty.';
        if (mb_strlen($d['note']) > 1000) $e[] = 'The note is too long (1000 letters at most).';
        $d['follow_up_date'] = $d['follow_up_date'] === '' ? null : $d['follow_up_date'];
        $d['email'] = $d['email'] === '' ? null : $d['email'];
        return [$d, $e];
    }

    // ------------------------------------------------------------------ reading

    private function nameOf(array $sec): string { return secretary_person_name($this->db, (int) $sec['user_id']); }

    /** The four boxes: pending, follow up today (not Registered or Not interested), called back, registered. */
    public function stats(?DateTime $now = null): array
    {
        $today = self::today($now);
        $c = fn(string $w, array $a = []) => (function () use ($w, $a) { $s = $this->db->prepare("SELECT COUNT(*) FROM secretary_enquiries WHERE $w"); $s->execute($a); return (int) $s->fetchColumn(); })();
        return ['pending' => $c("status = 'pending'"), 'follow_today' => $c("follow_up_date = ? AND status IN ('pending', 'called_back')", [$today]),
                'called_back' => $c("status = 'called_back'"), 'registered' => $c("status = 'registered'")];
    }

    /** Escape a search word for LIKE. */
    private static function like(string $q): string { return '%' . strtolower(str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q)) . '%'; }

    /**
     * The list, newest first. $filter: '' (all) or a status key. $q: search in name, phone, email and course. Each row has the names of who added it (name only).
     * Returns [rows, total].
     */
    public function search(string $q = '', string $filter = '', int $limit = 100, int $offset = 0, bool $followToday = false, ?DateTime $now = null): array
    {
        $w = ['1 = 1']; $a = [];
        if ($followToday) { $w[] = "follow_up_date = ? AND status IN ('pending', 'called_back')"; $a[] = self::today($now); }
        if (isset(self::STATUSES[$filter])) { $w[] = 'status = ?'; $a[] = $filter; }
        $q = trim($q);
        if ($q !== '') {
            $w[] = "(LOWER(name) LIKE ? ESCAPE '!' OR LOWER(phone) LIKE ? ESCAPE '!' OR LOWER(COALESCE(email, '')) LIKE ? ESCAPE '!' OR LOWER(course) LIKE ? ESCAPE '!')";
            $l = self::like($q); array_push($a, $l, $l, $l, $l);
        }
        $where = implode(' AND ', $w);
        $cnt = $this->db->prepare("SELECT COUNT(*) FROM secretary_enquiries WHERE $where"); $cnt->execute($a);
        $st = $this->db->prepare("SELECT * FROM secretary_enquiries WHERE $where ORDER BY enquiry_date DESC, id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset);
        $st->execute($a);
        return [$st->fetchAll(PDO::FETCH_ASSOC), (int) $cnt->fetchColumn()];
    }

    /** One enquiry with its notes (oldest first) and its history (newest first). */
    public function get(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM secretary_enquiries WHERE id = ?');
        $st->execute([$id]);
        $e = $st->fetch(PDO::FETCH_ASSOC);
        if (!$e) return null;
        $n = $this->db->prepare('SELECT * FROM secretary_enquiry_notes WHERE enquiry_id = ? ORDER BY id'); $n->execute([$id]);
        $h = $this->db->prepare('SELECT * FROM secretary_enquiry_history WHERE enquiry_id = ? ORDER BY id DESC'); $h->execute([$id]);
        $e['notes'] = $n->fetchAll(PDO::FETCH_ASSOC);
        $e['history'] = $h->fetchAll(PDO::FETCH_ASSOC);
        // is the secretary who has it away? (a badge only: On leave, Left or suspended)
        $e['away'] = $this->awayStatus((int) ($e['handled_by'] ?: $e['added_by']));
        return $e;
    }

    /** '' when she is active, else 'On leave', 'Left' or 'Suspended' (a badge for the team; nothing is blocked). */
    private function awayStatus(int $secretaryId): string
    {
        $st = $this->db->prepare('SELECT s.status, u.account_status FROM secretaries s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
        $st->execute([$secretaryId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return '';
        if ($r['status'] === 'left') return 'Left';
        if ($r['account_status'] === 'suspended') return 'Suspended';
        return $r['status'] === 'on_leave' ? 'On leave' : '';
    }

    // ------------------------------------------------------------------ writing

    private function history(int $enquiryId, ?array $sec, string $action, ?string $old, ?string $new, ?string $name = null): void
    {
        $this->db->prepare('INSERT INTO secretary_enquiry_history (enquiry_id, secretary_id, actor_name, action, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                 ->execute([$enquiryId, $sec ? (int) $sec['id'] : null, mb_substr($name ?? ($sec ? $this->nameOf($sec) : ''), 0, 160), $action, $old === null ? null : mb_substr($old, 0, 200), $new === null ? null : mb_substr($new, 0, 200), $this->stamp()]);
        if ($sec) {   // the activity record (one shared function; the key says where it came from, so it is never recorded twice)
            $hid = (int) $this->db->lastInsertId();
            $n = $this->db->prepare('SELECT name FROM secretary_enquiries WHERE id = ?'); $n->execute([$enquiryId]);
            $map = ['created' => 'added', 'follow_up' => 'follow-up date', 'taken_over' => 'took over'];
            secretary_activity($this->db, 'enquiries', $map[$action] ?? $action, (int) $sec['id'], $name ?? $this->nameOf($sec), (string) $n->fetchColumn(), $new, 'enq:' . $hid);
        }
    }

    private function writeNote(int $enquiryId, array $sec, string $body): void
    {
        $this->db->prepare('INSERT INTO secretary_enquiry_notes (enquiry_id, secretary_id, author_name, body, created_at) VALUES (?, ?, ?, ?, ?)')
                 ->execute([$enquiryId, (int) $sec['id'], mb_substr($this->nameOf($sec), 0, 160), $body, $this->stamp()]);
    }

    /** Add an enquiry (validated data from validate()). Returns ['ok', 'id'] or ['ok' => false, 'error']. */
    public function add(array $sec, array $d): array
    {
        $me = $this->nameOf($sec);
        try {
            $this->db->beginTransaction();
            $this->db->prepare('INSERT INTO secretary_enquiries (name, phone, email, course, source, enquiry_date, follow_up_date, status, added_by, added_by_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', ?, ?, ?)')
                     ->execute([$d['name'], $d['phone'], $d['email'], $d['course'], $d['source'], $d['enquiry_date'], $d['follow_up_date'], (int) $sec['id'], $me, $this->stamp()]);
            $id = (int) $this->db->lastInsertId();
            $this->history($id, $sec, 'created', null, $d['name'] . ' · ' . $d['course']);
            if (($d['note'] ?? '') !== '') { $this->writeNote($id, $sec, $d['note']); $this->history($id, $sec, 'note', null, 'First note'); }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[SecretaryEnquiryService::add] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not save the enquiry. Nothing was saved. Please try again.'];
        }
        return ['ok' => true, 'id' => $id];
    }

    private function row(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM secretary_enquiries WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function setStatus(array $sec, int $id, string $status): array
    {
        if (!isset(self::STATUSES[$status])) return ['ok' => false, 'error' => 'Choose Pending, Called back, Registered or Not interested.'];
        $e = $this->row($id);
        if (!$e) return ['ok' => false, 'error' => 'That enquiry was not found.'];
        if ($e['status'] === $status) return ['ok' => true, 'changed' => false];
        $this->db->prepare('UPDATE secretary_enquiries SET status = ? WHERE id = ?')->execute([$status, $id]);
        $this->history($id, $sec, 'status', self::STATUSES[$e['status']] ?? $e['status'], self::STATUSES[$status]);
        return ['ok' => true, 'changed' => true];
    }

    public function setFollowUp(array $sec, int $id, string $date): array
    {
        $date = trim($date);
        if ($date !== '' && !self::validDate($date)) return ['ok' => false, 'error' => 'Choose a valid follow-up date, or leave it empty to clear it.'];
        $e = $this->row($id);
        if (!$e) return ['ok' => false, 'error' => 'That enquiry was not found.'];
        $old = (string) ($e['follow_up_date'] ?? '');
        if ($old === $date) return ['ok' => true, 'changed' => false];
        $this->db->prepare('UPDATE secretary_enquiries SET follow_up_date = ? WHERE id = ?')->execute([$date === '' ? null : $date, $id]);
        $this->history($id, $sec, 'follow_up', $old === '' ? 'none' : $old, $date === '' ? 'none' : $date);
        return ['ok' => true, 'changed' => true];
    }

    public function addNote(array $sec, int $id, string $body): array
    {
        $body = trim($body);
        if ($body === '') return ['ok' => false, 'error' => 'Write the note first.'];
        if (mb_strlen($body) > 1000) return ['ok' => false, 'error' => 'The note is too long (1000 letters at most).'];
        if (!$this->row($id)) return ['ok' => false, 'error' => 'That enquiry was not found.'];
        $this->writeNote($id, $sec, $body);
        $this->history($id, $sec, 'note', null, mb_substr($body, 0, 120));
        return ['ok' => true];
    }

    /** Take the enquiry over (for example from a secretary who is away). It stays visible to everyone; the history says who took it from whom. */
    public function takeOver(array $sec, int $id): array
    {
        $e = $this->row($id);
        if (!$e) return ['ok' => false, 'error' => 'That enquiry was not found.'];
        $curId = (int) ($e['handled_by'] ?: $e['added_by']);
        if ($curId === (int) $sec['id']) return ['ok' => false, 'error' => 'You already have this enquiry.'];
        $from = $e['handled_by'] ? (string) $e['handled_by_name'] : (string) $e['added_by_name'];
        $me = $this->nameOf($sec);
        $this->db->prepare('UPDATE secretary_enquiries SET handled_by = ?, handled_by_name = ? WHERE id = ?')->execute([(int) $sec['id'], $me, $id]);
        $this->history($id, $sec, 'taken_over', $from, $me);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ follow-up reminders (cron)

    /**
     * Who should be reminded now. A follow-up today, status Pending or Called back; from 8:00 am Nairobi time and up to LATE_HOURS late. The secretary who added it, and the one who took it over
     * if different, when she is not Left, her login is active and her Enquiries switch is on (On leave does not stop it).
     * @return array<int, array{enquiry: array, user_id: int, name: string, first: string, email: string}>
     */
    public function dueReminders(DateTime $now): array
    {
        $now = (clone $now)->setTimezone(self::tz());
        $today = $now->format('Y-m-d');
        $from = new DateTime($today . ' 08:00:00', self::tz());
        $to = (clone $from)->modify('+' . self::LATE_HOURS . ' hours');
        if ($now < $from || $now > $to) return [];
        $st = $this->db->prepare("SELECT * FROM secretary_enquiries WHERE follow_up_date = ? AND status IN ('pending', 'called_back') ORDER BY id");
        $st->execute([$today]);
        $who = $this->db->prepare("SELECT s.id, s.user_id, u.email, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))), ''), u.username) AS full_name, TRIM(COALESCE(p.first_name, '')) AS first_name
                                   FROM secretaries s JOIN users u ON u.id = s.user_id LEFT JOIN user_profiles p ON p.user_id = u.id
                                   WHERE s.id = ? AND s.status <> 'left' AND u.is_active = 1
                                     AND EXISTS (SELECT 1 FROM secretary_switches w WHERE w.secretary_id = s.id AND w.switch_key = 'enquiries' AND w.switch_value <> 'off')");
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $e) {
            foreach (array_unique(array_filter([(int) $e['added_by'], (int) $e['handled_by']])) as $sid) {
                $who->execute([$sid]);
                $r = $who->fetch(PDO::FETCH_ASSOC);
                if (!$r) continue;
                $out[] = ['enquiry' => $e, 'user_id' => (int) $r['user_id'], 'name' => (string) $r['full_name'], 'first' => $r['first_name'] !== '' ? (string) $r['first_name'] : (string) $r['full_name'], 'email' => (string) $r['email']];
            }
        }
        return $out;
    }

    /** Claim one send: true only for the run that gets it (the UNIQUE key decides). A dry run only checks. */
    private function claim(int $enquiryId, int $userId, string $date, string $channel, bool $dry): bool
    {
        if ($dry) {
            $s = $this->db->prepare('SELECT 1 FROM secretary_enquiry_reminder_log WHERE enquiry_id = ? AND user_id = ? AND follow_up_date = ? AND channel = ?');
            $s->execute([$enquiryId, $userId, $date, $channel]);
            return !$s->fetchColumn();
        }
        try {
            $this->db->prepare("INSERT INTO secretary_enquiry_reminder_log (enquiry_id, user_id, follow_up_date, channel, status, detail, created_at) VALUES (?, ?, ?, ?, 'claimed', '', ?)")
                     ->execute([$enquiryId, $userId, $date, $channel, $this->stamp()]);
            return true;
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000' || (string) $e->getCode() === '23505' || stripos($e->getMessage(), 'duplicate') !== false || stripos($e->getMessage(), 'unique') !== false) return false;
            throw $e;
        }
    }

    private function finish(int $enquiryId, int $userId, string $date, string $channel, string $status, string $detail): void
    {
        $this->db->prepare('UPDATE secretary_enquiry_reminder_log SET status = ?, detail = ? WHERE enquiry_id = ? AND user_id = ? AND follow_up_date = ? AND channel = ?')
                 ->execute([$status, mb_substr($detail, 0, 250), $enquiryId, $userId, $date, $channel]);
    }

    /**
     * One pass of the reminder job at $now. $opt: dry (only report, write nothing), no_send (do everything except call the notification and mail code; for tests), only_users, mail_fn (test: replaces Mailer).
     * Returns ['jobs', 'sent_app', 'sent_email', 'dupe', 'failed', 'would', 'lines'].
     */
    public function reminders(DateTime $now, array $opt = []): array
    {
        $st = ['jobs' => 0, 'sent_app' => 0, 'sent_email' => 0, 'dupe' => 0, 'failed' => 0, 'would' => 0, 'lines' => []];
        $dry = !empty($opt['dry']); $noSend = !empty($opt['no_send']);
        $only = isset($opt['only_users']) ? array_map('intval', (array) $opt['only_users']) : null;
        foreach ($this->dueReminders($now) as $j) {
            if ($only !== null && !in_array($j['user_id'], $only, true)) continue;
            $st['jobs']++;
            $e = $j['enquiry']; $date = (string) $e['follow_up_date']; $uid = $j['user_id'];
            $title = 'Follow up today';
            $body = $e['name'] . ' · ' . $e['course'] . ' · ' . $e['phone'];
            $path = 'secretary/enquiries/view.php?id=' . (int) $e['id'];
            foreach (['app', 'email'] as $ch) {
                try {
                    if ($ch === 'email' && !filter_var($j['email'], FILTER_VALIDATE_EMAIL)) continue;
                    if (!$this->claim((int) $e['id'], $uid, $date, $ch, $dry)) { $st['dupe']++; continue; }
                    if ($dry) { $st['lines'][] = 'WOULD SEND ' . $ch . ' → ' . $j['name'] . ': ' . $body; $st['would']++; continue; }
                    if ($noSend) { $this->finish((int) $e['id'], $uid, $date, $ch, 'sent', 'no-send (test)'); $st[$ch === 'app' ? 'sent_app' : 'sent_email']++; continue; }
                    if ($ch === 'app') {   // the existing notification code: the in-app list and the phone push to every device she turned alerts on
                        PortalNotifications::notify($this->db, $uid, 'enquiry_followup', $title, $body, $path);
                        $this->finish((int) $e['id'], $uid, $date, 'app', 'sent', '');
                        $st['sent_app']++;
                        continue;
                    }
                    $r = $this->sendMail($j, $title, $body, $path, $opt['mail_fn'] ?? null);
                    $good = !empty($r['ok']) && empty($r['skipped']);
                    $this->finish((int) $e['id'], $uid, $date, 'email', $good ? 'sent' : (!empty($r['skipped']) ? 'skipped' : 'failed'), $good ? '' : (string) ($r['message'] ?? 'not sent'));
                    if ($good) $st['sent_email']++; elseif (empty($r['skipped'])) $st['failed']++;
                } catch (Throwable $ex) {   // one person's failure is logged and never stops the others
                    $st['failed']++;
                    error_log('[secretary_enquiry_reminders] ' . $ch . ' for user ' . $uid . ': ' . $ex->getMessage());
                    try { $this->finish((int) $e['id'], $uid, $date, $ch, 'failed', $ex->getMessage()); } catch (Throwable $e2) { }
                }
            }
        }
        return $st;
    }

    private function sendMail(array $j, string $title, string $body, string $path, ?callable $fn): array
    {
        $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $p = 'font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#111827;';
        $link = PortalNotifications::absolute($path);
        $html = '<p style="' . $p . '">Hi ' . $h($j['first']) . ',</p><p style="' . $p . '"><strong>' . $h($title) . '</strong>: ' . $h($body) . '</p>'
              . '<p style="' . $p . '"><a href="' . $h($link) . '" style="background:#048C44;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;display:inline-block;">Open the enquiry</a></p>'
              . '<p style="' . $p . '">Dazu Training Institute</p>';
        $subject = $title . ' — Dazu Training Institute';
        if ($fn) return $fn($j['email'], $j['name'], $subject, $html);
        require_once __DIR__ . '/Mailer.php';
        return (new Mailer(require __DIR__ . '/../config/mail.php'))->send($j['email'], $j['name'], $subject, $html);
    }
}
