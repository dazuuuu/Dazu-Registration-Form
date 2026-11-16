<?php
/**
 * The secretary portal's look: her menu (only what the admin switched on), the greeting, initials, and the data the dashboard boxes show.
 * secretary_dash_data() is the one place prompts 4 to 6 fill with real tasks, enquiries, blogs and the room diary; until then every box shows a friendly empty state.
 * The menu only HIDES links. Every page and request still calls secretary_require(<switch>) on the server (helpers/secretary.php).
 */
require_once __DIR__ . '/secretary.php';

if (!function_exists('secretary_menu')) {
    /**
     * The menu in order. Each item: [key, label, icon, address (from the site root), bottom-bar label or null, group ('main' or 'account')].
     * Dashboard and Notifications are always there; the rest only when her switch is on (Staff Calendar: View only or Full, hidden when Off).
     */
    function secretary_menu(array $switches): array
    {
        $on = static fn(string $k): bool => ($switches[$k] ?? 'off') !== 'off';
        $all = [
            ['dashboard',      'Dashboard',       'fa-house',         'secretary/dashboard/index.php',      'Home',     'main',    true],
            ['blogs',          'Blogs',           'fa-newspaper',     'secretary/blogs/index.php',          'Blogs',    'main',    $on('blogs')],
            ['enquiries',      'Enquiries',       'fa-inbox',         'secretary/enquiries/index.php',      'Enquiries', 'main',   $on('enquiries')],
            ['tasks',          'My Tasks',        'fa-list-check',    'secretary/tasks/index.php',          'Tasks',    'main',    $on('tasks')],
            ['room_visitors',  'Room & Visitors', 'fa-door-open',     'secretary/room/index.php',           null,       'main',    $on('room_visitors')],
            ['staff_calendar', 'Staff Calendar',  'fa-calendar-days', 'secretary/staffcalendar/index.php',  'Calendar', 'main',    $on('staff_calendar')],
            ['id_card',        'My ID Card',      'fa-id-card',       'secretary/idcard/index.php',         null,       'main',    $on('id_card')],
            ['notifications',  'Notifications',   'fa-bell',          'secretary/notifications/index.php',  null,       'account', true],
        ];
        $out = [];
        foreach ($all as $i) { if (array_pop($i)) $out[] = $i; }
        return $out;
    }

    /** Good morning (before 12:00), Good afternoon (12:00 to 16:59), Good evening (17:00 on), by Nairobi time. */
    function secretary_greeting(?DateTime $now = null): string
    {
        $h = (int) ($now ?? new DateTime('now', new DateTimeZone('Africa/Nairobi')))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('G');
        return 'Good ' . ($h < 12 ? 'morning' : ($h < 17 ? 'afternoon' : 'evening'));
    }

    function secretary_first_name(string $fullName): string
    {
        $p = preg_split('/\s+/', trim($fullName)) ?: [];
        return $p[0] ?? '';
    }

    /** AB for Ann Bakari: first letter of the first and the last name. */
    function secretary_initials(string $fullName): string
    {
        $p = preg_split('/\s+/', trim($fullName)) ?: [];
        return mb_strtoupper(mb_substr($p[0] ?? '?', 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
    }

    /**
     * What the dashboard boxes show. Empty for now: prompts 4 to 6 fill these from the tasks, enquiries, blogs and room diary tables.
     *   tasks:     [['title', 'due' (text), 'chip' (text), 'chip_class' ('today'|'soon'|''), 'done' (bool)]]   her next three, from the admin
     *   enquiries: [['name', 'course', 'how' (text), 'added_by' (name only), 'status' (text), 'status_class' ('pending'|'done')]]   the latest three, whoever added them
     *   diary:     [['title', 'time' (text), 'note' (text)]]   room bookings and expected visitors for today
     * The counts are the real totals (a box shows three items, the banner shows the totals).
     */
    function secretary_dash_data(PDO $db, array $sec, DateTime $now): array
    {
        $d = ['tasks_today' => 0, 'tasks' => [], 'enquiries_pending' => 0, 'enquiries' => [], 'blogs_waiting' => 0, 'diary' => []];
        if (($sec['switches']['enquiries'] ?? 'off') !== 'off') {   // the shared enquiries log (prospects only): the pending count and the latest three pending, with who added each (name only)
            try {
                $d['enquiries_pending'] = (int) $db->query("SELECT COUNT(*) FROM secretary_enquiries WHERE status = 'pending'")->fetchColumn();
                $src = ['phone' => 'Phone call', 'walk_in' => 'Walk-in', 'website' => 'Website form', 'social' => 'Social media', 'other' => 'Other'];
                foreach ($db->query("SELECT id, name, course, source, added_by_name FROM secretary_enquiries WHERE status = 'pending' ORDER BY enquiry_date DESC, id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $d['enquiries'][] = ['name' => $r['name'], 'course' => $r['course'], 'how' => $src[$r['source']] ?? $r['source'], 'added_by' => $r['added_by_name'], 'status' => 'Pending', 'status_class' => 'pending', 'id' => (int) $r['id']];
                }
            } catch (Throwable $e) { /* the enquiries tables are not installed yet: the box stays empty */ }
        }
        if (($sec['switches']['tasks'] ?? 'off') !== 'off') {   // HER tasks only: how many are due today and her next three
            try { require_once __DIR__ . '/../services/SecretaryTaskService.php'; [$d['tasks_today'], $d['tasks']] = (new SecretaryTaskService($db))->dashboard((int) $sec['id'], $now); } catch (Throwable $e) { /* tables not installed yet */ }
        }
        if (($sec['switches']['room_visitors'] ?? 'off') !== 'off') {   // today's diary: room bookings, visitors and bookings from the Staff Calendar
            try { require_once __DIR__ . '/../services/SecretaryDiaryService.php'; $d['diary'] = (new SecretaryDiaryService($db))->dashboard($now); } catch (Throwable $e) { /* tables not installed yet */ }
        }
        return $d;
    }

    /** The next Staff Calendar event for her, or null when her calendar level is Off (or nothing is coming up). Uses the Staff Calendar code, only reading. */
    function secretary_next_event(PDO $db, array $sec, DateTime $now): ?array
    {
        if (($sec['switches']['staff_calendar'] ?? 'off') === 'off') return null;
        try {
            require_once __DIR__ . '/../services/StaffCalendar.php';
            if (!scal_installed($db)) return null;
            $actor = scal_actor($db, (int) $sec['user_id']);
            if (!$actor) return null;
            $n = new DateTime($now->format('Y-m-d H:i:s'), scal_tz());
            $o = scal_next($db, $actor, $n);
            if (!$o) return null;
            return ['title' => (string) $o['title'], 'place' => trim((string) ($o['location'] ?? '')), 'when' => scal_when($o), 'in' => scal_in_days($o, $n), 'in_class' => scal_in_days($o, $n) === 'Today' ? 'today' : ''];
        } catch (Throwable $e) {
            return null;
        }
    }
}
