<?php
// Tabs across the intern admin pages. Expects $inTab.
$tabs = [
    'index' => ['All interns', 'admin/interns/index.php', 'fa-users'],
    'supervise' => ['My interns', 'admin/interns/supervise.php', 'fa-user-check'],
    'learning' => ['Learning', 'admin/interns/learning.php', 'fa-book-open'],
    'departments' => ['Departments', 'admin/interns/departments.php', 'fa-sitemap'],
    'settings' => ['Rules & settings', 'admin/interns/settings.php', 'fa-sliders'],
];
?>
<style>
.in-tabs{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1rem}
.in-tabs a{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .9rem;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#334155;font-size:.86rem;font-weight:600;text-decoration:none}
.in-tabs a.on{background:#44A024;border-color:#44A024;color:#fff}
.in-pill{display:inline-block;padding:.18rem .6rem;border-radius:999px;font-size:.72rem;font-weight:700}
.in-pill.active,.in-pill.approved,.in-pill.completed,.in-pill.on_time,.in-pill.released{background:#e7f4e1;color:#2f7a14}
.in-pill.pending,.in-pill.submitted,.in-pill.late,.in-pill.draft,.in-pill.in_progress{background:#fff4e0;color:#b45309}
.in-pill.rejected,.in-pill.revision,.in-pill.absent,.in-pill.withdrawn,.in-pill.suspended,.in-pill.high{background:#fde8e6;color:#b42318}
.in-pill.todo,.in-pill.normal,.in-pill.early_out{background:#eef2f7;color:#334155}
.in-pill.medium{background:#fff4e0;color:#b45309}
.in-card{background:#fff;border-radius:16px;box-shadow:0 3px 14px rgba(20,35,25,.07);padding:18px;margin-bottom:16px}
.in-card h5{font-size:1rem;font-weight:700;margin-bottom:12px}
</style>
<div class="in-tabs">
    <?php foreach ($tabs as $k => [$label, $href, $icon]): ?>
        <a href="<?php echo url($href); ?>" class="<?php echo $inTab === $k ? 'on' : ''; ?>"><i class="fas <?php echo $icon; ?>"></i><?php echo $label; ?></a>
    <?php endforeach; ?>
</div>
<?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $key => $cls): if (isset($_SESSION[$key])): ?>
    <div class="alert alert-<?php echo $cls; ?> alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION[$key]); unset($_SESSION[$key]); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; endforeach; ?>
