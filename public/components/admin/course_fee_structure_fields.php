<?php
/** Admin fee-structure repeater. Expects $fee_items as list of {label,amount}. */
$fee_items = $fee_items ?? [];
if ($fee_items === []) {
    $fee_items = [['label' => '', 'amount' => '']];
}
?>
<div class="col-12" id="feeStructureBlock">
    <label class="form-label">Fee structure</label>
    <p class="text-muted small mb-2">Break the course fee into line items (tuition, materials, exam, etc.). The total below becomes the course fee. Leave blank to use a single total only.</p>
    <div id="feeStructureRows" class="d-flex flex-column gap-2">
        <?php foreach ($fee_items as $item): ?>
        <div class="row g-2 align-items-center fee-structure-row">
            <div class="col-md-7">
                <input type="text" name="fee_item_label[]" class="form-control" maxlength="120"
                       placeholder="e.g. Tuition" value="<?php echo htmlspecialchars((string) ($item['label'] ?? '')); ?>">
            </div>
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text">KSh</span>
                    <input type="number" name="fee_item_amount[]" class="form-control fee-item-amount" min="0" step="1"
                           placeholder="0" value="<?php echo htmlspecialchars((string) ($item['amount'] ?? '')); ?>">
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger w-100 fee-row-remove" title="Remove row">&times;</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="feeStructureAdd">
            <i class="fas fa-plus me-1"></i> Add line item
        </button>
        <span class="small text-muted">Breakdown total: <strong id="feeStructureTotal">KSh 0</strong></span>
    </div>
</div>
<template id="feeStructureRowTpl">
    <div class="row g-2 align-items-center fee-structure-row">
        <div class="col-md-7">
            <input type="text" name="fee_item_label[]" class="form-control" maxlength="120" placeholder="e.g. Tuition">
        </div>
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text">KSh</span>
                <input type="number" name="fee_item_amount[]" class="form-control fee-item-amount" min="0" step="1" placeholder="0">
            </div>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger w-100 fee-row-remove" title="Remove row">&times;</button>
        </div>
    </div>
</template>
<script>
(function () {
  var block = document.getElementById('feeStructureBlock');
  if (!block) return;
  var rows = document.getElementById('feeStructureRows');
  var tpl = document.getElementById('feeStructureRowTpl');
  var addBtn = document.getElementById('feeStructureAdd');
  var totalEl = document.getElementById('feeStructureTotal');
  var feeInput = document.querySelector('input[name="course_fee"]');

  function fmt(n) {
    return 'KSh ' + Math.round(n).toLocaleString('en-KE');
  }
  function sum() {
    var total = 0;
    rows.querySelectorAll('.fee-item-amount').forEach(function (el) {
      var v = parseFloat(String(el.value).replace(/,/g, ''));
      if (!isNaN(v) && v > 0) total += v;
    });
    totalEl.textContent = fmt(total);
    if (feeInput && total > 0) {
      feeInput.value = String(Math.round(total));
    }
  }
  addBtn.addEventListener('click', function () {
    rows.appendChild(tpl.content.cloneNode(true));
  });
  block.addEventListener('click', function (e) {
    var btn = e.target.closest('.fee-row-remove');
    if (!btn) return;
    var row = btn.closest('.fee-structure-row');
    if (rows.querySelectorAll('.fee-structure-row').length <= 1) {
      row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      sum();
      return;
    }
    row.remove();
    sum();
  });
  block.addEventListener('input', function (e) {
    if (e.target.classList.contains('fee-item-amount') || e.target.name === 'fee_item_label[]') {
      sum();
    }
  });
  sum();
})();
</script>
