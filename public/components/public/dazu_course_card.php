<?php
/** Course card — requires $course array (enriched). */
if (!isset($course) || !function_exists('dazu_course_apply_url')) {
    require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
}
$id = (int) ($course['id'] ?? 0);
$img = dazu_course_image_url($course);
$category = dazu_course_category($course['abbreviation'] ?? '');
$rating = htmlspecialchars($course['rating'] ?? '4.8');
$reviews = dazu_course_rating_count($course['abbreviation'] ?? '');
$months = (int) ($course['duration_months'] ?? 0);
$fee = number_format((float) ($course['course_fee'] ?? 0));
$title = htmlspecialchars($course['title'] ?? '');
$overview = htmlspecialchars($course['overview'] ?? $course['description'] ?? '');
$applyUrl = dazu_course_apply_url($course);
$syllabusUrl = $applyUrl . '#learn';
?>
<article class="group bg-white border border-slate-200 rounded-2xl overflow-hidden flex flex-col h-full cursor-pointer hover:border-green-600 hover:-translate-y-1.5 transition-all duration-300 shadow-sm hover:shadow-md"
         onclick="window.location.href='<?php echo htmlspecialchars($applyUrl, ENT_QUOTES); ?>'">
  <div class="relative h-48 w-full bg-slate-100 overflow-hidden">
    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo $title; ?>"
         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" referrerpolicy="no-referrer">
  </div>
  <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
    <div class="space-y-3">
      <div class="flex items-center gap-1.5 text-xs flex-wrap">
        <div class="flex items-center text-amber-500"><svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></div>
        <span class="font-extrabold text-slate-800"><?php echo $rating; ?></span>
        <span class="text-slate-400 font-semibold">(<?php echo $reviews; ?> reviews)</span>
        <span class="text-slate-300">•</span>
        <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px] flex items-center gap-1">
          <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <?php echo $months; ?> months
        </span>
      </div>
      <h3 class="text-xl font-extrabold text-slate-900 group-hover:text-green-600 transition-colors uppercase tracking-tight"><?php echo $title; ?></h3>
      <p class="text-slate-500 text-xs font-semibold leading-relaxed line-clamp-2"><?php echo $overview; ?></p>
    </div>
    <div class="pt-4 border-t border-slate-100 flex items-center justify-between" onclick="event.stopPropagation()">
      <div>
        <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider">Tuition Fee</span>
        <span class="text-green-600 font-extrabold text-base">KES <?php echo $fee; ?></span>
      </div>
      <div class="flex items-center gap-2">
        <a href="<?php echo htmlspecialchars($applyUrl); ?>"
           class="px-3.5 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-lg transition-colors shadow-sm">Apply</a>
        <a href="<?php echo htmlspecialchars($syllabusUrl); ?>"
           class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg border border-slate-200">Syllabus</a>
      </div>
    </div>
  </div>
</article>
