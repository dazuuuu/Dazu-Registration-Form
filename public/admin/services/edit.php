<?php
// public/admin/services/edit.php
$page_title = 'Edit Service';
$breadcrumbs = [
    ['label' => 'Services', 'url' => 'index.php'],
    ['label' => 'Edit', 'active' => true]
];

ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
    header('Location: /Realestate/public/auth/login.php');
    exit();
}

$service_id = $_GET['id'] ?? null;
if (!$service_id) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../../../app/config/db_connect.php';

// Get service details
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = :id");
$stmt->execute([':id' => $service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header('Location: index.php');
    exit();
}

// Get sections, gallery, benefits, FAQs...
$stmt = $pdo->prepare("SELECT * FROM service_sections WHERE service_id = :service_id ORDER BY sort_order ASC");
$stmt->execute([':service_id' => $service_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM service_gallery WHERE service_id = :service_id ORDER BY sort_order ASC");
$stmt->execute([':service_id' => $service_id]);
$gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM service_benefits WHERE service_id = :service_id ORDER BY sort_order ASC");
$stmt->execute([':service_id' => $service_id]);
$benefits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM service_faqs WHERE service_id = :service_id AND is_active = 1 ORDER BY sort_order ASC");
$stmt->execute([':service_id' => $service_id]);
$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .section-item, .benefit-item, .faq-item {
        background: #fafafa;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    .gallery-image {
        width: 100px;
        height: 100px;
        object-fit: cover;
        margin: 5px;
        border-radius: 6px;
        cursor: pointer;
    }
    .preview-image {
        max-width: 200px;
        margin-top: 10px;
        border-radius: 6px;
    }
    .nav-tabs {
        border-bottom: 1px solid #e0e0e0;
        margin-bottom: 20px;
    }
    .nav-tabs .nav-link {
        color: #1a1a1a;
        border: none;
        padding: 10px 20px;
    }
    .nav-tabs .nav-link:hover {
        border-color: #e0e0e0;
    }
    .nav-tabs .nav-link.active {
        color: #1a1a1a;
        border-bottom: 2px solid #1a1a1a;
        background: transparent;
    }
    .btn-sm-custom {
        padding: 4px 12px;
        font-size: 0.8rem;
        border-radius: 6px;
    }
    .btn-add {
        background: #1a1a1a;
        border: none;
        color: #fff;
        padding: 8px 16px;
        border-radius: 6px;
        margin-bottom: 20px;
    }
    .btn-add:hover {
        background: #333;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>Editing: <?php echo htmlspecialchars($service['title']); ?></h3>
    <a href="index.php" class="btn-secondary-custom">
        <i class="fas fa-arrow-left me-2"></i>Back to Services
    </a>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#basic">
            <i class="fas fa-info-circle me-2"></i>Basic Info
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sections">
            <i class="fas fa-layer-group me-2"></i>Sections 
            <span class="badge bg-secondary"><?php echo count($sections); ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#gallery">
            <i class="fas fa-images me-2"></i>Gallery 
            <span class="badge bg-secondary"><?php echo count($gallery); ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#benefits">
            <i class="fas fa-gem me-2"></i>Benefits 
            <span class="badge bg-secondary"><?php echo count($benefits); ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#faqs">
            <i class="fas fa-question-circle me-2"></i>FAQs 
            <span class="badge bg-secondary"><?php echo count($faqs); ?></span>
        </button>
    </li>
</ul>

<div class="tab-content mt-4">
    <!-- Basic Info Tab -->
    <div class="tab-pane fade show active" id="basic">
        <div class="card">
            <div class="card-body">
                <form id="updateBasicForm" enctype="multipart/form-data">
                    <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Service Title *</label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($service['title']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Short Description *</label>
                                <textarea name="short_description" class="form-control" rows="3" required><?php echo htmlspecialchars($service['short_description']); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="draft" <?php echo $service['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo $service['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="archived" <?php echo $service['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cover Image</label>
                                <?php if ($service['cover_image']): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo $service['cover_image']; ?>" class="preview-image">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="cover_image" class="form-control" accept="image/*">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary-custom">
                        <i class="fas fa-save me-2"></i>Update Basic Info
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sections Tab -->
    <div class="tab-pane fade" id="sections">
        <button class="btn-add" onclick="openSectionModal()">
            <i class="fas fa-plus me-2"></i>Add New Section
        </button>
        <div id="sectionsList">
            <?php if (empty($sections)): ?>
                <div class="alert alert-info">No sections added yet.</div>
            <?php else: ?>
                <?php foreach ($sections as $index => $section): ?>
                    <div class="section-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5><?php echo $index + 1; ?>. <?php echo ucfirst(str_replace('_', ' ', $section['section_type'])); ?></h5>
                            <button class="btn-delete btn-sm-custom" onclick="deleteSection(<?php echo $section['id']; ?>)">
                                <i class="fas fa-trash me-1"></i>Delete
                            </button>
                        </div>
                        <?php if ($section['title']): ?>
                            <div class="mt-2"><strong>Title:</strong> <?php echo htmlspecialchars($section['title']); ?></div>
                        <?php endif; ?>
                        <div class="mt-2">
                            <strong>Content:</strong>
                            <div class="mt-1 p-2 bg-white" style="border:1px solid #eee; border-radius:6px;">
                                <?php echo substr(strip_tags($section['content']), 0, 200); ?>...
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('updateBasicForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    const response = await fetch('/Realestate/public/api/services/update.php', {
        method: 'POST',
        body: formData
    });
    
    const result = await response.json();
    if (result.success) {
        alert('Service updated successfully!');
        location.reload();
    } else {
        alert('Error: ' + result.message);
    }
});

function openSectionModal() {
    // Implement section modal
    alert('Section modal would open here');
}

async function deleteSection(id) {
    if (confirm('Delete this section?')) {
        const response = await fetch('/Realestate/public/api/services/delete_section.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        });
        const result = await response.json();
        if (result.success) location.reload();
        else alert('Error: ' + result.message);
    }
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>