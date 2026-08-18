<?php
/**
 * Admin dual fee-structure repeaters (online + physical).
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
    <p class="text-muted small mb-3">Set a separate breakdown for online and physical classes. The online total is stored as the default course fee; registration uses the total for the study mode the student picks.</p>
    <div class="row g-3">
        <?php foreach ($blocks as $mode => $heading):
            $rows = $fee_modes[$mode] ?? [];
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
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function () {
  function fmt(n) { return 'KSh ' + Math.round(n).toLocaleString('en-KE'); }
  var feeInput = document.querySelector('input[name="course_fee"]');
  function sumBlock(block) {
    var total = 0;
    block.querySelectorAll('.fee-item-amount').forEach(function (el) {
      var v = parseFloat(String(el.value).replace(/,/g, ''));
      if (!isNaN(v) && v > 0) total += v;
    });
    var label = block.querySelector('.fee-mode-total');
    if (label) label.textContent = fmt(total);
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
    block.addEventListener('input', function (e) {
      if (e.target.classList.contains('fee-item-amount')) syncCourseFee();
    });
  });
  syncCourseFee();
})();
</script>
