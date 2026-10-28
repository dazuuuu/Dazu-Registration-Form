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

<!-- Vision, mission, core commitment -->
<section class="bg-slate-50 border-y border-slate-100 py-20">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="max-w-2xl mb-12">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Who We Are</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 mt-3 uppercase tracking-tight">Our Vision, Mission and Commitment</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white border border-slate-200 rounded-2xl p-8">
        <div class="w-12 h-12 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-[#15A24A] mb-6">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        </div>
        <h3 class="text-sm font-extrabold text-slate-950 uppercase tracking-wide mb-3">Our Vision</h3>
        <p class="text-slate-600 text-sm leading-relaxed">To be Africa&rsquo;s leading hub for technology, innovation, digital and practical skills.</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-8">
        <div class="w-12 h-12 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-[#15A24A] mb-6">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><circle cx="12" cy="12" r="5" stroke-width="2"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/></svg>
        </div>
        <h3 class="text-sm font-extrabold text-slate-950 uppercase tracking-wide mb-3">Our Mission</h3>
        <p class="text-slate-600 text-sm leading-relaxed">To equip learners with practical digital skills for careers, entrepreneurship, and innovation.</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-8">
        <div class="w-12 h-12 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-[#15A24A] mb-6">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>
        <h3 class="text-sm font-extrabold text-slate-950 uppercase tracking-wide mb-3">Our Core Commitment</h3>
        <p class="text-slate-600 text-sm leading-relaxed">Practical Skills. Digital Innovation. Limitless Opportunities.</p>
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
.dz-team{background:#fff;padding:80px 16px 90px}
.dz-team *{box-sizing:border-box}
.dz-team-wrap{max-width:1120px;margin:0 auto}
.dz-team-title{text-align:center;font-size:clamp(28px,3.6vw,40px);line-height:1.2;font-weight:700;color:#1e293b;letter-spacing:-.02em;margin:0 auto 64px;max-width:520px;text-transform:none}
.dz-team-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:40px 14px}
@media(min-width:640px){.dz-team-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(min-width:960px){.dz-team-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:56px 10px}}
.dz-team-photo{position:relative;aspect-ratio:1/0.88;border-radius:14px;overflow:hidden;display:flex;align-items:flex-end;justify-content:center}
/* soft pastel backdrops, in the same order as the design */
.dz-team-card:nth-child(8n+1) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#f1fbe2 100%)}
.dz-team-card:nth-child(8n+2) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#e3d3fb 100%)}
.dz-team-card:nth-child(8n+3) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#fbd3e6 100%)}
.dz-team-card:nth-child(8n+4) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#d3e7fb 100%)}
.dz-team-card:nth-child(8n+5) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#dbefc8 100%)}
.dz-team-card:nth-child(8n+6) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#c9ece6 100%)}
.dz-team-card:nth-child(8n+7) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#cfdde8 100%)}
.dz-team-card:nth-child(8n+8) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#f6e6d6 100%)}
.dz-team-photo img{width:100%;height:100%;object-fit:cover;object-position:top center;display:block}
.dz-team-initial{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:64px;font-weight:700;color:#334155}
.dz-team-name{font-size:19px;line-height:1.3;font-weight:600;color:#0f172a;margin:18px 0 6px;text-transform:none;letter-spacing:-.01em}
.dz-team-role{display:block;font-size:14px;color:#64748b}
.dz-team-empty{border:1px dashed #cbd5e1;border-radius:14px;padding:40px;text-align:center;color:#64748b;grid-column:1/-1}
</style>
<section class="dz-team" id="team">
  <svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs><linearGradient id="dz-ig-grad" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#FEDA75"/><stop offset=".3" stop-color="#FA7E1E"/><stop offset=".6" stop-color="#D62976"/><stop offset=".8" stop-color="#962FBF"/><stop offset="1" stop-color="#4F5BD5"/></linearGradient></defs></svg>
  <div class="dz-team-wrap">
    <h2 class="dz-team-title">A Team That Never Says Impossible</h2>
    <div class="dz-team-grid">
      <?php if (!$teamMembers): ?>
        <div class="dz-team-empty">Our team profiles will be available soon.</div>
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
          <h3 class="dz-team-name"><?php $tn = trim((string) $t['name']); echo htmlspecialchars($tn === mb_strtoupper($tn) ? mb_convert_case(mb_strtolower($tn), MB_CASE_TITLE) : $tn); ?></h3>
          <span class="dz-team-role"><?php echo htmlspecialchars($t['role']); ?></span>
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
