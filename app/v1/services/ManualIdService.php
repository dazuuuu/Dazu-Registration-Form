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

if (!function_exists('midc_people_search')) {
    /**
     * People already in the system whose name (or reg/staff no, email) matches: students, tutors, interns, staff,
     * admins and directors. Each row carries everything the manual card needs, so the form only asks for a photo.
     * Rows: key ('student:<registration id>', 'user:<users.id>', 'director:<id>'), full_name, role, title, id_no, has_photo, label.
     */
    function midc_people_search(PDO $db, string $q, int $limit = 12): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        $like = '%' . $q . '%';
        $name = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''),' ',COALESCE(up.last_name,''))),''), u.username)";
        $out = [];
        $add = static function (array $rows) use (&$out) { foreach ($rows as $r) $out[] = $r; };
        $run = static function (string $sql, array $args) use ($db): array {
            try { $st = $db->prepare($sql); $st->execute($args); return $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return []; }
        };
        $add($run("SELECT CONCAT('student:', r.id) AS `key`, r.full_name, 'student' AS role, c.title, r.registration_no AS id_no
                   FROM registrations r JOIN courses c ON c.id = r.course_id
                   WHERE r.status = 'approved' AND (r.full_name LIKE ? OR r.registration_no LIKE ? OR r.email LIKE ?)
                   ORDER BY r.id DESC LIMIT $limit", [$like, $like, $like]));
        $add($run("SELECT CONCAT('user:', u.id) AS `key`, $name AS full_name,
                          CASE ro.role_name WHEN 'tutor' THEN 'tutor' WHEN 'intern' THEN 'attachee' WHEN 'employee' THEN 'employee' ELSE 'administrator' END AS role,
                          CASE ro.role_name WHEN 'tutor' THEN 'Tutor' WHEN 'intern' THEN COALESCE(d.name, 'Intern') WHEN 'employee' THEN e.job_title ELSE 'Administration' END AS title,
                          CASE ro.role_name WHEN 'tutor' THEN t.staff_no WHEN 'intern' THEN i.student_id_no ELSE NULL END AS id_no
                   FROM users u JOIN roles ro ON ro.id = u.role_id LEFT JOIN user_profiles up ON up.user_id = u.id
                   LEFT JOIN tutors t ON t.user_id = u.id LEFT JOIN interns i ON i.user_id = u.id LEFT JOIN intern_departments d ON d.id = i.department_id
                   LEFT JOIN employees e ON e.user_id = u.id
                   WHERE ro.role_name IN ('tutor','intern','employee','admin') AND u.is_active = 1
                     AND ($name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR t.staff_no LIKE ?)
                   ORDER BY u.id DESC LIMIT $limit", [$like, $like, $like, $like]));
        $add($run("SELECT CONCAT('director:', id) AS `key`, full_name, 'director' AS role, title, NULL AS id_no
                   FROM directors WHERE is_active = 1 AND full_name LIKE ? LIMIT $limit", [$like]));
        foreach ($out as &$r) {
            $r['has_photo'] = midc_person_photo($db, (string) $r['key']) !== null;
            $r['label'] = MIDC_ROLES[$r['role']][0] ?? ucfirst((string) $r['role']);
        }
        return array_slice($out, 0, 25);
    }

    /** Re-read one person by key (never trust the browser's copy). */
    function midc_person(PDO $db, string $key): ?array
    {
        foreach (midc_people_search_by_key($db, $key) as $r) return $r;
        return null;
    }

    function midc_people_search_by_key(PDO $db, string $key): array
    {
        if (!preg_match('/^(student|user|director):(\d+)$/', $key, $m)) return [];
        $id = (int) $m[2];
        if ($m[1] === 'student') {
            $st = $db->prepare('SELECT full_name FROM registrations WHERE id = ?');
        } elseif ($m[1] === 'director') {
            $st = $db->prepare('SELECT full_name FROM directors WHERE id = ?');
        } else {
            $st = $db->prepare("SELECT COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''),' ',COALESCE(up.last_name,''))),''), u.username) FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ?");
        }
        $st->execute([$id]);
        $nm = (string) $st->fetchColumn();
        return $nm === '' ? [] : array_values(array_filter(midc_people_search($db, $nm, 50), fn($r) => $r['key'] === $key));
    }

    /** Relative path of a photo already on file for this person (profile, tutor, intern or staff photo), or null. */
    function midc_person_photo(PDO $db, string $key): ?string
    {
        if (!preg_match('/^(student|user):(\d+)$/', $key, $m)) return null;
        $uid = (int) $m[2];
        if ($m[1] === 'student') {
            $st = $db->prepare('SELECT user_id FROM registrations WHERE id = ?');
            $st->execute([$uid]);
            $uid = (int) $st->fetchColumn();
            if (!$uid) return null;
        }
        $paths = [];
        foreach (["SELECT profile_photo_path FROM user_profiles WHERE user_id = ? AND profile_photo_status <> 'rejected'",
                  'SELECT photo_path FROM tutors WHERE user_id = ?', 'SELECT photo_path FROM interns WHERE user_id = ?',
                  'SELECT photo_path FROM employees WHERE user_id = ?'] as $sql) {
            try { $st = $db->prepare($sql); $st->execute([$uid]); $paths[] = (string) $st->fetchColumn(); } catch (Throwable $e) {}
        }
        $root = dirname(__DIR__, 3) . '/public/';
        foreach ($paths as $p) {
            $p = ltrim($p, '/');
            if ($p !== '' && !str_contains($p, '..') && is_file($root . $p)) return $p;
        }
        return null;
    }
}

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
        // A new upload wins; otherwise the photo already on file for the person picked from the search.
        $onFile = !empty($in['person']) ? midc_person_photo($db, (string) $in['person']) : null;
        if (empty($photo['tmp_name']) && !$onFile) return ['ok' => false, 'error' => 'Upload a passport photo.'];
        if (!empty($photo['tmp_name'])) {
            $up = upload_image($photo, dirname(__DIR__, 3) . '/public/uploads/id_photos', 'uploads/id_photos', ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
            if (!$up['ok']) return ['ok' => false, 'error' => $up['error'] ?? 'Photo upload failed.'];
        } else {
            $up = ['path' => $onFile];
        }

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
