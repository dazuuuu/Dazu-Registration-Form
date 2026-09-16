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
            'summary' => $b['excerpt'] ?: mb_strimwidth(strip_tags($b['content'] ?? ''), 0, 160, '…'),
            'author' => $b['author_name'] ?? 'Dazu Training Institute',
            'read_time' => max(1, (int) ceil($wordCount / 200)) . ' min read',
            'url' => url('blogs/readmore.php?slug=' . rawurlencode($b['slug'])),
            'image' => $b['featured_image'] ?? '',
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

ob_start();
?>
<div class="bg-white min-h-screen py-16 text-slate-900">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 space-y-16">
    <div class="max-w-2xl">
      <span class="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full">Latest Insights</span>
      <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 mt-4">The Dazu Training Institute Blog</h1>
      <p class="text-slate-600 font-medium leading-relaxed mt-3">Stay updated with modern technologies, cybersecurity practices, coding tips, and inspiring activities from our Nairobi, Thika, and online learning community.</p>
    </div>

    <form method="GET" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center border-b border-slate-100 pb-8">
      <div class="relative lg:col-span-4">
        <svg class="absolute left-3.5 top-3.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search articles, topics, authors..."
               class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:outline-none focus:border-green-600 focus:bg-white">
      </div>
      <div class="lg:col-span-8 flex flex-wrap items-center gap-1.5">
        <?php foreach ($categories as $cat): ?>
          <?php
            $active = $categoryFilter === $cat;
            $qs = [];
            if ($cat !== 'All') $qs['category'] = $cat;
            if ($search !== '') $qs['search'] = $search;
            $href = url('blogs/index.php' . ($qs ? '?' . http_build_query($qs) : ''));
          ?>
          <a href="<?php echo htmlspecialchars($href); ?>"
             class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider rounded-xl border transition-all <?php echo $active ? 'bg-green-600 text-white border-green-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'; ?>">
            <?php echo htmlspecialchars($cat === 'All' ? 'ALL' : strtoupper($cat)); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </form>

    <?php if (empty($articles)): ?>
      <div class="text-center py-20 border border-dashed border-slate-200 rounded-2xl bg-slate-50">
        <p class="text-slate-600 font-semibold">No articles match your search.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <?php foreach ($articles as $article): ?>
          <article class="bg-white border border-slate-200 rounded-2xl overflow-hidden hover:border-green-600 hover:shadow-md transition-all flex flex-col justify-between min-h-[260px]">
            <?php if (!empty($article['image'])): ?>
              <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="" class="w-full h-40 object-cover">
            <?php endif; ?>
            <div class="space-y-4 p-8 pb-0">
              <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-extrabold tracking-widest bg-green-50 text-green-700 border border-green-200 px-3 py-1 rounded-full"><?php echo htmlspecialchars(strtoupper($article['category'])); ?></span>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"><?php echo htmlspecialchars(strtoupper($article['read_time'])); ?></span>
              </div>
              <h2 class="text-lg md:text-xl font-extrabold text-slate-900 uppercase tracking-tight leading-snug"><?php echo htmlspecialchars($article['title']); ?></h2>
              <p class="text-slate-500 text-xs font-semibold leading-relaxed line-clamp-3"><?php echo htmlspecialchars($article['summary']); ?></p>
            </div>
            <div class="flex items-center justify-between pt-6 border-t border-slate-100 mt-6 p-8 pt-6">
              <span class="text-xs text-slate-500 font-semibold">By <?php echo htmlspecialchars($article['author']); ?></span>
              <a href="<?php echo htmlspecialchars($article['url']); ?>" class="text-xs font-extrabold text-green-600 hover:text-green-700">Read Article →</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
foreach ($articles as $article) {
    if (!empty($article['image'])) {
        $og_image = $article['image'];
        break;
    }
}
require __DIR__ . '/../templates/public/dazu_layout.php';
