<?php
/**
 * Tasks the admin gives to a secretary (tables 129 and 130). Everything is new; nothing existing is edited.
 *
 *  - ONLY the normal admin creates, changes, re-opens and deletes tasks (the admin pages call secretary_admin_require()). A secretary can only tick HER OWN task and add a short note to it: tick() and addNote()
 *    refuse a task that is not hers, so she never sees or touches another secretary's task.
 *  - A task shows on HER Staff Calendar as a private "deadline" entry (written with a plain insert into the Staff Calendar's own table, no Staff Calendar code or file is edited). It is visible to nobody else, and it
 *    follows the task (title, time, done). Its own reminders are off: the reminders below do the reminding. A secretary whose calendar level is Off simply never opens the calendar.
 *  - Notices: a new task -> the secretary; a tick -> the admin who gave it. Reminders (reminders(), run by app/v1/tools/secretary_task_reminders.php): the day before the due date and on the due date, from 8:00 am
 *    Nairobi time (up to 4 hours late), to the secretary, once each, through the existing notification (in-app and push) and mail code, claimed first in secretary_task_reminder_log.
 *  - Every tick and every note goes to the activity record through the one shared function.
 */
require_once __DIR__ . '/../helpers/secretary.php';
require_once __DIR__ . '/../helpers/secretary_activity.php';
require_once __DIR__ . '/PortalNotifications.php';

class SecretaryTaskService
{
    public const LATE_HOURS = 4;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function tz(): DateTimeZone { return new DateTimeZone('Africa/Nairobi'); }
    public static function now(): DateTime { return new DateTime('now', self::tz()); }
    private function stamp(): string { return self::now()->format('Y-m-d H:i:s'); }

    // ------------------------------------------------------------------ the admin

    /** Check the admin's task form. Returns [data, errors]. */
    public function validate(array $in): array
    {
        $e = [];
        $t = static fn($k) => trim((string) ($in[$k] ?? ''));
        $d = ['secretary_id' => (int) ($in['secretary_id'] ?? 0), 'title' => $t('title'), 'description' => $t('description'), 'due_date' => $t('due_date'), 'due_time' => $t('due_time') ?: '17:00'];
        if ($d['title'] === '' || mb_strlen($d['title']) > 200) $e[] = 'Give the task a title (up to 200 letters).';
        if (mb_strlen($d['description']) > 2000) $e[] = 'The description is too long (2000 letters at most).';
        $due = DateTime::createFromFormat('!Y-m-d H:i', $d['due_date'] . ' ' . $d['due_time'], self::tz());
        if (!$due || $due->format('Y-m-d H:i') !== $d['due_date'] . ' ' . $d['due_time']) $e[] = 'Choose a valid due date and time.';
        $d['due_at'] = $due ? $due->format('Y-m-d H:i:s') : null;
        return [$d, $e];
    }

    /** The secretaries a task can be given to: not Left, login active. [id => name]. */
    public function assignable(): array
    {
        $out = [];
        foreach ($this->db->query("SELECT s.id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))), ''), u.username) AS n
                                   FROM secretaries s JOIN users u ON u.id = s.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE s.status <> 'left' AND u.is_active = 1 ORDER BY n")->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['id']] = (string) $r['n'];
        return $out;
    }

    /** Give a task. $adminId is the admin's users.id. Returns ['ok', 'id'] or ['ok' => false, 'error']. */
    public function give(int $adminId, array $d): array
    {
        $sec = $this->secretaryRow((int) $d['secretary_id']);
        if (!$sec || $sec['status'] === 'left') return ['ok' => false, 'error' => 'Choose a secretary who is still with the team.'];
        $me = secretary_person_name($this->db, $adminId);
        $this->db->prepare('INSERT INTO secretary_tasks (secretary_id, title, description, due_at, status, given_by, given_by_name, created_at, updated_at) VALUES (?, ?, ?, ?, \'open\', ?, ?, ?, ?)')
                 ->execute([(int) $d['secretary_id'], $d['title'], $d['description'] === '' ? null : $d['description'], $d['due_at'], $adminId, $me, $this->stamp(), $this->stamp()]);
        $id = (int) $this->db->lastInsertId();
        $this->syncCalendar($id);
        $this->notify((int) $sec['user_id'], 'New task from ' . $me, $d['title'] . ' · due ' . date('D j M, g:i a', strtotime((string) $d['due_at'])), 'secretary/tasks/index.php');
        return ['ok' => true, 'id' => $id];
    }

    public function update(int $adminId, int $id, array $d): array
    {
        $t = $this->get($id);
        if (!$t) return ['ok' => false, 'error' => 'That task was not found.'];
        $this->db->prepare('UPDATE secretary_tasks SET title = ?, description = ?, due_at = ?, updated_at = ? WHERE id = ?')
                 ->execute([$d['title'], $d['description'] === '' ? null : $d['description'], $d['due_at'], $this->stamp(), $id]);
        $this->syncCalendar($id);
        if ($t['due_at'] !== $d['due_at'] && $t['status'] === 'open') $this->notify((int) $t['user_id'], 'Task changed', $d['title'] . ' · due ' . date('D j M, g:i a', strtotime((string) $d['due_at'])), 'secretary/tasks/index.php');
        return ['ok' => true];
    }

    /** The admin opens a ticked task again. */
    public function reopen(int $adminId, int $id): array
    {
        $t = $this->get($id);
        if (!$t) return ['ok' => false, 'error' => 'That task was not found.'];
        $this->db->prepare("UPDATE secretary_tasks SET status = 'open', done_at = NULL, updated_at = ? WHERE id = ?")->execute([$this->stamp(), $id]);
        $this->syncCalendar($id);
        return ['ok' => true];
    }

    /** Only the admin deletes a task (the secretary pages have no delete). Its calendar entry goes with it. */
    public function delete(int $adminId, int $id): array
    {
        $t = $this->get($id);
        if (!$t) return ['ok' => false, 'error' => 'That task was not found.'];
        $this->dropCalendar($t);
        $this->db->prepare('DELETE FROM secretary_tasks WHERE id = ?')->execute([$id]);
        return ['ok' => true];
    }

    /** Every task for the admin, with the secretary's name and the latest note: soonest due first among the open ones, then the done ones. */
    public function listAll(int $secretaryId = 0, string $status = ''): array
    {
        $w = ['1 = 1']; $a = [];
        if ($secretaryId > 0) { $w[] = 't.secretary_id = ?'; $a[] = $secretaryId; }
        if ($status === 'open' || $status === 'done') { $w[] = 't.status = ?'; $a[] = $status; }
        $st = $this->db->prepare("SELECT t.*, s.user_id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))), ''), u.username) AS secretary_name,
                                         (SELECT COUNT(*) FROM secretary_task_notes n WHERE n.task_id = t.id) AS note_count
                                  FROM secretary_tasks t JOIN secretaries s ON s.id = t.secretary_id JOIN users u ON u.id = s.user_id LEFT JOIN user_profiles p ON p.user_id = u.id
                                  WHERE " . implode(' AND ', $w) . " ORDER BY CASE WHEN t.status = 'open' THEN 0 ELSE 1 END, t.due_at");
        $st->execute($a);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------ the secretary

    public function get(int $id): ?array
    {
        $st = $this->db->prepare('SELECT t.*, s.user_id FROM secretary_tasks t JOIN secretaries s ON s.id = t.secretary_id WHERE t.id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** HER tasks only: open ones by due date, then done ones (newest first). Each has its notes. */
    public function forSecretary(int $secretaryId): array
    {
        $st = $this->db->prepare("SELECT * FROM secretary_tasks WHERE secretary_id = ? ORDER BY CASE WHEN status = 'open' THEN 0 ELSE 1 END, CASE WHEN status = 'open' THEN due_at END, done_at DESC");
        $st->execute([$secretaryId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $n = $this->db->prepare('SELECT * FROM secretary_task_notes WHERE task_id = ? ORDER BY id');
        foreach ($rows as &$r) { $n->execute([(int) $r['id']]); $r['notes'] = $n->fetchAll(PDO::FETCH_ASSOC); }
        return $rows;
    }

    /** The pill for a task: [text, class] — Done, Overdue, Today, Tomorrow, In N days. */
    public static function pill(array $t, ?DateTime $now = null): array
    {
        if ($t['status'] === 'done') return ['Done', 'done'];
        $now = $now ? (clone $now)->setTimezone(self::tz()) : self::now();
        $today = new DateTime($now->format('Y-m-d'), self::tz());
        $due = new DateTime(substr((string) $t['due_at'], 0, 10), self::tz());
        $n = (int) $today->diff($due)->format('%r%a');
        if ($n < 0 || ($n === 0 && (string) $t['due_at'] < $now->format('Y-m-d H:i:s'))) return ['Overdue', 'late'];
        return $n === 0 ? ['Today', 'today'] : ($n === 1 ? ['Tomorrow', 'soon'] : ['In ' . $n . ' days', 'soon']);
    }

    /** She ticks her own task, with an optional short note. */
    public function tick(array $sec, int $id, string $note = ''): array
    {
        $t = $this->get($id);
        if (!$t || (int) $t['secretary_id'] !== (int) $sec['id']) return ['ok' => false, 'error' => 'That task was not found.'];   // another secretary's task looks like it does not exist
        if ($t['status'] === 'done') return ['ok' => false, 'error' => 'That task is already ticked.'];
        $note = trim($note);
        if (mb_strlen($note) > 500) return ['ok' => false, 'error' => 'The note is too long (500 letters at most).'];
        $me = secretary_person_name($this->db, (int) $sec['user_id']);
        $now = $this->stamp();
        $claim = $this->db->prepare("UPDATE secretary_tasks SET status = 'done', done_at = ?, updated_at = ? WHERE id = ? AND status = 'open'");
        $claim->execute([$now, $now, $id]);
        if ($claim->rowCount() !== 1) return ['ok' => false, 'error' => 'That task is already ticked.'];
        if ($note !== '') $this->writeNote($id, $sec, $note, $me);
        $this->syncCalendar($id);
        secretary_activity($this->db, 'tasks', 'ticked', (int) $sec['id'], $me, (string) $t['title'], $note !== '' ? $note : null, 'task:' . $id . ':tick');
        $to = $this->adminToTell($t);
        foreach ($to as $adminUser) $this->notify($adminUser, $me . ' ticked a task', '“' . $t['title'] . '” was done ' . date('D j M, g:i a', strtotime($now)) . ($note !== '' ? ' · ' . $note : ''), 'admin/secretaries/tasks.php');
        return ['ok' => true];
    }

    public function addNote(array $sec, int $id, string $body): array
    {
        $t = $this->get($id);
        if (!$t || (int) $t['secretary_id'] !== (int) $sec['id']) return ['ok' => false, 'error' => 'That task was not found.'];
        $body = trim($body);
        if ($body === '') return ['ok' => false, 'error' => 'Write the note first.'];
        if (mb_strlen($body) > 500) return ['ok' => false, 'error' => 'The note is too long (500 letters at most).'];
        $me = secretary_person_name($this->db, (int) $sec['user_id']);
        $nid = $this->writeNote($id, $sec, $body, $me);
        secretary_activity($this->db, 'tasks', 'note', (int) $sec['id'], $me, (string) $t['title'], $body, 'taskn:' . $nid);
        return ['ok' => true];
    }

    private function writeNote(int $taskId, array $sec, string $body, string $name): int
    {
        $this->db->prepare('INSERT INTO secretary_task_notes (task_id, secretary_id, author_name, body, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$taskId, (int) $sec['id'], mb_substr($name, 0, 160), $body, $this->stamp()]);
        return (int) $this->db->lastInsertId();
    }

    /** The admin to tell: the one who gave it (when still an active admin), else every active admin. users.id list. */
    private function adminToTell(array $t): array
    {
        if (!empty($t['given_by'])) {
            $q = $this->db->prepare("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND r.role_name = 'admin' AND u.is_active = 1");
            $q->execute([(int) $t['given_by']]);
            if ($q->fetchColumn()) return [(int) $t['given_by']];
        }
        return array_map('intval', $this->db->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.role_name = 'admin' AND u.is_active = 1")->fetchAll(PDO::FETCH_COLUMN));
    }

    private function secretaryRow(int $id): ?array
    {
        $st = $this->db->prepare('SELECT s.id, s.user_id, s.status FROM secretaries s WHERE s.id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function notify(int $userId, string $title, string $body, string $path): void
    {
        try { PortalNotifications::notify($this->db, $userId, 'secretary_task', $title, $body, $path); } catch (Throwable $e) { error_log('[SecretaryTaskService] notify: ' . $e->getMessage()); }
    }

    // ------------------------------------------------------------------ the Staff Calendar copy (private, hers only)

    /** Make the private calendar entry match the task. Silent when the Staff Calendar tables are not installed. */
    public function syncCalendar(int $taskId): void
    {
        try {
            $t = $this->get($taskId);
            if (!$t) return;
            $title = ($t['status'] === 'done' ? 'Done: ' : 'Task: ') . $t['title'];
            $desc = 'Given by ' . $t['given_by_name'] . '. This entry comes from My Tasks; changing it here does not change the task.';
            if (!empty($t['calendar_event_id'])) {
                $x = $this->db->prepare('SELECT id FROM staff_calendar_events WHERE id = ?'); $x->execute([(int) $t['calendar_event_id']]);
                if ($x->fetchColumn()) {
                    $this->db->prepare('UPDATE staff_calendar_events SET title = ?, description = ?, start_at = ?, end_at = ?, owner_user_id = ? WHERE id = ?')
                             ->execute([mb_substr($title, 0, 200), $desc, $t['due_at'], $t['due_at'], (int) $t['user_id'], (int) $t['calendar_event_id']]);
                    return;
                }
            }
            $this->db->prepare("INSERT INTO staff_calendar_events (title, description, type_key, start_at, end_at, all_day, repeat_rule, owner_user_id, notify_all, is_private, remind_day_before, remind_on_day, remind_hour_before, remind_email)
                                VALUES (?, ?, 'deadline', ?, ?, 0, 'none', ?, 0, 1, 0, 0, 0, 0)")
                     ->execute([mb_substr($title, 0, 200), $desc, $t['due_at'], $t['due_at'], (int) $t['user_id']]);
            $this->db->prepare('UPDATE secretary_tasks SET calendar_event_id = ? WHERE id = ?')->execute([(int) $this->db->lastInsertId(), $taskId]);
        } catch (Throwable $e) {
            error_log('[SecretaryTaskService] calendar: ' . $e->getMessage());
        }
    }

    private function dropCalendar(array $t): void
    {
        try { if (!empty($t['calendar_event_id'])) $this->db->prepare('DELETE FROM staff_calendar_events WHERE id = ?')->execute([(int) $t['calendar_event_id']]); } catch (Throwable $e) { error_log('[SecretaryTaskService] calendar: ' . $e->getMessage()); }
    }

    // ------------------------------------------------------------------ the dashboard

    /** [tasks_today, next three open tasks as dashboard rows] for one secretary. */
    public function dashboard(int $secretaryId, ?DateTime $now = null): array
    {
        $now = $now ? (clone $now)->setTimezone(self::tz()) : self::now();
        $today = $now->format('Y-m-d');
        $c = $this->db->prepare("SELECT COUNT(*) FROM secretary_tasks WHERE secretary_id = ? AND status = 'open' AND due_at >= ? AND due_at < ?");
        $c->execute([$secretaryId, $today . ' 00:00:00', (new DateTime($today, self::tz()))->modify('+1 day')->format('Y-m-d H:i:s')]);
        $st = $this->db->prepare("SELECT * FROM secretary_tasks WHERE secretary_id = ? AND status = 'open' ORDER BY due_at LIMIT 3");
        $st->execute([$secretaryId]);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
            [$chip, $cls] = self::pill($t, $now);
            $rows[] = ['title' => $t['title'], 'due' => 'Due ' . date('D j M, g:i a', strtotime((string) $t['due_at'])) . ' · given by ' . $t['given_by_name'], 'chip' => $chip, 'chip_class' => $cls === 'late' ? 'pending' : ($cls === 'today' ? 'today' : ''), 'id' => (int) $t['id']];
        }
        return [(int) $c->fetchColumn(), $rows];
    }

    // ------------------------------------------------------------------ reminders (cron)

    /**
     * Who should be reminded now: an open task due today ("on_day") or tomorrow ("day_before"), from 8:00 am Nairobi time and up to LATE_HOURS late, to the secretary who has it when she is not Left,
     * her login is active and her My Tasks switch is on (On leave does not stop it).
     * @return array<int, array{task: array, kind: string, user_id: int, name: string, first: string, email: string}>
     */
    public function dueReminders(DateTime $now): array
    {
        $now = (clone $now)->setTimezone(self::tz());
        $today = $now->format('Y-m-d');
        $from = new DateTime($today . ' 08:00:00', self::tz());
        if ($now < $from || $now > (clone $from)->modify('+' . self::LATE_HOURS . ' hours')) return [];
        $tomorrow = (new DateTime($today, self::tz()))->modify('+1 day')->format('Y-m-d');
        $st = $this->db->prepare("SELECT t.*, s.user_id, s.status AS sec_status, u.email, u.is_active, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))), ''), u.username) AS full_name, TRIM(COALESCE(p.first_name, '')) AS first_name
                                  FROM secretary_tasks t JOIN secretaries s ON s.id = t.secretary_id JOIN users u ON u.id = s.user_id LEFT JOIN user_profiles p ON p.user_id = u.id
                                  WHERE t.status = 'open' AND t.due_at >= ? AND t.due_at < ? AND s.status <> 'left' AND u.is_active = 1
                                    AND EXISTS (SELECT 1 FROM secretary_switches w WHERE w.secretary_id = s.id AND w.switch_key = 'tasks' AND w.switch_value <> 'off') ORDER BY t.id");
        $st->execute([$today . ' 00:00:00', (new DateTime($tomorrow, self::tz()))->modify('+1 day')->format('Y-m-d H:i:s')]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $day = substr((string) $t['due_at'], 0, 10);
            $out[] = ['task' => $t, 'kind' => $day === $today ? 'on_day' : 'day_before', 'user_id' => (int) $t['user_id'], 'name' => (string) $t['full_name'], 'first' => $t['first_name'] !== '' ? (string) $t['first_name'] : (string) $t['full_name'], 'email' => (string) $t['email']];
        }
        return $out;
    }

    private function claim(int $taskId, int $userId, string $kind, string $channel, bool $dry): bool
    {
        if ($dry) {
            $s = $this->db->prepare('SELECT 1 FROM secretary_task_reminder_log WHERE task_id = ? AND user_id = ? AND kind = ? AND channel = ?');
            $s->execute([$taskId, $userId, $kind, $channel]);
            return !$s->fetchColumn();
        }
        try {
            $this->db->prepare("INSERT INTO secretary_task_reminder_log (task_id, user_id, kind, channel, status, detail, created_at) VALUES (?, ?, ?, ?, 'claimed', '', ?)")->execute([$taskId, $userId, $kind, $channel, $this->stamp()]);
            return true;
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000' || (string) $e->getCode() === '23505' || stripos($e->getMessage(), 'duplicate') !== false || stripos($e->getMessage(), 'unique') !== false) return false;
            throw $e;
        }
    }

    private function finish(int $taskId, int $userId, string $kind, string $channel, string $status, string $detail): void
    {
        $this->db->prepare('UPDATE secretary_task_reminder_log SET status = ?, detail = ? WHERE task_id = ? AND user_id = ? AND kind = ? AND channel = ?')->execute([$status, mb_substr($detail, 0, 250), $taskId, $userId, $kind, $channel]);
    }

    /** One pass at $now. $opt: dry, no_send (everything except calling push and mail; for tests), only_users, mail_fn (test). Returns ['jobs', 'sent_app', 'sent_email', 'dupe', 'failed', 'would', 'lines']. */
    public function reminders(DateTime $now, array $opt = []): array
    {
        $st = ['jobs' => 0, 'sent_app' => 0, 'sent_email' => 0, 'dupe' => 0, 'failed' => 0, 'would' => 0, 'lines' => []];
        $dry = !empty($opt['dry']); $noSend = !empty($opt['no_send']);
        $only = isset($opt['only_users']) ? array_map('intval', (array) $opt['only_users']) : null;
        foreach ($this->dueReminders($now) as $j) {
            if ($only !== null && !in_array($j['user_id'], $only, true)) continue;
            $st['jobs']++;
            $t = $j['task']; $uid = $j['user_id']; $kind = $j['kind'];
            $title = $kind === 'on_day' ? 'Task due today' : 'Task due tomorrow';
            $body = $t['title'] . ' · due ' . date('g:i a', strtotime((string) $t['due_at']));
            $path = 'secretary/tasks/index.php';
            foreach (['app', 'email'] as $ch) {
                try {
                    if ($ch === 'email' && !filter_var($j['email'], FILTER_VALIDATE_EMAIL)) continue;
                    if (!$this->claim((int) $t['id'], $uid, $kind, $ch, $dry)) { $st['dupe']++; continue; }
                    if ($dry) { $st['lines'][] = 'WOULD SEND ' . $ch . ' → ' . $j['name'] . ': ' . $title . ' · ' . $body; $st['would']++; continue; }
                    if ($noSend) { $this->finish((int) $t['id'], $uid, $kind, $ch, 'sent', 'no-send (test)'); $st[$ch === 'app' ? 'sent_app' : 'sent_email']++; continue; }
                    if ($ch === 'app') {
                        PortalNotifications::notify($this->db, $uid, 'secretary_task', $title, $body, $path);   // the existing notification code: in-app list and phone push
                        $this->finish((int) $t['id'], $uid, $kind, 'app', 'sent', '');
                        $st['sent_app']++;
                        continue;
                    }
                    $r = $this->sendMail($j, $title, $body, $path, $opt['mail_fn'] ?? null);
                    $good = !empty($r['ok']) && empty($r['skipped']);
                    $this->finish((int) $t['id'], $uid, $kind, 'email', $good ? 'sent' : (!empty($r['skipped']) ? 'skipped' : 'failed'), $good ? '' : (string) ($r['message'] ?? 'not sent'));
                    if ($good) $st['sent_email']++; elseif (empty($r['skipped'])) $st['failed']++;
                } catch (Throwable $ex) {   // one person's failure is logged and never stops the others
                    $st['failed']++;
                    error_log('[secretary_task_reminders] ' . $ch . ' for user ' . $uid . ': ' . $ex->getMessage());
                    try { $this->finish((int) $t['id'], $uid, $kind, $ch, 'failed', $ex->getMessage()); } catch (Throwable $e2) { }
                }
            }
        }
        return $st;
    }

    private function sendMail(array $j, string $title, string $body, string $path, ?callable $fn): array
    {
        $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $p = 'font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#111827;';
        $html = '<p style="' . $p . '">Hi ' . $h($j['first']) . ',</p><p style="' . $p . '"><strong>' . $h($title) . '</strong>: ' . $h($body) . '</p>'
              . '<p style="' . $p . '"><a href="' . $h(PortalNotifications::absolute($path)) . '" style="background:#048C44;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;display:inline-block;">Open My Tasks</a></p>'
              . '<p style="' . $p . '">Dazu Training Institute</p>';
        $subject = $title . ' — Dazu Training Institute';
        if ($fn) return $fn($j['email'], $j['name'], $subject, $html);
        require_once __DIR__ . '/Mailer.php';
        return (new Mailer(require __DIR__ . '/../config/mail.php'))->send($j['email'], $j['name'], $subject, $html);
    }
}
