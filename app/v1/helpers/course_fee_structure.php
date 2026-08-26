<?php
// Course fee model stored as JSON on courses.fee_structure.
// Current shape:
// {
//   "version":2,
//   "online":{"tuition_total":360000,"per_semester":60000,"additional":[...]},
//   "physical":{"tuition_total":420000,"per_semester":70000,"additional":[...]}
// }
// Legacy line-item arrays are still decoded so older courses do not break.

if (!function_exists('course_fee_money_value')) {
    function course_fee_money_value($raw): float
    {
        if ($raw === null) {
            return 0.0;
        }
        $value = (float) str_replace(',', '', trim((string) $raw));
        return $value > 0 ? round($value, 2) : 0.0;
    }
}

if (!function_exists('course_fee_structure_from_post')) {
    /**
     * @return list<array{label:string,amount:float}>
     */
    function course_fee_structure_from_post(array $post, string $mode = 'online'): array
    {
        $mode = $mode === 'physical' ? 'physical' : 'online';
        $labels = $post['fee_' . $mode . '_label'] ?? [];
        $amounts = $post['fee_' . $mode . '_amount'] ?? [];
        // Backward-compatible names from the first fee-structure form.
        if ($labels === [] && $mode === 'online') {
            $labels = $post['fee_item_label'] ?? [];
            $amounts = $post['fee_item_amount'] ?? [];
        }
        if (!is_array($labels)) {
            $labels = [];
        }
        if (!is_array($amounts)) {
            $amounts = [];
        }

        $items = [];
        $count = max(count($labels), count($amounts));
        for ($i = 0; $i < $count; $i++) {
            $label = trim((string) ($labels[$i] ?? ''));
            $rawAmount = trim((string) ($amounts[$i] ?? ''));
            if ($label === '' && $rawAmount === '') {
                continue;
            }
            $amount = course_fee_money_value($rawAmount);
            if ($label === '' || $amount < 0) {
                continue;
            }
            $items[] = [
                'label' => mb_strimwidth($label, 0, 120, ''),
                'amount' => round($amount, 2),
            ];
        }
        return $items;
    }
}

if (!function_exists('course_fee_structure_modes_from_post')) {
    /**
     * @return array{online:list<array{label:string,amount:float}>,physical:list<array{label:string,amount:float}>}
     */
    function course_fee_structure_modes_from_post(array $post): array
    {
        $build = static function (string $mode) use ($post): array {
            $total = course_fee_money_value($post['fee_' . $mode . '_total'] ?? 0);
            $perSemester = course_fee_money_value($post['fee_' . $mode . '_per_semester'] ?? 0);
            return [
                'tuition_total' => $total,
                'per_semester' => $perSemester,
                'additional' => course_fee_structure_from_post($post, $mode),
            ];
        };

        return ['online' => $build('online'), 'physical' => $build('physical')];
    }
}

if (!function_exists('course_fee_structure_decode')) {
    /**
     * Decode a flat list of line items.
     * @return list<array{label:string,amount:float}>
     */
    function course_fee_structure_decode($raw): array
    {
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $json = trim((string) $raw);
            if ($json === '') {
                return [];
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                return [];
            }
        }

        $items = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            if ($label === '' || $amount < 0) {
                continue;
            }
            $items[] = ['label' => $label, 'amount' => round($amount, 2)];
        }
        return $items;
    }
}

if (!function_exists('course_fee_mode_decode')) {
    /**
     * @return array{tuition_total:float,per_semester:float,additional:list<array{label:string,amount:float}>}
     */
    function course_fee_mode_decode($raw): array
    {
        $empty = ['tuition_total' => 0.0, 'per_semester' => 0.0, 'additional' => []];
        if (!is_array($raw)) {
            return $empty;
        }
        if (array_key_exists('tuition_total', $raw) || array_key_exists('per_semester', $raw) || array_key_exists('additional', $raw)) {
            return [
                'tuition_total' => course_fee_money_value($raw['tuition_total'] ?? 0),
                'per_semester' => course_fee_money_value($raw['per_semester'] ?? 0),
                'additional' => course_fee_structure_decode($raw['additional'] ?? []),
            ];
        }

        // Legacy list of line items: keep it as additional charges so totals still match.
        return ['tuition_total' => 0.0, 'per_semester' => 0.0, 'additional' => course_fee_structure_decode($raw)];
    }
}

if (!function_exists('course_fee_structure_modes_decode')) {
    /**
     * @return array{online:array{tuition_total:float,per_semester:float,additional:list<array{label:string,amount:float}>},physical:array{tuition_total:float,per_semester:float,additional:list<array{label:string,amount:float}>}}
     */
    function course_fee_structure_modes_decode($raw): array
    {
        $empty = ['online' => course_fee_mode_decode([]), 'physical' => course_fee_mode_decode([])];
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $json = trim((string) $raw);
            if ($json === '') {
                return $empty;
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                return $empty;
            }
        }

        if (array_key_exists('online', $decoded) || array_key_exists('physical', $decoded)) {
            return [
                'online' => course_fee_mode_decode($decoded['online'] ?? []),
                'physical' => course_fee_mode_decode($decoded['physical'] ?? []),
            ];
        }

        $legacy = ['tuition_total' => 0.0, 'per_semester' => 0.0, 'additional' => course_fee_structure_decode($decoded)];
        return ['online' => $legacy, 'physical' => $legacy];
    }
}

if (!function_exists('course_fee_structure_modes_encode')) {
    function course_fee_structure_modes_encode(array $modes): ?string
    {
        $clean = [
            'version' => 2,
            'online' => course_fee_mode_decode($modes['online'] ?? []),
            'physical' => course_fee_mode_decode($modes['physical'] ?? []),
        ];
        if (course_fee_mode_total($clean['online']) <= 0 && course_fee_mode_total($clean['physical']) <= 0) {
            return null;
        }
        return json_encode($clean, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('course_fee_structure_encode')) {
    function course_fee_structure_encode(array $items): ?string
    {
        if (isset($items['online']) || isset($items['physical'])) {
            return course_fee_structure_modes_encode($items);
        }
        $clean = course_fee_structure_decode($items);
        if ($clean === []) {
            return null;
        }
        return course_fee_structure_modes_encode(['online' => $clean, 'physical' => $clean]);
    }
}

if (!function_exists('course_fee_structure_total')) {
    function course_fee_structure_total(array $items): float
    {
        if (array_key_exists('tuition_total', $items) || array_key_exists('additional', $items)) {
            return course_fee_mode_total($items);
        }
        $sum = 0.0;
        foreach ($items as $item) {
            if (!is_array($item) || !array_key_exists('amount', $item)) {
                continue;
            }
            $sum += (float) $item['amount'];
        }
        return round($sum, 2);
    }
}

if (!function_exists('course_fee_mode_total')) {
    function course_fee_mode_total(array $mode): float
    {
        $sum = course_fee_money_value($mode['tuition_total'] ?? 0);
        foreach (($mode['additional'] ?? []) as $item) {
            if (is_array($item)) {
                $sum += course_fee_money_value($item['amount'] ?? 0);
            }
        }
        return round($sum, 2);
    }
}

if (!function_exists('course_fee_mode_per_semester')) {
    function course_fee_mode_per_semester(array $course, array $mode): float
    {
        $manual = course_fee_money_value($mode['per_semester'] ?? 0);
        if ($manual > 0) {
            return $manual;
        }
        $semesters = max(1, (int) ($course['semester_count'] ?? 0));
        $tuition = course_fee_money_value($mode['tuition_total'] ?? 0);
        return $tuition > 0 ? round($tuition / $semesters, 2) : 0.0;
    }
}

if (!function_exists('course_fee_structure_all')) {
    /**
     * @return array{online:list<array{label:string,amount:float}>,physical:list<array{label:string,amount:float}>}
     */
    function course_fee_structure_all(array $course): array
    {
        return course_fee_structure_modes_decode($course['fee_structure'] ?? null);
    }
}

if (!function_exists('course_fee_structure_items')) {
    /**
     * @return list<array{label:string,amount:float}>
     */
    function course_fee_structure_items(array $course, string $mode = 'online'): array
    {
        $modes = course_fee_structure_all($course);
        $mode = $mode === 'physical' ? 'physical' : 'online';
        if (!empty($modes[$mode]['additional'])) {
            return $modes[$mode]['additional'];
        }
        $other = $mode === 'physical' ? 'online' : 'physical';
        return $modes[$other]['additional'] ?? [];
    }
}

if (!function_exists('course_fee_for_mode')) {
    function course_fee_for_mode(array $course, string $mode = 'online'): float
    {
        $modes = course_fee_structure_all($course);
        $mode = $mode === 'physical' ? 'physical' : 'online';
        $sum = course_fee_mode_total($modes[$mode] ?? []);
        if ($sum > 0) {
            return $sum;
        }
        return (float) ($course['course_fee'] ?? 0);
    }
}

if (!function_exists('course_fee_structure_catalog_total')) {
    /** Stored course_fee / listing total: online first, then physical. */
    function course_fee_structure_catalog_total(array $modes, $fallback = 0): float
    {
        $online = course_fee_mode_total($modes['online'] ?? []);
        if ($online > 0) {
            return $online;
        }
        $physical = course_fee_mode_total($modes['physical'] ?? []);
        if ($physical > 0) {
            return $physical;
        }
        return (float) $fallback;
    }
}

if (!function_exists('course_brochure_url')) {
    function course_brochure_url(array $course): ?string
    {
        $path = trim((string) ($course['brochure_path'] ?? ''));
        if ($path === '') {
            return null;
        }
        $id = (int) ($course['id'] ?? 0);
        if ($id > 0) {
            return url('courses/brochure.php?id=' . $id);
        }
        if (!function_exists('settings_asset_url')) {
            require_once __DIR__ . '/uploads.php';
        }
        return settings_asset_url($path);
    }
}

if (!function_exists('course_brochure_absolute_path')) {
    function course_brochure_absolute_path(array $course): ?string
    {
        $rel = ltrim(trim((string) ($course['brochure_path'] ?? '')), '/');
        if ($rel === '' || preg_match('#^https?://#i', $rel)) {
            return null;
        }
        if (!defined('PROJECT_ROOT')) {
            require_once __DIR__ . '/../config/paths.php';
        }
        $candidates = [
            public_path($rel),
            PROJECT_ROOT . '/public/' . $rel,
        ];
        foreach ($candidates as $abs) {
            if (is_string($abs) && is_file($abs)) {
                return $abs;
            }
        }
        return null;
    }
}

if (!function_exists('course_brochure_download_name')) {
    function course_brochure_download_name(array $course): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower(trim((string) ($course['abbreviation'] ?? 'course')))) ?: 'course';
        return $slug . '-brochure.pdf';
    }
}

if (!function_exists('course_fee_structure_file_name')) {
    function course_fee_structure_file_name(array $course): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower(trim((string) ($course['abbreviation'] ?? 'course')))) ?: 'course';
        return $slug . '-fee-structure.pdf';
    }
}

if (!function_exists('course_fee_structure_write_temp_pdf')) {
    function course_fee_structure_write_temp_pdf(array $course): ?string
    {
        $pdf = course_fee_structure_render_pdf($course);
        if ($pdf === null || $pdf === '') {
            return null;
        }
        $path = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . uniqid('fee-structure-', true) . '.pdf';
        return file_put_contents($path, $pdf) !== false ? $path : null;
    }
}

if (!function_exists('course_fee_structure_download_url')) {
    function course_fee_structure_download_url(int $courseId, bool $download = true): string
    {
        return url('courses/fee-structure.php?id=' . $courseId . ($download ? '&download=1' : ''));
    }
}

if (!function_exists('course_fee_structure_table_html')) {
    function course_fee_structure_table_html(array $items, float $fallbackTotal = 0): string
    {
        require_once __DIR__ . '/currency.php';
        $mode = course_fee_mode_decode($items);
        $total = course_fee_mode_total($mode);
        if ($total <= 0) {
            $total = $fallbackTotal;
        }
        $rows = '';
        foreach ($mode['additional'] as $item) {
            $rows .= '<tr>'
                . '<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">'
                . htmlspecialchars((string) $item['label']) . '</td>'
                . '<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;">'
                . htmlspecialchars(format_price($item['amount'])) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Course fee</td>'
                . '<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;">'
                . htmlspecialchars(format_price($total)) . '</td></tr>';
        }
        return '<table style="width:100%;border-collapse:collapse;font-size:13px;margin:0 0 8px;">'
            . '<thead><tr style="background:#f8fafc;">'
            . '<th style="text-align:left;padding:8px 10px;border-bottom:1px solid #cbd5e1;">Item</th>'
            . '<th style="text-align:right;padding:8px 10px;border-bottom:1px solid #cbd5e1;">Amount</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody>'
            . '<tfoot><tr>'
            . '<td style="padding:10px;font-weight:700;">Total</td>'
            . '<td style="padding:10px;text-align:right;font-weight:800;color:#15A24A;">' . htmlspecialchars(format_price($total)) . '</td>'
            . '</tr></tfoot></table>';
    }
}

if (!function_exists('course_fee_structure_document_html')) {
    function course_fee_structure_document_html(array $course, array $items = []): string
    {
        require_once __DIR__ . '/site_logo.php';
        require_once __DIR__ . '/currency.php';
        require_once __DIR__ . '/course_duration.php';
        require_once __DIR__ . '/payment_credentials.php';
        $title = (string) ($course['title'] ?? 'Course');
        $abbr = strtoupper(trim((string) ($course['abbreviation'] ?? '')));
        $logo = site_logo_data_uri() ?: '';
        $modes = course_fee_structure_all($course);
        if ($items !== [] && course_fee_mode_total($modes['online']) <= 0 && course_fee_mode_total($modes['physical']) <= 0) {
            $modes['online'] = $items;
        }
        $classSchedules = [
            'online' => '8am, 12pm, 6pm',
            'physical' => '5am',
        ];
        try {
            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                require_once __DIR__ . '/../models/SettingModel.php';
                $siteSettings = new SettingModel($pdo);
                $classSchedules['online'] = trim((string) ($siteSettings->get('class_schedule_online', '8am, 12pm, 6pm') ?? '8am, 12pm, 6pm'));
                $classSchedules['physical'] = trim((string) ($siteSettings->get('class_schedule_physical', '5am') ?? '5am'));
            }
        } catch (Throwable $e) {
            // Fall back to the site-wide defaults above.
        }
        $semesters = max(1, (int) ($course['semester_count'] ?? 0));
        $duration = course_study_summary($course);
        $overview = trim(strip_tags((string) ($course['overview'] ?? $course['description'] ?? '')));
        $overview = $overview !== '' ? mb_strimwidth($overview, 0, 320, '...') : 'This course equips students with practical, job-ready skills through hands-on training and real-world projects.';
        $semesterLength = $semesters > 0 && (int) ($course['duration_months'] ?? 0) > 0
            ? round(((int) $course['duration_months']) / $semesters, 1) . ' months per semester'
            : 'Calculated across ' . $semesters . ' semester' . ($semesters === 1 ? '' : 's');
        $payment = function_exists('payment_credentials')
            ? payment_credentials()
            : ['business_short_code' => '880100', 'account_number' => '681158', 'phone' => '0722 922 454'];

        $renderMode = static function (string $heading, array $mode) use ($course, $semesters, $classSchedules): string {
            $mode = course_fee_mode_decode($mode);
            $perSemester = course_fee_mode_per_semester($course, $mode);
            $tuitionTotal = course_fee_money_value($mode['tuition_total'] ?? 0);
            $rows = '';
            for ($i = 1; $i <= $semesters; $i++) {
                $rows .= '<tr><td style="padding:13px 18px;background:' . ($i % 2 === 0 ? '#ffffff' : '#fafafa') . ';font-size:13px;">Semester ' . $i . '</td>'
                    . '<td style="padding:13px 18px;background:' . ($i % 2 === 0 ? '#ffffff' : '#fafafa') . ';text-align:right;font-size:13px;font-weight:800;">'
                    . htmlspecialchars(format_price($perSemester)) . '</td></tr>';
            }
            foreach ($mode['additional'] as $item) {
                $rows .= '<tr><td style="padding:13px 18px;background:#ffffff;font-size:13px;">' . htmlspecialchars((string) $item['label']) . '</td>'
                    . '<td style="padding:13px 18px;background:#ffffff;text-align:right;font-size:13px;font-weight:800;">' . htmlspecialchars(format_price($item['amount'])) . '</td></tr>';
            }
            if ($rows === '') {
                $rows = '<tr><td style="padding:13px 18px;background:#fafafa;font-size:13px;">Course fee</td>'
                    . '<td style="padding:13px 18px;background:#fafafa;text-align:right;font-size:13px;font-weight:800;">' . htmlspecialchars(format_price(course_fee_mode_total($mode))) . '</td></tr>';
            }
            $total = course_fee_mode_total($mode);
            $monthly = $tuitionTotal > 0 && (int) ($course['duration_months'] ?? 0) > 0
                ? round($tuitionTotal / max(1, (int) $course['duration_months']))
                : 0;
            $scheduleKey = strtolower($heading) === 'physical classes' ? 'physical' : 'online';
            $schedule = trim((string) ($classSchedules[$scheduleKey] ?? ''));
            return '<div style="page-break-inside:avoid;margin-top:24px;">'
                . '<h3 style="margin:0 0 10px;font-size:13px;color:#111827;text-transform:uppercase;letter-spacing:.02em;">' . htmlspecialchars($heading) . '</h3>'
                . ($schedule !== '' ? '<p style="margin:0 0 10px;font-size:11px;color:#4b5563;"><strong>Class schedule:</strong> ' . htmlspecialchars($schedule) . '</p>' : '')
                . '<table style="width:100%;border-collapse:collapse;margin:0 0 0;border:0;">'
                . '<thead><tr><th style="padding:12px 18px;background:#e5e7eb;text-align:left;font-size:11px;text-transform:uppercase;">Fee item</th>'
                . '<th style="padding:12px 18px;background:#e5e7eb;text-align:right;font-size:11px;text-transform:uppercase;">Amount</th></tr></thead>'
                . '<tbody>' . $rows . '</tbody></table>'
                . '<div style="display:table;width:100%;background:#111827;color:#ffffff;border-radius:7px;margin-top:0;">'
                . '<div style="display:table-cell;padding:15px 18px;font-size:13px;font-weight:800;text-transform:uppercase;">Total program fees</div>'
                . '<div style="display:table-cell;padding:15px 18px;font-size:13px;font-weight:800;text-align:right;">' . htmlspecialchars(format_price($total)) . '</div></div>'
                . ($monthly > 0 ? '<div style="margin-top:18px;font-size:12px;color:#111827;"><strong style="display:block;text-transform:uppercase;margin-bottom:8px;">Monthly payment option</strong>'
                . htmlspecialchars(format_price($monthly)) . ' per month x ' . (int) ($course['duration_months'] ?? 0) . ' months'
                . ($mode['additional'] ? '<br>+ additional charges = ' . htmlspecialchars(format_price($total)) . ' total' : '')
                . '<p style="font-style:italic;color:#6b7280;margin:12px 0 0;">Each semester runs for the stated semester length.</p></div>' : '')
                . '</div>';
        };
        $sections = '';
        foreach (['online' => 'Online classes', 'physical' => 'Physical classes'] as $key => $heading) {
            if (course_fee_mode_total($modes[$key] ?? []) > 0) {
                $sections .= $renderMode($heading, $modes[$key]);
            }
        }
        if ($sections === '') {
            $sections = $renderMode('Course fees', ['tuition_total' => (float) ($course['course_fee'] ?? 0), 'per_semester' => 0, 'additional' => []]);
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:720px;margin:0 auto;background:#ffffff;color:#111827;padding:46px 52px;">'
            . '<div style="display:table;width:100%;margin-bottom:42px;">'
            . '<div style="display:table-cell;width:120px;vertical-align:middle;">'
            . ($logo !== '' ? '<img src="' . htmlspecialchars($logo) . '" alt="Dazu Hub" style="max-height:78px;max-width:100px;object-fit:contain;">' : '')
            . '</div>'
            . '<div style="display:table-cell;vertical-align:middle;"><h1 style="margin:0;font-size:24px;letter-spacing:.02em;">DAZU HUB</h1>'
            . '<p style="margin:8px 0 0;font-size:13px;font-weight:800;text-transform:uppercase;color:#374151;">Fee Structure</p></div>'
            . '</div>'
            . '<div style="border-top:1px solid #d1d5db;padding-top:30px;">'
            . '<h2 style="margin:0 0 18px;font-size:17px;">' . htmlspecialchars($title)
            . ($abbr !== '' ? ' (' . htmlspecialchars($abbr) . ')' : '')
            . '</h2>'
            . '<p style="margin:0 0 34px;font-size:12px;line-height:1.65;color:#374151;max-width:600px;">' . htmlspecialchars($overview) . '</p>'
            . '<div style="display:table;width:100%;background:#f5f6f8;border-radius:10px;margin-bottom:28px;">'
            . '<div style="display:table-cell;width:50%;padding:18px 20px;"><div style="font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:8px;">Program duration</div><div style="font-size:12px;">' . htmlspecialchars($duration ?: ((int) ($course['duration_months'] ?? 0) . ' months')) . '</div></div>'
            . '<div style="display:table-cell;width:50%;padding:18px 20px;"><div style="font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:8px;">Semester length</div><div style="font-size:12px;">' . htmlspecialchars($semesterLength) . '</div></div>'
            . '</div>'
            . $sections
            . '<div style="display:table;width:100%;background:#f5f6f8;border-radius:10px;margin-top:30px;">'
            . '<div style="display:table-cell;padding:14px 18px;width:33%;"><strong style="display:block;font-size:11px;text-transform:uppercase;">Payment details</strong><span style="font-size:11px;">M-PESA Paybill: ' . htmlspecialchars((string) ($payment['paybill'] ?? $payment['business_short_code'] ?? '880100')) . '</span></div>'
            . '<div style="display:table-cell;padding:14px 18px;width:34%;font-size:11px;vertical-align:bottom;">Account: ' . htmlspecialchars((string) ($payment['account_number'] ?? '681158')) . '</div>'
            . '<div style="display:table-cell;padding:14px 18px;width:33%;font-size:11px;text-align:right;vertical-align:bottom;">Phone: ' . htmlspecialchars((string) ($payment['phone'] ?? '0722 922 454')) . '</div>'
            . '</div>'
            . '<p style="margin:10px 0 0;font-size:9px;color:#6b7280;">Issued ' . htmlspecialchars(date('d M Y')) . ' - dazuhub.ac.ke</p>'
            . '</div></div>';
    }
}

if (!function_exists('course_fee_structure_render_pdf')) {
    /** @return string|null PDF binary */
    function course_fee_structure_render_pdf(array $course): ?string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
            . course_fee_structure_document_html($course)
            . '</body></html>';

        require_once __DIR__ . '/dompdf.php';
        if (!dazu_load_dompdf()) {
            return null;
        }
        try {
            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return $dompdf->output();
        } catch (Throwable $e) {
            error_log('[fee-structure] PDF render failed: ' . $e->getMessage());
            return null;
        }
    }
}
