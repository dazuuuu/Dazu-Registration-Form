<?php
// app/v1/services/ManualIdService.php — ID cards typed in by an admin (name, role, photo) instead of taken
// from a registration. Same card design as student/staff cards (helpers/id_card_pdf.php); table: manual_id_cards (migration 071).
require_once __DIR__ . '/IdCardService.php';

const MIDC_ROLES = [
    'student'       => ['Student', 'STU'],
    'tutor'         => ['Tutor', 'TUT'],
    'administrator' => ['Administrator', 'ADM'],
    'director'      => ['Director', 'DIR'],
    'employee'      => ['Employee', 'EMP'],
    'attachee'      => ['Intern', 'INT'],
];

if (!function_exists('midc_create')) {
    /** $in: full_name, role, id_no (optional), title, valid_until. $photo: the $_FILES entry. */
    function midc_create(PDO $db, array $in, array $photo, int $createdBy): array
    {
        $name = trim((string) ($in['full_name'] ?? ''));
        $role = (string) ($in['role'] ?? '');
        $title = trim((string) ($in['title'] ?? ''));
        $idNo = strtoupper(trim((string) ($in['id_no'] ?? '')));
        $valid = (int) ($in['valid_until'] ?? 0) ?: (int) date('Y') + 1;

        if ($name === '' || mb_strlen($name) > 150) return ['ok' => false, 'error' => 'Enter the full name (up to 150 characters).'];
        if (!isset(MIDC_ROLES[$role])) return ['ok' => false, 'error' => 'Choose a role.'];
        if ($role === 'student' && $title === '') return ['ok' => false, 'error' => 'Enter the course for a student card.'];
        if ($valid < (int) date('Y') || $valid > 2100) return ['ok' => false, 'error' => 'Valid-until year must be this year or later.'];
        if ($idNo !== '' && !preg_match('#^[A-Z0-9/\-]{2,50}$#', $idNo)) return ['ok' => false, 'error' => 'The ID number may only use letters, numbers, / and -.'];
        if ($idNo !== '') {
            $st = $db->prepare('SELECT 1 FROM manual_id_cards WHERE id_no = ?');
            $st->execute([$idNo]);
            if ($st->fetchColumn()) return ['ok' => false, 'error' => 'ID number ' . $idNo . ' is already on another card.'];
        }
        if (empty($photo['tmp_name'])) return ['ok' => false, 'error' => 'Upload a passport photo.'];

        $up = upload_image($photo, dirname(__DIR__, 3) . '/public/uploads/id_photos', 'uploads/id_photos', ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
        if (!$up['ok']) return ['ok' => false, 'error' => $up['error'] ?? 'Photo upload failed.'];

        $db->prepare('INSERT INTO manual_id_cards (full_name, role, id_no, title, photo_path, valid_until, qr_hash, created_by)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$name, $role, $idNo !== '' ? $idNo : 'PENDING-' . bin2hex(random_bytes(6)), $title ?: null,
                      ltrim((string) $up['path'], '/'), $valid, bin2hex(random_bytes(16)), $createdBy ?: null]);
        $id = (int) $db->lastInsertId();
        if ($idNo === '') {
            // Auto number from the row id, e.g. DTI-DIR-0007.
            $db->prepare('UPDATE manual_id_cards SET id_no = ? WHERE id = ?')
               ->execute(['DTI-' . MIDC_ROLES[$role][1] . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);
        }
        return ['ok' => true, 'id' => $id];
    }

    function midc_get(PDO $db, int $id): ?array
    {
        $st = $db->prepare('SELECT * FROM manual_id_cards WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    function midc_list(PDO $db): array
    {
        return $db->query('SELECT * FROM manual_id_cards ORDER BY created_at DESC, id DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
    }

    function midc_delete(PDO $db, int $id): void
    {
        $card = midc_get($db, $id);
        if (!$card) return;
        $db->prepare('DELETE FROM manual_id_cards WHERE id = ?')->execute([$id]);
        $abs = dirname(__DIR__, 3) . '/public/' . $card['photo_path'];
        if (strpos($card['photo_path'], 'uploads/id_photos/') === 0 && is_file($abs)) @unlink($abs);
    }

    function midc_photo_file(array $row): ?string
    {
        $abs = dirname(__DIR__, 3) . '/public/' . ltrim((string) $row['photo_path'], '/');
        return is_file($abs) ? $abs : null;
    }

    /** The array helpers/id_card_pdf.php renders. Students get the student wording, everyone else the staff wording. */
    function midc_card(array $row): array
    {
        $roleLabel = MIDC_ROLES[$row['role']][0] ?? 'Staff';
        $student = $row['role'] === 'student';
        return [
            '_kind' => $student ? 'student' : 'staff',
            '_heading' => $student ? 'STUDENT ID' : ($row['role'] === 'attachee' ? 'INTERN ID' : 'STAFF ID'),
            'snapshot_name' => $row['full_name'],
            'snapshot_course' => $student ? (string) $row['title'] : $roleLabel . ($row['title'] ? ' — ' . $row['title'] : ''),
            'snapshot_reg_no' => $row['id_no'],
            'valid_until' => (int) $row['valid_until'],
            'qr_hash' => $row['qr_hash'],
            '_photo_file' => midc_photo_file($row),
            '_signature_file' => null,
        ];
    }
}
