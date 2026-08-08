<?php
/**
 * Admission application form.
 *
 * Requires: $course, $reg_success, $reg_error
 * Optional: $pfx, $referrer
 *   $allowCourseChange  bool   render Course Choice as a real <select> (default false)
 *   $courseChoices      array  active course rows to offer; only used when the above is true
 *
 * IMPORTANT: CourseRegistrationHandler::process() registers $course['id'] and ignores
 * $post['course_id']. Any page that sets $allowCourseChange = true MUST re-resolve
 * $course from $_POST['course_id'] before calling process(), or the applicant's choice
 * is silently discarded and they get enrolled on the URL's course instead.
 * courses/view.php does this. courses/share.php does not, so it leaves the flag off.
 */
if (!isset($course)) {
    throw new RuntimeException('dazu_admission_form requires $course');
}
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../../app/v1/helpers/payment_credentials.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';

$pfx = $pfx ?? 'adm';
$reg_success = $reg_success ?? false;
$reg_error = $reg_error ?? null;
$referrer = $referrer ?? null;
$allowCourseChange = $allowCourseChange ?? false;
$courseChoices = $courseChoices ?? [];
$isFree = !empty($course['is_free']);
// Nothing to switch between unless there are at least two options.
$showCourseSelect = $allowCourseChange && count($courseChoices) > 1;
$payment = payment_credentials();
$fee = (float) ($course['course_fee'] ?? 0);
$mo = max(1, (int) ($course['duration_months'] ?? 1));
$studyMode = $_POST['study_mode'] ?? 'online';
$paymentPlan = $_POST['payment_plan'] ?? 'monthly';
$quote = dazu_fee_quote($course, $studyMode, $paymentPlan);
?>

<?php if ($reg_success): ?>
<div class="bg-green-50 border border-green-200 text-green-800 rounded-2xl p-8 text-center space-y-4">
  <svg class="h-12 w-12 text-green-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
  <h2 class="text-xl font-extrabold">Application Received!</h2>
  <p class="text-sm text-green-700 max-w-md mx-auto">
    <?php echo $isFree
        ? 'Our team will review your application and get back to you within 24 hours. You can also reach us on WhatsApp at 0750 116 600.'
        : 'Our team will verify your M-Pesa payment and contact you within 24 hours. You can also reach us on WhatsApp at 0750 116 600.'; ?>
  </p>
  <a href="<?php echo url('/'); ?>" class="inline-block px-6 py-3 bg-green-600 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl">Back to Home</a>
</div>
<?php else: ?>

<div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-10">
  <div class="border-b border-slate-100 pb-6 mb-8">
    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-950 tracking-tight"><?php echo $isFree ? 'Apply For This Free Course' : 'Admission Application Form'; ?></h1>
    <p class="text-slate-500 text-xs font-medium mt-1">
      <?php echo $isFree
          ? 'This course is free — no payment required. Tell us why you\'d be a great fit and our team will review your application.'
          : 'Fill in all required fields to reserve your slot and verify your intake.'; ?>
    </p>
  </div>

  <?php if ($reg_error): ?>
  <div class="mb-6 p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($reg_error); ?></div>
  <?php endif; ?>

  <?php if ($referrer): ?>
  <div class="mb-6 p-4 bg-blue-50 border border-blue-100 text-blue-800 text-xs font-semibold rounded-xl">
    Recommended by <strong><?php echo htmlspecialchars($referrer['full_name']); ?></strong> (<?php echo htmlspecialchars($referrer['registration_no']); ?>)
  </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="<?php echo $pfx; ?>Form" class="space-y-6">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="dh_register" value="1">
    <?php if (!$showCourseSelect): ?>
    <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
    <?php endif; ?>
    <?php if ($isFree): ?>
    <!-- Free course: the real Payment Plan select / M-Pesa textarea below are kept in the DOM
         (just hidden) so the page script's early-return guard on #FeeBox doesn't break unrelated
         functionality, and disabled so only these sentinel values are submitted. -->
    <input type="hidden" name="payment_plan" value="full">
    <input type="hidden" name="payment_reference" value="FREE COURSE - NO PAYMENT REQUIRED">
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <div class="space-y-1.5">
        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_name">Names *</label>
        <input type="text" id="<?php echo $pfx; ?>_name" name="full_name" required placeholder="E.g. Jane Wangari Kamau"
               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white">
      </div>
      <div class="space-y-1.5">
        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_county">County / Country *</label>
        <input type="text" id="<?php echo $pfx; ?>_county" name="county_country" required placeholder="E.g. Kiambu / Kenya"
               value="<?php echo htmlspecialchars($_POST['county_country'] ?? ''); ?>"
               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white">
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <div class="space-y-1.5">
        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_email">Email Address *</label>
        <input type="email" id="<?php echo $pfx; ?>_email" name="email" required placeholder="E.g. jane.wangari@example.com"
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white">
      </div>
      <div class="space-y-1.5">
        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_phone">Phone Number *</label>
        <input type="tel" id="<?php echo $pfx; ?>_phone" name="phone" required placeholder="E.g. 0712345678"
               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white">
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <div class="space-y-1.5">
        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_course">Course Choice *</label>
        <?php if ($showCourseSelect): ?>
          <select id="<?php echo $pfx; ?>_course" name="course_id" required
                  data-change-url="<?php echo htmlspecialchars(url('courses/view.php'), ENT_QUOTES); ?>"
                  data-current-id="<?php echo (int) $course['id']; ?>"
                  class="w-full px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-xs font-bold text-slate-900 cursor-pointer focus:outline-none focus:border-green-600">
            <?php foreach ($courseChoices as $choice): ?>
              <option value="<?php echo (int) $choice['id']; ?>"<?php echo ((int) $choice['id'] === (int) $course['id']) ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars($choice['title']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="text-[10px] font-medium text-green-700">Not the course you wanted? Pick another — the details and fees on the left update to match.</p>
        <?php else: ?>
          <input type="text" id="<?php echo $pfx; ?>_course" readonly value="<?php echo htmlspecialchars($course['title']); ?>"
                 class="w-full px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-xs font-bold text-slate-900">
        <?php endif; ?>
      </div>
      <div class="space-y-1.5">
        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_intake">Intake with Calendar *</label>
        <input type="date" id="<?php echo $pfx; ?>_intake" name="intake_date" required
               value="<?php echo htmlspecialchars($_POST['intake_date'] ?? ''); ?>"
               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white">
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <div class="space-y-2">
        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Study Mode *</label>
        <div class="grid grid-cols-2 gap-2">
          <label class="p-3 border rounded-xl text-xs font-bold cursor-pointer text-center <?php echo $studyMode === 'online' ? 'border-green-600 bg-green-50' : 'border-slate-200'; ?>">
            <input type="radio" name="study_mode" value="online" class="sr-only" <?php echo $studyMode === 'online' ? 'checked' : ''; ?>> Online (KES 1,000)
          </label>
          <label class="p-3 border rounded-xl text-xs font-bold cursor-pointer text-center <?php echo $studyMode === 'physical' ? 'border-green-600 bg-green-50' : 'border-slate-200'; ?>">
            <input type="radio" name="study_mode" value="physical" class="sr-only" <?php echo $studyMode === 'physical' ? 'checked' : ''; ?>> Physical (KES 1,500)
          </label>
        </div>
      </div>
      <div class="space-y-2" <?php echo $isFree ? 'hidden' : ''; ?>>
        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Payment Plan *</label>
        <select name="payment_plan" <?php echo $isFree ? 'disabled' : ''; ?> class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-green-600">
          <option value="monthly" <?php echo $paymentPlan === 'monthly' ? 'selected' : ''; ?>>Monthly installments</option>
          <option value="3months" <?php echo $paymentPlan === '3months' ? 'selected' : ''; ?>>3-month plan</option>
          <option value="full" <?php echo $paymentPlan === 'full' ? 'selected' : ''; ?>>Full payment</option>
        </select>
      </div>
    </div>

    <div class="p-5 bg-green-50 border border-green-200 rounded-2xl space-y-4" <?php echo $isFree ? 'hidden' : ''; ?>>
      <div class="flex items-center gap-2 border-b border-green-100 pb-3">
        <svg class="h-5 w-5 text-[#15A24A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        <span class="text-xs font-extrabold text-green-950 uppercase tracking-wider">M-Pesa Admission Fee Payment Details</span>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
        <div class="bg-white p-3 border border-green-100 rounded-xl text-center">
          <span class="block text-slate-400 text-[9px] uppercase tracking-wider font-extrabold mb-0.5">Paybill Number</span>
          <strong class="text-sm font-extrabold text-slate-900 select-all"><?php echo htmlspecialchars($payment['paybill']); ?></strong>
        </div>
        <div class="bg-white p-3 border border-green-100 rounded-xl text-center">
          <span class="block text-slate-400 text-[9px] uppercase tracking-wider font-extrabold mb-0.5">Account Number</span>
          <strong class="text-sm font-extrabold text-[#15A24A] select-all"><?php echo htmlspecialchars($payment['account_number']); ?></strong>
        </div>
        <div class="bg-white p-3 border border-green-100 rounded-xl text-center">
          <span class="block text-slate-400 text-[9px] uppercase tracking-wider font-extrabold mb-0.5">Business Name</span>
          <strong class="text-sm font-extrabold text-slate-900">Dazu hub</strong>
        </div>
      </div>
      <div class="bg-white border border-green-100 rounded-xl p-4 text-xs space-y-2" id="<?php echo $pfx; ?>FeeBox"
           data-fee="<?php echo $fee; ?>" data-months="<?php echo $mo; ?>">
        <div class="flex justify-between"><span>Registration fee</span><strong id="<?php echo $pfx; ?>RegFee">KES <?php echo number_format($quote['registration_fee']); ?></strong></div>
        <div class="flex justify-between"><span>First installment</span><strong id="<?php echo $pfx; ?>InstAmt">KES <?php echo number_format($quote['installment_amount']); ?></strong></div>
        <div class="flex justify-between font-extrabold text-green-800 border-t border-green-100 pt-2"><span>Pay now</span><span id="<?php echo $pfx; ?>Total">KES <?php echo number_format($quote['total_due_now']); ?></span></div>
      </div>
      <p class="text-[11px] text-green-800 font-semibold leading-relaxed flex items-start gap-2">
        <svg class="h-4 w-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Pay the admission fee to the Paybill above, then paste your full M-Pesa confirmation SMS below.
      </p>
    </div>

    <div class="space-y-1.5" <?php echo $isFree ? 'hidden' : ''; ?>>
      <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_mpesa">Paste M-Pesa / Payment Message *</label>
      <textarea id="<?php echo $pfx; ?>_mpesa" name="payment_reference" <?php echo $isFree ? 'disabled' : 'required'; ?> rows="4"
                placeholder="Paste your full payment confirmation SMS here..."
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white"><?php echo htmlspecialchars($_POST['payment_reference'] ?? ''); ?></textarea>
    </div>

    <?php if ($isFree): ?>
    <div class="space-y-1.5">
      <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500" for="<?php echo $pfx; ?>_reason">Why should you be selected for this free course? *</label>
      <textarea id="<?php echo $pfx; ?>_reason" name="free_course_reason" required rows="4"
                placeholder="Tell us about your goals and why this free course matters to you..."
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-green-600 focus:bg-white"><?php echo htmlspecialchars($_POST['free_course_reason'] ?? ''); ?></textarea>
    </div>
    <?php endif; ?>

    <div class="space-y-2 pt-4 border-t border-slate-100">
      <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-500">National ID / Result Slip (PDF, max 5 MB) *</label>
      <label class="flex flex-col items-center justify-center p-6 bg-slate-50 border border-dashed border-slate-300 rounded-xl cursor-pointer hover:border-green-600 transition-colors min-h-[120px]"
             for="<?php echo $pfx; ?>_id_doc">
        <svg class="h-8 w-8 text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
        <span class="text-xs font-bold text-slate-600" id="<?php echo $pfx; ?>_uploadText">Click to upload PDF</span>
      </label>
      <input type="file" id="<?php echo $pfx; ?>_id_doc" name="id_document" accept="application/pdf" required class="sr-only">
    </div>

    <button type="submit" id="<?php echo $pfx; ?>SubmitBtn"
            class="w-full py-4 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl transition-colors">
      <?php echo $isFree ? 'Submit Free Application' : 'Submit Application'; ?>
    </button>
  </form>
</div>

<script>
(function(){
  var pfx = <?php echo json_encode($pfx); ?>;
  var box = document.getElementById(pfx + 'FeeBox');
  if (!box) return;
  var fee = parseFloat(box.getAttribute('data-fee')) || 0;
  var mo = parseInt(box.getAttribute('data-months'), 10) || 1;
  function el(id){ return document.getElementById(pfx + id); }
  function fmt(n){ return 'KES ' + Math.round(n).toLocaleString(); }
  function recalc(){
    var plan = 'monthly', mode = 'online';
    document.querySelectorAll('#'+pfx+'Form input[name="payment_plan"]').forEach(function(s){ if(s.tagName==='SELECT' && s.value) plan=s.value; });
    var sel = document.querySelector('#'+pfx+'Form select[name="payment_plan"]');
    if (sel) plan = sel.value;
    document.querySelectorAll('#'+pfx+'Form input[name="study_mode"]').forEach(function(r){ if(r.checked) mode=r.value; });
    var reg = mode === 'physical' ? 1500 : 1000;
    var count = plan === 'monthly' ? Math.max(1, mo) : (plan === '3months' ? 3 : 1);
    var inst = fee / count;
    if (el('RegFee')) el('RegFee').textContent = fmt(reg);
    if (el('InstAmt')) el('InstAmt').textContent = fmt(inst);
    if (el('Total')) el('Total').textContent = fmt(reg + inst);
    document.querySelectorAll('#'+pfx+'Form label:has(input[name="study_mode"])').forEach(function(l){
      var inp = l.querySelector('input');
      l.className = 'p-3 border rounded-xl text-xs font-bold cursor-pointer text-center ' + (inp && inp.checked ? 'border-green-600 bg-green-50' : 'border-slate-200');
    });
  }
  document.querySelectorAll('#'+pfx+'Form input[name="study_mode"], #'+pfx+'Form select[name="payment_plan"]').forEach(function(r){ r.addEventListener('change', recalc); });
  recalc();
  var inp = document.getElementById(pfx+'_id_doc'), txt = document.getElementById(pfx+'_uploadText');
  if (inp && txt) inp.addEventListener('change', function(){ txt.textContent = inp.files[0] ? inp.files[0].name : 'Click to upload PDF'; });
  var form = document.getElementById(pfx+'Form');

  /* ---- Course switcher ----
   * Switching course reloads the page for that course, so the sidebar, the fee
   * box and the share link all come from the server and cannot disagree with the
   * dropdown. Everything already typed is carried across; the PDF cannot be
   * (browsers forbid setting a file input), so we only warn when one is attached.
   */
  var courseSel = document.getElementById(pfx + '_course');
  if (courseSel && courseSel.tagName === 'SELECT' && form) {
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
      // Free courses submit fixed sentinel payment_plan/payment_reference values (see hidden
      // inputs above) — never let a stale draft from a previously-viewed paid course overwrite them.
      var isFreeNow = <?php echo $isFree ? 'true' : 'false'; ?>;
      Object.keys(data).forEach(function (n) {
        if (isFreeNow && (n === 'payment_plan' || n === 'payment_reference')) return;
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
    }

    try {
      var saved = sessionStorage.getItem(STORE);
      if (saved) {
        sessionStorage.removeItem(STORE);   // one-shot: never replay on a later load
        var parsed = JSON.parse(saved);
        if (parsed && parsed.t && (Date.now() - parsed.t) < 600000) {
          restore(parsed.d || {});
          recalc();
        }
      }
    } catch (e) { /* sessionStorage blocked (private mode) — fields just start empty */ }

    courseSel.addEventListener('change', function () {
      var base = courseSel.getAttribute('data-change-url');
      var id = courseSel.value;
      if (!base || !id) return;

      var fileInp = document.getElementById(pfx + '_id_doc');
      if (fileInp && fileInp.files && fileInp.files.length) {
        if (!window.confirm('Switching course reloads this form, so you will need to attach your PDF again. Continue?')) {
          courseSel.value = courseSel.getAttribute('data-current-id');
          return;
        }
      }

      try { sessionStorage.setItem(STORE, JSON.stringify({ t: Date.now(), d: snapshot() })); } catch (e) {}
      window.location.href = base + (base.indexOf('?') === -1 ? '?' : '&') + 'id=' + encodeURIComponent(id);
    });
  }

  if (form) form.addEventListener('submit', function(){
    var county = document.getElementById(pfx+'_county');
    var mpesa = document.getElementById(pfx+'_mpesa');
    if (county && mpesa && county.value.trim() && !mpesa.value.includes('[Location:')) {
      mpesa.value = '[Location: ' + county.value.trim() + '] ' + mpesa.value;
    }
    var b = document.getElementById(pfx+'SubmitBtn');
    if (b){ b.disabled=true; b.textContent='Submitting…'; }
  });
})();
</script>
<?php endif; ?>