<?php
// public/components/progress/overview.php — the overview: one small card per student, filter tabs, search, course filter, sort.
require __DIR__ . '/boot.php';

$tab = in_array($_GET['tab'] ?? '', ['good', 'slow', 'bad'], true) ? $_GET['tab'] : 'all';
$sort = in_array($_GET['sort'] ?? '', ['attention', 'name', 'progress'], true) ? $_GET['sort'] : 'attention';
$q = trim((string) ($_GET['q'] ?? ''));
$course = (int) ($_GET['course'] ?? 0);
$tutorF = $viewer['role'] === 'admin' ? (int) ($_GET['tutor'] ?? 0) : 0;
$f = ['q' => $q, 'course' => $course, 'tutor' => $tutorF];

$students = sp_students($db, $viewer, $f);
$all = sp_compute($db, $students, false);            // one query for the marks of everyone listed
$counts = sp_counts($all);
$cards = sp_sort(sp_filter_status($all, $tab), $sort);
$courses = sp_course_options($db, $viewer);
$tutors = $viewer['role'] === 'admin' ? sp_tutor_options($db) : [];

$per = 48;
$pages = max(1, (int) ceil(count($cards) / $per));
$page = max(1, min($pages, (int) ($_GET['page'] ?? 1)));
$show = array_slice($cards, ($page - 1) * $per, $per, true);
$keep = fn(array $over = []) => '?' . http_build_query(array_filter(
    array_merge(['tab' => $tab === 'all' ? '' : $tab, 'q' => $q, 'course' => $course ?: '', 'tutor' => $tutorF ?: '', 'sort' => $sort === 'attention' ? '' : $sort], $over),
    fn($v) => $v !== '' && $v !== null
));

$page_title = 'Student Progress';
$breadcrumbs = [['label' => 'Student Progress', 'active' => true]];
ob_start();
echo sp_css();
?>
<div class="sp">
<?php if (!sp_installed($db)): ?><div class="alert alert-warning">Notes, nudges and the download log need the latest update. Open System Updates and install migration 103. The cards below still work.</div><?php endif; ?>
<form class="sp-top" method="get">
    <div class="sp-tabs">
        <?php foreach (['all' => 'All', 'good' => 'Working well', 'slow' => 'Slowing down', 'bad' => 'Not working'] as $k => $label): ?>
            <a href="<?php echo sp_e($keep(['tab' => $k === 'all' ? '' : $k, 'page' => ''])); ?>" class="<?php echo $tab === $k ? 'on' : ''; ?>"><?php echo sp_e($label); ?><span><?php echo (int) $counts[$k]; ?></span></a>
        <?php endforeach; ?>
    </div>
    <div class="sp-find">
        <input type="hidden" name="tab" value="<?php echo $tab === 'all' ? '' : sp_e($tab); ?>">
        <span class="srch"><i class="fas fa-search"></i><input type="search" name="q" value="<?php echo sp_e($q); ?>" placeholder="Search by name or number"></span>
        <select name="course" onchange="this.form.submit()"><option value="">All courses</option>
            <?php foreach ($courses as $id => $t): ?><option value="<?php echo (int) $id; ?>" <?php echo $course === $id ? 'selected' : ''; ?>><?php echo sp_e($t); ?></option><?php endforeach; ?></select>
        <?php if ($tutors): ?><select name="tutor" onchange="this.form.submit()"><option value="">All tutors</option>
            <?php foreach ($tutors as $id => $t): ?><option value="<?php echo (int) $id; ?>" <?php echo $tutorF === $id ? 'selected' : ''; ?>><?php echo sp_e($t); ?></option><?php endforeach; ?></select><?php endif; ?>
        <select name="sort" onchange="this.form.submit()">
            <option value="attention" <?php echo $sort === 'attention' ? 'selected' : ''; ?>>Needs attention first</option>
            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A to Z</option>
            <option value="progress" <?php echo $sort === 'progress' ? 'selected' : ''; ?>>Least progress first</option></select>
        <button class="d-none" type="submit">Search</button>
    </div>
</form>

<?php if (!$cards): ?>
    <div class="sp-card text-muted">No students to show<?php echo ($q !== '' || $course || $tutorF || $tab !== 'all') ? ' for this filter' : ''; ?>.</div>
<?php else: ?>
<div class="sp-grid">
    <?php foreach ($show as $c) echo sp_mini($c, $sp_base, $viewer['role'] === 'admin'); ?>
</div>
<?php if ($pages > 1): ?>
<div class="d-flex gap-2 align-items-center mt-3 small">
    <?php if ($page > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo sp_e($keep(['page' => $page - 1])); ?>">Previous</a><?php endif; ?>
    <span>Page <?php echo $page; ?> of <?php echo $pages; ?> · <?php echo count($cards); ?> students</span>
    <?php if ($page < $pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo sp_e($keep(['page' => $page + 1])); ?>">Next</a><?php endif; ?>
</div>
<?php endif; ?>

<form id="spSel" method="post" action="<?php echo sp_e($sp_base . 'download.php'); ?>" class="sp-sel">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="tab" value="<?php echo sp_e($tab); ?>"><input type="hidden" name="q" value="<?php echo sp_e($q); ?>">
    <input type="hidden" name="course" value="<?php echo $course; ?>"><input type="hidden" name="tutor" value="<?php echo $tutorF; ?>"><input type="hidden" name="sort" value="<?php echo sp_e($sort); ?>">
    <button type="submit" name="mode" value="selected" id="spDl" class="btn btn-g" disabled><i class="fas fa-download me-1"></i>Download selected</button>
    <button type="submit" name="mode" value="all" class="btn btn-line"><i class="fas fa-file-pdf me-1"></i>Download all (<?php echo count($cards); ?>)</button>
    <span class="small text-muted" id="spCount">Tick students to download only those, or download every student in this filter. One student per page.</span>
</form>
<script>
(function () {
    var boxes = document.querySelectorAll('.sp-mini .tick'), btn = document.getElementById('spDl'), txt = document.getElementById('spCount');
    function upd() { var n = document.querySelectorAll('.sp-mini .tick:checked').length; btn.disabled = n < 1; if (n) txt.textContent = n + ' selected'; }
    boxes.forEach(function (b) { b.addEventListener('change', upd); });
})();
</script>
<?php endif; ?>
<div class="sp-legend"><span><b style="border-left:2px solid #1f2326;padding-left:6px;font-weight:400">The line on each bar is where the student should be today.</b></span><span>Tap a card to open the full progress card.</span><span>Photo if the student uploaded one, otherwise the initials.</span></div>
</div>
<?php
$content = ob_get_clean();
require $sp_layout;
