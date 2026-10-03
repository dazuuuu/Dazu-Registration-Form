<?php
$page_title = 'The Dazu Training Institute Blog — Dazu Training Institute';
$page_description = 'Tech guides, campus life, career tips and AI trends from Dazu Training Institute in Nairobi, Thika and online.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';

use Models\Connection;

$pdo = Connection::getInstance()->getConnection();
$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? 'All';
$categories = ['All', 'Tech Guides', 'Campus Life', 'Career Tips', 'AI Trends'];
try {
    // Categories managed in Admin → Blog → Categories (fallback list only when none exist yet)
    $dbCats = $pdo->query("SELECT name FROM blog_categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    if ($dbCats) {
        $categories = array_merge(['All'], $dbCats);
    }
} catch (Throwable $e) {
}

/** Readable one-paragraph excerpt: no HTML, no markdown/divider symbols (#, ===, ***), collapsed whitespace. */
function blog_plain_summary(string $text): string
{
    $t = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
    $t = preg_replace(['/^\s*#+\s*/m', '/[=*_~`#>\-]{3,}/', '/\s+/u'], ['', ' ', ' '], $t);
    return mb_strimwidth(trim($t), 0, 160, '…');
}

/** Stored image paths include the install folder they were uploaded under (e.g. /Registration-System/public/uploads/…); rebuild them for this site. */
function blog_image_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '' || preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }
    if (preg_match('#(uploads/.+)$#', $path, $m)) {
        return url($m[1]);
    }
    return $path;
}
$sort = ($_GET['sort'] ?? '') === 'popular' ? 'popular' : 'latest';

function dazu_static_articles(): array
{
    return [
        ['slug' => 'software-career-kenya-2026', 'title' => 'How to Switch to a Software Career in Kenya in 2026', 'category' => 'Career Tips', 'summary' => 'A practical step-by-step blueprint detailing how to successfully transition from non-technical fields into software engineering with local labs.', 'author' => 'Eng. Davis Kamau', 'read_time' => '5 min read', 'date' => 'July 2, 2026'],
        ['slug' => 'ai-agents-after-chatgpt', 'title' => 'Demystifying AI Agents: The Next Big Shift After ChatGPT', 'category' => 'AI Trends', 'summary' => 'Understand what custom AI agents are, how they automate complex multi-step workflows, and why they are replacing simple static prompt models.', 'author' => 'Dr. Catherine Wambui', 'read_time' => '7 min read', 'date' => 'June 28, 2026'],
        ['slug' => 'cybersecurity-thika-msme', 'title' => 'Top 5 Cybersecurity Pitfalls of Thika Small Businesses', 'category' => 'Tech Guides', 'summary' => 'Simple security audits and crucial measures every local shop or MSME should implement immediately to safeguard data.', 'author' => 'Mark Mwenda', 'read_time' => '4 min read', 'date' => 'June 15, 2026'],
        ['slug' => 'student-spotlight-thika-soko', 'title' => 'Student Spotlight: Coding for Social Impact in Kiambu County', 'category' => 'Campus Life', 'summary' => 'How Eunice and her student peers coded Thika Soko Hub during laboratory sessions to assist local vegetable vendors.', 'author' => 'Eunice Wangari', 'read_time' => '3 min read', 'date' => 'May 30, 2026'],
    ];
}

$articles = [];
try {
    $sql = "SELECT b.*, c.name AS category_name, c.slug AS category_slug, u.username AS author_name
            FROM blogs b
            LEFT JOIN blog_categories c ON b.category_id = c.id
            LEFT JOIN users u ON b.author_id = u.id
            WHERE b.status = 'published'";
    $params = [];
    if ($categoryFilter !== 'All') {
        $sql .= " AND c.name = :cat";
        $params[':cat'] = $categoryFilter;
    }
    if ($search !== '') {
        $sql .= " AND (b.title LIKE :q OR b.excerpt LIKE :q OR b.content LIKE :q)";
        $params[':q'] = '%' . $search . '%';
    }
    $sql .= " ORDER BY b.published_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $b) {
        $wordCount = str_word_count(strip_tags($b['content'] ?? ''));
        $articles[] = [
            'slug' => $b['slug'],
            'title' => $b['title'],
            'category' => $b['category_name'] ?? 'Tech Guides',
            'summary' => blog_plain_summary($b['excerpt'] ?: ($b['content'] ?? '')),
            'author' => $b['author_name'] ?? 'Dazu Training Institute',
            'read_time' => max(1, (int) ceil($wordCount / 200)) . ' min read',
            'url' => url('blogs/readmore.php?slug=' . rawurlencode($b['slug'])),
            'image' => blog_image_url($b['featured_image'] ?? ''),
            'date' => $b['published_at'] ?: $b['created_at'],
            'popular' => !empty($b['is_featured']),
            'views' => (int) ($b['view_count'] ?? 0),
        ];
    }
} catch (Throwable $e) {
    $articles = [];
}

if (empty($articles)) {
    $articles = array_map(static function ($a) {
        $a['url'] = url('blogs/readmore.php?slug=' . rawurlencode($a['slug']));
        return $a;
    }, dazu_static_articles());
    if ($categoryFilter !== 'All') {
        $articles = array_values(array_filter($articles, static fn($a) => $a['category'] === $categoryFilter));
    }
    if ($search !== '') {
        $articles = array_values(array_filter($articles, static function ($a) use ($search) {
            return stripos($a['title'], $search) !== false || stripos($a['summary'], $search) !== false;
        }));
    }
}

// Top section: a slider of posts the admin ticked as "popular" + the 4 newest posts beside it.
// No popular posts → the whole top section is hidden and the grid starts straight away.
$filtered = $search !== '' || $categoryFilter !== 'All';
$popular = [];
$latest = [];
if (!$filtered) {
    $popular = array_values(array_filter($articles, static fn($a) => !empty($a['popular'])));
    usort($popular, static fn($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
    $popular = array_slice($popular, 0, 8);
    $latest = array_slice($articles, 0, 4); // $articles is already newest first
}

// Grid: every post matching the current tab/search (nothing is left out), 4 per row, paginated.
$gridArticles = $articles;
if ($sort === 'popular') {
    usort($gridArticles, static fn($a, $b) => [(int) !empty($b['popular']), $b['views'] ?? 0] <=> [(int) !empty($a['popular']), $a['views'] ?? 0]);
}
$perPage = 16;
$pages = max(1, (int) ceil(count($gridArticles) / $perPage));
$pageNo = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$pageArticles = array_slice($gridArticles, ($pageNo - 1) * $perPage, $perPage);

$blogHref = static function (array $over) use ($categoryFilter, $search, $sort): string {
    $qs = ['category' => $categoryFilter, 'search' => $search, 'sort' => $sort];
    $qs = array_filter(array_merge($qs, $over), static fn($v) => $v !== null && $v !== '' && $v !== 'All' && $v !== 'latest' && $v !== 1);
    return url('blogs/index.php' . ($qs ? '?' . http_build_query($qs) : ''));
};
$fmtDate = static fn($a) => date('M j', strtotime($a['date'] ?? 'now') ?: time());
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<style>
.bl{background:#fff;color:#1f2937;padding:48px 16px 72px}
.bl *{box-sizing:border-box}
.bl-wrap{max-width:1180px;margin:0 auto}
.bl a{text-decoration:none;color:inherit}
.bl h1,.bl h2,.bl h3,.bl p{margin:0;text-transform:none;letter-spacing:normal}
.bl-sec{font-size:22px;font-weight:600;color:#111827;letter-spacing:-.01em}
.bl-chip{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:500;color:#374151;background:#f3f4f6;border-radius:999px;padding:4px 10px;line-height:1.3;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bl-chip i{width:7px;height:7px;border-radius:50%;background:#16a34a;flex:none}
.bl-meta{display:flex;align-items:center;gap:6px;font-size:11px;color:#6b7280}
.bl-meta b{width:3px;height:3px;border-radius:50%;background:#9ca3af;font-weight:400}

/* ── Top: popular slider + latest list ── */
.bl-top{display:grid;grid-template-columns:1fr;gap:28px;padding-bottom:36px;margin-bottom:36px;border-bottom:1px solid #eef0f2}
@media(min-width:900px){.bl-top{grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:28px}}
.bl-slider{position:relative;border-radius:14px;overflow:hidden;background:#111827;aspect-ratio:16/10;min-height:260px;max-height:430px;width:100%}
.bl-slide{position:absolute;inset:0;opacity:0;visibility:hidden;transition:opacity .6s,visibility .6s}
.bl-slide.on{opacity:1;visibility:visible}
.bl-slide img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;max-width:none}
.bl-slide::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 40%,rgba(17,24,39,.78))}
.bl-slide.noimg{background:linear-gradient(135deg,#16a34a,#14532d 60%,#111827)}
.bl-slide-in{position:absolute;left:0;right:0;bottom:0;z-index:1;padding:22px 24px 26px;color:#fff}
.bl-slide-in .bl-chip{background:rgba(255,255,255,.92)}
.bl-slide-in h2{font-size:clamp(19px,2.4vw,26px);line-height:1.25;font-weight:500;color:#fff;margin:10px 0 8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;max-width:90%}
.bl-slide-in .bl-meta{color:rgba(255,255,255,.85)}
.bl-slide-in .bl-meta b{background:rgba(255,255,255,.7)}
.bl-dots{position:absolute;right:20px;bottom:24px;z-index:2;display:flex;gap:6px}
.bl-dots button{width:8px;height:8px;border-radius:999px;border:0;padding:0;background:rgba(255,255,255,.5);cursor:pointer;transition:width .25s,background .25s}
.bl-dots button.on{width:22px;background:#fff}
.bl-arr{position:absolute;top:50%;transform:translateY(-50%);z-index:2;width:36px;height:36px;border-radius:50%;border:0;background:rgba(255,255,255,.85);color:#111827;cursor:pointer;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s}
.bl-slider:hover .bl-arr{opacity:1}
.bl-arr.prev{left:14px}.bl-arr.next{right:14px}
.bl-latest .bl-sec{margin-bottom:16px}
.bl-li{display:flex;gap:14px;align-items:center;padding:8px 0}
.bl-li + .bl-li{margin-top:8px}
.bl-li-img{width:84px;height:84px;flex:none;border-radius:8px;overflow:hidden;background:linear-gradient(135deg,#16a34a,#14532d)}
.bl-li-img img{width:100%;height:100%;object-fit:cover;display:block;max-width:none}
.bl-li h3{font-size:14px;line-height:1.35;font-weight:500;color:#111827;margin-bottom:6px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.bl-li:hover h3{color:#15803d}

/* ── Filters ── */
.bl-bar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;margin-bottom:22px}
.bl-tabs{display:flex;flex-wrap:wrap;gap:6px}
.bl-tabs a{font-size:13px;font-weight:500;color:#4b5563;padding:7px 14px;border-radius:999px;background:#f3f4f6;transition:.15s}
.bl-tabs a:hover{background:#e5e7eb}
.bl-tabs a.on{background:#111827;color:#fff}
.bl-tools{display:flex;gap:8px;flex-wrap:wrap}
.bl-search{position:relative;display:block}
.bl-search input{width:220px;max-width:100%;border:1px solid #e5e7eb;background:#fff;border-radius:999px;padding:8px 14px 8px 34px;font-size:13px}
.bl-search input:focus{outline:none;border-color:#86efac}
.bl-search svg{position:absolute;left:12px;top:10px;color:#9ca3af;display:block}
.bl-sort{border:1px solid #e5e7eb;background:#fff;border-radius:999px;padding:8px 14px;font-size:13px;cursor:pointer}

/* ── Grid of small cards: 4 per row ── */
.bl-grid{display:grid;grid-template-columns:1fr;gap:20px}
@media(min-width:560px){.bl-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1000px){.bl-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
.bl-card{display:flex;flex-direction:column;background:#fafaf9;border-radius:14px;padding:6px 6px 16px;transition:background .2s,box-shadow .2s;min-width:0}
.bl-card:hover{background:#fff;box-shadow:0 10px 28px rgba(17,24,39,.08)}
.bl-thumb{display:block;aspect-ratio:16/10;border-radius:10px;overflow:hidden;background:linear-gradient(135deg,#16a34a,#14532d)}
.bl-thumb img{width:100%;height:100%;object-fit:cover;display:block;max-width:none;transition:transform .5s}
.bl-card:hover .bl-thumb img{transform:scale(1.05)}
.bl-card .bl-meta{width:100%}
.bl-more{margin-left:auto;display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:500;color:#15803d;opacity:0;transform:translateX(-4px);transition:opacity .2s,transform .2s}
.bl-more svg{display:block;transition:transform .2s}
.bl-card:hover .bl-more,.bl-more:focus-visible{opacity:1;transform:none}
.bl-more:hover svg{transform:translateX(2px)}
@media(hover:none){.bl-more{opacity:1;transform:none}}
.bl-body{padding:12px 8px 0;display:flex;flex-direction:column;flex:1;min-width:0}
.bl-body .bl-chip{align-self:flex-start;background:#f0f0ee}
.bl-card h3{font-size:16px;line-height:1.35;font-weight:600;color:#111827;margin:10px 0 6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.7em}
.bl-card h3 a:hover{color:#15803d}
.bl-card p{font-size:13px;line-height:1.55;color:#6b7280;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:4.65em;margin-bottom:10px}
.bl-card .bl-meta{margin-top:auto}
.bl-empty{text-align:center;padding:60px 20px;border:1px dashed #d1d5db;border-radius:14px;color:#6b7280}

/* ── Pagination ── */
.bl-pages{display:flex;justify-content:space-between;align-items:center;margin-top:36px;padding-top:24px;border-top:1px solid #eef0f2}
.bl-pages .nums{display:flex;gap:6px}
.bl-pages a,.bl-pages span{min-width:30px;height:30px;padding:0 8px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;color:#374151}
.bl-pages a:hover{background:#f3f4f6}
.bl-pages .cur{background:#111827;color:#fff}
.bl-pages .dis{opacity:.3}
</style>
<div class="bl"><div class="bl-wrap">

  <?php if ($popular): ?>
  <section class="bl-top">
    <div class="bl-slider" id="blSlider" aria-roledescription="carousel" aria-label="Popular posts">
      <?php foreach ($popular as $i => $a): ?>
        <a class="bl-slide<?php echo $i === 0 ? ' on' : ''; ?><?php echo empty($a['image']) ? ' noimg' : ''; ?>" href="<?php echo $h($a['url']); ?>" aria-hidden="<?php echo $i === 0 ? 'false' : 'true'; ?>">
          <?php if (!empty($a['image'])): ?><img src="<?php echo $h($a['image']); ?>" alt="" <?php echo $i ? 'loading="lazy"' : ''; ?> onerror="this.remove()"><?php endif; ?>
          <div class="bl-slide-in">
            <span class="bl-chip"><i></i><?php echo $h($a['category']); ?></span>
            <h2><?php echo $h($a['title']); ?></h2>
            <div class="bl-meta"><?php echo $h($fmtDate($a)); ?><b></b><?php echo $h($a['read_time']); ?></div>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (count($popular) > 1): ?>
        <button class="bl-arr prev" type="button" aria-label="Previous"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></button>
        <button class="bl-arr next" type="button" aria-label="Next"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button>
        <div class="bl-dots"><?php foreach ($popular as $i => $a): ?><button type="button" class="<?php echo $i === 0 ? 'on' : ''; ?>" aria-label="Show popular post <?php echo $i + 1; ?>"></button><?php endforeach; ?></div>
      <?php endif; ?>
    </div>

    <aside class="bl-latest">
      <h2 class="bl-sec">Latest post</h2>
      <?php foreach ($latest as $a): ?>
        <a class="bl-li" href="<?php echo $h($a['url']); ?>">
          <span class="bl-li-img"><?php if (!empty($a['image'])): ?><img src="<?php echo $h($a['image']); ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></span>
          <span>
            <h3><?php echo $h($a['title']); ?></h3>
            <span class="bl-meta"><?php echo $h($fmtDate($a)); ?><b></b><?php echo $h($a['read_time']); ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </aside>
  </section>
  <?php endif; ?>

  <div class="bl-bar">
    <nav class="bl-tabs" aria-label="Categories">
      <?php foreach ($categories as $cat): ?>
        <a href="<?php echo $h($blogHref(['category' => $cat, 'page' => null])); ?>" class="<?php echo $categoryFilter === $cat ? 'on' : ''; ?>"><?php echo $h($cat); ?></a>
      <?php endforeach; ?>
    </nav>
    <form method="GET" class="bl-tools">
      <?php if ($categoryFilter !== 'All'): ?><input type="hidden" name="category" value="<?php echo $h($categoryFilter); ?>"><?php endif; ?>
      <label class="bl-search">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="search" name="search" value="<?php echo $h($search); ?>" placeholder="Search articles…" aria-label="Search articles">
      </label>
      <select name="sort" class="bl-sort" onchange="this.form.submit()" aria-label="Sort">
        <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest</option>
        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Popular</option>
      </select>
    </form>
  </div>

  <?php if (!$pageArticles): ?>
    <div class="bl-empty">No articles match your search.</div>
  <?php else: ?>
    <div class="bl-grid">
      <?php foreach ($pageArticles as $a): ?>
        <article class="bl-card">
          <a class="bl-thumb" href="<?php echo $h($a['url']); ?>" aria-label="Read more: <?php echo $h($a['title']); ?>"><?php if (!empty($a['image'])): ?><img src="<?php echo $h($a['image']); ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></a>
          <div class="bl-body">
            <span class="bl-chip"><i></i><?php echo $h($a['category']); ?></span>
            <h3><a href="<?php echo $h($a['url']); ?>"><?php echo $h($a['title']); ?></a></h3>
            <p><?php echo $h($a['summary']); ?></p>
            <div class="bl-meta"><?php echo $h($fmtDate($a)); ?><b></b><?php echo $h($a['read_time']); ?>
              <a class="bl-more" href="<?php echo $h($a['url']); ?>">Read more <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="bl-pages" aria-label="Pages">
        <?php if ($pageNo > 1): ?><a href="<?php echo $h($blogHref(['page' => $pageNo - 1])); ?>" aria-label="Previous page">&larr;</a><?php else: ?><span class="dis">&larr;</span><?php endif; ?>
        <div class="nums">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <?php if ($p === $pageNo): ?><span class="cur"><?php echo $p; ?></span><?php else: ?><a href="<?php echo $h($blogHref(['page' => $p])); ?>"><?php echo $p; ?></a><?php endif; ?>
          <?php endfor; ?>
        </div>
        <?php if ($pageNo < $pages): ?><a href="<?php echo $h($blogHref(['page' => $pageNo + 1])); ?>" aria-label="Next page">&rarr;</a><?php else: ?><span class="dis">&rarr;</span><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div></div>
<script>
(function () {
  const root = document.getElementById('blSlider');
  if (!root) return;
  const slides = root.querySelectorAll('.bl-slide'), dots = root.querySelectorAll('.bl-dots button');
  if (slides.length < 2) return;
  let i = 0, t;
  const show = n => {
    i = (n + slides.length) % slides.length;
    slides.forEach((s, k) => { s.classList.toggle('on', k === i); s.setAttribute('aria-hidden', k === i ? 'false' : 'true'); });
    dots.forEach((d, k) => d.classList.toggle('on', k === i));
  };
  const play = () => { clearInterval(t); t = setInterval(() => show(i + 1), 5000); };
  root.querySelector('.prev').onclick = e => { e.preventDefault(); show(i - 1); play(); };
  root.querySelector('.next').onclick = e => { e.preventDefault(); show(i + 1); play(); };
  dots.forEach((d, k) => d.onclick = e => { e.preventDefault(); show(k); play(); });
  root.addEventListener('mouseenter', () => clearInterval(t));
  root.addEventListener('mouseleave', play);
  let x0 = null;
  root.addEventListener('touchstart', e => x0 = e.touches[0].clientX, {passive: true});
  root.addEventListener('touchend', e => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) { show(i + (dx < 0 ? 1 : -1)); play(); } x0 = null; });
  play();
})();
</script>
<?php
$content = ob_get_clean();
foreach ($articles as $article) {
    if (!empty($article['image'])) {
        $og_image = $article['image'];
        break;
    }
}
require __DIR__ . '/../templates/public/dazu_layout.php';
