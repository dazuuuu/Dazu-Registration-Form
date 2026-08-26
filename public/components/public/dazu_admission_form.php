<?php
/**
 * Enrolment form: name, email, course, online/physical.
 *
 * Requires: $course, $reg_success, $reg_error
 * Optional: $pfx, $referrer, $allowCourseChange, $courseChoices, $issued_reg_no
 *
 * Pages that set $allowCourseChange = true must re-resolve $course from $_POST['course_id']
 * before CourseRegistrationHandler::process().
 */
if (!isset($course)) {
    throw new RuntimeException('dazu_admission_form requires $course');
}

$pfx = $pfx ?? 'adm';
$reg_success = $reg_success ?? false;
$reg_error = $reg_error ?? null;
$referrer = $referrer ?? null;
$allowCourseChange = $allowCourseChange ?? false;
$courseChoices = $courseChoices ?? [];
$issued_reg_no = $issued_reg_no ?? '';
$showCourseSelect = $allowCourseChange && count($courseChoices) > 1;
$courseChangeUrl = $courseChangeUrl ?? url('courses/view.php');
$studyMode = $_POST['study_mode'] ?? 'online';
?>

<?php if ($reg_success): ?>
<div class="bg-green-50 border border-green-200 text-green-800 rounded-2xl p-8 text-center space-y-4">
  <svg class="h-12 w-12 text-green-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
  <h2 class="text-xl font-extrabold">You are enrolled</h2>
  <?php if ($issued_reg_no !== ''): ?>
  <p class="text-2xl font-extrabold tracking-wide text-green-800"><?php echo htmlspecialchars($issued_reg_no); ?></p>
  <p class="text-xs font-bold uppercase tracking-wider text-green-700">Your registration number</p>
  <?php endif; ?>
  <p class="text-sm text-green-700 max-w-md mx-auto">
    We emailed a confirmation to the address you entered. Keep your registration number for records and payments.
  </p>
  <a href="<?php echo url('/'); ?>" class="inline-block px-6 py-3 bg-green-600 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl">Back to Home</a>
</div>
<?php else: ?>

<div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-10">
  <div class="border-b border-slate-100 pb-6 mb-8">
    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-950 tracking-tight">Enrol</h1>
    <p class="text-slate-500 text-xs font-medium mt-1">Name, email, course, and online or physical classes. We email your registration number straight away.</p>
  </div>

  <?php if ($reg_error): ?>
  <div class="mb-6 p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($reg_error); ?></div>
  <?php endif; ?>

  <?php if ($referrer): ?>
  <div class="mb-6 p-4 bg-blue-50 border border-blue-100 text-blue-800 text-xs font-semibold rounded-xl">
    Recommended by <strong><?php echo htmlspecialchars($referrer['full_name']); ?></strong> (<?php echo htmlspecialchars($referrer['registration_no']); ?>)
  </div>
  <?php endif; ?>

  <?php
  ob_start();
  $fee_compact = false;
  $fee_show_download = true;
  include __DIR__ . '/dazu_course_fee_breakdown.php';
  $courseResourceHtml = trim(ob_get_clean());
  ?>
  <?php if ($courseResourceHtml !== ''): ?>
  <div class="mb-8 p-4 border border-slate-200 bg-slate-50 rounded-2xl space-y-4">
    <?php echo $courseResourceHtml; ?>
  </div>
  <?php endif; ?>

  <form method="POST" id="<?php echo $pfx; ?>Form" class="space-y-6">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="dh_register" value="1">
    <?php if (!$showCourseSelect): ?>
    <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
    <?php endif; ?>

    <div class="space-y-1.5">
      <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_name">Full name *</label>
      <input type="text" id="<?php echo $pfx; ?>_name" name="full_name" required placeholder="E.g. Jane Wangari Kamau"
             value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
             class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white">
    </div>

    <div class="space-y-1.5">
      <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_email">Email address *</label>
      <input type="email" id="<?php echo $pfx; ?>_email" name="email" required placeholder="E.g. jane.wangari@example.com"
             value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
             class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white">
    </div>

    <div class="space-y-1.5">
      <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_course">Course *</label>
      <?php if ($showCourseSelect): ?>
        <select id="<?php echo $pfx; ?>_course" name="course_id" required
                data-change-url="<?php echo htmlspecialchars($courseChangeUrl, ENT_QUOTES); ?>"
                data-current-id="<?php echo (int) $course['id']; ?>"
                class="w-full px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-xs font-bold text-slate-900 cursor-pointer focus:outline-none focus:border-green-600">
          <?php foreach ($courseChoices as $choice): ?>
            <option value="<?php echo (int) $choice['id']; ?>"<?php echo ((int) $choice['id'] === (int) $course['id']) ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars($choice['title']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="text-[10px] font-medium text-green-700">Not the course you wanted? Pick another — the details on the left update to match.</p>
      <?php else: ?>
        <input type="text" id="<?php echo $pfx; ?>_course" readonly value="<?php echo htmlspecialchars($course['title']); ?>"
               class="w-full px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-xs font-bold text-slate-900">
      <?php endif; ?>
    </div>

    <div class="space-y-2">
      <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Classes *</label>
      <div class="grid grid-cols-2 gap-2">
        <label class="p-3 border rounded-xl text-xs font-bold cursor-pointer text-center <?php echo $studyMode === 'online' ? 'border-green-600 bg-green-50' : 'border-slate-200'; ?>">
          <input type="radio" name="study_mode" value="online" class="sr-only" <?php echo $studyMode === 'online' ? 'checked' : ''; ?>> Online
        </label>
        <label class="p-3 border rounded-xl text-xs font-bold cursor-pointer text-center <?php echo $studyMode === 'physical' ? 'border-green-600 bg-green-50' : 'border-slate-200'; ?>">
          <input type="radio" name="study_mode" value="physical" class="sr-only" <?php echo $studyMode === 'physical' ? 'checked' : ''; ?>> Physical (offline)
        </label>
      </div>
    </div>

    <button type="submit" id="<?php echo $pfx; ?>SubmitBtn"
            class="w-full py-4 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl transition-colors">
      Enrol now
    </button>
  </form>
</div>

<script>
(function(){
  var pfx = <?php echo json_encode($pfx); ?>;
  var form = document.getElementById(pfx + 'Form');
  if (!form) return;

  function paintMode(){
    form.querySelectorAll('label:has(input[name="study_mode"])').forEach(function(l){
      var inp = l.querySelector('input');
      l.className = 'p-3 border rounded-xl text-xs font-bold cursor-pointer text-center ' + (inp && inp.checked ? 'border-green-600 bg-green-50' : 'border-slate-200');
    });
  }
  form.querySelectorAll('input[name="study_mode"]').forEach(function(r){ r.addEventListener('change', paintMode); });

  var courseSel = document.getElementById(pfx + '_course');
  if (courseSel && courseSel.tagName === 'SELECT') {
    var STORE = 'dazu-adm-' + pfx;
    var SKIP = { course_id: 1, csrf_token: 1, dh_register: 1 };
    function snapshot() {
      var data = {};
      form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (f) {
        if (SKIP[f.name] || f.type === 'file') return;
        if (f.type === 'radio') { if (f.checked) data[f.name] = f.value; return; }
        data[f.name] = f.value;
      });
      return data;
    }
    function restore(data) {
      Object.keys(data).forEach(function (n) {
        var val = data[n];
        if (val === '' || val == null) return;
        var radios = form.querySelectorAll('input[type="radio"][name="' + n + '"]');
        if (radios.length) {
          radios.forEach(function (r) { r.checked = (r.value === val); });
          return;
        }
        var f = form.querySelector('[name="' + n + '"]');
        if (f) f.value = val;
      });
      paintMode();
    }
    try {
      var saved = sessionStorage.getItem(STORE);
      if (saved) {
        sessionStorage.removeItem(STORE);
        var parsed = JSON.parse(saved);
        if (parsed && parsed.t && (Date.now() - parsed.t) < 600000) restore(parsed.d || {});
      }
    } catch (e) {}
    courseSel.addEventListener('change', function () {
      var base = courseSel.getAttribute('data-change-url');
      var id = courseSel.value;
      if (!base || !id) return;
      try { sessionStorage.setItem(STORE, JSON.stringify({ t: Date.now(), d: snapshot() })); } catch (e) {}
      window.location.href = base + (base.indexOf('?') === -1 ? '?' : '&') + 'id=' + encodeURIComponent(id);
    });
  }

  form.addEventListener('submit', function(){
    var b = document.getElementById(pfx+'SubmitBtn');
    if (b){ b.disabled=true; b.textContent='Enrolling…'; }
  });
})();
</script>
<?php endif; ?>
