<?php
// public/admin/courses/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_duration.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/models/CourseCategoryModel.php';

use Models\Connection;
use Models\CourseModel;
use Models\CourseCategoryModel;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$categoryModel = new CourseCategoryModel($pdo);

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['all', 'active', 'inactive'], true)) {
    $status = 'all';
}
$categoryParam = $_GET['category'] ?? 'all';
$categoryId = null;
if ($categoryParam === 'uncategorized') {
    $categoryId = 0;
} elseif ($categoryParam !== 'all' && $categoryParam !== '') {
    $categoryId = (int) $categoryParam;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed, please try again.');
    } else {
        $id = (int) $_POST['toggle_id'];
        $active = $_POST['set_active'] === '1';
        $courseModel->setActive($id, $active);
        SessionModel::setFlash('success', $active ? 'Course activated.' : 'Course deactivated.');
    }
    $redirect = url('admin/courses/index.php');
    if (!empty($_POST['redirect_query'])) {
        $redirect .= '?' . $_POST['redirect_query'];
    }
    header('Location: ' . $redirect);
    exit();
}

try {
    $categories = $categoryModel->getAll(false);
} catch (Throwable $e) {
    $categories = [];
}

$allCourses = $courseModel->getAll(false);
$categoryCounts = ['all' => count($allCourses), 'uncategorized' => 0];
foreach ($categories as $cat) {
    $categoryCounts[(int) $cat['id']] = 0;
}
foreach ($allCourses as $c) {
    $cid = $c['category_id'] ?? null;
    if ($cid === null || $cid === '') {
        $categoryCounts['uncategorized']++;
    } elseif (isset($categoryCounts[(int) $cid])) {
        $categoryCounts[(int) $cid]++;
    }
}

$courses = $courseModel->listFiltered($search !== '' ? $search : null, $categoryId, $status);

$page_title = 'Courses';
$breadcrumbs = [['label' => 'Courses', 'active' => true]];

ob_start();
?>
<style>
.admin-courses-layout { display: flex; gap: 1.25rem; align-items: flex-start; }
.admin-courses-sidebar {
  flex: 0 0 260px;
  max-width: 260px;
  position: sticky;
  top: 1rem;
}
.admin-courses-main { flex: 1; min-width: 0; }
.admin-courses-sidebar .list-group-item {
  border: none;
  border-radius: 8px !important;
  margin-bottom: 4px;
  font-size: 0.9rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.admin-courses-sidebar .list-group-item.active {
  background: var(--brand-primary, #0D9488);
  border-color: var(--brand-primary, #0D9488);
}
.admin-course-card { overflow: hidden; transition: box-shadow .2s ease, transform .2s ease; }
.admin-course-card:hover { box-shadow: 0 .5rem 1.25rem rgba(0,0,0,.12) !important; transform: translateY(-2px); }
.admin-course-card__img-wrap { position: relative; height: 160px; background: #f1f5f9; }
.admin-course-card__img { width: 100%; height: 100%; object-fit: cover; display: block; }
.admin-course-card__status { position: absolute; top: 10px; right: 10px; }
.admin-course-card__category { position: absolute; top: 10px; left: 10px; max-width: calc(100% - 80px); overflow: hidden; text-overflow: ellipsis; }
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
@media (max-width: 991.98px) {
  .admin-courses-layout { flex-direction: column; }
  .admin-courses-sidebar { flex: none; max-width: none; width: 100%; position: static; }
}
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h5 class="mb-0"><i class="fas fa-book me-2"></i>Courses</h5>
  <div class="d-flex gap-2">
    <a href="<?php echo url('admin/courses/categories.php'); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-folder me-1"></i> Categories
    </a>
    <a href="<?php echo url('admin/courses/create.php'); ?>" class="btn btn-primary btn-sm">
      <i class="fas fa-plus me-1"></i> Add Course
    </a>
  </div>
</div>

<div class="admin-courses-layout">
  <aside class="admin-courses-sidebar">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold py-2"><i class="fas fa-filter me-1"></i> Search &amp; filter</div>
      <div class="card-body">
        <form method="get" class="mb-3">
          <label class="form-label small text-muted mb-1">Search courses</label>
          <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                 class="form-control form-control-sm mb-2" placeholder="Title, abbrev, category…">
          <label class="form-label small text-muted mb-1">Status</label>
          <select name="status" class="form-select form-select-sm mb-2">
            <?php foreach (['all' => 'All statuses', 'active' => 'Active only', 'inactive' => 'Inactive only'] as $val => $label): ?>
              <option value="<?php echo $val; ?>" <?php echo $status === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($categoryParam !== 'all'): ?>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryParam); ?>">
          <?php endif; ?>
          <button type="submit" class="btn btn-primary btn-sm w-100">Apply</button>
          <?php if ($search !== '' || $status !== 'all' || $categoryParam !== 'all'): ?>
            <a href="<?php echo url('admin/courses/index.php'); ?>" class="btn btn-link btn-sm w-100 mt-1">Clear filters</a>
          <?php endif; ?>
        </form>

        <div class="small text-muted text-uppercase fw-semibold mb-2" style="letter-spacing:.04em;">Categories</div>
        <div class="list-group list-group-flush">
          <?php
          $catLinks = [
              'all' => ['label' => 'All courses', 'count' => $categoryCounts['all']],
              'uncategorized' => ['label' => 'Uncategorized', 'count' => $categoryCounts['uncategorized']],
          ];
          foreach ($categories as $cat) {
              $catLinks[(string) (int) $cat['id']] = [
                  'label' => $cat['name'],
                  'count' => $categoryCounts[(int) $cat['id']] ?? 0,
              ];
          }
          foreach ($catLinks as $key => $info):
              $isActive = (string) $categoryParam === (string) $key;
              $qs = http_build_query(array_filter([
                  'search' => $search !== '' ? $search : null,
                  'status' => $status !== 'all' ? $status : null,
                  'category' => $key === 'all' ? null : $key,
              ]));
              $href = url('admin/courses/index.php') . ($qs ? '?' . $qs : '');
          ?>
            <a href="<?php echo htmlspecialchars($href); ?>"
               class="list-group-item list-group-item-action <?php echo $isActive ? 'active' : ''; ?>">
              <span><?php echo htmlspecialchars($info['label']); ?></span>
              <span class="badge <?php echo $isActive ? 'bg-light text-dark' : 'bg-secondary'; ?>"><?php echo (int) $info['count']; ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </aside>

  <div class="admin-courses-main">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <span class="text-muted small"><?php echo count($courses); ?> course<?php echo count($courses) === 1 ? '' : 's'; ?> found</span>
    </div>

    <?php if (empty($courses)): ?>
      <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
          <i class="fas fa-search fa-2x mb-3 opacity-50"></i>
          <p class="mb-0">No courses match your filters.</p>
          <a href="<?php echo url('admin/courses/create.php'); ?>" class="btn btn-sm btn-primary mt-3">Add a course</a>
        </div>
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($courses as $course): ?>
          <div class="col-sm-6 col-xl-4">
            <?php include __DIR__ . '/../../components/admin/course_card.php'; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
