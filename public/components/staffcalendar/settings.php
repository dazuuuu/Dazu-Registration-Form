<?php
// public/components/staffcalendar/settings.php — "Settings · Staff Calendar". SUPER ADMIN ONLY: boot.php refuses everyone else with the real role read from the database, on every request, and every
// action below is checked again in the services. This is a different thing from the account "Settings" menu item, which it does not touch.
// Tabs: Event types · Birthday messages · Holidays · Gifts · Notifications. Notifications only SAVES the team defaults for now: nothing is sent from here.
$sc_portal = 'superadmin';
$page_title = 'Settings · Staff Calendar';
$sa_custom_header = true;
require __DIR__ . '/boot.php';
require_once __DIR__ . '/../../../app/v1/services/StaffSettings.php';
require_once __DIR__ . '/../../../app/v1/services/StaffHolidays.php';
require_once __DIR__ . '/../../../app/v1/services/StaffGifts.php';

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$self = url('superadmin/staffcalendar/settings.php');
$calUrl = url('superadmin/staffcalendar/index.php');
if (!sst_installed($db) || !sh_installed($db)) {
    $content = '<div class="alert alert-warning">The Staff Calendar settings are not installed yet. Ask the office to run System Updates (migrations 119 and 120).</div>';
    return;
}
$tabs = ['types' => 'Event types', 'messages' => 'Birthday messages', 'holidays' => 'Holidays', 'gifts' => 'Gifts', 'notifications' => 'Notifications'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'types';
$tag = in_array($_GET['tag'] ?? '', SBD_TAGS, true) ? $_GET['tag'] : '';
$status = in_array($_GET['status'] ?? '', ['approved', 'pending', 'rejected', 'off'], true) ? $_GET['status'] : '';
$thisYear = (int) scal_now()->format('Y');
$year = (int) ($_GET['year'] ?? $thisYear); if ($year < 2000 || $year > 2200) $year = $thisYear;
$back = fn() => $self . '?' . http_build_query(array_filter(['tab' => 'messages', 'tag' => $tag, 'status' => $status]));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = false; $msg = ''; $goTab = isset($tabs[$_POST['tab'] ?? '']) ? $_POST['tab'] : 'types'; $goYear = (int) ($_POST['year'] ?? 0);
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $msg = 'Security check failed. Please try again.';
    else {
        $id = (int) ($_POST['id'] ?? 0); $key = (string) ($_POST['key'] ?? '');
        switch ((string) ($_POST['op'] ?? '')) {
            // event types
            case 'type_active': $r = sst_type_active($db, $actor, $key, !empty($_POST['on'])); $msg = $r['ok'] ? (!empty($_POST['on']) ? 'Type switched on.' : 'Type switched off. Its events are hidden, not deleted.') : $r['error']; break;
            case 'type_color':  $r = sst_type_color($db, $actor, $key, (string) ($_POST['color'] ?? '')); $msg = $r['ok'] ? 'Colour saved.' : $r['error']; break;
            case 'type_add':    $r = sst_type_add($db, $actor, (string) ($_POST['name'] ?? ''), (string) ($_POST['color'] ?? '')); $msg = $r['ok'] ? 'Type added.' : $r['error']; break;
            case 'type_delete': $r = sst_type_delete($db, $actor, $key); $msg = $r['ok'] ? 'Type deleted.' : $r['error']; break;
            // birthday messages (the one library)
            case 'add':     $r = sbd_msg_add($db, $actor, $_POST); $msg = $r['ok'] ? 'Message added.' : $r['error']; break;
            case 'edit':    $r = sbd_msg_edit($db, $actor, $id, $_POST); $msg = $r['ok'] ? 'Message saved.' : $r['error']; break;
            case 'on':      $r = sbd_msg_active($db, $actor, $id, true); $msg = $r['ok'] ? 'Switched on.' : $r['error']; break;
            case 'off':     $r = sbd_msg_active($db, $actor, $id, false); $msg = $r['ok'] ? 'Switched off.' : $r['error']; break;
            case 'approve': $r = sbd_msg_review($db, $actor, $id, true); $msg = $r['ok'] ? 'Suggestion approved. It is now in use.' : $r['error']; break;
            case 'reject':  $r = sbd_msg_review($db, $actor, $id, false); $msg = $r['ok'] ? 'Suggestion rejected.' : $r['error']; break;
            // holidays
            case 'hol_add':     $r = sh_add($db, $actor, $_POST); $msg = $r['ok'] ? 'Holiday added.' : $r['error']; break;
            case 'hol_edit':    $r = sh_edit($db, $actor, $id, $_POST); $msg = $r['ok'] ? 'Holiday saved.' : $r['error']; break;
            case 'hol_confirm': $r = sh_confirm($db, $actor, $id, (string) ($_POST['date'] ?? '')); $msg = $r['ok'] ? 'Date confirmed.' : $r['error']; break;
            case 'hol_delete':  $r = sh_delete($db, $actor, $id); $msg = $r['ok'] ? 'Holiday removed.' : $r['error']; break;
            case 'hol_fill':    $r = sh_fill_year($db, $actor, (int) ($_POST['fill_year'] ?? 0)); $msg = $r['ok'] ? ($r['added'] . ' holiday' . ($r['added'] === 1 ? '' : 's') . ' added for ' . (int) $_POST['fill_year'] . '.') : $r['error']; if ($r['ok']) $goYear = (int) $_POST['fill_year']; break;
            // gifts (the same settings as the gift tracker)
            case 'gift_amount':    $r = sg_set_amount($db, $actor, $_POST['amount'] ?? ''); $msg = $r['ok'] ? 'The gift amount is saved. It applies to new birthdays only.' : $r['error']; break;
            case 'gift_exclude':   $r = sg_set_excluded($db, $actor, (int) ($_POST['user_id'] ?? 0), true, (string) ($_POST['note'] ?? '')); $msg = $r['ok'] ? 'Saved. It applies to birthdays from now on.' : $r['error']; break;
            case 'gift_unexclude': $r = sg_set_excluded($db, $actor, (int) ($_POST['user_id'] ?? 0), false, ''); $msg = $r['ok'] ? 'Removed from the excluded list.' : $r['error']; break;
            // notifications (team defaults: only saved for now)
            case 'team_save': $r = sst_team_save($db, $actor, $_POST); $msg = $r['ok'] ? 'The team defaults are saved.' : $r['error']; break;
            default: $r = ['ok' => false]; $msg = 'Unknown action.';
        }
        $ok = !empty($r['ok']);
    }
    SessionModel::setFlash($ok ? 'success' : 'error', $e($msg));
    $q = ['tab' => $goTab];
    if ($goYear >= 2000 && $goYear <= 2200 && $goTab === 'holidays') $q['year'] = $goYear;
    if ($goTab === 'messages') { if (in_array($_GET['tag'] ?? '', SBD_TAGS, true)) $q['tag'] = $_GET['tag']; if (in_array($_GET['status'] ?? '', ['approved', 'pending', 'rejected', 'off'], true)) $q['status'] = $_GET['status']; }
    header('Location: ' . $self . '?' . http_build_query($q));
    exit;
}

$hidden = fn(string $op, string $tabName, array $extra = []) => csrf_field() . '<input type="hidden" name="tab" value="' . $e($tabName) . '"><input type="hidden" name="op" value="' . $e($op) . '">'
    . implode('', array_map(fn($k, $v) => '<input type="hidden" name="' . $e($k) . '" value="' . $e($v) . '">', array_keys($extra), $extra));

ob_start();
?>
<style>
.st{--g:#048C44;--gd:#037A3B;--gl:#E3F3EA;--mut:#6b7067;--line:#e6e9e4;--ink:#1d1b1c;color:var(--ink);font-family:inherit;max-width:1240px}
.st *{box-sizing:border-box}.st [hidden]{display:none!important}
.st h1{font-size:1.5rem;font-weight:800;margin:0 0 2px}.st .sub{color:var(--mut);font-size:.9rem;margin:0 0 6px}
.st a.back{color:var(--gd);font-weight:700;text-decoration:none;font-size:.88rem}
.st-tabs{display:flex;gap:6px;border-bottom:1px solid var(--line);margin:14px 0 18px;overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch}
.st-tabs a{padding:10px 16px;color:#4b524a;text-decoration:none;font-weight:600;font-size:.95rem;border-bottom:3px solid transparent;margin-bottom:-1px;flex:none}
.st-tabs a.on{color:var(--gd);border-bottom-color:var(--g)}
.st-card{background:#fff;border-radius:18px;box-shadow:0 1px 2px rgba(29,27,28,.04),0 6px 20px rgba(29,27,28,.05);padding:20px 24px;margin-bottom:18px}
.st-card h2{font-size:1.15rem;font-weight:700;margin:0}
.st-ch{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
.st-note{color:var(--mut);font-size:.84rem;margin:10px 0 0}
.st-btn{background:var(--g);color:#fff;border:0;border-radius:12px;padding:9px 18px;font-weight:700;font-size:.88rem;cursor:pointer;font-family:inherit;display:inline-flex;gap:6px;align-items:center;justify-content:center;text-decoration:none}
.st-btn:hover{background:var(--gd);color:#fff}.st-btn.ghost{background:#fff;color:var(--ink);border:1px solid var(--line)}.st-btn.ghost:hover{background:var(--gl);color:var(--gd)}.st-btn.danger{background:#fff;color:#b42318;border:1px solid #f1c9c4}
.st-btn.sm{padding:6px 12px;font-size:.8rem;border-radius:10px}
.st input[type=text],.st input[type=number],.st input[type=date],.st select,.st textarea{border:1px solid #d8ddd5;border-radius:12px;padding:9px 12px;font-family:inherit;font-size:.95rem;background:#fff;color:var(--ink);max-width:100%}
.st input[type=color]{width:34px;height:34px;padding:2px;border:1px solid #d8ddd5;border-radius:10px;background:#fff;cursor:pointer}
.st-trow{display:flex;gap:12px;align-items:center;padding:10px 0;border-top:1px solid var(--line)}
.st-trow .nm{flex:1;min-width:0;font-weight:500}.st-trow small{color:var(--mut);font-weight:400;margin-left:8px}
.st-trow.off .nm{color:var(--mut)}
.st-dot{width:16px;height:16px;border-radius:5px;flex:none}
.st-switch{position:relative;width:50px;height:28px;border-radius:999px;border:0;background:#d8ddd5;cursor:pointer;flex:none;padding:0;transition:background .15s}
.st-switch:before{content:"";position:absolute;top:3px;left:3px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:transform .15s}
.st-switch.on{background:var(--g)}.st-switch.on:before{transform:translateX(22px)}
.st-add{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.st-years{display:inline-flex;flex-wrap:wrap;background:#f1f3ef;border-radius:999px;padding:3px;gap:2px}
.st-years a{padding:5px 14px;border-radius:999px;color:#4b524a;text-decoration:none;font-size:.85rem;font-weight:600}.st-years a.on{background:var(--g);color:#fff}
.st-hrow{display:grid;grid-template-columns:90px minmax(0,1fr) auto;gap:12px;align-items:center;padding:9px 0;border-top:1px solid var(--line)}
.st-hrow .dt{color:var(--mut);font-size:.9rem}.st-hrow .nm{font-weight:500}.st-hrow .nt{color:var(--mut);font-size:.8rem;display:block}
.st-pill{background:#fdf0d5;color:#9a6700;border-radius:999px;padding:3px 12px;font-size:.76rem;font-weight:700;white-space:nowrap}
.st-hrow details[open]{grid-column:1/-1}.st-hrow summary{cursor:pointer;color:var(--gd);font-weight:700;font-size:.84rem;list-style:none;display:inline-block}
.st-hrow summary::-webkit-details-marker{display:none}
.st-hform{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px}
.st-sw{display:flex;align-items:center;gap:12px;padding:12px 0;border-top:1px solid var(--line)}.st-sw span{flex:1}
.st-sw input{appearance:none;-webkit-appearance:none;width:50px;height:28px;border-radius:999px;background:#d8ddd5;position:relative;cursor:pointer;border:0;margin:0;flex:none;transition:background .15s}
.st-sw input:before{content:"";position:absolute;top:3px;left:3px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:transform .15s}
.st-sw input:checked{background:var(--g)}.st-sw input:checked:before{transform:translateX(22px)}
.st-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}.st label.l{display:block;font-size:.8rem;color:var(--mut);margin:0 0 4px}
@media (max-width:767.98px){
  .st h1{font-size:1.25rem}.st-card{padding:16px;border-radius:16px}.st-grid2{grid-template-columns:1fr}
  .st-hrow{grid-template-columns:70px minmax(0,1fr)}.st-hrow details{grid-column:1/-1}
  .st-btn{padding:11px 18px}.st-btn.sm{padding:9px 14px}.st-switch,.st-sw input{width:56px;height:32px}.st-switch:before,.st-sw input:before{width:26px;height:26px}.st-switch.on:before,.st-sw input:checked:before{transform:translateX(24px)}
  .st-trow{flex-wrap:wrap}.st-add input[type=text]{flex:1 1 100%}
}
</style>
<div class="st">
    <a class="back" href="<?php echo $e($calUrl); ?>"><i class="fas fa-arrow-left"></i> Staff Calendar</a>
    <h1>Settings · Staff Calendar</h1>
    <p class="sub">Event types, birthday messages, holidays and gifts</p>
    <nav class="st-tabs" aria-label="Settings sections"><?php foreach ($tabs as $k => $l): ?><a href="<?php echo $e($self . '?tab=' . $k); ?>" class="<?php echo $tab === $k ? 'on' : ''; ?>"<?php echo $tab === $k ? ' aria-current="page"' : ''; ?>><?php echo $e($l); ?></a><?php endforeach; ?></nav>

<?php if ($tab === 'types'): $types = sst_types($db); ?>
    <section class="st-card">
        <div class="st-ch"><h2>Event types</h2></div>
        <form class="st-add" method="post" action="<?php echo $e($self); ?>"><?php echo $hidden('type_add', 'types'); ?>
            <input type="text" name="name" maxlength="40" placeholder="Name of a new type" aria-label="Name of a new type" required><input type="color" name="color" value="#048C44" aria-label="Colour"><button class="st-btn"><i class="fas fa-plus"></i> Add type</button></form>
        <div style="margin-top:12px">
        <?php foreach ($types as $t): ?>
        <div class="st-trow<?php echo $t['active'] ? '' : ' off'; ?>">
            <form method="post" action="<?php echo $e($self); ?>" style="display:flex;gap:10px;align-items:center;flex:1;min-width:0"><?php echo $hidden('type_color', 'types', ['key' => $t['key']]); ?>
                <i class="st-dot" style="background:<?php echo $e($t['color']); ?>"></i><input type="color" name="color" value="<?php echo $e($t['color']); ?>" aria-label="Colour of <?php echo $e($t['label']); ?>">
                <span class="nm"><?php echo $e($t['label']); ?><small><?php echo (int) $t['events']; ?> event<?php echo $t['events'] === 1 ? '' : 's'; ?></small></span>
                <button class="st-btn ghost sm">Save colour</button></form>
            <?php if ($t['can_delete']): ?><form method="post" action="<?php echo $e($self); ?>" onsubmit="return confirm('Delete this type? This cannot be undone.')"><?php echo $hidden('type_delete', 'types', ['key' => $t['key']]); ?><button class="st-btn danger sm">Delete</button></form><?php endif; ?>
            <form method="post" action="<?php echo $e($self); ?>"><?php echo $hidden('type_active', 'types', ['key' => $t['key'], 'on' => $t['active'] ? 0 : 1]); ?>
                <button class="st-switch<?php echo $t['active'] ? ' on' : ''; ?>" role="switch" aria-checked="<?php echo $t['active'] ? 'true' : 'false'; ?>" aria-label="<?php echo $e($t['label']); ?> is <?php echo $t['active'] ? 'on' : 'off'; ?>"></button></form>
        </div>
        <?php endforeach; ?>
        </div>
        <p class="st-note">Colours can be changed. A type with events can be switched off but not deleted: switching it off hides its events everywhere on the calendar and removes it from the New event form, and the events are kept. Birthday, Holiday and Project are used by those features and are never deleted.</p>
    </section>

<?php elseif ($tab === 'messages'): ?>
    <?php require __DIR__ . '/settings_messages.php'; ?>

<?php elseif ($tab === 'holidays'): $hols = sh_year($db, $year); $years = sh_years($db); if (!in_array($year, $years, true)) $years[] = $year; sort($years); ?>
    <section class="st-card">
        <div class="st-ch"><h2>Holidays · <?php echo (int) $year; ?></h2>
            <div class="st-years"><?php foreach ($years as $y): ?><a href="<?php echo $e($self . '?tab=holidays&year=' . $y); ?>" class="<?php echo $y === $year ? 'on' : ''; ?>"><?php echo (int) $y; ?></a><?php endforeach; ?></div></div>
        <?php if (!$hols): ?><p class="st-note" style="margin:0 0 8px">No holidays for <?php echo (int) $year; ?> yet.</p>
            <form method="post" action="<?php echo $e($self); ?>"><?php echo $hidden('hol_fill', 'holidays', ['fill_year' => $year, 'year' => $year]); ?><button class="st-btn"><i class="fas fa-calendar-plus"></i> Fill in <?php echo (int) $year; ?></button></form><?php endif; ?>
        <?php foreach ($hols as $h): ?>
        <div class="st-hrow">
            <span class="dt"><?php echo $e((new DateTime($h['date'], scal_tz()))->format('j M')); ?></span>
            <span class="nm"><?php echo $e($h['name']); ?><?php if (!$h['confirmed']): ?> <span class="st-pill">Expected, to be confirmed</span><?php endif; ?><?php echo $h['note'] !== '' ? '<span class="nt">' . $e($h['note']) . '</span>' : ''; ?></span>
            <details><summary>Edit<?php echo !$h['confirmed'] ? ' or confirm' : ''; ?></summary>
                <?php if (!$h['confirmed']): ?>
                <form class="st-hform" method="post" action="<?php echo $e($self); ?>"><?php echo $hidden('hol_confirm', 'holidays', ['id' => $h['id'], 'year' => $year]); ?><label class="l" style="margin:0">Announced date</label><input type="date" name="date" value="<?php echo $e($h['date']); ?>" required><button class="st-btn sm">Confirm date</button></form>
                <?php endif; ?>
                <form class="st-hform" method="post" action="<?php echo $e($self); ?>"><?php echo $hidden('hol_edit', 'holidays', ['id' => $h['id'], 'year' => $year]); ?><input type="date" name="date" value="<?php echo $e($h['date']); ?>" required aria-label="Date"><input type="text" name="name" value="<?php echo $e($h['name']); ?>" maxlength="120" required aria-label="Name"><input type="text" name="note" value="<?php echo $e($h['note']); ?>" maxlength="160" placeholder="Note" aria-label="Note"><button class="st-btn ghost sm">Save</button></form>
                <form class="st-hform" method="post" action="<?php echo $e($self); ?>" onsubmit="return confirm('Remove this holiday?')"><?php echo $hidden('hol_delete', 'holidays', ['id' => $h['id'], 'year' => $year]); ?><button class="st-btn danger sm">Remove</button></form>
            </details>
        </div>
        <?php endforeach; ?>
        <p class="st-note">Kenyan public holidays. A holiday on a Sunday is kept on the Monday after it. Good Friday and Easter Monday are worked out from Easter. Eid ul-Fitr has no fixed date: the expected date is shown until you confirm the announced date.</p>
    </section>
    <section class="st-card">
        <h2 style="margin-bottom:10px">Add a holiday</h2>
        <form class="st-add" method="post" action="<?php echo $e($self); ?>"><?php echo $hidden('hol_add', 'holidays', ['year' => $year]); ?>
            <input type="date" name="date" value="<?php echo (int) $year; ?>-01-01" required aria-label="Date"><input type="text" name="name" maxlength="120" placeholder="Name of the holiday" required aria-label="Name"><input type="text" name="note" maxlength="160" placeholder="Note (optional)" aria-label="Note"><button class="st-btn"><i class="fas fa-plus"></i> Add</button></form>
        <form class="st-add" method="post" action="<?php echo $e($self); ?>" style="margin-top:14px"><?php echo $hidden('hol_fill', 'holidays'); ?>
            <label for="fy" class="l" style="margin:0">Add the standard holidays for another year</label><input type="number" id="fy" name="fill_year" min="2000" max="2200" value="<?php echo (int) (max($years) + 1); ?>" style="width:110px"><button class="st-btn ghost">Fill in year</button></form>
    </section>

<?php elseif ($tab === 'gifts'): $gOn = sg_installed($db); ?>
    <?php if (!$gOn): ?><div class="alert alert-warning">The gift tracker is not installed yet (migration 117).</div><?php else:
    $amount = sg_amount($db); $gpeople = sg_people($db); $gexcl = sg_exclusions($db); ?>
    <section class="st-card">
        <div class="st-ch"><h2>Gifts</h2><a class="st-btn ghost sm" href="<?php echo $e(url('superadmin/staffcalendar/gifts.php')); ?>">Gift history</a></div>
        <div class="st-grid2">
            <form method="post" action="<?php echo $e($self); ?>"><?php echo $hidden('gift_amount', 'gifts'); ?><label class="l" for="ga">Amount from each person (KES)</label>
                <div class="st-add"><input type="number" id="ga" name="amount" min="100" max="1000000" value="<?php echo (int) $amount; ?>" required style="width:170px"><button class="st-btn">Save</button></div></form>
            <div><label class="l" for="gr">Reminder button</label><select id="gr" disabled aria-label="Reminder button"><option>Once a day</option></select></div>
        </div>
        <p class="st-note">A new amount applies to new birthdays only. Lists that are already made keep the amount they were made with. The reminder button can be used once a day for each birthday.</p>
        <h2 style="font-size:.78rem;letter-spacing:.12em;color:var(--mut);text-transform:uppercase;margin:22px 0 4px">Excluded from gifts</h2>
        <?php if (!$gexcl): ?><p class="st-note" style="margin:6px 0">Nobody is excluded.</p><?php endif; ?>
        <?php foreach ($gexcl as $uid => $note): $gp = $gpeople[$uid] ?? null; if (!$gp) continue; ?>
        <div class="st-trow"><span class="nm"><?php echo $e($gp['name']); ?><?php echo $note !== '' ? '<small>· ' . $e($note) . '</small>' : ''; ?></span>
            <form method="post" action="<?php echo $e($self); ?>"><?php echo $hidden('gift_unexclude', 'gifts', ['user_id' => $uid]); ?><button class="st-btn ghost sm">Remove</button></form></div>
        <?php endforeach; ?>
        <form class="st-add" method="post" action="<?php echo $e($self); ?>" style="margin-top:12px"><?php echo $hidden('gift_exclude', 'gifts'); ?>
            <select name="user_id" aria-label="Person" required><option value="">Exclude someone…</option><?php foreach ($gpeople as $uid => $gp): if (isset($gexcl[$uid])) continue; ?><option value="<?php echo (int) $uid; ?>"><?php echo $e($gp['name']); ?></option><?php endforeach; ?></select>
            <input type="text" name="note" maxlength="120" placeholder="Note, e.g. on leave" aria-label="Note"><button class="st-btn"><i class="fas fa-plus"></i> Exclude</button></form>
        <p class="st-note">A person who is excluded appears on new gift lists as “Excluded” and is not asked for a gift. It does not change lists that are already made.</p>
    </section>
    <?php endif; ?>

<?php else: $defs = sst_team_defaults($db); ?>
    <section class="st-card">
        <div class="st-ch"><h2>Team defaults</h2></div>
        <form method="post" action="<?php echo $e($self); ?>"><?php echo $hidden('team_save', 'notifications'); ?>
            <?php foreach (SST_PREFS as $k => [$label, $setting]): ?>
            <label class="st-sw"><span><?php echo $e($k === 'notice_birthday' ? 'The birthday notice' : ($k === 'notice_gift' ? 'The gift reminder' : $label)); ?></span><input type="checkbox" name="<?php echo $e($k); ?>" value="1"<?php echo $defs[$k] ? ' checked' : ''; ?>></label>
            <?php endforeach; ?>
            <div style="margin-top:14px"><button class="st-btn">Save defaults</button></div>
        </form>
        <p class="st-note">These are the choices new events start with. Each person can turn things off for themselves in “My settings” on the Staff Calendar. They are only saved for now: nothing is sent from here.</p>
    </section>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
