<?php
// public/components/selfstudy/list.php — the Self-study course list. Tutors see the courses they created and the ones shared with them; admin sees every course with the owner's name.
require __DIR__ . '/boot.php';
require_once __DIR__ . '/ui.php';

$page_title = 'Self-study';
$courses = $sst_ready ? sst_list($db, $viewer) : [];
$counts = ['all' => count($courses), 'draft' => 0, 'published' => 0, 'archived' => 0];
foreach ($courses as $c) if (isset($counts[$c['status']])) $counts[$c['status']]++;
$chips = ['all' => 'All', 'draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];
$isAdmin = $viewer['role'] === 'admin';
$extra_css = '<link rel="stylesheet" href="' . sst_e(url('assets/css/selfstudy-tutor.css')) . '?v=1">';
ob_start();
?>
<div class="sst-page">
<div class="sst-listhead">
    <div><h1 class="h4 fw-bold mb-0">Self-study</h1><div class="text-muted small"><?php echo $isAdmin ? 'Every self-study course, with its owner' : 'Courses you created and courses shared with you'; ?></div></div>
    <?php if (!$isAdmin): ?><a class="sst-btn sst-primary" href="<?php echo sst_e($sst_base . 'edit.php'); ?>"><i class="fas fa-plus"></i> New self-study course</a><?php endif; ?>
</div>
<?php if (!$sst_ready): ?>
    <div class="sst-empty"><i class="fas fa-database"></i>Self-study needs migration 107 first. Ask the administrator to run the updates.</div>
<?php elseif (!$courses): ?>
    <div class="sst-empty"><i class="fas fa-laptop-code"></i><b>No self-study courses yet</b><br><?php echo $isAdmin ? 'Tutors have not created any.' : 'Create a short online course that students can study at their own pace.'; ?></div>
<?php else: ?>
    <div class="sst-top">
        <div class="sst-chips" role="group" aria-label="Filter by status">
            <?php foreach ($chips as $k => $label): ?><button type="button" class="sst-chip<?php echo $k === 'all' ? ' on' : ''; ?>" data-f="<?php echo sst_e($k); ?>" aria-pressed="<?php echo $k === 'all' ? 'true' : 'false'; ?>"><?php echo $label; ?> <span data-n="<?php echo sst_e($k); ?>"><?php echo (int) $counts[$k]; ?></span></button><?php endforeach; ?>
        </div>
        <label class="sst-search"><i class="fas fa-search"></i><input type="search" id="sstQ" placeholder="Search courses" aria-label="Search courses" autocomplete="off"></label>
    </div>
    <div class="sst-grid" id="sstGrid">
    <?php foreach ($courses as $c): $mods = (int) $c['modules']; ?>
        <a class="sst-card" href="<?php echo sst_e($sst_base . ($c['status'] === 'published' ? 'students.php?id=' : 'edit.php?id=') . (int) $c['id']); ?>" data-state="<?php echo sst_e($c['status']); ?>" data-title="<?php echo sst_e(mb_strtolower($c['title'] . ' ' . $c['owner_name'])); ?>">
            <div class="sst-cv"><?php echo sst_cover_img($c); ?><?php echo sst_pill($c['status']); ?><span class="sst-lvl <?php echo sst_e($c['level']); ?>"><?php echo sst_e(SS_LEVELS[$c['level']] ?? 'Beginner'); ?></span></div>
            <div class="sst-cb">
                <h3><?php echo sst_e(trim($c['title']) !== '' ? $c['title'] : 'Untitled course'); ?></h3>
                <p><?php echo sst_e($c['short_description']); ?></p>
                <div class="sst-meta">
                    <span><i class="fas fa-user"></i> <?php echo sst_e($isAdmin ? $c['owner_name'] : ($c['mine'] ? 'You' : $c['owner_name'])); ?><?php if ($c['access_label'] && !$c['mine']): ?> · <?php echo sst_e($c['access_label']); ?><?php if (!empty($c['shared_by'])): ?> · shared by <?php echo sst_e($c['shared_by']); ?><?php endif; ?><?php endif; ?></span>
                    <span><?php echo $mods; ?> module<?php echo $mods === 1 ? '' : 's'; ?> · <?php echo (int) $c['students']; ?> student<?php echo (int) $c['students'] === 1 ? '' : 's'; ?></span>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
    </div>
    <div class="sst-empty" id="sstNone" hidden><i class="fas fa-search"></i>No course matches. Try another word or filter.</div>
    <script>
    (function () {
        var cards = [].slice.call(document.querySelectorAll('.sst-card')), chips = [].slice.call(document.querySelectorAll('.sst-chip')), q = document.getElementById('sstQ'), none = document.getElementById('sstNone'), f = 'all';
        function run() {
            var term = q.value.trim().toLowerCase(), n = { all: 0, draft: 0, published: 0, archived: 0 }, shown = 0;
            cards.forEach(function (c) { var hit = !term || c.dataset.title.indexOf(term) !== -1; if (hit) { n.all++; n[c.dataset.state]++; } var show = hit && (f === 'all' || c.dataset.state === f); c.hidden = !show; if (show) shown++; });
            Object.keys(n).forEach(function (k) { var el = document.querySelector('[data-n="' + k + '"]'); if (el) el.textContent = n[k]; });
            none.hidden = shown > 0;
        }
        chips.forEach(function (b) { b.addEventListener('click', function () { f = b.dataset.f; chips.forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); run(); }); });
        q.addEventListener('input', run);
    })();
    </script>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require $sst_layout;
