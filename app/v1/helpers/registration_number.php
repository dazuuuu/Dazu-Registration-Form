<?php
// Registration number format: DH/{ABBR}-0050/27 (sequence from 50, 2-digit year).

if (!function_exists('registration_number_format')) {
    function registration_number_format(string $abbreviation, int $sequence, ?int $year = null): string
    {
        $abbr = strtoupper(trim($abbreviation));
        $year = $year ?? (int) date('Y');
        $yy = substr((string) $year, -2);
        return sprintf('DH/%s-%04d/%s', $abbr, max(50, $sequence), $yy);
    }
}

if (!function_exists('registration_number_claim')) {
    /**
     * Atomically claim the next enrollment sequence for a course (minimum 50).
     */
    function registration_number_claim(\PDO $pdo, int $courseId): int
    {
        $pdo->prepare('SELECT last_sequence FROM courses WHERE id = :id FOR UPDATE')
            ->execute([':id' => $courseId]);

        $current = (int) $pdo->query(
            'SELECT last_sequence FROM courses WHERE id = ' . (int) $courseId
        )->fetchColumn();

        if ($current < 49) {
            $pdo->prepare('UPDATE courses SET last_sequence = 49 WHERE id = :id AND last_sequence < 49')
                ->execute([':id' => $courseId]);
        }

        $pdo->prepare('UPDATE courses SET last_sequence = last_sequence + 1 WHERE id = :id')
            ->execute([':id' => $courseId]);

        $seq = (int) $pdo->query(
            'SELECT last_sequence FROM courses WHERE id = ' . (int) $courseId
        )->fetchColumn();

        return max(50, $seq);
    }
}
