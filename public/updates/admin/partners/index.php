<?php
// public/admin/partners/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/PartnerModel.php';

use Models\PartnerModel;

Middleware::requireAdmin();

$page_title = 'Partners Management';
$breadcrumbs = [
    ['label' => 'System', 'url' => url('admin/dashboard.php')],
    ['label' => 'Partners', 'active' => true],
];

$partnerModel = new PartnerModel($pdo);
$PUBLIC = defined('PUBLIC_ROOT') ? rtrim(PUBLIC_ROOT, '/\\') : realpath(__DIR__ . '/../..');
$uploadDir = ['abs' => $PUBLIC . '/uploads/partners', 'web' => 'uploads/partners'];

if (!is_dir($uploadDir['abs'])) {
    @mkdir($uploadDir['abs'], 0777, true);
}

function partner_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $message];
}

function partner_redirect(): void
{
    header('Location: index.php');
    exit;
}

function partner_safe_unlink(?string $relPath, string $publicRoot): void
{
    if (!$relPath || preg_match('#^(https?:)?//#', $relPath) || str_starts_with($relPath, '/assets/')) {
        return;
    }
    $abs = realpath($publicRoot . '/' . ltrim($relPath, '/'));
    if ($abs && str_starts_with($abs, $publicRoot) && is_file($abs)) {
        @unlink($abs);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        partner_flash('error', 'Session expired. Please try again.');
        partner_redirect();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            partner_flash('error', 'Partner name is required.');
            partner_redirect();
        }

        $logoPath = '';
        if (!empty($_FILES['logo']['name'])) {
            $upload = upload_image($_FILES['logo'], $uploadDir['abs'], $uploadDir['web']);
            if (!$upload['ok']) {
                partner_flash('error', $upload['error']);
                partner_redirect();
            }
            $logoPath = '/' . ltrim($upload['path'], '/');
        } elseif (!empty($_POST['logo_url'])) {
            $logoPath = trim($_POST['logo_url']);
        }

        if ($logoPath === '') {
            partner_flash('error', 'Please upload a partner logo image or provide a logo URL.');
            partner_redirect();
        }

        $id = $partnerModel->create([
            'name' => $name,
            'logo' => $logoPath,
            'website_url' => trim($_POST['website_url'] ?? ''),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);

        if ($id > 0) {
            partner_flash('success', "Partner '{$name}' added successfully.");
        } else {
            partner_flash('error', 'Failed to save partner.');
        }
        partner_redirect();
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $partner = $partnerModel->getById($id);
        if (!$partner) {
            partner_flash('error', 'Partner not found.');
            partner_redirect();
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            partner_flash('error', 'Partner name is required.');
            partner_redirect();
        }

        $data = [
            'name' => $name,
            'website_url' => trim($_POST['website_url'] ?? ''),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if (!empty($_FILES['logo']['name'])) {
            $upload = upload_image($_FILES['logo'], $uploadDir['abs'], $uploadDir['web']);
            if ($upload['ok']) {
                $data['logo'] = '/' . ltrim($upload['path'], '/');
                partner_safe_unlink($partner['logo'], $PUBLIC);
            } else {
                partner_flash('error', $upload['error']);
                partner_redirect();
            }
        } elseif (!empty($_POST['logo_url'])) {
            $data['logo'] = trim($_POST['logo_url']);
        }

        if ($partnerModel->update($id, $data)) {
            partner_flash('success', "Partner '{$name}' updated successfully.");
        } else {
            partner_flash('error', 'Failed to update partner.');
        }
        partner_redirect();
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $partnerModel->toggleActive($id);
        partner_flash('success', 'Partner status updated.');
        partner_redirect();
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $partner = $partnerModel->getById($id);
        if ($partner) {
            partner_safe_unlink($partner['logo'], $PUBLIC);
            $partnerModel->delete($id);
            partner_flash('success', "Partner '{$partner['name']}' deleted.");
        }
        partner_redirect();
    }
}

$partners = $partnerModel->getAll(false);

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="mb-1 fw-bold text-dark"><i class="fas fa-handshake text-success me-2"></i>Institutional & Industry Partners</h4>
    <p class="text-muted small mb-0">Manage partner logos shown in the homepage carousel after the courses section.</p>
  </div>
  <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPartnerModal">
    <i class="fas fa-plus me-1"></i> Add New Partner
  </button>
</div>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-<?php echo $_SESSION['flash']['type'] === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($_SESSION['flash']['msg']); unset($_SESSION['flash']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="card shadow-sm border-0 rounded-3">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light text-uppercase fs-7">
          <tr>
            <th class="ps-4" style="width: 80px;">Order</th>
            <th style="width: 140px;">Logo</th>
            <th>Partner Name</th>
            <th>Website</th>
            <th>Status</th>
            <th class="text-end pe-4" style="width: 180px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($partners)): ?>
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="fas fa-handshake fa-3x mb-3 text-secondary opacity-50"></i>
                <p class="mb-2">No partners added yet.</p>
                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addPartnerModal">Add First Partner</button>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($partners as $p): ?>
              <tr>
                <td class="ps-4 fw-bold text-secondary"><?php echo (int) $p['sort_order']; ?></td>
                <td>
                  <div class="p-2 bg-light border rounded d-inline-flex align-items-center justify-center" style="width: 100px; height: 46px;">
                    <img src="<?php echo htmlspecialchars(settings_asset_url($p['logo'])); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="img-fluid" style="max-height: 36px; max-width: 90px; object-fit: contain;" onerror="this.src='/assets/images/logo/logo.png'">
                  </div>
                </td>
                <td>
                  <strong class="text-dark d-block"><?php echo htmlspecialchars($p['name']); ?></strong>
                  <small class="text-muted">Added: <?php echo date('M d, Y', strtotime($p['created_at'])); ?></small>
                </td>
                <td>
                  <?php if (!empty($p['website_url'])): ?>
                    <a href="<?php echo htmlspecialchars($p['website_url']); ?>" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none small">
                      <?php echo htmlspecialchars(parse_url($p['website_url'], PHP_URL_HOST) ?: $p['website_url']); ?>
                      <i class="fas fa-external-link-alt ms-1 fs-8"></i>
                    </a>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="POST" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                    <button type="submit" class="btn btn-sm badge rounded-pill border-0 <?php echo $p['is_active'] ? 'bg-success text-white' : 'bg-secondary text-white'; ?>" title="Click to toggle status">
                      <?php echo $p['is_active'] ? 'Active' : 'Inactive'; ?>
                    </button>
                  </form>
                </td>
                <td class="text-end pe-4">
                  <button type="button" class="btn btn-sm btn-outline-primary me-1"
                          data-bs-toggle="modal" data-bs-target="#editPartnerModal"
                          data-id="<?php echo (int) $p['id']; ?>"
                          data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                          data-logo="<?php echo htmlspecialchars(settings_asset_url($p['logo']), ENT_QUOTES); ?>"
                          data-website="<?php echo htmlspecialchars($p['website_url'] ?? '', ENT_QUOTES); ?>"
                          data-order="<?php echo (int) $p['sort_order']; ?>"
                          data-active="<?php echo $p['is_active'] ? '1' : '0'; ?>">
                    <i class="fas fa-edit"></i>
                  </button>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete partner \'<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>\'?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: Add Partner -->
<div class="modal fade" id="addPartnerModal" tabindex="-1" aria-labelledby="addPartnerModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold" id="addPartnerModalLabel"><i class="fas fa-plus-circle text-success me-2"></i>Add Partner</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold">Partner / Company Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Microsoft Learn, Safaricom" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Partner Logo <span class="text-danger">*</span></label>
            <input type="file" name="logo" class="form-control" accept="image/*,.svg" onchange="previewPartnerLogo(this, '#addLogoPreview')">
            <small class="text-muted d-block mt-1">Upload PNG, SVG, JPG, or WebP logo (transparent background recommended).</small>
            <div id="addLogoPreview" class="mt-2 text-center p-2 bg-light border rounded" style="display:none;">
              <img src="" alt="Preview" style="max-height: 50px; max-width: 140px; object-fit: contain;">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Or Logo URL / Asset Path</label>
            <input type="text" name="logo_url" class="form-control" placeholder="https://... or /assets/images/...">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Website URL (Optional)</label>
            <input type="url" name="website_url" class="form-control" placeholder="https://example.com">
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-bold">Sort Order</label>
              <input type="number" name="sort_order" class="form-control" value="0">
            </div>
            <div class="col-md-6 mb-3 d-flex align-items-end">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="add_is_active" checked value="1">
                <label class="form-check-label fw-bold" for="add_is_active">Active on Carousel</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Save Partner</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Edit Partner -->
<div class="modal fade" id="editPartnerModal" tabindex="-1" aria-labelledby="editPartnerModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id" value="">
        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold" id="editPartnerModalLabel"><i class="fas fa-edit text-primary me-2"></i>Edit Partner</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold">Partner / Company Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Current Logo</label>
            <div class="mb-2 p-2 bg-light border rounded text-center">
              <img src="" id="edit_current_logo" alt="Current Logo" style="max-height: 50px; max-width: 140px; object-fit: contain;">
            </div>
            <label class="form-label">Replace Logo File</label>
            <input type="file" name="logo" class="form-control" accept="image/*,.svg" onchange="previewPartnerLogo(this, '#editLogoPreview')">
            <small class="text-muted d-block mt-1">Leave empty to keep current logo.</small>
            <div id="editLogoPreview" class="mt-2 text-center p-2 bg-light border rounded" style="display:none;">
              <img src="" alt="New Preview" style="max-height: 50px; max-width: 140px; object-fit: contain;">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Or Custom Logo URL</label>
            <input type="text" name="logo_url" id="edit_logo_url" class="form-control" placeholder="https://... or /assets/...">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Website URL (Optional)</label>
            <input type="url" name="website_url" id="edit_website_url" class="form-control" placeholder="https://example.com">
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-bold">Sort Order</label>
              <input type="number" name="sort_order" id="edit_sort_order" class="form-control">
            </div>
            <div class="col-md-6 mb-3 d-flex align-items-end">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                <label class="form-check-label fw-bold" for="edit_is_active">Active on Carousel</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Partner</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function previewPartnerLogo(input, containerSelector) {
  var container = document.querySelector(containerSelector);
  if (!container) return;
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var img = container.querySelector('img');
      if (img) img.src = e.target.result;
      container.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  } else {
    container.style.display = 'none';
  }
}

document.addEventListener('DOMContentLoaded', function(){
  var editModal = document.getElementById('editPartnerModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', function(e){
      var btn = e.relatedTarget;
      document.getElementById('edit_id').value = btn.getAttribute('data-id') || '';
      document.getElementById('edit_name').value = btn.getAttribute('data-name') || '';
      document.getElementById('edit_current_logo').src = btn.getAttribute('data-logo') || '';
      document.getElementById('edit_website_url').value = btn.getAttribute('data-website') || '';
      document.getElementById('edit_sort_order').value = btn.getAttribute('data-order') || '0';
      document.getElementById('edit_is_active').checked = (btn.getAttribute('data-active') === '1');
      document.getElementById('editLogoPreview').style.display = 'none';
    });
  }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>
