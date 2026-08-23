<?php
/** Admin school-fee inputs (online + physical). Expects $school_fees = ['online' => '', 'physical' => '']. */
$school_fees = $school_fees ?? ['online' => '', 'physical' => ''];
?>
<div class="col-md-6">
    <label class="form-label">Online school fees (KSh)</label>
    <input type="number" name="school_fee_online" class="form-control" min="0" step="1"
           placeholder="e.g. 35000" value="<?php echo htmlspecialchars((string) ($school_fees['online'] ?? '')); ?>">
    <small class="text-muted">Fee for students studying online.</small>
</div>
<div class="col-md-6">
    <label class="form-label">Physical school fees (KSh)</label>
    <input type="number" name="school_fee_physical" class="form-control" min="0" step="1"
           placeholder="e.g. 45000" value="<?php echo htmlspecialchars((string) ($school_fees['physical'] ?? '')); ?>">
    <small class="text-muted">Fee for in-person / physical classes.</small>
</div>
