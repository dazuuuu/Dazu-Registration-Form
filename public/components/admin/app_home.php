<?php
// Phone home screen for the admin dashboard (shown up to 768px; tablets and desktops keep the full dashboard).
// Expects $pdo and $stats (DashboardModel::adminStats()).
require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';
$ahUid = (int) ($_SESSION['user_id'] ?? 0);
$ahUnread = PortalNotifications::unreadCount($pdo, $ahUid);
$ahName = (string) ($_SESSION['username'] ?? 'Admin');
try {
    $st = $pdo->prepare("SELECT first_name FROM user_profiles WHERE user_id = ?");
    $st->execute([$ahUid]);
    $ahName = trim((string) $st->fetchColumn()) ?: $ahName;
} catch (Throwable $e) {
}
$ahHour = (int) date('G');
$ahHello = $ahHour < 12 ? 'Good morning' : ($ahHour < 17 ? 'Good afternoon' : 'Good evening');
$ahPending = [];
try {
    $ahPending = $pdo->query(
        "SELECT r.id, r.full_name, r.study_mode, r.created_at, r.course_fee, r.registration_fee, r.registration_fee_waived, c.title AS course
         FROM registrations r LEFT JOIN courses c ON c.id = r.course_id
         WHERE r.status = 'pending' ORDER BY r.created_at DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ini = fn(string $n) => strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($n)), 0, 2))));
$pend = (int) $stats['pending_regs'];
$enq = (int) $stats['new_enquiries'];
$ahSlides = [
    ['tag' => $pend ? $pend . ' application' . ($pend === 1 ? '' : 's') . ' waiting' : 'All caught up', 'title' => $pend ? 'Approve them fast.' : 'No applications waiting.',
     'points' => [['fa-bolt', 'Review details & documents'], ['fa-shield-halved', 'Approve in one tap · auto email']], 'icon' => 'fa-user-check',
     'primary' => ['fa-bolt', 'Review now', url('admin/registrations/index.php?status=pending')], 'secondary' => ['fa-user-plus', 'Enrol student', url('admin/students/enroll.php')]],
    ['tag' => 'Reach everyone', 'title' => 'Send an announcement.', 'icon' => 'fa-bullhorn',
     'points' => [['fa-mobile-screen', 'Pops up on their portal & phone'], ['fa-envelope', 'Emailed automatically']],
     'primary' => ['fa-plus', 'New announcement', url('admin/announcements/index.php')], 'secondary' => ['fa-bell', 'Notifications', url('admin/notifications/index.php')]],
    ['tag' => $enq ? $enq . ' new enquir' . ($enq === 1 ? 'y' : 'ies') : 'Enquiries', 'title' => 'Reply while they\'re hot.', 'icon' => 'fa-comments',
     'points' => [['fa-clock', 'Answer within the hour'], ['fa-user-graduate', 'Turn enquiries into students']],
     'primary' => ['fa-inbox', 'Open inbox', url('admin/enquiries/index.php')], 'secondary' => ['fa-book-open', 'Courses', url('admin/courses/index.php')]],
];
$ahTiles = [
    ['Approvals', 'fa-user-clock', url('admin/registrations/index.php?status=pending'), $pend],
    ['Students', 'fa-user-graduate', url('admin/students/index.php'), 0],
    ['Courses', 'fa-book-open', url('admin/courses/index.php'), 0],
    ['Tutors', 'fa-chalkboard-user', url('admin/tutors/index.php'), 0],
    ['Certificates', 'fa-award', url('admin/certificates/index.php'), 0],
    ['ID Cards', 'fa-id-card', url('admin/id_cards/index.php'), 0],
    ['Announce', 'fa-bullhorn', url('admin/announcements/index.php'), 0],
    ['More', 'fa-grip', '#more', 0],
];
?>
<style>
.ah{--g:#2f7d4f;--gd:#1f5e3a;--gl:#eaf5ee;display:none;color:#111827}
@media(max-width:768px){
  .ah{display:block}.dash-desktop{display:none}
  .admin-content .top-bar{display:none!important}
  .admin-content{padding:12px 16px calc(96px + env(safe-area-inset-bottom))!important;background:#fff!important}
}
.ah-head{display:flex;align-items:center;gap:12px;margin:4px 0 14px}
.ah-ava{width:42px;height:42px;border-radius:50%;background:var(--gl);color:var(--g);display:flex;align-items:center;justify-content:center;font-weight:800;flex:none}
.ah-hello{flex:1;min-width:0;line-height:1.2}.ah-hello b{display:block;font-size:1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ah-hello small{color:#6b7280;font-size:.78rem}
.ah-round{position:relative;width:42px;height:42px;border-radius:50%;border:1px solid #eceff1;background:#fff;color:#111827;display:flex;align-items:center;justify-content:center;text-decoration:none;flex:none}
.ah-round .dot{position:absolute;top:8px;right:9px;width:9px;height:9px;border-radius:50%;background:#34c759;border:2px solid #fff}
.ah-search{display:flex;align-items:center;gap:10px;background:#f4f5f6;border-radius:14px;padding:12px 14px;margin-bottom:16px}
.ah-search input{flex:1;border:0;background:transparent;outline:none;font-size:.92rem;min-width:0}
.ah-search i{color:#6b7280}
.ah-slides{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;margin:0 -16px;padding:0 16px}
.ah-slides::-webkit-scrollbar{display:none}
.ah-slide{position:relative;flex:0 0 100%;scroll-snap-align:center;border-radius:18px;padding:18px;color:#fff;overflow:hidden;
  background:linear-gradient(120deg,#2f7d4f 0%,#3f9463 55%,#5aa97a 100%);min-height:190px}
.ah-slide .art{position:absolute;right:-18px;bottom:-22px;font-size:140px;opacity:.16}
.ah-slide .tag{font-size:1.02rem;opacity:.95}.ah-slide h3{font-size:1.45rem;font-weight:800;margin:2px 0 10px;color:#fff}
.ah-slide ul{list-style:none;padding:0;margin:0 0 14px;font-size:.82rem}.ah-slide li{margin-bottom:4px}.ah-slide li i{width:16px;margin-right:6px}
.ah-btns{display:flex;gap:8px;flex-wrap:wrap;position:relative}
.ah-btn{display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:9px 16px;font-size:.85rem;font-weight:700;text-decoration:none}
.ah-btn.w{background:#fff;color:var(--g)}.ah-btn.o{border:1px solid rgba(255,255,255,.7);color:#fff}
.ah-dots{display:flex;justify-content:center;gap:6px;margin:10px 0 18px}.ah-dots span{width:7px;height:7px;border-radius:50%;background:#d1d5db}.ah-dots span.on{background:var(--g)}
.ah-sec{display:flex;justify-content:space-between;align-items:center;margin:4px 0 12px}
.ah-sec h4{font-size:1.08rem;font-weight:700;margin:0}.ah-sec a{font-size:.85rem;color:#374151;text-decoration:none}
.ah-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:20px}
.ah-tile{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:14px 4px;border-radius:14px;background:#f6f7f7;
  color:#111827;text-decoration:none;font-size:.74rem;text-align:center;line-height:1.15;border:0;width:100%}
.ah-tile i{font-size:1.35rem;color:var(--g)}
.ah-tile .n{position:absolute;top:6px;right:8px;background:#ef4444;color:#fff;font-size:.65rem;font-weight:800;border-radius:999px;padding:1px 6px}
.ah-cards{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;margin:0 -16px 18px;padding:0 16px 6px}
.ah-cards::-webkit-scrollbar{display:none}
.ah-card{flex:0 0 72%;scroll-snap-align:start;border-radius:16px;background:#fff;box-shadow:0 4px 16px rgba(17,24,39,.08);overflow:hidden}
.ah-card .top{position:relative;height:92px;background:linear-gradient(135deg,#dff0e5,#bfe1cc);display:flex;align-items:center;justify-content:center}
.ah-card .top b{width:54px;height:54px;border-radius:50%;background:#fff;color:var(--g);display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.ah-card .badge-p{position:absolute;top:10px;left:10px;background:#fff;border-radius:8px;padding:2px 8px;font-size:.7rem;font-weight:700;color:#92400e}
.ah-card .body{padding:12px 14px}.ah-card h5{font-size:1rem;font-weight:700;margin:0 0 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ah-card .meta{font-size:.75rem;color:#6b7280;margin-bottom:8px}
.ah-card .ok{display:inline-flex;gap:5px;align-items:center;font-size:.72rem;color:var(--g);background:var(--gl);border-radius:8px;padding:3px 8px;margin-bottom:10px}
.ah-card .row2{display:flex;justify-content:space-between;align-items:center;gap:8px}
.ah-card .price{font-weight:800;font-size:.95rem}.ah-card .price s{font-weight:400;color:#9ca3af;font-size:.78rem;margin-left:4px}
.ah-card .go{background:var(--g);color:#fff;border-radius:10px;padding:7px 14px;font-size:.8rem;font-weight:700;text-decoration:none}
.ah-empty{background:#f6f7f7;border-radius:14px;padding:18px;text-align:center;color:#6b7280;font-size:.88rem;margin-bottom:18px}
.ah-strip{display:grid;grid-template-columns:repeat(4,1fr);background:#f6f7f7;border-radius:14px;padding:12px 4px;margin-bottom:8px}
.ah-strip div{text-align:center;font-size:.7rem;color:#374151;line-height:1.2;border-right:1px solid #e5e7eb}.ah-strip div:last-child{border:0}
.ah-strip b{display:block;font-size:1.05rem;color:var(--g)}
</style>
<div class="ah">
    <div class="ah-head">
        <span class="ah-ava"><?php echo $e($ini($ahName) ?: 'A'); ?></span>
        <div class="ah-hello"><b><?php echo $e($ahHello . ', ' . $ahName); ?></b><small>Dazu admin · <?php echo date('D j M'); ?></small></div>
        <a class="ah-round" href="<?php echo url('admin/notifications/index.php'); ?>" aria-label="Notifications"><i class="far fa-bell"></i><?php if ($ahUnread): ?><span class="dot"></span><?php endif; ?></a>
        <a class="ah-round" href="<?php echo url('admin/announcements/index.php'); ?>" aria-label="Announcements"><i class="fas fa-bullhorn"></i></a>
    </div>

    <form class="ah-search" method="GET" action="<?php echo url('admin/students/index.php'); ?>">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="q" placeholder="Search for a student, course or reg. no." autocomplete="off">
        <i class="fas fa-sliders"></i>
    </form>

    <div class="ah-slides" id="ahSlides">
        <?php foreach ($ahSlides as $sl): ?>
        <div class="ah-slide">
            <i class="fas <?php echo $sl['icon']; ?> art"></i>
            <div class="tag"><?php echo $e($sl['tag']); ?></div>
            <h3><?php echo $e($sl['title']); ?></h3>
            <ul><?php foreach ($sl['points'] as [$ic, $t]): ?><li><i class="fas <?php echo $ic; ?>"></i><?php echo $e($t); ?></li><?php endforeach; ?></ul>
            <div class="ah-btns">
                <a class="ah-btn w" href="<?php echo $e($sl['primary'][2]); ?>"><i class="fas <?php echo $sl['primary'][0]; ?>"></i><?php echo $e($sl['primary'][1]); ?></a>
                <a class="ah-btn o" href="<?php echo $e($sl['secondary'][2]); ?>"><i class="fas <?php echo $sl['secondary'][0]; ?>"></i><?php echo $e($sl['secondary'][1]); ?></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="ah-dots" id="ahDots"><?php foreach ($ahSlides as $i => $_): ?><span class="<?php echo $i ? '' : 'on'; ?>"></span><?php endforeach; ?></div>

    <div class="ah-sec"><h4>Quick actions</h4><button type="button" class="btn btn-link p-0 text-decoration-none text-dark small" data-adm-sheet="system">See all <i class="fas fa-chevron-right ms-1"></i></button></div>
    <div class="ah-grid">
        <?php foreach ($ahTiles as [$label, $icon, $href, $n]): ?>
            <?php if ($href === '#more'): ?>
                <button type="button" class="ah-tile" data-adm-sheet="more"><i class="fas <?php echo $icon; ?>"></i><?php echo $e($label); ?></button>
            <?php else: ?>
                <a class="ah-tile" href="<?php echo $e($href); ?>"><i class="fas <?php echo $icon; ?>"></i><?php echo $e($label); ?><?php if ($n): ?><span class="n"><?php echo $n > 99 ? '99+' : (int) $n; ?></span><?php endif; ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="ah-sec"><h4>Waiting for approval</h4><a href="<?php echo url('admin/registrations/index.php?status=pending'); ?>">View all <i class="fas fa-chevron-right ms-1"></i></a></div>
    <?php if (!$ahPending): ?>
        <div class="ah-empty"><i class="fas fa-circle-check text-success me-1"></i>No applications waiting.</div>
    <?php else: ?>
    <div class="ah-cards">
        <?php foreach ($ahPending as $i => $r): ?>
        <div class="ah-card">
            <div class="top"><?php if ($i === 0): ?><span class="badge-p">Newest</span><?php endif; ?><b><?php echo $e($ini($r['full_name'])); ?></b></div>
            <div class="body">
                <h5><?php echo $e($r['full_name']); ?></h5>
                <div class="meta"><i class="fas fa-book-open me-1"></i><?php echo $e($r['course'] ?? 'No course'); ?> · <i class="far fa-clock ms-1 me-1"></i><?php echo $e(date('j M', strtotime($r['created_at']))); ?></div>
                <span class="ok"><i class="fas fa-<?php echo $r['study_mode'] === 'physical' ? 'school' : 'laptop'; ?>"></i><?php echo $r['study_mode'] === 'physical' ? 'Physical' : 'Online'; ?> student</span>
                <div class="row2">
                    <span class="price"><?php echo $r['study_mode'] === 'physical' ? 'Physical' : 'Online'; ?></span>
                    <a class="go" href="<?php echo url('admin/registrations/view.php?id=' . (int) $r['id']); ?>">Review</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="ah-strip">
        <div><b><?php echo number_format((int) $stats['students']); ?></b>Active students</div>
        <div><b><?php echo number_format((int) $stats['tutors']); ?></b>Tutors</div>
        <div><b><?php echo number_format((int) $stats['courses']); ?></b>Courses</div>
        <div><b><?php echo number_format($enq); ?></b>New enquiries</div>
    </div>
</div>
<script>
(function () {
    var box = document.getElementById('ahSlides'), dots = document.querySelectorAll('#ahDots span');
    if (!box) return;
    box.addEventListener('scroll', function () {
        var i = Math.round(box.scrollLeft / box.clientWidth);
        dots.forEach(function (d, k) { d.classList.toggle('on', k === i); });
    }, { passive: true });
})();
</script>
