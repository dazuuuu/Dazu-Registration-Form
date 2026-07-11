<?php
// public/components/students/bottom_nav.php — mobile tab bar
if ($portal_locked ?? false) return;

$current_dir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
$tabs = [
    ['dir' => 'dashboard', 'href' => url('students/dashboard/index.php'), 'icon' => 'fa-house', 'label' => 'Home'],
    ['dir' => 'course',    'href' => url('students/course/index.php'),    'icon' => 'fa-book-open', 'label' => 'Course'],
    ['dir' => 'payments',  'href' => url('students/payments/index.php'),  'icon' => 'fa-wallet', 'label' => 'Pay'],
    ['dir' => 'account',   'href' => url('students/dashboard/index.php#account'), 'icon' => 'fa-user', 'label' => 'Account'],
];
?>
<nav class="student-bottom-nav d-lg-none" aria-label="Student navigation">
    <?php foreach ($tabs as $tab):
        $active = ($tab['dir'] === 'account' && $current_dir === 'dashboard')
            ? false
            : ($current_dir === $tab['dir']);
        if ($tab['dir'] === 'account' && $current_dir === 'dashboard' && !empty($_GET['tab'])) {
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
