<?php
// app/v1/services/CapstoneNotifier.php
// Capstone notices (in the app) and emails (PHPMailer, the same Dazu look as the Assignments emails).
// Emails never contain marks, comments, instructions or file names: only a short sentence and a link.
// The admin gets no capstone emails.

require_once __DIR__ . '/AssignmentNotifier.php';

use Models\AssignmentModel;

class CapstoneNotifier extends AssignmentNotifier
{
    public function __construct(PDO $db)
    {
        parent::__construct($db, new AssignmentModel($db));
    }

    private function students(int $capId): array
    {
        $st = $this->db->prepare('SELECT student_user_id FROM capstone_students WHERE capstone_id = ? AND removed_at IS NULL');
        $st->execute([$capId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    private function tell(int $uid, string $title, string $body, string $path, string $heading, ?string $icon = null): void
    {
        PortalNotifications::notify($this->db, $uid, 'capstone', $title, $body, $path, $icon);
        $this->mail(0, $this->m->student($uid), $heading, $title,
            '<p>' . self::e($title) . '</p>' . ($body !== '' ? '<p>' . self::e($body) . '</p>' : '') . self::button('Open capstone', $path));
    }

    private function tutorIcon(array $cap): ?string
    {
        $t = $this->m->tutorById((int) $cap['tutor_id']);
        return $t ? PortalNotifications::photoOf($this->db, (int) $t['user_id']) : null;
    }

    /** Capstone opened: tell every student. Returns how many were told. */
    public function opened(array $cap): int
    {
        $n = 0;
        $due = $cap['due_at'] ? AssignmentModel::local($cap['due_at'], 'j M Y, H:i') . ' EAT' : '';
        foreach ($this->students((int) $cap['id']) as $uid) {
            $this->tell($uid, 'Your capstone is open.' . ($due !== '' ? ' Final due ' . $due . '.' : ''), '', 'students/capstone/view.php?id=' . (int) $cap['id'], 'Your capstone is open', $this->tutorIcon($cap));
            $n++;
        }
        return $n;
    }

    /** Three days before it opens. Says only the date. */
    public function opensSoon(array $cap): void
    {
        $when = AssignmentModel::local($cap['opens_at'], 'D j M Y, H:i') . ' EAT';
        foreach ($this->students((int) $cap['id']) as $uid) {
            $this->tell($uid, 'Your capstone opens on ' . $when . '.', '', 'students/capstone/index.php', 'Your capstone opens soon');
        }
    }

    /** Milestone due in $days days: only students who have not sent it. Returns how many were told. */
    public function milestoneReminder(int $milestoneId, int $days): int
    {
        $st = $this->db->prepare('SELECT m.* FROM capstone_milestones m WHERE m.id = ?');
        $st->execute([$milestoneId]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m || $m['kind'] === 'presentation') return 0;
        $told = 0;
        foreach ($this->students((int) $m['capstone_id']) as $uid) {
            $own = $this->db->prepare('SELECT id, shared_attempt_id FROM capstone_attempts WHERE capstone_id = ? AND student_user_id = ? ORDER BY attempt_no DESC LIMIT 1');
            $own->execute([(int) $m['capstone_id'], $uid]);
            $a = $own->fetch(PDO::FETCH_ASSOC);
            $ownerId = $a ? (int) ($a['shared_attempt_id'] ?: $a['id']) : 0;
            $has = $this->db->prepare('SELECT 1 FROM capstone_milestone_submissions WHERE milestone_id = ? AND attempt_id = ?');
            $has->execute([$milestoneId, $ownerId]);
            if ($has->fetchColumn()) continue;
            if ($m['kind'] === 'final' && $a) {
                $sub = $this->db->prepare("SELECT status FROM capstone_attempts WHERE id = ? AND status IN ('submitted','marked')");
                $sub->execute([$ownerId]);
                if ($sub->fetchColumn()) continue;
            }
            $this->db->prepare('INSERT IGNORE INTO capstone_reminders_sent (kind, ref_id, student_user_id, sent_at) VALUES (?, ?, ?, ?)')
                     ->execute(['milestone' . $days . 'd', $milestoneId, $uid, gmdate('Y-m-d H:i:s')]);
            $when = AssignmentModel::local($m['due_at'], 'D j M, H:i') . ' EAT';
            $this->tell($uid, '"' . $m['title'] . '" is due in ' . ($days === 1 ? '1 day' : $days . ' days') . ' (' . $when . ').', '', 'students/capstone/view.php?id=' . (int) $m['capstone_id'], 'Capstone reminder');
            $told++;
        }
        return $told;
    }

    /** A student sent the capstone: tell the tutor. */
    public function capSubmitted(array $cap, int $uid): void
    {
        $tutor = $this->m->tutorById((int) $cap['tutor_id']);
        if (!$tutor) return;
        $st = $this->m->student($uid);
        $a = $this->db->prepare('SELECT id FROM capstone_attempts WHERE capstone_id = ? AND student_user_id = ? ORDER BY attempt_no DESC LIMIT 1');
        $a->execute([(int) $cap['id'], $uid]);
        $path = 'tutor/capstone/mark.php?id=' . (int) $a->fetchColumn();
        $title = $st['full_name'] . ' sent a capstone.';
        PortalNotifications::notify($this->db, (int) $tutor['user_id'], 'capstone', $title, (string) ($cap['course_title'] ?? ''), $path, PortalNotifications::photoOf($this->db, $uid));
        $this->mail(0, ['email' => $tutor['email'], 'full_name' => $tutor['full_name'], 'user_id' => $tutor['user_id']], 'A capstone was sent', $title,
            '<p>' . self::e($title) . '</p>' . self::button('Open and mark', $path));
    }

    /** Returned (1 of 2, 2 of 2). The instructions stay in the portal; the email only points to them. */
    public function returned(array $cap, array $owner, int $no, ?string $sendBy): void
    {
        $st = $this->db->prepare('SELECT student_user_id FROM capstone_attempts WHERE id = ? OR shared_attempt_id = ?');
        $st->execute([(int) $owner['id'], (int) $owner['id']]);
        $by = $sendBy ? ' by ' . AssignmentModel::local($sendBy, 'D j M, H:i') . ' EAT' : '';
        foreach (array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)) as $uid) {
            $this->tell($uid, 'Your tutor returned your capstone (' . $no . ' of 2). You can only do this twice. Read the instructions and send it again' . $by . '.', '',
                'students/capstone/view.php?id=' . (int) $cap['id'], 'Capstone returned', $this->tutorIcon($cap));
            if ($no >= 2) {
                $this->tell($uid, 'This is your last send. After this, the mark is final.', '', 'students/capstone/view.php?id=' . (int) $cap['id'], 'Your last send');
            }
        }
    }

    /** The mark was saved and is not held (or its hold ended): "ready", then passed / not passed. No numbers in the message. */
    public function markReleasedFor(array $cap, array $attempt, float $total, bool $updated): void
    {
        $uid = (int) $attempt['student_user_id'];
        $path = 'students/capstone/view.php?id=' . (int) $cap['id'];
        $this->tell($uid, $updated ? 'Your capstone mark was updated.' : 'Your capstone mark is ready.', '', $path, 'Your capstone mark', $this->tutorIcon($cap));
        if ($total >= CAP_PASS) {
            $this->tell($uid, 'You passed your capstone.', '', $path, 'You passed your capstone');
            return;
        }
        $this->tell($uid, 'Your capstone was not passed. Your tutor will tell you about a retake.', '', $path, 'Capstone not passed');
        $tutor = $this->m->tutorById((int) $cap['tutor_id']);
        if ($tutor) {
            $st = $this->m->student($uid);
            $tp = 'tutor/capstone/track.php?id=' . (int) $cap['id'];
            PortalNotifications::notify($this->db, (int) $tutor['user_id'], 'capstone', $st['full_name'] . ' did not pass the capstone.', 'You can start a retake once the retake fee is set.', $tp);
            $this->mail(0, ['email' => $tutor['email'], 'full_name' => $tutor['full_name'], 'user_id' => $tutor['user_id']], 'Capstone not passed', $st['full_name'] . ' did not pass the capstone.',
                '<p>' . self::e($st['full_name']) . ' did not pass the capstone.</p>' . self::button('Open tracker', $tp));
        }
    }

    /** Cron: a held mark reached its release time. */
    public function markReleased(int $markId): void
    {
        $st = $this->db->prepare('SELECT a.*, m.total FROM capstone_marks m JOIN capstone_attempts a ON a.id = m.attempt_id WHERE m.id = ?');
        $st->execute([$markId]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) return;
        $cap = cap_get($this->db, (int) $a['capstone_id']);
        if ($cap) $this->markReleasedFor($cap, $a, (float) $a['total'], false);
    }

    /** A nudge from the tutor to one student (the same wording style as the Assignments nudge). */
    public function capNudge(array $cap, int $uid): void
    {
        $tutor = $this->m->tutorById((int) $cap['tutor_id']);
        $this->tell($uid, ($tutor['full_name'] ?? 'Your tutor') . ' is waiting for your capstone work.', '', 'students/capstone/view.php?id=' . (int) $cap['id'], 'A reminder from your tutor', $this->tutorIcon($cap));
    }

    public function retakeStarted(array $cap, array $attempt, float $fee): void
    {
        $this->tell((int) $attempt['student_user_id'], 'A retake is ready. Please pay the retake fee first (KES ' . number_format($fee) . ').', 'The retake opens after the office confirms your payment.',
            'students/capstone/view.php?id=' . (int) $cap['id'], 'Capstone retake', $this->tutorIcon($cap));
    }

    public function retakeOpen(array $cap, array $attempt): void
    {
        $this->tell((int) $attempt['student_user_id'], 'Your capstone retake is open now.', '', 'students/capstone/view.php?id=' . (int) $cap['id'], 'Your retake is open', $this->tutorIcon($cap));
    }

    /** The student is Ready: one notice and one email. No marks, no result word. */
    public function completionReady(int $uid, string $course, int $courseId): void
    {
        // Only courses of 12 months and longer have a transcript (TranscriptService); the capstone page shows it once it is released.
        require_once __DIR__ . '/TranscriptService.php';
        $tr = tr_installed($this->db) && tr_course_eligible($this->db, $courseId);
        $this->tell($uid, $tr ? 'Your course is complete. Your transcript and letter of recommendation are ready.' : 'Your course is complete. Your letter of recommendation is ready.', $course,
            'students/capstone/index.php', 'Your course is complete');
    }

    public function courseFinished(int $uid, string $course): void
    {
        $this->tell($uid, 'Your tutor marked your course as finished.', $course, 'students/capstone/index.php', 'Course finished');
    }
}
