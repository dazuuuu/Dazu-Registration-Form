<?php
$page_title = 'About Us — Dazu Hub Institute';
$page_description = 'Dazu Hub Tech Skills Institute in Thika Town — hands-on labs, KICD-aligned curricula, and industry-led training for the next generation of innovators.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';

$teamMembers = (new \Models\TeamMemberModel($pdo))->all(true);

$trainers = [
    [
        'initials' => 'DK',
        'name' => 'ENG. DAVIS KAMAU',
        'title' => 'Lead Software & Cloud Architect',
        'bio' => 'Ex-Safaricom software engineer with 8+ years experience developing React systems, microservices, and secure databases.',
    ],
    [
        'initials' => 'CW',
        'name' => 'DR. CATHERINE WAMBUI',
        'title' => 'AI & Data Science Specialist',
        'bio' => 'Active data consultant specializing in custom LLMs, prompt pipelines, business intelligence, and Power BI reporting.',
    ],
    [
        'initials' => 'MM',
        'name' => 'MARK MWENDA',
        'title' => 'Cybersecurity & Cisco Instructor',
        'bio' => 'Certified Security Engineer lecturing ethical hacking, network defenses, router setups, and CompTIA systems.',
    ],
];

$faqs = [
    [
        'q' => 'Where is Dazu Hub Institute located physically?',
        'a' => 'We are based in Thika Town. Our primary campus labs are in Room 529 and surrounding training rooms — visit our Contact page for directions and a live map.',
    ],
    [
        'q' => 'Are the training sessions conducted physically or online?',
        'a' => 'Both. Most tracks offer physical lab sessions in Thika plus flexible online study modes. Choose your preferred mode when you apply for a course.',
    ],
    [
        'q' => 'Will I receive an industry-recognized certificate?',
        'a' => 'Yes. Students who complete all modules, projects, and assessments receive a Dazu Hub Institute certificate aligned with practical industry competencies.',
    ],
    [
        'q' => 'Do you offer flexible installment options for tuition fees?',
        'a' => 'Yes. We support monthly, 3-month, and full-payment plans. Registration fees and M-Pesa paybill details are shown on each course application page.',
    ],
    [
        'q' => 'What are the entry prerequisites for the courses?',
        'a' => 'Most beginner tracks require basic computer literacy. Advanced courses may expect prior programming or networking fundamentals — each course page lists specific requirements.',
    ],
];

ob_start();
?>

<!-- Hero + stats -->
<section class="bg-white py-16 md:py-24">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
      <div class="space-y-6">
        <span class="inline-block text-[#15A24A] text-xs uppercase tracking-widest font-extrabold px-3 py-1.5 bg-green-50 border border-green-200 rounded-full">About Dazu Hub</span>
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-slate-950 tracking-tight leading-tight uppercase">
          Empowering Next-Gen Innovators in Thika.
        </h1>
        <div class="space-y-4 text-slate-600 font-medium leading-relaxed">
          <p>Dazu Hub Tech Skills Institute is a premier technology training school located in Thika Town. We bridge the critical gap between university theory and high-growth industry standards.</p>
          <p>Our training is designed around physical coding labs, daily software execution, and custom digital graphic sprints. We provide students, professionals, and corporate businesses with the practical expertise needed to innovate in today's tech-driven ecosystem.</p>
        </div>
        <div class="flex flex-wrap gap-3 pt-2">
          <a href="<?php echo url('courses/index.php'); ?>" class="px-6 py-3.5 text-xs font-extrabold tracking-widest uppercase text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl transition-colors">Apply for Admission</a>
          <a href="<?php echo url('courses/index.php'); ?>" class="px-6 py-3.5 text-xs font-extrabold tracking-widest uppercase text-slate-900 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl transition-colors">View Our Courses</a>
        </div>
      </div>

      <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 md:p-8">
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-white border border-slate-200 rounded-xl p-6 text-center">
            <span class="block text-3xl md:text-4xl font-extrabold text-[#15A24A]">100%</span>
            <span class="block mt-2 text-[10px] uppercase tracking-widest font-bold text-slate-500">Hands-On Labs</span>
          </div>
          <div class="bg-white border border-slate-200 rounded-xl p-6 text-center">
            <span class="block text-3xl md:text-4xl font-extrabold text-[#15A24A]">85%</span>
            <span class="block mt-2 text-[10px] uppercase tracking-widest font-bold text-slate-500">Job Placement Support</span>
          </div>
          <div class="bg-white border border-slate-200 rounded-xl p-6 text-center">
            <span class="block text-3xl md:text-4xl font-extrabold text-[#15A24A]">Yes</span>
            <span class="block mt-2 text-[10px] uppercase tracking-widest font-bold text-slate-500">KICD-Aligned Curriculums</span>
          </div>
          <div class="bg-white border border-slate-200 rounded-xl p-6 text-center">
            <span class="block text-3xl md:text-4xl font-extrabold text-[#15A24A]">Room 529</span>
            <span class="block mt-2 text-[10px] uppercase tracking-widest font-bold text-slate-500">Thika Campus Rooms</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Value pillars -->
<section class="bg-slate-50 border-y border-slate-100 py-20">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="max-w-2xl mb-12">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Value Pillars</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 mt-3 uppercase tracking-tight">Our Core Mission and Values</h2>
      <p class="text-slate-600 font-medium mt-4">We operate under three primary philosophies to guarantee value for every single minute spent in our technical lectures.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white border border-slate-200 rounded-2xl p-8">
        <div class="w-12 h-12 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-[#15A24A] mb-6">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><circle cx="12" cy="12" r="5" stroke-width="2"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/></svg>
        </div>
        <h3 class="text-sm font-extrabold text-slate-950 uppercase tracking-wide mb-3">Career Alignment</h3>
        <p class="text-slate-600 text-sm leading-relaxed">Our courses align closely with technical requirements drafted by software studios, networking firms, and business sectors.</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-8">
        <div class="w-12 h-12 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-[#15A24A] mb-6">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
        </div>
        <h3 class="text-sm font-extrabold text-slate-950 uppercase tracking-wide mb-3">Lab-First Philosophy</h3>
        <p class="text-slate-600 text-sm leading-relaxed">We skip boring theory and jump straight into functional labs where students type code, structure firewalls, and compile models.</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-8">
        <div class="w-12 h-12 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-[#15A24A] mb-6">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>
        <h3 class="text-sm font-extrabold text-slate-950 uppercase tracking-wide mb-3">Uncompromising Integrity</h3>
        <p class="text-slate-600 text-sm leading-relaxed">Honest mentoring, clear evaluation rubrics, and high accountability for every enrolled student.</p>
      </div>
    </div>
  </div>
</section>

<!-- Team -->
<section class="bg-white py-20">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="max-w-2xl mb-12">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">The people behind Dazu Hub</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 mt-3 uppercase tracking-tight">Meet Our Team</h2>
      <p class="text-slate-600 font-medium mt-4">Our founder, senior developer and tutors work together to make every learning experience practical and career-focused.</p>
    </div>

    <?php if (!$teamMembers): ?>
      <p class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-600">Our team profiles will be available soon.</p>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-10">
        <?php foreach ($teamMembers as $t): ?>
          <article class="group mx-auto w-full max-w-[320px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-slate-200/70">
            <div class="relative aspect-[16/10] overflow-hidden bg-gradient-to-br from-emerald-100 via-slate-100 to-white">
              <?php if (!empty($t['photo_path'])): ?>
                <img class="h-full w-full object-cover transition duration-500 group-hover:scale-105" src="<?php echo htmlspecialchars(url($t['photo_path']), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>">
              <?php else: ?>
                <div class="flex h-full w-full items-center justify-center bg-[#0c1f17] text-6xl font-extrabold text-white">
                  <?php echo htmlspecialchars(strtoupper(substr($t['name'], 0, 1))); ?>
                </div>
              <?php endif; ?>
              <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-slate-950/45 to-transparent"></div>
              <span class="absolute bottom-4 left-4 rounded-full bg-white/95 px-2.5 py-1 text-[9px] font-extrabold uppercase tracking-widest text-[#15803d] shadow-sm"><?php echo htmlspecialchars($t['role']); ?></span>
            </div>
            <div class="p-5">
              <h3 class="text-lg font-extrabold tracking-tight text-slate-950"><?php echo htmlspecialchars($t['name']); ?></h3>
              <p class="mt-2 text-sm leading-relaxed text-slate-600"><?php echo htmlspecialchars($t['bio'] ?? ''); ?></p>
              <div class="mt-5 flex items-center gap-2 border-t border-slate-100 pt-3 text-[9px] font-bold uppercase tracking-[0.14em] text-slate-400">
                <span class="h-2 w-2 rounded-full bg-[#15A24A]"></span>
                Dazu Hub team
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- FAQ -->
<section class="bg-slate-50 border-t border-slate-100 py-20">
  <div class="max-w-[800px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="text-center mb-12">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Got Questions?</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 mt-3 uppercase tracking-tight">Frequently Asked Questions</h2>
      <p class="text-slate-600 font-medium mt-4">Here are direct, helpful answers to the common questions about Dazu Hub courses, campus, and fees.</p>
    </div>

    <div class="space-y-3" id="dazu-faq">
      <?php foreach ($faqs as $i => $faq): ?>
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <button type="button"
                  class="dazu-faq-toggle w-full flex items-center justify-between gap-4 px-5 py-4 text-left font-bold text-slate-900 hover:bg-slate-50 transition-colors"
                  aria-expanded="false"
                  data-faq="<?php echo $i; ?>">
            <span class="text-sm md:text-base"><?php echo htmlspecialchars($faq['q']); ?></span>
            <span class="dazu-faq-icon shrink-0 text-[#15A24A] text-xl font-light leading-none">+</span>
          </button>
          <div class="dazu-faq-panel hidden px-5 pb-5 text-slate-600 text-sm leading-relaxed border-t border-slate-100 pt-4">
            <?php echo htmlspecialchars($faq['a']); ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
$extra_js = <<<'HTML'
<script>
(function(){
  document.querySelectorAll('.dazu-faq-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
      var panel = btn.nextElementSibling;
      var icon = btn.querySelector('.dazu-faq-icon');
      var open = !panel.classList.contains('hidden');
      document.querySelectorAll('.dazu-faq-panel').forEach(function(p){ p.classList.add('hidden'); });
      document.querySelectorAll('.dazu-faq-toggle').forEach(function(b){
        b.setAttribute('aria-expanded', 'false');
        var ic = b.querySelector('.dazu-faq-icon');
        if (ic) ic.textContent = '+';
      });
      if (!open) {
        panel.classList.remove('hidden');
        btn.setAttribute('aria-expanded', 'true');
        if (icon) icon.textContent = '−';
      }
    });
  });
})();
</script>
HTML;

$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
