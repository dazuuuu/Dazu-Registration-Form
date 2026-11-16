<?php
// A secretary page that is not built yet (prompts 4 to 6 fill these). The caller has ALREADY passed secretary_require(<its switch>), so a switched-off page never reaches this file.
// Expects $soon = [title, icon, text, menu key].
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$page_title = $soon[0];
$sp_active = $soon[3];
ob_start();
?>
<div class="sp-soon"><i class="fas <?php echo $h($soon[1]); ?>"></i><h2 class="h5 fw-bold"><?php echo $h($soon[0]); ?></h2><p class="text-muted mb-0"><?php echo $h($soon[2]); ?></p></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/secretary/layout.php';
