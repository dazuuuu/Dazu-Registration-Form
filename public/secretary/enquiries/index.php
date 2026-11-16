<?php
// Enquiries (secretary portal): the shared log of people who asked about a course. Prospects only: no student record is read, shown or linked here.
// Checked on every request: an active secretary (On leave is fine) with the Enquiries switch on (secretary_require). There is NO delete: any delete is refused.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary_ui.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryEnquiryService.php';

$sec = secretary_require('enquiries');
$db = \Models\Connection::getInstance()->getConnection();
$svc = new SecretaryEnquiryService($db);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$errors = [];
$in = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'delete' || isset($_POST['delete'])) {   // a secretary can never delete an enquiry
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Not allowed. Enquiries are never deleted.');
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'The security check failed. Please try again.';
        $in = $_POST;
    } elseif (($_POST['action'] ?? '') === 'add') {
        [$d, $errors] = $svc->validate($_POST);
        if (!$errors) {
            $r = $svc->add($sec, $d);
            if (!empty($r['ok'])) {
                SessionModel::setFlash('success', 'Enquiry added.');
                header('Location: view.php?id=' . (int) $r['id']);
                exit();
            }
            $errors[] = $r['error'];
        }
        $in = $_POST;
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$f = isset(SecretaryEnquiryService::STATUSES[$_GET['f'] ?? '']) ? (string) $_GET['f'] : '';
$fuToday = ($_GET['fu'] ?? '') === 'today';
$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 50;
$notReady = false; $rows = []; $total = 0; $stats = ['pending' => 0, 'follow_today' => 0, 'called_back' => 0, 'registered' => 0]; $courses = [];
try {
    $stats = $svc->stats();
    [$rows, $total] = $svc->search($q, $f, $per, ($page - 1) * $per, $fuToday);
    $courses = $svc->courseNames();
} catch (Throwable $e) {
    $notReady = true;   // migrations 127 and 128 not run yet
}
$today = SecretaryEnquiryService::today();
$dshort = static fn(?string $d): string => $d ? date('D j M', strtotime($d)) : '';
$statuses = SecretaryEnquiryService::STATUSES;
$sources = SecretaryEnquiryService::SOURCES;
$initial = static fn(string $n): string => mb_strtoupper(mb_substr(trim($n), 0, 1));
$val = static fn(string $k, string $d = '') => $h($GLOBALS['in'][$k] ?? $d);
$qs = static fn(array $o) => '?' . http_build_query(array_filter($o + ['q' => $GLOBALS['q'], 'f' => $GLOBALS['f'], 'fu' => $GLOBALS['fuToday'] ? 'today' : ''], fn($v) => $v !== '' && $v !== null));

$page_title = 'Enquiries';
$sp_active = 'enquiries';
ob_start();
require __DIR__ . '/../../components/secretary/enquiries_css.php';
?>
<?php if ($notReady): ?><div class="alert alert-warning">The enquiries tables are not in the database yet. Ask the admin to run the database update (migrations 127 and 128).</div><?php else: ?>
<?php if ($errors && !isset($_POST['action'])): ?><div class="alert alert-danger"><?php echo $h(implode(' ', $errors)); ?></div><?php endif; ?>

<div class="se-stats">
    <a class="se-stat" href="index.php?f=pending"><small>Pending</small><b><?php echo (int) $stats['pending']; ?></b></a>
    <a class="se-stat" href="index.php?fu=today"><small>Follow up today</small><b><?php echo (int) $stats['follow_today']; ?></b></a>
    <a class="se-stat" href="index.php?f=called_back"><small>Called back</small><b><?php echo (int) $stats['called_back']; ?></b></a>
    <a class="se-stat" href="index.php?f=registered"><small>Registered</small><b><?php echo (int) $stats['registered']; ?></b></a>
</div>

<section class="se-box">
    <div class="se-head">
        <div><h2>Enquiries</h2><p>Everyone in the secretary team sees every enquiry and who added it</p></div>
        <form class="se-tools" method="GET" action="index.php" role="search">
            <input type="search" name="q" value="<?php echo $h($q); ?>" placeholder="Search enquiries" aria-label="Search enquiries" maxlength="100">
            <?php if ($fuToday): ?><input type="hidden" name="fu" value="today"><?php endif; ?>
            <select name="f" aria-label="Filter by status" onchange="this.form.submit()">
                <option value="">Status: All</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?php echo $h($k); ?>" <?php echo $f === $k ? 'selected' : ''; ?>><?php echo $h($l); ?></option><?php endforeach; ?>
            </select>
            <button type="button" class="se-add" data-open-sheet><i class="fas fa-plus"></i> Add enquiry</button>
        </form>
    </div>
    <?php if ($fuToday): ?><div class="mb-2"><span class="se-chip called_back">Follow up today</span> <a href="index.php" class="small">show all</a></div><?php endif; ?>
    <?php if (!$rows): ?>
        <div class="se-empty"><i class="fas fa-inbox"></i><b><?php echo ($q !== '' || $f !== '') ? 'No enquiries match' : 'No enquiries yet'; ?></b><?php echo ($q !== '' || $f !== '') ? 'Try another search or status.' : 'Tap Add enquiry when someone asks about a course.'; ?></div>
    <?php else: ?>
    <table class="se-table">
        <thead><tr><th>Name and course</th><th>Source</th><th>Date</th><th>Added by</th><th>Follow up</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $fu = (string) ($r['follow_up_date'] ?? ''); $late = $fu !== '' && $fu < $today && in_array($r['status'], ['pending', 'called_back'], true); ?>
        <tr onclick="location.href='view.php?id=<?php echo (int) $r['id']; ?>'">
            <td class="se-first" data-label="Enquiry"><a class="se-name" href="view.php?id=<?php echo (int) $r['id']; ?>"><?php echo $h($r['name']); ?></a><small><?php echo $h($r['course']); ?> · <?php echo $h($r['phone']); ?></small></td>
            <td data-label="Source"><?php echo $h($sources[$r['source']] ?? $r['source']); ?></td>
            <td data-label="Date"><?php echo $h($dshort($r['enquiry_date'])); ?></td>
            <td data-label="Added by"><span class="se-by"><i><?php echo $h($initial($r['added_by_name'])); ?></i><?php echo $h($r['added_by_name']); ?></span></td>
            <td data-label="Follow up"><?php echo $fu === '' ? '—' : ($fu === $today ? '<strong>Today</strong>' : $h($dshort($fu))) . ($late ? ' <span class="se-late">overdue</span>' : ''); ?></td>
            <td data-label="Status"><span class="se-chip <?php echo $h($r['status']); ?>"><?php echo $h($statuses[$r['status']] ?? $r['status']); ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($total > $per): ?>
    <div class="se-pager"><?php if ($page > 1): ?><a href="index.php<?php echo $h($qs(['p' => $page - 1])); ?>">← Newer</a><?php endif; ?><span><?php echo (int) $total; ?> enquiries</span><?php if ($page * $per < $total): ?><a href="index.php<?php echo $h($qs(['p' => $page + 1])); ?>">Older →</a><?php endif; ?></div>
    <?php endif; ?>
    <?php endif; ?>
</section>

<div class="se-notes">
    <div><h3>If a secretary is away</h3><p>Another secretary can open any enquiry, add a note and take it over. Each note shows who wrote it and when.</p></div>
    <div><h3>Only prospects</h3><p>This log holds people who asked about a course. It is not the student list, and it never shows registered students’ details.</p></div>
</div>

<div class="se-sheet<?php echo ($errors && ($_POST['action'] ?? '') === 'add') ? ' open' : ''; ?>" id="seSheet" role="dialog" aria-modal="true" aria-labelledby="seSheetTitle">
    <form method="POST" action="index.php" class="se-form" autocomplete="off" novalidate>
        <?php echo csrf_field(); ?><input type="hidden" name="action" value="add">
        <div class="se-form-h"><h2 id="seSheetTitle">Add enquiry</h2><button type="button" class="se-x" data-close-sheet aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <?php if ($errors && ($_POST['action'] ?? '') === 'add'): ?><div class="alert alert-danger"><?php echo $h(implode(' ', $errors)); ?></div><?php endif; ?>
        <div class="se-grid">
            <div><label for="f_name">Name *</label><input id="f_name" name="name" maxlength="120" required value="<?php echo $val('name'); ?>"></div>
            <div><label for="f_phone">Phone *</label><input id="f_phone" name="phone" type="tel" inputmode="tel" maxlength="20" required value="<?php echo $val('phone'); ?>"></div>
            <div><label for="f_email">Email (optional)</label><input id="f_email" name="email" type="email" maxlength="150" value="<?php echo $val('email'); ?>"></div>
            <div><label for="f_course">Course they asked about *</label><input id="f_course" name="course" list="seCourses" maxlength="150" required value="<?php echo $val('course'); ?>" placeholder="Type, or choose from the list">
                <datalist id="seCourses"><?php foreach ($courses as $c): ?><option value="<?php echo $h($c); ?>"><?php endforeach; ?></datalist></div>
            <div><label for="f_source">Source *</label><select id="f_source" name="source"><?php foreach ($sources as $k => $l): ?><option value="<?php echo $h($k); ?>" <?php echo ($in['source'] ?? 'phone') === $k ? 'selected' : ''; ?>><?php echo $h($l); ?></option><?php endforeach; ?></select></div>
            <div><label for="f_date">Date *</label><input id="f_date" name="enquiry_date" type="date" required value="<?php echo $val('enquiry_date', $today); ?>"></div>
            <div><label for="f_fu">Follow-up date</label><input id="f_fu" name="follow_up_date" type="date" value="<?php echo $val('follow_up_date'); ?>"></div>
            <div class="full"><label for="f_note">First note</label><textarea id="f_note" name="note" rows="3" maxlength="1000" placeholder="What did they ask? What was said?"><?php echo $val('note'); ?></textarea></div>
        </div>
        <div class="se-form-f"><button type="submit" class="se-save"><i class="fas fa-check me-1"></i> Save enquiry</button><button type="button" class="se-cancel" data-close-sheet>Cancel</button></div>
    </form>
</div>
<script>
(function () {
  var sheet = document.getElementById('seSheet'); if (!sheet) return;
  function open() { sheet.classList.add('open'); document.body.style.overflow = 'hidden'; var f = document.getElementById('f_name'); if (f) setTimeout(function () { f.focus(); }, 50); }
  function close() { sheet.classList.remove('open'); document.body.style.overflow = ''; }
  document.addEventListener('click', function (e) { if (e.target.closest('[data-open-sheet]')) open(); if (e.target.closest('[data-close-sheet]')) close(); if (e.target === sheet) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  if (sheet.classList.contains('open')) document.body.style.overflow = 'hidden';
})();
</script>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/secretary/layout.php';
