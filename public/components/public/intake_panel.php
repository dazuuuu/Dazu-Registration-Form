<?php
/**
 * Enrolment and Book now on the course page (simple enrolment, migrations 086 and 101).
 *
 * Included three times by courses/view.php and courses/share.php: $ikPart = 'prepare' (before output), 'top' (above the Enroll form) and
 * 'after' (below it).
 *   - Enrolment open: the Enroll form shows ("Enroll now") and, above it, a card with Book now for the next intake.
 *   - Enrolment closed: with "Hide the Enroll form when enrolment is closed" on (the default) the form is hidden and the page says
 *     "Hello, our intake has closed. ..." with Book now for the next intake and "Already booked? Check my booking". The server refuses an
 *     enrolment too (CourseRegistrationHandler), so a direct link does not get around this.
 *   - The bar at the bottom of a phone shows "Book now" and, only when enrolment is open, "Enroll now".
 *   - Early access: a person who booked, paid and was invited opens the course page with ?ea=<secure token>. They see a banner (countdown and
 *     "seat held until") and the Enroll form, already filled in from the booking. Everyone else still sees Book now only. Under the form:
 *     "Not ready? Move my booking to the next intake or cancel it." A person whose hold ended sees that the seat was released.
 * Requires: $pdo, $course.
 */
require_once __DIR__ . '/../../../app/v1/services/EnrolmentService.php';
$ikPart = $ikPart ?? 'prepare';

if ($ikPart === 'prepare') {
    $ikShowForm = true;
    $enState = ['open' => false, 'next' => null, 'bookable' => [], 'last_closed' => null, 'upcoming' => null, 'any' => false, 'early' => null];
    $enEa = null;          // the booking this visitor may enroll with before enrolment opens (early-access link)
    $enEaExpired = null;   // a booking whose hold ended
    if (!empty($course['id']) && en_installed($pdo)) {
        en_track_source();
        $enState = en_state($pdo, (int) $course['id']);
        if (($tok = (string) ($_GET['ea'] ?? '')) !== '' && ($eb = en_ea_lookup($pdo, $tok, (int) $course['id']))) {
            if ($eb['status'] === 'expired' || ($eb['status'] === 'confirmed' && !empty($eb['hold_until']) && $eb['hold_until'] <= en_now())) $enEaExpired = $eb;
            elseif ($eb['status'] === 'confirmed') en_ea_remember($eb);
        }
        $enEa = en_ea_booking($pdo, (int) $course['id']);
        if ($enEa && $_SERVER['REQUEST_METHOD'] === 'GET') {   // the Enroll form is filled in from the booking; the person checks it and presses Enroll now
            $_POST += ['full_name' => $enEa['full_name'], 'email' => $enEa['email'], 'phone' => $enEa['phone'], 'secondary_phone' => (string) ($enEa['phone2'] ?? ''), 'study_mode' => $enEa['study_mode']];
        }
        $ikShowForm = $enState['open'] || $enEa || !en_hide_form($pdo);   // closed + hidden: the Enroll form is not shown (unless this person has early access)
    }
    return;
}
if (!en_installed($pdo) || empty($course['id'])) return;
$cid = (int) $course['id'];
$cfg = en_cfg($pdo);
$next = $enState['next'];
$closedHidden = !$enState['open'] && !$enEa && en_hide_form($pdo);
$bookUrl = fn($o) => url('intake/book.php?course=' . $cid . ($o ? '&opening=' . (int) $o['id'] : ''));
$manageUrl = url('intake/manage.php');
$nextFull = false;
$nextSeats = ['limit' => null, 'left' => null];
if ($next) {
    $nextSeats = en_seat_info($pdo, $next, $cid);
    $nextFull = $nextSeats['left'] !== null && $nextSeats['left'] <= 0;
}

if ($ikPart === 'top') {
    echo '<link rel="stylesheet" href="' . en_e(url('assets/css/book-now.css')) . '?v=2"><span id="enroll-form"></span>';
    // ---- early access: the banner above the Enroll form ----
    if ($enEa) {
        $left = max(0, strtotime((string) $enEa['hold_until']) - strtotime(en_now()));
        $eo = en_opening($pdo, (int) $enEa['opening_id']); ?>
<div id="early-banner" class="bn-card" style="background:#f3faef">
  <span class="bn-pill">Early access</span>
  <h2 class="text-lg font-extrabold text-slate-900" style="margin-top:8px">Your seat is held for you</h2>
  <p class="text-sm text-slate-700" style="margin-top:4px">You booked <strong><?php echo en_e($enEa['opening_title']); ?></strong> (booking <?php echo en_e($enEa['reference']); ?>), so you can enroll before everyone else.
    Check your details below, then press <strong>Enroll now</strong>. You do not pay the booking fee again.</p>
  <div style="display:flex;flex-wrap:wrap;gap:24px;margin-top:12px">
    <div><div class="text-xs font-bold uppercase tracking-widest text-slate-500">Seat held until</div><div class="font-extrabold text-slate-900"><?php echo en_e($enEa['hold_until'] ? en_when($enEa['hold_until']) : 'enrolment closes'); ?></div></div>
    <?php if ($enEa['hold_until']): ?><div><div class="text-xs font-bold uppercase tracking-widest text-slate-500">Time left</div><div id="eaCountdown" class="font-extrabold text-slate-900" data-left="<?php echo (int) $left; ?>"><?php echo en_e(en_left($enEa['hold_until'])); ?></div></div><?php endif; ?>
    <?php if ($eo && $eo['stage'] === 'early'): ?><div><div class="text-xs font-bold uppercase tracking-widest text-slate-500">Enrolment opens for everyone</div><div class="font-extrabold text-slate-900"><?php echo en_e(en_when($eo['opens_at'])); ?></div></div><?php endif; ?>
  </div>
</div>
<script>(function(){var e=document.getElementById('eaCountdown');if(!e)return;var s=parseInt(e.getAttribute('data-left'),10),t0=Date.now();
function f(){var r=Math.max(0,s-Math.floor((Date.now()-t0)/1000)),d=Math.floor(r/86400),h=Math.floor(r%86400/3600),m=Math.floor(r%3600/60),x=r%60;
e.textContent=(d?d+' day'+(d>1?'s ':' '):'')+(d||h?h+' h ':'')+m+' min '+x+' s';if(r>0)setTimeout(f,1000);}f();})();</script>
<?php }
    // ---- a person whose hold ended ----
    if ($enEaExpired) { ?>
<div id="early-expired" class="bn-card plain">
  <span class="bn-pill dark">Seat released</span>
  <p class="text-base font-bold text-slate-900" style="margin-top:12px">Your held seat was released.</p>
  <p class="text-sm text-slate-600" style="margin-top:4px">Your seat for <?php echo en_e($enEaExpired['opening_title']); ?> was held until <?php echo en_e(en_when($enEaExpired['hold_until'])); ?>, and it was not used, so we released it to someone else.</p>
  <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:14px">
    <?php $seatLeft = $enState['open'] ? en_seat_info($pdo, $enState['opening'], $cid) : null; ?>
    <?php if ($enState['open'] && ($seatLeft['left'] === null || $seatLeft['left'] > 0)): ?><a class="bn-btn" href="#enroll-form">Enroll now</a>
    <?php elseif ($next): ?><a class="bn-btn" href="<?php echo en_e($bookUrl($next)); ?>">Book now</a><?php endif; ?>
    <a class="bn-btn ghost" href="<?php echo en_e(en_manage_url($enEaExpired)); ?>">Check my booking</a>
  </div>
</div>
<?php }
    // ---- the Book now card ----
    if ($next) { ?>
<div id="book-now" class="bn-card">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div class="space-y-1 min-w-0">
      <span class="bn-pill">Next intake</span>
      <h2 class="text-lg font-extrabold text-slate-900"><?php echo en_e($next['title']); ?></h2>
      <p class="text-xs font-semibold text-slate-500">
        <?php if ($next['classes_start']): ?>Classes start <?php echo en_e(en_date($next['classes_start'])); ?>. <?php endif; ?>
        Booking fee <?php echo en_e(en_money($cfg['booking_fee'])); ?> (not refundable).
      </p>
      <?php if ($nextSeats['limit'] !== null): $pct = $nextSeats['limit'] > 0 ? min(100, round($nextSeats['taken'] / $nextSeats['limit'] * 100)) : 100; ?>
      <div class="pt-1 max-w-xs"><div class="bn-bar"><i style="width:<?php echo (int) $pct; ?>%"></i></div>
        <p class="text-[11px] font-semibold text-slate-500 mt-1"><?php echo $nextFull ? 'All slots are taken. You can join the waiting list.' : $nextSeats['left'] . ' of ' . $nextSeats['limit'] . ' slot' . ($nextSeats['limit'] === 1 ? '' : 's') . ' left'; ?></p></div>
      <?php endif; ?>
    </div>
    <a href="<?php echo en_e($bookUrl($next)); ?>" class="bn-btn"><?php echo $nextFull ? 'Join the waiting list' : 'Book now'; ?></a>
  </div>
  <?php if ($closedHidden): ?><p class="text-xs font-semibold text-slate-500 mt-4 pt-3 border-t border-slate-100">Already booked? <a class="bn-link" href="<?php echo en_e($manageUrl); ?>">Check my booking</a></p><?php endif; ?>
</div>
<?php }
    // ---- enrolment closed and the form hidden: the message, and what to do instead ----
    if ($closedHidden) { ?>
<div id="intake-closed" class="bn-card plain">
  <span class="bn-pill dark">Enrolment closed</span>
  <p class="text-base font-bold text-slate-900 mt-3"><?php echo en_e(en_closed_message($enState)); ?></p>
  <?php if (!$next): ?>
    <p class="text-sm text-slate-600 mt-2"><?php echo $enState['upcoming'] && $enState['upcoming']['bookings_open_at']
        ? 'Bookings for the ' . en_e($enState['upcoming']['title']) . ' intake open on ' . en_e(en_date($enState['upcoming']['bookings_open_at'], 'j F Y')) . '.'
        : 'We have not opened the next intake yet.'; ?> Leave your details and we will tell you when it opens. You pay nothing now.</p>
    <?php if (!empty($_SESSION['en_interest_error'])): ?><div class="bn-err" style="margin-top:16px"><?php echo en_e($_SESSION['en_interest_error']); unset($_SESSION['en_interest_error']); ?></div><?php endif; ?>
    <?php if (!empty($_GET['interest'])): ?><div class="bn-ok" style="margin-top:16px">Thank you. We will email you when the next intake opens.</div>
    <?php else: ?>
    <form method="POST" action="<?php echo en_e(url('intake/book.php')); ?>" class="mt-4 grid gap-3 md:grid-cols-3">
      <?php echo csrf_field(); ?><input type="hidden" name="action" value="interest"><input type="hidden" name="course_id" value="<?php echo $cid; ?>">
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <input name="full_name" required maxlength="150" autocomplete="name" placeholder="Full name" class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm">
      <input type="email" name="email" required maxlength="150" autocomplete="email" placeholder="Email" class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm">
      <input type="tel" name="phone" maxlength="30" autocomplete="tel" placeholder="Phone (07xx xxx xxx)" class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm">
      <button class="bn-btn md:col-span-3">Tell me when the next intake opens</button>
    </form>
    <p class="text-xs font-semibold text-slate-500 mt-4">Already booked? <a class="bn-link" href="<?php echo en_e($manageUrl); ?>">Check my booking</a></p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php }
    return;
}

// ---- 'after': under the Enroll form, and the bar at the bottom of a phone ----
if ($ikPart === 'after' && $enEa) { ?>
<p class="text-sm font-semibold text-slate-600" style="margin-top:16px;text-align:center">Not ready? <a class="bn-link" href="<?php echo en_e(en_manage_url($enEa)); ?>">Move my booking to the next intake or cancel it.</a></p>
<?php }
if ($ikPart === 'after' && ($next || $enState['open'])) { ?>
<div class="bn-mspace"></div>
<div id="intake-mobile-bar" class="bn-mbar">
  <?php if ($next): ?><a href="<?php echo en_e($bookUrl($next)); ?>" class="bn-btn"><?php echo $nextFull ? 'Join the waiting list' : 'Book now'; ?></a><?php endif; ?>
  <?php if ($enState['open']): ?><a href="#enroll-form" class="bn-btn dark">Enroll now</a><?php endif; ?>
</div>
<?php }
