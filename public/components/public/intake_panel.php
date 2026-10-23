<?php
/**
 * Intake Booking on the course page (around the unchanged Enrol form).
 *
 * Include once with $ikPart = 'prepare' before any output (reads the early-access link, decides whether the Enrol
 * form shows: sets $ikShowForm), then with $ikPart = 'top' above the form and $ikPart = 'after' below it.
 * Requires: $pdo, $course. Optional: $reg_error (shown in the closed card when the form is hidden).
 * With no intake for this course, $ikState is null and nothing is shown: the page is exactly as before.
 */
require_once __DIR__ . '/../../../app/v1/services/IntakeService.php';
$ikPart = $ikPart ?? 'prepare';
$ike = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

if ($ikPart === 'prepare') {
    $ikState = ik_installed($pdo) ? ik_course_state($pdo, (int) $course['id']) : null;
    $ikShowForm = true;
    $ikBooking = null;
    $ikExpired = null;
    if ($ikState) {
        ik_track_source();
        // Early-access link from the invite email: remember the booking for this visit and fill the form from it.
        if (!empty($_GET['ea']) && ik_rate_ok($pdo, 'link', 60, 3600)) {
            $b = ik_by_token($pdo, (string) $_GET['ea']);
            if ($b && (int) $b['course_id'] === (int) $course['id']) {
                $bi = ik_intakes($pdo)[(int) $b['intake_id']] ?? null;
                if ($b['status'] === 'expired') {
                    $ikExpired = $b;
                } elseif ($bi && in_array($b['status'], ['booked', 'contacted', 'invited'], true) && in_array($bi['status'], ['early_access', 'enrol_open'], true)) {
                    if ($b['status'] !== 'invited') { ik_invite($pdo, $b, $bi); $b = ik_booking($pdo, (int) $b['id']); }
                    ik_remember_booking($b);
                }
            }
        }
        $ikBooking = ik_session_booking($pdo, (int) $course['id']);
        $ikGate = ik_enrol_check($pdo, (int) $course['id'], $ikBooking);
        if (empty($ikGate['booking'])) $ikBooking = null;
        $ikShowForm = $ikGate['ok'] || ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($ikState['enrol']));
        if ($ikBooking && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            // The existing Enrol form reads its values from $_POST: prefill it from the booking.
            $_POST += ['full_name' => $ikBooking['full_name'], 'email' => $ikBooking['email'], 'phone' => $ikBooking['phone'],
                       'secondary_phone' => (string) $ikBooking['phone2'], 'study_mode' => $ikBooking['mode']];
        }
        $ikNext = $ikState['bookable'][0] ?? null;
        $ikNextSeats = $ikNext ? ik_seats($pdo, $ikNext, (int) $course['id']) : null;
    }
    return;
}

if (empty($ikState)) return;
$ikBookUrl = url('intake/book.php?course=' . (int) $course['id']);
$ikManage = url('intake/manage.php');
$ikFormAnchor = '#' . ($pfx ?? 'view') . 'Form';
$tz = ik_tz_label($pdo);

if ($ikPart === 'top'):
    $ikCountdown = ik_countdown($pdo, $ikState);
?>
<link rel="stylesheet" href="<?php echo $ike(url('assets/css/intake.css') . '?v=' . @filemtime(__DIR__ . '/../../assets/css/intake.css')); ?>">
<?php if ($ikExpired): ?>
<div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 mb-6 text-center">
  <div class="w-14 h-14 mx-auto rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mb-3"><svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M12 7v5l3 2"/></svg></div>
  <h2 class="text-xl font-extrabold text-slate-950 tracking-tight">Your held seat has been released</h2>
  <p class="text-xs text-slate-500 font-medium mt-1">Booking <?php echo $ike($ikExpired['number']); ?> was not used within <?php echo (int) ik_settings($pdo)['hold_days']; ?> days of the invite, so we gave the seat to the next person.</p>
  <div class="mt-4 p-3 bg-slate-50 rounded-xl text-xs text-slate-600 text-left"><strong>Don't worry.</strong> If enrolment is still open and seats are left, you can enrol now. Otherwise you can book the next intake.</div>
  <div class="mt-4 space-y-2">
    <?php if ($ikShowForm): ?><a href="<?php echo $ikFormAnchor; ?>" class="block w-full py-3 bg-[#15A24A] text-white text-xs font-extrabold uppercase tracking-wider rounded-xl">Enrol now</a><?php endif; ?>
    <?php if ($ikNext): ?><a href="<?php echo $ike($ikBookUrl); ?>" class="block w-full py-3 border border-green-600 text-green-700 text-xs font-extrabold uppercase tracking-wider rounded-xl">Book the next intake</a><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($ikBooking): $bi = ik_intakes($pdo)[(int) $ikBooking['intake_id']]; $eaEnd = ik_ts($bi['enrol_opens_at']); ?>
<div class="relative overflow-hidden rounded-2xl p-6 md:p-7 mb-6 text-white" style="background:#373435">
  <div class="absolute -right-8 -top-8 w-32 h-32 rotate-12 rounded-3xl" style="background:#44A024"></div>
  <div class="relative">
    <span class="inline-block text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 rounded-full" style="background:#44A024">⚡ Early access</span>
    <h2 class="text-xl md:text-2xl font-extrabold tracking-tight mt-3">Welcome back, <?php echo $ike(ik_first($ikBooking['full_name'])); ?>. You can enrol first.</h2>
    <p class="text-xs text-slate-300 mt-1">You booked with number <strong class="text-white"><?php echo $ike($ikBooking['number']); ?></strong> for the <?php echo $ike($bi['name']); ?> intake.</p>
    <div class="flex flex-wrap gap-3 mt-4">
      <?php if ($bi['status'] === 'early_access'): ?>
      <div class="rounded-xl px-4 py-2" style="background:rgba(255,255,255,.1)"><div class="text-[9px] uppercase tracking-widest text-slate-300 font-bold">Early access ends in</div><div class="text-lg font-extrabold" data-ik-countdown="<?php echo $eaEnd; ?>">—</div></div>
      <?php endif; ?>
      <?php if ($ikBooking['hold_until']): ?>
      <div class="rounded-xl px-4 py-2" style="background:rgba(255,255,255,.1)"><div class="text-[9px] uppercase tracking-widest text-slate-300 font-bold">Your seat is held until</div><div class="text-lg font-extrabold"><?php echo $ike(ik_fmt($pdo, ik_ts($ikBooking['hold_until']), 'D, j M, g:i A')); ?> <span class="text-xs font-bold"><?php echo $ike($tz); ?></span></div></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php elseif (!$ikShowForm): $lc = $ikState['last_closed']; ?>
<div class="bg-white border border-slate-200 rounded-2xl p-6 mb-6" style="border-left:4px solid #373435">
  <div class="flex items-start gap-3">
    <span class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center flex-shrink-0"><svg class="h-5 w-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2" stroke-width="2"/><path stroke-width="2" d="M8 11V7a4 4 0 118 0v4"/></svg></span>
    <div>
      <?php if (!empty($ikState['enrol'])): ?><h2 class="text-lg font-extrabold text-slate-950 tracking-tight">This course is full for the <?php echo $ike($ikState['enrol']['name']); ?> intake</h2>
      <p class="text-xs text-slate-500 font-medium mt-0.5">All seats are taken. A seat may come free if someone cancels.</p>
      <?php else: ?><h2 class="text-lg font-extrabold text-slate-950 tracking-tight">Hello, our intake has closed</h2>
      <?php if ($lc): ?><p class="text-xs text-slate-500 font-medium mt-0.5">Enrolment for the <?php echo $ike($lc['name']); ?> intake closed on <?php echo $ike(ik_fmt($pdo, min(time(), ik_ts($lc['enrol_closes_at'])), 'j F Y')); ?>.</p>
      <?php else: ?><p class="text-xs text-slate-500 font-medium mt-0.5">Enrolment for the next intake has not opened yet.</p><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <p class="text-sm text-slate-700 mt-3"><?php if ($ikNext): ?>You can still <strong>book your slot in the next intake</strong>. We will keep a seat for you and invite you first when enrolment opens.<?php else: ?>Leave your details below and we will tell you as soon as the next intake opens.<?php endif; ?></p>
  <?php if (!empty($reg_error)): ?><div class="mt-3 p-3 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo $ike($reg_error); ?></div><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($ikNext && !$ikBooking):
    $full = $ikNextSeats['left'] !== null && $ikNextSeats['left'] <= 0;
    $startTs = $ikNext['classes_start_on'] ? strtotime($ikNext['classes_start_on'] . ' 12:00 UTC') : ik_ts($ikNext['enrol_opens_at']); ?>
<div class="rounded-2xl p-6 mb-6 border-2" style="border-color:#44A024;background:linear-gradient(180deg,#f3faef,#fff 60%)">
  <div class="flex items-center gap-2"><span class="text-[9px] uppercase tracking-widest font-extrabold px-2 py-0.5 rounded-full text-white" style="background:#44A024">New</span><span class="text-[10px] uppercase tracking-widest font-extrabold text-slate-500">Next intake</span></div>
  <h2 class="text-lg md:text-xl font-extrabold text-slate-950 tracking-tight mt-2"><?php echo $ikShowForm ? 'Not ready to start now? Book your slot.' : 'Book your slot in the next intake'; ?></h2>
  <p class="text-xs text-slate-500 font-medium">Reserve your seat<?php echo $ikShowForm ? ' in the next intake' : ' now'; ?>. No payment needed to book.</p>
  <div class="mt-4 bg-white border border-slate-200 rounded-xl p-3 flex items-center gap-3">
    <div class="w-12 rounded-lg overflow-hidden text-center flex-shrink-0 border border-slate-200"><div class="text-[9px] font-extrabold uppercase text-white py-0.5" style="background:#44A024"><?php echo $ike(ik_fmt($pdo, $startTs, 'M')); ?></div><div class="text-lg font-extrabold text-white py-0.5" style="background:#373435"><?php echo $ike(ik_fmt($pdo, $startTs, 'j')); ?></div></div>
    <div class="flex-1 min-w-0">
      <div class="text-sm font-extrabold text-slate-900"><?php echo $ike($ikNext['name']); ?> intake</div>
      <div class="text-xs text-slate-500"><?php echo $ikNext['classes_start_on'] ? 'Starts ' . $ike(ik_fmt($pdo, $startTs, 'l, j F Y')) : 'Enrolment opens ' . $ike(ik_fmt($pdo, $startTs, 'j F Y')); ?></div>
      <?php if ($ikNextSeats['limit'] !== null): $pct = $ikNextSeats['limit'] ? min(100, round($ikNextSeats['taken'] / $ikNextSeats['limit'] * 100)) : 100; ?>
      <div class="h-1.5 bg-slate-100 rounded-full mt-2 overflow-hidden"><div class="h-full rounded-full" style="width:<?php echo $pct; ?>%;background:#44A024"></div></div>
      <div class="text-[11px] font-bold mt-1" style="color:#b7791f"><?php echo $full ? 'Full: join the waiting list' : (int) $ikNextSeats['left'] . ' of ' . (int) $ikNextSeats['limit'] . ' seats left'; ?></div>
      <?php endif; ?>
    </div>
  </div>
  <a href="<?php echo $ike($ikBookUrl); ?>" class="mt-4 flex items-center justify-center gap-2 w-full py-3.5 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl shadow-sm" style="background:#44A024">
    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2h12a1 1 0 011 1v19l-7-4-7 4V3a1 1 0 011-1z"/></svg><?php echo $full ? 'Join the waiting list' : 'Book my slot'; ?></a>
  <p class="text-[11px] text-slate-500 text-center mt-2"><?php echo $ikShowForm ? 'Want to start right away? Use the Enrol form below.' : 'Already booked? <a class="font-bold text-green-700" href="' . $ike($ikManage) . '">Check my booking</a>'; ?></p>
</div>
<?php elseif (!$ikNext && !$ikShowForm && !$ikBooking): ?>
<div class="bg-white border border-slate-200 rounded-2xl p-6 mb-6">
  <h2 class="text-lg font-extrabold text-slate-950 tracking-tight">Tell me when the next intake opens</h2>
  <p class="text-xs text-slate-500 font-medium mb-4">We send you one email when bookings open. Nothing else.</p>
  <form method="POST" action="<?php echo $ike(url('intake/book.php?course=' . (int) $course['id'])); ?>" class="space-y-3">
    <?php echo csrf_field(); ?><input type="hidden" name="action" value="interest">
    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
    <input type="text" name="name" required maxlength="150" placeholder="Full name" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm">
    <input type="email" name="email" required maxlength="150" placeholder="Email address" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm">
    <input type="tel" name="phone" maxlength="20" placeholder="Phone (optional)" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm">
    <button class="w-full py-3 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl" style="background:#44A024">Tell me</button>
  </form>
</div>
<?php endif; ?>
<?php if ($ikCountdown && !$ikBooking): ?><p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-4 -mt-3 text-center"><svg class="ik-inline-ico h-3.5 w-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><path stroke-width="2" d="M12 7v5l3 2"/></svg><?php echo $ike($ikCountdown); ?></p><?php endif; ?>

<?php elseif ($ikPart === 'after'): ?>
<?php if ($ikBooking): $moves = array_values(array_filter($ikState['bookable'], fn($i) => (int) $i['id'] !== (int) $ikBooking['intake_id'])); ?>
<p class="text-xs text-slate-500 mt-4 text-center"><svg class="ik-inline-ico h-3.5 w-3.5 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-6h2zm0-8h-2V7h2z"/></svg>Not ready?
  <a class="font-bold text-green-700" href="<?php echo $ike(url('intake/manage.php?t=' . ik_token($ikBooking))); ?>"><?php echo $moves ? 'Move my booking to ' . $ike($moves[0]['name']) : 'Manage my booking'; ?></a> or
  <a class="font-bold text-green-700" href="<?php echo $ike(url('intake/manage.php?t=' . ik_token($ikBooking) . '#cancel')); ?>">cancel it</a>.</p>
<?php endif; ?>
<?php if ($ikNext || $ikShowForm): ?>
<div class="lg:hidden h-20"></div>
<div class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-slate-200 px-4 py-3 flex gap-3" style="padding-bottom:max(12px,env(safe-area-inset-bottom))">
  <?php if ($ikNext && !$ikBooking): ?><a href="<?php echo $ike($ikBookUrl); ?>" class="flex-1 flex items-center justify-center gap-2 py-3 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl" style="background:#44A024"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2h12a1 1 0 011 1v19l-7-4-7 4V3a1 1 0 011-1z"/></svg>Book next intake</a><?php endif; ?>
  <?php if ($ikShowForm): ?><a href="<?php echo $ikFormAnchor; ?>" class="flex-1 flex items-center justify-center py-3 text-xs font-extrabold uppercase tracking-wider rounded-xl <?php echo $ikNext && !$ikBooking ? 'border border-green-600 text-green-700' : 'text-white'; ?>" style="<?php echo $ikNext && !$ikBooking ? '' : 'background:#44A024'; ?>">Enrol now</a><?php endif; ?>
</div>
<?php endif; ?>
<script>
document.querySelectorAll('[data-ik-countdown]').forEach(function (el) {
  var end = +el.dataset.ikCountdown * 1000;
  function tick() { var s = Math.max(0, Math.floor((end - Date.now()) / 1000)); el.textContent = Math.floor(s / 3600) + 'h ' + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + 'm'; }
  tick(); setInterval(tick, 30000);
});
</script>
<?php endif;
