<?php
// public/client/dashboard/index.php
$page_title = 'Dashboard';
$page_header = 'Welcome Back!';
$page_subheader = 'Here\'s what\'s happening with your account';

ob_start();

require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

// Check if logged in
if (!isLoggedIn()) {
    redirect('/Realestate/public/auth/login.php');
}

// Check if user is client (role_id 3)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
    redirect('/Realestate/public/auth/login.php');
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];

// Get user profile
$stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$profile = $stmt->fetch();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM projects WHERE created_by = :user_id");
$stmt->execute([':user_id' => $user_id]);
$userProjects = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE status = 'published'");
$stmt->execute();
$totalServices = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM projects WHERE status = 'published'");
$stmt->execute();
$totalProjects = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM blogs WHERE status = 'published'");
$stmt->execute();
$totalBlogs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent projects (if any)
$stmt = $pdo->prepare("SELECT * FROM projects WHERE created_by = :user_id ORDER BY created_at DESC LIMIT 5");
$stmt->execute([':user_id' => $user_id]);
$recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current date time
$currentHour = date('H');
$greeting = '';
if ($currentHour < 12) {
    $greeting = 'Good Morning';
} elseif ($currentHour < 18) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}
?>

<style>
    .welcome-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 25px;
        color: #fff;
        margin-bottom: 25px;
    }
    .welcome-card h2 {
        font-size: 1.5rem;
        margin-bottom: 8px;
    }
    .stat-card {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        transition: all 0.2s;
        margin-bottom: 20px;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: #667eea;
    }
    .stat-label {
        color: #666;
        font-size: 0.85rem;
        margin-top: 5px;
    }
    .quick-action {
        text-align: center;
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        transition: all 0.2s;
        text-decoration: none;
        display: block;
        background: #fff;
    }
    .quick-action:hover {
        transform: translateY(-3px);
        border-color: #667eea;
    }
    .quick-action i {
        font-size: 28px;
        color: #667eea;
        margin-bottom: 10px;
    }
    .quick-action span {
        display: block;
        color: #1a1a1a;
        font-size: 0.9rem;
    }
    .recent-item {
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .recent-item:last-child {
        border-bottom: none;
    }
</style>

<div class="welcome-card">
    <h2><?php echo $greeting . ', ' . htmlspecialchars($username); ?>!</h2>
    <p class="mb-0 opacity-75">Welcome to your dashboard. Here's an overview of your activity.</p>
</div>

<!-- Statistics Row -->
<div class="row">
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-number"><?php echo $userProjects; ?></div>
            <div class="stat-label">Your Projects</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalServices; ?></div>
            <div class="stat-label">Total Services</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalProjects; ?></div>
            <div class="stat-label">Total Projects</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalBlogs; ?></div>
            <div class="stat-label">Blog Posts</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card-modern mt-2">
    <div class="card-header-modern">
        <i class="fas fa-bolt me-2"></i> Quick Actions
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-4 col-md-3 mb-3">
                <a href="/Realestate/public/contact.php" class="quick-action">
                    <i class="fas fa-envelope"></i>
                    <span>Contact Us</span>
                </a>
            </div>
            <div class="col-4 col-md-3 mb-3">
                <a href="/Realestate/public/projects/" class="quick-action">
                    <i class="fas fa-folder-open"></i>
                    <span>Browse Projects</span>
                </a>
            </div>
            <div class="col-4 col-md-3 mb-3">
                <a href="/Realestate/public/services/" class="quick-action">
                    <i class="fas fa-cogs"></i>
                    <span>Our Services</span>
                </a>
            </div>
            <div class="col-4 col-md-3 mb-3">
                <a href="/Realestate/public/blogs/" class="quick-action">
                    <i class="fas fa-blog"></i>
                    <span>Read Blog</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card-modern mt-3">
    <div class="card-header-modern">
        <i class="fas fa-history me-2"></i> Recent Activity
    </div>
    <div class="card-body">
        <?php if (empty($recentProjects)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">No recent projects found.</p>
                <a href="/Realestate/public/contact.php" class="btn btn-primary">Start a Project</a>
            </div>
        <?php else: ?>
            <?php foreach ($recentProjects as $project): ?>
                <div class="recent-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?php echo htmlspecialchars($project['small_title']); ?></strong>
                        <br>
                        <small class="text-muted"><?php echo date('M d, Y', strtotime($project['created_at'])); ?></small>
                    </div>
                    <a href="/Realestate/public/projects/readmore.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-primary">
                        View <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/client/layout.php';
?>