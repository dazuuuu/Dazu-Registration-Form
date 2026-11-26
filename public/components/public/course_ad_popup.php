<?php
// A promo ad for one course (admin-managed, public/admin/course-ads/), AliExpress/Jumia-style: opens
// centered over a blurred backdrop on every page load, closing it collapses into a small bottom-right
// bubble (a red dot while collapsed, bouncing now and then) that reopens the same centered ad on click.
// Falls back to the course's own title/cover image when the admin left those blank. The "Apply" action
// is a small pill that hovers over the ad image itself (not a separate solid button), and goes straight
// to that course's page with the Book Now form ready.
require_once __DIR__ . '/../../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';

try {
    $pdo = \Models\Connection::getInstance()->getConnection();
    $ad = $pdo->query("SELECT ca.*, c.title AS course_title, c.abbreviation, c.cover_image FROM course_ads ca
        JOIN courses c ON c.id = ca.course_id AND c.is_active = 1
        WHERE ca.is_active = 1 AND (ca.ends_at IS NULL OR ca.ends_at > UTC_TIMESTAMP())
        ORDER BY ca.created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $ad = null;
}

if ($ad):
    $adUrl = course_view_url(['id' => (int) $ad['course_id'], 'abbreviation' => $ad['abbreviation']]) . '#book-now-form';
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $img = settings_asset_url($ad['image_path'] ?: $ad['cover_image']);
    $title = $ad['title'] ?: $ad['course_title'];
    $endsAtIso = $ad['ends_at'] ? gmdate('Y-m-d\TH:i:s\Z', strtotime($ad['ends_at'])) : '';
    // Auto-opens centered only on the landing page (public/index.php); every other public page just shows the bubble.
    $isHome = !empty($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__DIR__ . '/../../index.php');
?>
<div id="dazuAdWrap" data-ad-id="<?php echo (int) $ad['id']; ?>" data-ends="<?php echo $e($endsAtIso); ?>" data-autoopen="<?php echo $isHome ? '1' : '0'; ?>">
  <div id="dazuAdOverlay" class="dazu-ad-overlay">
    <div class="dazu-ad-modal">
      <button type="button" class="dazu-ad-x" aria-label="Close" onclick="dazuAdMinimize()">&times;</button>
      <?php if ($endsAtIso): ?><div class="dazu-ad-timer" id="dazuAdTimer"><i class="fas fa-clock"></i> <span></span></div><?php endif; ?>
      <div class="dazu-ad-imgwrap">
        <img src="<?php echo $e($img); ?>" alt="<?php echo $e($title); ?>">
        <a href="<?php echo $e($adUrl); ?>" class="dazu-ad-apply"><?php echo $e($ad['button_text']); ?></a>
      </div>
      <div class="dazu-ad-copy">
        <div class="dazu-ad-title"><?php echo $e($title); ?></div>
        <?php if ($ad['subtitle']): ?><div class="dazu-ad-subtitle"><?php echo $e($ad['subtitle']); ?></div><?php endif; ?>
      </div>
    </div>
  </div>
  <button type="button" id="dazuAdBubble" class="dazu-ad-bubble" aria-label="View offer" onclick="dazuAdOpen()">
    <img src="<?php echo $e($img); ?>" alt="">
    <span class="dazu-ad-dot"></span>
  </button>
</div>
<style>
.dazu-ad-overlay{position:fixed;inset:0;z-index:1060;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.45);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
.dazu-ad-overlay.show{display:flex}
.dazu-ad-modal{position:relative;width:100%;max-width:380px;background:#fff;border-radius:1.25rem;overflow:hidden;box-shadow:0 24px 70px rgba(0,0,0,.4);animation:dazuAdPop .22s ease}
@keyframes dazuAdPop{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:scale(1)}}
.dazu-ad-x{position:absolute;top:10px;right:10px;z-index:3;width:32px;height:32px;border-radius:999px;border:none;background:rgba(15,23,42,.55);color:#fff;font-size:1.3rem;line-height:1;cursor:pointer}
.dazu-ad-timer{position:absolute;top:12px;left:12px;z-index:3;color:#ef4444;font-weight:800;font-size:.8rem;letter-spacing:.02em;white-space:nowrap;text-shadow:0 1px 3px rgba(0,0,0,.6)}
.dazu-ad-imgwrap{position:relative}
.dazu-ad-imgwrap img{width:100%;max-height:60vh;object-fit:cover;display:block}
.dazu-ad-apply{position:absolute;right:12px;bottom:12px;background:rgba(22,163,74,.95);color:#fff;font-weight:700;font-size:.85rem;padding:.5rem .9rem;border-radius:999px;text-decoration:none;box-shadow:0 4px 14px rgba(0,0,0,.3);transition:background .15s ease}
.dazu-ad-apply:hover{background:#15803d;color:#fff}
.dazu-ad-copy{padding:.9rem 1.1rem 1.1rem}
.dazu-ad-title{font-weight:800;font-size:1rem;color:#0f172a;line-height:1.25}
.dazu-ad-subtitle{font-size:.8rem;color:#64748b;margin-top:3px}

.dazu-ad-bubble{position:fixed;right:18px;bottom:18px;z-index:1050;width:60px;height:60px;border-radius:999px;border:3px solid #fff;padding:0;overflow:visible;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.25);background:#16a34a;display:none}
.dazu-ad-bubble.show{display:block;animation:dazuAdPulseRing 2.2s ease-out infinite}
.dazu-ad-bubble img{width:100%;height:100%;object-fit:cover;display:block;border-radius:999px}
.dazu-ad-bubble.bounce{animation:dazuAdBounce .9s ease 3}
.dazu-ad-dot{position:absolute;top:-2px;right:-2px;width:14px;height:14px;border-radius:999px;background:#dc2626;border:2px solid #fff;animation:dazuAdDotPulse 1.4s ease-in-out infinite}
@keyframes dazuAdBounce{0%,100%{transform:translateY(0) scale(1)}20%{transform:translateY(-16px) scale(1.04)}45%{transform:translateY(0) scale(.98)}65%{transform:translateY(-8px)}85%{transform:translateY(0)}}
@keyframes dazuAdPulseRing{0%{box-shadow:0 8px 24px rgba(0,0,0,.25),0 0 0 0 rgba(22,163,74,.55)}70%{box-shadow:0 8px 24px rgba(0,0,0,.25),0 0 0 14px rgba(22,163,74,0)}100%{box-shadow:0 8px 24px rgba(0,0,0,.25),0 0 0 0 rgba(22,163,74,0)}}
@keyframes dazuAdDotPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.3);opacity:.75}}
@media (max-width:420px){.dazu-ad-modal{max-width:calc(100vw - 32px)}}
</style>
<script>
(function(){
  var wrap = document.getElementById('dazuAdWrap');
  if (!wrap) return;
  var adId = wrap.getAttribute('data-ad-id');
  var ends = wrap.getAttribute('data-ends');
  var overlay = document.getElementById('dazuAdOverlay');
  var bubble = document.getElementById('dazuAdBubble');
  var bounceKey = 'dazu-ad-last-bounce-' + adId;
  var BOUNCE_EVERY = 15 * 1000;

  function ding() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = new Ctx();
      var osc = ctx.createOscillator(), gain = ctx.createGain();
      osc.type = 'sine'; osc.frequency.setValueAtTime(880, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(660, ctx.currentTime + 0.15);
      gain.gain.setValueAtTime(0.12, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.3);
      osc.connect(gain); gain.connect(ctx.destination);
      osc.start(); osc.stop(ctx.currentTime + 0.32);
      osc.onended = function(){ ctx.close(); };
    } catch (e) {}
  }
  var endMs = ends ? new Date(ends).getTime() : null;
  var autoOpen = wrap.getAttribute('data-autoopen') === '1';

  function expired() { return endMs !== null && Date.now() >= endMs; }

  window.dazuAdOpen = function(){
    if (expired()) return;
    overlay.classList.add('show');
    bubble.classList.remove('show', 'bounce');
  };
  window.dazuAdMinimize = function(){
    overlay.classList.remove('show');
    if (!expired()) bubble.classList.add('show');
  };

  if (autoOpen && !expired()) setTimeout(window.dazuAdOpen, 700);
  else if (!expired()) bubble.classList.add('show');

  bubble.addEventListener('animationend', function(ev){ if (ev.animationName === 'dazuAdBounce') bubble.classList.remove('bounce'); });
  function bounce() {
    if (overlay.classList.contains('show') || expired() || !bubble.classList.contains('show')) return;
    bubble.classList.remove('bounce'); void bubble.offsetWidth; bubble.classList.add('bounce');
    ding();
    try { sessionStorage.setItem(bounceKey, String(Date.now())); } catch (e) {}
  }
  setTimeout(bounce, 2500);
  setInterval(function(){
    var last = 0;
    try { last = parseInt(sessionStorage.getItem(bounceKey) || '0', 10); } catch (e) {}
    if (Date.now() - last >= BOUNCE_EVERY) bounce();
  }, 2000);

  overlay.addEventListener('click', function(ev){ if (ev.target === overlay) window.dazuAdMinimize(); });

  if (endMs !== null) {
    var el = document.querySelector('#dazuAdTimer span');
    function tick() {
      var s = Math.max(0, Math.floor((endMs - Date.now()) / 1000));
      if (s <= 0) { overlay.classList.remove('show'); bubble.classList.remove('show'); return; }
      var w = Math.floor(s / 604800); s -= w * 604800;
      var d = Math.floor(s / 86400); s -= d * 86400;
      var h = Math.floor(s / 3600); s -= h * 3600;
      var m = Math.floor(s / 60); s -= m * 60;
      var parts = [];
      if (w) parts.push(w + 'w');
      if (w || d) parts.push(d + 'd');
      parts.push(h + 'h', m + 'm', s + 's');
      if (el) el.textContent = parts.join(' ') + ' left';
    }
    tick();
    setInterval(tick, 1000);
  }
})();
</script>
<?php endif; ?>
