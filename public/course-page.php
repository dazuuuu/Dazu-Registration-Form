<?php
// public/course-page.php — a course landing page at the site root: dazu.ac.ke/<slug> (the router in public/index.php sends
// single-word addresses here). Hero with the preview video (or the cover), the sections, the carousel cards and, on the right
// (below on phones), the same enrolment form as the course page. Its own meta title, description, keywords, share picture
// (the course cover) and structured data (Course + breadcrumbs) for Google. Pages are made in Admin → Courses → Course pages.
require_once __DIR__ . '/../app/v1/init.php';
require_once __DIR__ . '/../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../app/v1/helpers/course_catalog.php';
require_once __DIR__ . '/../app/v1/helpers/enrolment_offer.php';
require_once __DIR__ . '/../app/v1/models/Connection.php';
require_once __DIR__ . '/../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../app/v1/services/CourseRegistrationHandler.php';
require_once __DIR__ . '/../app/v1/services/CoursePageService.php';

use Models\Connection;
use Models\CourseModel;
use Services\CourseRegistrationHandler;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$slug = strtolower((string) ($_GET['slug'] ?? ''));
$cpage = cp_get_by_slug($pdo, $slug);
$course = $cpage ? $courseModel->getById((int) $cpage['course_id']) : null;
if (!$cpage || !course_is_shareable($course)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$course = course_catalog_enrich($course);
if (!empty($course['category_id'])) {   // the label above the heading: the course's own category
    $cc = $pdo->prepare('SELECT name FROM course_categories WHERE id = ?');
    $cc->execute([(int) $course['category_id']]);
    $course['category_name'] = (string) $cc->fetchColumn();
}
$courseId = (int) $course['id'];
$pageUrl = cp_url($cpage);

// The form: same rules as courses/view.php. The course is fixed to this page's course.
$courseChoices = [];
$allowCourseChange = false;
$ikPart = 'prepare';
include __DIR__ . '/components/public/intake_panel.php';
$reg_success = false;
$reg_error = null;
$issued_reg_no = '';
$enrolment_offer_email = '';
if (!empty($_GET['applied']) && enrolment_offer_consume_show()) {
    $offer = enrolment_offer_get();
    $reg_success = true;
    $issued_reg_no = (string) ($offer['registration_no'] ?? '');
    $enrolment_offer_email = (string) ($offer['email'] ?? '');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = (new CourseRegistrationHandler($pdo))->process($_POST, $_FILES, $course, null);
    if ($result['ok']) {
        redirect($pageUrl . '?applied=1#apply');
        exit;
    }
    $reg_error = $result['error'] ?? 'Registration failed.';
} elseif (!preg_match('/bot|crawl|spider|preview/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
    try { $pdo->prepare('UPDATE course_pages SET views = views + 1 WHERE id = ?')->execute([(int) $cpage['id']]); } catch (Throwable $e) {}
}

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sections = json_decode((string) $cpage['sections_json'], true) ?: [];
$cards = json_decode((string) $cpage['cards_json'], true) ?: [];
$cover = course_og_image_url($course);
$video = cp_video_html($cpage['video_url'], (string) $course['title']);
$months = (int) ($course['duration_months'] ?? 0);
$fee = (float) ($course['course_fee'] ?? 0);

// SEO and sharing: this page's own title, description, keywords, share picture and address.
$page_title = $cpage['meta_title'];
$page_description = $cpage['meta_description'];
$page_tags = array_values(array_filter(array_map('trim', explode(',', (string) $cpage['keywords']))));
$og_image = $cover;
$og_url = $pageUrl;
$GLOBALS['dazu_canonical'] = $pageUrl;
$home = course_share_absolute_url(url('/'));
$ld = [
    ['@context' => 'https://schema.org', '@type' => 'Course', 'name' => $course['title'], 'description' => $cpage['meta_description'], 'url' => $pageUrl, 'image' => $cover,
     'provider' => ['@type' => 'EducationalOrganization', 'name' => 'Dazu Training Institute', 'sameAs' => $home],
     'hasCourseInstance' => ['@type' => 'CourseInstance', 'courseMode' => ['online', 'onsite'], 'location' => 'Nairobi and Thika, Kenya'] + ($months ? ['courseWorkload' => 'P' . $months . 'M'] : []),
     'offers' => ['@type' => 'Offer', 'category' => $fee > 0 ? 'Paid' : 'Free', 'price' => $fee, 'priceCurrency' => 'KES', 'availability' => 'https://schema.org/InStock', 'url' => $pageUrl]],
    ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $home],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Courses', 'item' => course_share_absolute_url(url('courses'))],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $course['title'], 'item' => $pageUrl]]],
];
$faq = [];
foreach ($sections as $s) {
    if (preg_match_all('/^Q:\s*(.+)\R+A:\s*(.+)$/mi', (string) $s['body'], $m, PREG_SET_ORDER)) {
        foreach ($m as $x) $faq[] = ['@type' => 'Question', 'name' => trim($x[1]), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim($x[2])]];
    }
}
if ($faq) $ld[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq];
$extra_head = ($extra_head ?? '') . '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';

ob_start();
?>
<style>
/* Own styles: the site's built CSS only has the classes the other pages use. */
.cp-hero{background:linear-gradient(135deg,#020617 0%,#0f172a 55%,#03341d 100%);color:#fff}
.cp-hero h1{color:#fff;font-size:clamp(1.9rem,4vw,3rem);line-height:1.1;font-weight:800;letter-spacing:-.02em;margin:0 0 1rem}
.cp-hero .cp-lead{color:#cbd5e1;font-size:1.05rem;line-height:1.7;margin:0 0 1.5rem}
.cp-hero .cp-crumbs,.cp-hero .cp-crumbs a{color:#94a3b8;font-size:.75rem;text-decoration:none}.cp-hero .cp-crumbs a:hover{color:#fff}
.cp-chip{display:inline-block;font-size:10px;letter-spacing:.15em;text-transform:uppercase;font-weight:800;padding:.3rem .8rem;border-radius:999px;background:rgba(4,140,68,.2);color:#86efac;border:1px solid rgba(34,197,94,.3);margin-bottom:1rem}
.cp-fact{display:inline-block;padding:.4rem .8rem;border-radius:.5rem;background:rgba(255,255,255,.1);color:#fff;font-size:.85rem;margin:0 .4rem .5rem 0}
.cp-btn{display:inline-block;padding:.9rem 1.75rem;background:#048C44;color:#fff;font-size:.85rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;border-radius:.75rem;text-decoration:none}.cp-btn:hover{background:#037A3B;color:#fff}
.cp-media{border-radius:1rem;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.6);aspect-ratio:16/9;background:#000}.cp-media img,.cp-media iframe,.cp-media video{width:100%;height:100%;object-fit:cover;border:0;display:block}
.cp-track{display:flex;gap:1rem;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding-bottom:.5rem;scrollbar-width:thin}
.cp-track article{flex:0 0 78%;scroll-snap-align:start;border:1px solid #e2e8f0;border-radius:1rem;overflow:hidden;background:#fff}
@media(min-width:640px){.cp-track article{flex-basis:46%}}
.cp-card-top{height:7rem;position:relative;background-size:cover;background-position:center}.cp-card-top span{position:absolute;left:1rem;bottom:.6rem;color:rgba(255,255,255,.92);font-size:1.9rem;font-weight:800}
.cp-nav{height:2.25rem;width:2.25rem;border-radius:999px;border:1px solid #cbd5e1;background:#fff;color:#334155;font-size:1.1rem;line-height:1}.cp-nav:hover{border-color:#048C44;color:#048C44}
</style>
<!-- Hero -->
<section class="cp-hero">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 py-12 md:py-16 grid lg:grid-cols-2 gap-10 items-center">
    <div>
      <nav class="cp-crumbs mb-4" aria-label="Breadcrumb"><a href="<?php echo url('/'); ?>">Home</a> / <a href="<?php echo url('courses'); ?>">Courses</a> / <span style="color:#e2e8f0"><?php echo $e($course['title']); ?></span></nav>
      <span class="cp-chip"><?php echo $e($course['category_name'] ?? '' ?: dazu_course_category($course['abbreviation'] ?? '')); ?></span>
      <h1><?php echo $e($cpage['hero_heading']); ?></h1>
      <?php if ($cpage['hero_text']): ?><p class="cp-lead"><?php echo $e($cpage['hero_text']); ?></p><?php endif; ?>
      <div class="mb-5">
        <?php if ($months): ?><span class="cp-fact"><?php echo $months; ?> month<?php echo $months > 1 ? 's' : ''; ?></span><?php endif; ?>
        <span class="cp-fact">Online or on campus</span>
        <span class="cp-fact"><?php echo $fee > 0 ? 'KSh ' . number_format($fee) : 'Free'; ?></span>
        <span class="cp-fact">Certificate</span>
      </div>
      <a href="#apply" class="cp-btn">Apply now</a>
    </div>
    <div class="cp-media">
      <?php echo $video ?: '<img src="' . $e($cover) . '" alt="' . $e($course['title']) . ' course at Dazu Training Institute" width="1200" height="630">'; ?>
    </div>
  </div>
</section>

<div class="bg-slate-50 py-10 md:py-14">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
    <div class="lg:col-span-7 space-y-6">
      <?php foreach ($sections as $i => $s): ?>
        <section class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8">
          <?php if ($s['title'] !== ''): ?><h2 class="text-xl md:text-2xl font-extrabold text-slate-950 tracking-tight mb-4"><?php echo $e($s['title']); ?></h2><?php endif; ?>
          <?php echo cp_body_html((string) $s['body']); ?>
        </section>
        <?php if ($i === 1 && $cards): // the carousel after "What you will learn" ?>
        <section class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8" aria-label="Course modules">
          <div class="flex items-center justify-between mb-4"><h2 class="text-xl md:text-2xl font-extrabold text-slate-950 tracking-tight">Inside the course</h2>
            <div class="flex gap-2"><button type="button" class="cp-prev cp-nav" aria-label="Previous">‹</button><button type="button" class="cp-next cp-nav" aria-label="Next">›</button></div></div>
          <div class="cp-track">
            <?php foreach ($cards as $n => $c): ?>
              <article>
                <div class="cp-card-top" style="background-image:linear-gradient(135deg,rgba(4,140,68,.88),rgba(3,52,29,.9)),url('<?php echo $e($cover); ?>')">
                  <span><?php echo str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT); ?></span></div>
                <div class="p-4"><h3 class="font-extrabold text-slate-900 mb-1"><?php echo $e($c['title']); ?></h3><p class="text-sm text-slate-600 leading-relaxed"><?php echo $e($c['text']); ?></p></div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <aside class="lg:col-span-5 lg:sticky lg:top-24" id="apply">
      <?php $ikPart = 'top'; include __DIR__ . '/components/public/intake_panel.php'; ?>
      <?php if ($ikShowForm || $reg_success): $pfx = 'lp'; include __DIR__ . '/components/public/dazu_admission_form.php'; endif; ?>
      <?php $ikPart = 'after'; include __DIR__ . '/components/public/intake_panel.php'; ?>
    </aside>
  </div>
</div>
<script>
document.querySelectorAll('.cp-track').forEach(function (t) {
  var box = t.closest('section'), step = function () { var c = t.querySelector('article'); return c ? c.offsetWidth + 16 : 300; };
  box.querySelector('.cp-prev').onclick = function () { t.scrollBy({ left: -step(), behavior: 'smooth' }); };
  box.querySelector('.cp-next').onclick = function () { t.scrollBy({ left: step(), behavior: 'smooth' }); };
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/public/dazu_layout.php';
