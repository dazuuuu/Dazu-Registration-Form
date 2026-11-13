<?php
// app/v1/services/StaffProjects.php — projects on the Staff Calendar: multi-day events (type "Project") with tasks, people and a progress figure.
//
// THE RULES, checked here on the server on every action (the screens only show them):
//  * A project is an ordinary event of the type Project. It always has a start and an end date, is always all day, never repeats, and is never private (everyone who can use the calendar sees it).
//  * Its tasks are the rows of the event's checklist (staff_calendar_checklist), with a person, an optional start date, a due date and a status: To do, In progress or Done. Ticking sets Done.
//  * Progress counts Done tasks only (5 of 8 = 62%). "On track" while no unfinished task is past its due date, "Behind" when one is, "Done" once every task is done.
//  * The owner and the super admin can add, edit, delete and give tasks, and change any task. A person given a task can tick it or change its status, but not its title, person or dates.
//    Everyone else only views.
//  * When a task is given to someone (or its person changes) a PENDING notice is recorded for them, and another for the day before the due date while the task is not done. Nothing is sent here.
//  * Names only, never roles.
require_once __DIR__ . '/StaffBirthdays.php';

if (!defined('SPJ_STATUS')) define('SPJ_STATUS', ['todo' => 'To do', 'in_progress' => 'In progress', 'done' => 'Done']);
if (!defined('SPJ_MAX_TASKS')) define('SPJ_MAX_TASKS', 100);

if (!function_exists('spj_installed')) {

    function spj_installed(PDO $db): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $db->query('SELECT assignee_user_id, start_date, due_date, status FROM staff_calendar_checklist WHERE 1 = 0'); $db->query('SELECT 1 FROM staff_calendar_task_notices WHERE 1 = 0'); return $ok = true; }
        catch (Throwable $e) { return $ok = false; }
    }

    function spj_valid_date(string $d): bool
    {
        if ($d === '') return true;
        $x = DateTime::createFromFormat('!Y-m-d', $d, scal_tz());
        return $x && $x->format('Y-m-d') === $d;
    }

    /** One task from a form or a request, checked. @return array{0:?array,1:?string} [task, error] */
    function spj_clean_task(array $t, array $people): array
    {
        $title = trim((string) ($t['t'] ?? $t['title'] ?? ''));
        if ($title === '') return [null, 'Give the task a title.'];
        if (mb_strlen($title) > 200) return [null, 'A task title is too long (200 letters at most).'];
        $who = (int) ($t['who'] ?? 0);
        if ($who > 0 && !isset($people[$who])) return [null, 'That person cannot be given a task. Choose a tutor or the super admin.'];
        $start = trim((string) ($t['start'] ?? '')); $due = trim((string) ($t['due'] ?? ''));
        if (!spj_valid_date($start) || !spj_valid_date($due)) return [null, 'A task date is not a real date.'];
        if ($start !== '' && $due !== '' && $start > $due) return [null, 'A task cannot start after it is due.'];
        $status = (string) ($t['status'] ?? 'todo');
        if (!isset(SPJ_STATUS[$status])) $status = 'todo';
        return [['id' => (int) ($t['id'] ?? 0), 't' => $title, 'who' => $who, 'start' => $start, 'due' => $due, 'status' => $status], null];
    }

    /** @return array{0:?array,1:?string} the tasks of a form: [[task...], error] */
    function spj_clean_tasks(PDO $db, string $raw): array
    {
        $list = json_decode($raw, true);
        if (!is_array($list)) return [null, 'The task list could not be read.'];
        if (count($list) > SPJ_MAX_TASKS) return [null, 'A project can have ' . SPJ_MAX_TASKS . ' tasks at most.'];
        $people = sbd_people($db); $out = [];
        foreach ($list as $t) {
            if (!is_array($t) || trim((string) ($t['t'] ?? $t['title'] ?? '')) === '') continue;   // blank rows are dropped
            [$c, $err] = spj_clean_task($t, $people);
            if ($err) return [null, $err];
            $out[] = $c;
        }
        return [$out, null];
    }

    // ------------------------------------------------------------------ pending notices (nothing is sent from here)

    function spj_notice_cancel(PDO $db, int $taskId, ?int $toUser = null, ?string $kind = null): void
    {
        $sql = 'DELETE FROM staff_calendar_task_notices WHERE task_id = ? AND sent_at IS NULL'; $args = [$taskId];
        if ($toUser !== null) { $sql .= ' AND to_user_id = ?'; $args[] = $toUser; }
        if ($kind !== null) { $sql .= ' AND kind = ?'; $args[] = $kind; }
        $db->prepare($sql)->execute($args);
    }

    function spj_notice_add(PDO $db, int $taskId, int $eventId, int $to, string $kind, ?string $sendOn): void
    {
        $db->prepare('INSERT INTO staff_calendar_task_notices (task_id, event_id, to_user_id, kind, send_on, created_at) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$taskId, $eventId, $to, $kind, $sendOn, scal_now()->format('Y-m-d H:i:s')]);
    }

    /** The "day before it is due" notice: replaced whenever the person, the due date or the status changes, and only while the task is not done. */
    function spj_notice_due(PDO $db, int $taskId, int $eventId, int $who, string $due, bool $done): void
    {
        spj_notice_cancel($db, $taskId, null, 'due_before');
        if ($done || $who <= 0 || $due === '') return;
        spj_notice_add($db, $taskId, $eventId, $who, 'due_before', (new DateTime($due, scal_tz()))->modify('-1 day')->format('Y-m-d'));
    }

    // ------------------------------------------------------------------ reading

    /** The tasks of an event, in order, with the person's name. */
    function spj_tasks(PDO $db, int $eventId): array
    {
        $st = $db->prepare('SELECT c.id, c.label, c.is_done, c.assignee_user_id, c.start_date, c.due_date, c.status FROM staff_calendar_checklist c WHERE c.event_id = ? ORDER BY c.sort_order, c.id');
        $st->execute([$eventId]);
        $people = sbd_people($db); $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $who = (int) $r['assignee_user_id']; $p = $people[$who] ?? null;
            $status = isset(SPJ_STATUS[$r['status']]) ? (string) $r['status'] : ((int) $r['is_done'] === 1 ? 'done' : 'todo');
            if ((int) $r['is_done'] === 1) $status = 'done';
            $out[] = ['id' => (int) $r['id'], 't' => (string) $r['label'], 'who' => $who, 'who_name' => $p['name'] ?? '', 'who_first' => $p['first'] ?? '',
                      'start' => (string) ($r['start_date'] ?? ''), 'due' => (string) ($r['due_date'] ?? ''), 'status' => $status, 'status_label' => SPJ_STATUS[$status], 'done' => $status === 'done'];
        }
        return $out;
    }

    /** Progress of a list of tasks: total, done, percent (done only, rounded down: 5 of 8 = 62%), how many unfinished tasks are past due, and the word to show. */
    function spj_stats(array $tasks, string $today): array
    {
        $total = count($tasks); $done = 0; $behind = 0;
        foreach ($tasks as $t) {
            if ($t['status'] === 'done') $done++;
            elseif ($t['due'] !== '' && $t['due'] < $today) $behind++;
        }
        $percent = $total > 0 ? (int) floor($done * 100 / $total) : 0;
        $state = ($total > 0 && $done === $total) ? 'Done' : ($behind > 0 ? 'Behind' : 'On track');
        return ['total' => $total, 'done' => $done, 'percent' => $percent, 'behind' => $behind, 'state' => $state];
    }

    /** Progress for several projects at once: [event id => stats]. */
    function spj_progress_bulk(PDO $db, array $eventIds, string $today): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds))));
        $out = array_fill_keys($eventIds, spj_stats([], $today));
        if (!$eventIds) return $out;
        $st = $db->query('SELECT event_id, status, is_done, due_date FROM staff_calendar_checklist WHERE event_id IN (' . implode(',', $eventIds) . ')');
        $by = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $by[(int) $r['event_id']][] = ['status' => ((int) $r['is_done'] === 1 ? 'done' : (string) $r['status']), 'due' => (string) ($r['due_date'] ?? '')];
        foreach ($by as $id => $tasks) $out[$id] = spj_stats($tasks, $today);
        return $out;
    }

    function spj_event(PDO $db, array $actor, int $id): ?array
    {
        $ev = scal_find($db, $actor, $id);
        return ($ev && $ev['type'] === 'project') ? $ev : null;
    }

    /** Everything the project page shows. Null when the event is not a project or the viewer may not see it. */
    function spj_view(PDO $db, array $actor, int $id, DateTime $now): ?array
    {
        $ev = spj_event($db, $actor, $id);
        if (!$ev) return null;
        $today = $now->format('Y-m-d');
        $tasks = spj_tasks($db, $id);
        $people = sbd_people($db);
        $stats = spj_stats($tasks, $today);
        $startD = substr($ev['start_at'], 0, 10); $endD = substr($ev['end_at'], 0, 10);
        $manage = scal_can_change($actor, $ev);
        foreach ($tasks as &$t) {
            $p = $people[$t['who']] ?? null;
            $t['initials'] = $p ? sbd_initials_of($p['name']) : ''; $t['photo'] = $p ? sbd_photo($db, $t['who']) : '';
            $t['can_status'] = $manage || ($t['who'] > 0 && $t['who'] === $actor['uid']);
            $t['overdue'] = $t['status'] !== 'done' && $t['due'] !== '' && $t['due'] < $today;
        }
        unset($t);
        // people and how many tasks each has
        $count = [];
        foreach ($tasks as $t) if ($t['who'] > 0) $count[$t['who']] = ($count[$t['who']] ?? 0) + 1;
        $pp = [];
        foreach ($count as $uid => $n) if (isset($people[$uid])) $pp[] = ['id' => $uid, 'name' => $people[$uid]['name'], 'first' => $people[$uid]['first'], 'initials' => sbd_initials_of($people[$uid]['name']), 'photo' => sbd_photo($db, $uid), 'count' => $n];
        usort($pp, fn($a, $b) => [-$a['count'], $a['name']] <=> [-$b['count'], $b['name']]);
        // the timeline: one column per day (at most 62), one bar per task
        $days = []; $d = new DateTime($startD, scal_tz()); $last = new DateTime($endD, scal_tz());
        for ($i = 0; $i < 62 && $d <= $last; $i++, $d->modify('+1 day')) $days[] = ['date' => $d->format('Y-m-d'), 'dow' => $d->format('D'), 'day' => $d->format('j'), 'today' => $d->format('Y-m-d') === $today];
        $n = count($days); $idx = array_flip(array_column($days, 'date'));
        $skip = ['project', 'out_of_office', 'birthday', 'holiday', 'payday']; $colors = [];
        foreach (scal_types($db) as $ty) if (!in_array($ty['type_key'], $skip, true)) $colors[] = $ty['color'];
        if (!$colors) $colors = ['#048C44'];
        $clip = fn(string $dt) => $dt < $startD ? $startD : ($dt > $endD ? $endD : $dt);
        $bars = [];
        foreach ($tasks as $i => $t) {
            $s = $t['start'] !== '' ? $t['start'] : $t['due']; $e = $t['due'] !== '' ? $t['due'] : $t['start'];
            $none = ($s === '' && $e === '');
            if ($none) { $s = $startD; $e = $endD; }
            $s = $clip($s); $e = $clip($e);
            $c1 = $idx[$s] ?? ($s > $endD ? $n - 1 : 0); $c2 = $idx[$e] ?? $n - 1;
            if ($c2 < $c1) $c2 = $c1;
            $bars[] = ['task' => $t['id'], 'title' => $t['t'], 'from' => $c1 + 1, 'to' => $c2 + 1, 'color' => $none ? '#9aa39a' : $colors[$i % count($colors)], 'none' => $none, 'done' => $t['status'] === 'done'];
        }
        $todayIdx = isset($idx[$today]) ? $idx[$today] : null;
        $tN = new DateTime($today, scal_tz());
        if ($today < $startD) { $diff = (int) $tN->diff(new DateTime($startD, scal_tz()))->format('%a'); $rel = $diff <= 7 ? 'the week before' : $diff . ' days before it starts'; }
        elseif ($today > $endD) { $diff = (int) (new DateTime($endD, scal_tz()))->diff($tN)->format('%a'); $rel = $diff === 1 ? 'the project ended yesterday' : 'the project ended ' . $diff . ' days ago'; }
        else { $dayNo = (int) (new DateTime($startD, scal_tz()))->diff($tN)->format('%a') + 1; $rel = 'day ' . $dayNo . ' of ' . ((int) (new DateTime($startD, scal_tz()))->diff($last)->format('%a') + 1); }
        $s0 = new DateTime($startD, scal_tz());
        $range = $startD === $endD ? $s0->format('D j M') : $s0->format('D j M') . ' to ' . (new DateTime($endD, scal_tz()))->format('D j M');
        return [
            'id' => $id, 'title' => $ev['title'], 'description' => $ev['description'], 'color' => $ev['color'], 'owner' => $ev['owner'], 'owner_id' => $ev['owner_id'],
            'start' => $startD, 'end' => $endD, 'range_text' => $range, 'added_at' => $ev['created_at'] !== '' ? (new DateTime($ev['created_at'], scal_tz()))->format('j M Y, g:i a') : '',
            'owner_initials' => sbd_initials_of($ev['owner']), 'owner_photo' => sbd_photo($db, $ev['owner_id']),
            'tasks' => $tasks, 'stats' => $stats, 'people' => $pp, 'days' => $days, 'bars' => $bars, 'today_idx' => $todayIdx, 'today_text' => 'Today is ' . $now->format('D j M') . ', ' . $rel,
            'can_manage' => $manage, 'cap' => $n < ((int) (new DateTime($startD, scal_tz()))->diff($last)->format('%a') + 1),
        ];
    }

    // ------------------------------------------------------------------ saving tasks

    /**
     * Replace the tasks of a project from the event form (owner / super admin only, checked by scal_save). Tasks that keep their id are updated (so their notices stay right), new ones are added,
     * tasks that are no longer in the list are deleted. A person given a task (or a changed person) gets a pending notice, and one for the day before it is due while it is not done.
     */
    function spj_sync_tasks(PDO $db, int $eventId, array $tasks): void
    {
        $st = $db->prepare('SELECT id, assignee_user_id FROM staff_calendar_checklist WHERE event_id = ?'); $st->execute([$eventId]);
        $existing = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $existing[(int) $r['id']] = (int) $r['assignee_user_id'];
        $keep = [];
        $upd = $db->prepare('UPDATE staff_calendar_checklist SET label = ?, is_done = ?, assignee_user_id = ?, start_date = ?, due_date = ?, status = ?, sort_order = ? WHERE id = ? AND event_id = ?');
        $ins = $db->prepare('INSERT INTO staff_calendar_checklist (event_id, label, is_done, sort_order, assignee_user_id, start_date, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($tasks as $i => $t) {
            $done = $t['status'] === 'done' ? 1 : 0; $who = $t['who'] > 0 ? $t['who'] : null;
            $start = $t['start'] !== '' ? $t['start'] : null; $due = $t['due'] !== '' ? $t['due'] : null;
            if ($t['id'] > 0 && isset($existing[$t['id']])) {
                $tid = $t['id']; $oldWho = $existing[$tid]; $keep[$tid] = true;
                $upd->execute([$t['t'], $done, $who, $start, $due, $t['status'], $i, $tid, $eventId]);
            } else {
                $ins->execute([$eventId, $t['t'], $done, $i, $who, $start, $due, $t['status']]);
                $tid = (int) $db->lastInsertId(); $oldWho = 0;
            }
            spj_after_task_change($db, $tid, $eventId, $oldWho, (int) $t['who'], $t['due'], $done === 1);
        }
        foreach ($existing as $tid => $_) if (!isset($keep[$tid])) {
            $db->prepare('DELETE FROM staff_calendar_checklist WHERE id = ? AND event_id = ?')->execute([$tid, $eventId]);
            spj_notice_cancel($db, $tid);
        }
    }

    /** Record the pending notices for a task that was just added or changed. */
    function spj_after_task_change(PDO $db, int $taskId, int $eventId, int $oldWho, int $newWho, string $due, bool $done): void
    {
        if ($newWho !== $oldWho) {
            spj_notice_cancel($db, $taskId);   // whatever was still waiting for the old person is no longer needed
            if ($newWho > 0) spj_notice_add($db, $taskId, $eventId, $newWho, 'assigned', scal_now()->format('Y-m-d'));
        }
        spj_notice_due($db, $taskId, $eventId, $newWho, $due, $done);
    }

    function spj_task_row(PDO $db, int $eventId, int $taskId): ?array
    {
        $st = $db->prepare('SELECT * FROM staff_calendar_checklist WHERE id = ? AND event_id = ?'); $st->execute([$taskId, $eventId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Add (task id 0) or change one task from the project page. Owner and super admin only. */
    function spj_task_save(PDO $db, array $actor, int $eventId, array $in): array
    {
        $ev = spj_event($db, $actor, $eventId);
        if (!$ev) return ['ok' => false, 'error' => 'That project was not found.'];
        if (!scal_can_change($actor, $ev)) return ['ok' => false, 'error' => 'Only the person who added the project or the super admin can add or change tasks.'];
        [$t, $err] = spj_clean_task($in, sbd_people($db));
        if ($err) return ['ok' => false, 'error' => $err];
        $taskId = (int) ($in['task'] ?? 0);
        $done = $t['status'] === 'done' ? 1 : 0; $who = $t['who'] > 0 ? $t['who'] : null;
        $start = $t['start'] !== '' ? $t['start'] : null; $due = $t['due'] !== '' ? $t['due'] : null;
        if ($taskId > 0) {
            $row = spj_task_row($db, $eventId, $taskId);
            if (!$row) return ['ok' => false, 'error' => 'That task was not found.'];
            $oldWho = (int) $row['assignee_user_id'];
            $db->prepare('UPDATE staff_calendar_checklist SET label = ?, is_done = ?, assignee_user_id = ?, start_date = ?, due_date = ?, status = ? WHERE id = ? AND event_id = ?')->execute([$t['t'], $done, $who, $start, $due, $t['status'], $taskId, $eventId]);
        } else {
            if (count(spj_tasks($db, $eventId)) >= SPJ_MAX_TASKS) return ['ok' => false, 'error' => 'A project can have ' . SPJ_MAX_TASKS . ' tasks at most.'];
            $order = (int) $db->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM staff_calendar_checklist WHERE event_id = ' . (int) $eventId)->fetchColumn();
            $db->prepare('INSERT INTO staff_calendar_checklist (event_id, label, is_done, sort_order, assignee_user_id, start_date, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$eventId, $t['t'], $done, $order, $who, $start, $due, $t['status']]);
            $taskId = (int) $db->lastInsertId(); $oldWho = 0;
        }
        spj_after_task_change($db, $taskId, $eventId, $oldWho, $t['who'], $t['due'], $done === 1);
        return ['ok' => true, 'task' => $taskId];
    }

    function spj_task_delete(PDO $db, array $actor, int $eventId, int $taskId): array
    {
        $ev = spj_event($db, $actor, $eventId);
        if (!$ev) return ['ok' => false, 'error' => 'That project was not found.'];
        if (!scal_can_change($actor, $ev)) return ['ok' => false, 'error' => 'Only the person who added the project or the super admin can delete tasks.'];
        if (!spj_task_row($db, $eventId, $taskId)) return ['ok' => false, 'error' => 'That task was not found.'];
        $db->prepare('DELETE FROM staff_calendar_checklist WHERE id = ? AND event_id = ?')->execute([$taskId, $eventId]);
        spj_notice_cancel($db, $taskId);
        return ['ok' => true];
    }

    /** Tick or change the status of a task: the person it is given to, the owner and the super admin. Nobody else. Ticking sets Done. */
    function spj_task_status(PDO $db, array $actor, int $eventId, int $taskId, string $status): array
    {
        $ev = spj_event($db, $actor, $eventId);
        if (!$ev) return ['ok' => false, 'error' => 'That project was not found.'];
        $row = spj_task_row($db, $eventId, $taskId);
        if (!$row) return ['ok' => false, 'error' => 'That task was not found.'];
        $who = (int) $row['assignee_user_id'];
        if (!scal_can_change($actor, $ev) && !($who > 0 && $who === $actor['uid'])) return ['ok' => false, 'error' => 'Only the person this task is given to, the owner or the super admin can change its status.'];
        if (!isset(SPJ_STATUS[$status])) return ['ok' => false, 'error' => 'Choose To do, In progress or Done.'];
        $db->prepare('UPDATE staff_calendar_checklist SET status = ?, is_done = ? WHERE id = ? AND event_id = ?')->execute([$status, $status === 'done' ? 1 : 0, $taskId, $eventId]);
        spj_notice_due($db, $taskId, $eventId, $who, (string) ($row['due_date'] ?? ''), $status === 'done');
        return ['ok' => true, 'status' => $status];
    }

    function spj_delete_event_notices(PDO $db, int $eventId): void
    {
        $db->prepare('DELETE FROM staff_calendar_task_notices WHERE event_id = ?')->execute([$eventId]);
    }
}
