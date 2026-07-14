<?php
/** Course card — DAZU reference design */
if (!isset($course) || !function_exists('dazu_course_apply_url')) {
    require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
}

$img = dazu_course_image_url($course);
$ratingVal = (float) ($course['rating'] ?? 4.8);
$reviews = dazu_course_rating_count($course['abbreviation'] ?? '');
$months = (int) ($course['duration_months'] ?? 0);
$fee = number_format((float) ($course['course_fee'] ?? 0));
$title = htmlspecialchars($course['title'] ?? '');
$overview = htmlspecialchars($course['overview'] ?? $course['description'] ?? '');
$rawCategory = $course['category_name'] ?? dazu_course_category($course['abbreviation'] ?? '');
$categoryLabel = strtoupper(preg_replace('/\s+/', ' ', trim($rawCategory)));
$applyUrl = dazu_course_apply_url($course);
$durationLabel = $months > 0 ? strtoupper($months . ' MONTH' . ($months === 1 ? '' : 'S')) : 'FLEXIBLE';
$viewUrl = dazu_course_apply_url($course);
if ((int) ($course['id'] ?? 0) > 0) {
    $viewUrl = url('courses/view.php?id=' . (int) $course['id']);
}
?>
<article class="dazu-course-card group bg-white overflow-hidden flex flex-col h-full border border-slate-200/80">
  <div class="relative h-[180px] sm:h-[190px] w-full overflow-hidden bg-slate-100">
    <img src="<?= htmlspecialchars($img) ?>" alt="<?= $title ?>"
         class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500"
         referrerpolicy="no-referrer" loading="lazy">
    <span class="absolute top-3 left-3 z-10 text-[10px] font-extrabold text-white bg-[#15A24A] px-3 py-1.5 rounded-full uppercase tracking-wider leading-none">
      <?= htmlspecialchars($categoryLabel) ?>
    </span>
    <a href="<?= htmlspecialchars($viewUrl) ?>"
       onclick="event.stopPropagation()"
       class="absolute bottom-3 right-3 z-10 w-9 h-9 bg-white border border-green-200 rounded-[8px] flex items-center justify-center text-[#15A24A] shadow-sm hover:bg-green-50 transition-colors"
       aria-label="View course details">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
      </svg>
    </a>
  </div>

  <div class="p-5 flex-1 flex flex-col">
    <div class="flex flex-wrap items-center gap-1.5 text-xs mb-3">
      <div class="flex items-center text-amber-500">
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
      </div>
      <span class="font-bold text-slate-800"><?= number_format($ratingVal, 1) ?></span>
      <span class="text-slate-500 font-semibold">(<?= $reviews ?>)</span>
      <span class="text-slate-300 font-bold">•</span>
      <span class="text-slate-700 font-bold uppercase tracking-wide text-[11px]"><?= htmlspecialchars($durationLabel) ?></span>
    </div>

    <h3 class="font-extrabold text-slate-950 text-base sm:text-[17px] uppercase tracking-tight leading-snug mb-2 line-clamp-2 group-hover:text-[#15A24A] transition-colors">
      <?= $title ?>
    </h3>
    <p class="text-slate-500 text-sm leading-relaxed line-clamp-2 mb-5 flex-1">
      <?= $overview ?>
    </p>

    <div class="pt-4 border-t border-slate-100 flex items-end justify-between gap-3 mt-auto">
      <div class="min-w-0">
        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-[0.14em] mb-0.5">Tuition Fee</span>
        <span class="text-[#15A24A] font-extrabold text-base sm:text-lg leading-none">Ksh <?= $fee ?></span>
      </div>
      <a href="<?= htmlspecialchars($applyUrl) ?>"
         class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-[#15A24A] hover:bg-[#128a3e] text-white text-[11px] font-extrabold uppercase tracking-wide rounded-[8px] transition-colors shadow-sm whitespace-nowrap shrink-0">
        Apply Now
        <span aria-hidden="true">→</span>
      </a>
    </div>
  </div>
</article>
