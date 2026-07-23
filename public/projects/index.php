<?php
$page_title = 'Student Capstone Projects — Dazu hub Institute';
$page_description = 'Browse graduate projects built at Dazu hub Institute — web apps, AI, mobile UX and automation.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/controllers/ProjectController.php';

$controller = new ProjectController($pdo);
$categoryFilterId = (int) ($_GET['category'] ?? 0);

$dbCategories = [];
$dbProjects = [];
try {
    $dbCategories = $controller->getCategories();
    $dbProjects = $controller->getProjects('published');
} catch (Throwable $e) {
    $dbCategories = [];
    $dbProjects = [];
}

$projects = [];
if (!empty($dbProjects)) {
    foreach ($dbProjects as $p) {
        $tags = [];
        try {
            $tags = array_column($controller->getProjectTags($p['id']) ?? [], 'tag_name');
        } catch (Throwable $e) {
            $tags = [];
        }
        $cover = $p['cover_image'] ?? '';
        if ($cover && !preg_match('#^https?://#i', $cover)) {
            $cover = (str_starts_with($cover, '/')) ? $cover : url(ltrim($cover, '/'));
        }
        $projects[] = [
            'id' => (int) $p['id'],
            'category_id' => (int) ($p['category_id'] ?? 0),
            'title' => $p['major_title'] ?: $p['small_title'],
            'category' => $p['category_name'] ?? 'Capstone',
            'cohort' => strtoupper($p['small_title'] ?? 'Capstone'),
            'description' => $p['description'] ?? '',
            'tech' => $tags ?: ['Capstone Project'],
            'developer' => $p['author'] ?? 'Dazu Hub Graduate',
            'details' => $p['description'] ?? '',
            'cover_image' => $cover,
            'readmore' => url('projects/readmore.php?id=' . (int) $p['id']),
        ];
    }
}

$usingStatic = empty($projects);
if ($usingStatic) {
    foreach ([
        ['id' => 0, 'category_id' => 1, 'title' => 'Thika Soko Hub', 'category' => 'Web Apps', 'cohort' => 'Software Dev - Q1 2026', 'description' => 'A fast local e-commerce platform for agricultural traders.', 'tech' => ['React.js', 'Node.js', 'PostgreSQL'], 'developer' => 'Eunice Wangari & Team', 'details' => 'Full-stack with M-Pesa integration.', 'cover_image' => ''],
        ['id' => 0, 'category_id' => 2, 'title' => 'MedicAI Predictor', 'category' => 'AI & Data', 'cohort' => 'AI Class - Feb 2026', 'description' => 'Diagnostic-assisting AI model for basic patient symptoms.', 'tech' => ['Python', 'Scikit-Learn'], 'developer' => 'Dennis Kipruto', 'details' => 'Streamlit app with Gemini API.', 'cover_image' => ''],
    ] as $p) {
        $p['readmore'] = null;
        $projects[] = $p;
    }
    $dbCategories = [
        ['id' => 1, 'category_name' => 'Web Apps', 'project_count' => 1],
        ['id' => 2, 'category_name' => 'AI & Data', 'project_count' => 1],
    ];
}

$allProjects = $projects;
$activeCategoryId = $categoryFilterId;

if ($categoryFilterId > 0) {
    $projects = array_values(array_filter($allProjects, static fn($p) => (int)($p['category_id'] ?? 0) === $categoryFilterId));
} elseif (!empty($dbCategories)) {
    foreach ($dbCategories as $cat) {
        if ((int)($cat['project_count'] ?? 0) > 0) {
            $activeCategoryId = (int) $cat['id'];
            break;
        }
    }
    if ($activeCategoryId === 0) {
        $activeCategoryId = (int) ($dbCategories[0]['id'] ?? 0);
    }
    $projects = array_values(array_filter($allProjects, static fn($p) => (int)($p['category_id'] ?? 0) === $activeCategoryId));
} else {
    $projects = $allProjects;
}

ob_start();
?>
<div class="bg-white min-h-screen py-16 text-slate-900">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 space-y-10">
    <div class="max-w-2xl">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-100 rounded-full">Student Achievements</span>
      <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 mt-4">Student Capstone Projects</h1>
      <p class="text-slate-600 font-medium leading-relaxed mt-3">See products designed, coded, and deployed by Dazu Hub graduates. Categories are managed in Admin → Projects.</p>
    </div>

    <?php if (!empty($dbCategories)): ?>
      <div class="flex gap-4 overflow-x-auto pb-2 snap-x" id="dazu-project-cat-tabs">
        <?php foreach ($dbCategories as $i => $cat):
            $catId = (int) $cat['id'];
            $isActive = (int)$activeCategoryId === $catId;
            $count = (int) ($cat['project_count'] ?? 0);
            $href = url('projects/index.php?category=' . $catId);
        ?>
          <a href="<?php echo htmlspecialchars($href); ?>"
             class="flex-shrink-0 snap-start min-w-[200px] sm:min-w-[220px] rounded-none border p-4 text-left transition-all <?php echo $isActive ? 'bg-[#15A24A] border-[#15A24A] text-white shadow-md' : 'bg-white border-slate-200 text-slate-900 hover:border-[#15A24A]/50'; ?>">
            <p class="text-sm font-extrabold leading-tight"><?php echo htmlspecialchars($cat['category_name']); ?></p>
            <p class="text-[11px] font-semibold mt-1 opacity-80"><?php echo $count; ?> Project<?php echo $count === 1 ? '' : 's'; ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($projects)): ?>
      <div class="text-center py-20 border border-dashed border-slate-200 rounded-2xl bg-slate-50">
        <p class="text-slate-600 font-semibold">No projects in this category yet.</p>
        <p class="text-slate-500 text-sm mt-1">Publish projects from Admin → Projects.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($projects as $project): ?>
          <article class="bg-white border border-slate-200 rounded-2xl overflow-hidden flex flex-col hover:border-[#15A24A] hover:shadow-md transition-all duration-300">
            <?php if (!empty($project['cover_image'])): ?>
              <img src="<?php echo htmlspecialchars($project['cover_image']); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>" class="w-full h-44 object-cover">
            <?php else: ?>
              <div class="h-44 bg-gradient-to-br from-[#15A24A] to-slate-800 flex items-center justify-center text-white text-[10px] font-extrabold uppercase tracking-widest px-4 text-center"><?php echo htmlspecialchars($project['category']); ?></div>
            <?php endif; ?>
            <div class="p-6 flex flex-col flex-1">
              <div class="flex items-center justify-between mb-4">
                <span class="text-[10px] font-bold text-[#15A24A] bg-green-50 border border-green-100 rounded-full px-2.5 py-1 uppercase tracking-widest"><?php echo htmlspecialchars(strtoupper($project['category'])); ?></span>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"><?php echo htmlspecialchars($project['cohort']); ?></span>
              </div>
              <h3 class="text-lg font-extrabold text-slate-900 mb-2 uppercase tracking-tight leading-snug"><?php echo htmlspecialchars($project['title']); ?></h3>
              <p class="text-slate-600 text-xs leading-relaxed font-semibold line-clamp-3 mb-4 flex-1"><?php echo htmlspecialchars($project['description']); ?></p>
              <div class="flex flex-wrap gap-1.5 mb-4">
                <?php foreach ($project['tech'] as $t): ?>
                  <span class="text-[10px] font-bold bg-slate-50 border border-slate-200 text-slate-600 px-2 py-0.5 rounded-md"><?php echo htmlspecialchars($t); ?></span>
                <?php endforeach; ?>
              </div>
              <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                <span class="text-xs text-slate-500 font-semibold">By <?php echo htmlspecialchars($project['developer']); ?></span>
                <?php if (!empty($project['readmore'])): ?>
                  <a href="<?php echo htmlspecialchars($project['readmore']); ?>" class="text-xs font-extrabold text-[#15A24A] hover:text-[#128a3e] flex items-center gap-1">
                    View Details <span>→</span>
                  </a>
                <?php else: ?>
                  <span class="text-xs font-bold text-slate-400 italic">Details coming soon</span>
                <?php endif; ?>
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
require __DIR__ . '/../templates/public/dazu_layout.php';
