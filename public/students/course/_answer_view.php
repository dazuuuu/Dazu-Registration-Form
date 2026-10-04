<?php
// The student's submitted answer for one module question. Expects $c (question) and $sub (submission).
if (!isset($c, $sub)) return;
?>
<?php if (($c['answer_mode'] ?? '') === 'choice'): ?>
    <div>You chose: <strong><?php echo htmlspecialchars($c['options'][(int) $sub['answer_text']] ?? '?'); ?></strong></div>
    <?php if ($sub['status'] === 'graded' && isset($c['options'][(int) $c['correct_option']])): ?>
        <div class="small text-muted">Correct answer: <?php echo htmlspecialchars($c['options'][(int) $c['correct_option']]); ?></div>
    <?php endif; ?>
<?php else: ?>
    <?php if ($sub['answer_text']): ?><div class="fst-italic" style="white-space:pre-wrap"><?php echo htmlspecialchars($sub['answer_text']); ?></div><?php endif; ?>
    <?php if ($sub['file_path']): ?><a href="<?php echo htmlspecialchars(settings_asset_url($sub['file_path'])); ?>" target="_blank" class="small"><i class="fas fa-paperclip me-1"></i>Your file</a><?php endif; ?>
    <?php if ($sub['github_url']): ?><a href="<?php echo htmlspecialchars($sub['github_url']); ?>" target="_blank" rel="noopener" class="small ms-2"><i class="fab fa-github me-1"></i><?php echo htmlspecialchars($sub['github_url']); ?></a><?php endif; ?>
<?php endif; ?>
