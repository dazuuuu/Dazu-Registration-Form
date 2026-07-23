<?php
// public/projects/readmore.php
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: ' . url('projects/index.php'));
    exit();
}

$stmt = $pdo->prepare("
    SELECT p.*, c.category_name
    FROM projects p
    LEFT JOIN project_categories c ON p.category_id = c.id
    WHERE p.id = :id AND p.status = 'published'
");
$stmt->execute([':id' => $id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: ' . url('projects/index.php'));
    exit();
}

$page_title = htmlspecialchars($project['small_title']) . ' — Dazu Hub Institute';
$page_description = mb_strimwidth(strip_tags($project['description'] ?? ''), 0, 160, '…');

$pdo->prepare("UPDATE projects SET view_count = view_count + 1 WHERE id = :id")->execute([':id' => $project['id']]);

$stmt = $pdo->prepare("SELECT * FROM project_gallery WHERE project_id = :project_id ORDER BY sort_order ASC");
$stmt->execute([':project_id' => $project['id']]);
$gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM project_videos WHERE project_id = :project_id ORDER BY sort_order ASC");
$stmt->execute([':project_id' => $project['id']]);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT t.* FROM project_tags t
    INNER JOIN project_tag_relations ptr ON t.id = ptr.tag_id
    WHERE ptr.project_id = :project_id
");
$stmt->execute([':project_id' => $project['id']]);
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

function dazu_project_media_url(string $path): string {
    if ($path === '' || preg_match('#^https?://#i', $path)) return $path;
    return (str_starts_with($path, '/')) ? $path : url(ltrim($path, '/'));
}

function dazu_project_youtube_id($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $m);
    return $m[1] ?? '';
}

function dazu_project_vimeo_id($url) {
    preg_match('/vimeo\.com\/(?:channels\/\w+\/|groups\/[^\/]*\/|video\/|)(\d+)/', $url, $m);
    return $m[1] ?? '';
}

$coverImg = !empty($project['cover_image'])
    ? dazu_project_media_url($project['cover_image'])
    : 'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=1200&q=80';

ob_start();
?>

<!-- Hero -->
<section class="bg-slate-950 relative overflow-hidden">
  <img src="<?php echo htmlspecialchars($coverImg); ?>" alt="" class="absolute inset-0 w-full h-full object-cover opacity-25">
  <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/85 to-slate-950/60"></div>
  <div class="max-w-[1000px] mx-auto px-4 md:px-8 lg:px-12 py-16 md:py-24 relative">
    <a href="<?php echo url('projects/index.php'); ?>" class="inline-flex items-center gap-2 text-slate-300 hover:text-white text-xs font-bold uppercase tracking-widest mb-6">
      <i class="fas fa-arrow-left"></i> Back to Projects
    </a>
    <span class="inline-block text-[#15A24A] text-xs uppercase tracking-widest font-extrabold px-3 py-1.5 bg-green-500/10 border border-green-500/30 rounded-full mb-5"><?php echo htmlspecialchars($project['category_name'] ?? 'Capstone'); ?></span>
    <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-white tracking-tight leading-tight uppercase"><?php echo htmlspecialchars($project['small_title']); ?></h1>
    <?php if (!empty($project['major_title'])): ?>
      <p class="text-slate-300 font-medium mt-4 max-w-2xl"><?php echo htmlspecialchars($project['major_title']); ?></p>
    <?php endif; ?>
  </div>
</section>

<section class="bg-white py-16">
  <div class="max-w-[1000px] mx-auto px-4 md:px-8 lg:px-12">

    <!-- Info strip -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-12">
      <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl">
        <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Category</span>
        <span class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($project['category_name'] ?? 'Uncategorized'); ?></span>
      </div>
      <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl">
        <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Completed</span>
        <span class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars(date('F Y', strtotime($project['created_at']))); ?></span>
      </div>
      <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl">
        <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Views</span>
        <span class="text-sm font-bold text-slate-900"><?php echo number_format($project['view_count']); ?></span>
      </div>
    </div>

    <!-- Overview -->
    <div class="mb-12">
      <h2 class="text-xl font-extrabold text-slate-950 uppercase tracking-tight mb-4">Project Overview</h2>
      <div class="text-slate-600 font-medium leading-relaxed"><?php echo nl2br(htmlspecialchars($project['description'] ?? '')); ?></div>
    </div>

    <!-- Gallery -->
    <?php if (!empty($gallery)): ?>
    <div class="mb-12">
      <h2 class="text-xl font-extrabold text-slate-950 uppercase tracking-tight mb-4">Project Gallery</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <?php foreach ($gallery as $i => $image): $imgUrl = dazu_project_media_url($image['image_path']); ?>
          <button type="button" class="dazu-gallery-open group relative aspect-square overflow-hidden rounded-xl border border-slate-200"
                  data-src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES); ?>"
                  data-title="<?php echo htmlspecialchars($image['image_title'] ?? '', ENT_QUOTES); ?>">
            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($image['image_title'] ?? ''); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
            <span class="absolute inset-0 bg-slate-900/0 group-hover:bg-slate-900/40 transition-colors flex items-center justify-center">
              <i class="fas fa-search-plus text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
            </span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Videos -->
    <?php if (!empty($videos)): ?>
    <div class="mb-12">
      <h2 class="text-xl font-extrabold text-slate-950 uppercase tracking-tight mb-4">Project Videos</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <?php foreach ($videos as $video): ?>
          <div>
            <?php if (!empty($video['video_title'])): ?>
              <h3 class="text-sm font-bold text-slate-900 mb-2"><?php echo htmlspecialchars($video['video_title']); ?></h3>
            <?php endif; ?>
            <div class="aspect-video rounded-xl overflow-hidden border border-slate-200 bg-slate-950">
              <?php if ($video['video_type'] === 'youtube'): ?>
                <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?php echo htmlspecialchars(dazu_project_youtube_id($video['video_url'])); ?>" allowfullscreen title="Video"></iframe>
              <?php elseif ($video['video_type'] === 'vimeo'): ?>
                <iframe class="w-full h-full" src="https://player.vimeo.com/video/<?php echo htmlspecialchars(dazu_project_vimeo_id($video['video_url'])); ?>" allowfullscreen title="Video"></iframe>
              <?php else: ?>
                <video controls class="w-full h-full"><source src="<?php echo htmlspecialchars(dazu_project_media_url($video['video_url'])); ?>" type="video/mp4"></video>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tags -->
    <?php if (!empty($tags)): ?>
    <div class="pt-8 border-t border-slate-100">
      <h2 class="text-xl font-extrabold text-slate-950 uppercase tracking-tight mb-4">Tags</h2>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($tags as $tag): ?>
          <a href="<?php echo url('projects/index.php?tag=' . urlencode($tag['tag_slug'])); ?>"
             class="text-xs font-bold text-slate-600 bg-slate-50 border border-slate-200 hover:border-[#15A24A] hover:text-[#15A24A] rounded-full px-3 py-1.5 transition-colors">
            <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($tag['tag_name']); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- CTA -->
<section class="bg-[#15A24A] py-16 text-center">
  <div class="max-w-[700px] mx-auto px-4">
    <h2 class="text-2xl md:text-3xl font-extrabold text-white uppercase tracking-tight mb-3">Interested in Building Something Similar?</h2>
    <p class="text-green-50 font-medium mb-6">Join Dazu Hub Institute and build real, portfolio-ready projects like this one.</p>
    <div class="flex flex-wrap gap-3 justify-center">
      <a href="<?php echo url('courses/index.php'); ?>" class="px-6 py-3.5 text-xs font-extrabold tracking-widest uppercase text-[#15A24A] bg-white hover:bg-green-50 rounded-xl transition-colors">View Our Courses</a>
      <a href="<?php echo url('projects/index.php'); ?>" class="px-6 py-3.5 text-xs font-extrabold tracking-widest uppercase text-white border border-white/60 hover:bg-white/10 rounded-xl transition-colors">All Projects</a>
    </div>
  </div>
</section>

<!-- Lightbox -->
<div id="dazu-gallery-lightbox" class="fixed inset-0 bg-slate-950/90 hidden items-center justify-center z-50 p-4">
  <button type="button" id="dazu-gallery-lightbox-close" class="absolute top-5 right-5 text-white/80 hover:text-white text-2xl" aria-label="Close">
    <i class="fas fa-times"></i>
  </button>
  <img id="dazu-gallery-lightbox-img" src="" alt="" class="max-w-full max-h-full rounded-xl">
</div>
<script>
(function(){
  var lb = document.getElementById('dazu-gallery-lightbox');
  var img = document.getElementById('dazu-gallery-lightbox-img');
  document.querySelectorAll('.dazu-gallery-open').forEach(function(btn){
    btn.addEventListener('click', function(){
      img.src = btn.getAttribute('data-src');
      img.alt = btn.getAttribute('data-title') || '';
      lb.classList.remove('hidden'); lb.classList.add('flex');
    });
  });
  function close(){ lb.classList.add('hidden'); lb.classList.remove('flex'); img.src=''; }
  document.getElementById('dazu-gallery-lightbox-close').addEventListener('click', close);
  lb.addEventListener('click', function(e){ if (e.target === lb) close(); });
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
