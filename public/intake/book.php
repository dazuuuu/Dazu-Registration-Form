<?php
// Public: book a slot for a course whose enrolment is open (simple enrolment, migration 086). No payment yet.
// A few details, then a confirmation page and an email. Bookings past the slots are kept and allocated later.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../app/v1/services/EnrolmentService.php';

$pdo = \Models\Connection::getInstance()->getConnection();
$course = (new \Models\CourseModel($pdo))->getById((int) ($_GET['course'] ?? $_POST['course_id'] ?? 0));
$courseUrl = url('courses/view.php?id=' . (int) ($course['id'] ?? 0));
$state = $course && course_is_shareable($course) ? en_state($pdo, (int) $course['id']) : ['open' => false];
$done = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $state['open']) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } elseif (trim((string) ($_POST['website'] ?? '')) !== '') {
        $error = 'Please try again.';   // spam trap: people never fill this hidden field
    } else {
        $r = en_book($pdo, $course, $_POST, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($r['ok']) {
            $_SESSION['en_done'] = (int) $r['booking']['id'];
            $_SESSION['en_again'] = !empty($r['again']);
            header('Location: ' . url('intake/book.php?course=' . (int) $course['id'] . '&done=1'));
            exit;
        }
        $error = $r['error'];
    }
}
if (!empty($_GET['done']) && !empty($_SESSION['en_done'])) {
    $done = en_booking($pdo, (int) $_SESSION['en_done']);
    $again = !empty($_SESSION['en_again']);
}
if (!$done && !$state['open']) {
    header('Location: ' . ($course ? $courseUrl : url('courses/index.php')));
    exit;
}

$page_title = 'Book a slot — ' . ($course['title'] ?? 'Dazu Training Institute');
$standalone = false;
$v = fn($k, $d = '') => en_e($_POST[$k] ?? $d);
$field = 'w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600';
$label = 'block text-xs font-bold uppercase tracking-widest text-slate-500 mb-1';
ob_start();
?>
<div class="bg-slate-50 min-h-screen py-10 md:py-16">
  <div class="max-w-xl mx-auto px-4">
    <a href="<?php echo en_e($courseUrl); ?>" class="mb-6 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-green-600">← Back to the course</a>
    <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">
    <?php if ($done): $ok = $done['status'] === 'confirmed'; ?>
      <div class="text-center space-y-3">
        <div class="mx-auto w-14 h-14 rounded-full flex items-center justify-center text-2xl text-white" style="background:<?php echo $ok ? '#44A024' : '#373435'; ?>">✓</div>
        <h1 class="text-2xl font-extrabold text-slate-900"><?php echo $again ? 'You have already booked' : ($ok ? 'Your slot is booked' : 'Booking received'); ?></h1>
        <p class="text-sm text-slate-600"><?php echo $ok
            ? 'We have emailed the details to ' . en_e($done['email']) . '.'
            : 'All slots are taken right now, but your booking is saved. We will contact you on ' . en_e($done['email']) . ' when we allocate your slot.'; ?></p>
      </div>
      <dl class="mt-6 divide-y divide-slate-100 text-sm">
        <div class="flex justify-between py-2"><dt class="text-slate-500">Booking number</dt><dd class="font-extrabold"><?php echo en_e($done['reference']); ?></dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Course</dt><dd class="font-semibold text-right"><?php echo en_e($done['course_title']); ?></dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Intake</dt><dd class="font-semibold"><?php echo en_e($done['opening_title']); ?></dd></div>
        <?php if ($done['classes_start']): ?><div class="flex justify-between py-2"><dt class="text-slate-500">Classes start</dt><dd class="font-semibold"><?php echo en_e(en_date($done['classes_start'])); ?></dd></div><?php endif; ?>
      </dl>
      <p class="text-xs text-slate-500 mt-4">To finish enrolling, apply on the course page, or call or WhatsApp us on 0722 922 454.</p>
      <a href="<?php echo en_e($courseUrl); ?>" class="mt-4 block text-center py-3 text-xs font-extrabold uppercase tracking-widest text-white bg-[#44A024] rounded-xl">Back to the course</a>
    <?php else: $o = $state['opening']; ?>
      <span class="inline-block text-[10px] font-extrabold uppercase tracking-widest text-white bg-[#44A024] px-2.5 py-1 rounded-full">Enrolment open</span>
      <h1 class="text-2xl font-extrabold text-slate-900 mt-2">Book a slot</h1>
      <p class="text-sm font-semibold text-slate-600"><?php echo en_e($course['title']); ?> · <?php echo en_e($o['title']); ?><?php echo $o['classes_start'] ? ' · classes start ' . en_e(en_date($o['classes_start'])) : ''; ?></p>
      <?php if ($state['left'] !== null): ?><p class="text-xs text-slate-500 mt-1"><?php echo $state['left'] > 0 ? $state['left'] . ' slot' . ($state['left'] === 1 ? '' : 's') . ' left.' : 'All slots are taken, but you can still book: we will allocate you a slot.'; ?></p><?php endif; ?>
      <?php if ($error): ?><div class="mt-4 p-3 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo en_e($error); ?></div><?php endif; ?>
      <form method="POST" class="mt-6 space-y-4"><?php echo csrf_field(); ?>
        <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
        <div><label class="<?php echo $label; ?>" for="fn">Full name</label><input id="fn" name="full_name" required maxlength="150" autocomplete="name" class="<?php echo $field; ?>" value="<?php echo $v('full_name'); ?>"></div>
        <div><label class="<?php echo $label; ?>" for="em">Email</label><input id="em" type="email" name="email" required maxlength="150" autocomplete="email" class="<?php echo $field; ?>" value="<?php echo $v('email'); ?>"></div>
        <div><label class="<?php echo $label; ?>" for="ph">Phone (WhatsApp)</label><input id="ph" type="tel" name="phone" required maxlength="30" autocomplete="tel" placeholder="07xx xxx xxx" class="<?php echo $field; ?>" value="<?php echo $v('phone'); ?>"></div>
        <div><span class="<?php echo $label; ?>">How will you study?</span>
          <div class="flex gap-4 text-sm font-semibold">
            <label><input type="radio" name="study_mode" value="online" <?php echo ($_POST['study_mode'] ?? 'online') !== 'physical' ? 'checked' : ''; ?>> Online</label>
            <label><input type="radio" name="study_mode" value="physical" <?php echo ($_POST['study_mode'] ?? '') === 'physical' ? 'checked' : ''; ?>> Physical class</label>
          </div></div>
        <div id="campusBox"><span class="<?php echo $label; ?>">Campus</span>
          <div class="flex gap-4 text-sm font-semibold">
            <label><input type="radio" name="campus" value="thika" <?php echo ($_POST['campus'] ?? '') === 'thika' ? 'checked' : ''; ?>> Thika</label>
            <label><input type="radio" name="campus" value="nairobi" <?php echo ($_POST['campus'] ?? '') === 'nairobi' ? 'checked' : ''; ?>> Nairobi</label>
          </div></div>
        <div><label class="<?php echo $label; ?>" for="nt">Anything we should know? <span class="normal-case font-semibold">(optional)</span></label><textarea id="nt" name="note" rows="2" maxlength="500" class="<?php echo $field; ?>"><?php echo $v('note'); ?></textarea></div>
        <button class="w-full py-3.5 text-xs font-extrabold uppercase tracking-widest text-white bg-[#44A024] hover:bg-[#36851b] rounded-xl">Book my slot</button>
        <p class="text-[11px] text-slate-400 text-center">No payment needed now. We email you a booking number.</p>
      </form>
      <script>(function () { var b = document.getElementById('campusBox'); function up() { var p = document.querySelector('input[name=study_mode]:checked'); b.style.display = p && p.value === 'physical' ? '' : 'none'; }
        document.querySelectorAll('input[name=study_mode]').forEach(function (r) { r.addEventListener('change', up); }); up(); })();</script>
    <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
