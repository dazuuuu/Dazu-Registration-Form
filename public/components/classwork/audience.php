<?php
// "Who should see this?" picker for tutor assignments and quizzes.
// Needs $cwStudents (ClassworkModel::studentsOf), optional $cwAudience ('all'|'selected') and $cwSelected (user ids).
$cwAudience = $cwAudience ?? 'all';
$cwSelected = $cwSelected ?? [];
$cwKey = 'cw' . bin2hex(random_bytes(3));
?>
<div class="mb-3" id="<?php echo $cwKey; ?>">
    <label class="form-label fw-semibold">Who should see this?</label>
    <div class="d-flex gap-3 flex-wrap mb-2">
        <div class="form-check"><input class="form-check-input" type="radio" name="audience" value="all" id="<?php echo $cwKey; ?>a" <?php echo $cwAudience === 'all' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="<?php echo $cwKey; ?>a">All my students (<?php echo count($cwStudents); ?>)</label></div>
        <div class="form-check"><input class="form-check-input" type="radio" name="audience" value="selected" id="<?php echo $cwKey; ?>s" <?php echo $cwAudience === 'selected' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="<?php echo $cwKey; ?>s">Only the students I pick (one or more)</label></div>
    </div>
    <div class="cw-pick border rounded p-2 <?php echo $cwAudience === 'selected' ? '' : 'd-none'; ?>" style="max-height:220px;overflow:auto">
        <?php if (!$cwStudents): ?><div class="small text-muted">No students are assigned to you yet.</div><?php endif; ?>
        <?php foreach ($cwStudents as $uid => $s): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="students[]" value="<?php echo (int) $uid; ?>" id="<?php echo $cwKey . 'u' . (int) $uid; ?>" <?php echo in_array((int) $uid, $cwSelected, true) ? 'checked' : ''; ?>>
                <label class="form-check-label small" for="<?php echo $cwKey . 'u' . (int) $uid; ?>"><?php echo htmlspecialchars($s['full_name']); ?>
                    <span class="text-muted">· <?php echo htmlspecialchars($s['registration_no']); ?> · <?php echo htmlspecialchars($s['course_title']); ?></span></label>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function () {
    var box = document.getElementById('<?php echo $cwKey; ?>'), pick = box.querySelector('.cw-pick');
    box.querySelectorAll('input[name=audience]').forEach(function (r) {
        r.addEventListener('change', function () { pick.classList.toggle('d-none', r.value !== 'selected' || !r.checked); });
    });
})();
</script>
