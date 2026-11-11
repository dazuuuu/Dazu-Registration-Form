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
$cover = cp_cover_url($cpage, $course);
$video = cp_video_html($cpage['video_url'], (string) $course['title']);
$mediaHtml = $video ?: '<img src="' . htmlspecialchars($cover, ENT_QUOTES) . '" alt="' . htmlspecialchars($course['title'] . ' course at Dazu Training Institute', ENT_QUOTES) . '" width="1200" height="630">';
$months = (int) ($course['duration_months'] ?? 0);
$fee = (float) ($course['course_fee'] ?? 0);

// SEO and sharing: this page's own title, description, keywords, share picture and address.
$page_title = $cpage['meta_title'];
$page_description = $cpage['meta_description'];
$page_tags = array_values(array_filter(array_map('trim', explode(',', (string) $cpage['keywords']))));
$og_image = $cover;
$og_url = $pageUrl;
$GLOBALS['dazu_canonical'] = $pageUrl;
$GLOBALS['dazu_bare'] = true;   // shared like a form: no site menu or footer
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
/* Own styles (the site's built CSS only has the classes other pages use). Simple layout: header band, content left, sticky card right. */
.lp{--g:#048C44;--ink:#0f172a;--mut:#475569;--line:#e2e8f0;color:var(--ink);font-size:16px}
.lp-wrap{max-width:1180px;margin:0 auto;padding:0 16px}
.lp-top{border-bottom:1px solid var(--line);background:#fff;position:sticky;top:0;z-index:30}
.lp-top .lp-wrap{display:flex;align-items:center;justify-content:space-between;height:60px}
.lp-top img{height:36px;width:auto}
.lp-btn{display:inline-block;background:var(--g);color:#fff!important;font-weight:700;font-size:.9rem;padding:.7rem 1.3rem;border-radius:8px;text-decoration:none;text-align:center}.lp-btn:hover{background:#037A3B}
.lp-band{background:#0f172a;color:#fff;padding:40px 0}
.lp-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:32px}.lp-grid>*{min-width:0}.lp{overflow-x:clip}
@media(min-width:1024px){.lp-grid{grid-template-columns:minmax(0,1fr) 400px}.lp-band .lp-main{padding-right:8px}}
.lp-cat{font-size:.8rem;font-weight:700;color:#86efac;text-transform:uppercase;letter-spacing:.08em}
.lp-band h1{color:#fff;font-size:clamp(1.8rem,3.6vw,2.6rem);line-height:1.15;font-weight:800;margin:.5rem 0 .8rem}
.lp-band p{color:#cbd5e1;font-size:1.05rem;line-height:1.6;margin:0 0 1.2rem;max-width:680px}
.lp-facts{display:flex;flex-wrap:wrap;gap:8px 20px;font-size:.9rem;color:#e2e8f0}.lp-facts b{color:#fff}
.lp-media{aspect-ratio:16/9;background:#000;border-radius:10px;overflow:hidden}.lp-media img,.lp-media iframe,.lp-media video{width:100%;height:100%;object-fit:cover;border:0;display:block}
.lp-side{position:relative}
@media(min-width:1024px){.lp-side{margin-top:-310px}.lp-stick{position:sticky;top:76px}}
.lp-card{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 10px 30px rgba(15,23,42,.12);overflow:hidden}
.lp-card .lp-in{padding:16px}
.lp-price{font-size:1.6rem;font-weight:800}
.lp-body{padding:32px 0 56px}
.lp-box{border:1px solid var(--line);border-radius:10px;padding:20px 22px;margin-bottom:28px}
.lp-learn{display:grid;grid-template-columns:1fr;gap:10px 24px;list-style:none;padding:0;margin:0}@media(min-width:640px){.lp-learn{grid-template-columns:1fr 1fr}}
.lp-learn li{display:flex;gap:10px;font-size:.95rem;line-height:1.45;color:var(--mut)}.lp-learn li:before{content:"✓";color:var(--g);font-weight:800}
.lp-learn b{color:var(--ink);display:block}
.lp h2{font-size:1.4rem;font-weight:800;margin:0 0 12px;color:var(--ink)}
.lp-sec{margin-bottom:32px}.lp-sec p,.lp-sec li{color:var(--mut);line-height:1.7}
.lp-sec ul{padding-left:1.2rem;list-style:disc}.lp-sec details{border:1px solid var(--line);border-radius:8px;padding:12px 16px;margin-bottom:8px}.lp-sec summary{font-weight:700;cursor:pointer;color:var(--ink)}
.lp-mods{border:1px solid var(--line);border-radius:10px;overflow:hidden;margin-bottom:32px}
.lp-mods div{display:flex;gap:14px;padding:14px 18px;border-top:1px solid var(--line)}.lp-mods div:first-child{border-top:0}
.lp-mods span{flex:0 0 28px;height:28px;border-radius:50%;background:#ecfdf5;color:var(--g);font-weight:800;font-size:.8rem;display:flex;align-items:center;justify-content:center}
.lp-mods b{display:block}.lp-mods small{color:var(--mut);font-size:.9rem}
.lp-foot{border-top:1px solid var(--line);padding:20px 0;font-size:.85rem;color:var(--mut);text-align:center}
.lp-mob{display:block}@media(min-width:1024px){.lp-mob{display:none}}
.lp-desk{display:none}@media(min-width:1024px){.lp-desk{display:block}}
</style>
<div class="lp">
  <header class="lp-top"><div class="lp-wrap">
    <img src="<?php echo url('assets/images/logo/logo.png'); ?>" alt="Dazu Training Institute">
    <a href="#apply" class="lp-btn">Apply now</a>
  </div></header>

  <section class="lp-band"><div class="lp-wrap lp-grid"><div class="lp-main">
    <div class="lp-cat"><?php echo $e($course['category_name'] ?? '' ?: dazu_course_category($course['abbreviation'] ?? '')); ?></div>
    <h1><?php echo $e($cpage['hero_heading']); ?></h1>
    <?php if ($cpage['hero_text']): ?><p><?php echo $e($cpage['hero_text']); ?></p><?php endif; ?>
    <div class="lp-facts">
      <?php if ($months): ?><span>⏱ <b><?php echo $months; ?> month<?php echo $months > 1 ? 's' : ''; ?></b></span><?php endif; ?>
      <span>🎓 <b>Certificate</b> on completion</span>
      <span>📍 Online or on campus</span>
    </div>
    <div class="lp-mob" style="margin-top:20px"><div class="lp-media"><?php echo $mediaHtml; ?></div></div>
  </div><div class="lp-desk"></div></div></section>

  <div class="lp-body"><div class="lp-wrap lp-grid">
    <main>
      <?php if ($cards): ?>
      <div class="lp-box"><h2>What you'll learn</h2><ul class="lp-learn">
        <?php foreach ($cards as $c): ?><li><span><b><?php echo $e($c['title']); ?></b><?php echo $e($c['text']); ?></span></li><?php endforeach; ?>
      </ul></div>
      <?php endif; ?>
      <?php foreach ($sections as $s): if (preg_match('/^what you.?ll learn|^what you will learn/i', (string) $s['title']) && $cards) continue; ?>
        <section class="lp-sec">
          <?php if ($s['title'] !== ''): ?><h2><?php echo $e($s['title']); ?></h2><?php endif; ?>
          <?php echo cp_body_html((string) $s['body']); ?>
        </section>
      <?php endforeach; ?>
    </main>
    <aside class="lp-side" id="apply"><div class="lp-stick">
      <div class="lp-card">
        <div class="lp-media lp-desk" style="border-radius:0"><?php echo $mediaHtml; ?></div>
        <div class="lp-in">
          <div class="lp-price"><?php echo $fee > 0 ? 'KSh ' . number_format($fee) : 'Free'; ?></div>
          <?php $ikPart = 'top'; include __DIR__ . '/components/public/intake_panel.php'; ?>
          <?php if ($ikShowForm || $reg_success): $pfx = 'lp'; include __DIR__ . '/components/public/dazu_admission_form.php'; endif; ?>
          <?php $ikPart = 'after'; include __DIR__ . '/components/public/intake_panel.php'; ?>
        </div>
      </div>
    </div></aside>
  </div></div>
  <footer class="lp-foot">© <?php echo date('Y'); ?> Dazu Training Institute · Thika and Nairobi, Kenya</footer>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/public/dazu_layout.php';
