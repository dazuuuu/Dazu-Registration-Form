<?php
// A full-image promo popup (admin-managed, public/admin/course-ads/) advertising one course.
// Shows the newest active ad only, once per visitor per ad (dismissed state kept in localStorage,
// keyed by the ad's id so a NEW ad shows again even if an older one was dismissed).
// Its button links straight to that course's page with the Book Now form ready to go.
require_once __DIR__ . '/../../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';

try {
    $pdo = \Models\Connection::getInstance()->getConnection();
    $ad = $pdo->query("SELECT ca.*, c.title AS course_title, c.abbreviation FROM course_ads ca
        JOIN courses c ON c.id = ca.course_id AND c.is_active = 1
        WHERE ca.is_active = 1 ORDER BY ca.created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $ad = null;
}

if ($ad):
    $adUrl = course_view_url(['id' => (int) $ad['course_id'], 'abbreviation' => $ad['abbreviation']]) . '#book-now-form';
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div id="dazuAdPopup" class="dazu-ad-overlay" style="display:none" data-ad-id="<?php echo (int) $ad['id']; ?>">
  <div class="dazu-ad-card">
    <button type="button" class="dazu-ad-close" aria-label="Close" onclick="dazuAdClose()">&times;</button>
    <img src="<?php echo $e(settings_asset_url($ad['image_path'])); ?>" alt="<?php echo $e($ad['title'] ?: $ad['course_title']); ?>">
    <?php if ($ad['title'] || $ad['subtitle']): ?>
    <div class="dazu-ad-copy">
      <?php if ($ad['title']): ?><div class="dazu-ad-title"><?php echo $e($ad['title']); ?></div><?php endif; ?>
      <?php if ($ad['subtitle']): ?><div class="dazu-ad-subtitle"><?php echo $e($ad['subtitle']); ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <a href="<?php echo $e($adUrl); ?>" class="dazu-ad-btn"><?php echo $e($ad['button_text']); ?></a>
  </div>
</div>
<style>
.dazu-ad-overlay{position:fixed;inset:0;z-index:1050;background:rgba(15,23,42,.6);display:flex;align-items:center;justify-content:center;padding:16px}
.dazu-ad-card{position:relative;max-width:480px;width:100%;background:#0f172a;border-radius:1rem;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35)}
.dazu-ad-card img{display:block;width:100%;height:auto;max-height:70vh;object-fit:cover}
.dazu-ad-close{position:absolute;top:10px;right:10px;width:36px;height:36px;border-radius:999px;border:none;background:rgba(255,255,255,.9);color:#0f172a;font-size:1.5rem;line-height:1;cursor:pointer;z-index:2}
.dazu-ad-copy{padding:16px 20px 0;color:#fff;text-align:center}
.dazu-ad-title{font-weight:800;font-size:1.1rem}
.dazu-ad-subtitle{font-size:.875rem;opacity:.85;margin-top:2px}
.dazu-ad-btn{display:block;margin:16px 20px 20px;text-align:center;background:#16a34a;color:#fff;font-weight:700;padding:.75rem 1rem;border-radius:999px;text-decoration:none}
.dazu-ad-btn:hover{background:#15803d;color:#fff}
</style>
<script>
(function(){
  var el = document.getElementById('dazuAdPopup');
  if (!el) return;
  var key = 'dazu-ad-dismissed-' + el.getAttribute('data-ad-id');
  try { if (localStorage.getItem(key)) return; } catch (e) {}
  setTimeout(function(){ el.style.display = 'flex'; }, 600);
  window.dazuAdClose = function(){
    el.style.display = 'none';
    try { localStorage.setItem(key, '1'); } catch (e) {}
  };
  el.addEventListener('click', function(ev){ if (ev.target === el) window.dazuAdClose(); });
})();
</script>
<?php endif; ?>
