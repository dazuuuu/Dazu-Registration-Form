<?php
/** Course card — requires $course array (enriched). */
if (!isset($course) || !function_exists('dazu_course_apply_url')) {
    require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
}
$img = dazu_course_image_url($course);
$rating = htmlspecialchars($course['rating'] ?? '4.8');
$reviews = dazu_course_rating_count($course['abbreviation'] ?? '');
$months = (int) ($course['duration_months'] ?? 0);
$fee = number_format((float) ($course['course_fee'] ?? 0));
$title = htmlspecialchars($course['title'] ?? '');
$overview = htmlspecialchars($course['overview'] ?? $course['description'] ?? '');
$applyUrl = dazu_course_apply_url($course);
$detailsUrl = $applyUrl;
$weeks = max(1, (int) ($months * 4));
?>
<article class="dazu-course-card group bg-white border border-slate-200 overflow-hidden flex flex-col h-full hover:border-[#15A24A] transition-all duration-300 shadow-sm hover:shadow-md">
  <div class="relative w-full h-[176px] bg-slate-100 overflow-hidden shrink-0">
    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo $title; ?>"
         class="block w-full h-[176px] object-cover object-center group-hover:scale-105 transition-transform duration-500"
         referrerpolicy="no-referrer" loading="lazy">
    <span class="absolute top-3 left-3 inline-flex items-center gap-1 bg-red-500 text-white text-[10px] font-extrabold uppercase tracking-wider px-2 py-1 leading-none rounded-none">
      <?php echo str_pad((string) $weeks, 2, '0', STR_PAD_LEFT); ?> Weeks
    </span>
  </div>

  <div class="p-5 flex-1 flex flex-col min-h-0">
    <div class="space-y-2.5 flex-1">
      <div class="flex items-center gap-1.5 text-xs flex-wrap">
        <div class="flex items-center text-amber-500">
          <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        </div>
        <span class="font-extrabold text-slate-800"><?php echo $rating; ?></span>
        <span class="text-slate-400 font-semibold">(<?php echo $reviews; ?> reviews)</span>
        <span class="text-slate-300">•</span>
        <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]"><?php echo $months; ?> months</span>
      </div>
      <h3 class="text-base font-extrabold text-slate-900 group-hover:text-[#15A24A] transition-colors uppercase tracking-tight leading-snug line-clamp-2 min-h-[2.5rem]"><?php echo $title; ?></h3>
      <p class="text-slate-500 text-xs font-semibold leading-relaxed line-clamp-2"><?php echo $overview; ?></p>
    </div>

    <div class="mt-4 pt-4 border-t border-slate-100 flex flex-row flex-nowrap items-center justify-between gap-2">
      <div class="min-w-0">
        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-none">Tuition Fee</span>
        <span class="text-[#15A24A] font-extrabold text-base leading-tight whitespace-nowrap">KES <?php echo $fee; ?></span>
      </div>
      <div class="flex flex-row flex-nowrap items-center gap-2 shrink-0">
        <a href="<?php echo htmlspecialchars($applyUrl); ?>"
           class="inline-flex items-center justify-center px-3.5 py-2.5 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-bold rounded-none whitespace-nowrap">Apply</a>
        <a href="<?php echo htmlspecialchars($detailsUrl); ?>"
           class="inline-flex items-center justify-center px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-none border border-slate-200 whitespace-nowrap">View Details</a>
      </div>
    </div>
  </div>
</article>
