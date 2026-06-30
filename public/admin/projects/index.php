<?php
// public/admin/projects/index.php (COMPLETE WORKING VERSION)
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';
require_once __DIR__ . '/../../../app/controllers/ProjectController.php';

// Check if logged in and is admin
if (!isLoggedIn()) {
    redirect('/Realestate/public/admin/portal.php');
}

$role_id = $_SESSION['role_id'] ?? 3;
if ($role_id > 2) {
    redirect('/Realestate/public/auth/login.php');
}

$controller = new ProjectController($pdo);
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_category'])) {
        $result = $controller->createCategory($_POST);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['update_category'])) {
        $result = $controller->updateCategory($_POST['category_id'], $_POST);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['delete_category'])) {
        $result = $controller->deleteCategory($_POST['category_id']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['create_project'])) {
        $result = $controller->createProject($_POST, $_FILES);
        if ($result['success']) {
            $message = $result['message'];
            header("refresh:2;url=view.php?id=" . $result['project_id']);
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['delete_project'])) {
        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            $error = 'Invalid project ID.';
        } else {
            try {
                // Grab the cover image path first so we can remove the file after deletion
                $stmt = $pdo->prepare("SELECT cover_image FROM projects WHERE id = :id");
                $stmt->execute([':id' => $projectId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    $error = 'Project not found or already deleted.';
                } else {
                    // Remove children first (works whether or not FK cascade is set up)
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM project_gallery       WHERE project_id = :id")->execute([':id' => $projectId]);
                    $pdo->prepare("DELETE FROM project_videos        WHERE project_id = :id")->execute([':id' => $projectId]);
                    $pdo->prepare("DELETE FROM project_tag_relations WHERE project_id = :id")->execute([':id' => $projectId]);
                    $pdo->prepare("DELETE FROM projects              WHERE id = :id")->execute([':id' => $projectId]);
                    $pdo->commit();

                    // Best-effort: delete the cover image file from disk
                    if (!empty($row['cover_image'])) {
                        $imgPath = $_SERVER['DOCUMENT_ROOT'] . parse_url($row['cover_image'], PHP_URL_PATH);
                        if (is_file($imgPath)) { @unlink($imgPath); }
                    }
                    $message = 'Project deleted successfully.';
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'Failed to delete project. Please try again.';
            }
        }
    }
}

$categories = $controller->getCategories();
$projects = $controller->getProjects();

// Page variables
$page_title = 'Projects Management';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/Realestate/public/admin/dashboard.php'],
    ['label' => 'Projects', 'active' => true]
];

ob_start();
?>

<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .project-card {
        transition: transform 0.3s;
        margin-bottom: 20px;
    }
    .project-card:hover {
        transform: translateY(-5px);
    }
    .cover-image {
        height: 200px;
        object-fit: cover;
    }
    .status-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1;
    }
    .category-item {
        transition: all 0.3s;
    }
    .category-item:hover {
        background: #f8f9fa;
        transform: translateX(5px);
    }
</style>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card-modern">
            <div class="card-header-modern d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Projects Dashboard</h5>
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                    <i class="fas fa-plus me-2"></i>Create New Project
                </button>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" id="projectTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#allProjects" type="button" role="tab">
                            All Projects (<?php echo count($projects); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#published" type="button" role="tab">
                            Published
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#drafts" type="button" role="tab">
                            Drafts
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab">
                            Manage Categories (<?php echo count($categories); ?>)
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- All Projects Tab -->
                    <div class="tab-pane fade show active" id="allProjects">
                        <div class="row">
                            <?php if (empty($projects)): ?>
                                <div class="col-12">
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No projects yet. Click "Create New Project" to get started.
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($projects as $project): ?>
                                <div class="col-md-4">
                                    <div class="card project-card">
                                        <?php if ($project['cover_image']): ?>
                                            <img src="<?php echo $project['cover_image']; ?>" class="card-img-top cover-image" alt="<?php echo $project['small_title']; ?>">
                                        <?php else: ?>
                                            <div class="card-img-top cover-image bg-secondary d-flex align-items-center justify-content-center">
                                                <i class="fas fa-image fa-3x text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="status-badge">
                                            <span class="badge bg-<?php echo $project['status'] == 'published' ? 'success' : ($project['status'] == 'draft' ? 'warning' : 'secondary'); ?>">
                                                <?php echo ucfirst($project['status']); ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($project['small_title']); ?></h5>
                                            <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($project['major_title']); ?></h6>
                                            <p class="card-text small"><?php echo substr(htmlspecialchars($project['description']), 0, 100); ?>...</p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-folder"></i> <?php echo $project['category_name']; ?>
                                                </small>
                                                <small class="text-muted">
                                                    <i class="fas fa-eye"></i> <?php echo $project['view_count']; ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <a href="view.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View/Edit
                                            </a>
                                            <button onclick="deleteProject(<?php echo $project['id']; ?>)" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Published Projects Tab -->
                    <div class="tab-pane fade" id="published">
                        <div class="row">
                            <?php 
                            $published = array_filter($projects, function($p) { return $p['status'] == 'published'; });
                            foreach ($published as $project): 
                            ?>
                            <div class="col-md-4">
                                <div class="card project-card">
                                    <!-- Same card structure as above -->
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($project['small_title']); ?></h5>
                                        <p class="card-text"><?php echo substr(htmlspecialchars($project['description']), 0, 100); ?>...</p>
                                    </div>
                                    <div class="card-footer">
                                        <a href="view.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Drafts Tab -->
                    <div class="tab-pane fade" id="drafts">
                        <div class="row">
                            <?php 
                            $drafts = array_filter($projects, function($p) { return $p['status'] == 'draft'; });
                            foreach ($drafts as $project): 
                            ?>
                            <div class="col-md-4">
                                <div class="card project-card">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($project['small_title']); ?></h5>
                                        <p class="card-text"><?php echo substr(htmlspecialchars($project['description']), 0, 100); ?>...</p>
                                    </div>
                                    <div class="card-footer">
                                        <a href="view.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">Edit Draft</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Categories Management Tab -->
                    <div class="tab-pane fade" id="categories">
                        <div class="row">
                            <div class="col-md-5 mb-4">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">Create New Category</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label class="form-label">Category Name *</label>
                                                <input type="text" name="category_name" class="form-control" required 
                                                       placeholder="e.g., Web Development">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description (Optional)</label>
                                                <textarea name="category_description" class="form-control" rows="3" 
                                                          placeholder="Brief description of this category"></textarea>
                                            </div>
                                            <button type="submit" name="create_category" class="btn btn-primary w-100">
                                                <i class="fas fa-plus-circle me-2"></i>Create Category
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-7">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0">Your Categories</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($categories)): ?>
                                            <p class="text-muted text-center">No categories yet. Create your first category above.</p>
                                        <?php else: ?>
                                            <div class="list-group">
                                                <?php foreach ($categories as $category): ?>
                                                <div class="list-group-item category-item">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="flex-grow-1">
                                                            <strong><?php echo htmlspecialchars($category['category_name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo $category['project_count']; ?> projects | 
                                                                Created: <?php echo date('M d, Y', strtotime($category['created_at'])); ?>
                                                            </small>
                                                            <?php if ($category['category_description']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($category['category_description'], 0, 100)); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <button class="btn btn-sm btn-warning me-1" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['category_name']); ?>', '<?php echo htmlspecialchars($category['category_description']); ?>')">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>, <?php echo $category['project_count']; ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Project Modal -->
<div class="modal fade" id="createProjectModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['category_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Need a new category? Go to "Manage Categories" tab first.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="draft">Draft (Not visible to public)</option>
                                <option value="published">Published (Visible to public)</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Small Title *</label>
                        <input type="text" name="small_title" class="form-control" required placeholder="Short catchy title">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Major Title *</label>
                        <input type="text" name="major_title" class="form-control" required placeholder="Main project title">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cover Image</label>
                        <input type="file" name="cover_image" class="form-control" accept="image/*">
                        <small class="text-muted">Recommended size: 800x600px</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="5" placeholder="Detailed project description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tags (comma separated)</label>
                        <input type="text" name="tags" class="form-control" placeholder="e.g., web development, react, php">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_project" class="btn btn-primary">Create Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit Category
function editCategory(id, name, description) {
    Swal.fire({
        title: 'Edit Category',
        html: `
            <input id="category_name" class="swal2-input" placeholder="Category Name" value="${name}">
            <textarea id="category_description" class="swal2-textarea" placeholder="Description">${description || ''}</textarea>
        `,
        showCancelButton: true,
        confirmButtonText: 'Update',
        preConfirm: () => {
            const category_name = document.getElementById('category_name').value;
            const category_description = document.getElementById('category_description').value;
            if (!category_name) {
                Swal.showValidationMessage('Category name is required');
                return false;
            }
            return { category_name, category_description };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="category_id" value="${id}">
                <input type="hidden" name="category_name" value="${result.value.category_name}">
                <input type="hidden" name="category_description" value="${result.value.category_description}">
                <input type="hidden" name="update_category" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Delete Category
function deleteCategory(id, projectCount) {
    let warningText = "This category will be permanently deleted!";
    if (projectCount > 0) {
        warningText = `This category has ${projectCount} project(s). You cannot delete it until you delete or move these projects!`;
    }
    
    Swal.fire({
        title: 'Are you sure?',
        text: warningText,
        icon: 'warning',
        showCancelButton: projectCount == 0,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: projectCount == 0 ? 'Yes, delete it!' : 'OK'
    }).then((result) => {
        if (result.isConfirmed && projectCount == 0) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="category_id" value="${id}">
                <input type="hidden" name="delete_category" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Delete Project
function deleteProject(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This will delete the project and all its galleries/videos!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="project_id" value="${id}">
                <input type="hidden" name="delete_project" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Show messages
<?php if ($message): ?>
Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?php echo addslashes($message); ?>',
    timer: 3000,
    showConfirmButton: false
});
<?php endif; ?>

<?php if ($error): ?>
Swal.fire({
    icon: 'error',
    title: 'Error!',
    text: '<?php echo addslashes($error); ?>',
    timer: 3000,
    showConfirmButton: false
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/admin/layout.php';
?>