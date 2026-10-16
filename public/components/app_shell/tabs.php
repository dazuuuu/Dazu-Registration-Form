<?php
// Phone/tablet tab bar: the items marked as tabs, plus "More" which opens a sheet with everything else.
// Closes the .as-body wrapper opened in shell.php. Expects the same $shell.
$sh = $shell;
$shE = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
</div><!-- /.as-body -->
<?php if (empty($sh['hide_nav']) && !empty($sh['tabs'])):
    // Custom phone tab bar: $shell['tabs'] = [['link', label, icon, href, active] | ['sheet', key, label, icon, [[label, icon, href, active]...], active]]
    $sheetTabs = array_filter($sh['tabs'], fn($t) => $t[0] === 'sheet');
?>
<nav class="as-tabs" aria-label="App navigation">
    <?php foreach ($sh['tabs'] as $t): ?>
        <?php if ($t[0] === 'link'): ?>
            <a href="<?php echo $shE($t[3]); ?>" class="<?php echo !empty($t[4]) ? 'on' : ''; ?>"<?php echo !empty($t[4]) ? ' aria-current="page"' : ''; ?>>
                <i class="fas <?php echo $shE($t[2]); ?>"></i><span><?php echo $shE($t[1]); ?></span>
                <?php if (!empty($t[5])): ?><span class="dot"><?php echo (int) $t[5]; ?></span><?php endif; ?></a>
        <?php else: ?>
            <button type="button" class="<?php echo !empty($t[5]) ? 'on' : ''; ?>" data-bs-toggle="offcanvas" data-bs-target="#asSheet-<?php echo $shE($t[1]); ?>" aria-controls="asSheet-<?php echo $shE($t[1]); ?>">
                <i class="fas <?php echo $shE($t[3]); ?>"></i><span><?php echo $shE($t[2]); ?></span></button>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<?php foreach ($sheetTabs as $t): ?>
<div class="offcanvas offcanvas-bottom as-sheet" tabindex="-1" id="asSheet-<?php echo $shE($t[1]); ?>" aria-label="<?php echo $shE($t[2]); ?>">
    <div class="grab"></div>
    <div class="offcanvas-body pt-2">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="fw-semibold"><?php echo $shE($t[2]); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="as-grid">
            <?php foreach ($t[4] as $it): ?>
                <?php if ($it[2] === '#logout'): ?>
                    <a href="<?php echo $shE($sh['logout']); ?>" class="danger"><i class="fas fa-arrow-right-from-bracket"></i>Sign out</a>
                <?php elseif ($it[2] === '#install'): ?>
                    <a href="#" class="d-none" data-pwa-install><i class="fas fa-download"></i>Install app</a>
                <?php elseif ($it[2] === '#alerts'): ?>
                    <a href="#" class="d-none" data-pwa-enable-push><i class="fas fa-bell"></i>Turn on phone alerts</a>
                <?php else: ?>
                    <a href="<?php echo $shE($it[2]); ?>" class="<?php echo !empty($it[3]) ? 'on' : ''; ?>"><i class="fas <?php echo $shE($it[1]); ?>"></i><?php echo $shE($it[0]); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php elseif (empty($sh['hide_nav'])):
    $tabs = array_values(array_filter($sh['items'], fn($it) => !empty($it[5])));
    $more = array_values(array_filter($sh['items'], fn($it) => empty($it[5])));
    $moreActive = (bool) array_filter($more, fn($it) => !empty($it[4]));
?>
<nav class="as-tabs" aria-label="App navigation">
    <?php foreach ($tabs as $it): ?>
        <a href="<?php echo $shE($it[3]); ?>" class="<?php echo !empty($it[4]) ? 'on' : ''; ?>"<?php echo !empty($it[4]) ? ' aria-current="page"' : ''; ?>>
            <i class="fas <?php echo $shE($it[2]); ?>"></i><span><?php echo $shE($it[7] ?? $it[1]); ?></span>
            <?php if (!empty($it[8])): ?><span class="dot"><?php echo (int) $it[8]; ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
    <button type="button" class="<?php echo $moreActive ? 'on' : ''; ?>" data-bs-toggle="offcanvas" data-bs-target="#asMore" aria-controls="asMore">
        <i class="fas fa-grip"></i><span>More</span>
    </button>
</nav>
<div class="offcanvas offcanvas-bottom as-sheet" tabindex="-1" id="asMore" aria-label="More">
    <div class="grab"></div>
    <div class="offcanvas-body pt-2">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="fw-semibold">Menu</div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="as-grid">
            <?php foreach ($more as $it): ?>
                <a href="<?php echo $shE($it[3]); ?>" class="<?php echo !empty($it[4]) ? 'on' : ''; ?>"><i class="fas <?php echo $shE($it[2]); ?>"></i><?php echo $shE($it[1]); ?></a>
            <?php endforeach; ?>
            <a href="#" class="d-none" data-pwa-install><i class="fas fa-download"></i>Install app</a>
            <a href="<?php echo $shE($sh['logout']); ?>" class="danger"><i class="fas fa-arrow-right-from-bracket"></i>Sign out</a>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
(function () {
    var top = document.getElementById('asTop');
    if (!top) return;
    var on = function () { top.classList.toggle('scrolled', window.scrollY > 4); };
    window.addEventListener('scroll', on, {passive: true}); on();
})();
</script>
