<?php
// app/v1/services/StaffHolidays.php — the Kenyan public holidays on the Staff Calendar. Dates are worked out here with plain integer arithmetic (no PHP extension needed).
//
// THE RULES:
//  * Fixed dates: New Year's Day 1 Jan, Labour Day 1 May, Madaraka Day 1 Jun, Mazingira Day 10 Oct (the 10 October holiday, formerly Utamaduni Day, Huduma Day and Moi Day),
//    Mashujaa Day 20 Oct, Jamhuri Day 12 Dec, Christmas Day 25 Dec and Boxing Day 26 Dec.
//  * Good Friday and Easter Monday are worked out from Easter (sh_easter). Eid ul-Fitr has no fixed date: an EXPECTED date is worked out from the Islamic calendar (tabular, so it can be
//    a day out) and marked "Expected, to be confirmed" until the super admin sets the announced date.
//  * A holiday that falls on a Sunday is kept on the Monday after it (if that Monday is already a holiday, on the next day that is not).
//  * Only the super admin adds, edits, confirms, fills in a year or removes a holiday (checked on the server).
require_once __DIR__ . '/StaffCalendar.php';

if (!defined('SH_FIRST_YEAR')) define('SH_FIRST_YEAR', 2025);
if (!defined('SH_YEARS')) define('SH_YEARS', 10);

if (!function_exists('sh_installed')) {

    function sh_installed(PDO $db): bool { try { $db->query('SELECT 1 FROM staff_calendar_holidays WHERE 1 = 0'); return true; } catch (Throwable $e) { return false; } }

    /** Easter Sunday of a year (Gregorian calendar): [month, day]. */
    function sh_easter(int $y): array
    {
        $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100; $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30; $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31); $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        return [$month, $day];
    }

    /** Julian day number to a Gregorian date [y, m, d]. */
    function sh_jdn_to_date(int $jdn): array
    {
        $l = $jdn + 68569; $n = intdiv(4 * $l, 146097); $l = $l - intdiv(146097 * $n + 3, 4); $i = intdiv(4000 * ($l + 1), 1461001); $l = $l - intdiv(1461 * $i, 4) + 31;
        $j = intdiv(80 * $l, 2447); $d = $l - intdiv(2447 * $j, 80); $l = intdiv($j, 11); $m = $j + 2 - 12 * $l; $y = 100 * ($n - 49) + $i + $l;
        return [$y, $m, $d];
    }

    /** 1 Shawwal (Eid ul-Fitr) of every Islamic year that begins it inside a Gregorian year, from the tabular Islamic calendar. Usually one date, sometimes two (2033). Can be a day out. */
    function sh_eid_fitr_expected(int $year): array
    {
        $out = [];
        $h0 = (int) floor(($year - 622) * 33 / 32);
        for ($h = $h0 - 2; $h <= $h0 + 2; $h++) {
            $jd = 1 + (int) ceil(29.5 * 9) + ($h - 1) * 354 + (int) floor((3 + 11 * $h) / 30) + 1948439.5 - 1;   // day 1 of month 10
            [$y, $m, $d] = sh_jdn_to_date((int) floor($jd + 0.5));
            if ($y === $year) $out[] = sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
        sort($out);
        return $out;
    }

    /**
     * The standard holidays of a year, each kept on the right day: [['key','name','date','base','note','kind','confirmed'], ...] in date order.
     * A Sunday holiday moves to the Monday after it (or the next day that is not a holiday).
     */
    function sh_year_rows(int $year): array
    {
        $base = [];
        $add = function (string $key, string $name, string $date, string $kind, int $confirmed) use (&$base) { $base[] = ['key' => $key, 'name' => $name, 'base' => $date, 'kind' => $kind, 'confirmed' => $confirmed]; };
        $fx = fn(int $m, int $d) => sprintf('%04d-%02d-%02d', $year, $m, $d);
        $add('new_years_day:' . $year, 'New Year’s Day', $fx(1, 1), 'fixed', 1);
        [$em, $ed] = sh_easter($year); $easter = new DateTime($fx($em, $ed));
        $add('good_friday:' . $year, 'Good Friday', (clone $easter)->modify('-2 days')->format('Y-m-d'), 'easter', 1);
        $add('easter_monday:' . $year, 'Easter Monday', (clone $easter)->modify('+1 day')->format('Y-m-d'), 'easter', 1);
        $add('labour_day:' . $year, 'Labour Day', $fx(5, 1), 'fixed', 1);
        $add('madaraka_day:' . $year, 'Madaraka Day', $fx(6, 1), 'fixed', 1);
        $add('mazingira_day:' . $year, 'Mazingira Day', $fx(10, 10), 'fixed', 1);
        $add('mashujaa_day:' . $year, 'Mashujaa Day', $fx(10, 20), 'fixed', 1);
        $add('jamhuri_day:' . $year, 'Jamhuri Day', $fx(12, 12), 'fixed', 1);
        $add('christmas_day:' . $year, 'Christmas Day', $fx(12, 25), 'fixed', 1);
        $add('boxing_day:' . $year, 'Boxing Day', $fx(12, 26), 'fixed', 1);
        foreach (sh_eid_fitr_expected($year) as $i => $d) $add('eid_ul_fitr:' . $year . ($i ? ':' . ($i + 1) : ''), 'Eid ul-Fitr', $d, 'eid', 0);
        usort($base, fn($a, $b) => $a['base'] <=> $b['base']);
        $taken = array_column($base, 'base');
        $rows = [];
        foreach ($base as $h) {
            $date = $h['base']; $note = '';
            if ((int) (new DateTime($date))->format('N') === 7) {
                $d = new DateTime($date);
                do { $d->modify('+1 day'); } while (in_array($d->format('Y-m-d'), $taken, true));
                $note = 'Moved from Sunday ' . (new DateTime($date))->format('j M');
                $date = $d->format('Y-m-d'); $taken[] = $date;
            }
            $rows[] = $h + ['date' => $date, 'note' => $note];
        }
        usort($rows, fn($a, $b) => [$a['date'], $a['name']] <=> [$b['date'], $b['name']]);
        return $rows;
    }

    // ------------------------------------------------------------------ reading

    function sh_fmt(array $r): array
    {
        $unconfirmed = (int) $r['is_confirmed'] === 0;
        return ['id' => (int) $r['id'], 'date' => (string) $r['holiday_date'], 'name' => (string) $r['name'], 'kind' => (string) $r['kind'], 'confirmed' => !$unconfirmed,
                'base' => (string) ($r['base_date'] ?? ''), 'note' => (string) $r['note'], 'label' => $unconfirmed ? 'Expected, to be confirmed' : ''];
    }

    /** Holidays that fall in [$from, $to), as calendar entries (teal all-day chips). */
    function sh_range(PDO $db, DateTime $from, DateTime $to, string $color = '#1A9A9A'): array
    {
        $st = $db->prepare('SELECT * FROM staff_calendar_holidays WHERE holiday_date >= ? AND holiday_date < ? ORDER BY holiday_date, name');
        $st->execute([$from->format('Y-m-d'), $to->format('Y-m-d')]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $h = sh_fmt($r); $d = $h['date'];
            $out[] = ['holiday' => true, 'id' => 0, 'holiday_id' => $h['id'], 'title' => $h['name'], 'type' => 'holiday', 'type_label' => 'Holiday', 'color' => $color, 'label' => $h['label'], 'note' => $h['note'],
                      'occ_start' => $d . ' 00:00:00', 'occ_end' => $d . ' 23:59:59', 'occ_date' => $d, 'all_day' => true, 'location' => '', 'description' => '', 'repeat' => 'none', 'private' => false,
                      'owner' => '', 'start_at' => $d . ' 00:00:00', 'end_at' => $d . ' 23:59:59'];
        }
        return $out;
    }

    /** The next holiday from today (today included), with the days to go. */
    function sh_next(PDO $db, DateTime $now): ?array
    {
        $st = $db->prepare('SELECT * FROM staff_calendar_holidays WHERE holiday_date >= ? ORDER BY holiday_date, name LIMIT 1');
        $st->execute([$now->format('Y-m-d')]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        $h = sh_fmt($r);
        $h['days'] = (int) (new DateTime($now->format('Y-m-d'), scal_tz()))->diff(new DateTime($h['date'], scal_tz()))->format('%a');
        return $h;
    }

    function sh_year(PDO $db, int $year): array
    {
        $st = $db->prepare('SELECT * FROM staff_calendar_holidays WHERE holiday_date >= ? AND holiday_date <= ? ORDER BY holiday_date, name');
        $st->execute([sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)]);
        return array_map('sh_fmt', $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** The years the year switch offers: every year that has holidays (2025 to 2034 to begin with). */
    function sh_years(PDO $db): array
    {
        $st = $db->query('SELECT DISTINCT YEAR(holiday_date) FROM staff_calendar_holidays ORDER BY 1');
        $years = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        return $years ?: range(SH_FIRST_YEAR, SH_FIRST_YEAR + SH_YEARS - 1);
    }

    // ------------------------------------------------------------------ the super admin's changes

    function sh_super(array $actor): ?array { return $actor['role'] === 'superadmin' ? null : ['ok' => false, 'error' => 'Only the super admin can change the holidays.']; }

    function sh_clean(array $in): array
    {
        $name = trim((string) ($in['name'] ?? '')); $date = trim((string) ($in['date'] ?? ''));
        if ($name === '') return ['error' => 'Give the holiday a name.'];
        if (mb_strlen($name) > 120) return ['error' => 'The name is too long (120 letters at most).'];
        $d = DateTime::createFromFormat('!Y-m-d', $date, scal_tz());
        if (!$d || $d->format('Y-m-d') !== $date || (int) $d->format('Y') < 2000 || (int) $d->format('Y') > 2200) return ['error' => 'Choose a real date.'];
        $note = trim((string) ($in['note'] ?? ''));
        if (mb_strlen($note) > 160) return ['error' => 'The note is too long (160 letters at most).'];
        return ['name' => $name, 'date' => $date, 'note' => $note];
    }

    function sh_add(PDO $db, array $actor, array $in): array
    {
        if ($e = sh_super($actor)) return $e;
        $c = sh_clean($in); if (isset($c['error'])) return ['ok' => false, 'error' => $c['error']];
        $db->prepare("INSERT INTO staff_calendar_holidays (holiday_date, name, kind, is_confirmed, base_date, note, source_key, created_at) VALUES (?, ?, 'added', 1, ?, ?, NULL, ?)")
           ->execute([$c['date'], $c['name'], $c['date'], $c['note'], scal_now()->format('Y-m-d H:i:s')]);
        return ['ok' => true];
    }

    function sh_edit(PDO $db, array $actor, int $id, array $in): array
    {
        if ($e = sh_super($actor)) return $e;
        $c = sh_clean($in); if (isset($c['error'])) return ['ok' => false, 'error' => $c['error']];
        $st = $db->prepare('UPDATE staff_calendar_holidays SET holiday_date = ?, name = ?, note = ? WHERE id = ?'); $st->execute([$c['date'], $c['name'], $c['note'], $id]);
        $chk = $db->prepare('SELECT 1 FROM staff_calendar_holidays WHERE id = ?'); $chk->execute([$id]);
        return $chk->fetchColumn() ? ['ok' => true] : ['ok' => false, 'error' => 'That holiday was not found.'];
    }

    /** Set the announced date of a holiday that was only expected (Eid ul-Fitr). */
    function sh_confirm(PDO $db, array $actor, int $id, string $date): array
    {
        if ($e = sh_super($actor)) return $e;
        $c = sh_clean(['name' => 'x', 'date' => $date]); if (isset($c['error'])) return ['ok' => false, 'error' => $c['error']];
        $st = $db->prepare('SELECT is_confirmed FROM staff_calendar_holidays WHERE id = ?'); $st->execute([$id]);
        $cur = $st->fetchColumn();
        if ($cur === false) return ['ok' => false, 'error' => 'That holiday was not found.'];
        $db->prepare("UPDATE staff_calendar_holidays SET holiday_date = ?, is_confirmed = 1, note = 'Confirmed' WHERE id = ?")->execute([$date, $id]);
        return ['ok' => true];
    }

    function sh_delete(PDO $db, array $actor, int $id): array
    {
        if ($e = sh_super($actor)) return $e;
        $st = $db->prepare('DELETE FROM staff_calendar_holidays WHERE id = ?'); $st->execute([$id]);
        return $st->rowCount() === 1 ? ['ok' => true] : ['ok' => false, 'error' => 'That holiday was not found.'];
    }

    /** Add the standard holidays of a year that are not there yet (safe to repeat). */
    function sh_fill_year(PDO $db, array $actor, int $year): array
    {
        if ($e = sh_super($actor)) return $e;
        if ($year < 2000 || $year > 2200) return ['ok' => false, 'error' => 'Choose a year from 2000 to 2200.'];
        $has = $db->prepare('SELECT 1 FROM staff_calendar_holidays WHERE source_key = ?');
        $ins = $db->prepare('INSERT INTO staff_calendar_holidays (holiday_date, name, kind, is_confirmed, base_date, note, source_key, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $n = 0; $now = scal_now()->format('Y-m-d H:i:s');
        foreach (sh_year_rows($year) as $r) {
            $has->execute([$r['key']]);
            if ($has->fetchColumn()) continue;
            $ins->execute([$r['date'], $r['name'], $r['kind'], $r['confirmed'], $r['base'], $r['note'], $r['key'], $now]); $n++;
        }
        return ['ok' => true, 'added' => $n];
    }
}
