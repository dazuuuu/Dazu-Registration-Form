<?php
// app/v1/services/StaffSettings.php — the Staff Calendar settings: event types, the team defaults for reminders and notices, and each person's own switches.
//
// THE RULES, checked here on the server (the screens only show them):
//  * Only the super admin changes event types, the team defaults, the holidays, the gift settings and the messages (the settings page is super admin only).
//  * A type is never deleted while it has events (it can be switched off instead). Birthday, Holiday and Project are used by those features and are never deleted.
//    Switching a type off hides its events everywhere on the calendar and removes it from the New event form. The events are not deleted.
//  * A person's own switches only turn things off for themselves, and only for themselves (the person is always the one logged in). Every switch starts on.
//  * The team defaults and the personal switches are only saved here. Nothing is sent from here.
require_once __DIR__ . '/StaffCalendar.php';

if (!defined('SST_CORE_TYPES')) define('SST_CORE_TYPES', ['birthday', 'holiday', 'project']);
// pref key => [label, team setting key]
if (!defined('SST_PREFS')) define('SST_PREFS', [
    'remind_day_before' => ['Day before, 8:00 am', 'team_remind_day_before'],
    'remind_on_day'     => ['On the day, 8:00 am', 'team_remind_on_day'],
    'remind_hour_before' => ['1 hour before the start', 'team_remind_hour_before'],
    'remind_email'      => ['Also send an email', 'team_remind_email'],
    'notice_birthday'   => ['Birthday notices', 'team_notice_birthday'],
    'notice_gift'       => ['Gift reminders', 'team_notice_gift'],
]);

if (!function_exists('sst_installed')) {

    function sst_installed(PDO $db): bool
    {
        try { $db->query('SELECT is_active FROM staff_calendar_event_types WHERE 1 = 0'); $db->query('SELECT 1 FROM staff_calendar_user_prefs WHERE 1 = 0'); $db->query('SELECT 1 FROM staff_calendar_settings WHERE 1 = 0'); return true; }
        catch (Throwable $e) { return false; }
    }

    function sst_super(array $actor): ?array { return $actor['role'] === 'superadmin' ? null : ['ok' => false, 'error' => 'Only the super admin can change the Staff Calendar settings.']; }

    // ------------------------------------------------------------------ event types

    /** Every type, on or off, with how many events use it. */
    function sst_types(PDO $db): array
    {
        $count = $db->query('SELECT type_key, COUNT(*) FROM staff_calendar_events GROUP BY type_key')->fetchAll(PDO::FETCH_KEY_PAIR);
        $out = [];
        foreach ($db->query('SELECT type_key, label, color, is_active FROM staff_calendar_event_types ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $n = (int) ($count[$r['type_key']] ?? 0);
            $out[] = ['key' => (string) $r['type_key'], 'label' => (string) $r['label'], 'color' => (string) $r['color'], 'active' => (int) $r['is_active'] === 1, 'events' => $n,
                      'core' => in_array($r['type_key'], SST_CORE_TYPES, true), 'can_delete' => $n === 0 && !in_array($r['type_key'], SST_CORE_TYPES, true)];
        }
        return $out;
    }

    function sst_type_exists(PDO $db, string $key): bool { $st = $db->prepare('SELECT 1 FROM staff_calendar_event_types WHERE type_key = ?'); $st->execute([$key]); return (bool) $st->fetchColumn(); }

    function sst_valid_color(string $c): bool { return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c); }

    function sst_type_active(PDO $db, array $actor, string $key, bool $on): array
    {
        if ($e = sst_super($actor)) return $e;
        if (!sst_type_exists($db, $key)) return ['ok' => false, 'error' => 'That type was not found.'];
        $db->prepare('UPDATE staff_calendar_event_types SET is_active = ? WHERE type_key = ?')->execute([$on ? 1 : 0, $key]);
        return ['ok' => true];
    }

    function sst_type_color(PDO $db, array $actor, string $key, string $color): array
    {
        if ($e = sst_super($actor)) return $e;
        $color = strtoupper(trim($color));
        if (!sst_valid_color($color)) return ['ok' => false, 'error' => 'Choose a colour.'];
        if (!sst_type_exists($db, $key)) return ['ok' => false, 'error' => 'That type was not found.'];
        $db->prepare('UPDATE staff_calendar_event_types SET color = ? WHERE type_key = ?')->execute([$color, $key]);
        return ['ok' => true];
    }

    function sst_type_add(PDO $db, array $actor, string $name, string $color): array
    {
        if ($e = sst_super($actor)) return $e;
        $name = trim($name); $color = strtoupper(trim($color));
        if ($name === '') return ['ok' => false, 'error' => 'Give the type a name.'];
        if (mb_strlen($name) > 40) return ['ok' => false, 'error' => 'The name is too long (40 letters at most).'];
        if (!sst_valid_color($color)) return ['ok' => false, 'error' => 'Choose a colour.'];
        $st = $db->prepare('SELECT 1 FROM staff_calendar_event_types WHERE LOWER(label) = LOWER(?)'); $st->execute([$name]);
        if ($st->fetchColumn()) return ['ok' => false, 'error' => 'There is already a type with that name.'];
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_'); if ($slug === '') $slug = 'type';
        $slug = substr($slug, 0, 24); $key = $slug; $i = 2;
        while (sst_type_exists($db, $key)) $key = $slug . '_' . $i++;
        $order = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM staff_calendar_event_types')->fetchColumn();
        $db->prepare('INSERT INTO staff_calendar_event_types (type_key, label, color, sort_order, is_active) VALUES (?, ?, ?, ?, 1)')->execute([$key, $name, $color, $order]);
        return ['ok' => true, 'key' => $key];
    }

    function sst_type_delete(PDO $db, array $actor, string $key): array
    {
        if ($e = sst_super($actor)) return $e;
        if (!sst_type_exists($db, $key)) return ['ok' => false, 'error' => 'That type was not found.'];
        if (in_array($key, SST_CORE_TYPES, true)) return ['ok' => false, 'error' => 'This type is used by birthdays, holidays or projects, so it cannot be deleted. You can switch it off.'];
        $st = $db->prepare('SELECT COUNT(*) FROM staff_calendar_events WHERE type_key = ?'); $st->execute([$key]);
        if ((int) $st->fetchColumn() > 0) return ['ok' => false, 'error' => 'This type has events, so it cannot be deleted. You can switch it off.'];
        $db->prepare('DELETE FROM staff_calendar_event_types WHERE type_key = ?')->execute([$key]);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ team defaults (super admin)

    /** The team defaults: pref key => on/off. A missing setting is on. */
    function sst_team_defaults(PDO $db): array
    {
        $vals = $db->query("SELECT setting_key, setting_value FROM staff_calendar_settings WHERE setting_key LIKE 'team\\_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
        $out = [];
        foreach (SST_PREFS as $k => [$label, $setting]) $out[$k] = !isset($vals[$setting]) || (string) $vals[$setting] !== '0';
        return $out;
    }

    /** Save the team defaults from a form: every switch that is posted is on, the others are off. */
    function sst_team_save(PDO $db, array $actor, array $in): array
    {
        if ($e = sst_super($actor)) return $e;
        $now = scal_now()->format('Y-m-d H:i:s');
        $has = $db->prepare('SELECT 1 FROM staff_calendar_settings WHERE setting_key = ?');
        foreach (SST_PREFS as $k => [$label, $setting]) {
            $v = !empty($in[$k]) ? '1' : '0';
            $has->execute([$setting]);
            if ($has->fetchColumn()) $db->prepare('UPDATE staff_calendar_settings SET setting_value = ?, updated_by = ?, updated_at = ? WHERE setting_key = ?')->execute([$v, $actor['uid'], $now, $setting]);
            else $db->prepare('INSERT INTO staff_calendar_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, ?)')->execute([$setting, $v, $actor['uid'], $now]);
        }
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ a person's own switches

    /** This person's switches: pref key => on/off. No saved row means on. */
    function sst_user_prefs(PDO $db, int $userId): array
    {
        $st = $db->prepare('SELECT pref_key, enabled FROM staff_calendar_user_prefs WHERE user_id = ?'); $st->execute([$userId]);
        $saved = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        $out = [];
        foreach (SST_PREFS as $k => $_) $out[$k] = !isset($saved[$k]) || (int) $saved[$k] === 1;
        return $out;
    }

    /** Save the logged-in person's own switches (never anybody else's). Every switch that is posted is on, the others are off. */
    function sst_user_save(PDO $db, array $actor, array $in): array
    {
        $now = scal_now()->format('Y-m-d H:i:s');
        $has = $db->prepare('SELECT 1 FROM staff_calendar_user_prefs WHERE user_id = ? AND pref_key = ?');
        foreach (SST_PREFS as $k => $_) {
            $v = !empty($in[$k]) ? 1 : 0;
            $has->execute([$actor['uid'], $k]);
            if ($has->fetchColumn()) $db->prepare('UPDATE staff_calendar_user_prefs SET enabled = ?, updated_at = ? WHERE user_id = ? AND pref_key = ?')->execute([$v, $now, $actor['uid'], $k]);
            else $db->prepare('INSERT INTO staff_calendar_user_prefs (user_id, pref_key, enabled, updated_at) VALUES (?, ?, ?, ?)')->execute([$actor['uid'], $k, $v, $now]);
        }
        return ['ok' => true];
    }
}
