<?php
// public/blogs/category.php — a landing page for one blog category: /blog/category/<slug>.
// Top: a carousel of the category's posts. Then the category's heading, subheading and description (the admin's own
// description from Admin → Blog → Categories if there is one, else written from the posts), then every post in it.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';

$pdo = \Models\Connection::getInstance()->getConnection();
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$cat = null;
try {
    $st = $pdo->prepare("SELECT * FROM blog_categories WHERE slug = ? OR LOWER(name) = ? LIMIT 1");
    $st->execute([$slug, strtolower(str_replace('-', ' ', $slug))]);
    $cat = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
}
if (!$cat) { http_response_code(404); require __DIR__ . '/../404.php'; exit; }

$catPlain = static function (string $t): string {
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    $t = preg_replace(['/^\s*#+\s*/m', '/[=*_~`#>\-]{3,}/', '/\s+/u'], ['', ' ', ' '], $t);
    return trim($t);
};
$catImg = static function (?string $p): string {
    $p = trim((string) $p);
    if ($p === '' || preg_match('#^(https?:)?//#i', $p)) return $p;
    return preg_match('#(uploads/.+)$#', $p, $m) ? url($m[1]) : $p;
};
$st = $pdo->prepare("SELECT b.*, u.username AS author_name FROM blogs b LEFT JOIN users u ON u.id = b.author_id
                     WHERE b.status = 'published' AND b.category_id = ? ORDER BY b.is_featured DESC, b.published_at DESC");
$st->execute([(int) $cat['id']]);
$posts = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $plain = $catPlain($b['content'] ?? '');
    $posts[] = [
        'title' => $b['title'], 'url' => url('blog/' . rawurlencode($b['slug'])), 'image' => $catImg($b['featured_image'] ?? ''),
        'summary' => mb_strimwidth($catPlain(($b['excerpt'] ?? '') ?: $plain), 0, 170, '…'),
        'date' => $b['published_at'] ?: $b['created_at'], 'read' => max(1, (int) ceil(str_word_count($plain) / 200)) . ' min read',
        'featured' => !empty($b['is_featured']),
    ];
}
$name = (string) $cat['name'];
$n = count($posts);

// Heading, subheading and description: the admin's description first, else written from the category's own posts.
$heading = $name;
$sub = $n ? 'Practical articles, guides and ideas on ' . $name . ' from Dazu Training Institute' : 'Articles on ' . $name . ' are coming soon';
$desc = trim((string) ($cat['description'] ?? ''));
if ($desc === '' && $n) {
    $titles = array_map(fn($p) => '“' . $p['title'] . '”', array_slice($posts, 0, 2));
    $desc = 'Explore ' . $n . ' article' . ($n > 1 ? 's' : '') . ' on ' . $name . ', written by our trainers to help you learn the skills, understand the trends and plan your next step'
          . ($titles ? '. Start with ' . implode(' and ', $titles) . '.' : '.');
}
$slides = array_slice($posts, 0, 6);
$perPage = 12;
$pages = max(1, (int) ceil($n / $perPage));
$pageNo = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$pagePosts = array_slice($posts, ($pageNo - 1) * $perPage, $perPage);
$others = [];
try {
    $others = $pdo->query("SELECT c.name, c.slug FROM blog_categories c WHERE EXISTS (SELECT 1 FROM blogs b WHERE b.category_id = c.id AND b.status = 'published') ORDER BY c.name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}
$catUrl = static fn(string $s, int $p = 1): string => url('blog/category/' . rawurlencode($s)) . ($p > 1 ? '?page=' . $p : '');

$page_title = $name . ' — Dazu Training Institute Blog';
$page_description = mb_strimwidth($desc !== '' ? $desc : $sub, 0, 160, '…');
foreach ($posts as $p) if ($p['image'] !== '') { $og_image = $p['image']; break; }
$GLOBALS['dazu_canonical'] = $catUrl((string) $cat['slug'], $pageNo);
ob_start();
?>
<style>
.bc{background:#fff;color:#1f2937;padding:28px 16px 72px}
.bc *{box-sizing:border-box}.bc a{text-decoration:none;color:inherit}
.bc-wrap{max-width:1180px;margin:0 auto}
.bc h1,.bc h2,.bc h3,.bc p{margin:0;text-transform:none;letter-spacing:normal}
.bc-crumb{font-size:12px;color:#6b7280;margin-bottom:14px}.bc-crumb a:hover{color:#15803d}
.bc-hero{position:relative;width:100%;border-radius:18px;overflow:hidden;background:#111827;height:clamp(300px,38vw,470px)}
@media(max-width:640px){.bc-hero{height:460px}}
.bc-slide{position:absolute;inset:0;opacity:0;visibility:hidden;transition:opacity .6s,visibility .6s}
.bc-slide.on{opacity:1;visibility:visible}
.bc-slide img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.bc-slide.noimg{background:linear-gradient(135deg,#22c55e,#15A24A 45%,#064d22)}
.bc-slide::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(6,22,12,.82) 0%,rgba(6,22,12,.45) 55%,rgba(6,22,12,.1))}
@media(max-width:640px){.bc-slide::after{background:linear-gradient(180deg,rgba(6,22,12,.1) 20%,rgba(6,22,12,.88))}}
.bc-in{position:absolute;z-index:1;left:0;bottom:0;top:0;display:flex;flex-direction:column;justify-content:center;padding:32px 72px;max-width:720px;color:#fff}
@media(max-width:640px){.bc-in{justify-content:flex-end;padding:22px}}
.bc-chip{display:inline-flex;align-self:flex-start;align-items:center;gap:6px;font-size:11px;font-weight:600;background:rgba(255,255,255,.92);color:#14532d;border-radius:999px;padding:5px 11px}
.bc-chip i{width:7px;height:7px;border-radius:50%;background:#16a34a}
.bc-in h2{font-size:clamp(22px,3vw,34px);line-height:1.2;font-weight:700;color:#fff;margin:12px 0 10px}
.bc-in p{color:rgba(255,255,255,.85);font-size:15px;line-height:1.6;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.bc-go{align-self:flex-start;margin-top:18px;background:#15A24A;color:#fff!important;font-weight:700;font-size:13px;padding:11px 20px;border-radius:999px}
.bc-go:hover{background:#0f8a3d}
.bc-dots{position:absolute;z-index:2;right:22px;bottom:22px;display:flex;gap:6px}
.bc-dots button{width:8px;height:8px;border-radius:999px;border:0;padding:0;background:rgba(255,255,255,.5);cursor:pointer;transition:width .25s}
.bc-dots button.on{width:24px;background:#fff}
.bc-arr{position:absolute;z-index:2;top:50%;transform:translateY(-50%);width:40px;height:40px;border-radius:50%;border:0;background:rgba(255,255,255,.88);color:#111827;cursor:pointer;font-size:18px}
.bc-arr.prev{left:14px}.bc-arr.next{right:14px}
@media(max-width:640px){.bc-arr{display:none}}
.bc-intro{display:grid;grid-template-columns:1fr;gap:18px;padding:40px 0 30px;border-bottom:1px solid #eef0f2;margin-bottom:30px}
@media(min-width:900px){.bc-intro{grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);align-items:end}}
.bc-eye{font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#15A24A}
.bc-intro h1{font-size:clamp(28px,4vw,44px);font-weight:800;color:#0f172a;margin:6px 0 8px;letter-spacing:-.02em}
.bc-sub{font-size:17px;color:#334155;font-weight:500;margin-bottom:10px}
.bc-desc{color:#64748b;line-height:1.75;max-width:640px}
.bc-meta{display:flex;flex-wrap:wrap;gap:8px}
.bc-meta a,.bc-meta span{font-size:13px;font-weight:500;padding:7px 14px;border-radius:999px;background:#f3f4f6;color:#4b5563}
.bc-meta a:hover{background:#e5e7eb}.bc-meta .on{background:#111827;color:#fff}
.bc-count{font-weight:700;color:#0f172a}
.bc-grid{display:grid;grid-template-columns:1fr;gap:22px}
@media(min-width:600px){.bc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1000px){.bc-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.bc-card{display:flex;flex-direction:column;background:#fafaf9;border-radius:14px;padding:6px 6px 16px;transition:background .2s,box-shadow .2s}
.bc-card:hover{background:#fff;box-shadow:0 10px 28px rgba(17,24,39,.08)}
.bc-thumb{display:block;aspect-ratio:16/10;border-radius:10px;overflow:hidden;background:linear-gradient(135deg,#16a34a,#14532d)}
.bc-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .5s}
.bc-card:hover .bc-thumb img{transform:scale(1.05)}
.bc-body{padding:12px 8px 0;display:flex;flex-direction:column;flex:1}
.bc-card h3{font-size:17px;line-height:1.35;font-weight:600;color:#111827;margin:0 0 6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.bc-card h3:hover{color:#15803d}
.bc-card p{font-size:13px;line-height:1.6;color:#6b7280;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:10px}
.bc-m{margin-top:auto;font-size:11px;color:#6b7280}
.bc-empty{text-align:center;padding:60px 20px;border:1px dashed #d1d5db;border-radius:14px;color:#6b7280}
.bc-pages{display:flex;justify-content:center;gap:6px;margin-top:34px}
.bc-pages a,.bc-pages span{min-width:32px;height:32px;padding:0 9px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;color:#374151}
.bc-pages a:hover{background:#f3f4f6}.bc-pages .cur{background:#111827;color:#fff}
</style>
<div class="bc"><div class="bc-wrap">
  <div class="bc-crumb"><a href="<?php echo url('/'); ?>">Home</a> / <a href="<?php echo url('blog'); ?>">Blog</a> / <?php echo $h($name); ?></div>

  <?php if ($slides): ?>
  <section class="bc-hero" id="bcHero" aria-roledescription="carousel" aria-label="<?php echo $h($name); ?> articles">
    <?php foreach ($slides as $i => $p): ?>
      <div class="bc-slide<?php echo $i === 0 ? ' on' : ''; ?><?php echo $p['image'] === '' ? ' noimg' : ''; ?>" aria-hidden="<?php echo $i ? 'true' : 'false'; ?>">
        <?php if ($p['image'] !== ''): ?><img src="<?php echo $h($p['image']); ?>" alt="" <?php echo $i ? 'loading="lazy"' : ''; ?> onerror="this.remove()"><?php endif; ?>
        <div class="bc-in">
          <span class="bc-chip"><i></i><?php echo $h($name); ?></span>
          <h2><?php echo $h($p['title']); ?></h2>
          <p><?php echo $h($p['summary']); ?></p>
          <a class="bc-go" href="<?php echo $h($p['url']); ?>">Read article →</a>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (count($slides) > 1): ?>
      <button type="button" class="bc-arr prev" aria-label="Previous">&#8249;</button><button type="button" class="bc-arr next" aria-label="Next">&#8250;</button>
      <div class="bc-dots"><?php foreach ($slides as $i => $p): ?><button type="button" class="<?php echo $i ? '' : 'on'; ?>" aria-label="Article <?php echo $i + 1; ?>"></button><?php endforeach; ?></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="bc-intro">
    <div>
      <span class="bc-eye">Blog category</span>
      <h1><?php echo $h($heading); ?></h1>
      <p class="bc-sub"><?php echo $h($sub); ?></p>
      <?php if ($desc !== ''): ?><p class="bc-desc"><?php echo $h($desc); ?></p><?php endif; ?>
    </div>
    <div>
      <p class="bc-count" style="margin-bottom:10px"><?php echo $n; ?> article<?php echo $n === 1 ? '' : 's'; ?></p>
      <div class="bc-meta"><a href="<?php echo url('blog'); ?>">All articles</a>
        <?php foreach ($others as $o): ?><a class="<?php echo $o['slug'] === $cat['slug'] ? 'on' : ''; ?>" href="<?php echo $h($catUrl((string) $o['slug'])); ?>"><?php echo $h($o['name']); ?></a><?php endforeach; ?></div>
    </div>
  </section>

  <?php if (!$pagePosts): ?>
    <div class="bc-empty">No articles in this category yet. <a href="<?php echo url('blog'); ?>" style="color:#15803d">Browse all articles</a></div>
  <?php else: ?>
    <div class="bc-grid">
      <?php foreach ($pagePosts as $p): ?>
        <article class="bc-card">
          <a class="bc-thumb" href="<?php echo $h($p['url']); ?>" aria-label="<?php echo $h($p['title']); ?>"><?php if ($p['image'] !== ''): ?><img src="<?php echo $h($p['image']); ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></a>
          <div class="bc-body">
            <h3><a href="<?php echo $h($p['url']); ?>"><?php echo $h($p['title']); ?></a></h3>
            <p><?php echo $h($p['summary']); ?></p>
            <div class="bc-m"><?php echo $h(date('M j, Y', strtotime((string) $p['date']) ?: time())); ?> · <?php echo $h($p['read']); ?></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
      <nav class="bc-pages" aria-label="Pages"><?php for ($i = 1; $i <= $pages; $i++): ?><?php echo $i === $pageNo ? '<span class="cur">' . $i . '</span>' : '<a href="' . $h($catUrl((string) $cat['slug'], $i)) . '">' . $i . '</a>'; ?><?php endfor; ?></nav>
    <?php endif; ?>
  <?php endif; ?>
</div></div>
<script>
(function () {
  var r = document.getElementById('bcHero'); if (!r) return;
  var s = r.querySelectorAll('.bc-slide'), d = r.querySelectorAll('.bc-dots button'); if (s.length < 2) return;
  var i = 0, t;
  function show(n) { i = (n + s.length) % s.length; s.forEach(function (x, k) { x.classList.toggle('on', k === i); x.setAttribute('aria-hidden', k === i ? 'false' : 'true'); }); d.forEach(function (x, k) { x.classList.toggle('on', k === i); }); }
  function play() { clearInterval(t); t = setInterval(function () { show(i + 1); }, 5500); }
  r.querySelector('.prev').onclick = function () { show(i - 1); play(); };
  r.querySelector('.next').onclick = function () { show(i + 1); play(); };
  d.forEach(function (x, k) { x.onclick = function () { show(k); play(); }; });
  r.addEventListener('mouseenter', function () { clearInterval(t); }); r.addEventListener('mouseleave', play);
  var x0 = null; r.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
  r.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) { show(i + (dx < 0 ? 1 : -1)); play(); } x0 = null; });
  play();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
