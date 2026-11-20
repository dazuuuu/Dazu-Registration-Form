<?php
// public/components/assignments/by_student.php — submitted work grouped by student: one card per student (name, latest title, "+N others",
// "N to grade"); open it to see every piece they handed in, each graded on its own. A search box filters by name. No dropdowns.
// Needs: $rows (AssignmentModel::queue rows), $sbGrade (link to grade.php), optional $sbStudents (all students: user_id => [full_name]),
// optional $sbBulk (show the Mark-as-Pass ticks), $sbEmpty (message when nothing), $sbQuery (start text of the search).
use Models\AssignmentModel;
$sbBulk ??= false; $sbStudents ??= []; $sbQuery ??= ''; $sbEmpty ??= 'Nothing here.';
// One row per student: name, their latest submitted assignment, "+N others". Click to see everything they handed in.
$byStudent = [];
foreach ($rows as $r) {
    $k = (int) $r['student_user_id'];
    $byStudent[$k]['name'] = $r['full_name'];
    $byStudent[$k]['items'][] = $r;
}
foreach ($sbStudents as $k => $st) if (!isset($byStudent[(int) $k])) $byStudent[(int) $k] = ['name' => $st['full_name'], 'items' => []];
foreach ($byStudent as &$bs) {
    usort($bs['items'], fn($a, $b) => strcmp((string) $b['submitted_at'], (string) $a['submitted_at']));
    $bs['latest'] = $bs['items'][0] ?? null;
    $bs['waiting'] = count(array_filter($bs['items'], fn($x) => $x['status'] === 'submitted'));
}
unset($bs);
uasort($byStudent, fn($a, $b) => strcmp((string) ($b['latest']['submitted_at'] ?? ''), (string) ($a['latest']['submitted_at'] ?? '')) ?: strcasecmp($a['name'], $b['name']));
$ini ??= fn($n) => strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string) $n)), 0, 2))));
?>
<style>
.sb-search{position:relative;max-width:420px;margin-bottom:14px}
.sb-search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted)}
.sb-search input{padding-left:38px}
.sb-st{border-radius:16px;margin-bottom:10px;overflow:hidden}
.sb-st summary{list-style:none;display:flex;align-items:center;gap:12px;padding:14px 16px;cursor:pointer}
.sb-st summary::-webkit-details-marker{display:none}
.sb-ava{width:42px;height:42px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;background:var(--g,#15A24A)}
.sb-mid{min-width:0;flex:1}
.sb-nm{font-weight:700;line-height:1.2}
.sb-last{font-size:.82rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-last b{color:var(--ink,#14201a);font-weight:600}
.sb-more{font-size:.75rem;font-weight:700;color:var(--g-d,#0f8a3d);white-space:nowrap}
.sb-chev{color:var(--muted);transition:transform .2s}
.sb-st[open] .sb-chev{transform:rotate(180deg)}
.sb-body{padding:0 12px 12px}
</style>
<div class="sb-search"><i class="fas fa-search"></i><input type="search" class="form-control" id="sbFind" placeholder="Search a student's name…" value="<?php echo asg_e($sbQuery); ?>" autocomplete="off"></div>
<?php if ($sbBulk): ?><form method="POST" id="bulkPass"><?php echo csrf_field(); ?><input type="hidden" name="action" value="bulk_pass"></form><?php endif; ?>
<?php if (!$byStudent): ?>
  <div class="card-tutor"><div class="as-empty"><i class="fas fa-inbox"></i><?php echo asg_e($sbEmpty); ?></div></div>
<?php else: ?>
  <?php if ($sbBulk && array_filter($rows, fn($r) => $r['status'] === 'submitted' && $r['grading_mode'] === 'pass')): ?>
    <div class="d-flex justify-content-end mb-2"><button form="bulkPass" class="btn btn-sm btn-outline-success" onclick="return confirm('Mark the selected work as Pass?')">Mark selected as Pass</button></div>
  <?php endif; ?>
  <div id="sbList">
  <?php foreach ($byStudent as $sid => $bs): $L = $bs['latest']; $others = max(0, count($bs['items']) - 1); ?>
    <details class="card-tutor sb-st" data-q="<?php echo asg_e(mb_strtolower($bs['name'])); ?>">
      <summary>
        <span class="sb-ava"><?php echo asg_e($ini($bs['name'])); ?></span>
        <span class="sb-mid"><span class="sb-nm d-block"><?php echo asg_e($bs['name']); ?></span>
          <span class="sb-last d-block"><?php if ($L): ?><b><?php echo asg_e($L['title']); ?></b> · <?php echo asg_e(AssignmentModel::local($L['submitted_at'], 'D j M, H:i')); ?><?php else: ?>Nothing handed in yet<?php endif; ?></span></span>
        <?php if ($others > 0): ?><span class="sb-more">+<?php echo $others; ?> other<?php echo $others > 1 ? 's' : ''; ?></span><?php endif; ?>
        <?php if ($bs['waiting']): ?><span class="badge bg-success"><?php echo $bs['waiting']; ?> to grade</span><?php endif; ?>
        <i class="fas fa-chevron-down sb-chev"></i>
      </summary>
      <div class="sb-body"><?php if (!$bs['items']): ?><p class="small text-muted mb-0 px-1">No work handed in yet.</p><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Assignment and course</th><th>Submitted</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($bs['items'] as $r):
            $state = $r['status'] === 'graded' ? 'graded' : ($r['status'] === 'returned' ? 'returned' : 'submitted');
            $label = $r['status'] === 'submitted' ? ((int) $r['version'] > 1 ? 'Resubmitted' : 'Awaiting') : null; ?>
          <tr>
            <td><?php if ($sbBulk && $r['status'] === 'submitted' && $r['grading_mode'] === 'pass'): ?><input type="checkbox" class="form-check-input me-2" form="bulkPass" name="ids[]" value="<?php echo (int) $r['id']; ?>" aria-label="Select"><?php endif; ?>
              <span class="fw-semibold"><?php echo asg_e($r['title']); ?></span><div class="small text-muted"><?php echo asg_e($r['course_title'] ?: 'No course'); ?></div></td>
            <td class="small"><?php echo asg_e(AssignmentModel::local($r['submitted_at'], 'D j M, H:i')); ?><?php if ($r['is_late']): ?> <span class="asg-chip st-returned"><i class="fas fa-clock"></i>Late</span><?php endif; ?></td>
            <td><?php echo $label === 'Resubmitted' ? '<span class="asg-chip st-extension"><i class="fas fa-rotate"></i>Resubmitted</span>' : asg_chip($state, $state === 'graded' ? AssignmentModel::resultText($r) : $label); ?>
              <?php if (\LinkRules::isBad($r['link_status'] ?? null)): ?> <span class="asg-chip lc-bad">Needs access</span><?php endif; ?></td>
            <td class="text-end"><a href="<?php echo asg_e($sbGrade); ?>?id=<?php echo (int) $r['id']; ?>" class="btn btn-sm <?php echo $r['status'] === 'submitted' ? 'btn-brand' : 'btn-outline-secondary'; ?>"><?php echo $r['status'] === 'submitted' ? 'Grade' : 'Open'; ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div><?php endif; ?></div>
    </details>
  <?php endforeach; ?>
  </div>
  <p class="text-center text-muted small d-none" id="sbNone">No student matches your search.</p>
<?php endif; ?>
<p class="small text-muted fst-italic mt-2">Students with the newest work come first. Only students you teach appear here.</p>
<script>
(function () { var f = document.getElementById('sbFind'), list = document.querySelectorAll('#sbList .sb-st'), none = document.getElementById('sbNone');
  function run() { var q = f.value.trim().toLowerCase(), shown = 0; list.forEach(function (d) { var ok = !q || d.dataset.q.indexOf(q) > -1; d.style.display = ok ? '' : 'none'; shown += ok; }); if (none) none.classList.toggle('d-none', shown > 0 || !list.length); }
  f.addEventListener('input', run); run(); })();
</script>

