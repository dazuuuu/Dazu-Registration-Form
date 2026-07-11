<?php
/** EDURA-style course card for Popular Courses row — requires $course */
if (!isset($course) || !function_exists('dazu_course_apply_url')) {
    require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
}

$id = (int) ($course['id'] ?? 0);
$img = dazu_course_image_url($course);
$rating = htmlspecialchars($course['rating'] ?? '4.8');
$weeks = max(1, (int) (($course['duration_months'] ?? 1) * 4));
$title = htmlspecialchars($course['title'] ?? '');
$applyUrl = dazu_course_apply_url($course);
$lessons = max(8, count(dazu_course_syllabus($course)));
$students = dazu_course_rating_count($course['abbreviation'] ?? '');
$fee = (float) ($course['course_fee'] ?? 0);
$priceLabel = $fee <= 0 ? 'FREE' : 'KES ' . number_format($fee);
$categoryId = (int) ($course['category_id'] ?? $course['_display_category_id'] ?? 0);
?>
<article class="dazu-popular-course flex-shrink-0 w-[280px] sm:w-[300px] snap-start bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg hover:border-[#15A24A]/40 transition-all cursor-pointer"
         data-category-id="<?php echo $categoryId; ?>"
         onclick="window.location.href='<?php echo htmlspecialchars($applyUrl, ENT_QUOTES); ?>'">
  <div class="relative h-44 bg-slate-100 overflow-hidden">
    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo $title; ?>"
         class="w-full h-full object-cover hover:scale-105 transition-transform duration-500" referrerpolicy="no-referrer">
    <span class="absolute top-3 left-3 inline-flex items-center gap-1 bg-red-500 text-white text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-md shadow">
      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <?php echo str_pad((string) $weeks, 2, '0', STR_PAD_LEFT); ?> Weeks
    </span>
  </div>

  <div class="p-5 space-y-3">
    <div class="flex items-center gap-1 text-amber-400 text-sm">
      <?php for ($i = 0; $i < 5; $i++): ?>
        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
      <?php endfor; ?>
      <span class="text-slate-800 font-extrabold text-xs ml-1"><?php echo $rating; ?></span>
    </div>

    <h3 class="text-sm font-extrabold text-slate-900 leading-snug line-clamp-2 min-h-[2.5rem]"><?php echo $title; ?></h3>

    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] font-bold text-slate-500 uppercase tracking-wide">
      <span class="inline-flex items-center gap-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        <?php echo $lessons; ?> Lessons
      </span>
      <span class="inline-flex items-center gap-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <?php echo number_format($students); ?> Students
      </span>
      <span>Beginner</span>
    </div>

    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
      <div class="flex items-center gap-2 min-w-0">
        <span class="w-8 h-8 rounded-full bg-[#15A24A]/15 text-[#15A24A] flex items-center justify-center text-xs font-extrabold shrink-0">DH</span>
        <span class="text-xs font-bold text-slate-600 truncate">Dazu Hub</span>
      </div>
      <span class="text-sm font-extrabold text-[#15A24A]"><?php echo htmlspecialchars($priceLabel); ?></span>
    </div>
  </div>
</article>
