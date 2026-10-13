<?php
// App shell: desktop sidebar + top bar (this file), and the phone tab bar + "More" sheet (tabs.php).
// Expects $shell = [
//   'portal'   => 'tutor' | 'students'          (PWA portal key, for the bell)
//   'role'     => 'Tutor portal'                 (chip under the logo)
//   'title'    => page title, 'subtitle' => small line under it (optional)
//   'user'     => display name, 'user_sub' => small line under it, 'photo' => avatar URL or null
//   'items'    => [[key, label, icon, href, active(bool), tab(bool: on the phone tab bar), section(optional heading)]...]
//   'menu'     => extra <li> HTML for the avatar dropdown
//   'logout'   => logout URL
//   'hide_nav' => true to show only the bar (e.g. locked portal)
// ]
require_once __DIR__ . '/../../../app/v1/helpers/brand.php';
$sh = $shell;
$shE = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$shInitials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string) $sh['user'])) ?: ['?'], 0, 2))));
$shAvatar = !empty($sh['photo'])
    ? '<img class="as-ava" src="' . $shE($sh['photo']) . '" alt="">'
    : '<span class="as-ava">' . $shE($shInitials ?: '?') . '</span>';
?>
<?php if (empty($sh['hide_nav'])): ?>
<aside class="as-side d-none d-lg-flex" aria-label="Main navigation">
    <a class="as-logo" href="<?php echo $shE($sh['items'][0][3] ?? '#'); ?>"><img src="<?php echo $shE(brand_url('logo')); ?>" alt="Dazu Training Institute"></a>
    <span class="as-role"><i class="fas fa-circle" style="font-size:.4rem"></i><?php echo $shE($sh['role']); ?></span>
    <nav class="as-nav">
        <?php foreach ($sh['items'] as $it): ?>
            <?php if (!empty($it[6])): ?><div class="as-sep"><?php echo $shE($it[6]); ?></div><?php endif; ?>
            <a href="<?php echo $shE($it[3]); ?>" class="<?php echo !empty($it[4]) ? 'on' : ''; ?>"<?php echo !empty($it[4]) ? ' aria-current="page"' : ''; ?>>
                <i class="fas <?php echo $shE($it[2]); ?>"></i><span><?php echo $shE($it[1]); ?></span>
                <?php if (!empty($it[8])): ?><span class="ms-auto badge rounded-pill" style="background:var(--g);color:#fff" aria-label="<?php echo (int) $it[8]; ?> waiting"><?php echo (int) $it[8]; ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="as-me">
        <?php echo $shAvatar; ?>
        <div style="min-width:0"><div class="nm"><?php echo $shE($sh['user']); ?></div><div class="sb"><?php echo $shE($sh['user_sub'] ?? ''); ?></div></div>
        <a href="<?php echo $shE($sh['logout']); ?>" title="Sign out"><i class="fas fa-arrow-right-from-bracket"></i></a>
    </div>
</aside>
<?php endif; ?>

<div class="<?php echo empty($sh['hide_nav']) ? 'as-body' : ''; ?>">
<header class="as-top" id="asTop">
    <a class="as-mark d-lg-none" href="<?php echo $shE($sh['items'][0][3] ?? '#'); ?>"><img src="<?php echo $shE(brand_url('mark')); ?>" alt="Dazu"></a>
    <div class="as-title">
        <div class="t"><?php echo $shE($sh['title']); ?></div>
        <div class="s"><?php echo $shE($sh['subtitle'] ?? date('l, j F')); ?></div>
    </div>
    <div class="as-actions">
        <button type="button" class="as-iconbtn d-none" data-pwa-install title="Install app"><i class="fas fa-download"></i></button>
        <?php $pwaPortal = $sh['portal']; require __DIR__ . '/../pwa/bell.php'; ?>
        <div class="dropdown">
            <button class="as-user" type="button" data-bs-toggle="dropdown" aria-label="Account"><?php echo $shAvatar; ?></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-3 py-2"><div class="fw-semibold small"><?php echo $shE($sh['user']); ?></div><div class="text-muted" style="font-size:.75rem"><?php echo $shE($sh['user_sub'] ?? ''); ?></div></li>
                <li><hr class="dropdown-divider"></li>
                <?php echo $sh['menu'] ?? ''; ?>
                <li><a class="dropdown-item d-none" href="#" data-pwa-enable-push><i class="fas fa-bell me-2"></i>Turn on alerts</a></li>
                <li><a class="dropdown-item text-danger" href="<?php echo $shE($sh['logout']); ?>"><i class="fas fa-arrow-right-from-bracket me-2"></i>Sign out</a></li>
            </ul>
        </div>
    </div>
</header>
