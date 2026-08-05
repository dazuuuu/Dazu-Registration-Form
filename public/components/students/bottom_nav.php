<?php
// public/components/students/bottom_nav.php — mobile tab bar
if ($portal_locked ?? false) return;

$current_dir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
$tabs = [
    ['dir' => 'dashboard', 'href' => url('students/dashboard/index.php'), 'icon' => 'fa-house', 'label' => 'Home'],
    ['dir' => 'course',    'href' => url('students/course/index.php'),    'icon' => 'fa-book-open', 'label' => 'Course'],
    ['dir' => 'payments',  'href' => url('students/payments/index.php'),  'icon' => 'fa-wallet', 'label' => 'Pay'],
    ['dir' => 'resources', 'href' => url('students/resources/index.php'), 'icon' => 'fa-folder-open', 'label' => 'Resources'],
];
?>
<nav class="student-bottom-nav d-lg-none" aria-label="Student navigation">
    <?php foreach ($tabs as $tab):
        $active = ($current_dir === $tab['dir']);
        if ($tab['dir'] === 'resources' && $current_dir === 'resources') {
            $active = true;
        }
    ?>
    <a href="<?php echo $tab['href']; ?>"
       class="student-bottom-nav-item <?php echo $active ? 'is-active' : ''; ?>">
        <i class="fas <?php echo $tab['icon']; ?>"></i>
        <span><?php echo $tab['label']; ?></span>
    </a>
    <?php endforeach; ?>
</nav>
