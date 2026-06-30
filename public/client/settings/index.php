<?php
// public/client/settings/index.php
$page_title = 'Settings';
$page_header = 'Account Settings';
$page_subheader = 'Manage your account preferences';

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
$success = '';
$error = '';

// Handle notification preferences (example)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $project_updates = isset($_POST['project_updates']) ? 1 : 0;
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;
    
    // Save preferences (you'll need a user_settings table)
    // For now, we'll just show success message
    $success = 'Preferences saved successfully!';
}

// Handle account deletion request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $confirm = $_POST['confirm_delete'] ?? '';
    if ($confirm === 'DELETE') {
        // Delete user account (soft delete or permanent)
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        
        session_destroy();
        redirect('/Realestate/public/auth/login.php?deleted=1');
    } else {
        $error = 'Please type DELETE to confirm account deletion';
    }
}

// Set flash messages
if ($success) {
    $_SESSION['flash']['success'] = $success;
}
if ($error) {
    $_SESSION['flash']['error'] = $error;
}
?>

<style>
    .setting-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .setting-item:last-child {
        border-bottom: none;
    }
    .setting-info h6 {
        margin-bottom: 5px;
        font-weight: 600;
    }
    .setting-info p {
        margin-bottom: 0;
        font-size: 0.8rem;
        color: #666;
    }
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: 0.3s;
        border-radius: 24px;
    }
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
    }
    input:checked + .toggle-slider {
        background-color: #667eea;
    }
    input:checked + .toggle-slider:before {
        transform: translateX(26px);
    }
    .danger-zone {
        background: #fff5f5;
        border: 1px solid #feb2b2;
        border-radius: 12px;
        padding: 20px;
    }
</style>

<div class="row">
    <div class="col-md-8 mx-auto">
        <!-- Notification Preferences -->
        <div class="card-modern mb-4">
            <div class="card-header-modern">
                <i class="fas fa-bell me-2"></i> Notification Preferences
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h6>Email Notifications</h6>
                            <p>Receive email updates about your account activity</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_notifications" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h6>Project Updates</h6>
                            <p>Get notified when your project status changes</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="project_updates" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h6>Newsletter</h6>
                            <p>Receive company news and updates</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="newsletter">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="save_preferences" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Danger Zone -->
        <div class="card-modern">
            <div class="card-header-modern bg-danger text-white">
                <i class="fas fa-exclamation-triangle me-2"></i> Danger Zone
            </div>
            <div class="card-body">
                <div class="danger-zone">
                    <h6 class="mb-2">Delete Account</h6>
                    <p class="text-muted small mb-3">Once you delete your account, there is no going back. Please be certain.</p>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                        <i class="fas fa-trash me-2"></i> Delete Account
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to delete your account? This action cannot be undone.</p>
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> All your data will be permanently removed.
                    </div>
                    <div class="mb-3">
                        <label>Type <code>DELETE</code> to confirm</label>
                        <input type="text" name="confirm_delete" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_account" class="btn btn-danger">Delete Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/client/layout.php';
?>