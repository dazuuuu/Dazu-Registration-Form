<?php
// public/admin/projects/view.php (COMPLETE FIXED VERSION)
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';
require_once __DIR__ . '/../../../app/controllers/ProjectController.php';

if (!isLoggedIn()) {
    redirect('/Realestate/public/admin/portal.php');
}

$role_id = $_SESSION['role_id'] ?? 3;
if ($role_id > 2) {
    redirect('/Realestate/public/auth/login.php');
}

$controller = new ProjectController($pdo);
$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    redirect('index.php');
}

$project = $controller->getProject($project_id);
if (!$project) {
    redirect('index.php');
}

$gallery = $controller->getGalleryImages($project_id);
$videos = $controller->getVideos($project_id);
$tags = $controller->getProjectTags($project_id);

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_project'])) {
        // This saves all project data
        $result = $controller->updateProject($project_id, $_POST, $_FILES);
        if ($result['success']) {
            $message = $result['message'];
            $project = $controller->getProject($project_id);
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['publish_project'])) {
        // This just changes status to published, keeping all other data
        $result = $controller->publishProject($project_id);
        if ($result['success']) {
            $message = $result['message'];
            $project = $controller->getProject($project_id);
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['save_draft'])) {
        // This just changes status to draft, keeping all other data
        $result = $controller->saveAsDraft($project_id);
        if ($result['success']) {
            $message = $result['message'];
            $project = $controller->getProject($project_id);
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['delete_project'])) {
        $result = $controller->deleteProject($project_id);
        if ($result['success']) {
            header('Location: index.php');
            exit();
        }
    } elseif (isset($_POST['add_video'])) {
        $data = [
            'project_id' => $project_id,
            'video_title' => $_POST['video_title'],
            'video_url' => $_POST['video_url'],
            'video_type' => $_POST['video_type'],
            'sort_order' => 0
        ];
        $result = $controller->addVideo($project_id, $data);
        if ($result['success']) {
            $message = $result['message'];
            $videos = $controller->getVideos($project_id);
        } else {
            $error = $result['message'];
        }
    }
}

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    if (isset($_GET['delete_gallery'])) {
        $result = $controller->deleteGalleryImage($_GET['delete_gallery']);
        echo json_encode($result);
        exit();
    }
    if (isset($_GET['delete_video'])) {
        $result = $controller->deleteVideo($_GET['delete_video']);
        echo json_encode($result);
        exit();
    }
}

$categories = $controller->getCategories();

$page_title = $project['small_title'] . ' - Edit Project';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/Realestate/public/admin/dashboard.php'],
    ['label' => 'Projects', 'url' => 'index.php'],
    ['label' => $project['small_title'], 'active' => true]
];

ob_start();
?>

<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.js"></script>

<style>
    .gallery-item {
        position: relative;
        margin-bottom: 20px;
        cursor: pointer;
    }
    .gallery-item img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 8px;
    }
    .gallery-item .delete-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(220,53,69,0.9);
        color: white;
        border: none;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
    }
    .gallery-item .delete-btn:hover {
        background: #dc3545;
        transform: scale(1.1);
    }
    .video-item {
        margin-bottom: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #667eea;
    }
    .dropzone {
        border: 2px dashed #667eea;
        border-radius: 8px;
        background: #f8f9fa;
        min-height: 150px;
    }
    .cover-preview {
        max-width: 200px;
        margin-top: 10px;
    }
    .section-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
    }
    .action-buttons {
        position: sticky;
        top: 20px;
        z-index: 100;
        background: white;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .status-badge-header {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-left: 10px;
    }
    .status-published {
        background: #d4edda;
        color: #155724;
    }
    .status-draft {
        background: #fff3cd;
        color: #856404;
    }
    .status-archived {
        background: #f8d7da;
        color: #721c24;
    }
</style>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card-modern">
            <div class="card-header-modern d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">
                        Edit Project: <?php echo htmlspecialchars($project['small_title']); ?>
                        <span class="status-badge-header status-<?php echo $project['status']; ?>">
                            <?php echo ucfirst($project['status']); ?>
                        </span>
                    </h5>
                </div>
                <div>
                    <a href="index.php" class="btn btn-light me-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Projects
                    </a>
                    <button onclick="deleteProject()" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Project
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Action Buttons - Separate forms for each action -->
                <div class="action-buttons">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Project Status:</strong> 
                        <?php if ($project['status'] == 'published'): ?>
                            This project is <strong class="text-success">PUBLISHED</strong> and visible to the public.
                        <?php elseif ($project['status'] == 'draft'): ?>
                            This project is in <strong class="text-warning">DRAFT</strong> mode and not visible to the public.
                        <?php else: ?>
                            This project is <strong class="text-danger">ARCHIVED</strong> and not visible to the public.
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <!-- Save as Draft Form -->
                        <form method="POST" style="flex: 1;">
                            <button type="submit" name="save_draft" class="btn btn-warning btn-lg w-100">
                                <i class="fas fa-save me-2"></i>Save as Draft
                            </button>
                        </form>
                        
                        <!-- Publish Project Form -->
                        <form method="POST" style="flex: 1;">
                            <button type="submit" name="publish_project" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-globe me-2"></i>Publish Project
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Main Edit Form -->
                <form method="POST" enctype="multipart/form-data" id="projectForm">
                    <!-- Basic Information Section -->
                    <div class="section-title">
                        <i class="fas fa-info-circle me-2"></i>Basic Information
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $project['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="draft" <?php echo $project['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo $project['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                                <option value="archived" <?php echo $project['status'] == 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                            <small class="text-muted">You can also use the buttons above to quickly change status</small>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Small Title *</label>
                            <input type="text" name="small_title" class="form-control" value="<?php echo htmlspecialchars($project['small_title']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Major Title *</label>
                            <input type="text" name="major_title" class="form-control" value="<?php echo htmlspecialchars($project['major_title']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Cover Image</label>
                        <input type="file" name="cover_image" class="form-control" accept="image/*" id="coverImage">
                        <?php if ($project['cover_image']): ?>
                        <div class="cover-preview">
                            <img src="<?php echo $project['cover_image']; ?>" class="img-fluid rounded mt-2" style="max-width: 200px;">
                            <small class="text-muted d-block">Current cover image (upload new to replace)</small>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="8"><?php echo htmlspecialchars($project['description']); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Tags (comma separated)</label>
                        <?php
                        $tag_string = implode(', ', array_column($tags, 'tag_name'));
                        ?>
                        <input type="text" name="tags" class="form-control" value="<?php echo htmlspecialchars($tag_string); ?>" placeholder="e.g., web, design, development">
                        <small class="text-muted">Tags help organize and search projects</small>
                    </div>
                    
                    <button type="submit" name="update_project" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-save me-2"></i>Save All Changes
                    </button>
                </form>
                
                <!-- Gallery Section -->
                <div class="section-title mt-5">
                    <i class="fas fa-images me-2"></i>Project Gallery (Multiple Images)
                </div>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <form action="/Realestate/public/api/projects.php?action=upload_gallery" method="POST" class="dropzone" id="galleryDropzone">
                                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                    <div class="dz-message">
                                        <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: #667eea;"></i>
                                        <h6>Drag & Drop images here or click to upload</h6>
                                        <small class="text-muted">Supported formats: JPG, PNG, GIF, WEBP (Max 5MB per image)</small>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row" id="galleryContainer">
                    <?php if (empty($gallery)): ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                No images uploaded yet. Use the dropzone above to add images.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($gallery as $image): ?>
                        <div class="col-md-3 gallery-item" data-id="<?php echo $image['id']; ?>">
                            <img src="<?php echo $image['image_path']; ?>" class="img-fluid" alt="Gallery Image">
                            <button class="delete-btn" onclick="deleteGalleryImage(<?php echo $image['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
<!-- In public/projects/readmore.php, replace the video section with this -->

<!-- Videos Section -->
<?php if (!empty($videos)): ?>
    <div class="video-section mt-4 mb-4">
        <div class="container px-0">
            <h3 class="mb-4"><i class="fas fa-video me-2"></i>Project Videos</h3>
            <div class="row">
                <?php foreach ($videos as $video): ?>
                    <div class="col-md-6 mb-4">
                        <div class="video-card">
                            <div class="video-container">
                                <?php 
                                // Generate proper embed code for YouTube
                                if ($video['video_type'] == 'youtube') {
                                    // Extract YouTube video ID
                                    $video_id = '';
                                    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $video['video_url'], $matches)) {
                                        $video_id = $matches[1];
                                    }
                                    if ($video_id) {
                                        echo '<iframe width="100%" height="315" src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                                    } else {
                                        echo '<div class="alert alert-warning">Invalid YouTube URL</div>';
                                    }
                                } 
                                elseif ($video['video_type'] == 'vimeo') {
                                    // Extract Vimeo video ID
                                    $video_id = '';
                                    if (preg_match('/vimeo\.com\/(\d+)/', $video['video_url'], $matches)) {
                                        $video_id = $matches[1];
                                    }
                                    if ($video_id) {
                                        echo '<iframe src="https://player.vimeo.com/video/' . $video_id . '" width="100%" height="315" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
                                    } else {
                                        echo '<div class="alert alert-warning">Invalid Vimeo URL</div>';
                                    }
                                }
                                else {
                                    // For other video types, try to embed directly
                                    echo $video['video_embed_code'];
                                }
                                ?>
                            </div>
                            <?php if ($video['video_title']): ?>
                                <div class="video-card-body">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($video['video_title']); ?></h6>
                                    <small class="text-muted"><?php echo ucfirst($video['video_type']); ?> Video</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize Dropzone for gallery - FIXED VERSION
Dropzone.autoDiscover = false;

var galleryDropzone = new Dropzone("#galleryDropzone", {
    url: "/Realestate/public/api/projects.php?action=upload_gallery",
    params: {
        project_id: <?php echo $project_id; ?>
    },
    maxFilesize: 5,
    acceptedFiles: 'image/jpeg,image/png,image/gif,image/webp',
    dictDefaultMessage: "<i class='fas fa-cloud-upload-alt fa-3x mb-3'></i><br>Drag & Drop images here or click to upload",
    addRemoveLinks: true,
    dictRemoveFile: "Remove",
    success: function(file, response) {
        try {
            const result = JSON.parse(response);
            if (result.success) {
                // Reload the page to show new image
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                Swal.fire('Error', result.message, 'error');
                this.removeFile(file);
            }
        } catch(e) {
            Swal.fire('Error', 'Failed to upload image', 'error');
            this.removeFile(file);
        }
    },
    error: function(file, errorMessage) {
        Swal.fire('Upload Failed', errorMessage, 'error');
        this.removeFile(file);
    }
});

// Delete Gallery Image
function deleteGalleryImage(id) {
    Swal.fire({
        title: 'Delete Image?',
        text: "This image will be permanently removed from the gallery!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`?ajax=1&delete_gallery=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
        }
    });
}

// Delete Video
function deleteVideo(id) {
    Swal.fire({
        title: 'Remove Video?',
        text: "This video will be removed from the project!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`?ajax=1&delete_video=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
        }
    });
}

// Delete Project
function deleteProject() {
    Swal.fire({
        title: 'Delete Entire Project?',
        text: "This will delete the project, all gallery images, and all videos! This cannot be undone!",
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete everything!'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="delete_project" value="1">';
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