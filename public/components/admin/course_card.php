<?php
// public/components/admin/course_card.php — admin course grid card
if (!isset($course)) {
    return;
}
$cover = !empty($course['cover_image'])
    ? settings_asset_url($course['cover_image'])
    : url('assets/images/courses/default-course.jpg');
$durLabel = function_exists('course_study_summary') ? course_study_summary($course) : '';
$isActive = !empty($course['is_active']);
$isFree = !empty($course['is_free']);
if ($isFree && !function_exists('dazu_course_enrollment_window')) {
    require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
}
$enrollWindow = $isFree ? dazu_course_enrollment_window($course) : ['label' => null, 'closed' => false];
// Free and paid courses are separate admin sections/forms — route the Edit button to
// whichever one this course actually belongs to.
$editUrl = url('admin/' . ($isFree ? 'free-courses' : 'courses') . '/edit.php?id=' . (int) $course['id']);
?>
<div class="card h-100 shadow-sm admin-course-card">
  <div class="admin-course-card__img-wrap">
    <img src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars($course['title'] ?? '') ?>" class="admin-course-card__img">
    <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?> admin-course-card__status"><?= $isActive ? 'Active' : 'Inactive' ?></span>
    <?php if ($isFree): ?>
      <span class="badge bg-warning text-dark admin-course-card__category" style="left:auto; right:10px; top:38px;">Free</span>
    <?php endif; ?>
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
    <?php if (!empty($enrollWindow['label'])): ?>
      <p class="small mb-2 <?= $enrollWindow['closed'] ? 'text-danger' : 'text-muted' ?>">
        <i class="fas fa-calendar-days me-1"></i><?= htmlspecialchars($enrollWindow['label']) ?>
        <?= $enrollWindow['closed'] ? ' (closed — hidden from public list)' : '' ?>
      </p>
    <?php endif; ?>
    <?php if (!empty($course['overview'])): ?>
      <p class="small text-muted flex-grow-1 mb-2 line-clamp-2"><?= htmlspecialchars($course['overview']) ?></p>
    <?php else: ?>
      <div class="flex-grow-1"></div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-auto">
      <div>
        <div class="small text-muted">Fee</div>
        <div class="fw-bold text-success"><?= $isFree ? 'FREE' : 'KSh ' . number_format((float)($course['course_fee'] ?? 0), 0) ?></div>
        <?php
        if (!$isFree) {
            require_once __DIR__ . '/../../../app/v1/helpers/course_fee_structure.php';
            $fees = course_school_fees($course);
            if ($fees['online'] > 0 || $fees['physical'] > 0) {
                echo '<ul class="list-unstyled small mb-0 mt-1">';
                if ($fees['online'] > 0) {
                    echo '<li class="d-flex justify-content-between gap-2"><span>Online</span><span>KSh ' . number_format($fees['online']) . '</span></li>';
                }
                if ($fees['physical'] > 0) {
                    echo '<li class="d-flex justify-content-between gap-2"><span>Physical</span><span>KSh ' . number_format($fees['physical']) . '</span></li>';
                }
                echo '</ul>';
            }
        }
        ?>
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
    <a href="<?= htmlspecialchars($editUrl) ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit me-1"></i>Edit</a>
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
