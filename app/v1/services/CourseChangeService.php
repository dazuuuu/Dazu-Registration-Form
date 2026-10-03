<?php
// app/v1/services/CourseChangeService.php
// Shared by the admin and superadmin student pages: give a student another course, or move them from one course to another.
// One registration = one course, so a student with several courses has several approved registrations on the same user.
require_once __DIR__ . '/../controllers/ContinuingStudentController.php';
require_once __DIR__ . '/../models/RegistrationModel.php';
require_once __DIR__ . '/../models/CourseModel.php';
require_once __DIR__ . '/../models/ProgressModel.php';
require_once __DIR__ . '/../models/StudentModel.php';
require_once __DIR__ . '/../helpers/course_duration.php';
require_once __DIR__ . '/../helpers/study_location.php';
require_once __DIR__ . '/../helpers/registration_number.php';

use Models\CourseModel;
use Models\ProgressModel;
use Models\RegistrationModel;
use Models\StudentModel;

class CourseChangeService
{
    private PDO $pdo;
    private CourseModel $courses;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->courses = new CourseModel($pdo);
    }

    /** Every approved course enrolment this student (user) holds, newest first. Falls back to the one registration if it has no user. */
    public function enrolments(array $reg): array
    {
        $select = "SELECT r.*, c.title AS course_title,
                    (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.registration_id = r.id AND p.status = 'approved') AS paid
             FROM registrations r LEFT JOIN courses c ON c.id = r.course_id";
        if (empty($reg['user_id'])) {
            // No portal account yet: just this registration, with the same paid/course_title columns.
            $st = $this->pdo->prepare($select . " WHERE r.id = :id");
            $st->execute([':id' => (int) $reg['id']]);
            return $st->fetchAll() ?: [$reg + ['paid' => 0, 'course_title' => '']];
        }
        $st = $this->pdo->prepare($select . " WHERE r.user_id = :uid AND r.status = 'approved' ORDER BY r.created_at DESC, r.id DESC");
        $st->execute([':uid' => (int) $reg['user_id']]);
        return $st->fetchAll();
    }

    /** Active courses the student is not already enrolled in. */
    public function availableCourses(array $enrolments): array
    {
        $taken = array_map(fn($e) => (int) $e['course_id'], $enrolments);
        return array_values(array_filter($this->courses->getAll(true), fn($c) => !in_array((int) $c['id'], $taken, true)));
    }

    /** Enrol the student in an additional course (a new registration under the same portal account, fee = that course's fee). */
    public function addCourse(array $reg, int $courseId, int $adminId, ?string $mode = null, ?string $location = null): array
    {
        $course = $this->validTarget($reg, $courseId);
        if (isset($course['error'])) {
            return ['ok' => false, 'error' => $course['error']];
        }
        $result = (new \Controllers\ContinuingStudentController())->register([
            'prior_registration_id'   => (int) $reg['id'],
            'course_id'               => $courseId,
            'study_mode'              => in_array($mode, ['online', 'physical'], true) ? $mode : ($reg['study_mode'] ?: 'online'),
            'payment_plan'            => $reg['payment_plan'] ?: 'monthly',
            'current_learning_month'  => 1,
            'installments_paid'       => 0,
            'amount_paid_prior'       => 0,
            'registration_fee_waived' => true,
            'portal_locked'           => false,
            'fee_status'              => 'current',
            'payment_reference'       => 'Additional course',
        ], $adminId);
        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => $result['error'] ?? 'Could not add the course.'];
        }
        $this->syncSmsRecord((int) $result['registration_id'], $reg, $course);
        study_placement_set($this->pdo, (int) $result['registration_id'], $mode, $location ?: ($reg['study_location'] ?? null));
        return ['ok' => true, 'message' => 'Enrolled in ' . $course['title'] . '.', 'registration_id' => (int) $result['registration_id']];
    }

    /** Move one enrolment to another course. Payments already made stay; the fee and instalments now follow the new course. */
    public function transfer(array $reg, int $courseId, int $adminId, ?string $mode = null, ?string $location = null): array
    {
        $course = $this->validTarget($reg, $courseId);
        if (isset($course['error'])) {
            return ['ok' => false, 'error' => $course['error']];
        }
        $fromTitle = (string) ($this->courses->getById((int) $reg['course_id'])['title'] ?? 'previous course');
        $plan = $reg['payment_plan'] ?: 'monthly';
        $inst = RegistrationModel::calculateInstallments((float) $course['course_fee'], $plan, course_effective_months($course));

        $paid = $this->paidFor((int) $reg['id']);
        $fee = (float) $course['course_fee'] + ($reg['registration_fee_waived'] ? 0 : (float) $reg['registration_fee']);
        $status = ($paid >= $fee && $fee > 0) ? 'paid_in_full' : ($paid > 0 ? 'partial' : 'current');
        $note = mb_substr('Transferred from ' . $fromTitle . ' on ' . date('d M Y') . ($reg['fee_balance_note'] ? ' · ' . $reg['fee_balance_note'] : ''), 0, 255);

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare(
                'UPDATE registrations SET course_id = :cid, course_fee = :fee, installment_count = :ic, installment_amount = :ia,
                        current_learning_month = 1, installments_paid = 0, fee_status = :fs, fee_balance_note = :note WHERE id = :id'
            )->execute([':cid' => $courseId, ':fee' => $course['course_fee'], ':ic' => $inst['count'], ':ia' => $inst['amount'],
                        ':fs' => $status, ':note' => $note, ':id' => (int) $reg['id']]);
            $this->syncSmsRecord((int) $reg['id'], $reg, $course, true);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[CourseChangeService::transfer] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Transfer failed: ' . $e->getMessage()];
        }

        (new ProgressModel($this->pdo))->initializeForRegistration((int) $reg['id'], $courseId, 1);
        (new StudentModel($this->pdo))->setNextPaymentDue((int) $reg['id'], $plan);
        study_placement_set($this->pdo, (int) $reg['id'], $mode, $location);
        registration_number_resync($this->pdo, null, (int) $reg['id']);
        $moved = ($location && $location !== ($reg['study_location'] ?? null) && isset(study_locations()[$location])) ? ' Location changed to ' . study_locations()[$location] . '.' : '';
        return ['ok' => true, 'message' => 'Transferred from ' . $fromTitle . ' to ' . $course['title'] . '. The fee now follows the new course.' . $moved];
    }

    private function validTarget(array $reg, int $courseId)
    {
        $course = $this->courses->getById($courseId);
        if (!$course || empty($course['is_active'])) {
            return ['error' => 'That course is not available.'];
        }
        foreach ($this->enrolments($reg) as $e) {
            if ((int) $e['course_id'] === $courseId) {
                return ['error' => 'The student is already enrolled in ' . $course['title'] . '.'];
            }
        }
        return $course;
    }

    private function paidFor(int $registrationId): float
    {
        $st = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE registration_id = ? AND status = 'approved'");
        $st->execute([$registrationId]);
        return (float) $st->fetchColumn();
    }

    /** Keep the fee-record (sms_students) row in step: update it on transfer, create it for a newly added course. */
    private function syncSmsRecord(int $registrationId, array $reg, array $course, bool $update = false): void
    {
        try {
            $ex = $this->pdo->prepare('SELECT id FROM sms_students WHERE registration_id = ? LIMIT 1');
            $ex->execute([$registrationId]);
            $smsId = (int) $ex->fetchColumn();
            if ($smsId) {
                $this->pdo->prepare('UPDATE sms_students SET course_id = ?, cost = ?, duration = ? WHERE id = ?')
                    ->execute([(int) $course['id'], $course['course_fee'], $course['duration_label'] ?? null, $smsId]);
            } elseif (!$update) {
                $this->pdo->prepare(
                    "INSERT INTO sms_students (name, email, phone, course_id, cost, paid, status, user_id, registration_id)
                     VALUES (?, ?, ?, ?, ?, 0, 'active', ?, ?)"
                )->execute([$reg['full_name'], $reg['email'], $reg['phone'], (int) $course['id'], $course['course_fee'], $reg['user_id'] ?: null, $registrationId]);
            }
        } catch (Throwable $e) {
            // sms_students is optional (older installs); the registration is the source of truth.
        }
    }
}
