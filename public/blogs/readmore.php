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
                <?php if ($section['section_type'] === 'heading'): ?>
                  <?php if (!empty($section['title'])): ?><h2><?php echo htmlspecialchars($section['title']); ?></h2><?php endif; ?>

                <?php elseif ($section['section_type'] === 'paragraph'): ?>
                  <p><?php echo nl2br(htmlspecialchars($section['content'] ?? '')); ?></p>

                <?php elseif ($section['section_type'] === 'image' && !empty($section['media_url'])): ?>
                  <figure class="my-10">
                    <img src="<?php echo htmlspecialchars($section['media_url']); ?>" alt="<?php echo htmlspecialchars($section['title'] ?? ''); ?>" class="rounded-xl w-full border border-slate-200">
                    <?php if (!empty($section['title'])): ?>
                      <figcaption class="mt-2 text-center text-xs text-slate-500 font-semibold"><?php echo htmlspecialchars($section['title']); ?></figcaption>
                    <?php endif; ?>
                  </figure>

                <?php elseif ($section['section_type'] === 'image_gallery'):
                    $galleryImages = json_decode($section['gallery_images'] ?? '[]', true) ?: []; ?>
                  <?php if (!empty($galleryImages)): ?>
                    <div class="my-10">
                      <?php if (!empty($section['title'])): ?><h3><?php echo htmlspecialchars($section['title']); ?></h3><?php endif; ?>
                      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 not-prose">
                        <?php foreach ($galleryImages as $img): ?>
                          <a href="<?php echo htmlspecialchars($img); ?>" target="_blank" rel="noopener" class="block aspect-square overflow-hidden rounded-xl border border-slate-200">
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                          </a>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>

                <?php elseif ($section['section_type'] === 'youtube' && !empty($section['media_url'])): ?>
                  <div class="my-10">
                    <?php if (!empty($section['title'])): ?><h3><?php echo htmlspecialchars($section['title']); ?></h3><?php endif; ?>
                    <div class="aspect-video rounded-xl overflow-hidden border border-slate-200 shadow-sm">
                      <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?php echo htmlspecialchars($section['video_id'] ?: blog_youtube_id($section['media_url'])); ?>" allowfullscreen title="Video"></iframe>
                    </div>
                  </div>

                <?php elseif ($section['section_type'] === 'link' && !empty($section['media_url'])): ?>
                  <a href="<?php echo htmlspecialchars($section['media_url']); ?>" target="_blank" rel="noopener"
                     class="not-prose my-8 flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-xl hover:border-[#15A24A] transition-colors group">
                    <span class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-[#15A24A]">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l3-3a4 4 0 015.656 5.656l-1.5 1.5"/></svg>
                    </span>
                    <span class="text-sm font-bold text-slate-800 group-hover:text-[#15A24A] break-all"><?php echo htmlspecialchars($section['link_label'] ?: $section['media_url']); ?></span>
                  </a>

                <?php elseif ($section['section_type'] === 'quote'): ?>
                  <blockquote class="my-8 p-6 border-l-4 border-[#15A24A] bg-green-50/50 rounded-r-xl italic text-slate-700">
                    <?php echo nl2br(htmlspecialchars($section['content'] ?? '')); ?>
                    <?php if ($section['title']): ?><footer class="mt-3 not-italic text-sm text-slate-500 font-semibold">— <?php echo htmlspecialchars($section['title']); ?></footer><?php endif; ?>
                  </blockquote>

                <?php elseif ($section['section_type'] === 'cta' && !empty($section['cta_value'])):
                    $ctaType = $section['cta_type'] ?? 'link';
                    $ctaValue = $section['cta_value'];
                    if ($ctaType === 'whatsapp') {
                        $ctaHref = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $ctaValue);
                        $ctaDefaultLabel = 'Chat on WhatsApp';
                        $ctaIcon = 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z';
                    } elseif ($ctaType === 'email') {
                        $ctaHref = 'mailto:' . $ctaValue;
                        $ctaDefaultLabel = 'Email Us';
                        $ctaIcon = 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z';
                    } else {
                        $ctaHref = preg_match('#^https?://#i', $ctaValue) ? $ctaValue : 'https://' . $ctaValue;
                        $ctaDefaultLabel = 'Learn More';
                        $ctaIcon = 'M17 8l4 4m0 0l-4 4m4-4H3';
                    } ?>
                  <div class="not-prose my-10 text-center">
                    <a href="<?php echo htmlspecialchars($ctaHref); ?>" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 px-6 py-3.5 bg-[#15A24A] hover:bg-[#128a3e] text-white text-sm font-extrabold rounded-xl transition-colors shadow-sm">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="<?php echo $ctaIcon; ?>" stroke="currentColor" stroke-width="<?php echo $ctaType === 'link' ? '2' : '0'; ?>" fill="<?php echo $ctaType === 'link' ? 'none' : 'currentColor'; ?>" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      <?php echo htmlspecialchars($section['cta_label'] ?: $ctaDefaultLabel); ?>
                    </a>
                  </div>

                <?php elseif ($section['section_type'] === 'faq'):
                    $faqItems = json_decode($section['faq_items'] ?? '[]', true) ?: []; ?>
                  <?php if (!empty($faqItems)): ?>
                    <div class="not-prose my-10 space-y-3 blog-faq-block">
                      <?php foreach ($faqItems as $fi => $item): ?>
                        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                          <button type="button" class="blog-faq-toggle w-full flex items-center justify-between gap-4 px-5 py-4 text-left font-bold text-slate-900 hover:bg-slate-50 transition-colors" aria-expanded="false">
                            <span class="text-sm"><?php echo htmlspecialchars($item['question'] ?? ''); ?></span>
                            <span class="blog-faq-icon shrink-0 text-[#15A24A] text-xl font-light leading-none">+</span>
                          </button>
                          <div class="blog-faq-panel hidden px-5 pb-5 text-slate-600 text-sm leading-relaxed border-t border-slate-100 pt-4">
                            <?php echo nl2br(htmlspecialchars($item['answer'] ?? '')); ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
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
$extra_head .= '<meta property="og:type" content="article">' . "\n";
if (!empty($blog['published_at'])) {
    $extra_head .= '<meta property="article:published_time" content="' . htmlspecialchars($blog['published_at']) . '">' . "\n";
}
$og_image = $heroImage ?: ($blog['featured_image'] ?? null);

$extra_js = <<<'HTML'
<script>
(function(){
  document.querySelectorAll('.blog-faq-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
      var panel = btn.nextElementSibling;
      var icon = btn.querySelector('.blog-faq-icon');
      var open = !panel.classList.contains('hidden');
      panel.classList.toggle('hidden');
      btn.setAttribute('aria-expanded', open ? 'false' : 'true');
      if (icon) icon.textContent = open ? '+' : '−';
    });
  });
})();
</script>
HTML;

require __DIR__ . '/../templates/public/dazu_layout.php';
