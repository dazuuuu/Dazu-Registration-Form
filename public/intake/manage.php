<?php
// Public: confirmation after booking (?new=1), "Check my booking" (booking number + email) and the secure manage link
// (?t=<token> from the emails). Change class time or mode, move to another intake, cancel, add to calendar (.ics). No login.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/IntakeService.php';

$pdo = \Models\Connection::getInstance()->getConnection();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$self = url('intake/manage.php');
$error = null;
$flash = $_SESSION['ik_flash'] ?? null;
unset($_SESSION['ik_flash']);
if (!ik_installed($pdo)) { header('Location: ' . url('courses/index.php')); exit; }

$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$b = null;
if ($token !== '') {
    if (!ik_rate_ok($pdo, 'manage', 120, 3600)) { http_response_code(429); exit('Too many requests. Please try again later.'); }
    $b = ik_by_token($pdo, $token);
    if (!$b) $error = 'This link is not valid any more. Check your booking with your booking number and email.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'lookup') {
        if (!ik_rate_ok($pdo, 'lookup', 10, 900)) {
            $error = 'Too many tries. Please wait 15 minutes and try again.';
        } elseif ($found = ik_by_number_email($pdo, (string) ($_POST['number'] ?? ''), (string) ($_POST['email'] ?? ''))) {
            header('Location: ' . $self . '?t=' . ik_token($found));
            exit;
        } else {
            $error = 'We could not find a booking with that number and email. Check both and try again.';
        }
    } elseif ($b) {
        $act = (string) ($_POST['action'] ?? '');
        $r = ['ok' => false, 'error' => 'Unknown action.'];
        if ($act === 'cancel') $r = ik_cancel($pdo, $b, 'Cancelled from the manage page');
        elseif ($act === 'move') $r = ik_move($pdo, $b, (int) ($_POST['intake_id'] ?? 0));
        elseif ($act === 'prefs') $r = ik_update_prefs($pdo, $b, ($_POST['mode'] ?? '') === 'physical' ? 'physical' : 'online', (string) ($_POST['class_time'] ?? ''), (string) ($_POST['campus'] ?? ''));
        $_SESSION['ik_flash'] = $r['ok'] ? ['ok', ['cancel' => 'Your booking is cancelled. We sent you an email.', 'move' => 'Your booking is moved. We sent you an email.', 'prefs' => 'Your class choice is saved.'][$act]]
                                         : ['err', $r['error']];
        header('Location: ' . $self . '?t=' . $token);
        exit;
    }
}

$intake = $b ? (ik_intakes($pdo, true)[(int) $b['intake_id']] ?? null) : null;
if ($b && $intake && isset($_GET['ics'])) {
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $b['number'] . '.ics"');
    echo ik_ics($pdo, $b, $intake);
    exit;
}

$tz = ik_tz_label($pdo);
$isNew = $b && !empty($_GET['new']) && (int) ($_SESSION['ik_new'] ?? 0) === (int) $b['id'];
$state = $b ? ik_course_state($pdo, (int) $b['course_id']) : null;
$canEnrolNow = $state && ($state['enrol'] && ik_enrol_check($pdo, (int) $b['course_id'], null)['ok']);
$moves = $b ? array_values(array_filter(ik_bookable_for($pdo, (int) $b['course_id']), fn($i) => (int) $i['id'] !== (int) $b['intake_id'])) : [];
$open = $b && in_array($b['status'], ['booked', 'contacted', 'invited'], true);
$statusTone = ['booked' => 'bg-amber-50 text-amber-700', 'contacted' => 'bg-blue-50 text-blue-700', 'invited' => 'bg-green-50 text-green-700',
               'converted' => 'bg-green-100 text-green-800', 'expired' => 'bg-slate-100 text-slate-600', 'cancelled' => 'bg-red-50 text-red-700'];
$page_title = ($isNew ? 'Your slot is booked' : 'My booking') . ' | Dazu Training Institute';
$btn = 'flex items-center justify-center gap-2 py-3.5 text-xs font-extrabold uppercase tracking-wider rounded-xl';
$extra_head = ($extra_head ?? '') . '<link rel="stylesheet" href="' . htmlspecialchars(url('assets/css/intake.css') . '?v=' . @filemtime(__DIR__ . '/../assets/css/intake.css')) . '">';
ob_start();
?>
<div class="bg-slate-50 min-h-screen py-10 md:py-16">
  <div class="max-w-[760px] mx-auto px-4 space-y-5">
    <?php if ($flash): ?><div class="p-4 rounded-xl text-sm font-semibold <?php echo $flash[0] === 'ok' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-700'; ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>

    <?php if (!$b): ?>
      <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-10">
        <h1 class="text-2xl font-extrabold text-slate-950 tracking-tight">Check my booking</h1>
        <p class="text-xs text-slate-500 font-medium mt-1 mb-6">Enter the booking number from your email (for example DZB-2027-0142) and the email you booked with.</p>
        <?php if ($error): ?><div class="mb-5 p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo $e($error); ?></div><?php endif; ?>
        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?><input type="hidden" name="action" value="lookup">
          <div><label class="block text-[11px] font-extrabold text-slate-700 mb-1.5">Booking number</label><input name="number" required maxlength="20" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-bold uppercase tracking-wider" placeholder="DZB-2027-0000" value="<?php echo $e($_POST['number'] ?? ''); ?>"></div>
          <div><label class="block text-[11px] font-extrabold text-slate-700 mb-1.5">Email address</label><input type="email" name="email" required maxlength="150" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm" value="<?php echo $e($_POST['email'] ?? ''); ?>"></div>
          <button class="w-full <?php echo $btn; ?> text-white" style="background:#44A024">Find my booking</button>
        </form>
      </div>
    <?php else: ?>
      <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-10 text-center">
        <?php if ($b['status'] === 'expired'): ?>
          <div class="w-14 h-14 mx-auto rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mb-3"><svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M12 7v5l3 2"/></svg></div>
          <h1 class="text-2xl font-extrabold text-slate-950 tracking-tight">Your held seat has been released</h1>
          <p class="text-xs text-slate-500 mt-1">Booking <?php echo $e($b['number']); ?> was not used within <?php echo (int) ik_settings($pdo)['hold_days']; ?> days of the invite, so we gave the seat to the next person.</p>
        <?php elseif ($b['status'] === 'cancelled'): ?>
          <h1 class="text-2xl font-extrabold text-slate-950 tracking-tight">This booking is cancelled</h1>
        <?php elseif ($b['status'] === 'converted'): ?>
          <h1 class="text-2xl font-extrabold text-slate-950 tracking-tight">You are enrolled 🎉</h1>
          <p class="text-xs text-slate-500 mt-1">This booking became your enrolment. Your registration number was sent to <?php echo $e($b['email']); ?>.</p>
        <?php else: ?>
          <div class="w-14 h-14 mx-auto rounded-full bg-green-50 flex items-center justify-center mb-3"><svg class="h-7 w-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
          <h1 class="text-2xl font-extrabold text-slate-950 tracking-tight"><?php echo $isNew ? 'Your slot is booked' : 'Your booking'; ?></h1>
          <p class="text-xs text-slate-500 mt-1"><?php echo $isNew ? 'We sent a confirmation to <strong class="text-slate-800">' . $e($b['email']) . '</strong>' : 'Booked with ' . $e($b['email']); ?></p>
        <?php endif; ?>
        <div class="inline-block mt-4 px-6 py-3 rounded-xl text-white" style="background:#373435"><div class="text-[9px] uppercase tracking-widest text-slate-300 font-bold">Booking number</div><div class="text-xl font-extrabold tracking-wider"><?php echo $e($b['number']); ?></div></div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-5 text-left">
          <?php foreach ([['Course', $b['course_title'], ''], ['Intake', $intake['name'] ?? '', $intake ? ik_intake_start($pdo, $intake) : ''],
                          ['Classes', ucfirst($b['mode']) . ($b['mode'] === 'physical' ? ' · ' . (IK_CAMPUSES[$b['campus']] ?? '') : ''), (IK_CLASS_TIMES[$b['mode']][$b['class_time']] ?? '') . ' ' . $tz]] as [$k, $v1, $v2]): ?>
            <div class="bg-slate-50 rounded-xl p-3"><div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?php echo $k; ?></div><div class="text-sm font-bold text-slate-900"><?php echo $e($v1); ?></div><div class="text-xs text-slate-500"><?php echo $e($v2); ?></div></div>
          <?php endforeach; ?>
          <div class="bg-slate-50 rounded-xl p-3"><div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Status</div><span class="inline-block mt-1 text-[11px] font-extrabold px-2 py-0.5 rounded-full <?php echo $statusTone[$b['status']]; ?>"><?php echo IK_STATUS_LABELS[$b['status']]; ?></span>
            <?php if ($b['status'] === 'invited' && $b['hold_until']): ?><div class="text-[11px] text-slate-500 mt-1">Seat held until <?php echo $e(ik_fmt($pdo, ik_ts($b['hold_until']), 'D j M, g:i A')); ?></div><?php endif; ?></div>
        </div>
      </div>

      <?php if ($open): ?>
      <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8">
        <h2 class="text-sm font-extrabold text-slate-950 mb-4">What happens next</h2>
        <?php foreach ([['We keep your seat', 'Your slot is held for the ' . ($intake['name'] ?? '') . ' intake.'],
                        ['We remind you', 'You get an email before the intake opens for enrolment. You can enrol ' . (int) ik_settings($pdo)['early_access_hours'] . ' hours before everyone else.'],
                        ['You enrol and pay', 'Use the link in that email, or enrol now if you want to start sooner.']] as $n => [$t, $d]): ?>
          <div class="flex gap-3 mb-3 last:mb-0"><span class="w-6 h-6 rounded-full text-white text-[11px] font-extrabold flex items-center justify-center flex-shrink-0" style="background:#44A024"><?php echo $n + 1; ?></span>
            <div><div class="text-sm font-bold text-slate-900"><?php echo $e($t); ?></div><div class="text-xs text-slate-500"><?php echo $e($d); ?></div></div></div>
        <?php endforeach; ?>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <a href="<?php echo $e($self . '?t=' . $token . '&ics=1'); ?>" class="<?php echo $btn; ?> bg-white border border-green-600 text-green-700"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="16" rx="2" stroke-width="2"/><path stroke-width="2" d="M4 10h16M9 3v4M15 3v4"/></svg>Add to calendar</a>
        <a href="#cancel" class="<?php echo $btn; ?> bg-white border border-green-600 text-green-700">Cancel booking</a>
        <?php if ($b['status'] === 'invited' && $intake && in_array($intake['status'], ['early_access', 'enrol_open'], true)): ?>
          <a href="<?php echo $e(url('courses/view.php?id=' . (int) $b['course_id'] . '&ea=' . $token)); ?>" class="<?php echo $btn; ?> text-white" style="background:#44A024">Enrol now (early access)</a>
        <?php elseif ($canEnrolNow): ?>
          <a href="<?php echo $e(url('courses/view.php?id=' . (int) $b['course_id'])); ?>" class="<?php echo $btn; ?> text-white" style="background:#44A024">Enrol now instead</a>
        <?php endif; ?>
      </div>

      <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 space-y-6">
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="t" value="<?php echo $e($token); ?>"><input type="hidden" name="action" value="prefs">
          <h2 class="text-sm font-extrabold text-slate-950 mb-3">Change class time or mode</h2>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <select name="mode" class="px-3 py-2.5 border border-slate-200 rounded-xl text-sm" id="ikmMode"><option value="online" <?php echo $b['mode'] === 'online' ? 'selected' : ''; ?>>Online</option><option value="physical" <?php echo $b['mode'] === 'physical' ? 'selected' : ''; ?>>Physical</option></select>
            <select name="class_time" class="px-3 py-2.5 border border-slate-200 rounded-xl text-sm" id="ikmTime">
              <?php foreach (IK_CLASS_TIMES as $m => $times) foreach ($times as $k => $l): ?><option data-mode="<?php echo $m; ?>" value="<?php echo $k; ?>" <?php echo $b['class_time'] === $k && $b['mode'] === $m ? 'selected' : ''; ?>><?php echo $l . ' ' . $e($tz); ?></option><?php endforeach; ?></select>
            <select name="campus" class="px-3 py-2.5 border border-slate-200 rounded-xl text-sm" id="ikmCampus"><option value="">Campus…</option><?php foreach (IK_CAMPUSES as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $b['campus'] === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select>
          </div>
          <button class="mt-3 px-5 py-2.5 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl" style="background:#44A024">Save</button>
        </form>
        <?php if ($moves): ?>
        <form method="POST" class="border-t border-slate-100 pt-5"><?php echo csrf_field(); ?><input type="hidden" name="t" value="<?php echo $e($token); ?>"><input type="hidden" name="action" value="move">
          <h2 class="text-sm font-extrabold text-slate-950 mb-3">Move to another intake</h2>
          <div class="flex gap-2"><select name="intake_id" class="flex-1 px-3 py-2.5 border border-slate-200 rounded-xl text-sm"><?php foreach ($moves as $m): ?><option value="<?php echo (int) $m['id']; ?>"><?php echo $e($m['name'] . ($m['classes_start_on'] ? ' · starts ' . ik_fmt($pdo, strtotime($m['classes_start_on'] . ' 12:00 UTC'), 'j M Y') : '')); ?></option><?php endforeach; ?></select>
            <button class="px-5 py-2.5 border border-green-600 text-green-700 text-xs font-extrabold uppercase tracking-wider rounded-xl">Move</button></div>
        </form>
        <?php endif; ?>
        <form method="POST" id="cancel" class="border-t border-slate-100 pt-5" onsubmit="return confirm('Cancel your booking? Your seat will be given to someone else.')"><?php echo csrf_field(); ?><input type="hidden" name="t" value="<?php echo $e($token); ?>"><input type="hidden" name="action" value="cancel">
          <h2 class="text-sm font-extrabold text-slate-950 mb-1">Cancel my booking</h2>
          <p class="text-xs text-slate-500 mb-3">Your seat is released. You can book again later.</p>
          <button class="px-5 py-2.5 border border-red-300 text-red-700 text-xs font-extrabold uppercase tracking-wider rounded-xl">Cancel booking</button>
        </form>
      </div>
      <?php elseif (in_array($b['status'], ['expired', 'cancelled'], true)): ?>
      <div class="bg-white border border-slate-200 rounded-2xl p-6 space-y-3">
        <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-600"><strong>Don't worry.</strong> If enrolment is still open and seats are left, you can enrol now. Otherwise you can book the next intake.</div>
        <?php if ($canEnrolNow): ?><a href="<?php echo $e(url('courses/view.php?id=' . (int) $b['course_id'])); ?>" class="<?php echo $btn; ?> text-white" style="background:#44A024">Enrol now</a><?php endif; ?>
        <?php if (ik_bookable_for($pdo, (int) $b['course_id'])): ?><a href="<?php echo $e(url('intake/book.php?course=' . (int) $b['course_id'])); ?>" class="<?php echo $btn; ?> bg-white border border-green-600 text-green-700">Book the next intake</a><?php endif; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<script>
(function () {
  var m = document.getElementById('ikmMode'); if (!m) return;
  var t = document.getElementById('ikmTime'), c = document.getElementById('ikmCampus');
  function sync() { var first = null; Array.prototype.forEach.call(t.options, function (o) { var on = o.dataset.mode === m.value; o.hidden = !on; o.disabled = !on; if (on && !first) first = o; });
    if (t.selectedOptions[0] && t.selectedOptions[0].disabled && first) first.selected = true; c.style.display = m.value === 'physical' ? '' : 'none'; c.required = m.value === 'physical'; }
  m.addEventListener('change', sync); sync();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
