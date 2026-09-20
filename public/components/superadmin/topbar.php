<?php
// public/components/superadmin/topbar.php — brand, desktop nav pill, mobile bottom bar and the "More" sheet.
$sa_uri = $_SERVER['REQUEST_URI'] ?? '';
// [label, path, uri fragment that marks it active, icon]
$sa_nav = [
    ['Overview',  'superadmin/dashboard.php',           '/superadmin/dashboard.php', 'fa-house'],
    ['Students',  'superadmin/students/index.php',      '/superadmin/students/',     'fa-user-graduate'],
    ['Courses',   'superadmin/courses/index.php',       '/superadmin/courses/',      'fa-book-open'],
    ['Tutors',    'superadmin/tutors/index.php',        '/superadmin/tutors/',       'fa-chalkboard-user'],
    ['Payments',  'superadmin/payments/index.php',      '/superadmin/payments/',     'fa-money-bill-wave'],
    ['Salaries',  'superadmin/payroll/index.php',       '/superadmin/payroll/',      'fa-wallet'],
    ['Admins',    'superadmin/admins/index.php',        '/superadmin/admins/',       'fa-user-shield'],
    ['Finance',   'superadmin/sms/finance/index.php',   '/sms/finance/',             'fa-chart-line'],
    ['Sales',     'superadmin/sms/sales/index.php',     '/sms/sales/',               'fa-cash-register'],
];
$sa_more = [
    'People' => [
        ['Enrol a student', 'superadmin/students/enroll.php', 'fa-user-plus'],
        ['Add a tutor', 'superadmin/tutors/create.php', 'fa-chalkboard-user'],
        ['Employees', 'superadmin/payroll/index.php#employees', 'fa-id-badge'],
        ['Add an employee', 'superadmin/payroll/employee_create.php', 'fa-user-tie'],
        ['Add an admin', 'superadmin/admins/index.php', 'fa-user-shield'],
    ],
    'Courses' => [
        ['All courses', 'superadmin/courses/index.php', 'fa-book-open'],
        ['Add a course', 'superadmin/courses/create.php', 'fa-plus'],
        ['Categories', 'superadmin/courses/categories.php', 'fa-tags'],
    ],
    'Money' => [
        ['Payments', 'superadmin/payments/index.php', 'fa-money-bill-wave'],
        ['Salaries', 'superadmin/payroll/index.php', 'fa-wallet'],
        ['Fee finance', 'superadmin/sms/finance/index.php', 'fa-chart-line'],
        ['Sales', 'superadmin/sms/sales/index.php', 'fa-cash-register'],
    ],
    'SmS' => [
        ['SmS students', 'superadmin/sms/students/index.php', 'fa-users'],
        ['Prospects', 'superadmin/sms/prospects/index.php', 'fa-user-clock'],
        ['Projects', 'superadmin/sms/projects/index.php', 'fa-briefcase'],
    ],
    'System' => [
        ['Settings (PIN)', 'superadmin/settings.php', 'fa-gear'],
        ['Install app', '#install', 'fa-download'],
        ['Sign out', 'superadmin/logout.php', 'fa-arrow-right-from-bracket'],
    ],
];
$sa_bottom = [
    ['Home', 'superadmin/dashboard.php', '/superadmin/dashboard.php', 'fa-house'],
    ['Students', 'superadmin/students/index.php', '/superadmin/students/', 'fa-user-graduate'],
    ['Money', 'superadmin/payments/index.php', '/superadmin/payments/', 'fa-wallet'],
    ['Tutors', 'superadmin/tutors/index.php', '/superadmin/tutors/', 'fa-chalkboard-user'],
];
$sa_initial = strtoupper(substr((string)($_SESSION['username'] ?? 'S'), 0, 1));
$sa_isActive = fn(string $frag): bool => strpos(strtok($sa_uri, '?'), $frag) !== false;
?>
<header class="sa-top">
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
        <span class="sa-avatar" title="<?php echo htmlspecialchars((string)($_SESSION['email'] ?? '')); ?>"><?php echo htmlspecialchars($sa_initial); ?></span>
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
