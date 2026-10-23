<?php
// app/v1/services/AssignmentNotifier.php
// Emails (PHPMailer, Dazu look) and dashboard notices for the Assignments feature.
// Emails are queued and sent in one SMTP session after the page has been delivered, so publishing to a class never makes
// the tutor wait. Each send is written to tutor_assignment_events as the email log.
// Emails never contain the assignment itself: only the title, the due date and a link.

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/PortalNotifications.php';
require_once __DIR__ . '/../models/AssignmentModel.php';

use Models\AssignmentModel;

class AssignmentNotifier
{
    private static array $queue = [];
    private static bool $registered = false;

    private PDO $db;
    private AssignmentModel $m;

    public function __construct(PDO $db, ?AssignmentModel $m = null)
    {
        $this->db = $db;
        $this->m = $m ?? new AssignmentModel($db);
    }

    /* ---------- Events ---------- */

    /** Assignment published (or students added later): email + notice to each. */
    public function published(array $a, array $uids): void
    {
        $tutor = $this->m->tutorById((int) $a['tutor_id']);
        $icon = $tutor ? PortalNotifications::photoOf($this->db, (int) $tutor['user_id']) : null;
        foreach ($uids as $uid) {
            $item = $this->m->itemForStudent((int) $uid, (int) $a['id']);
            if (!$item) continue;
            $label = 'Assignment ' . $item['index'] . ($item['state'] === 'locked' ? '' : ': ' . $a['title']);
            $path = 'students/assignments/view.php?id=' . (int) $a['id'];
            PortalNotifications::notify($this->db, (int) $uid, 'assignment', 'New assignment: ' . $label,
                $item['state'] === 'locked' ? 'It unlocks when you submit Assignment ' . $item['prev_index'] . '.' : AssignmentModel::dueText($item['due']), $path, $icon);
            $st = $this->m->student((int) $uid);
            $lockedNote = $item['state'] === 'locked'
                ? 'This assignment unlocks when you submit Assignment ' . $item['prev_index'] . '. Log in to your student dashboard to see it.'
                : 'Log in to your student dashboard to read it and send your work.';
            $this->mail((int) $a['id'], $st, 'New assignment', 'New assignment: ' . $label,
                '<p>Your tutor ' . self::e($tutor['full_name'] ?? 'your tutor') . ' has added a new assignment' . ($a['course_title'] ? ' for ' . self::e($a['course_title']) : '') . '.</p>'
                . self::card($label, ($item['due'] ? 'Due: ' . AssignmentModel::local($item['due'], 'l j F Y, H:i') : 'No due date'), 'Submit as: ' . mb_strtolower($a['types_text']))
                . self::button('Open assignment', $path) . self::small($lockedNote));
        }
        $this->m->markNotified((int) $a['id'], array_map('intval', $uids));
    }

    /** Later assignments that opened because of a submit or a skip: notice only. */
    public function unlocked(int $uid, array $assignmentIds): void
    {
        foreach ($assignmentIds as $aid) {
            $item = $this->m->itemForStudent($uid, (int) $aid);
            if (!$item || !in_array($item['state'], ['open', 'in_progress'], true)) continue;
            PortalNotifications::notify($this->db, $uid, 'assignment', 'Assignment ' . $item['index'] . ' is now open: ' . $item['title'],
                AssignmentModel::dueText($item['due']), 'students/assignments/view.php?id=' . (int) $aid);
        }
    }

    /** Work submitted: notice to the tutor (the Submissions badge counts it too). */
    public function submitted(array $item, int $uid, bool $resubmitted, int $subId): void
    {
        $tutor = $this->m->tutorById((int) $item['tutor_id']);
        if (!$tutor) return;
        $st = $this->m->student($uid);
        PortalNotifications::notify($this->db, (int) $tutor['user_id'], 'submission',
            $st['full_name'] . ($resubmitted ? ' resubmitted: ' : ' submitted: ') . $item['title'], (string) ($item['course_title'] ?? ''),
            'tutor/submissions/grade.php?id=' . $subId, PortalNotifications::photoOf($this->db, $uid));
    }

    /** Result published / updated, or work returned for changes. */
    public function result(array $a, array $sub, string $kind): void
    {
        $uid = (int) $sub['student_user_id'];
        $st = $this->m->student($uid);
        $tutor = $this->m->tutorById((int) $a['tutor_id']);
        $icon = $tutor ? PortalNotifications::photoOf($this->db, (int) $tutor['user_id']) : null;
        if ($kind === 'returned') {
            $path = 'students/assignments/view.php?id=' . (int) $a['id'];
            PortalNotifications::notify($this->db, $uid, 'grade', 'Returned for changes: ' . $a['title'], 'Read your tutor\'s comments and resubmit.', $path, $icon);
            $this->mail((int) $a['id'], $st, 'Returned for changes', 'Your tutor returned: ' . $a['title'],
                '<p>Your tutor has looked at <strong>' . self::e($a['title']) . '</strong> and asked for some changes.</p>'
                . self::card('What to change', nl2br(self::e((string) $sub['feedback_why'])), ($sub['feedback_improve'] ? nl2br(self::e((string) $sub['feedback_improve'])) : '')
                    . (!empty($sub['resubmit_by']) ? '<br>Resubmit by ' . self::e(AssignmentModel::local($sub['resubmit_by'], 'D j M, g:i A')) . ' EAT. It will not be marked Late.' : ''))
                . self::button('Open and resubmit', $path));
            return;
        }
        $path = 'students/assignments/result.php?id=' . (int) $a['id'];
        $res = AssignmentModel::resultText($sub + ['grading_mode' => $a['grading_mode'], 'max_score' => $a['max_score']]);
        $updated = $kind === 'updated';
        PortalNotifications::notify($this->db, $uid, 'grade', ($updated ? 'Result updated: ' : 'Result ready: ') . $a['title'], $res, $path, $icon);
        $this->mail((int) $a['id'], $st, $updated ? 'Result updated' : 'Your result', ($updated ? 'Your result was updated: ' : 'Your result: ') . $a['title'],
            '<p>Your tutor has ' . ($updated ? 'updated the result' : 'marked your work') . ' for <strong>' . self::e($a['title']) . '</strong>.</p>'
            . self::card($res, 'Open it to see why you got this result and what to improve.', '')
            . self::button('View result', $path));
    }

    /**
     * Held grades reached their release time: tell each student once. The assignment is claimed first
     * (markReleaseNotified), so a second cron run or a double click never sends twice. Returns how many were told.
     */
    public function gradesReleased(array $a): int
    {
        if (!$this->m->markReleaseNotified($a)) return 0;
        $subs = $this->m->heldGradedSubmissions($a);
        foreach ($subs as $sub) $this->result($a, $sub, 'published');
        $this->m->log((int) $a['id'], null, null, 'grades_released', ['students' => count($subs)]);
        return count($subs);
    }

    public function extensionRequested(int $requestId): void
    {
        $this->toTutor($requestId, 'Extension request', function (array $q) {
            return [
                $q['full_name'] . ' asks for more time: ' . $q['title'],
                '<p><strong>' . self::e($q['full_name']) . '</strong> has asked for more time.</p>'
                . self::card($q['title'], 'Due: ' . AssignmentModel::local($q['current_due_at'] ?: $q['due_at']) . '<br>Asks for: ' . AssignmentModel::local($q['requested_due_at']),
                    'Reason: ' . self::e(AssignmentModel::REASONS[$q['reason_code']] ?? 'Other') . '. ' . self::e((string) $q['reason_text'])),
            ];
        });
    }

    public function unlockRequested(int $requestId): void
    {
        $this->toTutor($requestId, 'Unlock request', function (array $q) {
            return [
                $q['full_name'] . ' asks you to unlock: ' . $q['title'],
                '<p><strong>' . self::e($q['full_name']) . '</strong> missed the due date and asks you to unlock this assignment.</p>'
                . self::card($q['title'], 'Was due: ' . AssignmentModel::local($q['current_due_at'] ?: $q['due_at']), $q['reason_text'] ? 'Message: ' . self::e((string) $q['reason_text']) : ''),
            ];
        });
    }

    /** Reminder to the tutor about a request still pending after 24 hours. */
    public function requestReminder(int $requestId): void
    {
        $this->toTutor($requestId, 'Reminder', function (array $q) {
            return [
                'Reminder: ' . $q['full_name'] . ' is waiting for your answer',
                '<p>This ' . ($q['type'] === 'unlock' ? 'unlock' : 'extension') . ' request has been waiting for more than a day.</p>'
                . self::card($q['title'], self::e($q['full_name']), ''),
            ];
        });
    }

    /** Extension or unlock decided, or tutor unlocked / extended directly ($q = request row, or a synthetic one). */
    public function decided(array $a, int $uid, string $type, bool $approved, ?string $newDue, ?string $note): void
    {
        $st = $this->m->student($uid);
        $path = 'students/assignments/view.php?id=' . (int) $a['id'];
        $what = $type === 'unlock' ? 'unlock' : 'extension';
        $title = $approved
            ? ($type === 'unlock' ? 'Unlocked: ' : 'Extension approved: ') . $a['title']
            : ucfirst($what) . ' declined: ' . $a['title'];
        PortalNotifications::notify($this->db, $uid, 'assignment', $title, $approved ? 'New due date: ' . AssignmentModel::local($newDue) : (string) $note, $path);
        $this->mail((int) $a['id'], $st, $approved ? ($type === 'unlock' ? 'Assignment unlocked' : 'Extension approved') : ucfirst($what) . ' declined', $title,
            ($approved
                ? '<p>Your tutor has ' . ($type === 'unlock' ? 'unlocked' : 'given you more time for') . ' <strong>' . self::e($a['title']) . '</strong>.</p>'
                  . self::card('New due date', AssignmentModel::local($newDue, 'l j F Y, H:i'), $note ? self::e($note) : '')
                : '<p>Your tutor could not ' . ($type === 'unlock' ? 'unlock' : 'give you more time for') . ' <strong>' . self::e($a['title']) . '</strong>.</p>'
                  . ($note ? self::card('Your tutor says', nl2br(self::e($note)), '') : '')
                  . self::small('Speak to your tutor if you need help with this assignment.'))
            . self::button('Open assignment', $path));
    }

    /** "Due in 24 hours" reminder to one student. */
    public function dueReminder(array $recipientRow): bool
    {
        $uid = (int) $recipientRow['student_user_id'];
        $item = $this->m->itemForStudent($uid, (int) $recipientRow['assignment_id']);
        if (!$item || !in_array($item['state'], ['open', 'in_progress', 'extension'], true)) return false;
        $path = 'students/assignments/view.php?id=' . (int) $item['id'];
        PortalNotifications::notify($this->db, $uid, 'assignment', 'Due soon: ' . $item['title'], AssignmentModel::dueText($item['due']), $path);
        $this->mail((int) $item['id'], $this->m->student($uid), 'Due soon', 'Reminder: ' . $item['title'] . ' is due soon',
            '<p>This assignment is due in less than a day and you have not submitted it yet.</p>'
            . self::card('Assignment ' . $item['index'] . ': ' . $item['title'], 'Due: ' . AssignmentModel::local($item['due'], 'l j F Y, H:i'), 'If you need more time, ask for an extension before the due time.')
            . self::button('Open assignment', $path));
        return true;
    }

    /** Receipt after every send: dashboard notice and a short email. */
    public function receipt(array $item, int $uid, int $version, bool $late): void
    {
        $path = 'students/assignments/view.php?id=' . (int) $item['id'];
        $held = AssignmentModel::gradesHeld($item);
        PortalNotifications::notify($this->db, $uid, 'assignment', 'We got your work: ' . $item['title'], 'Version ' . $version . ($late ? ' · Late' : ' · On time'), $path);
        $this->mail((int) $item['id'], $this->m->student($uid), 'Receipt', 'We got your work: ' . $item['title'],
            '<p><strong>Thank you. We received your work.</strong> This is your receipt. Your tutor will mark it. Marks show when your tutor releases them.</p>'
            . self::card($item['title'], 'Sent: ' . AssignmentModel::local(AssignmentModel::now(), 'D j M Y, g:i A') . ' EAT', 'Status: ' . ($late ? 'Late' : 'On time')
                . ($held ? ' · Marks: released ' . AssignmentModel::local($item['release_grades_at'], 'D j M, g:i A') : '') . ($version > 1 ? ' · Version ' . $version : ''))
            . self::button('View my submission', $path));
        try {
            $this->db->prepare("UPDATE tutor_assignment_submissions SET receipt_sent_at = ? WHERE assignment_id = ? AND student_user_id = ?")
                ->execute([AssignmentModel::now(), (int) $item['id'], $uid]);
        } catch (\Throwable $e) {
        }
    }

    /** 24-hour or 1-hour reminder ($kind '24h' | '1h') to a student who has not submitted. */
    public function reminder(array $item, int $uid, string $kind): void
    {
        $path = 'students/assignments/view.php?id=' . (int) $item['id'];
        $when = $kind === '1h' ? 'in 1 hour' : 'tomorrow';
        PortalNotifications::notify($this->db, $uid, 'assignment', 'Reminder: ' . $item['title'] . ' is due ' . $when, AssignmentModel::dueText($item['due']), $path);
        $this->mail((int) $item['id'], $this->m->student($uid), 'Reminder', 'Reminder: ' . $item['title'] . ' is due ' . ($kind === '1h' ? 'in 1 hour' : 'in 24 hours'),
            '<p><strong>Your assignment is due ' . $when . '.</strong> A quick reminder from your tutor. You have not sent this assignment yet.</p>'
            . self::card($item['title'], 'Due: ' . AssignmentModel::local($item['due'], 'D j M, g:i A') . ' EAT',
                (int) $item['close_after_due'] ? 'It closes at the due time.' : 'Late work: still accepted, marked Late.')
            . self::button('Open assignment', $path));
    }

    /** Nudge from the tutor to one student who has not submitted. */
    public function nudge(array $a, int $uid): void
    {
        $tutor = $this->m->tutorById((int) $a['tutor_id']);
        $path = 'students/assignments/view.php?id=' . (int) $a['id'];
        PortalNotifications::notify($this->db, $uid, 'assignment', 'Your tutor is waiting for: ' . $a['title'], AssignmentModel::dueText($a['due_at']), $path,
            $tutor ? PortalNotifications::photoOf($this->db, (int) $tutor['user_id']) : null);
        $this->mail((int) $a['id'], $this->m->student($uid), 'A reminder from your tutor', 'Please send your work: ' . $a['title'],
            '<p>' . self::e($tutor['full_name'] ?? 'Your tutor') . ' is waiting for your work on <strong>' . self::e($a['title']) . '</strong>.</p>'
            . self::button('Open assignment', $path));
    }

    /** The tutor cannot open the student's link: ask them to fix the sharing, with the simple fix for that site. */
    public function linkFix(array $a, int $uid, string $host, string $fix): void
    {
        $path = 'students/assignments/view.php?id=' . (int) $a['id'];
        PortalNotifications::notify($this->db, $uid, 'assignment', 'Your tutor cannot open your link: ' . $a['title'], $fix, $path);
        $this->mail((int) $a['id'], $this->m->student($uid), 'Link needs access', 'Your tutor cannot open your link: ' . $a['title'],
            '<p>Your tutor tried to open the link you sent for <strong>' . self::e($a['title']) . '</strong>, but it is private.</p>'
            . self::card('How to fix it (' . $host . ')', self::e($fix), 'Your work keeps its original sent time. Fixing the link does not make it Late.')
            . self::button('Open assignment', $path));
    }

    /* ---------- Plumbing ---------- */

    private function toTutor(int $requestId, string $heading, callable $build): void
    {
        $rows = array_filter(array_merge($this->m->requests(null, 'pending')), fn($q) => (int) $q['id'] === $requestId);
        $q = reset($rows);
        if (!$q) return;
        $tutor = $this->m->tutorById((int) $q['tutor_id']);
        if (!$tutor) return;
        [$subject, $body] = $build($q);
        $path = 'tutor/submissions/index.php?tab=requests#req-' . $requestId;
        PortalNotifications::notify($this->db, (int) $tutor['user_id'], 'assignment_request', $subject, (string) ($q['course_title'] ?? ''), $path,
            PortalNotifications::photoOf($this->db, (int) $q['student_user_id']));
        $this->mail((int) $q['assignment_id'], ['email' => $tutor['email'], 'full_name' => $tutor['full_name'], 'user_id' => $tutor['user_id']], $heading, $subject,
            $body . self::button('Decide now', $path));
    }

    private function mail(int $assignmentId, array $to, string $heading, string $subject, string $body): void
    {
        if (empty($to['email']) || !filter_var($to['email'], FILTER_VALIDATE_EMAIL)) return;
        $first = trim(explode(' ', trim((string) ($to['full_name'] ?? '')))[0] ?? '');
        $html = self::layout($heading, '<p style="margin:0 0 10px;font-weight:600">Hello ' . self::e($first !== '' ? mb_convert_case($first, MB_CASE_TITLE) : 'there') . ',</p>' . $body);
        self::$queue[] = ['to_email' => $to['email'], 'to_name' => (string) ($to['full_name'] ?? ''), 'subject' => $subject, 'html' => $html,
                          '_log' => [$this->db, $assignmentId, (int) ($to['user_id'] ?? 0), $subject]];
        if (!self::$registered) {
            self::$registered = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    /** Send everything queued, after the response has gone to the browser. Safe to call directly (cron). */
    public static function flush(): void
    {
        if (!self::$queue) return;
        if (PHP_SAPI !== 'cli') {
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
        }
        $queue = self::$queue;
        self::$queue = [];
        try {
            $cfg = require __DIR__ . '/../config/mail.php';
            $res = (new Mailer($cfg))->sendBatch(array_map(fn($m) => array_diff_key($m, ['_log' => 1]), $queue));
        } catch (Throwable $e) {
            error_log('[AssignmentNotifier] ' . $e->getMessage());
            $res = ['sent' => 0, 'failed' => count($queue)];
        }
        foreach ($queue as $m) {
            [$db, $aid, $uid, $subject] = $m['_log'];
            (new AssignmentModel($db))->log($aid ?: null, null, $uid ?: null, 'email', ['to' => $m['to_email'], 'subject' => $subject, 'batch_failed' => $res['failed']]);
        }
    }

    private static function layout(string $heading, string $body): string
    {
        $g = '#44A024';
        return '<div style="background:#f4f5f1;padding:24px 12px;font-family:Poppins,Arial,Helvetica,sans-serif;color:#1d1b1c">'
            . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e9ebe6;border-radius:16px;overflow:hidden">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $g . '"><tr>'
            . '<td style="padding:18px 22px"><span style="display:inline-block;background:#ffffff;border-radius:10px;padding:6px 10px">'
            . '<img src="cid:ismanlogo" alt="DAZU Training Institute" height="34" style="display:block;height:34px"></span></td>'
            . '<td style="padding:18px 22px;text-align:right;color:#ffffff;font-weight:700;font-size:15px">' . self::e($heading) . '</td></tr></table>'
            . '<div style="padding:24px 26px;font-size:14px;line-height:1.6">' . $body . '</div>'
            . '<div style="padding:14px 26px;border-top:1px solid #e9ebe6;color:#6b7067;font-size:12px;text-align:center">'
            . 'Dazu Training Institute &nbsp;|&nbsp; 0722 922 454 &nbsp;|&nbsp; dazu.ac.ke</div></div></div>';
    }

    private static function card(string $title, string $line1, string $line2): string
    {
        return '<div style="background:#f4f5f1;border-left:5px solid #44A024;border-radius:10px;padding:14px 18px;margin:16px 0">'
            . '<div style="font-weight:700;font-size:15px;margin-bottom:4px">' . self::e($title) . '</div>'
            . '<div style="font-weight:600;font-size:13px">' . $line1 . '</div>'
            . ($line2 !== '' ? '<div style="color:#6b7067;font-size:13px;margin-top:2px">' . $line2 . '</div>' : '') . '</div>';
    }

    private static function button(string $label, string $path): string
    {
        return '<p style="margin:20px 0 8px"><a href="' . self::e(PortalNotifications::absolute($path)) . '" style="display:inline-block;background:#44A024;color:#ffffff;'
            . 'padding:11px 26px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px;letter-spacing:.03em">' . self::e(strtoupper($label)) . '</a></p>';
    }

    private static function small(string $text): string
    {
        return '<p style="border-top:1px solid #e9ebe6;margin-top:18px;padding-top:10px;color:#6b7067;font-size:12px;font-style:italic">' . self::e($text) . '</p>';
    }

    private static function e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
