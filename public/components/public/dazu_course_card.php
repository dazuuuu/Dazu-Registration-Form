<?php
/** Course card — requires $course array (enriched). */
if (!isset($course) || !function_exists('dazu_course_apply_url')) {
    require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
}
$img = dazu_course_image_url($course);
$ratingVal = (float) ($course['rating'] ?? 4.8);
$rating = number_format($ratingVal, 2);
$reviews = dazu_course_rating_count($course['abbreviation'] ?? '');
$months = (int) ($course['duration_months'] ?? 0);
$fee = number_format((float) ($course['course_fee'] ?? 0));
$title = htmlspecialchars($course['title'] ?? '');
$instructor = htmlspecialchars($course['instructor_name'] ?? 'Dazu Hub');
$applyUrl = dazu_course_apply_url($course);
$detailsUrl = $applyUrl;
$lessons = max(8, $months * 12);
$students = max(3, (int) round($reviews * 0.15));
$fullStars = min(5, max(0, (int) round($ratingVal)));
?>
<article class="dazu-course-card bg-white rounded-xl shadow-[0_2px_16px_rgba(15,23,42,0.08)] overflow-hidden">
  <div class="aspect-[3/2] bg-slate-100 overflow-hidden">
    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo $title; ?>"
         class="w-full h-full object-cover object-center hover:scale-105 transition-transform duration-500"
         referrerpolicy="no-referrer" loading="lazy">
  </div>

  <div class="px-4 py-4">
    <div class="flex items-center justify-between gap-2 mb-3">
      <div class="flex items-center gap-1.5 min-w-0">
        <span class="text-sm font-bold text-slate-800"><?php echo $rating; ?></span>
        <span class="text-xs text-slate-500">(<?php echo $reviews; ?>)</span>
        <div class="flex items-center gap-0.5 text-amber-400 ml-0.5">
          <?php for ($s = 1; $s <= 5; $s++): ?>
            <svg class="w-3.5 h-3.5 <?php echo $s <= $fullStars ? 'fill-current' : 'fill-slate-200'; ?>" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <?php endfor; ?>
        </div>
      </div>
      <div class="flex items-center gap-3 shrink-0 text-[#15A24A]">
        <span class="inline-flex items-center gap-1 text-xs font-bold">
          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
          <?php echo $students; ?>
        </span>
        <span class="inline-flex items-center gap-1 text-xs font-bold">
          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/></svg>
          <?php echo $lessons; ?>
        </span>
      </div>
    </div>

    <h3 class="font-serif text-[17px] font-bold text-slate-900 leading-snug line-clamp-2 mb-1"><?php echo $title; ?></h3>
    <p class="text-sm text-slate-400 mb-4"><?php echo $instructor; ?></p>

    <div class="flex items-center justify-between gap-2">
      <span class="text-lg font-extrabold text-slate-900 whitespace-nowrap">Ksh <?php echo $fee; ?></span>
      <div class="flex items-center gap-2 shrink-0">
        <a href="<?php echo htmlspecialchars($applyUrl); ?>"
           class="inline-flex items-center justify-center px-4 py-2 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-extrabold uppercase tracking-wide rounded-full whitespace-nowrap">
          Apply
        </a>
        <a href="<?php echo htmlspecialchars($detailsUrl); ?>"
           class="inline-flex items-center gap-1 text-xs font-bold text-slate-700 hover:text-[#15A24A] whitespace-nowrap">
          View
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
      </div>
    </div>
  </div>
</article>
