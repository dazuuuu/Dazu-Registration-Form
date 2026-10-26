<?php
// app/v1/services/CertificateNotifier.php
// Notices (in the app) and emails for applying for a certificate, in simple English. Same Dazu email look as the Assignments emails.
// Never contains a mark. The student: the button opened, the request was returned. The tutor: a request arrived, a request waited 3 days.
// (When the certificate is sent, the existing certificate code tells the student and emails the PDF.)

require_once __DIR__ . '/AssignmentNotifier.php';
require_once __DIR__ . '/PortalNotifications.php';

use Models\AssignmentModel;

class CertificateNotifier extends AssignmentNotifier
{
    public function __construct(PDO $db)
    {
        parent::__construct($db, new AssignmentModel($db));
    }

    private function tellStudent(int $uid, string $title, string $body, string $path, string $heading, string $button): void
    {
        PortalNotifications::notify($this->db, $uid, 'certificate', $title, $body, $path);
        $this->mail(0, $this->m->student($uid), $heading, $title,
            '<p>' . self::e($title) . '</p>' . ($body !== '' ? '<p>' . self::e($body) . '</p>' : '') . self::button($button, $path));
    }

    private function tellTutor(array $app, string $title, string $heading): void
    {
        $uid = (int) $app['tutor_user_id'];
        if ($uid <= 0) return;
        $path = 'tutor/certificates/request.php?id=' . (int) $app['id'];
        PortalNotifications::notify($this->db, $uid, 'certificate', $title, (string) $app['course_title'], $path, PortalNotifications::photoOf($this->db, (int) $app['student_user_id']));
        $st = $this->db->prepare("SELECT u.email, u.id AS user_id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username) AS full_name
                                  FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ?");
        $st->execute([$uid]);
        if ($to = $st->fetch(PDO::FETCH_ASSOC)) {
            $this->mail(0, $to, $heading, $title, '<p>' . self::e($title) . '</p><p>' . self::e((string) $app['course_title']) . '</p>' . self::button('Open the request', $path));
        }
    }

    /** The Apply button opened: all four are true. */
    public function buttonOpened(int $uid, string $course): void
    {
        $this->tellStudent($uid, 'You can now apply for your certificate.', $course, 'students/certificates/index.php', 'You can apply for your certificate', 'Apply now');
    }

    /** The tutor returned the request with a note. */
    public function returned(array $app): void
    {
        $this->tellStudent((int) $app['student_user_id'], 'Your certificate request was returned. Read your tutor\'s note and apply again.', (string) $app['course_title'],
            'students/certificates/index.php', 'Your certificate request was returned', 'Open my certificates');
    }

    /** A student applied. */
    public function requestArrived(array $app): void
    {
        $this->tellTutor($app, $app['student_name'] . ' applied for a certificate.', 'A certificate request is waiting');
    }

    /** A request has waited 3 days. */
    public function waiting(array $app): void
    {
        $this->tellTutor($app, 'The certificate request from ' . $app['student_name'] . ' has waited ' . CERTAPP_REMIND_DAYS . ' days.', 'A certificate request is still waiting');
    }
}
