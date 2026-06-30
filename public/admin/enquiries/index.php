<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/EnquiryController.php';

$enquiryController = new EnquiryController($pdo);
$page_title = 'Enquiries Management';
$breadcrumbs = [['label' => 'Enquiries', 'active' => true]];

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? null;
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get enquiries
$enquiries = $enquiryController->getAll($status, $limit, $offset, $search);
$counts = $enquiryController->getCounts();

ob_start();
?>

<style>
.enquiry-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.status-new { background: #e74c3c; color: white; }
.status-read { background: #f39c12; color: white; }
.status-contacted { background: #3498db; color: white; }
.status-closed { background: #27ae60; color: white; }
.priority-high { color: #e74c3c; }
.priority-medium { color: #f39c12; }
.priority-low { color: #27ae60; }
.enquiry-row-new { background-color: #fff5f5; }
.enquiry-row-read { background-color: #fffbf0; }
.enquiry-preview {
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <!-- Status Filters -->
                    <div class="col-md-8">
                        <div class="btn-group" role="group">
                            <a href="?status=all" class="btn btn-sm <?php echo $status === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                                All <span class="badge bg-secondary"><?php echo $counts['total']; ?></span>
                            </a>
                            <a href="?status=new" class="btn btn-sm <?php echo $status === 'new' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                                New <span class="badge bg-danger"><?php echo $counts['new']; ?></span>
                            </a>
                            <a href="?status=read" class="btn btn-sm <?php echo $status === 'read' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                                Read <span class="badge bg-warning"><?php echo $counts['read']; ?></span>
                            </a>
                            <a href="?status=contacted" class="btn btn-sm <?php echo $status === 'contacted' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                                Contacted <span class="badge bg-info"><?php echo $counts['contacted']; ?></span>
                            </a>
                            <a href="?status=closed" class="btn btn-sm <?php echo $status === 'closed' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                                Closed <span class="badge bg-success"><?php echo $counts['closed']; ?></span>
                            </a>
                        </div>
                    </div>
                    <!-- Search -->
                    <div class="col-md-4">
                        <form method="GET" class="d-flex">
                            <input type="hidden" name="status" value="<?php echo $status; ?>">
                            <input type="text" name="search" class="form-control form-control-sm" 
                                   placeholder="Search by name, email, phone..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-sm btn-primary ms-2">Search</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">All Enquiries</h5>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($enquiries)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p>No enquiries found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Name / Contact</th>
                            <th>Service</th>
                            <th>Message</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enquiries as $enquiry): ?>
                            <tr class="enquiry-row-<?php echo $enquiry['status']; ?>">
                                <td>#<?php echo $enquiry['id']; ?></td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($enquiry['created_at'])); ?>
                                    <br><small class="text-muted"><?php echo date('h:i A', strtotime($enquiry['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($enquiry['name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($enquiry['email']); ?></small>
                                    <br><small><?php echo htmlspecialchars($enquiry['phone']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($enquiry['service'] ?: 'Not specified'); ?></td>
                                <td class="enquiry-preview" title="<?php echo htmlspecialchars($enquiry['message']); ?>">
                                    <?php echo htmlspecialchars(substr($enquiry['message'], 0, 80)); ?>
                                    <?php echo strlen($enquiry['message']) > 80 ? '...' : ''; ?>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm priority-select" 
                                            data-id="<?php echo $enquiry['id']; ?>"
                                            style="width: 100px;">
                                        <option value="low" <?php echo $enquiry['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $enquiry['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $enquiry['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                    </select>
                                </td>
                                <td>
                                    <span class="enquiry-status status-<?php echo $enquiry['status']; ?>">
                                        <?php echo ucfirst($enquiry['status']); ?>
                                    </span>
                                    <?php if ($enquiry['reply_count'] > 0): ?>
                                        <br><small class="text-muted"><i class="fas fa-reply"></i> <?php echo $enquiry['reply_count']; ?> replies</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view.php?id=<?php echo $enquiry['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <button class="btn btn-sm btn-danger" onclick="deleteEnquiry(<?php echo $enquiry['id']; ?>)">
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
// Update priority via AJAX
document.querySelectorAll('.priority-select').forEach(select => {
    select.addEventListener('change', async function() {
        const id = this.dataset.id;
        const priority = this.value;
        
        const response = await fetch('/Realestate/public/api/enquiry/update_priority.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, priority: priority })
        });
        
        const data = await response.json();
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to update priority');
        }
    });
});

function deleteEnquiry(id) {
    if (confirm('Are you sure you want to delete this enquiry?')) {
        window.location.href = `?delete=${id}`;
    }
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>