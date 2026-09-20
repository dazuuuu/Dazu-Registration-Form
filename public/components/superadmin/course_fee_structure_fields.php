<?php
/**
 * Admin dual fee structure fields (online + physical).
 * Expects $fee_modes = ['online' => [...], 'physical' => [...]].
 */
$fee_modes = $fee_modes ?? ['online' => [], 'physical' => []];
$blocks = [
    'online' => 'Online classes',
    'physical' => 'Physical classes',
];
?>
<div class="col-12">
    <label class="form-label">Fee structure</label>
    <p class="text-muted small mb-3">Set total tuition for the whole course. Per-semester fees are optional; if left blank, the system divides total tuition by the number of semesters. Additional charges are added to the total.</p>
    <div class="row g-3">
        <?php foreach ($blocks as $mode => $heading):
            $modeData = course_fee_mode_decode($fee_modes[$mode] ?? []);
            $rows = $modeData['additional'] ?? [];
            if ($rows === []) {
                $rows = [['label' => '', 'amount' => '']];
            }
        ?>
        <div class="col-md-6">
            <div class="border rounded-3 p-3 h-100 fee-structure-block" data-mode="<?php echo htmlspecialchars($mode); ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong><?php echo htmlspecialchars($heading); ?></strong>
                    <span class="small text-muted">Total: <strong class="fee-mode-total">KSh 0</strong></span>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label small mb-1">Total tuition (KSh)</label>
                        <input type="number" name="fee_<?php echo $mode; ?>_total" class="form-control form-control-sm fee-tuition-total" min="0" step="1"
                               placeholder="e.g. 360000" value="<?php echo htmlspecialchars((string) ($modeData['tuition_total'] ?: '')); ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small mb-1">Per semester (optional)</label>
                        <input type="number" name="fee_<?php echo $mode; ?>_per_semester" class="form-control form-control-sm fee-per-semester" min="0" step="1"
                               placeholder="Auto-calculated" value="<?php echo htmlspecialchars((string) ($modeData['per_semester'] ?: '')); ?>">
                    </div>
                </div>
                <div class="small fw-semibold text-muted mb-2">Additional charges</div>
                <div class="d-flex flex-column gap-2 fee-structure-rows">
                    <?php foreach ($rows as $item): ?>
                    <div class="row g-2 align-items-center fee-structure-row">
                        <div class="col-7">
                            <input type="text" name="fee_<?php echo $mode; ?>_label[]" class="form-control form-control-sm" maxlength="120"
                                   placeholder="e.g. Tuition" value="<?php echo htmlspecialchars((string) ($item['label'] ?? '')); ?>">
                        </div>
                        <div class="col-4">
                            <input type="number" name="fee_<?php echo $mode; ?>_amount[]" class="form-control form-control-sm fee-item-amount" min="0" step="1"
                                   placeholder="0" value="<?php echo htmlspecialchars((string) ($item['amount'] ?? '')); ?>">
                        </div>
                        <div class="col-1 px-0">
                            <button type="button" class="btn btn-sm btn-outline-danger fee-row-remove" title="Remove">&times;</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2 fee-structure-add">
                    <i class="fas fa-plus me-1"></i> Add item
                </button>
                <template class="fee-structure-row-tpl">
                    <div class="row g-2 align-items-center fee-structure-row">
                        <div class="col-7">
                            <input type="text" name="fee_<?php echo $mode; ?>_label[]" class="form-control form-control-sm" maxlength="120" placeholder="e.g. Tuition">
                        </div>
                        <div class="col-4">
                            <input type="number" name="fee_<?php echo $mode; ?>_amount[]" class="form-control form-control-sm fee-item-amount" min="0" step="1" placeholder="0">
                        </div>
                        <div class="col-1 px-0">
                            <button type="button" class="btn btn-sm btn-outline-danger fee-row-remove" title="Remove">&times;</button>
                        </div>
                    </div>
                </template>
                <p class="small text-muted mt-2 mb-0">Per semester preview: <strong class="fee-semester-preview">KSh 0</strong></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function () {
  function fmt(n) { return 'KSh ' + Math.round(n).toLocaleString('en-KE'); }
  var feeInput = document.querySelector('input[name="course_fee"]');
  var semesterInput = document.querySelector('input[name="semester_count"]');
  var durationInput = document.querySelector('input[name="duration_months"]');
  if (durationInput && semesterInput) {
    durationInput.addEventListener('input', function() {
      var m = parseInt(durationInput.value, 10);
      if (!isNaN(m) && m >= 3) {
        semesterInput.value = Math.round(m / 3);
      } else if (!isNaN(m) && m <= 1) {
        semesterInput.value = 1;
      }
      syncCourseFee();
    });
  }
  function semesters() {
    var v = semesterInput ? parseInt(semesterInput.value, 10) : 0;
    return v > 0 ? v : 1;
  }
  function sumBlock(block) {
    var tuitionEl = block.querySelector('.fee-tuition-total');
    var perEl = block.querySelector('.fee-per-semester');
    var tuition = tuitionEl ? parseFloat(String(tuitionEl.value).replace(/,/g, '')) : 0;
    if (isNaN(tuition) || tuition < 0) tuition = 0;
    var total = tuition;
    var extra = 0;
    block.querySelectorAll('.fee-item-amount').forEach(function (el) {
      var v = parseFloat(String(el.value).replace(/,/g, ''));
      if (!isNaN(v) && v > 0) extra += v;
    });
    total += extra;
    var label = block.querySelector('.fee-mode-total');
    if (label) label.textContent = fmt(total);
    var per = perEl ? parseFloat(String(perEl.value).replace(/,/g, '')) : 0;
    if (isNaN(per) || per <= 0) per = tuition / semesters();
    var preview = block.querySelector('.fee-semester-preview');
    if (preview) preview.textContent = fmt(per || 0);
    return total;
  }
  function syncCourseFee() {
    var online = 0, physical = 0;
    document.querySelectorAll('.fee-structure-block').forEach(function (block) {
      var t = sumBlock(block);
      if (block.getAttribute('data-mode') === 'physical') physical = t;
      else online = t;
    });
    if (feeInput && (online > 0 || physical > 0)) {
      feeInput.value = String(Math.round(online > 0 ? online : physical));
    }
  }
  document.querySelectorAll('.fee-structure-block').forEach(function (block) {
    var rows = block.querySelector('.fee-structure-rows');
    var tpl = block.querySelector('.fee-structure-row-tpl');
    var addBtn = block.querySelector('.fee-structure-add');
    if (addBtn && tpl && rows) {
      addBtn.addEventListener('click', function () {
        rows.appendChild(tpl.content.cloneNode(true));
      });
    }
    block.addEventListener('click', function (e) {
      var btn = e.target.closest('.fee-row-remove');
      if (!btn) return;
      var row = btn.closest('.fee-structure-row');
      if (rows.querySelectorAll('.fee-structure-row').length <= 1) {
        row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        syncCourseFee();
        return;
      }
      row.remove();
      syncCourseFee();
    });
    block.addEventListener('input', syncCourseFee);
  });
  if (semesterInput) semesterInput.addEventListener('input', syncCourseFee);
  syncCourseFee();
})();
</script>
