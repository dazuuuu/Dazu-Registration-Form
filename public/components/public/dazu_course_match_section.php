<?php
/**
 * Smart learner guide. Expects $courseMatchCourses or falls back to $popularCourses/$courses.
 */
require_once __DIR__ . '/dazu_course_filter_helpers.php';

$courseMatchCourses = $courseMatchCourses ?? $popularCourses ?? $courses ?? [];
$courseMatchCourses = array_values(array_filter($courseMatchCourses, static function ($course) {
    return !empty($course['is_active']) || (int) ($course['id'] ?? 0) === 0;
}));

if (!$courseMatchCourses) {
    return;
}

foreach ($courseMatchCourses as &$matchCourse) {
    $matchCourse['_match_blob'] = $matchCourse['_search_blob'] ?? dazu_course_search_blob($matchCourse);
}
unset($matchCourse);

$courseGoals = [
    [
        'id' => 'websites',
        'label' => 'Build websites',
        'title' => 'I want to build websites and web apps',
        'summary' => 'Start with web development or software development. These tracks teach the practical foundations behind business websites, dashboards, ecommerce systems, portals, and client projects.',
        'keywords' => ['web', 'website', 'frontend', 'front-end', 'html', 'css', 'javascript', 'php', 'laravel', 'software', 'full-stack', 'development'],
        'secondary' => ['database', 'data', 'design', 'graphics', 'ai'],
        'why' => [
            'Every organization needs reliable digital systems, not just social media pages.',
            'You can build a portfolio quickly because projects are visible and easy to demonstrate.',
            'It opens paths into employment, freelance work, startups, and internal business automation.',
        ],
        'careers' => ['Web Developer', 'Frontend Developer', 'PHP/Laravel Developer', 'Freelance Website Builder'],
    ],
    [
        'id' => 'security',
        'label' => 'Protect systems',
        'title' => 'I want to secure networks, websites, and computers',
        'summary' => 'Choose cybersecurity, networking, or systems tracks. You learn how attacks happen, how to harden systems, and how to support organizations that depend on secure infrastructure.',
        'keywords' => ['cyber', 'security', 'network', 'ethical', 'hacking', 'router', 'linux', 'systems', 'comptia'],
        'secondary' => ['software', 'web', 'cloud', 'data'],
        'why' => [
            'Companies need people who can prevent breaches, monitor systems, and respond quickly.',
            'Security skills are valuable across banks, schools, hospitals, agencies, and tech companies.',
            'Practical labs build confidence because you learn by defending real scenarios.',
        ],
        'careers' => ['Cybersecurity Analyst', 'Network Support Technician', 'SOC Assistant', 'IT Support Specialist'],
    ],
    [
        'id' => 'data',
        'label' => 'Analyze data',
        'title' => 'I want to analyze data and create business insights',
        'summary' => 'Start with data analytics. You will learn how to clean data, analyze patterns, build reports, and explain decisions using practical business datasets.',
        'keywords' => ['data', 'analytics', 'analysis', 'excel', 'sql', 'python', 'tableau', 'power bi', 'dashboard', 'statistics'],
        'secondary' => ['ai', 'python', 'software', 'database'],
        'why' => [
            'Organizations collect data every day but still need people who can turn it into decisions.',
            'Analytics is useful in finance, marketing, operations, education, health, and logistics.',
            'It pairs well with AI because good automation depends on clean data and strong thinking.',
        ],
        'careers' => ['Data Analyst', 'Business Intelligence Assistant', 'Reporting Officer', 'Operations Analyst'],
    ],
    [
        'id' => 'ai',
        'label' => 'Use AI tools',
        'title' => 'I want to build with AI and automation',
        'summary' => 'Choose artificial intelligence, Python, or software development. The goal is to understand how AI tools work and how to apply them in real products and workflows.',
        'keywords' => ['ai', 'artificial', 'intelligence', 'machine learning', 'automation', 'python', 'prompt', 'chatbot', 'model'],
        'secondary' => ['data', 'software', 'web'],
        'why' => [
            'AI is becoming a normal part of office work, customer support, education, content, and software.',
            'Practical AI skills help you automate repetitive work and build smarter digital products.',
            'Learners with coding and data foundations can use AI more responsibly and effectively.',
        ],
        'careers' => ['AI Tools Specialist', 'Automation Assistant', 'Junior Python Developer', 'AI Product Assistant'],
    ],
    [
        'id' => 'creative',
        'label' => 'Design content',
        'title' => 'I want to design digital content and brand visuals',
        'summary' => 'Choose graphics, creative design, UI basics, or web tracks. This direction suits learners who enjoy visual communication, brand work, marketing assets, and digital product presentation.',
        'keywords' => ['graphic', 'graphics', 'design', 'creative', 'ui', 'ux', 'brand', 'photoshop', 'illustrator', 'figma', 'content'],
        'secondary' => ['web', 'frontend', 'marketing', 'software'],
        'why' => [
            'Businesses need posters, social media graphics, product visuals, and clean user interfaces.',
            'Design pairs well with web development because many clients need both visuals and implementation.',
            'A strong portfolio can show your skill even before you have years of experience.',
        ],
        'careers' => ['Graphic Designer', 'Digital Content Designer', 'UI Design Assistant', 'Brand Creative Assistant'],
    ],
    [
        'id' => 'career',
        'label' => 'Get job-ready',
        'title' => 'I want the broadest path into tech work',
        'summary' => 'Start with software development, web development, data analytics, or cybersecurity. These tracks build practical skills that can lead to entry-level roles and real portfolio evidence.',
        'keywords' => ['software', 'web', 'data', 'cyber', 'network', 'python', 'development', 'analytics'],
        'secondary' => ['ai', 'design', 'graphics'],
        'why' => [
            'Employers value people who can solve real problems, document their work, and learn fast.',
            'Practical projects help you prove ability during interviews and client conversations.',
            'You can specialize later after understanding what kind of tech work fits you best.',
        ],
        'careers' => ['Junior Developer', 'IT Support Assistant', 'Data Assistant', 'Technical Support Officer'],
    ],
];

$scoreCourses = static function (array $goal, int $limit = 3) use ($courseMatchCourses): array {
    $scored = [];
    foreach ($courseMatchCourses as $course) {
        $blob = $course['_match_blob'] ?? '';
        $score = 0;
        foreach ($goal['keywords'] as $keyword) {
            if ($keyword !== '' && str_contains($blob, $keyword)) {
                $score += 3;
            }
        }
        foreach ($goal['secondary'] as $keyword) {
            if ($keyword !== '' && str_contains($blob, $keyword)) {
                $score += 1;
            }
        }
        if ($score > 0) {
            $course['_match_score'] = $score;
            $scored[] = $course;
        }
    }

    usort($scored, static fn($a, $b) => ($b['_match_score'] <=> $a['_match_score']) ?: strcmp($a['title'] ?? '', $b['title'] ?? ''));
    return array_slice($scored ?: $courseMatchCourses, 0, $limit);
};
?>
<section class="py-20 bg-white" id="course-match">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
      <div class="lg:col-span-4 lg:sticky lg:top-28">
        <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Course Finder</span>
        <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-950 mt-3">Which Course Is Right For Me?</h2>
        <p class="text-slate-500 text-sm sm:text-base font-medium leading-relaxed mt-4">
          Pick what you want to learn. We will point you to the closest available course tracks and show where those skills can take you.
        </p>
        <div class="mt-8 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-1 gap-2" role="tablist" aria-label="Course goals">
          <?php foreach ($courseGoals as $index => $goal): ?>
            <button type="button"
                    class="dazu-course-match-tab text-left rounded-xl border px-4 py-3 text-xs font-extrabold transition-all <?php echo $index === 0 ? 'border-[#15A24A] bg-green-50 text-slate-950' : 'border-slate-200 bg-white text-slate-500 hover:border-green-200 hover:text-slate-900'; ?>"
                    data-target="match-<?php echo htmlspecialchars($goal['id']); ?>"
                    aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>">
              <?php echo htmlspecialchars($goal['label']); ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="lg:col-span-8">
        <?php foreach ($courseGoals as $index => $goal): ?>
          <?php
            $matches = $scoreCourses($goal, 3);
            $primary = array_slice($matches, 0, 2);
            $also = array_slice($matches, 2, 1);
          ?>
          <div id="match-<?php echo htmlspecialchars($goal['id']); ?>"
               class="dazu-course-match-panel <?php echo $index === 0 ? '' : 'hidden'; ?>">
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 sm:p-8">
              <div class="max-w-2xl">
                <h3 class="text-2xl font-extrabold tracking-tight text-slate-950"><?php echo htmlspecialchars($goal['title']); ?></h3>
                <p class="text-slate-600 text-sm font-medium leading-relaxed mt-3"><?php echo htmlspecialchars($goal['summary']); ?></p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8">
                <?php foreach ($primary as $course): ?>
                  <a href="<?php echo htmlspecialchars(dazu_course_apply_url($course)); ?>"
                     class="group bg-white border border-slate-200 rounded-2xl p-5 hover:border-[#15A24A] hover:-translate-y-0.5 transition-all shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#15A24A]">Recommended Track</span>
                    <h4 class="text-lg font-extrabold text-slate-950 mt-2 group-hover:text-[#15A24A] transition-colors"><?php echo htmlspecialchars($course['title'] ?? 'Course'); ?></h4>
                    <p class="text-xs text-slate-500 font-semibold leading-relaxed mt-2 line-clamp-3">
                      <?php echo htmlspecialchars(strip_tags($course['overview'] ?? $course['description'] ?? 'Open the course to view the full study plan and apply.')); ?>
                    </p>
                    <span class="inline-flex items-center gap-1 text-xs font-extrabold text-slate-900 mt-4">View course <span aria-hidden="true">&rarr;</span></span>
                  </a>
                <?php endforeach; ?>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                <div class="bg-white border border-slate-200 rounded-2xl p-5">
                  <h4 class="text-sm font-extrabold uppercase tracking-wider text-slate-900">Why It Matters</h4>
                  <ul class="mt-4 space-y-3">
                    <?php foreach ($goal['why'] as $reason): ?>
                      <li class="flex gap-3 text-sm text-slate-600 font-medium leading-relaxed">
                        <span class="mt-1 h-2 w-2 rounded-full bg-[#15A24A] shrink-0"></span>
                        <span><?php echo htmlspecialchars($reason); ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-5">
                  <h4 class="text-sm font-extrabold uppercase tracking-wider text-slate-900">Where You Can Work</h4>
                  <div class="flex flex-wrap gap-2 mt-4">
                    <?php foreach ($goal['careers'] as $career): ?>
                      <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-600"><?php echo htmlspecialchars($career); ?></span>
                    <?php endforeach; ?>
                  </div>
                  <?php if ($also): ?>
                    <div class="mt-6 pt-5 border-t border-slate-100">
                      <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Also Consider</p>
                      <?php foreach ($also as $course): ?>
                        <a href="<?php echo htmlspecialchars(dazu_course_apply_url($course)); ?>" class="inline-flex mt-2 text-sm font-extrabold text-[#15A24A] hover:underline">
                          <?php echo htmlspecialchars($course['title'] ?? 'Related course'); ?>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  var tabs = document.querySelectorAll('.dazu-course-match-tab');
  var panels = document.querySelectorAll('.dazu-course-match-panel');
  if (!tabs.length || !panels.length) return;

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var target = tab.getAttribute('data-target');
      tabs.forEach(function (item) {
        var active = item === tab;
        item.setAttribute('aria-selected', active ? 'true' : 'false');
        item.classList.toggle('border-[#15A24A]', active);
        item.classList.toggle('bg-green-50', active);
        item.classList.toggle('text-slate-950', active);
        item.classList.toggle('border-slate-200', !active);
        item.classList.toggle('bg-white', !active);
        item.classList.toggle('text-slate-500', !active);
      });
      panels.forEach(function (panel) {
        panel.classList.toggle('hidden', panel.id !== target);
      });
    });
  });
})();
</script>
