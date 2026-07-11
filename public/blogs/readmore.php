<?php
// public/blogs/readmore.php

require_once __DIR__ . '/../../app/v1/init.php';

$slug = $_GET['slug'] ?? null;
if (!$slug) {
    redirect(url('blogs/index.php'));
}

// Get blog post with all details
$stmt = $pdo->prepare("
    SELECT b.*, c.name as category_name, c.slug as category_slug, c.color as category_color,
           u.username as author_name
    FROM blogs b
    LEFT JOIN blog_categories c ON b.category_id = c.id
    LEFT JOIN users u ON b.author_id = u.id
    WHERE b.slug = :slug AND b.status = 'published'
");
$stmt->execute([':slug' => $slug]);
$blog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blog) {
    redirect(url('blogs/index.php'));
}

// Update view count
$updateStmt = $pdo->prepare("UPDATE blogs SET view_count = view_count + 1 WHERE id = :id");
$updateStmt->execute([':id' => $blog['id']]);

// Get blog sections
$stmt = $pdo->prepare("SELECT * FROM blog_sections WHERE blog_id = :blog_id ORDER BY sort_order ASC");
$stmt->execute([':blog_id' => $blog['id']]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get blog tags
$stmt = $pdo->prepare("
    SELECT t.* FROM blog_tags t
    INNER JOIN blog_tag_relations tr ON t.id = tr.tag_id
    WHERE tr.blog_id = :blog_id
");
$stmt->execute([':blog_id' => $blog['id']]);
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get related posts
$stmt = $pdo->prepare("
    SELECT b.*, c.name as category_name
    FROM blogs b
    LEFT JOIN blog_categories c ON b.category_id = c.id
    WHERE b.status = 'published' AND b.id != :blog_id
    AND b.category_id = :category_id
    ORDER BY b.published_at DESC
    LIMIT 3
");
$stmt->execute([':blog_id' => $blog['id'], ':category_id' => $blog['category_id']]);
$relatedPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getYoutubeId($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return $matches[1] ?? '';
}

$page_title = ($blog['meta_title'] ?? $blog['title']) . ' — Dazu hub Institute';
$page_description = $blog['meta_description'] ?? mb_strimwidth(strip_tags($blog['excerpt'] ?? $blog['content'] ?? ''), 0, 160, '…');

$shareUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');
$readMinutes = max(1, (int) ceil(str_word_count(strip_tags($blog['content'] ?? '')) / 200));

ob_start();
?>
<div class="bg-white min-h-screen text-slate-900">
  <!-- Hero -->
  <section class="bg-slate-900 text-white py-16 md:py-20 relative overflow-hidden">
    <div class="absolute inset-0 opacity-30">
      <?php if (!empty($blog['featured_image'])): ?>
        <img src="<?php echo htmlspecialchars($blog['featured_image']); ?>" alt="" class="w-full h-full object-cover">
      <?php endif; ?>
    </div>
    <div class="absolute inset-0 bg-gradient-to-r from-slate-900 via-slate-900/90 to-slate-900/70"></div>
    <div class="relative max-w-[850px] mx-auto px-4 md:px-8 space-y-4">
      <nav class="flex items-center gap-2 text-[10px] uppercase tracking-widest text-slate-400 font-bold">
        <a href="<?php echo url('/'); ?>" class="hover:text-green-400 transition-colors">Home</a>
        <span>/</span>
        <a href="<?php echo url('blogs/index.php'); ?>" class="hover:text-green-400 transition-colors">Blog</a>
      </nav>
      <?php if (!empty($blog['category_name'])): ?>
        <span class="inline-block text-green-400 text-[10px] uppercase tracking-widest font-extrabold px-3 py-1 bg-green-400/10 border border-green-400/30 rounded-full">
          <?php echo htmlspecialchars($blog['category_name']); ?>
        </span>
      <?php endif; ?>
      <h1 class="text-3xl md:text-4xl lg:text-5xl font-extrabold tracking-tight leading-tight">
        <?php echo htmlspecialchars($blog['title']); ?>
      </h1>
      <p class="text-sm text-slate-300 font-medium">
        By <?php echo htmlspecialchars($blog['author_name'] ?? 'Dazu Hub'); ?>
        · <?php echo date('M d, Y', strtotime($blog['published_at'])); ?>
        · <?php echo $readMinutes; ?> min read
        · <?php echo number_format((int) ($blog['view_count'] ?? 0)); ?> views
      </p>
      <?php if (!empty($blog['excerpt'])): ?>
        <p class="text-slate-400 text-sm leading-relaxed max-w-2xl">
          <?php echo htmlspecialchars(strip_tags($blog['excerpt'])); ?>
        </p>
      <?php endif; ?>
    </div>
  </section>

  <article class="max-w-[850px] mx-auto px-4 md:px-8 py-12 md:py-16 space-y-10">
    <?php if (!empty($blog['featured_image'])): ?>
      <img src="<?php echo htmlspecialchars($blog['featured_image']); ?>"
           alt="<?php echo htmlspecialchars($blog['title']); ?>"
           class="w-full rounded-2xl shadow-lg border border-slate-200">
    <?php endif; ?>

    <div class="blog-article text-slate-700 leading-relaxed space-y-6 [&_h2]:text-2xl [&_h2]:font-extrabold [&_h2]:text-slate-900 [&_h2]:mt-10 [&_h3]:text-xl [&_h3]:font-bold [&_h3]:text-slate-900 [&_p]:mb-4 [&_a]:text-green-600 [&_a]:font-semibold [&_img]:rounded-xl [&_img]:max-w-full">
      <?php if (!empty($sections)): ?>
        <?php foreach ($sections as $section): ?>
          <?php if ($section['section_type'] === 'text_only'): ?>
            <?php if ($section['title']): ?>
              <h2><?php echo htmlspecialchars($section['title']); ?></h2>
            <?php endif; ?>
            <?php echo $section['content']; ?>

          <?php elseif ($section['section_type'] === 'text_image_left'): ?>
            <div class="grid md:grid-cols-2 gap-8 items-center my-10">
              <img src="<?php echo htmlspecialchars($section['media_url']); ?>" alt="" class="rounded-xl w-full order-2 md:order-1">
              <div class="order-1 md:order-2">
                <?php if ($section['title']): ?>
                  <h2><?php echo htmlspecialchars($section['title']); ?></h2>
                <?php endif; ?>
                <?php echo $section['content']; ?>
              </div>
            </div>

          <?php elseif ($section['section_type'] === 'text_image_right'): ?>
            <div class="grid md:grid-cols-2 gap-8 items-center my-10">
              <div>
                <?php if ($section['title']): ?>
                  <h2><?php echo htmlspecialchars($section['title']); ?></h2>
                <?php endif; ?>
                <?php echo $section['content']; ?>
              </div>
              <img src="<?php echo htmlspecialchars($section['media_url']); ?>" alt="" class="rounded-xl w-full">
            </div>

          <?php elseif ($section['section_type'] === 'youtube' && $section['media_url']): ?>
            <div class="aspect-video my-10 rounded-xl overflow-hidden border border-slate-200">
              <iframe class="w-full h-full"
                      src="https://www.youtube.com/embed/<?php echo htmlspecialchars(getYoutubeId($section['media_url'])); ?>"
                      allowfullscreen title="Video"></iframe>
            </div>

          <?php elseif ($section['section_type'] === 'video' && $section['media_url']): ?>
            <div class="aspect-video my-10 rounded-xl overflow-hidden border border-slate-200">
              <video controls class="w-full h-full">
                <source src="<?php echo htmlspecialchars($section['media_url']); ?>" type="video/mp4">
              </video>
            </div>

          <?php elseif ($section['section_type'] === 'quote'): ?>
            <blockquote class="my-8 p-6 border-l-4 border-green-600 bg-slate-50 rounded-r-xl italic">
              <?php echo $section['content']; ?>
              <?php if ($section['title']): ?>
                <footer class="mt-3 not-italic text-sm text-slate-500">— <?php echo htmlspecialchars($section['title']); ?></footer>
              <?php endif; ?>
            </blockquote>

          <?php elseif ($section['section_type'] === 'code_block'): ?>
            <pre class="bg-slate-900 text-slate-100 p-5 rounded-xl overflow-x-auto my-8 text-sm"><code><?php echo htmlspecialchars($section['content']); ?></code></pre>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <?php echo $blog['content']; ?>
      <?php endif; ?>
    </div>

    <?php if (!empty($tags)): ?>
      <div class="pt-8 border-t border-slate-200">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500 mb-3">Tags</p>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($tags as $tag): ?>
            <a href="<?php echo url('blogs/tag.php?slug=' . rawurlencode($tag['slug'])); ?>"
               class="px-3 py-1.5 text-xs font-semibold bg-slate-50 border border-slate-200 rounded-full text-slate-600 hover:bg-green-600 hover:border-green-600 hover:text-white transition-colors">
              <?php echo htmlspecialchars($tag['name']); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl text-center">
      <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Share this article</p>
      <div class="flex justify-center gap-3 mt-4">
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($shareUrl); ?>" target="_blank" rel="noopener"
           class="w-10 h-10 rounded-full bg-[#3b5998] text-white flex items-center justify-center hover:opacity-90" aria-label="Share on Facebook">f</a>
        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($shareUrl); ?>&text=<?php echo urlencode($blog['title']); ?>" target="_blank" rel="noopener"
           class="w-10 h-10 rounded-full bg-[#1da1f2] text-white flex items-center justify-center hover:opacity-90" aria-label="Share on X">𝕏</a>
        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($shareUrl); ?>" target="_blank" rel="noopener"
           class="w-10 h-10 rounded-full bg-[#0077b5] text-white flex items-center justify-center hover:opacity-90" aria-label="Share on LinkedIn">in</a>
        <a href="https://wa.me/?text=<?php echo urlencode($blog['title'] . ' - ' . $shareUrl); ?>" target="_blank" rel="noopener"
           class="w-10 h-10 rounded-full bg-[#25d366] text-white flex items-center justify-center hover:opacity-90" aria-label="Share on WhatsApp">WA</a>
      </div>
    </div>

    <?php if (!empty($relatedPosts)): ?>
      <div class="pt-8 border-t border-slate-200 space-y-6">
        <h2 class="text-xl font-extrabold text-slate-900">Related Posts</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <?php foreach ($relatedPosts as $related): ?>
            <article class="bg-white border border-slate-200 rounded-2xl overflow-hidden hover:border-green-600 hover:shadow-md transition-all">
              <?php if ($related['featured_image']): ?>
                <img src="<?php echo htmlspecialchars($related['featured_image']); ?>"
                     alt="<?php echo htmlspecialchars($related['title']); ?>"
                     class="w-full h-36 object-cover">
              <?php else: ?>
                <div class="h-36 bg-gradient-to-br from-green-600 to-slate-800 flex items-center justify-center text-white text-xs font-bold uppercase tracking-widest">Article</div>
              <?php endif; ?>
              <div class="p-4 space-y-2">
                <h3 class="text-sm font-extrabold leading-snug">
                  <a href="<?php echo url('blogs/readmore.php?slug=' . rawurlencode($related['slug'])); ?>"
                     class="text-slate-900 hover:text-green-600">
                    <?php echo htmlspecialchars($related['title']); ?>
                  </a>
                </h3>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                  <?php echo date('M d, Y', strtotime($related['published_at'])); ?>
                </p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </article>
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
$extra_head .= '<meta property="article:published_time" content="' . htmlspecialchars($blog['published_at']) . '">' . "\n";
if (!empty($blog['featured_image'])) {
    $extra_head .= '<meta property="og:image" content="' . htmlspecialchars($blog['featured_image']) . '">' . "\n";
}

require __DIR__ . '/../templates/public/dazu_layout.php';
