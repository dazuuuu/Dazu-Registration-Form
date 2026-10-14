<?php
$page_title = 'About Us — Dazu Training Institute';
$page_description = 'Dazu Training Institute in Nairobi, Thika and online — hands-on labs, flexible classes, KICD-aligned curricula, and industry-led training for the next generation of innovators.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/controllers/GalleryController.php';

$teamMembers = (new \Models\TeamMemberModel($pdo))->all(true);

try {
    $galleryItems = (new GalleryController($pdo))->getAll('active', 8, 0);
} catch (Throwable $e) {
    $galleryItems = [];
}

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
        'q' => 'Where is Dazu Training Institute located?',
        'a' => 'We serve learners through Thika Town, Nairobi, and online classes. Our Thika campus labs are in Room 529 and surrounding training rooms, and our Nairobi intake supports learners who prefer the city location.',
    ],
    [
        'q' => 'Are the training sessions conducted physically or online?',
        'a' => 'Both. Most tracks offer physical lab sessions in Thika and Nairobi plus flexible online study modes. Choose your preferred mode when you apply for a course.',
    ],
    [
        'q' => 'Will I receive an industry-recognized certificate?',
        'a' => 'Yes. Students who complete all modules, projects, and assessments receive a Dazu Training Institute certificate aligned with practical industry competencies.',
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
        <span class="inline-block text-[#15A24A] text-xs uppercase tracking-widest font-extrabold px-3 py-1.5 bg-green-50 border border-green-200 rounded-full">About Dazu Training Institute</span>
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-slate-950 tracking-tight leading-tight uppercase">
          Empowering Next-Gen Innovators Across Kenya.
        </h1>
        <div class="space-y-4 text-slate-600 font-medium leading-relaxed">
          <p>Dazu Training Institute is a leading African technology and digital skills institute serving learners in Nairobi, Thika Town, and online. We equip students, graduates, professionals, entrepreneurs, and businesses with practical, job-ready skills for Africa's growing digital economy.</p>
          <p>We bridge the gap between traditional education and real industry demands through hands-on training, project-based learning, and mentorship. Our programs focus on software development, artificial intelligence, cybersecurity, data science, digital marketing, graphics design, automation, and other high-demand digital skills.</p>
          <p>Every learner builds real-world projects, gains practical experience, and develops the confidence to work, freelance, launch a business, or secure employment across Africa and beyond. Our mission is to develop skilled innovators, problem solvers, and technology leaders who drive Africa's digital transformation.</p>
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
            <span class="block text-3xl md:text-4xl font-extrabold text-[#15A24A]">3 Modes</span>
            <span class="block mt-2 text-[10px] uppercase tracking-widest font-bold text-slate-500">Nairobi, Thika & Online</span>
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
        <p class="text-slate-600 text-sm leading-relaxed">We believe skills are built through practice. Our training follows an 80% practical and 20% theory approach, where learners spend most of their time working on real projects, solving real problems, and using industry-standard tools. Every session is designed to build hands-on experience, confidence, and job-ready skills that prepare students for employment, freelancing, entrepreneurship, and professional growth.</p>
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

<!-- Gallery -->
<section class="bg-white py-20">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="max-w-2xl mb-12">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Life at Dazu Training Institute</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 mt-3 uppercase tracking-tight">Our Gallery</h2>
      <p class="text-slate-600 font-medium mt-4">A look inside our labs, classes, and student milestones across our Nairobi, Thika, and online learning community.</p>
    </div>

    <?php if (empty($galleryItems)): ?>
      <p class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-600">Gallery photos and videos will be available soon.</p>
    <?php else: ?>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php foreach ($galleryItems as $g):
            $isVideo = ($g['media_type'] ?? 'image') === 'video';
            $thumb = $g['thumbnail_path'] ?: $g['file_path'];
            if ($thumb && !preg_match('#^https?://#i', $thumb)) $thumb = url(ltrim($thumb, '/'));
        ?>
          <button type="button" class="dazu-about-gallery-open group relative aspect-square overflow-hidden rounded-2xl border border-slate-200 bg-slate-50"
                  data-title="<?php echo htmlspecialchars($g['title'], ENT_QUOTES); ?>"
                  data-desc="<?php echo htmlspecialchars($g['description'] ?? '', ENT_QUOTES); ?>"
                  data-video="<?php echo $isVideo ? '1' : '0'; ?>"
                  data-src="<?php echo htmlspecialchars($isVideo ? ($g['video_url'] ?? $g['file_path'] ?? '') : ($g['file_path'] ?? ''), ENT_QUOTES); ?>">
            <?php if ($thumb): ?>
              <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($g['title']); ?>" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
            <?php else: ?>
              <div class="w-full h-full flex items-center justify-center text-slate-300"><i class="fas fa-image text-3xl"></i></div>
            <?php endif; ?>
            <span class="absolute inset-0 bg-slate-900/0 group-hover:bg-slate-900/50 transition-colors flex items-center justify-center">
              <i class="fas <?php echo $isVideo ? 'fa-play' : 'fa-search-plus'; ?> text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
            </span>
            <?php if ($isVideo): ?>
              <span class="absolute top-2 right-2 w-7 h-7 rounded-full bg-slate-900/70 text-white flex items-center justify-center text-[10px]"><i class="fas fa-play"></i></span>
            <?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Gallery lightbox -->
<div id="dazu-about-gallery-lightbox" class="fixed inset-0 bg-slate-950/90 hidden items-center justify-center z-50 p-4">
  <button type="button" id="dazu-about-gallery-close" class="absolute top-5 right-5 text-white/80 hover:text-white text-2xl" aria-label="Close">
    <i class="fas fa-times"></i>
  </button>
  <div class="max-w-3xl w-full">
    <div id="dazu-about-gallery-media" class="w-full"></div>
    <div class="mt-3 text-center">
      <h3 id="dazu-about-gallery-title" class="text-white font-bold"></h3>
      <p id="dazu-about-gallery-desc" class="text-slate-300 text-sm mt-1"></p>
    </div>
  </div>
</div>
<script>
(function(){
  var lb = document.getElementById('dazu-about-gallery-lightbox');
  var media = document.getElementById('dazu-about-gallery-media');
  var titleEl = document.getElementById('dazu-about-gallery-title');
  var descEl = document.getElementById('dazu-about-gallery-desc');

  function youtubeId(url) {
    var m = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
    return m ? m[1] : '';
  }

  document.querySelectorAll('.dazu-about-gallery-open').forEach(function(btn){
    btn.addEventListener('click', function(){
      var src = btn.getAttribute('data-src');
      var isVideo = btn.getAttribute('data-video') === '1';
      titleEl.textContent = btn.getAttribute('data-title') || '';
      descEl.textContent = btn.getAttribute('data-desc') || '';
      media.innerHTML = '';
      if (isVideo && /youtube\.com|youtu\.be/.test(src)) {
        media.innerHTML = '<div class="aspect-video rounded-xl overflow-hidden"><iframe class="w-full h-full" src="https://www.youtube.com/embed/' + youtubeId(src) + '" allowfullscreen title="Video"></iframe></div>';
      } else if (isVideo) {
        media.innerHTML = '<video controls class="w-full rounded-xl max-h-[70vh]"><source src="' + src + '" type="video/mp4"></video>';
      } else {
        media.innerHTML = '<img src="' + src + '" alt="" class="w-full rounded-xl max-h-[70vh] object-contain mx-auto">';
      }
      lb.classList.remove('hidden'); lb.classList.add('flex');
    });
  });
  function closeLb(){ lb.classList.add('hidden'); lb.classList.remove('flex'); media.innerHTML=''; }
  document.getElementById('dazu-about-gallery-close').addEventListener('click', closeLb);
  lb.addEventListener('click', function(e){ if (e.target === lb) closeLb(); });
})();
</script>

<!-- Team -->
<?php
// Social icon links for a team member — only fields that are filled in are shown.
$teamIcons = [
  'phone'     => ['Call', '<path d="M6.6 10.8a15.1 15.1 0 006.6 6.6l2.2-2.2a1 1 0 011-.25 11.4 11.4 0 003.6.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1z"/>'],
  'email'     => ['Email', '<path d="M20 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2zm0 4l-8 5-8-5V6l8 5 8-5z"/>'],
  'whatsapp'  => ['WhatsApp', '<path d="M17.5 14.4c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.47-.88-.79-1.47-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.27.49 1.7.63.72.23 1.37.2 1.88.12.58-.09 1.75-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.15l-.3-.18-3 .78.8-2.9-.2-.3A8.2 8.2 0 1112 20.2z"/>'],
  'tiktok'    => ['TikTok', '<path d="M16.6 5.8A4.3 4.3 0 0115.5 3h-3.1v12.4a2.6 2.6 0 11-2.6-2.6c.27 0 .53.04.78.12V9.7a5.8 5.8 0 105 5.7V9.1a7.4 7.4 0 004.3 1.4V7.4a4.3 4.3 0 01-3.3-1.6z"/>'],
  'linkedin'  => ['LinkedIn', '<path d="M4.98 3.5a2.5 2.5 0 11-.02 5 2.5 2.5 0 01.02-5zM3 9h4v12H3zm7 0h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.6c0-1.33-.02-3.05-1.86-3.05-1.86 0-2.15 1.45-2.15 2.95V21h-4z"/>'],
  'facebook'  => ['Facebook', '<path d="M22 12a10 10 0 10-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0022 12z"/>'],
  'instagram' => ['Instagram', '<path d="M12 7.3a4.7 4.7 0 100 9.4 4.7 4.7 0 000-9.4zm0 7.7a3 3 0 110-6 3 3 0 010 6zm6-7.9a1.1 1.1 0 11-2.2 0 1.1 1.1 0 012.2 0zM21.9 8.1c-.1-1.6-.4-3-1.6-4.2S17.7 2.2 16 2.1C14.4 2 9.6 2 8 2.1c-1.6.1-3 .4-4.2 1.6S2.2 6.3 2.1 8c-.1 1.6-.1 6.4 0 8 .1 1.6.4 3 1.6 4.2s2.6 1.5 4.2 1.6c1.6.1 6.4.1 8 0 1.6-.1 3-.4 4.2-1.6s1.5-2.6 1.6-4.2c.1-1.6.1-6.4 0-8zm-2 9.7a3 3 0 01-1.7 1.7c-1.2.5-4 .4-5.2.4s-4.1.1-5.2-.4a3 3 0 01-1.7-1.7c-.5-1.2-.4-4-.4-5.3s-.1-4.1.4-5.2a3 3 0 011.7-1.7C7.9 5.1 10.7 5.2 12 5.2s4.1-.1 5.2.4a3 3 0 011.7 1.7c.5 1.2.4 4 .4 5.2s.1 4.1-.4 5.3z"/>'],
];
$teamHref = static function (string $key, string $v): string {
  $v = trim($v);
  if ($key === 'phone') return 'tel:' . preg_replace('/[^\d+]/', '', $v);
  if ($key === 'email') return 'mailto:' . $v;
  if ($key === 'whatsapp') {
    if (preg_match('#^https?://#i', $v)) return $v;
    $d = preg_replace('/\D/', '', $v);
    if (str_starts_with($d, '0')) $d = '254' . substr($d, 1); // Kenyan local number → international
    return 'https://wa.me/' . $d;
  }
  return preg_match('#^https?://#i', $v) ? $v : 'https://' . ltrim($v, '/');
};
?>
<style>
.dz-team{background:#f6f8f7;border-top:1px solid #eef2f0;padding:80px 16px}
.dz-team *{box-sizing:border-box}
.dz-team-wrap{max-width:1040px;margin:0 auto}
.dz-team-grid{display:grid;grid-template-columns:1fr;gap:20px}
@media(min-width:600px){.dz-team-grid{grid-template-columns:repeat(2,1fr)}}
@media(min-width:960px){.dz-team-grid{grid-template-columns:repeat(3,1fr);gap:24px}}
.dz-team-intro{background:#15803d;background:linear-gradient(160deg,#16a34a,#14532d);color:#fff;border-radius:14px;padding:32px 28px;display:flex;flex-direction:column;justify-content:center;min-height:280px;margin-top:84px}
.dz-team-intro h2{font-size:30px;line-height:1.2;font-weight:800;margin:0 0 14px;letter-spacing:-.01em}
.dz-team-intro p{font-size:15px;line-height:1.6;opacity:.9;margin:0 0 24px}
.dz-team-intro a{align-self:flex-start;background:#fff;color:#14532d;font-size:13px;font-weight:700;padding:10px 20px;border-radius:999px;text-decoration:none;transition:.15s}
.dz-team-intro a:hover{background:#dcfce7}
.dz-team-card{position:relative;margin-top:84px;padding:96px 26px 34px;background:#15A24A;color:#fff;text-align:center;border-radius:120px 0 120px 0;transition:transform .25s,box-shadow .25s}
.dz-team-card:hover{transform:translateY(-4px);box-shadow:0 18px 40px rgba(21,162,74,.3)}
.dz-team-photo{position:absolute;top:-84px;left:14%;width:168px;height:168px;border-radius:50%;background:#15A24A;padding:8px;overflow:hidden}
.dz-team-photo img,.dz-team-initial{width:100%;height:100%;border-radius:50%;object-fit:cover;object-position:top;display:block;background:#fff}
.dz-team-initial{display:flex;align-items:center;justify-content:center;font-size:56px;font-weight:800;color:#15A24A}
.dz-team-card h3{font-size:17px;font-weight:700;text-transform:uppercase;letter-spacing:.02em;margin:0 0 4px;color:#fff}
.dz-team-role{display:block;font-size:13px;text-transform:uppercase;letter-spacing:.03em;color:#e2e8f0;margin-bottom:14px}
.dz-team-bio{font-size:12.5px;line-height:1.6;color:#e2e8f0;margin:0 auto 20px;max-width:260px}
.dz-team-social{display:flex;flex-wrap:wrap;justify-content:center;gap:8px}
.dz-team-social a{width:26px;height:26px;border-radius:50%;background:#fff;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 1px 3px rgba(0,0,0,.15);transition:transform .15s}
.dz-team-social a:hover{transform:translateY(-2px)}
.dz-team-social svg{width:14px;height:14px;fill:currentColor}
.dz-team-social .is-phone{color:#15A24A}
.dz-team-social .is-email{color:#EA4335}
.dz-team-social .is-whatsapp{color:#25D366}
.dz-team-social .is-tiktok{color:#000}
.dz-team-social .is-linkedin{color:#0A66C2}
.dz-team-social .is-facebook{color:#1877F2}
.dz-team-social .is-instagram svg{fill:url(#dz-ig-grad)}
.dz-team-empty{border:1px dashed #cbd5e1;border-radius:14px;padding:40px;text-align:center;color:#64748b;background:#fff}
</style>
<section class="dz-team" id="team">
  <svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs><linearGradient id="dz-ig-grad" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#FEDA75"/><stop offset=".3" stop-color="#FA7E1E"/><stop offset=".6" stop-color="#D62976"/><stop offset=".8" stop-color="#962FBF"/><stop offset="1" stop-color="#4F5BD5"/></linearGradient></defs></svg>
  <div class="dz-team-wrap">
    <div class="dz-team-grid">
      <div class="dz-team-intro">
        <h2>Meet our team</h2>
        <p>Our founder, developers and tutors work together to make every learning experience practical and career-focused.</p>
        <a href="<?php echo url('contact'); ?>">Join the team</a>
      </div>
      <?php if (!$teamMembers): ?>
        <div class="dz-team-empty" style="grid-column:span 2">Our team profiles will be available soon.</div>
      <?php endif; ?>
      <?php foreach ($teamMembers as $t): ?>
        <article class="dz-team-card">
          <div class="dz-team-photo">
            <?php if (!empty($t['photo_path'])): ?>
              <img src="<?php echo htmlspecialchars(url($t['photo_path']), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>" loading="lazy">
            <?php else: ?>
              <div class="dz-team-initial"><?php echo htmlspecialchars(strtoupper(substr($t['name'], 0, 1))); ?></div>
            <?php endif; ?>
          </div>
          <h3><?php echo htmlspecialchars($t['name']); ?></h3>
          <span class="dz-team-role"><?php echo htmlspecialchars($t['role']); ?></span>
          <?php if (!empty($t['bio'])): ?>
            <p class="dz-team-bio"><?php echo htmlspecialchars($t['bio']); ?></p>
          <?php endif; ?>
            <div class="dz-team-social">
              <?php foreach ($teamIcons as $key => [$label, $path]): $v = trim((string) ($t[$key] ?? '')); if ($v === '') continue; $ext = !in_array($key, ['phone', 'email'], true); ?>
                <a class="is-<?php echo $key; ?>" href="<?php echo htmlspecialchars($teamHref($key, $v), ENT_QUOTES); ?>" aria-label="<?php echo htmlspecialchars($label . ' ' . $t['name'], ENT_QUOTES); ?>" title="<?php echo $label; ?>"<?php echo $ext ? ' target="_blank" rel="noopener"' : ''; ?>><svg viewBox="0 0 24 24" aria-hidden="true"><?php echo $path; ?></svg></a>
              <?php endforeach; ?>
            </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="bg-slate-50 border-t border-slate-100 py-20">
  <div class="max-w-[800px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="text-center mb-12">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Got Questions?</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 mt-3 uppercase tracking-tight">Frequently Asked Questions</h2>
      <p class="text-slate-600 font-medium mt-4">Here are direct, helpful answers to the common questions about Dazu Training Institute courses, campus, and fees.</p>
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

if (empty($og_image) && !empty($teamMembers)) {
    foreach ($teamMembers as $t) {
        if (!empty($t['photo_path'])) {
            $og_image = url($t['photo_path']);
            break;
        }
    }
}
if (empty($og_image) && !empty($galleryItems)) {
    foreach ($galleryItems as $g) {
        if (!empty($g['file_path'])) {
            $og_image = $g['file_path'];
            break;
        }
    }
}

require __DIR__ . '/../templates/public/dazu_layout.php';
