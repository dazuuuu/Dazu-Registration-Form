<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/GalleryController.php';

$galleryController = new GalleryController($pdo);
$page_title = 'Gallery Management';
$breadcrumbs = [['label' => 'Gallery', 'active' => true]];

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $result = $galleryController->delete($_GET['delete']);
    $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
    header('Location: index.php');
    exit();
}

// Handle status toggle
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $item = $galleryController->getById($_GET['toggle']);
    if ($item) {
        $newStatus = $item['status'] === 'active' ? 'inactive' : 'active';
        $galleryController->update($_GET['toggle'], ['status' => $newStatus]);
        $_SESSION['flash']['success'] = 'Gallery item status updated';
    }
    header('Location: index.php');
    exit();
}

$items = $galleryController->getAll(null, 50, 0);
$categories = $galleryController->getCategories();

ob_start();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">All Gallery Items</h5>
        <a href="create.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add New Item
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($items)): ?>
            <div class="text-center py-5">
                <i class="fas fa-images fa-3x text-muted mb-3"></i>
                <p>No gallery items found.</p>
                <a href="create.php" class="btn btn-primary">Add Your First Gallery Item</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Media</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Sort</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td width="80">
                                    <?php if ($item['media_type'] === 'image' && $item['file_path']): ?>
                                        <img src="<?php echo $item['thumbnail_path'] ?? $item['file_path']; ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px;">
                                    <?php elseif ($item['media_type'] === 'video'): ?>
                                        <i class="fas fa-video fa-2x text-muted"></i>
                                    <?php else: ?>
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                    <?php if ($item['description']): ?>
                                        <br><small class="text-muted"><?php echo substr(htmlspecialchars($item['description']), 0, 60); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo ucfirst($item['media_type']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
                                <td>
                                    <a href="?toggle=<?php echo $item['id']; ?>" class="text-decoration-none">
                                        <span class="badge bg-<?php echo $item['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    </a>
                                </td>
                                <td><?php echo number_format($item['view_count']); ?></td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" style="width: 70px;" 
                                           value="<?php echo $item['sort_order']; ?>" 
                                           data-id="<?php echo $item['id']; ?>" 
                                           onchange="updateSortOrder(this)">
                                </td>
                                <td>
                                    <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['title']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateSortOrder(input) {
    const id = input.dataset.id;
    const sortOrder = input.value;
    
    fetch('/Realestate/public/api/gallery/update_sort.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, sort_order: sortOrder })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to update sort order');
        }
    });
}

function confirmDelete(id, title) {
    if (confirm(`Are you sure you want to delete "${title}"?`)) {
        window.location.href = `?delete=${id}`;
    }
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>