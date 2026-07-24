<?php
// public/components/admin/course_card.php — admin course grid card
if (!isset($course)) {
    return;
}
if (!function_exists('dazu_course_image_url')) {
    require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
}
$cover = dazu_course_image_url($course);
$durLabel = function_exists('course_study_summary') ? course_study_summary($course) : '';
$isActive = !empty($course['is_active']);
?>
<div class="card h-100 shadow-sm admin-course-card">
  <div class="admin-course-card__img-wrap">
    <img src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars($course['title'] ?? '') ?>" class="admin-course-card__img">
    <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?> admin-course-card__status"><?= $isActive ? 'Active' : 'Inactive' ?></span>
    <?php if (!empty($course['category_name'])): ?>
      <span class="badge bg-dark admin-course-card__category"><?= htmlspecialchars($course['category_name']) ?></span>
    <?php endif; ?>
  </div>
  <div class="card-body d-flex flex-column">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
      <h6 class="card-title mb-0 fw-semibold"><?= htmlspecialchars($course['title'] ?? '') ?></h6>
      <span class="badge bg-secondary text-nowrap"><?= htmlspecialchars($course['abbreviation'] ?? '') ?></span>
    </div>
    <?php if ($durLabel !== ''): ?>
      <p class="small text-muted mb-2"><i class="fas fa-clock me-1"></i><?= htmlspecialchars($durLabel) ?></p>
    <?php endif; ?>
    <?php if (!empty($course['overview'])): ?>
      <p class="small text-muted flex-grow-1 mb-2 line-clamp-2"><?= htmlspecialchars($course['overview']) ?></p>
    <?php else: ?>
      <div class="flex-grow-1"></div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-auto">
      <div>
        <div class="small text-muted">Fee</div>
        <div class="fw-bold text-success">KSh <?= number_format((float)($course['course_fee'] ?? 0), 0) ?></div>
      </div>
      <div class="text-end">
        <div class="small text-muted">Enrolled</div>
        <div class="fw-semibold"><?= (int)($course['last_sequence'] ?? 0) ?></div>
      </div>
    </div>
  </div>
  <div class="card-footer bg-white border-top-0 pt-0 d-flex flex-wrap gap-1 justify-content-end">
    <?php if ($isActive): ?>
    <a href="<?= htmlspecialchars(course_whatsapp_share_url($course)) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
    <a href="<?= course_share_url((int)$course['id']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Share link"><i class="fas fa-share-nodes"></i></a>
    <?php endif; ?>
    <a href="<?= url('admin/courses/edit.php?id=' . (int)$course['id']) ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit me-1"></i>Edit</a>
    <form method="POST" class="d-inline" onsubmit="return confirm('<?= $isActive ? 'Deactivate' : 'Activate' ?> this course?');">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="toggle_id" value="<?= (int)$course['id'] ?>">
      <input type="hidden" name="set_active" value="<?= $isActive ? '0' : '1' ?>">
      <input type="hidden" name="redirect_query" value="<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>">
      <button type="submit" class="btn btn-sm btn-outline-<?= $isActive ? 'danger' : 'success' ?>">
        <i class="fas fa-<?= $isActive ? 'ban' : 'check' ?>"></i>
      </button>
    </form>
  </div>
</div>
