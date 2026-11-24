<?php
/**
 * Book now — inline on the course page (courses/view.php), right under the "Book now" card
 * rendered by intake_panel.php ('top'). Step 1: the student's details. Step 2: pay the booking
 * fee by Daraja STK push (phone number, then an M-Pesa PIN prompt) instead of pasting a code.
 * Requires: $pdo, $course, $cid, $cfg, $enState (all set by intake_panel.php's 'top' part, which
 * must be included immediately before this file).
 */
if (!isset($pdo) || !isset($course) || empty($enState['bookable'])) return;
require_once __DIR__ . '/../../../app/v1/services/EnrolmentFeeService.php';
$bnFeeText = en_money($cfg['booking_fee']);
$bnSeatInfo = [];
foreach ($enState['bookable'] as $o) $bnSeatInfo[(int) $o['id']] = en_seat_info($pdo, $o, $cid);
?>
<div id="book-now-form" class="bn-card" style="margin-top:16px">
  <span class="bn-pill">Book now</span>
  <h2 class="text-lg font-extrabold text-slate-900 mt-2"><?php echo en_e($course['title']); ?></h2>
  <div id="bnErr" class="bn-err hidden" style="margin-top:16px"></div>
  <form id="bnForm" class="mt-5" novalidate>
    <input type="hidden" name="course_id" value="<?php echo (int) $cid; ?>">
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
    <div class="flex items-center gap-2 text-[11px] font-extrabold uppercase tracking-widest mb-4"><span id="bnDot1" class="bn-step on">1 Your details</span><span id="bnDot2" class="bn-step">2 Pay the booking fee</span></div>

    <div id="bnStep1" class="space-y-4">
      <div><label class="bn-label" for="bnOp">Intake</label>
        <select id="bnOp" name="opening_id" class="bn-field">
          <?php foreach ($enState['bookable'] as $o): $si = $bnSeatInfo[(int) $o['id']]; $full = $si['left'] !== null && $si['left'] <= 0; ?>
            <option value="<?php echo (int) $o['id']; ?>" data-full="<?php echo $full ? 1 : 0; ?>">
              <?php echo en_e($o['title'] . ($o['classes_start'] ? ' · starts ' . en_date($o['classes_start'], 'j M Y') : '') . ($si['left'] !== null ? ($full ? ' · full (waiting list)' : ' · ' . $si['left'] . ' seat' . ($si['left'] === 1 ? '' : 's') . ' left') : '')); ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label class="bn-label" for="bnFn">Full name</label><input id="bnFn" name="full_name" required maxlength="150" autocomplete="name" class="bn-field"></div>
      <div><label class="bn-label" for="bnEm">Email</label><input id="bnEm" type="email" name="email" required maxlength="150" autocomplete="email" class="bn-field"></div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="bn-label" for="bnPh">Primary phone</label><input id="bnPh" type="tel" name="phone" required maxlength="30" autocomplete="tel" placeholder="07xx xxx xxx" class="bn-field"></div>
        <div><label class="bn-label" for="bnPh2">Secondary phone <span class="normal-case font-semibold">(optional)</span></label><input id="bnPh2" type="tel" name="phone2" maxlength="30" placeholder="07xx xxx xxx" class="bn-field"></div>
      </div>
      <div><span class="bn-label">Classes</span>
        <div class="bn-radios text-sm font-semibold">
          <label><input type="radio" name="study_mode" value="online" checked> Online</label>
          <label><input type="radio" name="study_mode" value="physical"> Physical</label>
        </div></div>
      <div><label class="bn-label" for="bnCt">Preferred class time</label><select id="bnCt" name="class_time" class="bn-field"></select></div>
      <div id="bnCampusBox"><span class="bn-label">Campus</span>
        <div class="bn-radios text-sm font-semibold">
          <label><input type="radio" name="campus" value="nairobi"> Nairobi</label>
          <label><input type="radio" name="campus" value="thika"> Thika</label>
        </div></div>
      <label class="flex gap-2 text-xs font-semibold text-slate-600"><input type="checkbox" name="consent" value="1" class="mt-0.5"> I agree to be contacted about my booking.</label>
      <p id="bnErr1" class="text-xs font-semibold text-red-600 hidden"></p>
      <button type="button" id="bnNext" class="bn-btn block">Continue</button>
    </div>

    <div id="bnStep2" class="space-y-4 hidden">
      <div id="bnWaitBox" class="hidden bn-note text-sm text-slate-700"><b>This course is full for this intake.</b> You can join the waiting list. You pay nothing now. If a slot opens we email you and you pay the booking fee then.</div>
      <div id="bnPayBox" class="space-y-4">
        <h2 class="text-lg font-extrabold text-slate-900">Pay the booking fee</h2>
        <div class="bn-fee">
          <p class="text-2xl font-extrabold text-slate-900"><?php echo en_e($bnFeeText); ?></p>
          <p class="text-sm font-bold text-red-700">This fee is not refundable.</p>
          <p class="text-sm text-slate-700" id="bnFeeLine"></p>
        </div>
        <div><label class="bn-label" for="bnPayPhone">M-Pesa phone number</label>
          <input id="bnPayPhone" type="tel" name="pay_phone" maxlength="30" placeholder="07xx xxx xxx" class="bn-field">
          <p class="text-[11px] font-semibold text-slate-500 mt-1">We will send an M-Pesa prompt to this number. Enter your PIN to pay.</p></div>
      </div>
      <div id="bnWaiting" class="hidden bn-note text-sm text-slate-700 text-center">
        <p class="font-bold">Check your phone and enter your M-Pesa PIN.</p>
        <p id="bnWaitingNote" class="mt-1"></p>
      </div>
      <div id="bnActions" class="flex gap-3"><button type="button" id="bnBack" class="bn-btn ghost">Back</button>
        <button type="button" id="bnPay" class="bn-btn" style="flex:1">Pay the booking fee</button></div>
    </div>

    <div id="bnDone" class="space-y-4 hidden text-center">
      <div class="mx-auto w-14 h-14 rounded-full flex items-center justify-center text-2xl text-white" style="background:#44A024">✓</div>
      <h2 class="text-xl font-extrabold text-slate-900">Payment received</h2>
      <p id="bnDoneNote" class="text-sm text-slate-600"></p>
      <div id="bnReceiptBox" style="margin-top:12px;text-align:left"></div>
      <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-top:14px">
        <a id="bnDownload" href="#" class="bn-btn" target="_blank" rel="noopener">Download receipt</a>
      </div>
      <p class="text-xs font-semibold text-slate-500 mt-2">We also emailed a copy of your receipt to you.</p>
    </div>
  </form>
</div>
<style>
#book-now-form .bn-field { width:100%;padding:0.75rem 1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.75rem;font-size:0.875rem; }
#book-now-form .bn-field:focus { outline:none;border-color:#16a34a; }
#book-now-form .bn-label { display:block;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:0.25rem; }
</style>
<script>
(function () {
  var times = <?php echo json_encode(EN_CLASS_TIMES); ?>, f = document.getElementById('bnForm');
  var $ = function (id) { return document.getElementById(id); };
  var csrfToken = <?php echo json_encode(csrf_token()); ?>;
  var checkoutRequestId = null, pollTimer = null, pollStart = 0;

  function mode() { return f.querySelector('input[name=study_mode]:checked').value; }
  function fillTimes() {
    var sel = $('bnCt'), keep = sel.value, m = mode();
    sel.innerHTML = '';
    Object.keys(times[m]).forEach(function (k) { var o = document.createElement('option'); o.value = k; o.textContent = times[m][k]; if (k === keep) o.selected = true; sel.appendChild(o); });
    $('bnCampusBox').style.display = m === 'physical' ? '' : 'none';
    $('bnFeeLine').textContent = m === 'physical' ? 'It is your registration fee.' : 'It will be deducted from your course fees.';
  }
  function full() { var o = $('bnOp').options[$('bnOp').selectedIndex]; return o && o.getAttribute('data-full') === '1'; }
  function go(n) {
    $('bnStep1').classList.toggle('hidden', n !== 1);
    $('bnStep2').classList.toggle('hidden', n !== 2);
    $('bnDone').classList.toggle('hidden', n !== 3);
    $('bnDot1').className = 'bn-step ' + (n === 1 ? 'on' : 'done');
    $('bnDot2').className = 'bn-step ' + (n === 2 ? 'on' : (n === 3 ? 'done' : ''));
    if (n !== 3) document.getElementById('book-now-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function showErr(msg) { $('bnErr').textContent = msg; $('bnErr').classList.toggle('hidden', !msg); }

  $('bnNext').addEventListener('click', function () {
    var e = '', em = $('bnEm').value.trim(), ph = $('bnPh').value.replace(/[\s\-]/g, '');
    if ($('bnFn').value.trim().length < 3) e = 'Enter your full name.';
    else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)) e = 'Enter a valid email address.';
    else if (!/^(\+?254|0)?[17]\d{8}$/.test(ph)) e = 'Enter a valid Kenyan phone number, for example 0712 345 678.';
    else if (mode() === 'physical' && !f.querySelector('input[name=campus]:checked')) e = 'Choose the campus: Nairobi or Thika.';
    else if (!f.querySelector('input[name=consent]').checked) e = 'Please tick that you agree to be contacted.';
    $('bnErr1').textContent = e; $('bnErr1').classList.toggle('hidden', e === '');
    if (e === '') {
      var w = full();
      $('bnWaitBox').classList.toggle('hidden', !w); $('bnPayBox').classList.toggle('hidden', w);
      $('bnPay').textContent = w ? 'Join the waiting list' : 'Pay the booking fee';
      $('bnPayPhone').value = $('bnPh').value;
      go(2);
    }
  });
  $('bnBack').addEventListener('click', function () { clearPoll(); go(1); });

  function clearPoll() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

  function formData() {
    var d = { csrf_token: csrfToken, course_id: f.course_id.value, website: f.website.value, opening_id: f.opening_id.value,
      full_name: f.full_name.value, email: f.email.value, phone: f.phone.value, phone2: f.phone2.value,
      study_mode: mode(), class_time: f.class_time.value, consent: f.querySelector('input[name=consent]').checked ? '1' : '',
      pay_phone: $('bnPayPhone').value };
    var campus = f.querySelector('input[name=campus]:checked');
    if (campus) d.campus = campus.value;
    return d;
  }

  function poll() {
    if (!checkoutRequestId) return;
    fetch(<?php echo json_encode(url('api/intake/stk_status.php')); ?> + '?cr=' + encodeURIComponent(checkoutRequestId))
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r.success) return;
        if (r.payment_status === 'paid') {
          clearPoll();
          $('bnDoneNote').textContent = r.waitlisted
            ? 'Your payment was received, but the last slot was taken a moment before yours, so you are on the waiting list. We will contact you.'
            : 'Your seat is booked. Registration number: ' + (r.registration_no || '');
          if (r.receipt_html) $('bnReceiptBox').innerHTML = r.receipt_html;
          if (r.receipt_url) { $('bnDownload').href = r.receipt_url; $('bnDownload').classList.remove('hidden'); } else { $('bnDownload').classList.add('hidden'); }
          go(3);
        } else if (r.payment_status === 'failed') {
          clearPoll();
          $('bnWaiting').classList.add('hidden'); $('bnActions').classList.remove('hidden');
          showErr(r.message || 'The payment was not completed. You can try again.');
        } else if (Date.now() - pollStart > 90000) {
          clearPoll();
          $('bnWaitingNote').textContent = 'Still waiting? If you entered your PIN this can take a little longer — refresh this page in a minute, or contact us with your phone number.';
        }
      }).catch(function () {});
  }

  $('bnPay').addEventListener('click', function () {
    showErr('');
    var w = full();
    if (!w && !/^(\+?254|0)?[17]\d{8}$/.test($('bnPayPhone').value.replace(/[\s\-]/g, ''))) {
      showErr('Enter a valid Safaricom number for the M-Pesa prompt.');
      return;
    }
    $('bnPay').disabled = true; $('bnBack').disabled = true;
    fetch(<?php echo json_encode(url('api/intake/stk_initiate.php')); ?>, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(formData())
    }).then(function (r) { return r.json(); }).then(function (r) {
      $('bnPay').disabled = false; $('bnBack').disabled = false;
      if (!r.ok) { showErr(r.error || 'Something went wrong. Please try again.'); return; }
      if (r.waitlisted) {
        $('bnDoneNote').textContent = 'All slots are taken for now, so you pay nothing yet. We will email you when a slot opens.';
        go(3);
        return;
      }
      checkoutRequestId = r.checkout_request_id;
      pollStart = Date.now();
      $('bnWaiting').classList.remove('hidden'); $('bnActions').classList.add('hidden'); $('bnWaitingNote').textContent = '';
      pollTimer = setInterval(poll, 3000);
      poll();
    }).catch(function () {
      $('bnPay').disabled = false; $('bnBack').disabled = false;
      showErr('Could not reach the server. Please check your connection and try again.');
    });
  });

  f.querySelectorAll('input[name=study_mode]').forEach(function (r) { r.addEventListener('change', fillTimes); });
  fillTimes();
})();
</script>
