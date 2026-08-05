<?php
// app/v1/models/DashboardModel.php
namespace Models;

class DashboardModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    private function tableExists(string $table): bool {
        try {
            $this->db->query("SELECT 1 FROM `$table` LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function adminStats(): array {
        $stats = [
            'students'      => 0,
            'tutors'        => 0,
            'courses'       => 0,
            'pending_regs'  => 0,
            'pending_pay'   => 0,
            'total_collected' => 0,
            'total_outstanding' => 0,
            'registrations_by_status' => ['pending' => 0, 'approved' => 0, 'rejected' => 0],
            'students_by_course' => [],
            'monthly_revenue' => [],
            'portal_locked' => 0,
            'new_enquiries' => 0,
            'total_enquiries' => 0,
            'recent_enquiries' => [],
            'recent_registrations' => [],
            'recent_payments' => [],
            'payment_statuses' => ['approved' => 0, 'pending' => 0, 'rejected' => 0],
            'monthly_registrations' => [],
            'month_revenue' => 0,
            'previous_month_revenue' => 0,
            'expected_fees' => 0,
            'collection_rate' => 0,
        ];

        if ($this->tableExists('registrations')) {
            $stats['students'] = (int) $this->db->query("SELECT COUNT(*) FROM registrations WHERE status='approved'")->fetchColumn();
            $stats['pending_regs'] = (int) $this->db->query("SELECT COUNT(*) FROM registrations WHERE status='pending'")->fetchColumn();
            foreach (['pending','approved','rejected'] as $s) {
                $st = $this->db->prepare("SELECT COUNT(*) FROM registrations WHERE status=?");
                $st->execute([$s]);
                $stats['registrations_by_status'][$s] = (int) $st->fetchColumn();
            }
            if ($this->columnExists('registrations', 'portal_locked')) {
                $stats['portal_locked'] = (int) $this->db->query("SELECT COUNT(*) FROM registrations WHERE portal_locked=1 AND status='approved'")->fetchColumn();
            }
            $stats['students_by_course'] = $this->db->query(
                "SELECT c.title, COUNT(r.id) AS cnt FROM courses c
                 LEFT JOIN registrations r ON r.course_id=c.id AND r.status='approved'
                 GROUP BY c.id ORDER BY cnt DESC LIMIT 8"
            )->fetchAll();
            $stats['total_outstanding'] = $this->computeOutstanding();
            $stats['expected_fees'] = $stats['total_collected'] + $stats['total_outstanding'];
            $stats['recent_registrations'] = $this->db->query(
                "SELECT r.id, r.full_name, r.status, r.created_at, c.title AS course_title
                 FROM registrations r LEFT JOIN courses c ON c.id = r.course_id
                 ORDER BY r.created_at DESC LIMIT 6"
            )->fetchAll();
            $stats['monthly_registrations'] = array_reverse($this->db->query(
                "SELECT DATE_FORMAT(created_at, '%b') AS label, COUNT(*) AS total
                 FROM registrations
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b')
                 ORDER BY DATE_FORMAT(created_at, '%Y-%m') DESC"
            )->fetchAll());
        }

        if ($this->tableExists('tutors')) {
            $stats['tutors'] = (int) $this->db->query("SELECT COUNT(*) FROM tutors WHERE is_active=1")->fetchColumn();
        }

        if ($this->tableExists('courses')) {
            $stats['courses'] = (int) $this->db->query("SELECT COUNT(*) FROM courses WHERE is_active=1")->fetchColumn();
        }

        if ($this->tableExists('payments')) {
            $stats['pending_pay'] = (int) $this->db->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
            $stats['total_collected'] = (float) $this->db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='approved'")->fetchColumn();
            $stats['monthly_revenue'] = $this->db->query(
                "SELECT DATE_FORMAT(COALESCE(reviewed_at, paid_at), '%Y-%m') AS ym, SUM(amount) AS total
                 FROM payments WHERE status='approved'
                 GROUP BY ym ORDER BY ym DESC LIMIT 6"
            )->fetchAll();
            $stats['monthly_revenue'] = array_reverse($stats['monthly_revenue']);
            foreach (array_keys($stats['payment_statuses']) as $status) {
                $st = $this->db->prepare('SELECT COUNT(*) FROM payments WHERE status = ?');
                $st->execute([$status]);
                $stats['payment_statuses'][$status] = (int) $st->fetchColumn();
            }
            $stats['month_revenue'] = (float) $this->db->query(
                "SELECT COALESCE(SUM(amount), 0) FROM payments
                 WHERE status = 'approved' AND COALESCE(reviewed_at, paid_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
            )->fetchColumn();
            $stats['previous_month_revenue'] = (float) $this->db->query(
                "SELECT COALESCE(SUM(amount), 0) FROM payments
                 WHERE status = 'approved'
                   AND COALESCE(reviewed_at, paid_at) >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
                   AND COALESCE(reviewed_at, paid_at) < DATE_FORMAT(CURDATE(), '%Y-%m-01')"
            )->fetchColumn();
            $stats['recent_payments'] = $this->db->query(
                "SELECT p.amount, p.status, COALESCE(p.reviewed_at, p.paid_at) AS payment_date,
                        r.full_name, r.registration_no
                 FROM payments p LEFT JOIN registrations r ON r.id = p.registration_id
                 ORDER BY COALESCE(p.reviewed_at, p.paid_at) DESC LIMIT 6"
            )->fetchAll();
        }

        $stats['expected_fees'] = $stats['total_collected'] + $stats['total_outstanding'];
        if ($stats['expected_fees'] > 0) {
            $stats['collection_rate'] = round(($stats['total_collected'] / $stats['expected_fees']) * 100, 1);
        }

        if ($this->tableExists('enquiries')) {
            $stats['new_enquiries'] = (int) $this->db->query("SELECT COUNT(*) FROM enquiries WHERE status = 'new'")->fetchColumn();
            $stats['total_enquiries'] = (int) $this->db->query('SELECT COUNT(*) FROM enquiries')->fetchColumn();
            $stats['recent_enquiries'] = $this->db->query(
                "SELECT id, name, service, status, created_at FROM enquiries ORDER BY created_at DESC LIMIT 6"
            )->fetchAll();
        }

        return $stats;
    }

    private function computeOutstanding(): float {
        $rows = $this->db->query(
            "SELECT r.id, r.registration_fee, r.registration_fee_waived, r.course_fee FROM registrations r WHERE r.status='approved'"
        )->fetchAll();
        $total = 0;
        foreach ($rows as $r) {
            $owed = (float)$r['course_fee'] + ($r['registration_fee_waived'] ? 0 : (float)$r['registration_fee']);
            $paid = 0;
            if ($this->tableExists('payments')) {
                $st = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE registration_id=? AND status='approved'");
                $st->execute([$r['id']]);
                $paid = (float) $st->fetchColumn();
            }
            $total += max(0, $owed - $paid);
        }
        return $total;
    }

    public function tutorStats(int $tutorId): array {
        $stats = ['students' => 0, 'courses' => 0, 'pending' => 0, 'students_progress' => []];
        if (!$this->tableExists('course_tutors')) return $stats;

        $st = $this->db->prepare("SELECT COUNT(*) FROM course_tutors WHERE tutor_id=?");
        $st->execute([$tutorId]);
        $stats['courses'] = (int) $st->fetchColumn();

        $students = $this->db->prepare(
            "SELECT r.full_name, r.registration_no, c.title AS course_title,
                    (SELECT COUNT(*) FROM student_unit_progress sup
                     JOIN curriculum_units cu ON cu.id=sup.unit_id
                     WHERE sup.registration_id=r.id AND sup.completed_at IS NOT NULL) AS done,
                    (SELECT COUNT(*) FROM curriculum_units cu2 WHERE cu2.course_id=r.course_id AND cu2.is_active=1) AS total
             FROM registrations r JOIN course_tutors ct ON ct.course_id=r.course_id
             JOIN courses c ON c.id=r.course_id
             WHERE ct.tutor_id=? AND r.status='approved' LIMIT 20"
        );
        try {
            $students->execute([$tutorId]);
            $stats['students_progress'] = $students->fetchAll();
            $stats['students'] = count($stats['students_progress']);
        } catch (\Throwable $e) { /* tables may not exist */ }

        if ($this->tableExists('student_submissions')) {
            $st = $this->db->prepare(
                "SELECT COUNT(*) FROM student_submissions s
                 JOIN registrations r ON r.id=s.registration_id
                 JOIN course_tutors ct ON ct.course_id=r.course_id
                 WHERE ct.tutor_id=? AND s.status='submitted'"
            );
            $st->execute([$tutorId]);
            $stats['pending'] = (int) $st->fetchColumn();
        }
        return $stats;
    }

    public function studentStats(int $registrationId, int $courseId): array {
        $stats = ['units_done' => 0, 'units_total' => 0, 'progress_pct' => 0, 'submissions' => []];
        if ($this->tableExists('curriculum_units')) {
            $stats['units_total'] = (int) $this->db->prepare("SELECT COUNT(*) FROM curriculum_units WHERE course_id=? AND is_active=1")->execute([$courseId]) ? 0 : 0;
            $st = $this->db->prepare("SELECT COUNT(*) FROM curriculum_units WHERE course_id=? AND is_active=1");
            $st->execute([$courseId]);
            $stats['units_total'] = (int) $st->fetchColumn();
        }
        if ($this->tableExists('student_unit_progress')) {
            $st = $this->db->prepare("SELECT COUNT(*) FROM student_unit_progress WHERE registration_id=? AND completed_at IS NOT NULL");
            $st->execute([$registrationId]);
            $stats['units_done'] = (int) $st->fetchColumn();
        }
        if ($stats['units_total'] > 0) {
            $stats['progress_pct'] = round(100 * $stats['units_done'] / $stats['units_total']);
        }
        return $stats;
    }

    private function columnExists(string $table, string $col): bool {
        try {
            $this->db->query("SELECT `$col` FROM `$table` LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
