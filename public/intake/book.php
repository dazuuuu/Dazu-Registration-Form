<?php
// Public: book a slot in the next intake for one course (no payment), or join the interest list.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../app/v1/services/IntakeService.php';

$pdo = \Models\Connection::getInstance()->getConnection();
$course = (new \Models\CourseModel($pdo))->getById((int) ($_GET['course'] ?? $_POST['course_id'] ?? 0));
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!$course || !course_is_shareable($course) || !ik_installed($pdo) || !ik_course_state($pdo, (int) $course['id'])) {
    header('Location: ' . url($course ? 'courses/view.php?id=' . (int) $course['id'] : 'courses/index.php'));
    exit;
}
ik_track_source();
$courseUrl = url('courses/view.php?id=' . (int) $course['id']);
$bookable = ik_bookable_for($pdo, (int) $course['id']);
$tz = ik_tz_label($pdo);
$error = null;
$duplicate = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'interest') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (trim((string) ($_POST['website'] ?? '')) !== '' || !ik_rate_ok($pdo, 'interest', 10, 3600)) {
            $error = 'Please try again later.';
        } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter your name and a valid email address.';
        } else {
            $st = $pdo->prepare('SELECT 1 FROM intake_interest_list WHERE LOWER(email) = ? AND notified_at IS NULL AND (course_id <=> ?)');
            $st->execute([$email, (int) $course['id']]);
            if (!$st->fetchColumn()) {
                $pdo->prepare('INSERT INTO intake_interest_list (course_id, name, email, phone) VALUES (?, ?, ?, ?)')
                    ->execute([(int) $course['id'], mb_substr($name, 0, 150), $email, ik_phone((string) ($_POST['phone'] ?? ''))]);
            }
            $notice = 'Thank you. We will email you as soon as the next intake opens for booking.';
        }
    } else {
        $r = ik_book($pdo, $_POST, $course);
        if ($r['ok'] && !empty($r['waitlisted'])) {
            $notice = 'This intake is full, so you are on the waiting list. When a seat comes free, it is offered to the first person on the list and we email you straight away.';
        } elseif ($r['ok']) {
            $_SESSION['ik_new'] = (int) $r['booking']['id'];
            header('Location: ' . url('intake/manage.php?t=' . ik_token($r['booking']) . '&new=1'));
            exit;
        } else {
            $error = $r['error'];
            $duplicate = $r['duplicate'] ?? null;
        }
    }
}

$v = fn(string $k, string $d = '') => (string) ($_POST[$k] ?? $d);
$mode = $v('mode', 'online') === 'physical' ? 'physical' : 'online';
$selIntake = (int) $v('intake_id', (string) ($bookable[0]['id'] ?? 0));
$page_title = 'Book your slot — ' . $course['title'] . ' | Dazu Training Institute';
$og_image = course_og_image_url($course);
$input = 'w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:border-green-600 focus:ring-0 outline-none';
$label = 'block text-[11px] font-extrabold text-slate-700 mb-1.5';
$extra_head = ($extra_head ?? '') . '<link rel="stylesheet" href="' . htmlspecialchars(url('assets/css/intake.css') . '?v=' . @filemtime(__DIR__ . '/../assets/css/intake.css')) . '">';
ob_start();
?>
<div class="bg-slate-50 min-h-screen py-10 md:py-16">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8">
    <a href="<?php echo $e($courseUrl); ?>" class="mb-8 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-green-600">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>Back to course</a>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <div class="lg:col-span-5 space-y-6 order-2 lg:order-1">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8">
          <span class="text-green-600 text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 bg-green-50 border border-green-200 rounded-full inline-block mb-4">You are booking</span>
          <h1 class="text-xl font-extrabold text-slate-950 tracking-tight uppercase"><?php echo $e($course['title']); ?></h1>
          <div class="flex flex-wrap gap-2 mt-3">
            <?php if (!empty($course['duration_months'])): ?><span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-slate-100 text-slate-600"><?php echo (int) $course['duration_months']; ?> Months</span><?php endif; ?>
            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-blue-50 text-blue-700">Online · Physical</span>
            <?php if ((float) ($course['course_fee'] ?? 0) > 0): ?><span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-green-50 text-green-700">KES <?php echo number_format((float) $course['course_fee']); ?></span><?php endif; ?>
          </div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8">
          <h2 class="text-sm font-extrabold text-slate-950 mb-4">How booking works</h2>
          <?php foreach ([['Book your slot', 'Fill in the form. It takes 2 minutes. No payment now.'],
                          ['We confirm by email', 'You get a booking number straight away, and a reminder before the intake opens.'],
                          ['Enrol when you are ready', 'We invite you to enrol ' . (int) ik_settings($pdo)['early_access_hours'] . ' hours before everyone else. Your seat is held until then.']] as $n => [$t, $d]): ?>
            <div class="flex gap-3 mb-4 last:mb-0"><span class="w-7 h-7 rounded-full text-white text-xs font-extrabold flex items-center justify-center flex-shrink-0" style="background:#44A024"><?php echo $n + 1; ?></span>
              <div><div class="text-sm font-bold text-slate-900"><?php echo $t; ?></div><div class="text-xs text-slate-500"><?php echo $e($d); ?></div></div></div>
          <?php endforeach; ?>
        </div>
        <div class="p-4 bg-green-50 border border-green-100 rounded-2xl text-xs font-semibold text-green-800 flex items-center gap-2">
          <svg class="h-4 w-4 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a5 5 0 00-5 5v3H6a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2v-8a2 2 0 00-2-2h-1V7a5 5 0 00-5-5zm-3 8V7a3 3 0 116 0v3z"/></svg>
          Booking is free and does not commit you. You can cancel any time.</div>
        <p class="text-xs text-slate-500 text-center">Already booked? <a href="<?php echo $e(url('intake/manage.php')); ?>" class="font-bold text-green-700">Check my booking</a></p>
      </div>

      <div class="lg:col-span-7 order-1 lg:order-2">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-10">
          <div class="border-b border-slate-100 pb-5 mb-6">
            <h2 class="text-2xl md:text-3xl font-extrabold text-slate-950 tracking-tight">Book your slot</h2>
            <p class="text-slate-500 text-xs font-medium mt-1">Tell us a little about you and choose your intake.</p>
          </div>
          <?php if ($notice): ?>
            <div class="p-4 border border-green-200 bg-green-50 text-green-800 text-sm font-semibold rounded-xl"><?php echo $e($notice); ?></div>
            <a href="<?php echo $e($courseUrl); ?>" class="mt-4 inline-block px-6 py-3 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl" style="background:#44A024">Back to the course</a>
          <?php elseif (!$bookable): ?>
            <p class="text-sm text-slate-700 mb-4">No intake is taking bookings right now. Leave your details and we will tell you when the next intake opens.</p>
            <?php if ($error): ?><div class="mb-4 p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo $e($error); ?></div><?php endif; ?>
            <form method="POST" class="space-y-4"><?php echo csrf_field(); ?><input type="hidden" name="action" value="interest"><input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
              <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
              <div><label class="<?php echo $label; ?>">Full name *</label><input name="name" required maxlength="150" class="<?php echo $input; ?>"></div>
              <div><label class="<?php echo $label; ?>">Email address *</label><input type="email" name="email" required maxlength="150" class="<?php echo $input; ?>"></div>
              <div><label class="<?php echo $label; ?>">Phone</label><input type="tel" name="phone" maxlength="20" class="<?php echo $input; ?>" placeholder="07xx xxx xxx"></div>
              <button class="w-full py-4 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl" style="background:#44A024">Tell me when the next intake opens</button>
            </form>
          <?php else: ?>
            <?php if ($error): ?><div class="mb-5 p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo $e($error); ?>
              <?php if ($duplicate): ?> <a class="underline" href="<?php echo $e(url('intake/manage.php')); ?>">Manage my booking</a> (use your booking number and email; we sent them to you).<?php endif; ?></div><?php endif; ?>
            <form method="POST" id="ikBookForm" class="space-y-5">
              <?php echo csrf_field(); ?><input type="hidden" name="action" value="book"><input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
              <input type="hidden" name="ik_ref" value="<?php echo $e($_SESSION['ik_utm']['ref'] ?? ($_SERVER['HTTP_REFERER'] ?? '')); ?>">
              <div class="hidden" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

              <div><label class="<?php echo $label; ?>">Choose your intake *</label>
                <div class="space-y-2">
                <?php foreach ($bookable as $i): $s = ik_seats($pdo, $i, (int) $course['id']); $on = (int) $i['id'] === $selIntake; $full = $s['left'] !== null && $s['left'] <= 0; ?>
                  <label class="ik-opt flex items-center gap-3 p-4 border rounded-xl cursor-pointer <?php echo $on ? 'border-green-600 bg-green-50' : 'border-slate-200 bg-white'; ?>">
                    <input type="radio" name="intake_id" value="<?php echo (int) $i['id']; ?>" class="accent-green-600" <?php echo $on ? 'checked' : ''; ?> required>
                    <span class="flex-1"><span class="block text-sm font-extrabold text-slate-900"><?php echo $e($i['name']); ?></span>
                      <span class="block text-xs text-slate-500"><?php echo $i['classes_start_on'] ? 'Starts ' . $e(ik_fmt($pdo, strtotime($i['classes_start_on'] . ' 12:00 UTC'), 'D, j M Y')) : 'Enrolment opens ' . $e(ik_fmt($pdo, ik_ts($i['enrol_opens_at']), 'j M Y')); ?></span></span>
                    <?php if ($s['limit'] !== null): ?><span class="text-[10px] font-extrabold px-2 py-1 rounded-full <?php echo $full ? 'bg-red-50 text-red-700' : ($s['left'] <= 12 ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700'); ?>"><?php echo $full ? 'Full · waiting list' : (int) $s['left'] . ' seats left'; ?></span><?php endif; ?>
                  </label>
                <?php endforeach; ?>
                </div></div>

              <div><label class="<?php echo $label; ?>">Full name *</label><input name="full_name" required maxlength="150" class="<?php echo $input; ?>" placeholder="E.g. Jane Wangari Kamau" value="<?php echo $e($v('full_name')); ?>"></div>
              <div><label class="<?php echo $label; ?>">Email address *</label><input type="email" name="email" required maxlength="150" class="<?php echo $input; ?>" placeholder="you@example.com" value="<?php echo $e($v('email')); ?>"></div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="<?php echo $label; ?>">Primary phone *</label><input type="tel" name="phone" required maxlength="20" pattern="[0-9+ ()\-]{9,20}" class="<?php echo $input; ?>" placeholder="0712 345 678" value="<?php echo $e($v('phone')); ?>"></div>
                <div><label class="<?php echo $label; ?>">Secondary phone</label><input type="tel" name="phone2" maxlength="20" class="<?php echo $input; ?>" placeholder="Optional" value="<?php echo $e($v('phone2')); ?>"></div>
              </div>
              <div><label class="<?php echo $label; ?>">Classes *</label>
                <div class="grid grid-cols-2 gap-3">
                  <?php foreach (['online' => 'Online', 'physical' => 'Physical (offline)'] as $k => $l): ?>
                    <label class="ik-mode flex items-center gap-2 p-3.5 border rounded-xl cursor-pointer text-sm font-semibold <?php echo $mode === $k ? 'border-green-600 bg-green-50' : 'border-slate-200'; ?>"><input type="radio" name="mode" value="<?php echo $k; ?>" class="accent-green-600" <?php echo $mode === $k ? 'checked' : ''; ?>> <?php echo $l; ?></label>
                  <?php endforeach; ?>
                </div></div>
              <div id="ikCampus" class="<?php echo $mode === 'physical' ? '' : 'hidden'; ?>"><label class="<?php echo $label; ?>">Campus *</label>
                <div class="grid grid-cols-2 gap-3"><?php foreach (IK_CAMPUSES as $k => $l): ?>
                  <label class="ik-camp flex items-center gap-2 p-3.5 border rounded-xl cursor-pointer text-sm font-semibold <?php echo $v('campus') === $k ? 'border-green-600 bg-green-50' : 'border-slate-200'; ?>"><input type="radio" name="campus" value="<?php echo $k; ?>" class="accent-green-600" <?php echo $v('campus') === $k ? 'checked' : ''; ?>> <?php echo $l; ?></label>
                <?php endforeach; ?></div></div>
              <div><label class="<?php echo $label; ?>">Preferred class time *</label>
                <?php foreach (IK_CLASS_TIMES as $m => $times): ?>
                  <div class="ik-times flex flex-wrap gap-2 <?php echo $m === $mode ? '' : 'hidden'; ?>" data-mode="<?php echo $m; ?>">
                    <?php foreach ($times as $k => $l): $on = $v('class_time', $m === 'physical' ? '05:00' : '') === $k && $m === $mode; ?>
                      <label class="ik-time px-4 py-2 border rounded-lg cursor-pointer text-sm font-bold <?php echo $on ? 'text-white border-green-600' : 'border-slate-200 text-slate-700'; ?>" style="<?php echo $on ? 'background:#44A024' : ''; ?>">
                        <input type="radio" name="class_time" value="<?php echo $k; ?>" class="sr-only" <?php echo $on ? 'checked' : ''; ?> <?php echo $m === $mode ? '' : 'disabled'; ?>><?php echo $l; ?></label>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
                <p class="text-[11px] text-slate-500 mt-1.5">Times are East Africa Time (<?php echo $e($tz); ?>). You can change this when you enrol.</p></div>
              <label class="flex items-start gap-2 text-xs font-semibold text-slate-700"><input type="checkbox" name="consent" value="1" required class="mt-0.5 accent-green-600" <?php echo $v('consent') ? 'checked' : ''; ?>> I agree to be contacted about my booking and the intake.</label>
              <button class="w-full flex items-center justify-center gap-2 py-4 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl shadow-sm" style="background:#44A024">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2h12a1 1 0 011 1v19l-7-4-7 4V3a1 1 0 011-1z"/></svg>Book my slot</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var f = document.getElementById('ikBookForm'); if (!f) return;
  function paint(sel, cls) { f.querySelectorAll(sel).forEach(function (l) { var on = l.querySelector('input').checked;
    l.classList.toggle('border-green-600', on); l.classList.toggle('bg-green-50', on); l.classList.toggle('border-slate-200', !on); }); }
  function sync() {
    var mode = (f.querySelector('input[name=mode]:checked') || {}).value || 'online';
    document.getElementById('ikCampus').classList.toggle('hidden', mode !== 'physical');
    f.querySelectorAll('input[name=campus]').forEach(function (r) { r.required = mode === 'physical'; });
    f.querySelectorAll('.ik-times').forEach(function (g) { var on = g.dataset.mode === mode; g.classList.toggle('hidden', !on);
      g.querySelectorAll('input').forEach(function (r) { r.disabled = !on; r.required = on; }); });
    if (mode === 'physical') { var p = f.querySelector('.ik-times[data-mode=physical] input'); if (p) p.checked = true; }
    f.querySelectorAll('.ik-time').forEach(function (l) { var on = l.querySelector('input').checked && !l.querySelector('input').disabled;
      l.style.background = on ? '#44A024' : ''; l.classList.toggle('text-white', on); l.classList.toggle('border-green-600', on); l.classList.toggle('text-slate-700', !on); });
    paint('.ik-opt'); paint('.ik-mode'); paint('.ik-camp');
  }
  f.addEventListener('change', sync); sync();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
