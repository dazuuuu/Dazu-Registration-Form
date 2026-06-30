<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/TestimonialController.php';

$testimonialController = new TestimonialController($pdo);
$page_title = 'Testimonials Management';
$breadcrumbs = [['label' => 'Testimonials', 'active' => true]];

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $result = null;
    
    switch ($_GET['action']) {
        case 'approve':
            $result = $testimonialController->approve($id);
            break;
        case 'reject':
            $result = $testimonialController->reject($id);
            break;
        case 'delete':
            $result = $testimonialController->delete($id);
            break;
        case 'feature':
            $result = $testimonialController->toggleFeatured($id);
            break;
    }
    
    if ($result && isset($result['message'])) {
        $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
    }
    header('Location: index.php');
    exit();
}

// Get filter
$status = $_GET['status'] ?? 'all';
$testimonials = $testimonialController->getAll($status, 50, 0);
$counts = $testimonialController->getCounts();
$stats = $testimonialController->getStats();

ob_start();
?>

<style>
.testimonial-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: box-shadow 0.2s ease;
}
.testimonial-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}
.testimonial-header {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.testimonial-body {
    padding: 20px;
}
.testimonial-rating {
    color: #f39c12;
    margin-bottom: 10px;
}
.testimonial-text {
    color: #555;
    line-height: 1.6;
    margin-bottom: 15px;
}
.testimonial-meta {
    font-size: 13px;
    color: #888;
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.status-pending { background: #f39c12; color: white; }
.status-approved { background: #27ae60; color: white; }
.status-rejected { background: #e74c3c; color: white; }
.featured-badge {
    background: #0D9488;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}
.btn-action {
    padding: 4px 10px;
    font-size: 12px;
    margin: 2px;
}
</style>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <div class="btn-group" role="group">
                    <a href="?status=all" class="btn btn-sm <?php echo $status === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        All <span class="badge bg-secondary"><?php echo $counts['total']; ?></span>
                    </a>
                    <a href="?status=pending" class="btn btn-sm <?php echo $status === 'pending' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        Pending <span class="badge bg-warning"><?php echo $counts['pending']; ?></span>
                    </a>
                    <a href="?status=approved" class="btn btn-sm <?php echo $status === 'approved' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        Approved <span class="badge bg-success"><?php echo $counts['approved']; ?></span>
                    </a>
                    <a href="?status=rejected" class="btn btn-sm <?php echo $status === 'rejected' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        Rejected <span class="badge bg-danger"><?php echo $counts['rejected']; ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-6">
                        <h4><?php echo $stats['average_rating']; ?></h4>
                        <small>Average Rating</small>
                    </div>
                    <div class="col-6">
                        <h4><?php echo $stats['total_testimonials']; ?></h4>
                        <small>Total Approved</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">All Testimonials</h5>
        <a href="/Realestate/public/feedback/" class="btn btn-sm btn-primary" target="_blank">
            <i class="fas fa-external-link-alt"></i> View Feedback Page
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($testimonials)): ?>
            <div class="text-center py-5">
                <i class="fas fa-comment-dots fa-3x text-muted mb-3"></i>
                <p>No testimonials found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="testimonial-card">
                    <div class="testimonial-header">
                        <div>
                            <strong><?php echo htmlspecialchars($testimonial['customer_name']); ?></strong>
                            <?php if ($testimonial['role']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($testimonial['role']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($testimonial['is_featured']): ?>
                                <span class="featured-badge me-2"><i class="fas fa-star"></i> Featured</span>
                            <?php endif; ?>
                            <span class="status-badge status-<?php echo $testimonial['status']; ?>">
                                <?php echo ucfirst($testimonial['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="testimonial-body">
                        <div class="testimonial-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= $testimonial['rating'] ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="testimonial-text">
                            "<?php echo htmlspecialchars($testimonial['testimonial_text']); ?>"
                        </div>
                        <div class="testimonial-meta">
                            <?php if ($testimonial['service_tag']): ?>
                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($testimonial['service_tag']); ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($testimonial['created_at'])); ?></span>
                            <?php if ($testimonial['customer_email']): ?>
                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($testimonial['customer_email']); ?></span>
                            <?php endif; ?>
                            <?php if ($testimonial['customer_phone']): ?>
                                <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($testimonial['customer_phone']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3">
                            <?php if ($testimonial['status'] === 'pending'): ?>
                                <a href="?action=approve&id=<?php echo $testimonial['id']; ?>" class="btn btn-sm btn-success btn-action" onclick="return confirm('Approve this testimonial?')">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                                <a href="?action=reject&id=<?php echo $testimonial['id']; ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Reject this testimonial?')">
                                    <i class="fas fa-times"></i> Reject
                                </a>
                            <?php endif; ?>
                            <?php if ($testimonial['status'] === 'approved'): ?>
                                <a href="?action=feature&id=<?php echo $testimonial['id']; ?>" class="btn btn-sm btn-warning btn-action">
                                    <i class="fas fa-star"></i> <?php echo $testimonial['is_featured'] ? 'Unfeature' : 'Feature'; ?>
                                </a>
                            <?php endif; ?>
                            <a href="edit.php?id=<?php echo $testimonial['id']; ?>" class="btn btn-sm btn-primary btn-action">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="?action=delete&id=<?php echo $testimonial['id']; ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Delete this testimonial?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>