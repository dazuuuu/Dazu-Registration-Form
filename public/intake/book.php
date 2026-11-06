<?php
// Public: Book now (simple enrolment, migrations 086 and 101). ?course=<id>[&opening=<id>]
//   Step 1: the intake, your details, how you will study. Step 2: pay the booking fee and paste the payment code.
//   Course full: "Join the waiting list", no payment until a slot is offered. Also handles "Tell me when the next intake opens" (action=interest).
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../app/v1/services/EnrolmentService.php';

$pdo = \Models\Connection::getInstance()->getConnection();
$course = (new \Models\CourseModel($pdo))->getById((int) ($_GET['course'] ?? $_POST['course_id'] ?? 0));
$courseUrl = $course ? url('courses/view.php?id=' . (int) $course['id']) : url('courses/index.php');
if (!$course || !course_is_shareable($course) || !en_installed($pdo)) { header('Location: ' . url('courses/index.php')); exit; }
$cid = (int) $course['id'];
en_track_source();
$cfg = en_cfg($pdo);
$error = null;
$done = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } elseif (trim((string) ($_POST['website'] ?? '')) !== '') {
        $error = 'Please try again.';   // spam trap: people never fill this hidden field
    } elseif (($_POST['action'] ?? '') === 'interest') {
        $r = en_interest_add($pdo, $cid, (string) ($_POST['full_name'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['phone'] ?? ''));
        if ($r['ok']) { header('Location: ' . $courseUrl . '&interest=1#intake-closed'); exit; }
        $_SESSION['en_interest_error'] = $r['error'];
        header('Location: ' . $courseUrl . '#intake-closed');
        exit;
    } else {
        $r = en_book($pdo, $course, $_POST, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($r['ok']) {
            $_SESSION['en_done'] = (int) $r['booking']['id'];
            header('Location: ' . url('intake/book.php?course=' . $cid . '&done=1'));
            exit;
        }
        $error = $r['error'];
        $dupLink = !empty($r['duplicate']) ? en_manage_url($r['duplicate']) : null;
    }
}
if (!empty($_GET['done']) && !empty($_SESSION['en_done'])) $done = en_booking($pdo, (int) $_SESSION['en_done']);

$state = en_state($pdo, $cid);
if (!$done && !$state['bookable']) { header('Location: ' . $courseUrl); exit; }

$page_title = 'Book now: ' . ($course['title'] ?? 'Dazu Training Institute');
$standalone = false;
$v = fn($k, $d = '') => en_e($_POST[$k] ?? $d);
$field = 'w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600';
$label = 'block text-xs font-bold uppercase tracking-widest text-slate-500 mb-1';
$feeText = en_money($cfg['booking_fee']);
ob_start();
?>
<link rel="stylesheet" href="<?php echo en_e(url('assets/css/book-now.css')); ?>?v=1">
<div class="bg-slate-50 min-h-screen py-10 md:py-16">
  <div class="max-w-xl mx-auto px-4">
    <a href="<?php echo en_e($courseUrl); ?>" class="mb-6 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-green-600">← Back to the course</a>
    <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">
    <?php if ($done): $wait = $done['status'] === 'waiting'; ?>
      <div class="text-center space-y-3">
        <div class="mx-auto w-14 h-14 rounded-full flex items-center justify-center text-2xl text-white" style="background:<?php echo $wait ? '#373435' : '#44A024'; ?>">✓</div>
        <h1 class="text-2xl font-extrabold text-slate-900"><?php echo $wait ? 'You are on the waiting list' : 'Payment code received'; ?></h1>
        <p class="text-sm text-slate-600"><?php echo $wait
            ? 'All slots are taken for now, so you pay nothing yet. If a slot opens we will email ' . en_e($done['email']) . ' and you can pay the booking fee then.'
            : 'Your seat is held. We check every payment by hand and will email your receipt to ' . en_e($done['email']) . ' as soon as it is confirmed.'; ?></p>
      </div>
      <dl class="mt-6 divide-y divide-slate-100 text-sm">
        <div class="flex justify-between py-2"><dt class="text-slate-500">Booking number</dt><dd class="font-extrabold"><?php echo en_e($done['reference']); ?></dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Course</dt><dd class="font-semibold text-right"><?php echo en_e($done['course_title']); ?></dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Intake</dt><dd class="font-semibold"><?php echo en_e($done['opening_title']); ?></dd></div>
        <?php if ($done['classes_start']): ?><div class="flex justify-between py-2"><dt class="text-slate-500">Classes start</dt><dd class="font-semibold"><?php echo en_e(en_date($done['classes_start'])); ?></dd></div><?php endif; ?>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Classes</dt><dd class="font-semibold"><?php echo en_e(ucfirst((string) $done['study_mode']) . ($done['campus'] ? ', ' . ucfirst((string) $done['campus']) : '')); ?></dd></div>
        <?php if (!$wait): ?><div class="flex justify-between py-2"><dt class="text-slate-500">Amount</dt><dd class="font-semibold"><?php echo en_e(en_money((float) $done['payment_amount'])); ?></dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Payment code</dt><dd class="font-semibold"><?php echo en_e($done['payment_reference']); ?></dd></div><?php endif; ?>
      </dl>
      <?php if (!$wait): ?>
      <a href="<?php echo en_e(en_whatsapp_url($pdo, $done)); ?>" target="_blank" rel="noopener" class="bn-btn wa block" style="margin-top:20px">Send your payment message to admin on WhatsApp</a>
      <?php endif; ?>
      <a href="<?php echo en_e(en_manage_url($done)); ?>" class="bn-btn line block" style="margin-top:12px">Check my booking</a>
      <a href="<?php echo en_e($courseUrl); ?>" class="bn-btn dark block" style="margin-top:12px">Back to the course</a>
    <?php else:
      $sel = (int) ($_POST['opening_id'] ?? $_GET['opening'] ?? 0);
      $seatInfo = []; foreach ($state['bookable'] as $o) $seatInfo[(int) $o['id']] = en_seat_info($pdo, $o, $cid);
      if (!isset($seatInfo[$sel])) $sel = (int) $state['bookable'][0]['id'];
      $mode = ($_POST['study_mode'] ?? 'online') === 'physical' ? 'physical' : 'online';
    ?>
      <span class="bn-pill">Book now</span>
      <h1 class="text-2xl font-extrabold text-slate-900 mt-2"><?php echo en_e($course['title']); ?></h1>
      <?php if ($error): ?><div class="bn-err" style="margin-top:16px"><?php echo en_e($error); ?>
        <?php if (!empty($dupLink)): ?> <a class="underline" href="<?php echo en_e($dupLink); ?>">Manage my booking</a><?php endif; ?></div><?php endif; ?>
      <form method="POST" class="mt-5" id="bookForm" novalidate><?php echo csrf_field(); ?>
        <input type="hidden" name="course_id" value="<?php echo $cid; ?>">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
        <div class="flex items-center gap-2 text-[11px] font-extrabold uppercase tracking-widest mb-4"><span id="dot1" class="bn-step on">1 Your details</span><span id="dot2" class="bn-step">2 Pay the booking fee</span></div>

        <div id="step1" class="space-y-4">
          <div><label class="<?php echo $label; ?>" for="op">Intake</label>
            <select id="op" name="opening_id" class="<?php echo $field; ?>">
              <?php foreach ($state['bookable'] as $o): $si = $seatInfo[(int) $o['id']]; $full = $si['left'] !== null && $si['left'] <= 0; ?>
                <option value="<?php echo (int) $o['id']; ?>" data-full="<?php echo $full ? 1 : 0; ?>" <?php echo $sel === (int) $o['id'] ? 'selected' : ''; ?>>
                  <?php echo en_e($o['title'] . ($o['classes_start'] ? ' · starts ' . en_date($o['classes_start'], 'j M Y') : '') . ($si['left'] !== null ? ($full ? ' · full (waiting list)' : ' · ' . $si['left'] . ' seat' . ($si['left'] === 1 ? '' : 's') . ' left') : '')); ?></option>
              <?php endforeach; ?>
            </select></div>
          <div><label class="<?php echo $label; ?>" for="fn">Full name</label><input id="fn" name="full_name" required maxlength="150" autocomplete="name" class="<?php echo $field; ?>" value="<?php echo $v('full_name'); ?>"></div>
          <div><label class="<?php echo $label; ?>" for="em">Email</label><input id="em" type="email" name="email" required maxlength="150" autocomplete="email" class="<?php echo $field; ?>" value="<?php echo $v('email'); ?>"></div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="<?php echo $label; ?>" for="ph">Primary phone</label><input id="ph" type="tel" name="phone" required maxlength="30" autocomplete="tel" placeholder="07xx xxx xxx" class="<?php echo $field; ?>" value="<?php echo $v('phone'); ?>"></div>
            <div><label class="<?php echo $label; ?>" for="ph2">Secondary phone <span class="normal-case font-semibold">(optional)</span></label><input id="ph2" type="tel" name="phone2" maxlength="30" placeholder="07xx xxx xxx" class="<?php echo $field; ?>" value="<?php echo $v('phone2'); ?>"></div>
          </div>
          <div><span class="<?php echo $label; ?>">Classes</span>
            <div class="bn-radios text-sm font-semibold">
              <label><input type="radio" name="study_mode" value="online" <?php echo $mode === 'online' ? 'checked' : ''; ?>> Online</label>
              <label><input type="radio" name="study_mode" value="physical" <?php echo $mode === 'physical' ? 'checked' : ''; ?>> Physical</label>
            </div></div>
          <div><label class="<?php echo $label; ?>" for="ct">Preferred class time</label><select id="ct" name="class_time" class="<?php echo $field; ?>" data-selected="<?php echo $v('class_time'); ?>"></select></div>
          <div id="campusBox"><span class="<?php echo $label; ?>">Campus</span>
            <div class="bn-radios text-sm font-semibold">
              <label><input type="radio" name="campus" value="nairobi" <?php echo ($_POST['campus'] ?? '') === 'nairobi' ? 'checked' : ''; ?>> Nairobi</label>
              <label><input type="radio" name="campus" value="thika" <?php echo ($_POST['campus'] ?? '') === 'thika' ? 'checked' : ''; ?>> Thika</label>
            </div></div>
          <label class="flex gap-2 text-xs font-semibold text-slate-600"><input type="checkbox" name="consent" value="1" <?php echo !empty($_POST['consent']) ? 'checked' : ''; ?> class="mt-0.5"> I agree to be contacted about my booking.</label>
          <p id="err1" class="text-xs font-semibold text-red-600 hidden"></p>
          <button type="button" id="next" class="bn-btn block">Continue</button>
        </div>

        <div id="step2" class="space-y-4 hidden">
          <div id="waitBox" class="hidden bn-note text-sm text-slate-700"><b>This course is full for this intake.</b> You can join the waiting list. You pay nothing now. If a slot opens we email you and you pay the booking fee then.</div>
          <div id="payBox" class="space-y-4">
            <h2 class="text-lg font-extrabold text-slate-900">Pay the booking fee</h2>
            <div class="bn-fee">
              <p class="text-2xl font-extrabold text-slate-900"><?php echo en_e($feeText); ?></p>
              <p class="text-sm font-bold text-red-700">This fee is not refundable.</p>
              <p class="text-sm text-slate-700" id="feeLine"></p>
            </div>
            <div class="text-sm text-slate-700 space-y-3">
              <div class="bn-box"><p class="font-extrabold text-slate-900 mb-1">Pay by M-Pesa</p>
                <ol class="list-decimal ml-5 space-y-0.5"><li>Open M-Pesa and choose <b><?php echo $cfg['mpesa_type'] === 'till' ? 'Buy Goods and Services' : 'Lipa na M-Pesa, Paybill'; ?></b>.</li>
                  <li><?php echo $cfg['mpesa_type'] === 'till' ? 'Till number' : 'Business number'; ?>: <b><?php echo en_e($cfg['mpesa_number'] ?: 'ask admissions'); ?></b></li>
                  <?php if ($cfg['mpesa_type'] !== 'till' || $cfg['mpesa_account']): ?><li>Account name: <b><?php echo en_e($cfg['mpesa_account'] ?: 'your full name'); ?></b></li><?php endif; ?>
                  <li>Amount: <b><?php echo en_e($feeText); ?></b>. Send, then copy the code from the M-Pesa message.</li></ol></div>
              <div class="bn-box"><p class="font-extrabold text-slate-900 mb-1">Pay by bank</p>
                <ol class="list-decimal ml-5 space-y-0.5"><li>Bank: <b><?php echo en_e($cfg['bank_name'] ?: 'ask admissions'); ?></b></li><li>Account name: <b><?php echo en_e($cfg['bank_account_name'] ?: 'Dazu Training Institute'); ?></b></li>
                  <li>Account number: <b><?php echo en_e($cfg['bank_account_number'] ?: 'ask admissions'); ?></b></li><li>Amount: <b><?php echo en_e($feeText); ?></b>. Keep the bank slip or transaction code.</li></ol></div>
            </div>
            <div><span class="<?php echo $label; ?>">How did you pay?</span>
              <div class="bn-radios text-sm font-semibold"><label><input type="radio" name="pay_method" value="mpesa" <?php echo ($_POST['pay_method'] ?? 'mpesa') !== 'bank' ? 'checked' : ''; ?>> M-Pesa</label><label><input type="radio" name="pay_method" value="bank" <?php echo ($_POST['pay_method'] ?? '') === 'bank' ? 'checked' : ''; ?>> Bank</label></div></div>
            <div><label class="<?php echo $label; ?>" for="pc">Paste your M-Pesa code or bank code here</label><input id="pc" name="pay_code" autocomplete="off" maxlength="40" class="<?php echo $field; ?> uppercase" value="<?php echo $v('pay_code'); ?>">
              <p id="pcHint" class="text-[11px] font-semibold text-slate-500 mt-1"></p></div>
          </div>
          <div class="flex gap-3"><button type="button" id="back" class="bn-btn ghost">Back</button>
            <button id="submit" class="bn-btn" style="flex:1">Book now</button></div>
        </div>
      </form>
      <script>
      (function () {
        var times = <?php echo json_encode(EN_CLASS_TIMES); ?>, f = document.getElementById('bookForm');
        var $ = function (id) { return document.getElementById(id); };
        function mode() { return f.querySelector('input[name=study_mode]:checked').value; }
        function fillTimes() {
          var sel = $('ct'), keep = sel.value || sel.getAttribute('data-selected'), m = mode();
          sel.innerHTML = '';
          Object.keys(times[m]).forEach(function (k) { var o = document.createElement('option'); o.value = k; o.textContent = times[m][k]; if (k === keep) o.selected = true; sel.appendChild(o); });
          $('campusBox').style.display = m === 'physical' ? '' : 'none';
          $('feeLine').textContent = m === 'physical' ? 'It is your registration fee.' : 'It will be deducted from your course fees.';
        }
        function full() { var o = $('op').options[$('op').selectedIndex]; return o && o.getAttribute('data-full') === '1'; }
        function method() { return f.querySelector('input[name=pay_method]:checked').value; }
        function codeOk() {
          var c = $('pc').value.replace(/\s+/g, '').toUpperCase();
          return method() === 'mpesa' ? /^[A-Z0-9]{10}$/.test(c) : /^[A-Z0-9\-\/_.]{6,30}$/.test(c);
        }
        function check() {
          $('pcHint').textContent = method() === 'mpesa' ? 'An M-Pesa code has 10 letters and numbers, for example QGH7K2M9XP.' : 'A bank code has 6 to 30 letters and numbers.';
          var w = full();
          $('waitBox').classList.toggle('hidden', !w); $('payBox').classList.toggle('hidden', w);
          $('submit').textContent = w ? 'Join the waiting list' : 'Book now';
          $('submit').disabled = w ? false : !codeOk();
        }
        function go(n) { $('step1').classList.toggle('hidden', n !== 1); $('step2').classList.toggle('hidden', n !== 2);
          $('dot1').className = 'bn-step ' + (n === 1 ? 'on' : 'done'); $('dot2').className = 'bn-step ' + (n === 2 ? 'on' : ''); window.scrollTo({ top: 0, behavior: 'smooth' }); }
        $('next').addEventListener('click', function () {
          var e = '', em = $('em').value.trim(), ph = $('ph').value.replace(/[\s\-]/g, '');
          if ($('fn').value.trim().length < 3) e = 'Enter your full name.';
          else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)) e = 'Enter a valid email address.';
          else if (!/^(\+?254|0)?[17]\d{8}$/.test(ph)) e = 'Enter a valid Kenyan phone number, for example 0712 345 678.';
          else if (mode() === 'physical' && !f.querySelector('input[name=campus]:checked')) e = 'Choose the campus: Nairobi or Thika.';
          else if (!f.querySelector('input[name=consent]').checked) e = 'Please tick that you agree to be contacted.';
          $('err1').textContent = e; $('err1').classList.toggle('hidden', e === ''); if (e === '') { check(); go(2); }
        });
        $('back').addEventListener('click', function () { go(1); });
        f.querySelectorAll('input[name=study_mode]').forEach(function (r) { r.addEventListener('change', fillTimes); });
        f.querySelectorAll('input[name=pay_method]').forEach(function (r) { r.addEventListener('change', check); });
        $('pc').addEventListener('input', check); $('op').addEventListener('change', check);
        fillTimes(); check();
        <?php if ($error && !empty($_POST)): ?>go(<?php echo !empty($r['pay_error']) ? 2 : 1; ?>);<?php endif; ?>
      })();
      </script>
    <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
