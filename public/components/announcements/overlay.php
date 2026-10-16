<?php
// Full-screen announcement pop-up for portal layouts (students, tutor, intern, admin, superadmin). Shows the announcements the
// signed-in person has not closed yet, one after another; the X closes it for good (announcements/dismiss.php).
// Skipped in portal previews and on the announcements page itself.
require_once __DIR__ . '/../../../app/v1/services/AnnouncementService.php';
$annPending = [];
if (empty($_SESSION['preview']) && basename(dirname($_SERVER['PHP_SELF'])) !== 'announcements') {
    try {
        $annPending = ann_received(\Models\Connection::getInstance()->getConnection(), (int) ($_SESSION['user_id'] ?? 0), true, 5);
    } catch (Throwable $e) {
        $annPending = [];
    }
}
if ($annPending):
    require __DIR__ . '/styles.php';
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<?php foreach ($annPending as $i => $a): ?>
<div class="ann-pop" data-ann-pop="<?php echo (int) $a['id']; ?>" role="dialog" aria-modal="true" aria-labelledby="annT<?php echo (int) $a['id']; ?>" <?php echo $i ? 'hidden' : ''; ?>>
    <div class="ann-pop-card">
        <div class="ann-pop-bar">
            <span class="ann-pop-tag"><i class="fas fa-bullhorn me-1"></i>Announcement
                <?php if (count($annPending) > 1): ?><span class="ann-pop-count ms-1"><?php echo $i + 1; ?> of <?php echo count($annPending); ?></span><?php endif; ?></span>
            <button type="button" class="ann-pop-x" data-ann-close aria-label="Close announcement"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="ann-pop-body">
            <?php if ($media = ann_media_html($a)): ?><div class="ann-media"><?php echo $media; ?></div><?php endif; ?>
            <h2 id="annT<?php echo (int) $a['id']; ?>"><?php echo $e($a['title']); ?></h2>
            <p class="ann-summary"><?php echo $e($a['summary']); ?></p>
            <?php if ($a['message']): ?><div class="ann-msg mb-3"><?php echo $e($a['message']); ?></div><?php endif; ?>
            <div class="ann-meta"><i class="fas fa-user me-1"></i><?php echo $e($a['author_name']); ?> · <?php echo $e(date('j M Y, g:i a', strtotime($a['created_at']))); ?></div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<script>
(function () {
    var pops = Array.prototype.slice.call(document.querySelectorAll('[data-ann-pop]'));
    var csrf = <?php echo json_encode(csrf_token()); ?>, endpoint = <?php echo json_encode(url('announcements/dismiss.php')); ?>;
    document.documentElement.style.overflow = 'hidden';
    function stop(pop) {   // stop any video or music before hiding
        pop.querySelectorAll('iframe').forEach(function (f) { f.src = 'about:blank'; });
        pop.querySelectorAll('audio,video').forEach(function (m) { m.pause(); });
    }
    function close(pop) {
        var body = new URLSearchParams({ id: pop.getAttribute('data-ann-pop'), csrf_token: csrf });
        fetch(endpoint, { method: 'POST', body: body, credentials: 'same-origin' }).catch(function () {});
        stop(pop);
        pop.hidden = true;
        var next = pops[pops.indexOf(pop) + 1];
        if (next) next.hidden = false; else document.documentElement.style.overflow = '';
    }
    pops.forEach(function (pop) {
        pop.querySelector('[data-ann-close]').addEventListener('click', function () { close(pop); });
        pop.addEventListener('click', function (e) { if (e.target === pop) close(pop); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var open = pops.filter(function (p) { return !p.hidden; })[0];
        if (open) close(open);
    });
})();
</script>
<?php endif; ?>
