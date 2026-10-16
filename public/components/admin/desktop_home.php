<?php
// Admin dashboard for tablets and desktops (769px and up), Dazu green #44A024 + charcoal #373435.
// Expects $pdo and $stats (DashboardModel::adminStats()). Charts: Chart.js (loaded by the admin layout).
$dh = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$num = fn($n) => number_format((float) $n);
$trend = function ($now, $before) {
    if (!$before) return $now ? ['up', 'new'] : ['flat', '0%'];
    $p = round(($now - $before) / $before * 100, 1);
    return [$p >= 0 ? 'up' : 'down', ($p >= 0 ? '+' : '') . $p . '%'];
};
$q = function (string $sql) use ($pdo) { try { return $pdo->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; } };
$regThis = (int) $q("SELECT COUNT(*) FROM registrations WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND registration_no NOT LIKE 'PREVIEW-%'");
$regLast = (int) $q("SELECT COUNT(*) FROM registrations WHERE created_at >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') AND registration_no NOT LIKE 'PREVIEW-%'");
$pendPay = (int) $q("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
$rs = $stats['registrations_by_status'] + ['approved' => 0, 'pending' => 0, 'rejected' => 0];
$statusTotal = max(1, array_sum($rs));
$byCourse = array_slice($stats['students_by_course'], 0, 5);
$maxCourse = max(1, ...array_map(fn($c) => (int) $c['cnt'], $byCourse ?: [['cnt' => 1]]));
$recent = [];
try {
    $recent = $pdo->query("SELECT r.id, r.full_name, r.status, r.created_at, r.study_mode, r.course_fee, c.title AS course_title,
                                  (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.registration_id = r.id AND p.status = 'approved') AS paid
                           FROM registrations r LEFT JOIN courses c ON c.id = r.course_id
                           WHERE r.registration_no IS NULL OR r.registration_no NOT LIKE 'PREVIEW-%' ORDER BY r.created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}
[$revDir, $revPct] = $trend((float) $stats['month_revenue'], (float) $stats['previous_month_revenue']);
[$regDir, $regPct] = $trend($regThis, $regLast);
$first = explode(' ', (string) ($_SESSION['username'] ?? 'Admin'))[0];
try {
    $st = $pdo->prepare('SELECT first_name FROM user_profiles WHERE user_id = ?');
    $st->execute([(int) ($_SESSION['user_id'] ?? 0)]);
    $first = trim((string) $st->fetchColumn()) ?: $first;
} catch (Throwable $e) {
}
$hour = (int) date('G');
$hello = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$tones = ['#44A024', '#373435', '#2b78d0', '#e08a00', '#8b5cf6'];
$statusPill = ['approved' => ['Approved', 'g'], 'pending' => ['Pending', 'o'], 'rejected' => ['Rejected', 'r']];
?>
<style>
.dh{color:#1f2421}
.dh-top{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:20px}
.dh-top h2{font-size:1.6rem;font-weight:700;margin:0;color:#1f2421}.dh-top p{color:#6b7280;margin:2px 0 0;font-size:.9rem}
.dh-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:12px;font-weight:600;font-size:.88rem;text-decoration:none;border:1px solid #e5e7eb;background:#fff;color:#1f2421}
.dh-btn.pri{background:#44A024;border-color:#44A024;color:#fff;box-shadow:0 6px 16px rgba(68,160,36,.28)}
.dh-btn:hover{filter:brightness(.97)}
.dh-grid4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:16px}
.dh-card{background:#fff;border-radius:18px;border:1px solid #eef0ec;box-shadow:0 2px 10px rgba(20,35,25,.04);padding:18px 20px}
.dh-kpi{display:flex;gap:14px;align-items:flex-start}
.dh-kpi .ic{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;color:#fff;flex:none}
.dh-kpi .k{font-size:.82rem;color:#4b5563;font-weight:600}
.dh-kpi .v{font-size:1.65rem;font-weight:800;line-height:1.15;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dh-chip{font-size:.7rem;font-weight:700;border-radius:999px;padding:2px 8px}
.dh-chip.up{background:#e7f4e1;color:#2f7a14}.dh-chip.down{background:#fde8e6;color:#c0362c}.dh-chip.flat{background:#eef0ee;color:#4b5563}
.dh-kpi .s{font-size:.76rem;color:#6b7280;margin-top:8px;display:flex;gap:12px;flex-wrap:wrap}
.dh-kpi .s i{font-size:.45rem;vertical-align:2px;margin-right:4px}
.dh-row{display:grid;grid-template-columns:1.25fr 1fr 1fr;gap:16px;margin-bottom:16px}
.dh-h{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.dh-h h3{font-size:1rem;font-weight:700;margin:0}
.dh-h a,.dh-h .tag{font-size:.78rem;color:#44A024;font-weight:600;text-decoration:none;background:#f0f8eb;border-radius:8px;padding:4px 10px}
.dh-legend li{display:flex;align-items:center;gap:8px;font-size:.8rem;margin-bottom:10px}
.dh-legend b{display:block;font-size:.8rem}.dh-legend small{color:#6b7280}
.dh-legend i{width:10px;height:10px;border-radius:50%;flex:none}
.dh-bar{height:7px;border-radius:99px;background:#eceee9;overflow:hidden;margin-top:6px}.dh-bar>div{height:100%;border-radius:99px}
.dh-course{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.dh-course .av{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem;flex:none}
.dh-table th{font-size:.74rem;color:#6b7280;font-weight:600;border-bottom:1px solid #eef0ec!important;padding:10px 12px!important;background:#fafbf9!important}
.dh-table td{font-size:.86rem;padding:12px!important;vertical-align:middle;border-bottom:1px solid #f2f3f0!important}
.dh-ava{width:34px;height:34px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;font-weight:700;margin-right:10px}
.dh-pill{font-size:.72rem;font-weight:600;border-radius:8px;padding:4px 10px}
.dh-pill.g{background:#e7f4e1;color:#2f7a14}.dh-pill.o{background:#fff1dd;color:#b45309}.dh-pill.r{background:#fde8e6;color:#c0362c}.dh-pill.b{background:#e6efff;color:#2b5fb5}
.dh-prog{display:flex;align-items:center;gap:8px;min-width:120px}.dh-prog .dh-bar{flex:1;margin:0}
.dh-act{width:30px;height:30px;border-radius:9px;border:1px solid #eef0ec;display:inline-flex;align-items:center;justify-content:center;color:#4b5563;text-decoration:none}
.dh-act:hover{background:#f0f8eb;color:#44A024}
@media(max-width:1199.98px){.dh-grid4{grid-template-columns:repeat(2,minmax(0,1fr))}.dh-row{grid-template-columns:1fr 1fr}.dh-row>:first-child{grid-column:1/-1}}
</style>
<div class="dh">
    <div class="dh-top">
        <div><h2><?php echo $dh($hello . ', ' . $first); ?>! 👋</h2><p>Here is what is happening at Dazu Training Institute today.</p></div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="dh-btn" href="<?php echo url('admin/students/index.php'); ?>"><i class="fas fa-download"></i>Export report</a>
            <a class="dh-btn pri" href="<?php echo url('admin/students/enroll.php'); ?>"><i class="fas fa-plus"></i>Enrol student</a>
        </div>
    </div>

    <div class="dh-grid4">
        <a class="dh-card dh-kpi text-decoration-none text-reset" href="<?php echo url('admin/students/index.php'); ?>">
            <span class="ic" style="background:#44A024"><i class="fas fa-user-graduate"></i></span>
            <div><div class="k">Active Students</div><div class="v"><?php echo $num($stats['students']); ?> <span class="dh-chip <?php echo $regDir; ?>"><?php echo $dh($regPct); ?></span></div>
                <div class="s"><span><i class="fas fa-circle" style="color:#44A024"></i><?php echo $regThis; ?> joined this month</span><span><i class="fas fa-circle" style="color:#9ca3af"></i><?php echo (int) $stats['portal_locked']; ?> locked</span></div></div></a>
        <a class="dh-card dh-kpi text-decoration-none text-reset" href="<?php echo url('admin/courses/index.php'); ?>">
            <span class="ic" style="background:#373435"><i class="fas fa-book-open"></i></span>
            <div><div class="k">Courses &amp; Tutors</div><div class="v"><?php echo $num($stats['courses']); ?> <span class="dh-chip flat"><?php echo (int) $stats['tutors']; ?> tutors</span></div>
                <div class="s"><span><i class="fas fa-circle" style="color:#373435"></i><?php echo (int) $stats['courses']; ?> active courses</span><span><i class="fas fa-circle" style="color:#44A024"></i><?php echo (int) $stats['tutors']; ?> active tutors</span></div></div></a>
        <a class="dh-card dh-kpi text-decoration-none text-reset" href="<?php echo url('admin/students/index.php'); ?>">
            <span class="ic" style="background:#2f7a14"><i class="fas fa-coins"></i></span>
            <div><div class="k">Fees This Month</div><div class="v">KSh <?php echo $num($stats['month_revenue']); ?> <span class="dh-chip <?php echo $revDir; ?>"><?php echo $dh($revPct); ?></span></div>
                <div class="s"><span>vs last month KSh <?php echo $num($stats['previous_month_revenue']); ?></span></div></div></a>
        <a class="dh-card dh-kpi text-decoration-none text-reset" href="<?php echo url('admin/registrations/index.php?status=pending'); ?>">
            <span class="ic" style="background:#e08a00"><i class="fas fa-user-clock"></i></span>
            <div><div class="k">Waiting for You</div><div class="v"><?php echo $num($stats['pending_regs']); ?> <span class="dh-chip <?php echo $stats['pending_regs'] ? 'down' : 'up'; ?>"><?php echo $stats['pending_regs'] ? 'to review' : 'clear'; ?></span></div>
                <div class="s"><span><i class="fas fa-circle" style="color:#e08a00"></i><?php echo (int) $stats['pending_regs']; ?> applications</span><span><i class="fas fa-circle" style="color:#2b78d0"></i><?php echo $pendPay; ?> payments · <?php echo (int) $stats['new_enquiries']; ?> enquiries</span></div></div></a>
    </div>

    <div class="dh-row">
        <div class="dh-card">
            <div class="dh-h"><h3>Admissions Overview</h3><span class="tag">Last 6 months</span></div>
            <div style="position:relative;height:230px"><canvas id="dhAdmissions"></canvas></div>
        </div>
        <div class="dh-card">
            <div class="dh-h"><h3>Students by Status</h3><a href="<?php echo url('admin/registrations/index.php'); ?>">All</a></div>
            <div class="d-flex align-items-center gap-3">
                <div style="position:relative;width:170px;height:170px;flex:none"><canvas id="dhStatus"></canvas>
                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none"><b style="font-size:1.6rem"><?php echo array_sum($rs); ?></b><small class="text-muted">Applications</small></div></div>
                <ul class="dh-legend list-unstyled mb-0 flex-grow-1">
                    <?php foreach ([['approved', 'Active', '#44A024'], ['pending', 'Pending', '#e08a00'], ['rejected', 'Rejected', '#c0362c']] as [$k, $l, $c]): ?>
                        <li><i style="background:<?php echo $c; ?>"></i><div><b><?php echo $l; ?></b><small><?php echo (int) $rs[$k]; ?> (<?php echo round($rs[$k] / $statusTotal * 100, 1); ?>%)</small></div></li>
                    <?php endforeach; ?>
                    <li><i style="background:#9ca3af"></i><div><b>Portal locked</b><small><?php echo (int) $stats['portal_locked']; ?> students</small></div></li>
                </ul>
            </div>
        </div>
        <div class="dh-card">
            <div class="dh-h"><h3>Students per Course</h3><a href="<?php echo url('admin/courses/index.php'); ?>">Courses</a></div>
            <?php if (!$byCourse): ?><div class="text-muted small">No courses yet.</div><?php endif; ?>
            <?php foreach ($byCourse as $i => $c): $col = $tones[$i % 5]; ?>
                <div class="dh-course"><span class="av" style="background:<?php echo $col; ?>"><?php echo $dh(strtoupper(mb_substr((string) $c['title'], 0, 2))); ?></span>
                    <div class="flex-grow-1" style="min-width:0"><div class="d-flex justify-content-between small"><b class="text-truncate"><?php echo $dh(ucwords((string) $c['title'])); ?></b><span class="fw-semibold"><?php echo (int) $c['cnt']; ?></span></div>
                        <div class="dh-bar"><div style="width:<?php echo round($c['cnt'] / $maxCourse * 100); ?>%;background:<?php echo $col; ?>"></div></div></div></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="dh-card">
        <div class="dh-h"><h3>Recent Admissions</h3>
            <div class="d-flex gap-2"><input type="search" id="dhSearch" class="form-control form-control-sm" placeholder="Search students…" style="width:220px">
                <select id="dhFilter" class="form-select form-select-sm" style="width:auto"><option value="">All status</option><option value="approved">Approved</option><option value="pending">Pending</option><option value="rejected">Rejected</option></select></div></div>
        <div class="table-responsive"><table class="table dh-table mb-0">
            <thead><tr><th>Student</th><th>Course</th><th>Applied</th><th>Fees paid</th><th>Mode</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php if (!$recent): ?><tr><td colspan="7" class="text-center text-muted py-4">No admissions yet.</td></tr><?php endif; ?>
            <?php foreach ($recent as $i => $r): $pct = (float) $r['course_fee'] > 0 ? min(100, (int) round($r['paid'] / $r['course_fee'] * 100)) : 0; [$sl, $sc] = $statusPill[$r['status']] ?? [ucfirst($r['status']), 'b']; ?>
                <tr data-name="<?php echo $dh(strtolower($r['full_name'] . ' ' . $r['course_title'])); ?>" data-status="<?php echo $dh($r['status']); ?>">
                    <td><span class="dh-ava" style="background:<?php echo $tones[$i % 5]; ?>"><?php echo $dh(strtoupper(mb_substr((string) $r['full_name'], 0, 1))); ?></span><?php echo $dh($r['full_name']); ?></td>
                    <td><?php echo $dh($r['course_title'] ?? '—'); ?></td>
                    <td><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
                    <td><div class="dh-prog"><span class="small fw-semibold"><?php echo $pct; ?>%</span><div class="dh-bar"><div style="width:<?php echo $pct; ?>%;background:<?php echo $pct >= 100 ? '#44A024' : ($pct >= 50 ? '#2b78d0' : '#e08a00'); ?>"></div></div></div></td>
                    <td><span class="dh-pill b"><?php echo $r['study_mode'] === 'physical' ? 'Physical' : 'Online'; ?></span></td>
                    <td><span class="dh-pill <?php echo $sc; ?>"><?php echo $dh($sl); ?></span></td>
                    <td class="text-end"><a class="dh-act" href="<?php echo url('admin/registrations/view.php?id=' . (int) $r['id']); ?>" title="Open"><i class="far fa-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="d-flex justify-content-between align-items-center mt-3 small text-muted"><span>Showing the latest <?php echo count($recent); ?> admissions</span>
            <a class="dh-btn" style="padding:6px 12px" href="<?php echo url('admin/registrations/index.php'); ?>">View all →</a></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.Chart) return;
    var labels = <?php echo json_encode(array_column($stats['monthly_registrations'], 'label')); ?>, data = <?php echo json_encode(array_map('intval', array_column($stats['monthly_registrations'], 'total'))); ?>;
    var ctx = document.getElementById('dhAdmissions').getContext('2d'), grad = ctx.createLinearGradient(0, 0, 0, 230);
    grad.addColorStop(0, 'rgba(68,160,36,.35)'); grad.addColorStop(1, 'rgba(68,160,36,0)');
    new Chart(ctx, { type: 'line', data: { labels: labels, datasets: [{ label: 'Applications', data: data, fill: true, backgroundColor: grad, borderColor: '#44A024', borderWidth: 2.5, tension: .4, pointBackgroundColor: '#fff', pointBorderColor: '#44A024', pointRadius: 4, pointHoverRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f0f1ee' } }, x: { grid: { display: false } } } } });
    new Chart(document.getElementById('dhStatus'), { type: 'doughnut', data: { labels: ['Active', 'Pending', 'Rejected'],
        datasets: [{ data: <?php echo json_encode([(int) $rs['approved'], (int) $rs['pending'], (int) $rs['rejected']]); ?>, backgroundColor: ['#44A024', '#e08a00', '#c0362c'], borderWidth: 0 }] },
        options: { cutout: '68%', plugins: { legend: { display: false } } } });
    var s = document.getElementById('dhSearch'), f = document.getElementById('dhFilter');
    function filt() { var q = s.value.trim().toLowerCase(); document.querySelectorAll('.dh-table tbody tr[data-name]').forEach(function (tr) {
        tr.style.display = (!q || tr.dataset.name.indexOf(q) > -1) && (!f.value || tr.dataset.status === f.value) ? '' : 'none'; }); }
    s.addEventListener('input', filt); f.addEventListener('change', filt);
});
</script>
