<?php
// public/components/superadmin/topbar.php — brand, desktop nav pill, mobile bottom bar and the "More" sheet.
$sa_uri = $_SERVER['PHP_SELF'] ?? '';   // the real script path (the address bar may be a clean URL)
// [label, path, uri fragment that marks it active, icon]
$sa_nav = [
    ['Overview',  'superadmin/dashboard.php',           '/superadmin/dashboard.php', 'fa-house'],
    ['Approvals', 'superadmin/registrations/index.php', '/superadmin/registrations/', 'fa-user-clock'],
    ['Students',  'superadmin/students/index.php',      '/superadmin/students/',     'fa-user-graduate'],
    ['Courses',   'superadmin/courses/index.php',       '/superadmin/courses/',      'fa-book-open'],
    ['Tutors',    'superadmin/tutors/index.php',        '/superadmin/tutors/',       'fa-chalkboard-user'],
    ['Payments',  'superadmin/payments/index.php',      '/superadmin/payments/',     'fa-money-bill-wave'],
    ['Salaries',  'superadmin/payroll/index.php',       '/superadmin/payroll/',      'fa-wallet'],
    ['Accounting','superadmin/accounting/index.php',    '/superadmin/accounting/',   'fa-scale-balanced'],
    ['Admins',    'superadmin/admins/index.php',        '/superadmin/admins/',       'fa-user-shield'],
    ['Announce',  'superadmin/announcements/index.php', '/superadmin/announcements/', 'fa-bullhorn'],
    ['Certificates','superadmin/certificates/index.php', '/certificates/',            'fa-certificate'],
    ['ID Cards',  'superadmin/id_cards/index.php',      '/id_cards/',                'fa-id-card'],
    ['Finance',   'superadmin/sms/finance/index.php',   '/sms/finance/',             'fa-chart-line'],
    ['Sales',     'superadmin/sms/sales/index.php',     '/sms/sales/',               'fa-cash-register'],
];
$sa_more = [
    'People' => [
        ['Pending approvals', 'superadmin/registrations/index.php?status=pending', 'fa-user-clock'],
        ['Announcements', 'superadmin/announcements/index.php', 'fa-bullhorn'],
        ['All students', 'superadmin/students/index.php', 'fa-user-graduate'],
        ['All tutors', 'superadmin/tutors/index.php', 'fa-chalkboard-user'],
        ['Enrol a student', 'superadmin/students/enroll.php', 'fa-user-plus'],
        ['Add a tutor', 'superadmin/tutors/create.php', 'fa-chalkboard-user'],
        ['Tutor permissions', 'superadmin/tutors/permissions.php', 'fa-user-lock'],
        ['Employees', 'superadmin/payroll/index.php#employees', 'fa-id-badge'],
        ['Add an employee', 'superadmin/payroll/employee_create.php', 'fa-user-tie'],
        ['Admins', 'superadmin/admins/index.php', 'fa-user-shield'],
    ],
    'Courses' => [
        ['All courses', 'superadmin/courses/index.php', 'fa-book-open'],
        ['Add a course', 'superadmin/courses/create.php', 'fa-plus'],
        ['Categories', 'superadmin/courses/categories.php', 'fa-tags'],
        ['Classes', 'superadmin/courses/classes.php', 'fa-graduation-cap'],
        ['Certificates', 'superadmin/certificates/index.php', 'fa-certificate'],
        ['Student ID cards', 'superadmin/id_cards/index.php', 'fa-id-card'],
        ['Assignments', 'superadmin/assignments/index.php', 'fa-file-lines'],
    ],
    'Money' => [
        ['Payments', 'superadmin/payments/index.php', 'fa-money-bill-wave'],
        ['Salaries', 'superadmin/payroll/index.php', 'fa-wallet'],
        ['Accounting', 'superadmin/accounting/index.php', 'fa-scale-balanced'],
        ['Requests (expenses, tutor shares)', 'superadmin/accounting/requests.php', 'fa-inbox'],
        ['Expense tracker', 'superadmin/accounting/expenses.php', 'fa-receipt'],
        ['Statement', 'superadmin/accounting/statement.php', 'fa-file-invoice-dollar'],
        ['Fee finance', 'superadmin/sms/finance/index.php', 'fa-chart-line'],
        ['Sales', 'superadmin/sms/sales/index.php', 'fa-cash-register'],
    ],
    'SmS' => [
        ['SmS students', 'superadmin/sms/students/index.php', 'fa-users'],
        ['Prospects', 'superadmin/sms/prospects/index.php', 'fa-user-clock'],
        ['Projects', 'superadmin/sms/projects/index.php', 'fa-briefcase'],
    ],
    'System' => [
        ['My profile', 'superadmin/profile/index.php', 'fa-user-circle'],
        ['Settings (PIN)', 'superadmin/settings.php', 'fa-gear'],
        ['Backup & migrate', 'superadmin/backup.php', 'fa-box-archive'],
        ['Pull from live', 'superadmin/migrate.php', 'fa-cloud-arrow-down'],
        ['File check', 'superadmin/file_check.php', 'fa-list-check'],
        ['Install app', '#install', 'fa-download'],
        ['Sign out', 'superadmin/logout.php', 'fa-arrow-right-from-bracket'],
    ],
];
$sa_bottom = [
    ['Home', 'superadmin/dashboard.php', '/superadmin/dashboard.php', 'fa-house'],
    ['Students', 'superadmin/students/index.php', '/superadmin/students/', 'fa-user-graduate'],
    ['Money', 'superadmin/payments/index.php', '/superadmin/payments/', 'fa-wallet'],
    ['Approvals', 'superadmin/registrations/index.php', '/superadmin/registrations/', 'fa-user-clock'],
];
$sa_initial = strtoupper(substr((string)($_SESSION['username'] ?? 'S'), 0, 1));
$sa_isActive = fn(string $frag): bool => strpos(strtok($sa_uri, '?'), $frag) !== false;
?>
<?php
// Desktop sidebar (1025px and up): the same sections as the pill row, grouped like the "More" sheet.
$sa_side = [
    'Main menu' => [
        ['Overview', 'superadmin/dashboard.php', '/superadmin/dashboard.php', 'fa-house'],
        ['Approvals', 'superadmin/registrations/index.php', '/superadmin/registrations/', 'fa-user-clock'],
        ['Students', 'superadmin/students/index.php', '/superadmin/students/', 'fa-user-graduate'],
        ['Courses', 'superadmin/courses/index.php', '/superadmin/courses/', 'fa-book-open'],
        ['Tutors', 'superadmin/tutors/index.php', '/superadmin/tutors/', 'fa-chalkboard-user'],
        ['Admins', 'superadmin/admins/index.php', '/superadmin/admins/', 'fa-user-shield'],
        ['Announcements', 'superadmin/announcements/index.php', '/superadmin/announcements/', 'fa-bullhorn'],
        ['Certificates', 'superadmin/certificates/index.php', '/certificates/', 'fa-certificate'],
        ['ID Cards', 'superadmin/id_cards/index.php', '/id_cards/', 'fa-id-card'],
        ['Assignments', 'superadmin/assignments/index.php', '/superadmin/assignments/', 'fa-file-lines'],
    ],
    'Money' => [
        ['Payments', 'superadmin/payments/index.php', '/superadmin/payments/', 'fa-money-bill-wave'],
        ['Salaries', 'superadmin/payroll/index.php', '/superadmin/payroll/', 'fa-wallet'],
        ['Accounting', 'superadmin/accounting/index.php', '/superadmin/accounting/', 'fa-scale-balanced'],
        ['Finance', 'superadmin/sms/finance/index.php', '/sms/finance/', 'fa-chart-line'],
        ['Sales', 'superadmin/sms/sales/index.php', '/sms/sales/', 'fa-cash-register'],
    ],
    'SmS' => [
        ['SmS students', 'superadmin/sms/students/index.php', '/sms/students/', 'fa-users'],
        ['Prospects', 'superadmin/sms/prospects/index.php', '/sms/prospects/', 'fa-user-clock'],
        ['Projects', 'superadmin/sms/projects/index.php', '/sms/projects/', 'fa-briefcase'],
    ],
    'Help & system' => [
        ['My profile', 'superadmin/profile/index.php', '/superadmin/profile/', 'fa-user-circle'],
        ['Settings', 'superadmin/settings.php', '/superadmin/settings.php', 'fa-gear'],
        ['Backup & migrate', 'superadmin/backup.php', '/superadmin/backup.php', 'fa-box-archive'],
        ['Pull from live', 'superadmin/migrate.php', '/superadmin/migrate.php', 'fa-cloud-arrow-down'],
        ['File check', 'superadmin/file_check.php', '/superadmin/file_check.php', 'fa-list-check'],
        ['Log out', 'superadmin/logout.php', '/superadmin/logout.php', 'fa-arrow-right-from-bracket'],
    ],
];
$sa_name = (string) ($_SESSION['username'] ?? 'Superadmin');
?>
<style>/* desktop panel (1025px and up): white sidebar, title bar, light cards. Kept here so it ships with the menu markup. */
.sa-side,.sa-dtools,.sa-ptitle{display:none}
@media (min-width:1025px){
  :root{--sa-side:240px;--sa-g:#16a34a;--sa-gl:#e8f6ee;--sa-bd:#e8ecef}
  body.sa-body{padding:0;background:#f6f8f7}
  .sa-side{display:flex;flex-direction:column;position:fixed;inset:0 auto 0 0;width:var(--sa-side);background:#fff;border-right:1px solid var(--sa-bd);z-index:50;padding:20px 14px 16px}
  .sa-side-brand{display:flex;align-items:center;gap:10px;padding:2px 8px 22px;text-decoration:none;color:#111827;font-weight:700;line-height:1.1}
  .sa-side-brand .sa-sq{width:40px;height:40px;border-radius:10px;background:var(--sa-g);display:inline-flex;align-items:center;justify-content:center;overflow:hidden;flex:none}
  .sa-side-brand .sa-sq img{width:30px;height:30px;object-fit:contain;filter:brightness(0) invert(1)}
  .sa-side-brand small{display:block;font-weight:500;font-size:.7rem;color:#6b7280;margin-top:3px}
  .sa-side-nav{flex:1;min-height:0;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#e5e7eb transparent;display:flex;flex-direction:column;gap:2px}
  .sa-side-sep{font-size:.66rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;padding:14px 12px 6px}
  .sa-side-nav a{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;color:#374151;text-decoration:none;font-size:.86rem;font-weight:500;transition:background .15s,color .15s}
  .sa-side-nav a i{width:18px;text-align:center;color:#6b7280;font-size:.9rem}
  .sa-side-nav a:hover{background:#f3f4f6}
  .sa-side-nav a.active{background:var(--sa-gl);color:#111827;font-weight:600}
  .sa-side-nav a.active i{color:var(--sa-g)}
  .sa-side-rev{border:1px solid var(--sa-bd);border-radius:12px;padding:14px;margin:14px 0 12px}
  .sa-side-rev small{display:block;font-size:.7rem;color:#6b7280}
  .sa-side-rev b{display:block;font-size:1.25rem;color:#111827;margin:4px 0 2px}
  .sa-side-rev span{font-size:.72rem;color:var(--sa-g);font-weight:600}
  .sa-side-me{display:flex;align-items:center;gap:10px;border:1px solid var(--sa-bd);border-radius:12px;padding:10px 12px;text-decoration:none;color:#111827}
  .sa-side-me .sa-avatar{width:34px;height:34px;font-size:.8rem;flex:none}
  .sa-side-me b{display:block;font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px}
  .sa-side-me small{display:block;font-size:.68rem;color:#6b7280}
  .sa-side-me>i{margin-left:auto;color:#9ca3af;font-size:.75rem}
  .sa-frame{margin:0 0 0 var(--sa-side);max-width:none;border-radius:0;box-shadow:none;background:#f6f8f7;padding:0 28px 40px;min-height:100vh}
  .sa-top{position:sticky;top:0;z-index:40;background:#fff;margin:0 -28px 24px;padding:14px 28px;border-bottom:1px solid var(--sa-bd);flex-wrap:nowrap;max-width:none;width:calc(100% + 56px);min-height:68px}
  .sa-top .sa-brand,.sa-top .sa-nav,.sa-top .sa-tools{display:none!important}
  .sa-ptitle{display:block;font-size:1.2rem;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .sa-dtools{display:flex;align-items:center;gap:18px;margin-left:auto}
  .sa-date{border:1px solid var(--sa-bd);border-radius:8px;padding:8px 14px;font-size:.8rem;color:#374151;display:flex;gap:14px;align-items:center;white-space:nowrap}
  .sa-date i{color:#9ca3af}
  .sa-bell{position:relative;color:#374151;font-size:1.05rem;text-decoration:none}
  .sa-bell::after{content:"";position:absolute;top:-2px;right:-3px;width:7px;height:7px;border-radius:50%;background:#ef4444}
  .sa-dtools .sa-avatar{width:38px;height:38px}
  /* page heading: the title now sits in the top bar */
  .sa-head>.sa-back,.sa-head h1{display:none}
  .sa-head{margin-bottom:18px}
  /* cards and panels: white, thin border, small radius, no heavy shadows */
  .sa-card,.sa-quick a,.sa-content .card{border:1px solid var(--sa-bd)!important;border-radius:12px!important;box-shadow:none!important;background:#fff}
  .sa-card{min-height:0;padding:18px 20px}
  .sa-card.sa-hero-card{background:#fff}.sa-card.sa-hero-card::after{display:none}
  .sa-card .sa-k-label{font-size:.8rem;font-weight:500;color:#4b5563;flex-direction:row-reverse;justify-content:flex-end;gap:12px}
  .sa-card .sa-k-ico{width:40px;height:40px;border-radius:10px;border:0;background:var(--sa-gl);font-size:.95rem}
  .sa-card .sa-k-val{font-size:1.45rem;font-weight:700;margin-top:6px;padding-left:52px}
  .sa-card .sa-k-sub{padding-left:52px}
  .sa-up{color:var(--sa-g)!important;font-weight:600}
  .sa-kpis{gap:16px}
  .sa-quick a{font-weight:600;color:#111827}
  .sa-quick a i{border-radius:10px;background:var(--sa-gl);color:var(--sa-g)}
  .sa-panel{background:#fff;border:1px solid var(--sa-bd);border-radius:12px;color:#111827}
  .sa-panel .sa-row{background:#fff;color:#111827;border:1px solid var(--sa-bd);border-radius:10px}
  .sa-panel .sa-row:hover,.sa-panel .sa-row.on{background:var(--sa-gl);border-color:#cfeedd}
  .sa-panel .sa-row small{color:#6b7280}
  .sa-panel .sa-pill{background:var(--sa-gl);color:var(--sa-g)}
  .sa-panel .sa-av{background:#f3f4f6;color:#374151}
  .sa-panel .sa-seg,.sa-panel .sa-seg *{color:#374151}
  .sa-detail{background:#fff;border:1px solid var(--sa-bd);border-radius:12px;color:#111827}
  .sa-detail .sa-d-head small,.sa-detail .sa-tile small,.sa-detail .sa-d-foot small{color:#6b7280}
  .sa-detail .sa-tile{background:#f9fafb;border:1px solid var(--sa-bd);border-radius:10px}
  .sa-detail .sa-tile.add{border:1px dashed #cbd5e1;color:var(--sa-g);background:#fff}
  .sa-detail .sa-d-person .sa-av{background:var(--sa-gl);color:var(--sa-g)}
  .sa-detail .sa-d-foot{background:#f9fafb;border-radius:10px}
  .sa-btn-primary,.btn.sa-btn-primary{border-radius:8px;box-shadow:none;background:var(--sa-g)}
  .sa-btn-ghost{border-radius:8px}
  .sa-filters .sa-f{border-radius:8px}
  .sa-filters .sa-f-count{background:var(--sa-g)}
}
</style>
<aside class="sa-side" aria-label="Superadmin menu">
    <a class="sa-side-brand" href="<?php echo url('superadmin/dashboard.php'); ?>">
        <span class="sa-sq"><img src="<?php echo asset('superadmin/icon.svg'); ?>" alt=""></span>
        <span>DazuHub<small>Superadmin</small></span>
    </a>
    <nav class="sa-side-nav">
        <?php foreach ($sa_side as $group => $links): ?>
            <div class="sa-side-sep"><?php echo $group; ?></div>
            <?php foreach ($links as [$label, $path, $frag, $icon]): ?>
                <a href="<?php echo url($path); ?>" class="<?php echo $sa_isActive($frag) ? 'active' : ''; ?>"<?php echo $sa_isActive($frag) ? ' aria-current="page"' : ''; ?>><i class="fas <?php echo $icon; ?>"></i><span><?php echo $label; ?></span></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <?php
    $sa_photo = null; $sa_month = null;
    try {
        require_once __DIR__ . '/../../../app/v1/models/Connection.php';
        require_once __DIR__ . '/../../../app/v1/helpers/user_photo.php';
        $sa_db = \Models\Connection::getInstance()->getConnection();
        $sa_photo = user_photo_url($sa_db, (int) ($_SESSION['user_id'] ?? 0));
        $sa_month = (float) $sa_db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'approved' AND COALESCE(reviewed_at, paid_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
    } catch (Throwable $e) {
    }
    $sa_av = $sa_photo ? '<img src="' . htmlspecialchars($sa_photo) . '" alt="" style="width:100%;height:100%;object-fit:cover">' : htmlspecialchars($sa_initial);
    ?>
    <?php if ($sa_month !== null): ?>
    <div class="sa-side-rev"><small>Fees collected</small><small>This month</small><b>KES <?php echo number_format($sa_month); ?></b><span><i class="fas fa-caret-up"></i> <?php echo date('F Y'); ?></span></div>
    <?php endif; ?>
    <a class="sa-side-me" href="<?php echo url('superadmin/profile/index.php'); ?>" title="My profile">
        <span class="sa-avatar" style="overflow:hidden"><?php echo $sa_av; ?></span>
        <span><b><?php echo htmlspecialchars(strtok($sa_name, '@') ?: $sa_name); ?></b><small>Superadmin</small></span>
        <i class="fas fa-chevron-right"></i>
    </a>
</aside>

<header class="sa-top">
    <div class="sa-ptitle"><?php echo htmlspecialchars(strip_tags((string) ($page_title ?? 'Overview'))); ?></div>
    <div class="sa-dtools">
        <span class="sa-date"><?php echo date('M 1') . ' – ' . date('M j, Y'); ?> <i class="far fa-calendar"></i></span>
        <a class="sa-bell" href="<?php echo url('superadmin/registrations/index.php?status=pending'); ?>" title="Pending approvals"><i class="far fa-bell"></i></a>
        <a class="sa-avatar" style="overflow:hidden;text-decoration:none" href="<?php echo url('superadmin/profile/index.php'); ?>" title="My profile"><?php echo $sa_av; ?></a>
    </div>
    <a class="sa-brand" href="<?php echo url('superadmin/dashboard.php'); ?>">
        <img class="sa-brand-mark" src="<?php echo asset('superadmin/icon.svg'); ?>" alt="" width="38" height="38">
        <span>Dazu<span class="sa-accent">Hub</span><small>Superadmin</small></span>
    </a>
    <nav class="sa-nav" aria-label="Superadmin sections">
        <?php foreach ($sa_nav as [$label, $path, $frag]): ?>
            <a href="<?php echo url($path); ?>" class="<?php echo $sa_isActive($frag) ? 'active' : ''; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sa-tools">
        <button type="button" class="sa-install" id="saInstall" title="Install the app on this device"><i class="fas fa-download"></i><span>Install app</span></button>
        <a class="sa-icon d-none d-md-inline-flex" href="<?php echo url('superadmin/settings.php'); ?>" title="Settings"><i class="fas fa-gear"></i></a>
        <a class="sa-icon d-none d-md-inline-flex" href="<?php echo url('superadmin/logout.php'); ?>" title="Sign out"><i class="fas fa-arrow-right-from-bracket"></i></a>
        <span class="sa-avatar" style="overflow:hidden" title="<?php echo htmlspecialchars((string)($_SESSION['email'] ?? '')); ?>"><?php if ($sa_photo): ?><img src="<?php echo htmlspecialchars($sa_photo); ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else: echo htmlspecialchars($sa_initial); endif; ?></span>
    </div>
</header>

<nav class="sa-bottom" aria-label="Main">
    <?php foreach ($sa_bottom as [$label, $path, $frag, $icon]): ?>
        <a href="<?php echo url($path); ?>" class="<?php echo $sa_isActive($frag) ? 'on' : ''; ?>"><i class="fas <?php echo $icon; ?>"></i><span><?php echo $label; ?></span></a>
    <?php endforeach; ?>
    <button type="button" id="saMoreBtn" aria-controls="saSheet" aria-expanded="false"><i class="fas fa-grip"></i><span>More</span></button>
</nav>

<div class="sa-sheet-bg" id="saSheetBg" hidden></div>
<div class="sa-sheet" id="saSheet" role="dialog" aria-label="All sections" hidden>
    <div class="sa-sheet-grab"></div>
    <?php foreach ($sa_more as $group => $links): ?>
        <div class="sa-sheet-group"><h4><?php echo $group; ?></h4>
            <div class="sa-sheet-grid">
            <?php foreach ($links as [$label, $path, $icon]): ?>
                <a href="<?php echo $path === '#install' ? '#install' : url($path); ?>" <?php echo $path === '#install' ? 'data-sa-install' : ''; ?>><i class="fas <?php echo $icon; ?>"></i><span><?php echo $label; ?></span></a>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="sa-help-bg" id="saHelpBg" hidden></div>
<div class="sa-help" id="saHelp" role="dialog" aria-labelledby="saHelpTitle" hidden>
    <h3 id="saHelpTitle"><i class="fas fa-download me-2"></i>Install DazuHub Admin</h3>
    <p id="saHelpBody"></p>
    <button type="button" class="btn sa-btn-primary w-100" id="saHelpClose">Got it</button>
</div>
