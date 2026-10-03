<?php
// Sequential student IDs: DCH050/26 (certificate) or DDH051/26 (diploma); one running sequence, year is two digits.
require_once __DIR__ . '/course_duration.php';

if (!function_exists('registration_number_lock')) {
    function registration_number_lock(\PDO $pdo): bool
    {
        try {
            return (int) $pdo->query("SELECT GET_LOCK('dazu_registration_no', 15)")->fetchColumn() === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('registration_number_unlock')) {
    function registration_number_unlock(\PDO $pdo): void
    {
        try {
            $pdo->query("SELECT RELEASE_LOCK('dazu_registration_no')");
        } catch (\Throwable $e) {
            // released when the connection closes
        }
    }
}

if (!function_exists('registration_number_next')) {
    /**
     * Next institute registration number. First number of each calendar year is 050. The prefix follows the course.
     * Callers must hold registration_number_lock() until the number is saved.
     */
    function registration_number_next(\PDO $pdo, ?int $courseId = null): string
    {
        $prefix = 'DH';
        if ($courseId) {
            $st = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
            $st->execute([$courseId]);
            if ($course = $st->fetch()) {
                $prefix = course_qualification($course) === 'diploma' ? 'DD' . strtoupper($course['abbreviation']) : 'DC' . strtoupper($course['abbreviation']);
            }
        }
        $year = date('y');
        $max = 49;
        $stmt = $pdo->query(
            "SELECT registration_no FROM registrations
             WHERE registration_no IS NOT NULL AND registration_no != ''"
        );
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $no) {
            if (preg_match('/^[A-Z]+(\d+)\/(\d{2})$/', (string) $no, $m) && $m[2] === $year) {
                $max = max($max, (int) $m[1]);
            }
        }
        return sprintf('%s%03d/%s', $prefix, max(50, $max + 1), $year);
    }
}

if (!function_exists('registration_number_claim')) {
    /** Lock, allocate, and leave the lock held. Pair with registration_number_unlock(). */
    function registration_number_claim(\PDO $pdo, ?int $courseId = null): string
    {
        registration_number_lock($pdo);
        return registration_number_next($pdo, $courseId);
    }
}


if (!function_exists('registration_number_resync')) {
    /**
     * Make the letter in registration numbers match the course: DCH (certificate) or DDH (diploma). Limited to one course or
     * one registration when given, otherwise every registration. The sign-in username follows the number. Returns how many changed.
     */
    function registration_number_resync(\PDO $pdo, ?int $courseId = null, ?int $registrationId = null): int
    {
        $sql = "SELECT r.id AS reg_id, r.user_id AS reg_user_id, r.registration_no AS reg_no, c.* FROM registrations r JOIN courses c ON c.id = r.course_id
                WHERE r.registration_no REGEXP '^[A-Z]+[0-9]+/[0-9]{2}$'";
        $args = [];
        if ($courseId !== null) { $sql .= ' AND r.course_id = ?'; $args[] = $courseId; }
        if ($registrationId !== null) { $sql .= ' AND r.id = ?'; $args[] = $registrationId; }
        $st = $pdo->prepare($sql);
        $st->execute($args);
        $changed = 0;
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $old = (string) $row['reg_no'];
            if (preg_match('/^[A-Z]+(\d+\/\d{2})$/', $old, $m)) {
                $new = (course_qualification($row) === 'diploma' ? 'DD' : 'DC') . strtoupper($row['abbreviation']) . $m[1];
            } else {
                continue;
            }
            if ($new === $old) {
                continue;
            }
            try {
                $pdo->prepare('UPDATE registrations SET registration_no = ? WHERE id = ?')->execute([$new, $row['reg_id']]);
                if (!empty($row['reg_user_id'])) {
                    $pdo->prepare('UPDATE users SET username = ? WHERE id = ? AND username = ?')->execute([$new, $row['reg_user_id'], $old]);
                }
                $changed++;
            } catch (\Throwable $e) {
                error_log('[registration_number_resync] ' . $e->getMessage());
            }
        }
        return $changed;
    }
}
