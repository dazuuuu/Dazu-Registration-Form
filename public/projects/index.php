<?php
$page_title = 'Student Capstone Projects — Dazu hub Institute';
$page_description = 'Browse graduate projects built at Dazu hub Institute — web apps, AI, mobile UX and automation.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/controllers/ProjectController.php';

$controller = new ProjectController($pdo);
$categoryFilter = $_GET['category'] ?? 'All';
$categories = ['All', 'Web Apps', 'AI & Data', 'Mobile & UX', 'Automation'];

$dbProjects = [];
try {
    $dbProjects = $controller->getProjects('published');
} catch (Throwable $e) {
    $dbProjects = [];
}

function dazu_static_projects(): array
{
    return [
        ['id' => 0, 'title' => 'Thika Soko Hub', 'category' => 'Web Apps', 'cohort' => 'Software Dev - Q1 2026', 'description' => 'A fast local e-commerce platform allowing informal agricultural traders to list products, manage inventory, and receive payments.', 'tech' => ['React.js', 'Tailwind CSS', 'Node.js', 'Express', 'PostgreSQL'], 'developer' => 'Eunice Wangari & Team', 'details' => 'Full-stack application with merchant onboarding, storefront routing, and M-Pesa Daraja API integration.'],
        ['id' => 0, 'title' => 'MedicAI Predictor', 'category' => 'AI & Data', 'cohort' => 'AI Master Class - Feb 2026', 'description' => 'A localized diagnostic-assisting AI model trained to parse basic patient symptoms and suggest likely diagnoses.', 'tech' => ['Python', 'Pandas', 'Scikit-Learn', 'Streamlit', 'Gemini API'], 'developer' => 'Dennis Kipruto', 'details' => 'Integrated regional disease stats with Gemini API prompts for supportive clinical queries.'],
        ['id' => 0, 'title' => 'Figma RealEstate UI', 'category' => 'Mobile & UX', 'cohort' => 'Graphics & UI - Q2 2026', 'description' => 'A responsive mobile apartment searching interface formulated for Nairobi & Thika rentals.', 'tech' => ['Figma', 'UI Design', 'Color Theory', 'Responsive Grid'], 'developer' => 'Mercy Mwende', 'details' => 'Design system with custom icons, pricing sliders, and clean mobile booking flow.'],
        ['id' => 0, 'title' => 'AgriWater Smart Automation', 'category' => 'Automation', 'cohort' => 'AI Automations - Q1 2026', 'description' => 'An automated greenhouse hydration controller triggering irrigation schedules based on soil humidity APIs.', 'tech' => ['Python', 'Make.com', 'JSON APIs', 'Cron Schedulers'], 'developer' => 'Mark Kamore', 'details' => 'Uses climate API endpoints and Telegram alarms for farmer reports.'],
        ['id' => 0, 'title' => 'FinFlow Analytics Dashboard', 'category' => 'AI & Data', 'cohort' => 'Data Analytics - Q1 2026', 'description' => 'An interactive Power BI finance tracker designed to monitor operational costs and sales loops of Thika MSMEs.', 'tech' => ['Power BI', 'SQL', 'Excel', 'Data Modeling'], 'developer' => 'Joseph Njuguna', 'details' => 'DAX calculations, revenue trends, and dynamic filter panels for MSME owners.'],
        ['id' => 0, 'title' => 'SecureNet Threat Sentinel', 'category' => 'Automation', 'cohort' => 'Cybersecurity - Q2 2026', 'description' => 'A custom network auditing script designed to scan router terminals and report weak active configurations.', 'tech' => ['Python', 'Linux Bash', 'Networking', 'Cryptography'], 'developer' => 'Alex Kipkoech', 'details' => 'Maps local network nodes, identifies vulnerable ports, and logs remediation steps.'],
    ];
}

function dazu_map_project_category(string $name): string
{
    $n = strtolower($name);
    if (str_contains($n, 'web') || str_contains($n, 'software')) return 'Web Apps';
    if (str_contains($n, 'ai') || str_contains($n, 'data') || str_contains($n, 'analytics')) return 'AI & Data';
    if (str_contains($n, 'mobile') || str_contains($n, 'ux') || str_contains($n, 'design') || str_contains($n, 'graphic')) return 'Mobile & UX';
    if (str_contains($n, 'auto') || str_contains($n, 'cyber') || str_contains($n, 'security')) return 'Automation';
    return 'Web Apps';
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
        $projects[] = [
            'id' => (int) $p['id'],
            'title' => $p['major_title'] ?: $p['small_title'],
            'category' => dazu_map_project_category($p['category_name'] ?? ''),
            'cohort' => strtoupper($p['small_title'] ?? $p['category_name'] ?? 'Capstone'),
            'description' => $p['description'] ?? '',
            'tech' => $tags ?: ['Capstone Project'],
            'developer' => $p['author'] ?? 'Dazu Hub Graduate',
            'details' => $p['description'] ?? '',
            'readmore' => url('projects/readmore.php?id=' . (int) $p['id']),
        ];
    }
} else {
    foreach (dazu_static_projects() as $p) {
        $p['readmore'] = null;
        $projects[] = $p;
    }
}

if ($categoryFilter !== 'All') {
    $projects = array_values(array_filter($projects, static fn($p) => $p['category'] === $categoryFilter));
}

ob_start();
?>
<div class="bg-white min-h-screen py-16 text-slate-900">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="max-w-2xl mb-12">
      <span class="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full">Student Achievements</span>
      <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 mt-4">Student Capstone Projects</h1>
      <p class="text-slate-600 font-medium leading-relaxed mt-3">See actual products designed, coded, and deployed live by Dazu Hub graduates.</p>
    </div>

    <div class="flex flex-wrap items-center gap-2 pb-6 mb-12 border-b border-slate-100">
      <?php foreach ($categories as $cat): ?>
        <?php $active = $categoryFilter === $cat; $href = $cat === 'All' ? url('projects/index.php') : url('projects/index.php?category=' . rawurlencode($cat)); ?>
        <a href="<?php echo htmlspecialchars($href); ?>"
           class="px-4 py-3 text-xs font-bold uppercase tracking-wider rounded-xl border transition-all <?php echo $active ? 'bg-green-600 text-white border-green-600 font-extrabold' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'; ?>">
          <?php echo htmlspecialchars($cat === 'All' ? 'ALL' : strtoupper($cat)); ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
      <?php foreach ($projects as $idx => $project): ?>
        <article class="bg-slate-50 border border-slate-200 rounded-2xl p-8 flex flex-col justify-between min-h-[340px] hover:border-green-600 hover:bg-white transition-all duration-300">
          <div>
            <div class="flex items-center justify-between mb-6">
              <span class="text-[10px] font-bold text-green-700 bg-green-50 border border-green-100 rounded-full px-2.5 py-1 uppercase tracking-widest"><?php echo htmlspecialchars(strtoupper($project['category'])); ?></span>
              <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"><?php echo htmlspecialchars($project['cohort']); ?></span>
            </div>
            <h3 class="text-xl font-extrabold text-slate-900 mb-3 uppercase tracking-tight"><?php echo htmlspecialchars($project['title']); ?></h3>
            <p class="text-slate-600 text-xs leading-relaxed font-semibold line-clamp-3 mb-4"><?php echo htmlspecialchars($project['description']); ?></p>
            <div class="flex flex-wrap gap-1.5 mb-6">
              <?php foreach ($project['tech'] as $t): ?>
                <span class="text-[10px] font-bold bg-white border border-slate-200 text-slate-600 px-2 py-0.5 rounded-md"><?php echo htmlspecialchars($t); ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="flex items-center justify-between pt-4 border-t border-slate-200">
            <span class="text-xs text-slate-500 font-semibold">By <?php echo htmlspecialchars($project['developer']); ?></span>
            <button type="button" class="dazu-project-open text-xs font-extrabold text-green-600 hover:text-green-700 flex items-center gap-1"
                    data-title="<?php echo htmlspecialchars($project['title'], ENT_QUOTES); ?>"
                    data-details="<?php echo htmlspecialchars($project['details'], ENT_QUOTES); ?>"
                    data-url="<?php echo htmlspecialchars($project['readmore'] ?? '', ENT_QUOTES); ?>">
              View Sandbox <span class="text-green-500">▶</span>
            </button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div id="dazu-project-modal" class="fixed inset-0 bg-slate-900/80 hidden items-center justify-center z-50 p-4">
  <div class="bg-white border-2 border-slate-900 rounded-2xl w-full max-w-2xl overflow-hidden">
    <div class="flex items-center justify-between p-6 border-b border-slate-200 bg-slate-50">
      <h3 id="dazu-project-modal-title" class="text-lg font-bold text-slate-900 uppercase"></h3>
      <button type="button" id="dazu-project-modal-close" class="p-2 hover:bg-slate-200 rounded-lg text-slate-500">✕</button>
    </div>
    <div class="p-6 space-y-4">
      <p id="dazu-project-modal-body" class="text-sm text-slate-600 leading-relaxed font-semibold"></p>
      <a id="dazu-project-modal-link" href="#" class="hidden inline-block px-5 py-2.5 bg-green-600 text-white text-xs font-bold rounded-xl">Full project page →</a>
    </div>
  </div>
</div>
<script>
(function(){
  var modal = document.getElementById('dazu-project-modal');
  var title = document.getElementById('dazu-project-modal-title');
  var body = document.getElementById('dazu-project-modal-body');
  var link = document.getElementById('dazu-project-modal-link');
  document.querySelectorAll('.dazu-project-open').forEach(function(btn){
    btn.addEventListener('click', function(){
      title.textContent = btn.getAttribute('data-title') || '';
      body.textContent = btn.getAttribute('data-details') || '';
      var url = btn.getAttribute('data-url');
      if (url) { link.href = url; link.classList.remove('hidden'); } else { link.classList.add('hidden'); }
      modal.classList.remove('hidden'); modal.classList.add('flex');
    });
  });
  function close(){ modal.classList.add('hidden'); modal.classList.remove('flex'); }
  document.getElementById('dazu-project-modal-close').addEventListener('click', close);
  modal.addEventListener('click', function(e){ if(e.target===modal) close(); });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
