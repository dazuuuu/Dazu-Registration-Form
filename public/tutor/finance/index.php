<?php
// public/tutor/finance/index.php — the tutor's Finance (wallet): their pay, payments to confirm, and missed-payment claims.
// Locked by a 6-digit wallet PIN: set on the first visit, asked again each new login (or after "Lock"). 5 wrong tries lock it for 15 minutes.
// A claim (student + months or amount) goes to the superadmin; when approved it shows as owed until paid and confirmed.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollRequestModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\PayrollCalculator;

Middleware::requireTutor();
$pdo = \Models\Connection::getInstance()->getConnection();
$tutor = (new \Models\TutorModel($pdo))->getByUserId((int) $_SESSION['user_id']);
if (!$tutor) { header('Location: ' . url('tutor/dashboard/index.php')); exit; }
$tid = (int) $tutor['id'];
$self = url('tutor/finance/index.php');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$pinReady = true;
try { $pdo->query("SELECT 1 FROM tutor_wallet_pins LIMIT 0"); } catch (Throwable $ex) { $pinReady = false; }
$pinRow = null;
if ($pinReady) {
    $st = $pdo->prepare("SELECT * FROM tutor_wallet_pins WHERE tutor_id = ?");
    $st->execute([$tid]);
    $pinRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
$unlocked = !$pinReady || (($_SESSION['tutor_wallet_ok'] ?? 0) === $tid);
$pinError = '';
$now = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $pinError = 'Security check failed. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'set_pin' && $pinReady && !$pinRow) {
        $p1 = (string) ($_POST['pin'] ?? ''); $p2 = (string) ($_POST['pin2'] ?? '');
        if (!preg_match('/^\d{6}$/', $p1)) $pinError = 'The PIN must be exactly 6 digits.';
        elseif ($p1 !== $p2) $pinError = 'The two PINs do not match.';
        elseif (preg_match('/^(\d)\1{5}$/', $p1) || in_array($p1, ['123456', '654321'], true)) $pinError = 'Choose a PIN that is harder to guess.';
        else {
            $pdo->prepare("INSERT INTO tutor_wallet_pins (tutor_id, pin_hash, created_at, updated_at) VALUES (?, ?, ?, ?)")->execute([$tid, password_hash($p1, PASSWORD_DEFAULT), $now, $now]);
            $_SESSION['tutor_wallet_ok'] = $tid;
            header('Location: ' . $self); exit;
        }
    } elseif (($_POST['action'] ?? '') === 'unlock' && $pinRow) {
        if (!empty($pinRow['locked_until']) && $pinRow['locked_until'] > $now) {
            $pinError = 'Too many wrong tries. Try again after ' . date('H:i', strtotime($pinRow['locked_until'])) . '.';
        } elseif (password_verify((string) ($_POST['pin'] ?? ''), $pinRow['pin_hash'])) {
            $pdo->prepare("UPDATE tutor_wallet_pins SET failed_tries = 0, locked_until = NULL WHERE tutor_id = ?")->execute([$tid]);
            $_SESSION['tutor_wallet_ok'] = $tid;
            header('Location: ' . $self); exit;
        } else {
            $tries = (int) $pinRow['failed_tries'] + 1;
            $lock = $tries >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
            $pdo->prepare("UPDATE tutor_wallet_pins SET failed_tries = ?, locked_until = ? WHERE tutor_id = ?")->execute([$lock ? 0 : $tries, $lock, $tid]);
            $pinError = $lock ? 'Too many wrong tries. Your wallet is locked for 15 minutes.' : 'Wrong PIN. ' . (5 - $tries) . ' tries left.';
        }
    } elseif (($_POST['action'] ?? '') === 'lock') {
        unset($_SESSION['tutor_wallet_ok']);
        header('Location: ' . $self); exit;
    } elseif (($_POST['action'] ?? '') === 'claim' && $unlocked) {
        $prm = new \Models\PayrollRequestModel($pdo);
        $amount = str_replace(',', '', trim((string) ($_POST['amount'] ?? '')));
        $months = (int) ($_POST['months'] ?? 0);
        $r = $prm->create($tid, 'claim', (int) ($_POST['student'] ?? 0) ?: null, ($months <= 0 && is_numeric($amount)) ? (float) $amount : null,
            $months > 0 ? (string) $months : null, (string) ($_POST['reason'] ?? ''));
        SessionModel::setFlash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Sent to the superadmin. It shows here as owed once they approve it.' : htmlspecialchars($r['error']));
        header('Location: ' . $self . '#claim'); exit;
    }
}

$page_title = 'Finance';
ob_start();
?>
<style>
.fn-lock{max-width:380px;margin:6vh auto;text-align:center}
.fn-lock .ic{width:64px;height:64px;border-radius:50%;background:#ecfdf5;color:#15803d;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 14px}
.fn-pin{letter-spacing:.6em;text-align:center;font-size:1.4rem;font-weight:700}
.fn-find{position:relative}
.fn-list{position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 10px 24px rgba(0,0,0,.08);max-height:260px;overflow:auto;display:none}
.fn-list button{display:block;width:100%;text-align:left;border:0;background:none;padding:8px 12px;font-size:.9rem}
.fn-list button:hover,.fn-list button.on{background:#f0fdf4}
.fn-list small{color:#6b7280}
.fn-pick{display:none;align-items:center;gap:8px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:8px 12px;font-size:.9rem}
</style>
<?php if (!$unlocked): ?>
  <div class="card-tutor fn-lock p-4">
    <div class="ic"><i class="fas fa-wallet"></i></div>
    <?php if (!$pinRow): ?>
      <h5 class="mb-1">Set your wallet PIN</h5>
      <p class="small text-muted">Choose a 6-digit PIN. You will enter it every time you open Finance.</p>
      <?php if ($pinError): ?><div class="alert alert-danger py-2 small"><?php echo $e($pinError); ?></div><?php endif; ?>
      <form method="POST" autocomplete="off"><?php echo csrf_field(); ?><input type="hidden" name="action" value="set_pin">
        <input name="pin" class="form-control fn-pin mb-2" type="password" inputmode="numeric" pattern="\d{6}" maxlength="6" minlength="6" required placeholder="••••••" autofocus>
        <input name="pin2" class="form-control fn-pin mb-3" type="password" inputmode="numeric" pattern="\d{6}" maxlength="6" minlength="6" required placeholder="Repeat">
        <button class="btn btn-brand w-100">Save PIN and open</button></form>
    <?php else: ?>
      <h5 class="mb-1">Enter your wallet PIN</h5>
      <p class="small text-muted">Forgot it? Ask the superadmin to reset it.</p>
      <?php if ($pinError): ?><div class="alert alert-danger py-2 small"><?php echo $e($pinError); ?></div><?php endif; ?>
      <form method="POST" autocomplete="off"><?php echo csrf_field(); ?><input type="hidden" name="action" value="unlock">
        <input name="pin" class="form-control fn-pin mb-3" type="password" inputmode="numeric" pattern="\d{6}" maxlength="6" minlength="6" required placeholder="••••••" autofocus>
        <button class="btn btn-brand w-100">Open wallet</button></form>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0"><i class="fas fa-wallet me-2 text-success"></i>Finance</h4>
    <?php if ($pinReady): ?><form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="lock"><button class="btn btn-sm btn-outline-secondary"><i class="fas fa-lock me-1"></i>Lock</button></form><?php endif; ?>
  </div>
  <?php foreach (['success', 'error'] as $fk): if ($fm = SessionModel::getFlash($fk)): ?><div class="alert alert-<?php echo $fk === 'error' ? 'danger' : 'success'; ?> py-2"><?php echo $fm; ?></div><?php endif; endforeach; ?>

  <?php
  $prm = new \Models\PayrollRequestModel($pdo);
  if ($prm->isInstalled()):
      $people = $prm->studentsForSearch($tid);
      $claims = array_values(array_filter($prm->list($tid), fn($r) => $r['type'] === 'claim'));
  ?>
  <div class="card-tutor mt-2" id="claim"><div class="head">Missed payment? Tell the superadmin</div><div class="p-3">
    <p class="small text-muted mb-2">Find the student, then give the number of months or the amount you were not paid. Once approved it shows as owed below until the superadmin pays it and you confirm.</p>
    <form method="POST" class="row g-2 align-items-end" id="claimForm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="claim"><input type="hidden" name="student" id="clStudent">
      <div class="col-md-5"><label class="form-label small mb-0">Student</label>
        <div class="fn-find"><input class="form-control form-control-sm" id="clSearch" placeholder="Type a name, registration number or course…" autocomplete="off">
          <div class="fn-list" id="clList"></div></div>
        <div class="fn-pick mt-1" id="clPick"><i class="fas fa-user-check text-success"></i><span id="clPickName"></span><button type="button" class="btn btn-link btn-sm p-0 ms-auto" id="clClear">Change</button></div></div>
      <div class="col-6 col-md-2"><label class="form-label small mb-0">Months not paid</label><input type="number" name="months" min="0" max="24" class="form-control form-control-sm" placeholder="e.g. 1"></div>
      <div class="col-6 col-md-2"><label class="form-label small mb-0">or amount (KES)</label><input name="amount" inputmode="decimal" class="form-control form-control-sm" placeholder="e.g. 5000"></div>
      <div class="col-md-3"><label class="form-label small mb-0">Note</label><input name="reason" class="form-control form-control-sm" maxlength="500" required placeholder="e.g. October not paid"></div>
      <div class="col-12"><button class="btn btn-sm btn-brand"><i class="fas fa-paper-plane me-1"></i>Send to the superadmin</button></div>
    </form>
    <?php if ($claims): ?>
    <div class="table-responsive mt-3"><table class="table table-sm small mb-0">
      <thead class="table-light"><tr><th>Student</th><th class="text-end">Amount</th><th>Sent</th><th>Status</th></tr></thead>
      <?php foreach (array_slice($claims, 0, 15) as $c): ?>
        <tr><td><?php echo $e($c['student_name']); ?><div class="text-muted"><?php echo $e($c['reason']); ?><?php echo $c['decision_note'] ? ' · Superadmin: ' . $e($c['decision_note']) : ''; ?></div></td>
          <td class="text-end"><?php echo PayrollCalculator::money((float) $c['amount']); ?><?php echo $c['months'] ? '<div class="text-muted">' . (int) $c['months'] . ' month' . ((int) $c['months'] > 1 ? 's' : '') . '</div>' : ''; ?></td>
          <td><?php echo $e(date('j M Y', strtotime($c['created_at']))); ?></td>
          <td><span class="badge bg-<?php echo ['pending' => 'warning text-dark', 'approved' => 'success', 'declined' => 'secondary', 'cancelled' => 'light text-dark'][$c['status']]; ?>"><?php echo $c['status'] === 'approved' ? 'Approved: owed until paid' : ucfirst($c['status']); ?></span></td></tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </div></div>
  <script>
  (function () {
    var people = <?php echo json_encode(array_map(fn($p) => ['id' => (int) $p['user_id'], 'n' => (string) $p['name'], 'r' => (string) $p['reg_no'], 'c' => (string) $p['course']], $people), JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var q = document.getElementById('clSearch'), list = document.getElementById('clList'), hid = document.getElementById('clStudent'),
        pick = document.getElementById('clPick'), pickName = document.getElementById('clPickName'), at = -1;
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
    function render() {
      var t = q.value.trim().toLowerCase(), words = t.split(/\s+/).filter(Boolean);
      var hits = people.filter(function (p) { var s = (p.n + ' ' + p.r + ' ' + p.c).toLowerCase(); return words.every(function (w) { return s.indexOf(w) > -1; }); }).slice(0, 12);
      at = -1;
      list.innerHTML = hits.length ? hits.map(function (p) { return '<button type="button" data-id="' + p.id + '" data-n="' + esc(p.n) + '">' + esc(p.n) + '<br><small>' + esc([p.r, p.c].filter(Boolean).join(' · ')) + '</small></button>'; }).join('')
                                   : '<div class="p-2 small text-muted">No student matches.</div>';
      list.style.display = 'block';
    }
    function choose(b) { hid.value = b.dataset.id; pickName.textContent = b.dataset.n; pick.style.display = 'flex'; q.style.display = 'none'; list.style.display = 'none'; }
    q.addEventListener('input', render); q.addEventListener('focus', render);
    q.addEventListener('keydown', function (ev) {
      var bs = list.querySelectorAll('button'); if (!bs.length) return;
      if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') { ev.preventDefault(); at = (at + (ev.key === 'ArrowDown' ? 1 : -1) + bs.length) % bs.length; bs.forEach(function (b, i) { b.classList.toggle('on', i === at); }); }
      if (ev.key === 'Enter') { ev.preventDefault(); choose(bs[Math.max(0, at)]); }
    });
    list.addEventListener('mousedown', function (ev) { var b = ev.target.closest('button'); if (b) { ev.preventDefault(); choose(b); } });
    q.addEventListener('blur', function () { setTimeout(function () { list.style.display = 'none'; }, 150); });
    document.getElementById('clClear').onclick = function () { hid.value = ''; pick.style.display = 'none'; q.style.display = ''; q.value = ''; q.focus(); };
    document.getElementById('claimForm').addEventListener('submit', function (ev) { if (!hid.value) { ev.preventDefault(); alert('Choose the student from the list first.'); q.focus(); } });
  })();
  </script>
  <?php endif; ?>

  <?php $tutorFinancePage = true; require __DIR__ . '/../dashboard/payroll_section.php'; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
