<?php
// Public: Check my booking. Open it with the booking number and email, or with the secure link in the emails (?b=<token>).
// See the status, pay the booking fee when a slot was offered, change the class time or the way you study, move to another intake, or cancel.
// No login needed. A move keeps the payment. A cancel gives no refund, and the page says so before the person confirms.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/EnrolmentService.php';

$pdo = \Models\Connection::getInstance()->getConnection();
if (!en_installed($pdo)) { header('Location: ' . url('courses/index.php')); exit; }
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');
$error = null;
$flash = $_SESSION['en_manage_flash'] ?? null;
unset($_SESSION['en_manage_flash']);
$b = null;

// Lookup by booking number and email (limited, so nobody can guess their way through).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lookup') {
    $_SESSION['en_lookups'] = array_values(array_filter((array) ($_SESSION['en_lookups'] ?? []), fn($t) => $t > time() - 600));
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } elseif (count($_SESSION['en_lookups']) >= 8) {
        $error = 'Too many tries. Please wait a few minutes and try again.';
    } else {
        $_SESSION['en_lookups'][] = time();
        $found = en_by_ref_email($pdo, (string) ($_POST['reference'] ?? ''), (string) ($_POST['email'] ?? ''));
        if ($found && !empty($found['token_salt'])) { header('Location: ' . url('intake/manage.php?b=' . en_token($found))); exit; }
        $error = 'We could not find that booking. Check the booking number and the email you booked with.';
    }
} elseif (!empty($_GET['b'])) {
    $b = en_by_token($pdo, (string) $_GET['b']);
    if (!$b) $error = 'This link is not valid. Use your booking number and email instead.';
}

if ($b && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $self = url('intake/manage.php?b=' . en_token($b));
    $msg = null; $err = null;
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $err = 'Security check failed. Please refresh the page and try again.';
    } else {
        switch ($_POST['action'] ?? '') {
            case 'prefs':
                $r = en_update_prefs($pdo, $b, (string) ($_POST['study_mode'] ?? ''), (string) ($_POST['class_time'] ?? ''), (string) ($_POST['campus'] ?? ''));
                $r['ok'] ? $msg = 'Saved. Your class details are updated.' : $err = $r['error'];
                break;
            case 'move':
                $r = en_move($pdo, $b, (int) ($_POST['opening_id'] ?? 0));
                $r['ok'] ? $msg = 'Done. Your booking and your payment moved to the new intake.' : $err = $r['error'];
                break;
            case 'cancel':
                $r = en_cancel($pdo, $b, 'Cancelled by the student');
                $r['ok'] ? $msg = 'Your booking is cancelled. The booking fee is not refunded.' : $err = $r['error'];
                break;
            case 'pay':
                $r = en_pay_submit_existing($pdo, $b, (string) ($_POST['pay_method'] ?? ''), (string) ($_POST['pay_code'] ?? ''));
                $r['ok'] ? $msg = 'Payment code received. We will check it and email your receipt.' : $err = $r['error'];
                break;
        }
    }
    $_SESSION['en_manage_flash'] = $err ? ['err', $err] : ($msg ? ['ok', $msg] : null);
    header('Location: ' . $self);
    exit;
}

$page_title = 'Check my booking';
$standalone = false;
$field = 'w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600';
$label = 'block text-xs font-bold uppercase tracking-widest text-slate-500 mb-1';
$btn = 'bn-btn block';
ob_start();
?>
<link rel="stylesheet" href="<?php echo en_e(url('assets/css/book-now.css')); ?>?v=1">
<div class="bg-slate-50 min-h-screen py-10 md:py-16">
  <div class="max-w-xl mx-auto px-4">
    <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">
    <?php if ($flash): ?><div style="margin-bottom:16px" class="<?php echo $flash[0] === 'ok' ? 'bn-ok' : 'bn-err'; ?>"><?php echo en_e($flash[1]); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bn-err" style="margin-bottom:16px"><?php echo en_e($error); ?></div><?php endif; ?>
    <?php if (!$b): ?>
      <h1 class="text-2xl font-extrabold text-slate-900">Check my booking</h1>
      <p class="text-sm text-slate-600 mt-1">Enter your booking number and the email you booked with. You can also use the link in your emails.</p>
      <form method="POST" class="mt-5 space-y-4"><?php echo csrf_field(); ?><input type="hidden" name="action" value="lookup">
        <div><label class="<?php echo $label; ?>" for="rf">Booking number</label><input id="rf" name="reference" required maxlength="20" placeholder="BK-XXXXXX" class="<?php echo $field; ?> uppercase" value="<?php echo en_e($_POST['reference'] ?? ''); ?>"></div>
        <div><label class="<?php echo $label; ?>" for="em">Email</label><input id="em" type="email" name="email" required maxlength="150" class="<?php echo $field; ?>" value="<?php echo en_e($_POST['email'] ?? ''); ?>"></div>
        <button class="<?php echo $btn; ?>">Check my booking</button>
      </form>
    <?php else:
      $st = $b['status'];
      $active = in_array($st, ['payment_received', 'confirmed', 'waiting'], true);
      $state = en_state($pdo, (int) $b['course_id']);
      $moves = array_values(array_filter($state['bookable'], fn($o) => (int) $o['id'] !== (int) $b['opening_id']));
      $cfg = en_cfg($pdo);
      $courseUrl = url('courses/view.php?id=' . (int) $b['course_id']);
      $statusText = ['confirmed' => !empty($b['hold_until']) ? 'Confirmed: your seat is held until ' . en_when($b['hold_until']) : 'Confirmed', 'payment_received' => 'Payment code received: waiting for us to confirm',
                     'waiting' => !empty($b['offered_at']) ? 'A slot is offered: pay the booking fee to keep it' : 'On the waiting list', 'converted' => 'Converted: you are enrolled', 'expired' => 'Expired: your seat was released',
                     'cancelled' => 'Cancelled'][$st] ?? $st;
      $tone = ['confirmed' => '#44A024', 'payment_received' => '#b7791f', 'waiting' => '#373435', 'converted' => '#2f7a14', 'expired' => '#6b7280', 'cancelled' => '#b42318'][$st] ?? '#373435';
      $seatOpen = $state['open'] ? en_seat_info($pdo, $state['opening'], (int) $b['course_id']) : null;
      $canEnroll = $state['open'] && ($seatOpen['left'] === null || $seatOpen['left'] > 0);
      $early = $st === 'confirmed' && !empty($b['invited_at']) && ($eaOpening = en_opening($pdo, (int) $b['opening_id'])) && in_array($eaOpening['stage'], ['early', 'open'], true);
    ?>
      <span class="bn-pill" style="background:<?php echo $tone; ?>"><?php echo en_e($statusText); ?></span>
      <h1 class="text-2xl font-extrabold text-slate-900 mt-2">Booking <?php echo en_e($b['reference']); ?></h1>
      <?php if ($st === 'cancelled' && $b['reject_reason']): ?><p class="mt-2 text-sm text-red-700"><b>We could not confirm your payment.</b> Reason: <?php echo en_e($b['reject_reason']); ?>. You can book again and pay with the correct code.</p><?php endif; ?>
      <dl class="mt-4 divide-y divide-slate-100 text-sm">
        <?php foreach ([['Name', $b['full_name']], ['Course', $b['course_title']], ['Intake', $b['opening_title']], ['Classes start', $b['classes_start'] ? en_date($b['classes_start']) : ''],
            ['Classes', ucfirst((string) $b['study_mode']) . ($b['campus'] ? ', ' . ucfirst((string) $b['campus']) : '') . ($b['class_time'] ? ', ' . (EN_CLASS_TIMES[$b['study_mode']][$b['class_time']] ?? '') : '')],
            ['Booking fee', $b['payment_reference'] ? en_money((float) $b['payment_amount']) . ' · ' . ($b['payment_status'] === 'paid' ? 'paid' : 'waiting to be confirmed') : 'not paid yet'],
            ['Payment code', (string) $b['payment_reference']]] as [$k, $v]): if ($v === '' || $v === null) continue; ?>
          <div class="flex justify-between py-2 gap-4"><dt class="text-slate-500"><?php echo en_e($k); ?></dt><dd class="font-semibold text-right"><?php echo en_e($v); ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <p class="text-xs font-semibold text-slate-500 mt-3">The booking fee is not refundable. <?php echo en_e(en_fee_kind_line((string) $b['study_mode'])); ?></p>

      <?php if ($st === 'expired'): ?>
      <div class="bn-note" style="margin-top:20px"><p class="font-extrabold text-slate-900">Your seat was released.</p>
        <p class="text-sm text-slate-600" style="margin-top:4px">It was held until <?php echo en_e(en_when($b['hold_until'])); ?> and was not used, so we gave it to someone else. The booking fee is not refundable, and if you enroll it still counts towards your fees.</p>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:14px">
          <?php if ($canEnroll): ?><a class="bn-btn" href="<?php echo en_e($courseUrl); ?>#enroll-form">Enroll now</a>
          <?php elseif ($state['next']): ?><a class="bn-btn" href="<?php echo en_e(url('intake/book.php?course=' . (int) $b['course_id'] . '&opening=' . (int) $state['next']['id'])); ?>">Book now</a>
          <?php else: ?><a class="bn-btn" href="<?php echo en_e($courseUrl); ?>">See the course</a><?php endif; ?></div></div>
      <?php elseif ($st === 'converted'): ?>
      <div class="bn-ok" style="margin-top:20px">You are enrolled. Your booking fee was counted on your student record, so you do not pay it again.</div>
      <?php elseif ($early): ?>
      <a class="bn-btn block" style="margin-top:20px" href="<?php echo en_e(en_early_url($b)); ?>">Enroll now with early access</a>
      <?php endif; ?>

      <?php if ($st === 'waiting' && !empty($b['offered_at'])): ?>
      <form method="POST" class="mt-6 bn-fee space-y-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="pay">
        <p class="font-extrabold text-slate-900">Pay the booking fee: <?php echo en_e(en_money($cfg['booking_fee'])); ?></p>
        <p class="text-xs text-slate-700">M-Pesa <?php echo $cfg['mpesa_type'] === 'till' ? 'Till' : 'Paybill'; ?>: <b><?php echo en_e($cfg['mpesa_number'] ?: 'ask admissions'); ?></b><?php echo $cfg['mpesa_account'] ? ' · account ' . en_e($cfg['mpesa_account']) : ''; ?>.
          Bank: <b><?php echo en_e($cfg['bank_name'] ?: 'ask admissions'); ?></b>, <?php echo en_e($cfg['bank_account_name']); ?>, <b><?php echo en_e($cfg['bank_account_number']); ?></b>.</p>
        <div class="bn-radios text-sm font-semibold"><label><input type="radio" name="pay_method" value="mpesa" checked> M-Pesa</label><label><input type="radio" name="pay_method" value="bank"> Bank</label></div>
        <input name="pay_code" required maxlength="40" autocomplete="off" placeholder="Paste your M-Pesa code or bank code here" class="<?php echo $field; ?> uppercase">
        <button class="<?php echo $btn; ?>">Send my payment code</button>
      </form>
      <?php endif; ?>

      <?php if ($active): ?>
      <details class="mt-5 border border-slate-200 rounded-xl p-4"><summary class="font-extrabold text-sm cursor-pointer">Change my class time or how I study</summary>
        <form method="POST" class="mt-3 space-y-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="prefs">
          <div class="bn-radios text-sm font-semibold"><label><input type="radio" name="study_mode" value="online" <?php echo $b['study_mode'] === 'online' ? 'checked' : ''; ?>> Online</label><label><input type="radio" name="study_mode" value="physical" <?php echo $b['study_mode'] === 'physical' ? 'checked' : ''; ?>> Physical</label></div>
          <select name="class_time" id="mt" class="<?php echo $field; ?>" data-selected="<?php echo en_e($b['class_time']); ?>"></select>
          <div id="mc" class="bn-radios text-sm font-semibold"><label><input type="radio" name="campus" value="nairobi" <?php echo $b['campus'] === 'nairobi' ? 'checked' : ''; ?>> Nairobi</label><label><input type="radio" name="campus" value="thika" <?php echo $b['campus'] === 'thika' ? 'checked' : ''; ?>> Thika</label></div>
          <p class="text-xs text-slate-500">Changing between Physical and Online changes how the booking fee counts (registration fee, or part of your course fees).</p>
          <button class="<?php echo $btn; ?> dark">Save</button>
        </form></details>
      <details class="mt-3 border border-slate-200 rounded-xl p-4"><summary class="font-extrabold text-sm cursor-pointer">Move to another intake</summary>
        <?php if ($moves): ?><form method="POST" class="mt-3 space-y-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="move">
          <select name="opening_id" class="<?php echo $field; ?>"><?php foreach ($moves as $o): ?><option value="<?php echo (int) $o['id']; ?>"><?php echo en_e($o['title'] . ($o['classes_start'] ? ' · starts ' . en_date($o['classes_start'], 'j M Y') : '')); ?></option><?php endforeach; ?></select>
          <p class="text-xs text-slate-500">Your payment moves with you. Nothing more to pay.</p><button class="<?php echo $btn; ?> dark">Move my booking</button></form>
        <?php else: ?><p class="mt-3 text-sm text-slate-600">No other intake is taking bookings right now.</p><?php endif; ?></details>
      <details class="mt-3 border border-red-200 rounded-xl p-4"><summary class="font-extrabold text-sm cursor-pointer text-red-700">Cancel my booking</summary>
        <form method="POST" class="mt-3 space-y-3" onsubmit="return confirm('Cancel this booking? The booking fee is NOT refunded.')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="cancel">
          <p class="text-sm font-bold text-red-700">The booking fee is not refundable. If you cancel you do not get the <?php echo en_e(en_money((float) ($b['payment_amount'] ?: $cfg['booking_fee']))); ?> back, and your seat goes to someone else.</p>
          <button class="<?php echo $btn; ?> red">Cancel my booking</button></form></details>
      <script>(function () { var times = <?php echo json_encode(EN_CLASS_TIMES); ?>, f = document.querySelector('input[name=study_mode]').form, sel = document.getElementById('mt');
        function fill() { var m = f.querySelector('input[name=study_mode]:checked').value, keep = sel.value || sel.getAttribute('data-selected'); sel.innerHTML = '';
          Object.keys(times[m]).forEach(function (k) { var o = document.createElement('option'); o.value = k; o.textContent = times[m][k]; if (k === keep) o.selected = true; sel.appendChild(o); });
          document.getElementById('mc').style.display = m === 'physical' ? '' : 'none'; }
        f.querySelectorAll('input[name=study_mode]').forEach(function (r) { r.addEventListener('change', fill); }); fill(); })();</script>
      <?php elseif ($st === 'cancelled'): ?>
        <a href="<?php echo en_e(url('intake/book.php?course=' . (int) $b['course_id'])); ?>" class="mt-5 <?php echo $btn; ?>">Book now</a>
      <?php endif; ?>
      <a href="<?php echo en_e($courseUrl); ?>" class="mt-4 block text-center text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-green-600">Back to the course</a>
    <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
