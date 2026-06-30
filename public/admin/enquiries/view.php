<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/EnquiryController.php';

$enquiryController = new EnquiryController($pdo);
$page_title = 'View Enquiry';
$breadcrumbs = [
    ['label' => 'Enquiries', 'url' => 'index.php'],
    ['label' => 'View', 'active' => true]
];

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit();
}

// Handle status update
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_status':
            $result = $enquiryController->updateStatus($id, $_POST['status']);
            $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
            break;
        case 'add_reply':
            $result = $enquiryController->addReply($id, $_POST['reply']);
            $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
            break;
        case 'add_note':
            $result = $enquiryController->addNote($id, $_POST['note']);
            $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
            break;
    }
    header('Location: view.php?id=' . $id);
    exit();
}

// Handle delete
if (isset($_GET['delete']) && $_GET['delete'] == 'true') {
    $result = $enquiryController->delete($id);
    $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
    header('Location: index.php');
    exit();
}

// Mark as read when viewed
$enquiryController->markAsRead($id);
$enquiry = $enquiryController->getById($id);

if (!$enquiry) {
    header('Location: index.php');
    exit();
}

ob_start();
?>

<style>
.enquiry-detail-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    margin-bottom: 24px;
    overflow: hidden;
}
.enquiry-detail-header {
    background: #f8f9fa;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
}
.enquiry-info-row {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
}
.enquiry-label {
    width: 120px;
    font-weight: 600;
    color: #666;
}
.enquiry-value {
    flex: 1;
    color: #333;
}
.reply-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}
.reply-meta {
    font-size: 12px;
    color: #666;
    margin-bottom: 8px;
}
.reply-content {
    color: #333;
    line-height: 1.6;
}
.status-badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}
</style>

<div class="row">
    <div class="col-md-8">
        <!-- Enquiry Details -->
        <div class="enquiry-detail-card">
            <div class="enquiry-detail-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Enquiry #<?php echo $enquiry['id']; ?></h5>
                <div>
                    <span class="status-badge status-<?php echo $enquiry['status']; ?>">
                        <?php echo ucfirst($enquiry['status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="enquiry-info-row">
                <div class="enquiry-label">Date Submitted:</div>
                <div class="enquiry-value">
                    <?php echo date('F d, Y g:i A', strtotime($enquiry['created_at'])); ?>
                </div>
            </div>
            
            <div class="enquiry-info-row">
                <div class="enquiry-label">Name:</div>
                <div class="enquiry-value"><?php echo htmlspecialchars($enquiry['name']); ?></div>
            </div>
            
            <div class="enquiry-info-row">
                <div class="enquiry-label">Email:</div>
                <div class="enquiry-value">
                    <a href="mailto:<?php echo htmlspecialchars($enquiry['email']); ?>">
                        <?php echo htmlspecialchars($enquiry['email']); ?>
                    </a>
                </div>
            </div>
            
            <div class="enquiry-info-row">
                <div class="enquiry-label">Phone:</div>
                <div class="enquiry-value">
                    <a href="tel:<?php echo htmlspecialchars($enquiry['phone']); ?>">
                        <?php echo htmlspecialchars($enquiry['phone']); ?>
                    </a>
                </div>
            </div>
            
            <div class="enquiry-info-row">
                <div class="enquiry-label">Service:</div>
                <div class="enquiry-value"><?php echo htmlspecialchars($enquiry['service'] ?: 'Not specified'); ?></div>
            </div>
            
            <div class="enquiry-info-row">
                <div class="enquiry-label">Priority:</div>
                <div class="enquiry-value">
                    <select id="prioritySelect" class="form-select form-select-sm" style="width: 120px;">
                        <option value="low" <?php echo $enquiry['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $enquiry['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $enquiry['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                    </select>
                </div>
            </div>
            
            <div class="enquiry-info-row">
                <div class="enquiry-label">Message:</div>
                <div class="enquiry-value">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 5px;">
                        <?php echo nl2br(htmlspecialchars($enquiry['message'])); ?>
                    </div>
                </div>
            </div>
            
            <?php if ($enquiry['notes']): ?>
            <div class="enquiry-info-row">
                <div class="enquiry-label">Internal Notes:</div>
                <div class="enquiry-value">
                    <div style="background: #fff8e1; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 13px;">
                        <?php echo nl2br(htmlspecialchars($enquiry['notes'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Replies Section -->
        <div class="enquiry-detail-card">
            <div class="enquiry-detail-header">
                <h5 class="mb-0">Replies</h5>
            </div>
            <div class="p-3">
                <?php if (empty($enquiry['replies'])): ?>
                    <p class="text-muted text-center py-3">No replies yet.</p>
                <?php else: ?>
                    <?php foreach ($enquiry['replies'] as $reply): ?>
                        <div class="reply-item">
                            <div class="reply-meta">
                                <strong><?php echo htmlspecialchars($reply['admin_name']); ?></strong> 
                                on <?php echo date('F d, Y g:i A', strtotime($reply['created_at'])); ?>
                            </div>
                            <div class="reply-content">
                                <?php echo nl2br(htmlspecialchars($reply['reply'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Add Reply Form -->
                <form method="POST" class="mt-4">
                    <input type="hidden" name="action" value="add_reply">
                    <div class="mb-3">
                        <label class="form-label">Add Reply</label>
                        <textarea name="reply" class="form-control" rows="4" required 
                                  placeholder="Type your reply here..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Status Update -->
        <div class="enquiry-detail-card">
            <div class="enquiry-detail-header">
                <h5 class="mb-0">Update Status</h5>
            </div>
            <div class="p-3">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <div class="mb-3">
                        <select name="status" class="form-select">
                            <option value="new" <?php echo $enquiry['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="read" <?php echo $enquiry['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                            <option value="contacted" <?php echo $enquiry['status'] === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                            <option value="closed" <?php echo $enquiry['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Status</button>
                </form>
            </div>
        </div>
        
        <!-- Add Internal Note -->
        <div class="enquiry-detail-card">
            <div class="enquiry-detail-header">
                <h5 class="mb-0">Internal Note</h5>
            </div>
            <div class="p-3">
                <form method="POST">
                    <input type="hidden" name="action" value="add_note">
                    <div class="mb-3">
                        <textarea name="note" class="form-control" rows="4" required 
                                  placeholder="Add private note (only admins can see)..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary w-100">Add Note</button>
                </form>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="enquiry-detail-card">
            <div class="enquiry-detail-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="p-3">
                <a href="mailto:<?php echo htmlspecialchars($enquiry['email']); ?>" class="btn btn-outline-primary w-100 mb-2">
                    <i class="fas fa-envelope"></i> Send Email
                </a>
                <a href="tel:<?php echo htmlspecialchars($enquiry['phone']); ?>" class="btn btn-outline-success w-100 mb-2">
                    <i class="fas fa-phone"></i> Call Client
                </a>
                <a href="?delete=true" class="btn btn-outline-danger w-100" onclick="return confirm('Delete this enquiry?')">
                    <i class="fas fa-trash"></i> Delete Enquiry
                </a>
                <a href="index.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Update priority via AJAX
document.getElementById('prioritySelect')?.addEventListener('change', async function() {
    const priority = this.value;
    const id = <?php echo $enquiry['id']; ?>;
    
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
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>