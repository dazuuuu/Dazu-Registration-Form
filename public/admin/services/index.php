<?php
// public/admin/services/index.php
$page_title = 'Manage Services';
$breadcrumbs = [
    ['label' => 'Services', 'active' => true]
];

ob_start();

// Start session and check admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple admin check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
    header('Location: /Realestate/public/auth/login.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../../app/config/db_connect.php';

// Get all services directly from database
$sql = "SELECT s.*, u.username as creator_name 
        FROM services s 
        LEFT JOIN users u ON s.created_by = u.id 
        ORDER BY s.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .service-card {
        transition: transform 0.2s, box-shadow 0.2s;
        margin-bottom: 20px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
    }
    .service-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .cover-image {
        height: 200px;
        object-fit: cover;
        width: 100%;
    }
    .cover-placeholder {
        height: 200px;
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #999;
    }
    .status-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        padding: 4px 10px;
        font-size: 0.7rem;
        font-weight: 500;
        border-radius: 20px;
    }
    .status-published {
        background: #e8f5e9;
        color: #2e7d32;
    }
    .status-draft {
        background: #fff3e0;
        color: #e65100;
    }
    .status-archived {
        background: #f5f5f5;
        color: #666;
    }
    .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 10px;
    }
    .btn-group-custom {
        display: flex;
        gap: 8px;
        margin-top: 15px;
    }
    .btn-custom {
        flex: 1;
        padding: 6px 12px;
        font-size: 0.8rem;
        border-radius: 6px;
        text-decoration: none;
        text-align: center;
        transition: all 0.2s;
    }
    .btn-edit {
        background: #fff;
        border: 1px solid #ccc;
        color: #1a1a1a;
    }
    .btn-edit:hover {
        background: #1a1a1a;
        border-color: #1a1a1a;
        color: #fff;
    }
    .btn-view {
        background: #fff;
        border: 1px solid #ccc;
        color: #1a1a1a;
    }
    .btn-view:hover {
        background: #1a1a1a;
        border-color: #1a1a1a;
        color: #fff;
    }
    .btn-delete {
        background: #fff;
        border: 1px solid #dc2626;
        color: #dc2626;
    }
    .btn-delete:hover {
        background: #dc2626;
        border-color: #dc2626;
        color: #fff;
    }
    .stats-row {
        display: flex;
        gap: 20px;
        margin-top: 10px;
        font-size: 0.8rem;
        color: #666;
    }
    .stats-row i {
        width: 16px;
        margin-right: 4px;
    }
    .modal-content {
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    .modal-header {
        border-bottom: 1px solid #e0e0e0;
        background: #fafafa;
    }
    .form-control, .form-select {
        border-radius: 6px;
        border: 1px solid #ccc;
    }
    .form-control:focus, .form-select:focus {
        border-color: #1a1a1a;
        box-shadow: none;
    }
    .btn-primary-custom {
        background: #1a1a1a;
        border: 1px solid #1a1a1a;
        color: #fff;
        padding: 8px 20px;
        border-radius: 6px;
    }
    .btn-primary-custom:hover {
        background: #333;
        border-color: #333;
    }
    .btn-secondary-custom {
        background: #fff;
        border: 1px solid #ccc;
        color: #1a1a1a;
        padding: 8px 20px;
        border-radius: 6px;
    }
    .btn-secondary-custom:hover {
        background: #f5f5f5;
    }
    .alert-info {
        background: #e8f0fe;
        border: 1px solid #d0e0f0;
        color: #1a1a1a;
        border-radius: 8px;
    }
    .create-btn {
        background: #1a1a1a;
        border: 1px solid #1a1a1a;
        color: #fff;
        padding: 8px 20px;
        border-radius: 6px;
        text-decoration: none;
    }
    .create-btn:hover {
        background: #333;
        border-color: #333;
        color: #fff;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .page-header h2 {
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
    }
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .btn-group-custom {
            flex-direction: column;
        }
        .btn-custom {
            width: 100%;
        }
    }
</style>

<div class="page-header">
    <h2>Manage Services</h2>
    <button type="button" class="create-btn" data-bs-toggle="modal" data-bs-target="#createServiceModal">
        <i class="fas fa-plus me-2"></i>Create New Service
    </button>
</div>

<!-- Services Grid -->
<div class="row" id="servicesGrid">
    <?php if (empty($services)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No services found. Click "Create New Service" to get started.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($services as $service): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card service-card">
                <div style="position: relative;">
                    <?php if ($service['cover_image']): ?>
                        <img src="<?php echo htmlspecialchars($service['cover_image']); ?>" class="cover-image" alt="<?php echo htmlspecialchars($service['title']); ?>">
                    <?php else: ?>
                        <div class="cover-placeholder">
                            <i class="fas fa-image fa-3x"></i>
                        </div>
                    <?php endif; ?>
                    <span class="status-badge status-<?php echo $service['status']; ?>">
                        <?php echo ucfirst($service['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($service['title']); ?></h5>
                    <p class="card-text"><?php echo htmlspecialchars(substr($service['short_description'], 0, 100)) . '...'; ?></p>
                    <div class="stats-row">
                        <span><i class="fas fa-eye"></i> <?php echo number_format($service['view_count']); ?> views</span>
                        <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($service['created_at'])); ?></span>
                    </div>
                    <div class="btn-group-custom">
                        <a href="edit.php?id=<?php echo $service['id']; ?>" class="btn-custom btn-edit">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                        <a href="../../services/readmore.php?slug=<?php echo urlencode($service['slug']); ?>" target="_blank" class="btn-custom btn-view">
                            <i class="fas fa-eye me-1"></i> View
                        </a>
                        <button onclick="deleteService(<?php echo $service['id']; ?>)" class="btn-custom btn-delete">
                            <i class="fas fa-trash me-1"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Create Service Modal -->
<div class="modal fade" id="createServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createServiceForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Service Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="short_description" class="form-label">Short Description *</label>
                        <textarea class="form-control" id="short_description" name="short_description" rows="3" required></textarea>
                        <small class="text-muted">Brief description shown on the services listing page.</small>
                    </div>
                    <div class="mb-3">
                        <label for="cover_image" class="form-label">Cover Image</label>
                        <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/*">
                        <small class="text-muted">Recommended size: 800x600 pixels</small>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-custom">Create Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('createServiceForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('/Realestate/public/api/services/create.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Service created successfully!');
            window.location.href = `edit.php?id=${result.service_id}`;
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

async function deleteService(id) {
    if (confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
        try {
            const response = await fetch('/Realestate/public/api/services/delete.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            });
            const result = await response.json();
            if (result.success) {
                location.reload();
            } else {
                alert('Error deleting service: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while deleting');
        }
    }
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>