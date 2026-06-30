<?php
// public/admin/blogs/index.php
$page_title = 'Manage Blog Posts';
$breadcrumbs = [
    ['label' => 'Blog', 'active' => true]
];

ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
    header('Location: /Realestate/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/models/BlogModel.php';

$blogModel = new BlogModel($pdo);
$blogs = $blogModel->getAll(null, 100);
$categories = $blogModel->getAllCategories();
?>

<style>
    /* DataTables overrides for black/white theme */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
        color: #1a1a1a;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #ccc;
        border-radius: 6px;
        padding: 5px 10px;
        margin-left: 8px;
    }
    
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #1a1a1a;
        outline: none;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 5px 12px;
        margin: 0 2px;
        border-radius: 6px;
        border: 1px solid #ccc;
        background: #fff;
        color: #1a1a1a;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #1a1a1a;
        border-color: #1a1a1a;
        color: #fff;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #333;
        border-color: #333;
        color: #fff;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
        opacity: 0.5;
    }
    
    .table {
        margin-bottom: 0;
    }
    
    .table thead th {
        background: #fafafa;
        border-bottom: 2px solid #e0e0e0;
        font-weight: 600;
        font-size: 0.85rem;
        color: #1a1a1a;
    }
    
    .table tbody td {
        vertical-align: middle;
        color: #1a1a1a;
    }
    
    .status-badge {
        display: inline-block;
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
    
    .badge-featured {
        background: #e0e0e0;
        color: #1a1a1a;
        padding: 3px 8px;
        font-size: 0.65rem;
        border-radius: 12px;
        margin-left: 5px;
    }
    
    .action-buttons {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .btn-icon i {
        font-size: 0.9rem;
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
    
    .create-btn {
        background: #1a1a1a;
        border: none;
        color: #fff;
        padding: 8px 20px;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .create-btn:hover {
        background: #333;
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
    
    .card-table {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .card-header-table {
        padding: 15px 20px;
        background: #fafafa;
        border-bottom: 1px solid #e0e0e0;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .action-buttons {
            flex-wrap: wrap;
        }
    }
</style>

<div class="page-header">
    <h2>Manage Blog Posts</h2>
    <a href="create.php" class="create-btn">
        <i class="fas fa-plus"></i> Create New Post
    </a>
</div>

<div class="card-table">
    <div class="card-header-table">
        <div class="row align-items-center">
            <div class="col">
                <strong><i class="fas fa-blog me-2"></i> All Blog Posts</strong>
            </div>
            <div class="col-auto">
                <span class="text-muted small">Total: <?php echo count($blogs); ?> posts</span>
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table id="blogsTable" class="table table-hover mb-0">
            <thead>
                <tr>
                    <th width="60">ID</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th width="120">Status</th>
                    <th width="80">Views</th>
                    <th width="110">Created</th>
                    <th width="140">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($blogs as $blog): ?>
                <tr>
                    <td><?php echo $blog['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($blog['title']); ?></strong>
                        <?php if ($blog['is_featured']): ?>
                            <span class="badge-featured"><i class="fas fa-star"></i> Featured</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($blog['category_name']): ?>
                            <span class="text-muted"><?php echo htmlspecialchars($blog['category_name']); ?></span>
                        <?php else: ?>
                            <span class="text-muted">Uncategorized</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo $blog['status']; ?>">
                            <?php echo ucfirst($blog['status']); ?>
                        </span>
                    </td>
                    <td>
                        <i class="fas fa-eye text-muted me-1"></i>
                        <?php echo number_format($blog['view_count']); ?>
                    </td>
                    <td>
                        <i class="fas fa-calendar-alt text-muted me-1"></i>
                        <?php echo date('M d, Y', strtotime($blog['created_at'])); ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="edit.php?id=<?php echo $blog['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button onclick="deleteBlog(<?php echo $blog['id']; ?>)" class="btn-icon btn-delete" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                            <a href="../../blogs/readmore.php?slug=<?php echo urlencode($blog['slug']); ?>" target="_blank" class="btn-icon btn-view" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Include jQuery and DataTables -->
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#blogsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            zeroRecords: "No matching records found"
        }
    });
});

async function deleteBlog(id) {
    if (confirm('Are you sure you want to delete this blog post? This action cannot be undone.')) {
        try {
            const response = await fetch('/Realestate/public/api/blog/delete.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            });
            const result = await response.json();
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
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