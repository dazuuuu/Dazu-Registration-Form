<?php
// public/superadmin/dashboard.php — finance and control overview (superadmin only).
require_once __DIR__ . '/../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/helpers/superadmin_pin.php';
require_once __DIR__ . '/../../app/v1/models/DashboardModel.php';
require_once __DIR__ . '/../../app/v1/models/PayrollModel.php';
require_once __DIR__ . '/../../app/v1/models/EmployeeModel.php';
require_once __DIR__ . '/../../app/v1/services/SmsModuleService.php';

use Models\DashboardModel;
use Models\PayrollModel;
use Models\EmployeeModel;

Middleware::requireSuperPortal();

$smsService = new SmsModuleService($pdo, (int) ($_SESSION['user_id'] ?? 0));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sms_run_reminders'])) {
    $r = $smsService->runProspectReminders();
    $_SESSION['flash_success'] = 'Prospect reminders sent: ' . $r['today'] . ' due today, ' . $r['two_day'] . ' two-day.';
    redirect(url('superadmin/dashboard.php#sms'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sms_run_project_alerts'])) {
    $r = $smsService->runProjectAlerts();
    $_SESSION['flash_success'] = 'Project alerts: ' . $r['due'] . ' due soon, ' . $r['followups'] . ' follow-ups today.';
    redirect(url('superadmin/dashboard.php#sms'));
}

$stats = (new DashboardModel($pdo))->adminStats();
$sms = $smsService->dashboardAlerts();
$payroll = new PayrollModel($pdo);
$employeeModel = new EmployeeModel($pdo);
$tutorRows = $payroll->isInstalled() ? $payroll->listTutorSummaries() : [];
$employeeRows = $employeeModel->isInstalled() ? $employeeModel->listWithBalances() : [];

$kes = static fn ($n): string => number_format((float) $n, abs((float) $n - round((float) $n)) < 0.005 ? 0 : 2);
$initials = static function (string $name): string {
    $p = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(substr($p[0] ?? '?', 0, 1) . substr($p[1] ?? '', 0, 1));
};

// ── Salary totals ───────────────────────────────────────────
$tutorOwed = $tutorPaid = $empOwed = $empPaid = 0.0;
foreach ($tutorRows as $t) { $tutorOwed += max(0, $t['balance']); $tutorPaid += $t['paid_to_date']; }
foreach ($employeeRows as $e) { $empOwed += max(0, $e['balance']); $empPaid += $e['paid_to_date']; }
$staffOwed = $tutorOwed + $empOwed;
$staffPaid = $tutorPaid + $empPaid;

// ── Revenue bars + cumulative line ──────────────────────────
$rev = $stats['monthly_revenue'];
$maxRev = max(1, ...array_map('floatval', array_column($rev, 'total') ?: [0]));
$cum = 0; $cumData = [];
foreach ($rev as $r) { $cum += (float) $r['total']; $cumData[] = $cum; }
$revChange = $stats['previous_month_revenue'] > 0
    ? round((($stats['month_revenue'] - $stats['previous_month_revenue']) / $stats['previous_month_revenue']) * 100, 1) : null;

// ── Tab data ────────────────────────────────────────────────
$tabs = ['payments' => [], 'balances' => [], 'salaries' => [], 'sms' => []];

try {
    $rows = $pdo->query(
        "SELECT p.amount, p.status, p.payer_reference, COALESCE(p.reviewed_at, p.paid_at) AS d,
                r.id AS reg_id, r.full_name, r.registration_no, r.course_fee, c.title AS course_title
         FROM payments p LEFT JOIN registrations r ON r.id = p.registration_id LEFT JOIN courses c ON c.id = r.course_id
         ORDER BY d DESC LIMIT 12"
    )->fetchAll();
} catch (Throwable $e) { $rows = []; }
foreach ($rows as $p) {
    $tabs['payments'][] = [
        'name' => $p['full_name'] ?? 'Unknown student', 'sub' => $p['d'] ? date('d M Y', strtotime($p['d'])) : '—',
        'pill' => ucfirst((string) $p['status']), 'amount' => 'KES ' . $kes($p['amount']),
        'detail' => [
            'title' => 'Payment · ' . ($p['registration_no'] ?: 'no reg. no.'), 'chip' => ucfirst((string) $p['status']),
            'company' => $p['course_title'] ?: 'No course', 'person' => $p['full_name'] ?? 'Unknown', 'role' => (string) $p['payer_reference'],
            'tiles' => [['Amount paid', 'KES ' . $kes($p['amount'])], ['Course fee', 'KES ' . $kes($p['course_fee'] ?? 0)], ['Status', ucfirst((string) $p['status'])]],
            'foot' => [['Paid on', $p['d'] ? date('d M Y', strtotime($p['d'])) : '—'], ['Reference', $p['payer_reference'] ?: '—'], ['Amount', 'KES ' . $kes($p['amount'])]],
            'btn' => ['Open payments', url('superadmin/payments/index.php')],
        ],
    ];
}

try {
    $rows = $pdo->query(
        "SELECT r.id, r.full_name, r.registration_no, r.course_fee, r.registration_fee, r.registration_fee_waived, c.title AS course_title,
                (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.registration_id = r.id AND p.status = 'approved') AS paid
         FROM registrations r LEFT JOIN courses c ON c.id = r.course_id WHERE r.status = 'approved'"
    )->fetchAll();
} catch (Throwable $e) { $rows = []; }
$owing = [];
foreach ($rows as $r) {
    $fee = (float) $r['course_fee'] + ($r['registration_fee_waived'] ? 0 : (float) $r['registration_fee']);
    $bal = $fee - (float) $r['paid'];
    if ($bal > 0) $owing[] = $r + ['fee' => $fee, 'bal' => $bal];
}
usort($owing, fn($a, $b) => $b['bal'] <=> $a['bal']);
$owingCount = count($owing);
foreach (array_slice($owing, 0, 12) as $r) {
    $tabs['balances'][] = [
        'name' => $r['full_name'], 'sub' => $r['course_title'] ?: 'No course', 'pill' => 'Owing', 'amount' => 'KES ' . $kes($r['bal']),
        'detail' => [
            'title' => 'Fees · ' . ($r['registration_no'] ?: 'no reg. no.'), 'chip' => 'Balance due', 'company' => $r['course_title'] ?: 'No course',
            'person' => $r['full_name'], 'role' => 'Student',
            'tiles' => [['Total fees', 'KES ' . $kes($r['fee'])], ['Paid', 'KES ' . $kes($r['paid'])], ['Balance', 'KES ' . $kes($r['bal'])]],
            'foot' => [['Total', 'KES ' . $kes($r['fee'])], ['Paid', 'KES ' . $kes($r['paid'])], ['Balance due', 'KES ' . $kes($r['bal'])]],
            'btn' => ['Open student', url('superadmin/students/view.php?id=' . (int) $r['id'])],
        ],
    ];
}

$people = [];
foreach ($tutorRows as $t) {
    $people[] = ['name' => $t['full_name'], 'sub' => $t['student_count'] . ' student' . ($t['student_count'] == 1 ? '' : 's'), 'pill' => 'Tutor',
        'earned' => $t['earned_to_date'], 'paid' => $t['paid_to_date'], 'bal' => $t['balance'], 'link' => url('superadmin/payroll/tutor.php?id=' . (int) $t['id']), 'role' => 'Tutor'];
}
foreach ($employeeRows as $e) {
    $people[] = ['name' => $e['full_name'], 'sub' => $e['job_title'], 'pill' => 'Employee',
        'earned' => $e['earned_to_date'], 'paid' => $e['paid_to_date'], 'bal' => $e['balance'], 'link' => url('superadmin/payroll/employee.php?id=' . (int) $e['id']), 'role' => $e['job_title']];
}
usort($people, fn($a, $b) => $b['bal'] <=> $a['bal']);
foreach (array_slice($people, 0, 12) as $p) {
    $tabs['salaries'][] = [
        'name' => $p['name'], 'sub' => $p['sub'], 'pill' => $p['pill'], 'amount' => 'KES ' . $kes($p['bal']),
        'detail' => [
            'title' => 'Salary · ' . $p['name'], 'chip' => $p['bal'] > 0 ? 'Owed' : 'Paid up', 'company' => $p['pill'],
            'person' => $p['name'], 'role' => $p['role'],
            'tiles' => [['Earnings (total)', 'KES ' . $kes($p['earned'])], ['Paid', 'KES ' . $kes($p['paid'])], ['Balance', 'KES ' . $kes($p['bal'])]],
            'foot' => [['Earned', 'KES ' . $kes($p['earned'])], ['Paid', 'KES ' . $kes($p['paid'])], ['Balance owed', 'KES ' . $kes($p['bal'])]],
            'btn' => ['Pay ' . explode(' ', $p['name'])[0], $p['link']],
        ],
    ];
}

foreach (($sms['alerts'] ?? []) as $a) {
    $tabs['sms'][] = [
        'name' => $a['title'], 'sub' => ucfirst($a['kind']), 'pill' => $a['severity'] === 'danger' ? 'Urgent' : 'Soon', 'amount' => '',
        'detail' => [
            'title' => ucfirst($a['kind']) . ' alert', 'chip' => $a['severity'] === 'danger' ? 'Urgent' : 'Due soon', 'company' => 'SmS module',
            'person' => $a['title'], 'role' => ucfirst($a['kind']),
            'tiles' => [['Detail', $a['detail']]], 'foot' => [['Alert', $a['detail']]],
            'btn' => ['Open', url($a['path'])],
        ],
    ];
}

$tabMeta = [
    'payments' => ['Recent payments', count($tabs['payments']), 'No payments recorded yet.'],
    'balances' => ['Fee balances', $owingCount, 'No student owes fees. Nice.'],
    'salaries' => ['Salaries owed', count($tabs['salaries']), 'No tutors or employees yet.'],
    'sms'      => ['SmS alerts', count($tabs['sms']), 'No SmS alerts right now.'],
];

// ── PIN lock ────────────────────────────────────────────────
// Locked: swap every real figure and name for a placeholder BEFORE anything is rendered, so the page source
// never contains sensitive data. The placeholders are then blurred in the browser.
$unlocked = sa_pin_unlocked();
if (!$unlocked) {
    foreach (['total_outstanding', 'month_revenue', 'previous_month_revenue', 'total_collected', 'expected_fees'] as $k) $stats[$k] = 888888;
    $stats['collection_rate'] = 88.8; $stats['portal_locked'] = 8;
    $owingCount = 8; $revChange = null;
    $tutorOwed = $empOwed = $staffPaid = $staffOwed = 888888;
    $pattern = [45, 70, 55, 90, 65, 100];
    $rev = []; $cumData = [];
    foreach ($pattern as $i => $h) { $rev[] = ['ym' => date('Y-m', strtotime('-' . (5 - $i) . ' month')), 'total' => $h * 1000]; $cumData[] = array_sum(array_slice($pattern, 0, $i + 1)) * 1000; }
    $maxRev = 100000;
    $fakeDetail = ['title' => 'Hidden details', 'chip' => 'Locked', 'company' => 'Hidden', 'person' => 'Hidden name', 'role' => 'Hidden',
        'tiles' => [['Amount', 'KES 000,000'], ['Amount', 'KES 000,000'], ['Amount', 'KES 000,000']],
        'foot' => [['Amount', 'KES 000,000'], ['Amount', 'KES 000,000'], ['Amount', 'KES 000,000']], 'btn' => ['Locked', '#']];
    foreach ($tabs as $key => $items) {
        $tabs[$key] = [];
        for ($i = 0; $i < 5; $i++) {
            $tabs[$key][] = ['name' => 'Hidden name', 'sub' => 'Hidden details', 'pill' => 'Hidden', 'amount' => $key === 'sms' ? '' : 'KES 000,000', 'detail' => $fakeDetail];
        }
        $tabMeta[$key][1] = 5;
    }
}

$page_title = 'Overview';
$sa_custom_header = true;
$sa_smsInstalled = !empty($sms['installed']);
ob_start();
$pwaPortal = 'superadmin'; require __DIR__ . '/../components/pwa/banner.php';
?>
<div class="sa-head">
    <div>
        <h1>Overview</h1>
        <p>Fees, salaries, student balances and SmS alerts in one place.</p>
    </div>
    <div class="sa-head-actions">
        <?php if ($unlocked): ?><button type="button" class="sa-btn-ghost" id="saLockBtn"><i class="fas fa-lock me-1"></i>Lock</button><?php endif; ?>
        <a class="sa-btn-ghost" href="<?php echo url('superadmin/payroll/index.php'); ?>"><i class="fas fa-table me-1"></i>Salaries</a>
        <a class="btn sa-btn-primary" href="<?php echo url('superadmin/payments/index.php'); ?>"><i class="fas fa-plus me-1"></i>Record payment</a>
    </div>
</div>

<nav class="sa-quick" aria-label="Quick actions">
    <a href="<?php echo url('superadmin/students/enroll.php'); ?>"><i class="fas fa-user-plus"></i>Enrol student</a>
    <a href="<?php echo url('superadmin/tutors/create.php'); ?>"><i class="fas fa-chalkboard-user"></i>Add tutor</a>
    <a href="<?php echo url('superadmin/courses/create.php'); ?>"><i class="fas fa-book-medical"></i>Add course</a>
    <a href="<?php echo url('superadmin/payroll/employee_create.php'); ?>"><i class="fas fa-user-tie"></i>Add employee</a>
    <a href="<?php echo url('superadmin/admins/index.php'); ?>"><i class="fas fa-user-shield"></i>Add admin</a>
    <a href="<?php echo url('superadmin/payments/index.php'); ?>"><i class="fas fa-money-bill-wave"></i>Payments</a>
</nav>

<div class="sa-lockable <?php echo $unlocked ? '' : 'is-locked'; ?>">
<section class="sa-kpis sa-blur-target">
    <div class="sa-card sa-hero-card">
        <div class="sa-k-label">Outstanding fees <span class="sa-k-ico text-danger"><i class="fas fa-exclamation"></i></span></div>
        <div class="sa-k-val"><small>KES</small><?php echo $kes($stats['total_outstanding']); ?></div>
        <div class="sa-k-sub"><?php echo (int) $owingCount; ?> student<?php echo $owingCount === 1 ? '' : 's'; ?> owing &middot; <?php echo (int) $stats['portal_locked']; ?> portal<?php echo (int) $stats['portal_locked'] === 1 ? '' : 's'; ?> locked</div>
    </div>
    <div class="sa-card">
        <div class="sa-k-label">Collected this month <span class="sa-k-ico text-primary"><i class="fas fa-calendar"></i></span></div>
        <div class="sa-k-val"><small>KES</small><?php echo $kes($stats['month_revenue']); ?></div>
        <div class="sa-k-sub"><?php if ($revChange !== null): ?><span class="<?php echo $revChange >= 0 ? 'sa-up' : 'sa-down'; ?>"><?php echo $revChange >= 0 ? '▲' : '▼'; ?> <?php echo abs($revChange); ?>%</span> from last month<?php else: ?>Last month: KES <?php echo $kes($stats['previous_month_revenue']); ?><?php endif; ?></div>
        <div class="sa-bars">
            <?php foreach ($rev as $i => $r): $h = max(6, round(((float) $r['total'] / $maxRev) * 100)); ?>
                <div class="<?php echo $i === count($rev) - 1 ? 'hi' : ''; ?>" title="KES <?php echo $kes($r['total']); ?>"><i style="height:<?php echo $h; ?>%"></i><?php echo htmlspecialchars(date('M', strtotime($r['ym'] . '-01'))); ?></div>
            <?php endforeach; ?>
            <?php if (!$rev): ?><div class="sa-k-sub w-100 text-center">No approved payments yet</div><?php endif; ?>
        </div>
    </div>
    <div class="sa-card">
        <div class="sa-k-label">Collection rate <span class="sa-k-ico text-info"><i class="fas fa-percent"></i></span></div>
        <div class="sa-k-val"><?php echo number_format($stats['collection_rate'], 1); ?><small class="ms-1">%</small></div>
        <div class="sa-k-sub">KES <?php echo $kes($stats['total_collected']); ?> of KES <?php echo $kes($stats['expected_fees']); ?> expected</div>
        <canvas id="saCum" height="90"></canvas>
    </div>
    <div class="sa-card">
        <div class="sa-k-label">Owed to tutors &amp; staff <span class="sa-k-ico text-success"><i class="fas fa-wallet"></i></span></div>
        <div class="sa-k-val"><small>KES</small><?php echo $kes($staffOwed); ?></div>
        <div class="sa-pay-tabs">
            <div><b>KES <?php echo $kes($tutorOwed); ?></b>Tutors</div>
            <div class="on"><b>KES <?php echo $kes($empOwed); ?></b>Employees</div>
            <div><b>KES <?php echo $kes($staffPaid); ?></b>Paid</div>
        </div>
    </div>
</section>
<?php if (!$unlocked): ?><div class="sa-gate"><button type="button" class="sa-view-more" data-sa-unlock><i class="fas fa-lock"></i> View more</button></div><?php endif; ?>
</div>

<div class="sa-filters">
    <span class="sa-f-title"><span class="sa-f-count">2</span> Active filters</span>
    <label class="sa-f"><i class="fas fa-magnifying-glass"></i><input type="search" id="saSearch" placeholder="Search name or course"></label>
    <span class="sa-f"><?php echo date('F Y'); ?> <i class="fas fa-calendar"></i></span>
    <span class="sa-f">As of <?php echo date('d M Y, H:i'); ?></span>
</div>

<div class="sa-lockable sa-lockable-panel <?php echo $unlocked ? '' : 'is-locked'; ?>">
<section class="sa-panel sa-blur-target" id="saPanel">
    <div class="sa-panel-top">
        <h3 id="saPanelTitle">Recent payments</h3>
        <div class="sa-seg" role="tablist">
            <?php $first = true; $short = ['payments' => 'Payments', 'balances' => 'Balances', 'salaries' => 'Salaries', 'sms' => 'SmS']; foreach ($tabMeta as $key => $meta): ?>
                <button type="button" data-tab="<?php echo $key; ?>" class="<?php echo $first ? 'on' : ''; ?>"><?php echo $short[$key]; ?> <span class="n"><?php echo (int) $meta[1]; ?></span></button>
            <?php $first = false; endforeach; ?>
        </div>
    </div>
    <div class="sa-split">
        <div>
            <?php foreach ($tabs as $key => $items): ?>
            <div class="sa-list" data-list="<?php echo $key; ?>" <?php echo $key === 'payments' ? '' : 'hidden'; ?>>
                <?php if (!$items): ?><div class="sa-empty"><?php echo htmlspecialchars($tabMeta[$key][2]); ?></div><?php endif; ?>
                <?php foreach ($items as $it): ?>
                    <button type="button" class="sa-row" data-search="<?php echo htmlspecialchars(strtolower($it['name'] . ' ' . $it['sub']), ENT_QUOTES); ?>" data-detail="<?php echo htmlspecialchars(json_encode($it['detail']), ENT_QUOTES); ?>">
                        <span class="sa-av"><?php echo htmlspecialchars($initials($it['name'])); ?></span>
                        <span class="sa-r-main"><b><?php echo htmlspecialchars($it['name']); ?></b><small><?php echo htmlspecialchars($it['sub']); ?></small></span>
                        <span class="sa-pill"><?php echo htmlspecialchars($it['pill']); ?></span>
                        <?php if ($it['amount'] !== ''): ?><span class="sa-amt"><?php echo htmlspecialchars($it['amount']); ?></span><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($sa_smsInstalled): ?>
            <div class="mt-3 d-flex gap-2 flex-wrap" id="saSmsBtns" hidden>
                <form method="post"><button name="sms_run_reminders" value="1" class="btn btn-sm btn-light rounded-pill">Run prospect reminders</button></form>
                <form method="post"><button name="sms_run_project_alerts" value="1" class="btn btn-sm btn-outline-light rounded-pill">Run project alerts</button></form>
            </div>
            <?php elseif (empty($sms['installed'])): ?>
            <div class="mt-3" id="saSmsBtns" hidden><a class="btn btn-sm btn-light rounded-pill" href="<?php echo url('superadmin/sms/migrate.php'); ?>">Set up the SmS module</a></div>
            <?php endif; ?>
        </div>

        <div class="sa-detail on" id="saDetail">
            <div class="sa-d-head">
                <div><small>Details</small><div class="sa-d-big" id="dTitle">—</div></div>
                <div><small>Group</small><div class="sa-d-big" id="dCompany" style="font-size:1.1rem">—</div></div>
                <div><small>Person</small><div class="sa-d-person"><span class="sa-av" id="dAv">?</span><div><b id="dPerson">—</b><small id="dRole"></small></div></div></div>
            </div>
            <div class="sa-tiles" id="dTiles"></div>
            <div class="sa-d-foot">
                <span id="dFoot" class="d-flex flex-wrap gap-4"></span>
                <a class="sa-btn-white" id="dBtn" href="#">Open</a>
            </div>
        </div>
    </div>
</section>
<?php if (!$unlocked): ?><div class="sa-gate"><button type="button" class="sa-view-more" data-sa-unlock><i class="fas fa-lock"></i> View more</button></div><?php endif; ?>
</div>

<div class="sa-pin-bg" id="saPinBg" hidden></div>
<div class="sa-pin" id="saPin" role="dialog" aria-labelledby="saPinTitle" hidden
     data-url="<?php echo url('superadmin/pin.php'); ?>" data-csrf="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>" data-left="<?php echo $unlocked ? sa_pin_seconds_left() : 0; ?>">
    <div class="sa-pin-icon"><i class="fas fa-lock"></i></div>
    <h3 id="saPinTitle">Enter your PIN</h3>
    <p>Enter your PIN to view the figures and details.</p>
    <input type="password" id="saPinInput" inputmode="numeric" pattern="[0-9]*" maxlength="8" autocomplete="off" aria-label="PIN">
    <div class="sa-pin-err" id="saPinErr" role="alert"></div>
    <button type="button" class="btn sa-btn-primary w-100" id="saPinGo">Unlock</button>
    <button type="button" class="btn btn-link w-100 text-muted" id="saPinCancel">Cancel</button>
</div>
<?php
$labels = json_encode(array_map(fn($r) => date('M', strtotime($r['ym'] . '-01')), $rev));
$cumJson = json_encode($cumData);
$extra_js = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function () {
  var cum = $cumJson, labels = $labels, cv = document.getElementById('saCum');
  if (cv && cum.length) {
    new Chart(cv, { type: 'line', data: { labels: labels, datasets: [{ data: cum, borderColor: '#15a24a', backgroundColor: 'rgba(21,162,74,.12)', fill: true, tension: .4, pointRadius: 3, pointBackgroundColor: '#fff', pointBorderColor: '#15a24a', borderWidth: 2 }] },
      options: { responsive: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return 'KES ' + Number(c.parsed.y).toLocaleString(); } } } }, scales: { x: { grid: { display: false }, ticks: { font: { size: 10 } } }, y: { display: false } } } });
  }
  var titles = { payments: 'Recent payments', balances: 'Students owing fees', salaries: 'Salaries owed', sms: 'SmS alerts' };
  var tabs = document.querySelectorAll('.sa-seg button'), lists = document.querySelectorAll('.sa-list'), search = document.getElementById('saSearch');
  var smsBtns = document.getElementById('saSmsBtns'), panelTitle = document.getElementById('saPanelTitle');
  var detail = document.getElementById('saDetail');
  function initials(n) { var p = String(n || '?').trim().split(/\\s+/); return ((p[0] || '?')[0] + ((p[1] || '')[0] || '')).toUpperCase(); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function show(row) {
    document.querySelectorAll('.sa-row.on').forEach(function (r) { r.classList.remove('on'); });
    if (!row) { detail.classList.remove('on'); return; }
    row.classList.add('on'); detail.classList.add('on');
    var d = JSON.parse(row.dataset.detail);
    document.getElementById('dTitle').textContent = d.title;
    document.getElementById('dCompany').textContent = d.company;
    document.getElementById('dPerson').textContent = d.person;
    document.getElementById('dRole').textContent = d.role || '';
    document.getElementById('dAv').textContent = initials(d.person);
    document.getElementById('dTiles').innerHTML = d.tiles.map(function (t) { return '<div class="sa-tile"><small>' + esc(t[0]) + '</small><b>' + esc(t[1]) + '</b></div>'; }).join('')
      + '<a class="sa-tile add" href="' + esc(d.btn[1]) + '"><span><i class="fas fa-arrow-up-right-from-square d-block mb-1"></i>' + esc(d.btn[0]) + '</span></a>';
    document.getElementById('dFoot').innerHTML = d.foot.map(function (f) { return '<span><small>' + esc(f[0]) + '</small><b>' + esc(f[1]) + '</b></span>'; }).join('');
    var b = document.getElementById('dBtn'); b.textContent = d.btn[0]; b.href = d.btn[1];
  }
  function visibleRows() { var l = document.querySelector('.sa-list:not([hidden])'); return l ? Array.prototype.slice.call(l.querySelectorAll('.sa-row')) : []; }
  function activate(tab) {
    tabs.forEach(function (t) { t.classList.toggle('on', t.dataset.tab === tab); });
    lists.forEach(function (l) { l.hidden = l.dataset.list !== tab; });
    panelTitle.textContent = titles[tab];
    if (smsBtns) smsBtns.hidden = tab !== 'sms';
    search.value = ''; filter();
    var rows = visibleRows(); show(rows[0] || null);
    try { history.replaceState(null, '', '#' + tab); } catch (e) {}
  }
  function filter() {
    var q = search.value.toLowerCase().trim();
    visibleRows().forEach(function (r) { r.style.display = q === '' || r.dataset.search.indexOf(q) !== -1 ? '' : 'none'; });
  }
  tabs.forEach(function (t) { t.addEventListener('click', function () { activate(t.dataset.tab); }); });
  document.querySelectorAll('.sa-row').forEach(function (r) { r.addEventListener('click', function () { show(r); }); });
  search.addEventListener('input', filter);
  var h = location.hash.replace('#', ''); activate(titles[h] ? h : 'payments');
});
</script>
JS;
$extra_js .= '<script src="' . sa_asset('superadmin/pin.js') . '"></script>';
$content = ob_get_clean();
require __DIR__ . '/../templates/superadmin/layout.php';
