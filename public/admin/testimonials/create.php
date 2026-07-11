<?php
require_once __DIR__ . '/../../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';

if (empty($_SESSION['logged_in']) || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    header('Location: ' . url('admin/portal.php'));
    exit;
}

$page_title = 'Add Text Testimonial';
$breadcrumbs = [
    ['label' => 'Testimonials', 'url' => 'index.php'],
    ['label' => 'Add Text', 'active' => true],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash']['error'] = 'Security check failed.';
    } else {
        $name = trim($_POST['customer_name'] ?? '');
        $pdo->prepare(
            'INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, testimonial_type, service_tag, role, status, is_featured, sort_order, approved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $name,
            strtoupper(substr($name, 0, 2)),
            (int) $_POST['rating'],
            trim($_POST['testimonial_text'] ?? ''),
            'text',
            trim($_POST['service_tag'] ?? '') ?: null,
            trim($_POST['role'] ?? '') ?: null,
            'approved',
            !empty($_POST['is_featured']) ? 1 : 0,
            (int) ($_POST['sort_order'] ?? 0),
        ]);
        $_SESSION['flash']['success'] = 'Text testimonial published on homepage.';
        header('Location: index.php');
        exit;
    }
}

ob_start();
?>
<div class="card">
  <div class="card-header d-flex justify-content-between">
    <h5 class="mb-0">Add text testimonial</h5>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">Back</a>
  </div>
  <div class="card-body">
    <form method="POST">
      <?php echo csrf_field(); ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Name *</label>
          <input class="form-control" name="customer_name" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Role / title</label>
          <input class="form-control" name="role" placeholder="Data Analyst at Fintech Africa">
        </div>
        <div class="col-md-6">
          <label class="form-label">Course tag</label>
          <input class="form-control" name="service_tag" placeholder="Data Analytics">
        </div>
        <div class="col-md-3">
          <label class="form-label">Rating</label>
          <select class="form-select" name="rating">
            <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?php echo $i; ?>"><?php echo $i; ?> stars</option><?php endfor; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Sort order</label>
          <input class="form-control" type="number" name="sort_order" value="0">
        </div>
        <div class="col-12">
          <label class="form-label">Testimonial *</label>
          <textarea class="form-control" name="testimonial_text" rows="5" required></textarea>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="feat">
            <label class="form-check-label" for="feat">Featured on homepage carousel</label>
          </div>
        </div>
      </div>
      <button class="btn btn-success mt-3" type="submit"><i class="fas fa-save me-1"></i> Publish</button>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
