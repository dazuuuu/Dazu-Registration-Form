<?php
// public/blogs/readmore.php

require_once __DIR__ . '/../../app/v1/init.php';

$slug = $_GET['slug'] ?? null;
if (!$slug) {
    redirect(url('blogs/index.php'));
}

$stmt = $pdo->prepare("
    SELECT b.*,
           c.name AS category_name, c.slug AS category_slug, c.color AS category_color,
           u.username AS author_name,
           TRIM(CONCAT(IFNULL(up.first_name, ''), ' ', IFNULL(up.last_name, ''))) AS author_display
    FROM blogs b
    LEFT JOIN blog_categories c ON b.category_id = c.id
    LEFT JOIN users u ON b.author_id = u.id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE b.slug = :slug AND b.status = 'published'
");
$stmt->execute([':slug' => $slug]);
$blog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blog) {
    redirect(url('blogs/index.php'));
}

$updateStmt = $pdo->prepare("UPDATE blogs SET view_count = view_count + 1 WHERE id = :id");
$updateStmt->execute([':id' => $blog['id']]);

$stmt = $pdo->prepare("SELECT * FROM blog_sections WHERE blog_id = :blog_id ORDER BY sort_order ASC");
$stmt->execute([':blog_id' => $blog['id']]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT t.* FROM blog_tags t
    INNER JOIN blog_tag_relations tr ON t.id = tr.tag_id
    WHERE tr.blog_id = :blog_id
");
$stmt->execute([':blog_id' => $blog['id']]);
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($blog['category_id'])) {
    $stmt = $pdo->prepare("
        SELECT b.*, c.name AS category_name
        FROM blogs b
        LEFT JOIN blog_categories c ON b.category_id = c.id
        WHERE b.status = 'published' AND b.id != :blog_id AND b.category_id = :category_id
        ORDER BY b.published_at DESC
        LIMIT 3
    ");
    $stmt->execute([':blog_id' => $blog['id'], ':category_id' => $blog['category_id']]);
} else {
    $stmt = $pdo->prepare("
        SELECT b.*, c.name AS category_name
        FROM blogs b
        LEFT JOIN blog_categories c ON b.category_id = c.id
        WHERE b.status = 'published' AND b.id != :blog_id
        ORDER BY b.published_at DESC
        LIMIT 3
    ");
    $stmt->execute([':blog_id' => $blog['id']]);
}
$relatedPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function blog_youtube_id(string $url): string {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return $matches[1] ?? '';
}

$authorLabel = trim($blog['author_display'] ?? '');
if ($authorLabel === '') {
    $authorLabel = $blog['author_name'] ?? 'Dazu Hub';
}

$publishedRaw = $blog['published_at'] ?? $blog['created_at'] ?? null;
$publishedLabel = $publishedRaw ? date('F j, Y', strtotime($publishedRaw)) : '';
$readMinutes = max(1, (int) ceil(str_word_count(strip_tags($blog['content'] ?? '')) / 200));
$views = (int) ($blog['view_count'] ?? 0);

$page_title = ($blog['meta_title'] ?? $blog['title']) . ' — Dazu hub Institute';
$page_description = $blog['meta_description'] ?? mb_strimwidth(strip_tags($blog['excerpt'] ?? $blog['content'] ?? ''), 0, 160, '…');

$shareUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');

$heroImage = $blog['featured_image'] ?? '';

ob_start();
?>
<div class="bg-white min-h-screen text-slate-900">
  <!-- Breadcrumb -->
  <div class="border-b border-slate-100 bg-slate-50">
    <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 py-4">
      <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500" aria-label="Breadcrumb">
        <a href="<?php echo url('/'); ?>" class="hover:text-[#15A24A]">Home</a>
        <span>/</span>
        <a href="<?php echo url('blogs/index.php'); ?>" class="hover:text-[#15A24A]">Blog</a>
        <span>/</span>
        <span class="text-slate-700 truncate max-w-[200px] sm:max-w-none"><?php echo htmlspecialchars($blog['title']); ?></span>
      </nav>
    </div>
  </div>

  <?php if ($heroImage !== ''): ?>
    <div class="relative w-full max-h-[420px] overflow-hidden bg-slate-900">
      <img src="<?php echo htmlspecialchars($heroImage); ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>"
           class="w-full h-[280px] sm:h-[360px] md:h-[420px] object-cover opacity-90">
      <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/30 to-transparent"></div>
      <div class="absolute bottom-0 left-0 right-0 max-w-[900px] mx-auto px-4 md:px-8 pb-8 md:pb-10">
        <?php if (!empty($blog['category_name'])): ?>
          <span class="inline-block text-white text-[10px] uppercase tracking-widest font-extrabold px-3 py-1 bg-[#15A24A] rounded-full mb-4">
            <?php echo htmlspecialchars(strtoupper($blog['category_name'])); ?>
          </span>
        <?php endif; ?>
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-white tracking-tight leading-tight">
          <?php echo htmlspecialchars($blog['title']); ?>
        </h1>
      </div>
    </div>
  <?php endif; ?>

  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 py-10 md:py-14">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14">
      <!-- Main article -->
      <article class="lg:col-span-8">
        <?php if ($heroImage === ''): ?>
          <header class="space-y-5 pb-8 border-b border-slate-200 mb-8">
            <?php if (!empty($blog['category_name'])): ?>
              <span class="inline-block text-[#15A24A] text-[10px] uppercase tracking-widest font-extrabold px-3 py-1.5 bg-green-50 border border-green-100 rounded-full">
                <?php echo htmlspecialchars(strtoupper($blog['category_name'])); ?>
              </span>
            <?php endif; ?>
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold tracking-tight leading-tight text-slate-900">
              <?php echo htmlspecialchars($blog['title']); ?>
            </h1>
          </header>
        <?php endif; ?>

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-500 font-semibold pb-8 border-b border-slate-100 <?php echo $heroImage !== '' ? '' : 'mb-8'; ?>">
          <span class="inline-flex items-center gap-2">
            <svg class="w-4 h-4 text-[#15A24A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            By <?php echo htmlspecialchars($authorLabel); ?>
          </span>
          <?php if ($publishedLabel !== ''): ?>
            <span class="inline-flex items-center gap-2">
              <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              <?php echo htmlspecialchars($publishedLabel); ?>
            </span>
          <?php endif; ?>
          <span class="inline-flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php echo $readMinutes; ?> min read
          </span>
          <?php if ($views > 0): ?>
            <span class="inline-flex items-center gap-2">
              <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <?php echo number_format($views); ?> views
            </span>
          <?php endif; ?>
        </div>

        <div class="space-y-8">
          <?php if (!empty($blog['excerpt'])): ?>
            <p class="text-lg md:text-xl font-bold text-slate-800 leading-relaxed border-l-4 border-[#15A24A] pl-5">
              <?php echo htmlspecialchars(strip_tags($blog['excerpt'])); ?>
            </p>
          <?php endif; ?>

          <div class="blog-article text-base text-slate-700 leading-relaxed space-y-5
                      [&_h2]:text-xl [&_h2]:md:text-2xl [&_h2]:font-extrabold [&_h2]:text-slate-900 [&_h2]:mt-10 [&_h2]:tracking-tight
                      [&_h3]:text-lg [&_h3]:font-bold [&_h3]:text-slate-900 [&_h3]:mt-8
                      [&_p]:mb-4 [&_strong]:text-slate-900
                      [&_a]:text-[#15A24A] [&_a]:font-semibold [&_a]:underline-offset-2 [&_a]:hover:underline
                      [&_img]:rounded-xl [&_img]:max-w-full [&_img]:border [&_img]:border-slate-200
                      [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6">
            <?php if (!empty($sections)): ?>
              <?php foreach ($sections as $section): ?>
                <?php if ($section['section_type'] === 'text_only'): ?>
                  <?php if ($section['title']): ?><h2><?php echo htmlspecialchars($section['title']); ?></h2><?php endif; ?>
                  <?php echo $section['content']; ?>

                <?php elseif ($section['section_type'] === 'text_image_left'): ?>
                  <div class="grid md:grid-cols-2 gap-8 items-center my-10">
                    <img src="<?php echo htmlspecialchars($section['media_url'] ?? ''); ?>" alt="" class="rounded-xl w-full border border-slate-200 order-2 md:order-1">
                    <div class="order-1 md:order-2">
                      <?php if ($section['title']): ?><h2><?php echo htmlspecialchars($section['title']); ?></h2><?php endif; ?>
                      <?php echo $section['content']; ?>
                    </div>
                  </div>

                <?php elseif ($section['section_type'] === 'text_image_right'): ?>
                  <div class="grid md:grid-cols-2 gap-8 items-center my-10">
                    <div>
                      <?php if ($section['title']): ?><h2><?php echo htmlspecialchars($section['title']); ?></h2><?php endif; ?>
                      <?php echo $section['content']; ?>
                    </div>
                    <img src="<?php echo htmlspecialchars($section['media_url'] ?? ''); ?>" alt="" class="rounded-xl w-full border border-slate-200">
                  </div>

                <?php elseif ($section['section_type'] === 'youtube' && !empty($section['media_url'])): ?>
                  <div class="aspect-video my-10 rounded-xl overflow-hidden border border-slate-200 shadow-sm">
                    <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?php echo htmlspecialchars(blog_youtube_id($section['media_url'])); ?>" allowfullscreen title="Video"></iframe>
                  </div>

                <?php elseif ($section['section_type'] === 'video' && !empty($section['media_url'])): ?>
                  <div class="aspect-video my-10 rounded-xl overflow-hidden border border-slate-200 shadow-sm">
                    <video controls class="w-full h-full"><source src="<?php echo htmlspecialchars($section['media_url']); ?>" type="video/mp4"></video>
                  </div>

                <?php elseif ($section['section_type'] === 'quote'): ?>
                  <blockquote class="my-8 p-6 border-l-4 border-[#15A24A] bg-green-50/50 rounded-r-xl italic text-slate-700">
                    <?php echo $section['content']; ?>
                    <?php if ($section['title']): ?><footer class="mt-3 not-italic text-sm text-slate-500 font-semibold">— <?php echo htmlspecialchars($section['title']); ?></footer><?php endif; ?>
                  </blockquote>

                <?php elseif ($section['section_type'] === 'code_block'): ?>
                  <pre class="bg-slate-900 text-slate-100 p-5 rounded-xl overflow-x-auto my-8 text-sm border border-slate-800"><code><?php echo htmlspecialchars($section['content'] ?? ''); ?></code></pre>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php else: ?>
              <?php echo $blog['content']; ?>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($relatedPosts)): ?>
          <div class="pt-12 mt-12 border-t border-slate-200 space-y-6">
            <h2 class="text-xl font-extrabold tracking-tight text-slate-900">Related Articles</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <?php foreach ($relatedPosts as $related): ?>
                <article class="bg-white border border-slate-200 rounded-2xl overflow-hidden hover:border-[#15A24A] hover:shadow-md transition-all group">
                  <?php if (!empty($related['featured_image'])): ?>
                    <img src="<?php echo htmlspecialchars($related['featured_image']); ?>" alt="" class="w-full h-36 object-cover group-hover:scale-105 transition-transform duration-300">
                  <?php else: ?>
                    <div class="h-36 bg-gradient-to-br from-[#15A24A] to-slate-800"></div>
                  <?php endif; ?>
                  <div class="p-5 space-y-2">
                    <?php if (!empty($related['category_name'])): ?>
                      <span class="text-[9px] font-extrabold uppercase tracking-widest text-[#15A24A]"><?php echo htmlspecialchars($related['category_name']); ?></span>
                    <?php endif; ?>
                    <h3 class="text-sm font-extrabold leading-snug">
                      <a href="<?php echo url('blogs/readmore.php?slug=' . rawurlencode($related['slug'])); ?>" class="text-slate-900 group-hover:text-[#15A24A]">
                        <?php echo htmlspecialchars($related['title']); ?>
                      </a>
                    </h3>
                    <?php if (!empty($related['published_at'])): ?>
                      <p class="text-[10px] text-slate-400 font-bold"><?php echo date('M d, Y', strtotime($related['published_at'])); ?></p>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </article>

      <!-- Sidebar -->
      <aside class="lg:col-span-4 space-y-6">
        <div class="sticky top-24 space-y-6">
          <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl">
            <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-500 mb-4">Share this article</p>
            <div class="flex flex-wrap gap-2">
              <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($shareUrl); ?>" target="_blank" rel="noopener"
                 class="flex-1 min-w-[70px] py-2.5 text-center text-xs font-bold bg-[#3b5998] text-white rounded-lg hover:opacity-90">Facebook</a>
              <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($shareUrl); ?>&text=<?php echo urlencode($blog['title']); ?>" target="_blank" rel="noopener"
                 class="flex-1 min-w-[70px] py-2.5 text-center text-xs font-bold bg-slate-900 text-white rounded-lg hover:opacity-90">X</a>
              <a href="https://wa.me/?text=<?php echo urlencode($blog['title'] . ' - ' . $shareUrl); ?>" target="_blank" rel="noopener"
                 class="flex-1 min-w-[70px] py-2.5 text-center text-xs font-bold bg-[#25d366] text-white rounded-lg hover:opacity-90">WhatsApp</a>
            </div>
          </div>

          <?php if (!empty($tags)): ?>
            <div class="p-6 bg-white border border-slate-200 rounded-2xl">
              <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-500 mb-3">Tags</p>
              <div class="flex flex-wrap gap-2">
                <?php foreach ($tags as $tag): ?>
                  <a href="<?php echo url('blogs/tag.php?slug=' . rawurlencode($tag['slug'])); ?>"
                     class="px-3 py-1.5 text-xs font-semibold bg-slate-50 border border-slate-200 rounded-full text-slate-600 hover:bg-[#15A24A] hover:border-[#15A24A] hover:text-white transition-colors">
                    <?php echo htmlspecialchars($tag['name']); ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="p-6 bg-[#15A24A] rounded-2xl text-white">
            <p class="text-sm font-extrabold leading-snug">Ready to start your tech career?</p>
            <p class="text-xs text-green-100 mt-2 font-medium">Explore our hands-on courses at Dazu Hub, Thika Town.</p>
            <a href="<?php echo url('courses/index.php'); ?>" class="mt-4 inline-block w-full text-center py-3 bg-white text-[#15A24A] text-xs font-extrabold uppercase tracking-wider rounded-lg hover:bg-green-50">
              View Courses →
            </a>
          </div>

          <a href="<?php echo url('blogs/index.php'); ?>" class="block text-center py-3 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:border-[#15A24A] hover:text-[#15A24A]">
            ← Back to all articles
          </a>
        </div>
      </aside>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();

$extra_head = '';
if (!empty($blog['meta_keywords'])) {
    $extra_head .= '<meta name="keywords" content="' . htmlspecialchars($blog['meta_keywords']) . '">' . "\n";
}
$extra_head .= '<meta property="og:title" content="' . htmlspecialchars($blog['title']) . '">' . "\n";
$extra_head .= '<meta property="og:description" content="' . htmlspecialchars($page_description) . '">' . "\n";
$extra_head .= '<meta property="og:type" content="article">' . "\n";
if (!empty($blog['published_at'])) {
    $extra_head .= '<meta property="article:published_time" content="' . htmlspecialchars($blog['published_at']) . '">' . "\n";
}
if (!empty($blog['featured_image'])) {
    $extra_head .= '<meta property="og:image" content="' . htmlspecialchars($blog['featured_image']) . '">' . "\n";
}

require __DIR__ . '/../templates/public/dazu_layout.php';
