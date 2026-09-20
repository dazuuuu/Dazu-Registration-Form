<?php
// app/v1/models/EmployeeModel.php
// Non-tutor staff: their account, profile, fixed monthly salary and the payments made to them.
// The arithmetic lives in PayrollCalculator::employeeLedger.
namespace Models;

require_once __DIR__ . '/PayrollCalculator.php';

class EmployeeModel
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    /** False until migration 048 has been applied, so pages can say so instead of crashing. */
    public function isInstalled(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM employees LIMIT 1');
            $this->db->query('SELECT 1 FROM employee_payments LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private const SELECT = "SELECT e.*, u.username, u.email, u.is_active AS user_active, up.first_name, up.last_name,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', up.first_name, up.last_name)), ''), u.username) AS full_name
             FROM employees e
             JOIN users u ON u.id = e.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id";

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . " WHERE e.id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . " WHERE e.user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Every employee with earned / paid / balance, for the admin salary sheet. */
    public function listWithBalances(): array
    {
        $rows = $this->db->query(self::SELECT . " ORDER BY full_name")->fetchAll();
        foreach ($rows as &$r) {
            $l = $this->getLedger($r);
            $r['earned_to_date'] = $l['earned_to_date'];
            $r['paid_to_date'] = $l['paid_to_date'];
            $r['balance'] = $l['balance'];
            $r['next_pay_date'] = $l['next_pay_date'];
        }
        return $rows;
    }

    private function roleId(): int
    {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE role_name = 'employee' LIMIT 1");
        $stmt->execute();
        $id = (int) $stmt->fetchColumn();
        if (!$id) {
            throw new \RuntimeException("'employee' role not found. Apply migration 048.");
        }
        return $id;
    }

    /** Validates the fields an admin sets. @return array{ok:bool, error?:string, clean?:array} */
    private function validate(array $d): array
    {
        $title = trim((string) ($d['job_title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 100) {
            return ['ok' => false, 'error' => 'Enter the employee\'s role (up to 100 characters).'];
        }
        $salary = $d['monthly_salary'] ?? '';
        if (!is_numeric($salary) || (float) $salary < 0 || (float) $salary > 100000000) {
            return ['ok' => false, 'error' => 'Enter a valid monthly salary.'];
        }
        $payDay = (int) ($d['pay_day'] ?? 0);
        if ($payDay < 1 || $payDay > 31) {
            return ['ok' => false, 'error' => 'Pay day must be a day of the month from 1 to 31.'];
        }
        $isDate = function ($v): bool {
            $x = is_string($v) ? \DateTime::createFromFormat('Y-m-d', $v) : false;
            return $x && $x->format('Y-m-d') === $v;
        };
        $start = (string) ($d['start_date'] ?? '');
        if (!$isDate($start)) {
            return ['ok' => false, 'error' => 'Choose the date the employee starts.'];
        }
        $end = trim((string) ($d['end_date'] ?? ''));
        if ($end !== '' && (!$isDate($end) || $end < $start)) {
            return ['ok' => false, 'error' => 'The last day must be a valid date on or after the start date.'];
        }
        return ['ok' => true, 'clean' => [
            'job_title' => $title, 'monthly_salary' => round((float) $salary, 2), 'pay_day' => $payDay,
            'start_date' => $start, 'end_date' => $end === '' ? null : $end,
            'phone' => mb_substr(trim((string) ($d['phone'] ?? '')), 0, 30),
        ]];
    }

    /** @return array{ok:bool, error?:string, employee_id?:int, user_id?:int, temp_password?:string, username?:string} */
    public function create(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $fullName = trim((string) ($data['full_name'] ?? ''));
        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Enter the employee\'s name and a valid email address.'];
        }
        $v = $this->validate($data);
        if (!$v['ok']) {
            return $v;
        }
        $c = $v['clean'];

        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'A user with this email already exists.'];
        }

        $parts = explode(' ', $fullName, 2);
        $base = substr(preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0])), 0, 20) ?: 'staff';
        $username = 'EMP-' . $base;
        for ($n = 1; $this->usernameExists($username); $n++) {
            $username = 'EMP-' . $base . $n;
        }
        $tempPassword = bin2hex(random_bytes(8));

        try {
            $roleId = $this->roleId();
            $this->db->beginTransaction();
            $this->db->prepare(
                "INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status)
                 VALUES (?, ?, ?, ?, 1, 1, 'active')"
            )->execute([$username, $email, password_hash($tempPassword, PASSWORD_DEFAULT), $roleId]);
            $userId = (int) $this->db->lastInsertId();
            $this->db->prepare("UPDATE users SET must_change_password = 1 WHERE id = ?")->execute([$userId]);

            $this->db->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)")
                ->execute([$userId, $parts[0], $parts[1] ?? '', $c['phone']]);
            $this->db->prepare(
                "INSERT INTO employees (user_id, job_title, monthly_salary, pay_day, start_date, end_date, phone)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([$userId, $c['job_title'], $c['monthly_salary'], $c['pay_day'], $c['start_date'], $c['end_date'], $c['phone']]);
            $employeeId = (int) $this->db->lastInsertId();
            $this->db->commit();
            return ['ok' => true, 'employee_id' => $employeeId, 'user_id' => $userId, 'temp_password' => $tempPassword, 'username' => $username];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('EmployeeModel::create failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not create the employee. ' . $e->getMessage()];
        }
    }

    /** Admin edit: name, role, salary, pay day, dates, active flag. @return array{ok:bool, error?:string} */
    public function update(int $id, array $data): array
    {
        $emp = $this->getById($id);
        if (!$emp) {
            return ['ok' => false, 'error' => 'Employee not found.'];
        }
        $fullName = trim((string) ($data['full_name'] ?? ''));
        if ($fullName === '') {
            return ['ok' => false, 'error' => 'Enter the employee\'s name.'];
        }
        $v = $this->validate($data);
        if (!$v['ok']) {
            return $v;
        }
        $c = $v['clean'];
        $active = !empty($data['is_active']) ? 1 : 0;
        $parts = explode(' ', $fullName, 2);
        try {
            $this->db->beginTransaction();
            $this->db->prepare(
                "UPDATE employees SET job_title = ?, monthly_salary = ?, pay_day = ?, start_date = ?, end_date = ?, phone = ?, is_active = ? WHERE id = ?"
            )->execute([$c['job_title'], $c['monthly_salary'], $c['pay_day'], $c['start_date'], $c['end_date'], $c['phone'], $active, $id]);
            $this->db->prepare("UPDATE user_profiles SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?")
                ->execute([$parts[0], $parts[1] ?? '', $c['phone'], $emp['user_id']]);
            // A deactivated employee can no longer sign in.
            $this->db->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$active, $emp['user_id']]);
            $this->db->commit();
            return ['ok' => true];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** The employee edits their own contact details, bio and photo. */
    public function updateProfile(int $userId, string $phone, string $bio, ?string $photoPath): void
    {
        $phone = mb_substr(trim($phone), 0, 30);
        $sql = "UPDATE employees SET phone = ?, bio = ?" . ($photoPath ? ", photo_path = ?" : "") . " WHERE user_id = ?";
        $params = [$phone, trim($bio)];
        if ($photoPath) {
            $params[] = $photoPath;
        }
        $params[] = $userId;
        $this->db->prepare($sql)->execute($params);
        $this->db->prepare("UPDATE user_profiles SET phone = ? WHERE user_id = ?")->execute([$phone, $userId]);
    }

    public function markCredentialsSent(int $id): void
    {
        $this->db->prepare("UPDATE employees SET credentials_sent_at = NOW() WHERE id = ?")->execute([$id]);
    }

    /** Sets a new temporary password (the employee must change it at next sign-in). Returns it. */
    public function resetPassword(int $id): ?string
    {
        $emp = $this->getById($id);
        if (!$emp) {
            return null;
        }
        $temp = bin2hex(random_bytes(8));
        $this->db->prepare("UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?")
            ->execute([password_hash($temp, PASSWORD_DEFAULT), $emp['user_id']]);
        return $temp;
    }

    private function usernameExists(string $username): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return (bool) $stmt->fetchColumn();
    }

    // -------------------------------------------------------------- payments

    public function getPayments(int $employeeId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM employee_payments WHERE employee_id = ? ORDER BY paid_for_month DESC, id DESC");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    /** @return array{ok:bool, error?:string} */
    public function addPayment(int $employeeId, $amount, string $forMonth, string $paidOn, ?string $note, ?int $adminUserId): array
    {
        if (!is_numeric($amount) || (float) $amount <= 0) {
            return ['ok' => false, 'error' => 'Enter an amount greater than zero.'];
        }
        $amount = round((float) $amount, 2);
        if ($amount > 100000000) {
            return ['ok' => false, 'error' => 'That amount is too large.'];
        }
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $forMonth)) {
            return ['ok' => false, 'error' => 'Choose a valid month.'];
        }
        if ($forMonth > PayrollCalculator::currentMonth()) {
            return ['ok' => false, 'error' => 'A payment cannot be recorded against a future month.'];
        }
        $d = \DateTime::createFromFormat('Y-m-d', $paidOn);
        if (!$d || $d->format('Y-m-d') !== $paidOn) {
            return ['ok' => false, 'error' => 'Choose a valid payment date.'];
        }
        $note = $note !== null ? trim($note) : null;
        if ($note !== null && mb_strlen($note) > 255) {
            return ['ok' => false, 'error' => 'The note is too long (255 characters maximum).'];
        }
        if (!$this->getById($employeeId)) {
            return ['ok' => false, 'error' => 'Employee not found.'];
        }
        $this->db->prepare(
            "INSERT INTO employee_payments (employee_id, amount, paid_for_month, paid_on, note, recorded_by) VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$employeeId, $amount, $forMonth . '-01', $paidOn, ($note === '' ? null : $note), $adminUserId ?: null]);
        return ['ok' => true];
    }

    public function deletePayment(int $paymentId, int $employeeId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM employee_payments WHERE id = ? AND employee_id = ?");
        $stmt->execute([$paymentId, $employeeId]);
        return $stmt->rowCount() > 0;
    }

    /** Earned / paid / balance for one employee row (from getById or listWithBalances), plus payments. */
    public function getLedger(array $emp): array
    {
        $payments = $this->getPayments((int) $emp['id']);
        $ledger = PayrollCalculator::employeeLedger(
            (float) $emp['monthly_salary'], (string) $emp['start_date'], $emp['end_date'], (int) $emp['pay_day'],
            array_map(fn($p) => ['amount' => $p['amount'], 'month' => $p['paid_for_month']], $payments)
        );
        $ledger['payments'] = $payments;
        return $ledger;
    }
}
