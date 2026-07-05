<?php
// app/controllers/RegistrationController.php
namespace Controllers;

use Models\Connection;
use Models\CourseModel;
use Models\RegistrationModel;
use Models\UserModel;
use Models\SessionModel;

require_once __DIR__ . '/../models/StudentModel.php';
require_once __DIR__ . '/../models/ProgressModel.php';
require_once __DIR__ . '/StudentController.php';

class RegistrationController {

    private $db;
    private $regModel;
    private $courseModel;

    public function __construct() {
        $this->db = Connection::getInstance()->getConnection();
        $this->regModel   = new RegistrationModel($this->db);
        $this->courseModel = new CourseModel($this->db);
    }

    /**
     * Approve a registration:
     * 1. Open a transaction
     * 2. Claim next sequence (SELECT FOR UPDATE) — prevents duplicate IDs under concurrency
     * 3. Generate registration_no: DH-{ABBR}{SEQ}/{YEAR}
     * 4. Create the user account (role: student, status: active, is_active: 1)
     * 5. Create user_profile row
     * 6. Link user_id back on registrations row, set status=approved, store reg no
     * 7. Commit — if anything fails, rollback and return error
     *
     * @return array{ok:bool, error?:string, reg_no?:string, user_id?:int}
     */
    public function approve(int $registrationId, bool $waiveRegFee, int $reviewedBy): array {
        $reg = $this->regModel->getById($registrationId);
        if (!$reg) return ['ok' => false, 'error' => 'Registration not found.'];
        if ($reg['status'] !== 'pending') return ['ok' => false, 'error' => 'This registration has already been reviewed.'];

        $pdo = $this->db;
        $adminId = resolve_valid_user_id($pdo, $reviewedBy, true);
        if ($adminId === null) {
            return ['ok' => false, 'error' => 'No valid admin account found. Log out, log in again, then retry approval.'];
        }
        if ($adminId !== $reviewedBy) {
            error_log("[RegistrationController::approve] Session user_id {$reviewedBy} invalid; using user_id {$adminId}");
        }

        // Check email not already a user (race-safe — the UNIQUE constraint on users.email will catch it too)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $reg['email']]);
        if ($stmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'An account with this email already exists. Check if this is truly a new student.'];
        }

        try {
            $pdo->beginTransaction();

            // Step 1: claim sequence atomically
            $seq = $this->courseModel->claimNextSequence((int) $reg['course_id']);
            $year = date('Y');
            $regNo = sprintf('DH-%s%02d/%s', $reg['abbreviation'], $seq, $year);

            // Step 2: create the user account — temporary password, student will be prompted to reset
            $tempPassword = bin2hex(random_bytes(8)); // 16-char hex; admin shares manually
            $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

            // role_id for 'student' — inserted in migration 015
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'student' LIMIT 1");
            $stmt->execute();
            $studentRoleId = (int) $stmt->fetchColumn();
            if (!$studentRoleId) throw new \RuntimeException("'student' role not found. Did migration 015 run?");

            $stmt = $pdo->prepare(
                "INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status)
                 VALUES (:username, :email, :hash, :role_id, 1, 1, 'active')"
            );
            // Username from registration_no keeps it unique and readable
            $stmt->execute([
                ':username' => $regNo,
                ':email'    => $reg['email'],
                ':hash'     => $hash,
                ':role_id'  => $studentRoleId,
            ]);
            $userId = (int) $pdo->lastInsertId();

            // Step 3: user_profile
            $nameParts = explode(' ', trim($reg['full_name']), 2);
            $stmt = $pdo->prepare(
                "INSERT INTO user_profiles (user_id, first_name, last_name, phone)
                 VALUES (:uid, :fn, :ln, :phone)"
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':fn'    => $nameParts[0],
                ':ln'    => $nameParts[1] ?? '',
                ':phone' => $reg['phone'],
            ]);

            // Step 4: mark registration approved
            $stmt = $pdo->prepare(
                "UPDATE registrations
                 SET status = 'approved', registration_no = :reg_no, user_id = :uid,
                     registration_fee_waived = :waived, reviewed_by = :admin, reviewed_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                ':reg_no' => $regNo,
                ':uid'    => $userId,
                ':waived' => $waiveRegFee ? 1 : 0,
                ':admin'  => $adminId,
                ':id'     => $registrationId,
            ]);

            $pdo->commit();

            $approvedReg = $this->regModel->getById($registrationId);
            $approvedReg['registration_no'] = $regNo;

            $studentCtrl = new StudentController();
            $paymentResult = $studentCtrl->recordInitialPayment($approvedReg, $adminId);
            if (!empty($paymentResult['ok'])) {
                $this->studentModel()->unlockPortal($registrationId);
            }

            $progressModel = new \Models\ProgressModel($pdo);
            $progressModel->initializeForRegistration($registrationId, (int) $approvedReg['course_id']);

            return [
                'ok'            => true,
                'reg_no'        => $regNo,
                'user_id'       => $userId,
                'temp_password' => $tempPassword,
                'login_email'   => $reg['email'],
            ];

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[RegistrationController::approve] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Approval failed: ' . $e->getMessage()];
        }
    }

    /**
     * Reject a registration — sets status + stores the reason the admin typed.
     * No account is created. The submitted document path is retained for audit.
     */
    public function reject(int $registrationId, string $reason, int $reviewedBy): array {
        $reg = $this->regModel->getById($registrationId);
        if (!$reg) return ['ok' => false, 'error' => 'Registration not found.'];
        if ($reg['status'] !== 'pending') return ['ok' => false, 'error' => 'This registration has already been reviewed.'];
        if (empty(trim($reason))) return ['ok' => false, 'error' => 'A rejection reason is required.'];

        $adminId = resolve_valid_user_id($this->db, $reviewedBy, true);
        if ($adminId === null) {
            return ['ok' => false, 'error' => 'No valid admin account found. Log out, log in again, then retry.'];
        }

        $stmt = $this->db->prepare(
            "UPDATE registrations
             SET status = 'rejected', rejection_reason = :reason, reviewed_by = :admin, reviewed_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([':reason' => trim($reason), ':admin' => $adminId, ':id' => $registrationId]);

        return ['ok' => true];
    }

    /**
     * Delete a registration (any status). Removes the student login account if one was created.
     */
    public function delete(int $registrationId): array {
        return $this->regModel->delete($registrationId);
    }

    private function studentModel(): \Models\StudentModel {
        require_once __DIR__ . '/../models/StudentModel.php';
        return new \Models\StudentModel($this->db);
    }
}