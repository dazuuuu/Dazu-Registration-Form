<?php
// public/projects/view.php — single student project, sandbox-embedded, styled after public/blogs/readmore.php

require_once __DIR__ . '/../../app/v1/init.php';

$slug = $_GET['slug'] ?? null;
if (!$slug) {
    redirect(url('projects/index.php'));
}

$stmt = $pdo->prepare("
    SELECT p.*, c.category_name,
           TRIM(CONCAT(IFNULL(sp.first_name, ''), ' ', IFNULL(sp.last_name, ''))) AS student_name,
           su.username AS student_username
    FROM projects p
    LEFT JOIN project_categories c ON p.category_id = c.id
    LEFT JOIN users su ON p.student_id = su.id
    LEFT JOIN user_profiles sp ON sp.user_id = su.id
    WHERE p.project_slug = :slug AND p.status = 'published'
");
$stmt->execute([':slug' => $slug]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    redirect(url('projects/index.php'));
}

$updateStmt = $pdo->prepare("UPDATE projects SET view_count = view_count + 1 WHERE id = :id");
$updateStmt->execute([':id' => $project['id']]);

$stmt = $pdo->prepare("SELECT * FROM project_gallery WHERE project_id = :id ORDER BY sort_order ASC");
$stmt->execute([':id' => $project['id']]);
$gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM project_videos WHERE project_id = :id ORDER BY sort_order ASC");
$stmt->execute([':id' => $project['id']]);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT t.tag_name FROM project_tags t
    INNER JOIN project_tag_relations tr ON t.id = tr.tag_id
    WHERE tr.project_id = :id
");
$stmt->execute([':id' => $project['id']]);
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = $project['major_title'] . ' — Student Project — Dazu Training Institute';
$page_description = mb_strimwidth(strip_tags($project['description'] ?? ''), 0, 160, '…');

$studentDisplay = trim($project['student_name'] ?? '') ?: ($project['student_username'] ?? null);
$progress = max(0, min(100, (int) ($project['progress'] ?? 0)));
$complexity = $project['complexity'] ?? 'beginner';
$languages = array_filter(array_map('trim', explode(',', $project['languages_used'] ?? '')));

ob_start();
?>
<div class="bg-white min-h-screen py-16 text-slate-900">
  <div class="max-w-[1000px] mx-auto px-4 md:px-8 space-y-10">
    <a href="<?php echo url('projects/index.php'); ?>" class="text-xs font-bold text-slate-500 hover:text-green-600">← Back to all projects</a>

    <div class="space-y-4">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-[10px] uppercase font-extrabold tracking-widest bg-green-50 text-green-700 border border-green-200 px-3 py-1 rounded-full"><?php echo htmlspecialchars(strtoupper($complexity)); ?></span>
        <?php if (!empty($project['category_name'])): ?>
          <span class="text-[10px] uppercase font-extrabold tracking-widest bg-slate-50 text-slate-600 border border-slate-200 px-3 py-1 rounded-full"><?php echo htmlspecialchars($project['category_name']); ?></span>
        <?php endif; ?>
        <?php if (!empty($project['project_date'])): ?>
          <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"><?php echo htmlspecialchars(date('F d, Y', strtotime($project['project_date']))); ?></span>
        <?php endif; ?>
      </div>
      <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900"><?php echo htmlspecialchars($project['major_title']); ?></h1>
      <p class="text-slate-500 font-semibold"><?php echo htmlspecialchars($project['small_title']); ?></p>
      <div class="flex items-center gap-3 text-sm text-slate-600 font-semibold">
        <?php if ($studentDisplay): ?><span><i class="fas fa-user-graduate me-1 text-green-600"></i> <?php echo htmlspecialchars($studentDisplay); ?></span><?php endif; ?>
        <span><i class="fas fa-eye me-1 text-green-600"></i> <?php echo (int) $project['view_count']; ?> views</span>
      </div>
    </div>

    <?php if (!empty($project['live_url'])): ?>
      <div class="border border-slate-200 rounded-2xl overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-slate-50 border-b border-slate-200">
          <span class="text-xs font-bold text-slate-500 truncate"><i class="fas fa-flask me-1"></i> Live Sandbox Preview</span>
          <a href="<?php echo htmlspecialchars($project['live_url']); ?>" target="_blank" rel="noopener noreferrer" class="text-xs font-extrabold text-green-600 hover:text-green-700 whitespace-nowrap ml-3">Open in new tab ↗</a>
        </div>
        <iframe src="<?php echo htmlspecialchars($project['live_url']); ?>" class="w-full" style="height: 560px; border: 0;" loading="lazy" sandbox="allow-scripts allow-same-origin allow-forms allow-popups"></iframe>
      </div>
    <?php elseif (!empty($project['cover_image'])): ?>
      <img src="<?php echo htmlspecialchars($project['cover_image']); ?>" alt="" class="w-full rounded-2xl border border-slate-200">
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="md:col-span-2 space-y-3 text-slate-700 leading-relaxed whitespace-pre-line">
        <?php echo nl2br(htmlspecialchars($project['description'] ?? '')); ?>
      </div>
      <div class="space-y-5 md:border-l md:border-slate-100 md:pl-6">
        <?php if (!empty($languages)): ?>
        <div>
          <p class="text-[10px] uppercase font-extrabold tracking-widest text-slate-400 mb-2">Languages Used</p>
          <div class="flex flex-wrap gap-1.5">
            <?php foreach ($languages as $lang): ?>
              <span class="text-xs font-bold text-slate-600 bg-slate-50 border border-slate-200 px-2.5 py-1 rounded-md"><?php echo htmlspecialchars($lang); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <div>
          <div class="flex items-center justify-between text-xs font-bold text-slate-500 mb-1">
            <span>Progress</span>
            <span><?php echo $progress; ?>%</span>
          </div>
          <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-green-600 rounded-full" style="width: <?php echo $progress; ?>%"></div>
          </div>
        </div>
        <?php if (!empty($tags)): ?>
        <div>
          <p class="text-[10px] uppercase font-extrabold tracking-widest text-slate-400 mb-2">Tags</p>
          <div class="flex flex-wrap gap-1.5">
            <?php foreach ($tags as $tag): ?>
              <span class="text-xs font-bold text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-md"><?php echo htmlspecialchars($tag['tag_name']); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($gallery)): ?>
    <div>
      <h2 class="text-lg font-extrabold text-slate-900 mb-4">Gallery</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <?php foreach ($gallery as $image): ?>
          <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="<?php echo htmlspecialchars($image['image_title'] ?? ''); ?>" class="w-full h-40 object-cover rounded-xl border border-slate-200">
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($videos)): ?>
    <div>
      <h2 class="text-lg font-extrabold text-slate-900 mb-4">Videos</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($videos as $video): ?>
          <div class="rounded-xl overflow-hidden border border-slate-200">
            <?php echo $video['video_embed_code']; ?>
            <?php if (!empty($video['video_title'])): ?>
              <p class="text-sm font-bold text-slate-700 p-3"><?php echo htmlspecialchars($video['video_title']); ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
$og_image = $project['cover_image'] ?? null;
require __DIR__ . '/../templates/public/dazu_layout.php';
