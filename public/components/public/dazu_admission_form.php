<?php
/**
 * Simplified enrollment form: full name, course (auto-filled), study mode, email.
 *
 * Requires: $course, $reg_success, $reg_error
 * Optional: $pfx, $referrer, $reg_no (shown after success)
 */
if (!isset($course)) {
    throw new RuntimeException('dazu_admission_form requires $course');
}
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_fee_structure.php';
require_once __DIR__ . '/../../../app/v1/helpers/class_schedule.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';

$pfx = $pfx ?? 'adm';
$reg_success = $reg_success ?? false;
$reg_error = $reg_error ?? null;
$reg_no = $reg_no ?? null;
$referrer = $referrer ?? null;
$isFree = !empty($course['is_free']);
$studyMode = $_POST['study_mode'] ?? 'online';
$onlineFee = course_fee_for_mode($course, 'online');
$physicalFee = course_fee_for_mode($course, 'physical');
$onlineSchedule = class_schedule_entries('online');
$physicalSchedule = class_schedule_entries('physical');
?>

<?php if ($reg_success): ?>
<div class="bg-green-50 border border-green-200 text-green-800 rounded-2xl p-8 text-center space-y-4">
  <svg class="h-12 w-12 text-green-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
  <h2 class="text-xl font-extrabold">Enrollment Submitted!</h2>
  <?php if ($reg_no): ?>
    <p class="text-sm font-bold text-green-900">Registration number: <?php echo htmlspecialchars($reg_no); ?></p>
  <?php endif; ?>
  <p class="text-sm text-green-700 max-w-md mx-auto">
    Your admission letter has been generated and sent to your email address. Our team will contact you if anything else is needed.
  </p>
  <a href="<?php echo url('/'); ?>" class="inline-block px-6 py-3 bg-green-600 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl">Back to Home</a>
</div>
<?php else: ?>

<div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-10">
  <div class="border-b border-slate-100 pb-6 mb-8">
    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-950 tracking-tight">Course Enrollment</h1>
    <p class="text-slate-500 text-xs font-medium mt-1">Complete the form below to enroll. Your admission letter will be emailed immediately.</p>
  </div>

  <?php if ($reg_error): ?>
  <div class="mb-6 p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($reg_error); ?></div>
  <?php endif; ?>

  <?php if ($referrer): ?>
  <div class="mb-6 p-4 bg-blue-50 border border-blue-100 text-blue-800 text-xs font-semibold rounded-xl">
    Recommended by <strong><?php echo htmlspecialchars($referrer['full_name']); ?></strong> (<?php echo htmlspecialchars($referrer['registration_no']); ?>)
  </div>
  <?php endif; ?>

  <form method="POST" id="<?php echo $pfx; ?>Form" class="space-y-6">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="dh_register" value="1">
    <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
    <input type="hidden" name="payment_plan" value="full">

    <div class="space-y-1.5">
      <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_name">Full names *</label>
      <input type="text" id="<?php echo $pfx; ?>_name" name="full_name" required placeholder="E.g. Jane Wangari Kamau"
             value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
             class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:outline-none focus:border-green-600 focus:bg-white">
    </div>

    <div class="space-y-1.5">
      <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_course">Course *</label>
      <input type="text" id="<?php echo $pfx; ?>_course" readonly value="<?php echo htmlspecialchars($course['title']); ?>"
             class="w-full px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-sm font-bold text-slate-900">
    </div>

    <div class="space-y-2">
      <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Study mode *</label>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <label class="study-mode-option p-4 border rounded-xl cursor-pointer <?php echo $studyMode === 'online' ? 'border-green-600 bg-green-50' : 'border-slate-200'; ?>" data-mode="online">
          <input type="radio" name="study_mode" value="online" class="sr-only" <?php echo $studyMode === 'online' ? 'checked' : ''; ?>>
          <span class="block text-xs font-extrabold uppercase tracking-wider text-slate-800">Online classes</span>
          <?php if (!$isFree && $onlineFee > 0): ?>
            <span class="block text-sm font-bold text-[#15A24A] mt-1">KES <?php echo number_format($onlineFee); ?></span>
          <?php endif; ?>
          <?php if ($onlineSchedule): ?>
            <ul class="mt-2 space-y-1 text-[10px] text-slate-600 font-semibold">
              <?php foreach (array_slice($onlineSchedule, 0, 3) as $row): ?>
                <li><?php echo htmlspecialchars($row['day_label'] . ' · ' . $row['time_label']); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </label>
        <label class="study-mode-option p-4 border rounded-xl cursor-pointer <?php echo $studyMode === 'physical' ? 'border-green-600 bg-green-50' : 'border-slate-200'; ?>" data-mode="physical">
          <input type="radio" name="study_mode" value="physical" class="sr-only" <?php echo $studyMode === 'physical' ? 'checked' : ''; ?>>
          <span class="block text-xs font-extrabold uppercase tracking-wider text-slate-800">Physical classes</span>
          <?php if (!$isFree && $physicalFee > 0): ?>
            <span class="block text-sm font-bold text-[#15A24A] mt-1">KES <?php echo number_format($physicalFee); ?></span>
          <?php endif; ?>
          <?php if ($physicalSchedule): ?>
            <ul class="mt-2 space-y-1 text-[10px] text-slate-600 font-semibold">
              <?php foreach (array_slice($physicalSchedule, 0, 3) as $row): ?>
                <li><?php echo htmlspecialchars($row['day_label'] . ' · ' . $row['time_label']); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </label>
      </div>
    </div>

    <div class="space-y-1.5">
      <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_email">Email address *</label>
      <input type="email" id="<?php echo $pfx; ?>_email" name="email" required placeholder="E.g. jane.wangari@example.com"
             value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
             class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:outline-none focus:border-green-600 focus:bg-white">
      <p class="text-[10px] text-slate-500 font-medium">Your admission letter will be sent to this email.</p>
    </div>

    <button type="submit" id="<?php echo $pfx; ?>SubmitBtn"
            class="w-full py-4 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl transition-colors">
      Submit Enrollment
    </button>
  </form>
</div>

<script>
(function () {
  var form = document.getElementById(<?php echo json_encode($pfx . 'Form'); ?>);
  if (!form) return;
  form.querySelectorAll('.study-mode-option').forEach(function (label) {
    label.addEventListener('click', function () {
      form.querySelectorAll('.study-mode-option').forEach(function (el) {
        el.classList.remove('border-green-600', 'bg-green-50');
        el.classList.add('border-slate-200');
      });
      label.classList.add('border-green-600', 'bg-green-50');
      label.classList.remove('border-slate-200');
    });
  });
  form.addEventListener('submit', function () {
    var b = document.getElementById(<?php echo json_encode($pfx . 'SubmitBtn'); ?>);
    if (b) { b.disabled = true; b.textContent = 'Submitting…'; }
  });
})();
</script>
<?php endif; ?>
