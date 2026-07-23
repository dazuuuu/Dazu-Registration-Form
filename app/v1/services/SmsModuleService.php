<?php
// app/v1/services/SmsModuleService.php — SmS module business logic

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/StudentNotifier.php';
require_once __DIR__ . '/../models/CourseModel.php';
require_once __DIR__ . '/../controllers/ManualOnboardController.php';

class SmsModuleService
{
    private PDO $pdo;
    private int $actorId;

    public function __construct(PDO $pdo, int $actorId = 0)
    {
        $this->pdo = $pdo;
        $this->actorId = $actorId;
    }

    public static function balance(float $cost, float $paid): float
    {
        return round(max(0, $cost - $paid), 2);
    }

    public static function isInstalled(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM sms_students LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function log(string $type, string $description, ?string $entityType = null, ?int $entityId = null): void
    {
        if (!self::isInstalled($this->pdo)) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO sms_activity (activity_type, entity_type, entity_id, description, actor_id)
                 VALUES (:t, :et, :eid, :d, :aid)'
            );
            $stmt->execute([
                ':t' => $type,
                ':et' => $entityType,
                ':eid' => $entityId,
                ':d' => mb_substr($description, 0, 500),
                ':aid' => $this->actorId ?: null,
            ]);
        } catch (Throwable $e) {
            error_log('[SmsModule] log failed: ' . $e->getMessage());
        }
    }

    public function recentActivity(int $limit = 25): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.username AS actor_name FROM sms_activity a
             LEFT JOIN users u ON u.id = a.actor_id
             ORDER BY a.created_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Dashboard ───────────────────────────────────────────────

    public function dashboardStats(): array
    {
        if (!self::isInstalled($this->pdo)) {
            return [
                'students' => 0, 'prospects' => 0, 'openProspects' => 0, 'projects' => 0, 'activeProjects' => 0,
                'stuFin' => ['cost' => 0, 'paid' => 0], 'prjFin' => ['cost' => 0, 'paid' => 0],
                'stuArrears' => 0, 'salesStudents' => 0, 'salesProjects' => 0, 'dueSoon' => [], 'meetings' => [],
            ];
        }

        $students = (int) $this->pdo->query('SELECT COUNT(*) FROM sms_students')->fetchColumn();
        $prospects = (int) $this->pdo->query('SELECT COUNT(*) FROM sms_prospects')->fetchColumn();
        $openProspects = (int) $this->pdo->query("SELECT COUNT(*) FROM sms_prospects WHERE status = 'open'")->fetchColumn();
        $projects = (int) $this->pdo->query('SELECT COUNT(*) FROM sms_projects')->fetchColumn();
        $activeProjects = (int) $this->pdo->query("SELECT COUNT(*) FROM sms_projects WHERE status IN ('active','in_progress')")->fetchColumn();

        $stuFin = $this->pdo->query('SELECT COALESCE(SUM(cost),0) AS cost, COALESCE(SUM(paid),0) AS paid FROM sms_students')->fetch(PDO::FETCH_ASSOC);
        $prjFin = $this->pdo->query('SELECT COALESCE(SUM(estimated_cost),0) AS cost, COALESCE(SUM(paid_amount),0) AS paid FROM sms_projects')->fetch(PDO::FETCH_ASSOC);

        $stuArrears = (int) $this->pdo->query('SELECT COUNT(*) FROM sms_students WHERE cost > paid')->fetchColumn();

        $salesStudents = (float) $this->pdo->query("SELECT COALESCE(SUM(amount),0) FROM sms_sales WHERE category = 'students'")->fetchColumn();
        $salesProjects = (float) $this->pdo->query("SELECT COALESCE(SUM(amount),0) FROM sms_sales WHERE category = 'projects'")->fetchColumn();

        $dueSoon = $this->pdo->query(
            "SELECT * FROM sms_projects
             WHERE status IN ('active','in_progress')
               AND due_date IS NOT NULL
               AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
             ORDER BY due_date ASC LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        $meetings = $this->pdo->query(
            "SELECT m.*, p.title AS project_title FROM sms_project_meetings m
             INNER JOIN sms_projects p ON p.id = m.project_id
             WHERE m.status = 'scheduled' AND m.meeting_date >= NOW()
             ORDER BY m.meeting_date ASC LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);

        return compact(
            'students', 'prospects', 'openProspects', 'projects', 'activeProjects',
            'stuFin', 'prjFin', 'stuArrears', 'salesStudents', 'salesProjects', 'dueSoon', 'meetings'
        );
    }

    /** Compact alerts for the main admin dashboard. */
    public function dashboardAlerts(): array
    {
        if (!self::isInstalled($this->pdo)) {
            return ['installed' => false, 'summary' => [], 'alerts' => []];
        }

        $summary = [
            'students' => (int) $this->pdo->query('SELECT COUNT(*) FROM sms_students')->fetchColumn(),
            'arrears' => (int) $this->pdo->query('SELECT COUNT(*) FROM sms_students WHERE cost > paid')->fetchColumn(),
            'prospects_open' => (int) $this->pdo->query("SELECT COUNT(*) FROM sms_prospects WHERE status = 'open'")->fetchColumn(),
            'prospects_due' => (int) $this->pdo->query("SELECT COUNT(*) FROM sms_prospects WHERE status = 'open' AND follow_up_date IS NOT NULL AND follow_up_date <= CURDATE()")->fetchColumn(),
            'projects_active' => (int) $this->pdo->query("SELECT COUNT(*) FROM sms_projects WHERE status IN ('active','in_progress')")->fetchColumn(),
            'projects_due' => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM sms_projects WHERE status IN ('active','in_progress')
                 AND due_date IS NOT NULL AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)"
            )->fetchColumn(),
            'collected' => (float) $this->pdo->query('SELECT COALESCE(SUM(amount),0) FROM sms_sales')->fetchColumn(),
            'outstanding' => (float) $this->pdo->query('SELECT COALESCE(SUM(cost - paid),0) FROM sms_students WHERE cost > paid')->fetchColumn(),
        ];

        $alerts = [];

        $students = $this->pdo->query(
            'SELECT s.id, s.name, s.cost, s.paid, c.title AS course_title FROM sms_students s
             LEFT JOIN courses c ON c.id = s.course_id
             WHERE s.cost > s.paid ORDER BY (s.cost - s.paid) DESC LIMIT 8'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($students as $row) {
            $bal = self::balance((float) $row['cost'], (float) $row['paid']);
            $alerts[] = [
                'kind' => 'student',
                'severity' => 'danger',
                'title' => $row['name'],
                'detail' => ($row['course_title'] ?? 'No course') . ' · Balance KES ' . number_format($bal, 2),
                'path' => 'admin/sms/students/view.php?id=' . (int) $row['id'],
            ];
        }

        $prospects = $this->pdo->query(
            "SELECT id, name, follow_up_date, intake_or_course FROM sms_prospects
             WHERE status = 'open' AND follow_up_date IS NOT NULL
               AND follow_up_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
             ORDER BY follow_up_date ASC LIMIT 6"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($prospects as $row) {
            $overdue = $row['follow_up_date'] < date('Y-m-d');
            $alerts[] = [
                'kind' => 'prospect',
                'severity' => $overdue ? 'danger' : 'warning',
                'title' => $row['name'],
                'detail' => ($overdue ? 'Follow-up overdue' : 'Follow-up due') . ' · ' . ($row['intake_or_course'] ?? '—'),
                'path' => 'admin/sms/prospects/form.php?id=' . (int) $row['id'],
            ];
        }

        $projects = $this->pdo->query(
            "SELECT id, title, due_date, client_name FROM sms_projects
             WHERE status IN ('active','in_progress') AND due_date IS NOT NULL
               AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
             ORDER BY due_date ASC LIMIT 6"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($projects as $row) {
            $overdue = $row['due_date'] < date('Y-m-d');
            $alerts[] = [
                'kind' => 'project',
                'severity' => $overdue ? 'danger' : 'warning',
                'title' => $row['title'],
                'detail' => ($row['client_name'] ?? 'Client') . ' · Due ' . $row['due_date'],
                'path' => 'admin/sms/projects/view.php?id=' . (int) $row['id'],
            ];
        }

        return ['installed' => true, 'summary' => $summary, 'alerts' => $alerts];
    }

    // ─── Students ────────────────────────────────────────────────

    public function listStudents(?string $search = null, ?string $status = null): array
    {
        $sql = 'SELECT s.*, c.title AS course_title, r.registration_no, r.full_name AS reg_full_name,
                       r.email AS reg_email, r.phone AS reg_phone, r.user_id AS reg_user_id
                FROM sms_students s
                LEFT JOIN registrations r ON r.id = s.registration_id
                LEFT JOIN courses c ON c.id = COALESCE(s.course_id, r.course_id) WHERE 1=1';
        $params = [];
        if ($status && $status !== 'all') {
            $sql .= ' AND s.status = :st';
            $params[':st'] = $status;
        }
        if ($search) {
            $sql .= ' AND (s.name LIKE :q OR s.email LIKE :q OR s.phone LIKE :q OR c.title LIKE :q
                      OR r.full_name LIKE :q OR r.email LIKE :q OR r.registration_no LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY s.updated_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'normalizeStudentRow'], $rows);
    }

    public function getStudent(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, c.title AS course_title, r.registration_no, r.full_name AS reg_full_name,
                    r.email AS reg_email, r.phone AS reg_phone, r.user_id AS reg_user_id
             FROM sms_students s
             LEFT JOIN registrations r ON r.id = s.registration_id
             LEFT JOIN courses c ON c.id = COALESCE(s.course_id, r.course_id) WHERE s.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeStudentRow($row) : null;
    }

    private function normalizeStudentRow(array $row): array
    {
        if (!empty($row['reg_full_name'])) {
            $row['name'] = $row['reg_full_name'];
        }
        if (!empty($row['reg_email'])) {
            $row['email'] = $row['reg_email'];
        }
        if (!empty($row['reg_phone'])) {
            $row['phone'] = $row['reg_phone'];
        }
        if (!empty($row['reg_user_id']) && empty($row['user_id'])) {
            $row['user_id'] = $row['reg_user_id'];
        }
        return $row;
    }

    /** Human-readable study period from all course duration fields. */
    public static function courseStudySummary(?array $course): string
    {
        if (!$course) {
            return '';
        }
        $bits = [];
        $mo = (int) ($course['duration_months'] ?? 0);
        if ($mo > 0) {
            $bits[] = $mo . ' month' . ($mo === 1 ? '' : 's');
        }
        $wk = (int) ($course['weeks_of_study'] ?? 0);
        if ($wk > 0) {
            $bits[] = $wk . ' week' . ($wk === 1 ? '' : 's');
        }
        $hrs = (int) ($course['hours_of_study'] ?? 0);
        if ($hrs > 0) {
            $bits[] = $hrs . ' hour' . ($hrs === 1 ? '' : 's');
        }
        $sched = trim((string) ($course['study_schedule'] ?? ''));
        if ($sched !== '') {
            $bits[] = $sched;
        }
        return implode(' · ', $bits);
    }

    /** Primary billing/scaling unit for enrollment cost (weeks → hours → months). */
    public static function courseDurationMeta(?array $course): array
    {
        if (!$course) {
            return ['unit' => 'months', 'value' => 1, 'label' => '1 month', 'fee' => 0.0];
        }
        $fee = (float) ($course['course_fee'] ?? 0);
        $summary = self::courseStudySummary($course);
        if (!empty($course['weeks_of_study'])) {
            $v = (int) $course['weeks_of_study'];
            return ['unit' => 'weeks', 'value' => $v, 'label' => $summary ?: ($v . ' week' . ($v === 1 ? '' : 's')), 'fee' => $fee];
        }
        if (!empty($course['hours_of_study'])) {
            $v = (int) $course['hours_of_study'];
            return ['unit' => 'hours', 'value' => $v, 'label' => $summary ?: ($v . ' hour' . ($v === 1 ? '' : 's')), 'fee' => $fee];
        }
        $v = max(1, (int) ($course['duration_months'] ?? 1));
        return ['unit' => 'months', 'value' => $v, 'label' => $summary ?: ($v . ' month' . ($v === 1 ? '' : 's')), 'fee' => $fee];
    }

    public function coursePricingCatalog(): array
    {
        $catalog = [];
        foreach ($this->courses() as $course) {
            $meta = self::courseDurationMeta($course);
            $summary = self::courseStudySummary($course);
            $catalog[] = [
                'id' => (int) $course['id'],
                'title' => $course['title'],
                'fee' => $meta['fee'],
                'duration_months' => (int) ($course['duration_months'] ?? 0),
                'weeks_of_study' => (int) ($course['weeks_of_study'] ?? 0),
                'hours_of_study' => (int) ($course['hours_of_study'] ?? 0),
                'study_schedule' => trim((string) ($course['study_schedule'] ?? '')),
                'duration_summary' => $summary,
                'duration_unit' => $meta['unit'],
                'duration_value' => $meta['value'],
                'duration_label' => $summary ?: $meta['label'],
            ];
        }
        return $catalog;
    }

    private function resolveStudentCostDuration(array $data, ?array $existing = null): array
    {
        $courseId = (int) ($data['course_id'] ?? $existing['course_id'] ?? 0);
        $cm = new \Models\CourseModel($this->pdo);
        $course = $courseId ? $cm->getById($courseId) : null;
        $meta = self::courseDurationMeta($course);

        $unit = $data['duration_unit'] ?? $meta['unit'];
        $value = (float) ($data['duration_value'] ?? $meta['value']);
        if ($value <= 0) {
            $value = (float) $meta['value'];
        }
        $baseValue = (float) ($data['base_duration_value'] ?? $meta['value']);
        $baseFee = (float) ($data['base_course_fee'] ?? $meta['fee']);
        if ($baseFee <= 0 && $course) {
            $baseFee = (float) $course['course_fee'];
        }

        if ($baseValue > 0 && $value > 0 && abs($value - $baseValue) > 0.0001) {
            $cost = round($baseFee * ($value / $baseValue), 2);
        } else {
            $cost = round((float) ($data['cost'] ?? $baseFee), 2);
        }

        $fullSummary = $course ? self::courseStudySummary($course) : '';
        $primaryLabel = self::formatDurationLabel($unit, $value);
        $atDefault = abs($value - (float) $meta['value']) < 0.0001 && $unit === $meta['unit'];
        $duration = $atDefault && $fullSummary !== ''
            ? $fullSummary
            : ($primaryLabel ?: trim($data['duration'] ?? '') ?: ($fullSummary ?: $meta['label']));

        return [
            'cost' => $cost,
            'duration' => $duration,
        ];
    }

    private static function formatDurationLabel(string $unit, float $value): string
    {
        $v = (int) round($value);
        if ($v <= 0) {
            return '';
        }
        if ($unit === 'weeks') {
            return $v . ' week' . ($v === 1 ? '' : 's');
        }
        if ($unit === 'hours') {
            return $v . ' hour' . ($v === 1 ? '' : 's');
        }
        if ($unit === 'days') {
            return $v . ' day' . ($v === 1 ? '' : 's');
        }
        return $v . ' month' . ($v === 1 ? '' : 's');
    }

    public function createStudent(array $data): array
    {
        $email = strtolower(trim($data['email'] ?? ''));
        $phone = trim($data['phone'] ?? '');
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'error' => 'Student name is required.'];
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'A valid student email is required to create the portal account.'];
        }
        if ($phone === '') {
            return ['ok' => false, 'error' => 'Phone number is required to create the student account.'];
        }
        if (empty($data['course_id'])) {
            return ['ok' => false, 'error' => 'Please select a course.'];
        }

        $pricing = $this->resolveStudentCostDuration($data);
        $cost = $pricing['cost'];
        $paid = round((float) ($data['paid'] ?? 0), 2);
        if ($paid > $cost) {
            return ['ok' => false, 'error' => 'Paid amount cannot exceed cost.'];
        }

        $onboardPayload = [
            'full_name'               => $name,
            'email'                   => $email,
            'phone'                   => $phone,
            'course_id'               => (int) $data['course_id'],
            'study_mode'              => 'online',
            'payment_plan'            => $paid >= $cost && $cost > 0 ? 'full' : 'monthly',
            'current_learning_month'  => 1,
            'installments_paid'       => 0,
            'amount_paid_prior'       => $paid,
            'registration_fee_waived' => true,
            'portal_locked'           => false,
            'fee_balance_note'        => trim($data['notes'] ?? ''),
            'fee_status'              => $paid >= $cost && $cost > 0 ? 'paid_in_full' : ($paid > 0 ? 'partial' : 'current'),
            'payment_reference'       => 'SmS enrollment',
            'must_change_password'    => true,
        ];

        $onboard = new \Controllers\ManualOnboardController();
        $onboardResult = $onboard->onboardNew($onboardPayload, $this->actorId);
        if (empty($onboardResult['ok'])) {
            return ['ok' => false, 'error' => $onboardResult['error'] ?? 'Failed to create student account.'];
        }

        $registrationId = (int) $onboardResult['registration_id'];
        $uidStmt = $this->pdo->prepare('SELECT user_id FROM registrations WHERE id = :id LIMIT 1');
        $uidStmt->execute([':id' => $registrationId]);
        $userId = (int) $uidStmt->fetchColumn();

        if ($cost > 0) {
            $this->pdo->prepare('UPDATE registrations SET course_fee = :fee WHERE id = :id')
                ->execute([':fee' => $cost, ':id' => $registrationId]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO sms_students (name, email, phone, course_id, duration, start_date, parent_phone, parent_email, cost, paid, status, notes, user_id, registration_id)
             VALUES (:name, :email, :phone, :cid, :dur, :sd, :pp, :pe, :cost, :paid, :st, :notes, :uid, :rid)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':cid' => (int) $data['course_id'],
            ':dur' => $pricing['duration'] ?: null,
            ':sd' => $data['start_date'] ?: null,
            ':pp' => trim($data['parent_phone'] ?? '') ?: null,
            ':pe' => trim($data['parent_email'] ?? '') ?: null,
            ':cost' => $cost,
            ':paid' => $paid,
            ':st' => $data['status'] ?? 'active',
            ':notes' => trim($data['notes'] ?? '') ?: null,
            ':uid' => $userId ?: null,
            ':rid' => $registrationId,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        $student = $this->getStudent($id);
        $this->studentCredentials = [
            'temp_password' => $onboardResult['temp_password'],
            'username' => $onboardResult['reg_no'],
        ];

        $receipt = $this->generateReceipt($student, $paid > 0 ? $paid : 0);
        if ($paid > 0) {
            $paymentId = $this->recordPayment($id, $paid, $receipt['id'] ?? null, 'Initial payment on onboarding');
            $this->recordSale('students', 'Student payment: ' . $student['name'], $paid, $id, null, $paymentId);
        }

        $this->emailStudentOnboarding($student, $receipt);
        $this->log('student_created', 'Created student ' . $student['name'] . ' (' . $onboardResult['reg_no'] . ')', 'student', $id);

        return ['ok' => true, 'id' => $id, 'registration_id' => $registrationId, 'reg_no' => $onboardResult['reg_no']];
    }

    public function updateStudent(int $id, array $data): array
    {
        $existing = $this->getStudent($id);
        if (!$existing) {
            return ['ok' => false, 'error' => 'Student not found.'];
        }

        $pricing = $this->resolveStudentCostDuration($data, $existing);
        $cost = $pricing['cost'];
        $newPaid = round((float) ($data['paid'] ?? $existing['paid']), 2);
        $oldPaid = (float) $existing['paid'];
        if ($newPaid > $cost) {
            return ['ok' => false, 'error' => 'Paid amount cannot exceed cost.'];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE sms_students SET name=:name, email=:email, phone=:phone, course_id=:cid, duration=:dur,
             start_date=:sd, parent_phone=:pp, parent_email=:pe, cost=:cost, paid=:paid, status=:st, notes=:notes
             WHERE id=:id'
        );
        $stmt->execute([
            ':name' => trim($data['name']),
            ':email' => trim($data['email'] ?? '') ?: null,
            ':phone' => trim($data['phone'] ?? '') ?: null,
            ':cid' => !empty($data['course_id']) ? (int) $data['course_id'] : null,
            ':dur' => $pricing['duration'] ?: null,
            ':sd' => $data['start_date'] ?: null,
            ':pp' => trim($data['parent_phone'] ?? '') ?: null,
            ':pe' => trim($data['parent_email'] ?? '') ?: null,
            ':cost' => $cost,
            ':paid' => $newPaid,
            ':st' => $data['status'] ?? $existing['status'],
            ':notes' => trim($data['notes'] ?? '') ?: null,
            ':id' => $id,
        ]);

        if (!empty($existing['registration_id'])) {
            $this->pdo->prepare(
                'UPDATE registrations SET full_name = :name, email = :email, phone = :phone, course_fee = :fee, course_id = :cid WHERE id = :id'
            )->execute([
                ':name' => trim($data['name']),
                ':email' => trim($data['email'] ?? '') ?: null,
                ':phone' => trim($data['phone'] ?? '') ?: null,
                ':fee' => $cost,
                ':cid' => !empty($data['course_id']) ? (int) $data['course_id'] : null,
                ':id' => (int) $existing['registration_id'],
            ]);
        }

        $student = $this->getStudent($id);
        $delta = round($newPaid - $oldPaid, 2);
        if ($delta > 0) {
            $receipt = $this->generateReceipt($student, $delta);
            $paymentId = $this->recordPayment($id, $delta, $receipt['id'] ?? null, 'Payment recorded on edit');
            $this->recordSale('students', 'Student payment: ' . $student['name'], $delta, $id, null, $paymentId);
            $this->emailReceipt($student, $receipt);
            $this->log('receipt_generated', 'Receipt ' . ($receipt['receipt_no'] ?? '') . ' for ' . $student['name'], 'student', $id);
        } else {
            $this->log('student_updated', 'Updated student ' . $student['name'], 'student', $id);
        }

        return ['ok' => true];
    }

    public function deleteStudent(int $id): bool
    {
        $s = $this->getStudent($id);
        if (!$s) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM sms_students WHERE id = :id');
        $ok = $stmt->execute([':id' => $id]);
        if ($ok) {
            $this->log('student_deleted', 'Deleted student ' . $s['name'], 'student', $id);
        }
        return $ok;
    }

    /**
     * Reset portal password and email fresh login credentials to the student.
     * Creates or links a portal account first when missing (legacy SmS records).
     *
     * @return array{ok:bool, error?:string, email?:string}
     */
    public function resendStudentCredentials(int $id): array
    {
        $linked = $this->ensurePortalAccountForStudent($id);
        if (empty($linked['ok'])) {
            return $linked;
        }

        $student = $linked['student'];
        $email = strtolower(trim($student['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Student has no valid email address on file.'];
        }

        $registrationId = (int) ($student['registration_id'] ?? 0);
        $userId = (int) ($student['user_id'] ?? $student['reg_user_id'] ?? 0);
        if ($registrationId <= 0 || $userId <= 0) {
            return ['ok' => false, 'error' => 'Could not resolve a portal account for this student.'];
        }

        $regNo = trim($student['registration_no'] ?? '');
        if ($regNo === '') {
            $stmt = $this->pdo->prepare('SELECT registration_no FROM registrations WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $registrationId]);
            $regNo = trim((string) $stmt->fetchColumn());
        }
        if ($regNo === '') {
            $stmt = $this->pdo->prepare('SELECT username FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $regNo = trim((string) $stmt->fetchColumn());
        }
        if ($regNo === '') {
            return ['ok' => false, 'error' => 'Login username not found for this student.'];
        }

        $tempPassword = !empty($linked['temp_password'])
            ? $linked['temp_password']
            : bin2hex(random_bytes(8));

        if (empty($linked['temp_password'])) {
            $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
            try {
                $this->pdo->prepare(
                    'UPDATE users SET password_hash = :hash, is_active = 1, account_status = \'active\', must_change_password = 1 WHERE id = :uid'
                )->execute([':hash' => $hash, ':uid' => $userId]);
            } catch (PDOException $e) {
                $this->pdo->prepare(
                    'UPDATE users SET password_hash = :hash, is_active = 1, account_status = \'active\' WHERE id = :uid'
                )->execute([':hash' => $hash, ':uid' => $userId]);
            }
        }

        $notifier = new StudentNotifier($this->pdo, null, true);
        $result = $notifier->sendCredentials([
            'email' => $email,
            'full_name' => $student['name'],
            'registration_no' => $regNo,
            'course_title' => $student['course_title'] ?? '',
        ], $tempPassword);

        if (empty($result['ok'])) {
            return [
                'ok' => false,
                'error' => 'Password was reset but the email could not be sent. ' . ($result['message'] ?? 'Check SMTP settings.'),
            ];
        }

        $this->log('credentials_resent', 'Resent login credentials to ' . $email, 'student', $id);

        return ['ok' => true, 'email' => $email];
    }

    /**
     * Link or create the main-system portal account for an SmS student record.
     *
     * @return array{ok:bool, error?:string, student?:array, temp_password?:string, created?:bool}
     */
    private function ensurePortalAccountForStudent(int $smsStudentId): array
    {
        $student = $this->getStudent($smsStudentId);
        if (!$student) {
            return ['ok' => false, 'error' => 'Student not found.'];
        }

        $email = strtolower(trim($student['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Add a valid email address on the student profile first.'];
        }

        $registrationId = (int) ($student['registration_id'] ?? 0);
        $userId = (int) ($student['user_id'] ?? $student['reg_user_id'] ?? 0);
        if ($registrationId > 0 && $userId > 0) {
            return ['ok' => true, 'student' => $student];
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, user_id, registration_no FROM registrations
             WHERE email = :email AND status = 'approved' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($reg && !empty($reg['user_id'])) {
            $this->linkSmsStudentPortal($smsStudentId, (int) $reg['id'], (int) $reg['user_id']);
            return ['ok' => true, 'student' => $this->getStudent($smsStudentId)];
        }

        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.username FROM users u
             INNER JOIN roles r ON r.id = u.role_id AND r.role_name = 'student'
             WHERE u.email = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $userId = (int) $user['id'];
            $stmt = $this->pdo->prepare(
                "SELECT id, user_id, registration_no FROM registrations
                 WHERE user_id = :uid AND status = 'approved' ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([':uid' => $userId]);
            $reg = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($reg) {
                $this->linkSmsStudentPortal($smsStudentId, (int) $reg['id'], $userId);
                return ['ok' => true, 'student' => $this->getStudent($smsStudentId)];
            }

            $created = $this->createRegistrationForExistingUser($student, $userId);
            if (empty($created['ok'])) {
                return $created;
            }
            $this->linkSmsStudentPortal($smsStudentId, (int) $created['registration_id'], $userId);
            return ['ok' => true, 'student' => $this->getStudent($smsStudentId)];
        }

        if (empty($student['course_id'])) {
            return ['ok' => false, 'error' => 'Assign a course to this student before sending account details.'];
        }
        if (trim($student['phone'] ?? '') === '') {
            return ['ok' => false, 'error' => 'Add a phone number before sending account details.'];
        }

        $paid = (float) ($student['paid'] ?? 0);
        $cost = (float) ($student['cost'] ?? 0);
        $onboard = new \Controllers\ManualOnboardController();
        $result = $onboard->onboardNew([
            'full_name'               => $student['name'],
            'email'                   => $email,
            'phone'                   => $student['phone'],
            'course_id'               => (int) $student['course_id'],
            'study_mode'              => 'online',
            'payment_plan'            => $paid >= $cost && $cost > 0 ? 'full' : 'monthly',
            'current_learning_month'  => 1,
            'installments_paid'       => 0,
            'amount_paid_prior'       => 0,
            'registration_fee_waived' => true,
            'portal_locked'           => false,
            'fee_balance_note'        => trim($student['notes'] ?? ''),
            'fee_status'              => 'current',
            'payment_reference'       => 'SmS account provision',
            'must_change_password'    => true,
        ], $this->actorId);

        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => $result['error'] ?? 'Could not create portal account.'];
        }

        $uidStmt = $this->pdo->prepare('SELECT user_id FROM registrations WHERE id = :id LIMIT 1');
        $uidStmt->execute([':id' => $result['registration_id']]);
        $newUserId = (int) $uidStmt->fetchColumn();
        $this->linkSmsStudentPortal($smsStudentId, (int) $result['registration_id'], $newUserId);

        if ($cost > 0) {
            $this->pdo->prepare('UPDATE registrations SET course_fee = :fee WHERE id = :id')
                ->execute([':fee' => $cost, ':id' => $result['registration_id']]);
        }

        return [
            'ok' => true,
            'student' => $this->getStudent($smsStudentId),
            'temp_password' => $result['temp_password'],
            'created' => true,
        ];
    }

    private function linkSmsStudentPortal(int $smsStudentId, int $registrationId, int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE sms_students SET registration_id = :rid, user_id = :uid WHERE id = :id'
        )->execute([':rid' => $registrationId, ':uid' => $userId, ':id' => $smsStudentId]);
    }

    /**
     * @return array{ok:bool, error?:string, registration_id?:int}
     */
    private function createRegistrationForExistingUser(array $student, int $userId): array
    {
        $courseId = (int) ($student['course_id'] ?? 0);
        if ($courseId <= 0) {
            return ['ok' => false, 'error' => 'Assign a course to this student before sending account details.'];
        }

        $cm = new \Models\CourseModel($this->pdo);
        $course = $cm->getById($courseId);
        if (!$course || empty($course['is_active'])) {
            return ['ok' => false, 'error' => 'Selected course is not available.'];
        }

        $resolvedAdmin = resolve_valid_user_id($this->pdo, $this->actorId, true);
        if ($resolvedAdmin === null) {
            return ['ok' => false, 'error' => 'No valid admin account found.'];
        }

        try {
            $this->pdo->beginTransaction();
            $seq = $cm->claimNextSequence($courseId);
            $regNo = sprintf('DH-%s%02d/%s', $course['abbreviation'], $seq, date('Y'));
            $cost = (float) ($student['cost'] ?? $course['course_fee']);
            $paid = (float) ($student['paid'] ?? 0);

            $stmt = $this->pdo->prepare(
                "INSERT INTO registrations
                 (registration_no, full_name, email, phone, course_id, study_mode, id_document_path,
                  registration_fee, course_fee, payment_plan, installment_count, installment_amount,
                  payment_reference, possible_returning_student, enrollment_type, current_learning_month,
                  fee_balance_note, fee_status, registration_fee_waived, installments_paid,
                  status, reviewed_by, reviewed_at, user_id, portal_locked)
                 VALUES
                 (:reg_no, :full_name, :email, :phone, :course_id, 'online', 'admin/sms-provision',
                  0, :course_fee, :plan, 1, :course_fee,
                  'SmS account provision', 0, 'continuing', 1, :fee_note, :fee_status, 1, 0,
                  'approved', :admin, NOW(), :user_id, 0)"
            );
            $stmt->execute([
                ':reg_no'      => $regNo,
                ':full_name'   => $student['name'],
                ':email'       => strtolower(trim($student['email'] ?? '')),
                ':phone'       => trim($student['phone'] ?? '') ?: 'N/A',
                ':course_id'   => $courseId,
                ':course_fee'  => $cost,
                ':plan'        => $paid >= $cost && $cost > 0 ? 'full' : 'monthly',
                ':fee_note'    => trim($student['notes'] ?? '') ?: null,
                ':fee_status'  => $paid >= $cost && $cost > 0 ? 'paid_in_full' : ($paid > 0 ? 'partial' : 'current'),
                ':admin'       => $resolvedAdmin,
                ':user_id'     => $userId,
            ]);
            $registrationId = (int) $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare('UPDATE users SET username = :u WHERE id = :id AND username LIKE \'SMS%\'');
            $stmt->execute([':u' => $regNo, ':id' => $userId]);

            $this->pdo->commit();

            require_once __DIR__ . '/../models/ProgressModel.php';
            $progress = new \Models\ProgressModel($this->pdo);
            $progress->initializeForRegistration($registrationId, $courseId, 1);

            return ['ok' => true, 'registration_id' => $registrationId];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[SmsModule] createRegistrationForExistingUser: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not link portal account: ' . $e->getMessage()];
        }
    }

    public function studentPayments(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM sms_student_payments WHERE student_id = :id ORDER BY payment_date DESC, id DESC'
        );
        $stmt->execute([':id' => $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function studentReceipts(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM sms_receipts WHERE student_id = :id ORDER BY id DESC'
        );
        $stmt->execute([':id' => $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReceipt(int $receiptId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sms_receipts WHERE id = :id');
        $stmt->execute([':id' => $receiptId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function nextReceiptNo(): string
    {
        $year = (int) date('Y');
        $this->pdo->prepare('INSERT IGNORE INTO sms_receipt_counter (year, last_number) VALUES (:y, 0)')->execute([':y' => $year]);
        $this->pdo->prepare('UPDATE sms_receipt_counter SET last_number = last_number + 1 WHERE year = :y')->execute([':y' => $year]);
        $num = (int) $this->pdo->query('SELECT last_number FROM sms_receipt_counter WHERE year = ' . $year)->fetchColumn();
        return sprintf('SMS-%d-%05d', $year, $num);
    }

    public function generateReceipt(array $student, float $amountDelta = 0): array
    {
        $cost = (float) $student['cost'];
        $paid = (float) $student['paid'];
        $balance = self::balance($cost, $paid);
        $type = $balance > 0 ? 'arrears' : 'receipt';
        $receiptNo = $this->nextReceiptNo();
        $path = $this->renderReceiptPdf($student, $receiptNo, $type, $amountDelta, $cost, $paid, $balance);

        $stmt = $this->pdo->prepare(
            'INSERT INTO sms_receipts (student_id, receipt_no, receipt_type, amount_delta, cost, paid_total, balance, file_path)
             VALUES (:sid, :no, :type, :delta, :cost, :paid, :bal, :path)'
        );
        $stmt->execute([
            ':sid' => $student['id'],
            ':no' => $receiptNo,
            ':type' => $type,
            ':delta' => $amountDelta,
            ':cost' => $cost,
            ':paid' => $paid,
            ':bal' => $balance,
            ':path' => $path,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        return ['id' => $id, 'receipt_no' => $receiptNo, 'file_path' => $path, 'receipt_type' => $type];
    }

    public function regenerateReceipt(int $receiptId): ?array
    {
        $r = $this->getReceipt($receiptId);
        if (!$r) {
            return null;
        }
        $student = $this->getStudent((int) $r['student_id']);
        if (!$student) {
            return null;
        }
        $path = $this->renderReceiptPdf($student, $r['receipt_no'], $r['receipt_type'], (float) $r['amount_delta'], (float) $r['cost'], (float) $r['paid_total'], (float) $r['balance']);
        $this->pdo->prepare('UPDATE sms_receipts SET file_path = :p WHERE id = :id')->execute([':p' => $path, ':id' => $receiptId]);
        $this->log('receipt_regenerated', 'Regenerated receipt ' . $r['receipt_no'], 'receipt', $receiptId);
        return array_merge($r, ['file_path' => $path]);
    }

    private function recordPayment(int $studentId, float $amount, ?int $receiptId, string $notes = ''): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sms_student_payments (student_id, amount, payment_date, receipt_id, notes, recorded_by)
             VALUES (:sid, :amt, CURDATE(), :rid, :notes, :by)'
        );
        $stmt->execute([
            ':sid' => $studentId,
            ':amt' => $amount,
            ':rid' => $receiptId,
            ':notes' => $notes,
            ':by' => $this->actorId ?: null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function recordSale(string $category, string $description, float $amount, ?int $studentId = null, ?int $projectId = null, ?int $paymentId = null): void
    {
        $this->pdo->prepare(
            'INSERT INTO sms_sales (category, description, amount, sale_date, student_id, project_id, payment_id)
             VALUES (:cat, :desc, :amt, CURDATE(), :sid, :pid, :payid)'
        )->execute([
            ':cat' => $category,
            ':desc' => $description,
            ':amt' => $amount,
            ':sid' => $studentId,
            ':pid' => $projectId,
            ':payid' => $paymentId,
        ]);
        $this->log('sale_recorded', $description . ' — KES ' . number_format($amount, 2), $category, $studentId ?: $projectId);
    }

    private function companyInfo(): array
    {
        require_once __DIR__ . '/../helpers/receipt_settings.php';
        return sms_receipt_settings();
    }

    private function receiptLogoTag(array $co): string
    {
        $logo = sms_receipt_logo_absolute($co['logo_path'] ?? '');
        if (!$logo || !is_file($logo)) {
            return '';
        }
        $mime = mime_content_type($logo) ?: 'image/png';
        $b64 = base64_encode((string) file_get_contents($logo));
        return '<img src="data:' . $mime . ';base64,' . $b64 . '" alt="Logo" style="max-height:56px;max-width:180px;margin-bottom:10px;">';
    }

    private function receiptHtml(array $student, string $receiptNo, string $type, float $delta, float $cost, float $paid, float $balance): string
    {
        $co = $this->companyInfo();
        $typeLabel = strtoupper($type === 'arrears' ? 'FEE ARREARS NOTICE' : 'OFFICIAL RECEIPT');
        $logo = $this->receiptLogoTag($co);
        $addr = array_filter([$co['location'] ?? '', $co['pobox'] ? 'P.O. Box ' . $co['pobox'] : '']);
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($receiptNo) . '</title>
        <style>
        body{font-family:DejaVu Sans,Arial,sans-serif;margin:0;padding:36px;color:#111;background:#fff}
        .wrap{max-width:720px;margin:0 auto;border:1px solid #111;padding:28px}
        .head{text-align:center;border-bottom:2px solid #111;padding-bottom:16px;margin-bottom:20px}
        .head h1{font-size:20px;margin:8px 0 4px;letter-spacing:.04em;text-transform:uppercase}
        .head p{margin:2px 0;font-size:12px;color:#333}
        .doc-title{text-align:center;font-size:15px;font-weight:bold;margin:18px 0 14px;letter-spacing:.08em}
        table{width:100%;border-collapse:collapse;font-size:13px}
        td,th{border:1px solid #111;padding:9px 10px;text-align:left}
        th{width:34%;background:#f5f5f5;font-weight:700}
        .amount{font-weight:700}
        .footer{margin-top:24px;padding-top:14px;border-top:1px solid #111;font-size:11px;color:#333;text-align:center}
        </style></head><body><div class="wrap">
        <div class="head">' . $logo . '
        <h1>' . htmlspecialchars($co['company_name'] ?? 'Dazu Hub Institute') . '</h1>';
        if (!empty($co['phone'])) {
            $html .= '<p>' . htmlspecialchars($co['phone']) . '</p>';
        }
        if ($addr) {
            $html .= '<p>' . htmlspecialchars(implode(' · ', $addr)) . '</p>';
        }
        $html .= '</div>
        <div class="doc-title">' . htmlspecialchars($typeLabel) . '</div>
        <p style="font-size:12px;margin:0 0 14px;"><strong>Receipt No:</strong> ' . htmlspecialchars($receiptNo) . ' &nbsp;·&nbsp; <strong>Date:</strong> ' . date('d M Y H:i') . '</p>
        <table>
        <tr><th>Student</th><td>' . htmlspecialchars($student['name']) . '</td></tr>
        <tr><th>Course</th><td>' . htmlspecialchars($student['course_title'] ?? '—') . '</td></tr>
        <tr><th>Duration</th><td>' . htmlspecialchars($student['duration'] ?? '—') . '</td></tr>
        <tr><th>Total course cost</th><td class="amount">KES ' . number_format($cost, 2) . '</td></tr>
        <tr><th>Total paid</th><td class="amount">KES ' . number_format($paid, 2) . '</td></tr>
        <tr><th>Balance</th><td class="amount">KES ' . number_format($balance, 2) . '</td></tr>';
        if ($delta > 0) {
            $html .= '<tr><th>This payment</th><td class="amount">KES ' . number_format($delta, 2) . '</td></tr>';
        }
        $html .= '</table>
        <div class="footer">' . htmlspecialchars($co['footer'] ?? 'Thank you for your payment.') . '</div>
        </div></body></html>';
        return $html;
    }

    private function renderReceiptPdf(array $student, string $receiptNo, string $type, float $delta, float $cost, float $paid, float $balance): string
    {
        $dir = $this->storageDir('receipts');
        $html = $this->receiptHtml($student, $receiptNo, $type, $delta, $cost, $paid, $balance);
        $pdfFilename = $receiptNo . '.pdf';
        $pdfPath = $dir . '/' . $pdfFilename;

        if ($this->writePdfFile($html, $pdfPath)) {
            return 'storage/sms/receipts/' . $pdfFilename;
        }

        $htmlFilename = $receiptNo . '.html';
        file_put_contents($dir . '/' . $htmlFilename, $html);
        return 'storage/sms/receipts/' . $htmlFilename;
    }

    private function writePdfFile(string $html, string $absolutePath): bool
    {
        $autoload = defined('PROJECT_ROOT')
            ? PROJECT_ROOT . '/vendor/autoload.php'
            : dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            return false;
        }
        require_once $autoload;
        if (!class_exists('Dompdf\\Dompdf')) {
            return false;
        }
        try {
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            file_put_contents($absolutePath, $dompdf->output());
            return is_file($absolutePath);
        } catch (Throwable $e) {
            error_log('[SmsModule] PDF render failed: ' . $e->getMessage());
            return false;
        }
    }

    private function storageDir(string $sub): string
    {
        if (!defined('PROJECT_ROOT')) {
            require_once __DIR__ . '/../config/paths.php';
        }
        $dir = PROJECT_ROOT . '/storage/sms/' . $sub;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function provisionStudentAccount(array $student): void
    {
        if (empty($student['email']) || !empty($student['user_id'])) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $student['email']]);
        if ($stmt->fetchColumn()) {
            return;
        }
        $roleId = (int) $this->pdo->query("SELECT id FROM roles WHERE role_name = 'student' LIMIT 1")->fetchColumn();
        if (!$roleId) {
            return;
        }
        $username = 'SMS' . $student['id'] . '-' . substr(md5($student['email']), 0, 6);
        $tempPassword = bin2hex(random_bytes(8));
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
        try {
            $this->pdo->prepare(
                'INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status, must_change_password)
                 VALUES (:u, :e, :h, :r, 1, 1, \'active\', 1)'
            )->execute([':u' => $username, ':e' => $student['email'], ':h' => $hash, ':r' => $roleId]);
        } catch (PDOException $e) {
            $this->pdo->prepare(
                'INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status)
                 VALUES (:u, :e, :h, :r, 1, 1, \'active\')'
            )->execute([':u' => $username, ':e' => $student['email'], ':h' => $hash, ':r' => $roleId]);
        }
        $userId = (int) $this->pdo->lastInsertId();
        $parts = explode(' ', trim($student['name']), 2);
        $this->pdo->prepare('INSERT INTO user_profiles (user_id, first_name, last_name, phone) VALUES (:u,:f,:l,:p)')
            ->execute([':u' => $userId, ':f' => $parts[0], ':l' => $parts[1] ?? '', ':p' => $student['phone'] ?? '']);
        $this->pdo->prepare('UPDATE sms_students SET user_id = :uid WHERE id = :id')->execute([':uid' => $userId, ':id' => $student['id']]);
        $student['username'] = $username;
        $student['temp_password'] = $tempPassword;
        $this->studentCredentials = $student;
    }

    private ?array $studentCredentials = null;

    private function emailStudentOnboarding(array $student, array $receipt): void
    {
        if (!empty($this->studentCredentials['temp_password']) && !empty($student['email'])) {
            $notifier = new StudentNotifier($this->pdo, null, true);
            $reg = [
                'email' => $student['email'],
                'full_name' => $student['name'],
                'registration_no' => $this->studentCredentials['username'] ?? ($student['registration_no'] ?? ('SMS-' . $student['id'])),
                'course_title' => $student['course_title'] ?? '',
            ];
            $result = $notifier->sendCredentials($reg, $this->studentCredentials['temp_password']);
            if (!empty($result['ok'])) {
                $this->log('credentials_emailed', 'Emailed login credentials to ' . $student['email'], 'student', (int) $student['id']);
            } else {
                error_log('[SmsModule] credentials email failed for ' . $student['email'] . ': ' . ($result['message'] ?? 'unknown'));
            }
        }
        $this->emailReceipt($student, $receipt, true);
        if (!empty($student['parent_email'])) {
            $this->emailReceipt($student, $receipt, false, $student['parent_email'], $student['name'] . ' (parent)');
        }
    }

    public function emailReceipt(array $student, array $receipt, bool $isOnboarding = false, ?string $toEmail = null, ?string $toName = null): void
    {
        $email = $toEmail ?: ($student['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $cfg = require __DIR__ . '/../config/mail.php';
        if (empty($cfg['enquiry_mail_enabled']) && empty($cfg['enabled']) && empty($cfg['password_reset_enabled'])) {
            error_log('[SmsModule] mail skipped — SMTP not enabled');
            return;
        }
        $sendCfg = $cfg;
        $sendCfg['enabled'] = true;
        $mailer = new Mailer($sendCfg);
        $subject = ($receipt['receipt_type'] ?? 'receipt') === 'arrears' ? 'Fee arrears notice' : 'Payment receipt';
        $subject .= ' — ' . ($receipt['receipt_no'] ?? '');
        $body = '<p>Dear ' . htmlspecialchars($toName ?: $student['name']) . ',</p><p>Please find your ' . htmlspecialchars($subject) . ' attached.</p>';
        $file = $this->absoluteReceiptPath($receipt['file_path'] ?? '');
        if ($file && is_file($file)) {
            $mailer->sendWithAttachment($email, $toName ?: $student['name'], $subject, $body, $file, basename($file));
        } else {
            $mailer->send($email, $toName ?: $student['name'], $subject, $body);
        }
        $this->log('receipt_emailed', 'Emailed receipt to ' . $email, 'receipt', (int) ($receipt['id'] ?? 0));
    }

    public function whatsappUrl(string $phone, array $student, array $receipt = []): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (isset($digits[0]) && $digits[0] === '0') {
            $digits = '254' . substr($digits, 1);
        }
        $bal = self::balance((float) $student['cost'], (float) $student['paid']);
        $msg = sprintf(
            'Hello, %s. Course: %s. Cost: KES %s. Paid: KES %s. Balance: KES %s.',
            $student['name'],
            $student['course_title'] ?? '—',
            number_format((float) $student['cost'], 2),
            number_format((float) $student['paid'], 2),
            number_format($bal, 2)
        );
        if (!empty($receipt['receipt_no'])) {
            $msg .= ' Receipt: ' . $receipt['receipt_no'];
        }
        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($msg);
    }

    public function absoluteReceiptPath(?string $rel): ?string
    {
        if (!$rel) {
            return null;
        }
        if (!defined('PROJECT_ROOT')) {
            require_once __DIR__ . '/../config/paths.php';
        }
        return PROJECT_ROOT . '/' . ltrim($rel, '/');
    }

    // ─── Prospects ───────────────────────────────────────────────

    public function listProspects(?string $search = null, ?string $status = null): array
    {
        $sql = 'SELECT * FROM sms_prospects WHERE 1=1';
        $params = [];
        if ($status && $status !== 'all') {
            $sql .= ' AND status = :st';
            $params[':st'] = $status;
        }
        if ($search) {
            $sql .= ' AND (name LIKE :q OR phone LIKE :q OR email LIKE :q OR intake_or_course LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY follow_up_date ASC, id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function prospectSummary(): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM sms_prospects')->fetchColumn();
        $open = (int) $this->pdo->query("SELECT COUNT(*) FROM sms_prospects WHERE status = 'open'")->fetchColumn();
        $dueToday = (int) $this->pdo->query("SELECT COUNT(*) FROM sms_prospects WHERE status = 'open' AND follow_up_date = CURDATE()")->fetchColumn();
        $due2 = (int) $this->pdo->query("SELECT COUNT(*) FROM sms_prospects WHERE status = 'open' AND follow_up_date = DATE_ADD(CURDATE(), INTERVAL 2 DAY)")->fetchColumn();
        return compact('total', 'open', 'dueToday', 'due2');
    }

    public function saveProspect(?int $id, array $data): array
    {
        $fields = [
            'name' => trim($data['name'] ?? ''),
            'phone' => trim($data['phone'] ?? '') ?: null,
            'email' => trim($data['email'] ?? '') ?: null,
            'interest_type' => $data['interest_type'] ?? 'course',
            'intake_or_course' => trim($data['intake_or_course'] ?? '') ?: null,
            'follow_up_date' => $data['follow_up_date'] ?: null,
            'status' => $data['status'] ?? 'open',
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];
        if ($fields['name'] === '') {
            return ['ok' => false, 'error' => 'Name is required.'];
        }
        if ($id) {
            $old = $this->getProspect($id);
            $resetReminders = ($old && ($old['follow_up_date'] ?? '') !== ($fields['follow_up_date'] ?? ''));
            $sql = 'UPDATE sms_prospects SET name=:name, phone=:phone, email=:email, interest_type=:it, intake_or_course=:ioc,
                    follow_up_date=:fud, status=:st, notes=:notes';
            if ($resetReminders) {
                $sql .= ', reminder_sent_2day=0, reminder_sent_due=0';
            }
            $sql .= ' WHERE id=:id';
            $fields['id'] = $id;
            $this->pdo->prepare($sql)->execute([
                ':name' => $fields['name'], ':phone' => $fields['phone'], ':email' => $fields['email'],
                ':it' => $fields['interest_type'], ':ioc' => $fields['intake_or_course'], ':fud' => $fields['follow_up_date'],
                ':st' => $fields['status'], ':notes' => $fields['notes'], ':id' => $id,
            ]);
            $this->log('prospect_updated', 'Updated prospect ' . $fields['name'], 'prospect', $id);
            return ['ok' => true, 'id' => $id];
        }
        $this->pdo->prepare(
            'INSERT INTO sms_prospects (name, phone, email, interest_type, intake_or_course, follow_up_date, status, notes)
             VALUES (:name,:phone,:email,:it,:ioc,:fud,:st,:notes)'
        )->execute([
            ':name' => $fields['name'], ':phone' => $fields['phone'], ':email' => $fields['email'],
            ':it' => $fields['interest_type'], ':ioc' => $fields['intake_or_course'], ':fud' => $fields['follow_up_date'],
            ':st' => $fields['status'], ':notes' => $fields['notes'],
        ]);
        $newId = (int) $this->pdo->lastInsertId();
        $this->log('prospect_created', 'Created prospect ' . $fields['name'], 'prospect', $newId);
        if (!empty($fields['email'])) {
            $this->emailProspectWelcome($fields);
        }
        return ['ok' => true, 'id' => $newId];
    }

    public function getProspect(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sms_prospects WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function deleteProspect(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM sms_prospects WHERE id = :id')->execute([':id' => $id]);
    }

    private function emailProspectWelcome(array $p): void
    {
        $cfg = require __DIR__ . '/../config/mail.php';
        $sendCfg = $cfg;
        $sendCfg['enabled'] = true;
        $mailer = new Mailer($sendCfg);
        $body = '<p>Hi ' . htmlspecialchars($p['name']) . ',</p><p>Thank you for your interest in Dazu Hub Institute. We will contact you soon.</p>';
        $mailer->send($p['email'], $p['name'], 'Thanks for contacting Dazu Hub Institute', $body);
    }

    public function runProspectReminders(): array
    {
        $sent = ['today' => 0, 'two_day' => 0];
        $admins = $this->adminEmails();
        if (empty($admins)) {
            return $sent;
        }
        $todayRows = $this->pdo->query("SELECT * FROM sms_prospects WHERE status='open' AND follow_up_date=CURDATE() AND reminder_sent_due=0")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($todayRows as $row) {
            $this->notifyAdmins($admins, 'Prospect follow-up due today: ' . $row['name']);
            $this->pdo->prepare('UPDATE sms_prospects SET reminder_sent_due=1 WHERE id=:id')->execute([':id' => $row['id']]);
            $sent['today']++;
        }
        $twoDay = $this->pdo->query("SELECT * FROM sms_prospects WHERE status='open' AND follow_up_date=DATE_ADD(CURDATE(),INTERVAL 2 DAY) AND reminder_sent_2day=0")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($twoDay as $row) {
            $this->notifyAdmins($admins, 'Prospect follow-up in 2 days: ' . $row['name']);
            $this->pdo->prepare('UPDATE sms_prospects SET reminder_sent_2day=1 WHERE id=:id')->execute([':id' => $row['id']]);
            $sent['two_day']++;
        }
        return $sent;
    }

    private function adminEmails(): array
    {
        require_once __DIR__ . '/EnquiryNotifier.php';
        return EnquiryNotifier::adminRecipients($this->pdo);
    }

    private function notifyAdmins(array $admins, string $subject): void
    {
        $cfg = require __DIR__ . '/../config/mail.php';
        $sendCfg = $cfg;
        $sendCfg['enabled'] = true;
        $mailer = new Mailer($sendCfg);
        foreach ($admins as $a) {
            $mailer->send($a['email'], $a['name'], $subject, '<p>' . htmlspecialchars($subject) . '</p>');
        }
    }

    // ─── Projects ────────────────────────────────────────────────

    public function listProjects(?string $search = null, ?string $status = null): array
    {
        $sql = 'SELECT * FROM sms_projects WHERE 1=1';
        $params = [];
        if ($status && $status !== 'all') {
            $sql .= ' AND status = :st';
            $params[':st'] = $status;
        }
        if ($search) {
            $sql .= ' AND (title LIKE :q OR client_name LIKE :q OR client_company LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY updated_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProject(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sms_projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveProject(?int $id, array $data): array
    {
        $cost = round((float) ($data['estimated_cost'] ?? 0), 2);
        $paid = round((float) ($data['paid_amount'] ?? 0), 2);
        if ($paid > $cost) {
            return ['ok' => false, 'error' => 'Paid cannot exceed estimated cost.'];
        }
        $bind = [
            ':title' => trim($data['title'] ?? ''),
            ':cn' => trim($data['client_name'] ?? '') ?: null,
            ':ce' => trim($data['client_email'] ?? '') ?: null,
            ':cp' => trim($data['client_phone'] ?? '') ?: null,
            ':cc' => trim($data['client_company'] ?? '') ?: null,
            ':sd' => $data['start_date'] ?: null,
            ':dd' => $data['due_date'] ?: null,
            ':cost' => $cost,
            ':paid' => $paid,
            ':st' => $data['status'] ?? 'active',
            ':fud' => $data['follow_up_date'] ?: null,
            ':notes' => trim($data['notes'] ?? '') ?: null,
        ];
        if ($bind[':title'] === '') {
            return ['ok' => false, 'error' => 'Title is required.'];
        }
        if ($id) {
            $bind[':id'] = $id;
            $this->pdo->prepare(
                'UPDATE sms_projects SET title=:title, client_name=:cn, client_email=:ce, client_phone=:cp, client_company=:cc,
                 start_date=:sd, due_date=:dd, estimated_cost=:cost, paid_amount=:paid, status=:st, follow_up_date=:fud, notes=:notes WHERE id=:id'
            )->execute($bind);
            $this->log('project_updated', 'Updated project ' . $bind[':title'], 'project', $id);
            return ['ok' => true, 'id' => $id];
        }
        $this->pdo->prepare(
            'INSERT INTO sms_projects (title, client_name, client_email, client_phone, client_company, start_date, due_date, estimated_cost, paid_amount, status, follow_up_date, notes)
             VALUES (:title,:cn,:ce,:cp,:cc,:sd,:dd,:cost,:paid,:st,:fud,:notes)'
        )->execute($bind);
        $newId = (int) $this->pdo->lastInsertId();
        $this->log('project_created', 'Created project ' . $bind[':title'], 'project', $newId);
        return ['ok' => true, 'id' => $newId];
    }

    public function deleteProject(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM sms_projects WHERE id = :id')->execute([':id' => $id]);
    }

    public function markProjectSale(int $id): bool
    {
        $p = $this->getProject($id);
        if (!$p || $p['is_sale']) {
            return false;
        }
        $this->pdo->prepare('UPDATE sms_projects SET is_sale=1 WHERE id=:id')->execute([':id' => $id]);
        $amt = (float) $p['paid_amount'];
        if ($amt > 0) {
            $this->recordSale('projects', 'Project sale: ' . $p['title'], $amt, null, $id);
        }
        $this->log('project_sale', 'Marked project as sale: ' . $p['title'], 'project', $id);
        return true;
    }

    public function projectMeetings(int $projectId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sms_project_meetings WHERE project_id=:id ORDER BY meeting_date ASC');
        $stmt->execute([':id' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveMeeting(?int $id, int $projectId, array $data): void
    {
        if ($id) {
            $this->pdo->prepare(
                'UPDATE sms_project_meetings SET title=:t, meeting_date=:d, location=:l, notes=:n, status=:s WHERE id=:id AND project_id=:pid'
            )->execute([
                ':t' => $data['title'], ':d' => $data['meeting_date'], ':l' => $data['location'] ?? null,
                ':n' => $data['notes'] ?? null, ':s' => $data['status'] ?? 'scheduled', ':id' => $id, ':pid' => $projectId,
            ]);
        } else {
            $this->pdo->prepare(
                'INSERT INTO sms_project_meetings (project_id, title, meeting_date, location, notes, status)
                 VALUES (:pid,:t,:d,:l,:n,:s)'
            )->execute([
                ':pid' => $projectId, ':t' => $data['title'], ':d' => $data['meeting_date'],
                ':l' => $data['location'] ?? null, ':n' => $data['notes'] ?? null, ':s' => $data['status'] ?? 'scheduled',
            ]);
        }
    }

    public function deleteMeeting(int $id): void
    {
        $this->pdo->prepare('DELETE FROM sms_project_meetings WHERE id=:id')->execute([':id' => $id]);
    }

    public function projectFollowups(int $projectId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sms_project_followups WHERE project_id=:id ORDER BY follow_up_date DESC');
        $stmt->execute([':id' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addFollowup(int $projectId, array $data): void
    {
        $this->pdo->prepare(
            'INSERT INTO sms_project_followups (project_id, follow_up_date, note, followup_type, emailed)
             VALUES (:pid,:d,:n,:t,:e)'
        )->execute([
            ':pid' => $projectId,
            ':d' => $data['follow_up_date'],
            ':n' => $data['note'] ?? null,
            ':t' => $data['followup_type'] ?? 'followup',
            ':e' => !empty($data['emailed']) ? 1 : 0,
        ]);
    }

    public function emailProjectClient(int $projectId, string $subject, string $body): bool
    {
        $p = $this->getProject($projectId);
        if (!$p || empty($p['client_email'])) {
            return false;
        }
        $cfg = require __DIR__ . '/../config/mail.php';
        $sendCfg = $cfg;
        $sendCfg['enabled'] = true;
        $mailer = new Mailer($sendCfg);
        $res = $mailer->send($p['client_email'], $p['client_name'] ?? 'Client', $subject, nl2br(htmlspecialchars($body)));
        if (!empty($res['ok'])) {
            $this->addFollowup($projectId, ['follow_up_date' => date('Y-m-d'), 'note' => $subject . ': ' . $body, 'followup_type' => 'email', 'emailed' => 1]);
            $this->log('project_email', 'Emailed client for project ' . $p['title'], 'project', $projectId);
        }
        return !empty($res['ok']);
    }

    public function runProjectAlerts(): array
    {
        $due = $this->pdo->query(
            "SELECT title, due_date FROM sms_projects WHERE status IN ('active','in_progress')
             AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)"
        )->fetchAll(PDO::FETCH_ASSOC);
        $followups = $this->pdo->query(
            "SELECT p.title, f.follow_up_date FROM sms_project_followups f
             INNER JOIN sms_projects p ON p.id = f.project_id WHERE f.follow_up_date = CURDATE()"
        )->fetchAll(PDO::FETCH_ASSOC);
        $admins = $this->adminEmails();
        foreach ($due as $row) {
            $this->notifyAdmins($admins, 'Project due soon: ' . $row['title'] . ' (' . $row['due_date'] . ')');
        }
        foreach ($followups as $row) {
            $this->notifyAdmins($admins, 'Project follow-up due today: ' . $row['title']);
        }
        return ['due' => count($due), 'followups' => count($followups)];
    }

    // ─── Finance & Sales ─────────────────────────────────────────

    public function financeOverview(): array
    {
        $stu = $this->pdo->query('SELECT COALESCE(SUM(cost),0) c, COALESCE(SUM(paid),0) p FROM sms_students')->fetch(PDO::FETCH_ASSOC);
        $prj = $this->pdo->query('SELECT COALESCE(SUM(estimated_cost),0) c, COALESCE(SUM(paid_amount),0) p FROM sms_projects')->fetch(PDO::FETCH_ASSOC);
        return ['students' => $stu, 'projects' => $prj];
    }

    public function financeStudents(): array
    {
        $rows = $this->pdo->query(
            'SELECT s.id, s.name, s.cost, s.paid, (s.cost - s.paid) AS balance, c.title AS course_title FROM sms_students s
             LEFT JOIN courses c ON c.id = s.course_id ORDER BY s.name'
        )->fetchAll(PDO::FETCH_ASSOC);
        $payments = $this->pdo->query(
            "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM(amount) AS total
             FROM sms_student_payments GROUP BY ym ORDER BY ym DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['rows' => $rows, 'monthly' => $payments];
    }

    public function financeProjects(): array
    {
        return $this->pdo->query(
            'SELECT id, title, estimated_cost AS cost, paid_amount AS paid, (estimated_cost - paid_amount) AS balance, status FROM sms_projects ORDER BY title'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listSales(?string $category = null): array
    {
        $sql = 'SELECT s.*, st.name AS student_name, p.title AS project_title FROM sms_sales s
                LEFT JOIN sms_students st ON st.id = s.student_id
                LEFT JOIN sms_projects p ON p.id = s.project_id WHERE 1=1';
        $params = [];
        if ($category && $category !== 'all') {
            $sql .= ' AND s.category = :cat';
            $params[':cat'] = $category;
        }
        $sql .= ' ORDER BY s.sale_date DESC, s.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salesSummary(): array
    {
        $students = (float) $this->pdo->query("SELECT COALESCE(SUM(amount),0) FROM sms_sales WHERE category='students'")->fetchColumn();
        $projects = (float) $this->pdo->query("SELECT COALESCE(SUM(amount),0) FROM sms_sales WHERE category='projects'")->fetchColumn();
        return ['students' => $students, 'projects' => $projects, 'total' => $students + $projects];
    }

    public function courses(): array
    {
        $cm = new \Models\CourseModel($this->pdo);
        return $cm->getAll(true);
    }
}
