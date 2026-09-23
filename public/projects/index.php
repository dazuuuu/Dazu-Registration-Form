<?php
// public/projects/index.php — student project showcase, styled after public/blogs/index.php
$page_title = 'Student Projects — Dazu Training Institute';
$page_description = 'Real projects built by Dazu Training Institute students — languages used, complexity, progress and live sandbox demos.';

require_once __DIR__ . '/../../app/v1/init.php';

$search = trim($_GET['search'] ?? '');

$projects = [];
try {
    $sql = "SELECT p.*, c.category_name,
                   TRIM(CONCAT(IFNULL(sp.first_name, ''), ' ', IFNULL(sp.last_name, ''))) AS student_name,
                   su.username AS student_username
            FROM projects p
            LEFT JOIN project_categories c ON p.category_id = c.id
            LEFT JOIN users su ON p.student_id = su.id
            LEFT JOIN user_profiles sp ON sp.user_id = su.id
            WHERE p.status = 'published'";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (p.small_title LIKE :q OR p.major_title LIKE :q OR p.description LIKE :q OR p.languages_used LIKE :q)";
        $params[':q'] = '%' . $search . '%';
    }
    $sql .= " ORDER BY p.project_date DESC, p.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $projects = [];
}

ob_start();
?>
<div class="bg-white min-h-screen py-16 text-slate-900">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 space-y-16">
    <div class="max-w-2xl">
      <span class="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full">Student Work</span>
      <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 mt-4">Student Projects</h1>
      <p class="text-slate-600 font-medium leading-relaxed mt-3">Real, working projects built by our students — see the languages used, how far along they are, and try each one live.</p>
    </div>

    <form method="GET" class="border-b border-slate-100 pb-8">
      <div class="relative max-w-lg">
        <svg class="absolute left-3.5 top-3.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search projects, languages, students..."
               class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:outline-none focus:border-green-600 focus:bg-white">
      </div>
    </form>

    <?php if (empty($projects)): ?>
      <div class="text-center py-20 border border-dashed border-slate-200 rounded-2xl bg-slate-50">
        <p class="text-slate-600 font-semibold">No published projects yet.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($projects as $project): ?>
          <?php
            $studentDisplay = trim($project['student_name'] ?? '') ?: ($project['student_username'] ?? null);
            $progress = max(0, min(100, (int) ($project['progress'] ?? 0)));
            $complexity = $project['complexity'] ?? 'beginner';
            $detailUrl = url('projects/view.php?slug=' . rawurlencode($project['project_slug']));
          ?>
          <article class="bg-white border border-slate-200 rounded-2xl overflow-hidden hover:border-green-600 hover:shadow-md transition-all flex flex-col">
            <?php if (!empty($project['cover_image'])): ?>
              <img src="<?php echo htmlspecialchars($project['cover_image']); ?>" alt="" class="w-full h-40 object-cover">
            <?php else: ?>
              <div class="w-full h-40 bg-slate-100 flex items-center justify-center text-slate-300"><i class="fas fa-code text-3xl"></i></div>
            <?php endif; ?>
            <div class="space-y-3 p-6 flex-1 flex flex-col">
              <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-extrabold tracking-widest bg-green-50 text-green-700 border border-green-200 px-3 py-1 rounded-full"><?php echo htmlspecialchars(strtoupper($complexity)); ?></span>
                <?php if (!empty($project['project_date'])): ?>
                  <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"><?php echo htmlspecialchars(date('M d, Y', strtotime($project['project_date']))); ?></span>
                <?php endif; ?>
              </div>
              <h2 class="text-lg font-extrabold text-slate-900 leading-snug"><?php echo htmlspecialchars($project['major_title']); ?></h2>
              <p class="text-slate-500 text-xs font-semibold leading-relaxed line-clamp-3 flex-1"><?php echo htmlspecialchars(mb_strimwidth(strip_tags($project['description'] ?? ''), 0, 140, '…')); ?></p>

              <?php if (!empty($project['languages_used'])): ?>
                <div class="flex flex-wrap gap-1.5">
                  <?php foreach (array_slice(array_map('trim', explode(',', $project['languages_used'])), 0, 5) as $lang): if ($lang === '') continue; ?>
                    <span class="text-[10px] font-bold text-slate-600 bg-slate-50 border border-slate-200 px-2 py-0.5 rounded-md"><?php echo htmlspecialchars($lang); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <div>
                <div class="flex items-center justify-between text-[10px] font-bold text-slate-500 mb-1">
                  <span>Progress</span>
                  <span><?php echo $progress; ?>%</span>
                </div>
                <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                  <div class="h-full bg-green-600 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                </div>
              </div>

              <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                <span class="text-xs text-slate-500 font-semibold"><?php echo $studentDisplay ? 'By ' . htmlspecialchars($studentDisplay) : 'Dazu Training Institute'; ?></span>
                <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="text-xs font-extrabold text-green-600 hover:text-green-700">View Project →</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
foreach ($projects as $project) {
    if (!empty($project['cover_image'])) {
        $og_image = $project['cover_image'];
        break;
    }
}
require __DIR__ . '/../templates/public/dazu_layout.php';
