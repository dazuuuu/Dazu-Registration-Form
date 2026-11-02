<?php
// Sequential student IDs: DH050/26, DH051/26, … (year is two digits).

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
     * Next institute registration number. First number of each calendar year is DH050/{yy}.
     * Callers must hold registration_number_lock() until the number is saved.
     */
    function registration_number_next(\PDO $pdo): string
    {
        $year = date('y');
        $max = 49;
        $stmt = $pdo->query(
            "SELECT registration_no FROM registrations
             WHERE registration_no IS NOT NULL AND registration_no != ''"
        );
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $no) {
            if (preg_match('/^DH(\d+)\/(\d{2})$/', (string) $no, $m) && $m[2] === $year) {
                $max = max($max, (int) $m[1]);
            }
        }
        return sprintf('DH%03d/%s', max(50, $max + 1), $year);
    }
}

if (!function_exists('registration_number_claim')) {
    /** Lock, allocate, and leave the lock held. Pair with registration_number_unlock(). */
    function registration_number_claim(\PDO $pdo): string
    {
        registration_number_lock($pdo);
        return registration_number_next($pdo);
    }
}
