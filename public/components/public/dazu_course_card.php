<?php
/** Course card — ported from Dazu-frontend Home.tsx */
if (!isset($course) || !function_exists('dazu_course_apply_url')) {
    require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
}
$img = dazu_course_image_url($course);
$ratingVal = (float) ($course['rating'] ?? 4.8);
$reviews = dazu_course_rating_count($course['abbreviation'] ?? '');
$months = (int) ($course['duration_months'] ?? 0);
$weeks = (int) ($course['weeks_of_study'] ?? 0);
$hours = (int) ($course['hours_of_study'] ?? 0);
$schedule = trim((string) ($course['study_schedule'] ?? ''));
$isFree = !empty($course['is_free']);
$fee = number_format((float) ($course['course_fee'] ?? 0));
$title = htmlspecialchars($course['title'] ?? '');
$overview = htmlspecialchars($course['overview'] ?? $course['description'] ?? '');
$category = htmlspecialchars($course['category_name'] ?? dazu_course_category($course['abbreviation'] ?? ''));
$applyUrl = dazu_course_apply_url($course);
$duration = $months > 0 ? $months . ' Month' . ($months === 1 ? '' : 's') : 'Flexible';
$abbr = strtoupper(trim($course['abbreviation'] ?? ''));
$cardId = (int) ($course['id'] ?? 0);
?>
<article class="dazu-course-card group relative bg-white border border-slate-200 rounded-2xl overflow-hidden flex flex-col justify-between h-full cursor-pointer hover:border-[#15A24A] transition-all duration-300 select-none shadow-sm"
         onclick="window.location.href='<?php echo htmlspecialchars($applyUrl); ?>'">
  <div class="relative h-44 w-full overflow-hidden bg-slate-100">
    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo $title; ?>"
         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
         referrerpolicy="no-referrer" loading="lazy">
    <span class="absolute top-3 left-3 text-[10px] font-bold text-white bg-[#15A24A] px-2.5 py-1 rounded-full uppercase tracking-wider shadow-sm"><?php echo $category; ?></span>
    <?php if ($isFree): ?>
      <span class="absolute top-3 right-3 text-[10px] font-bold text-white bg-rose-500 px-2.5 py-1 rounded-full uppercase tracking-wider shadow-sm">Free</span>
    <?php endif; ?>
    <div class="absolute bottom-3 right-3 p-2 bg-white/90 backdrop-blur-sm text-[#15A24A] border border-green-100 rounded-lg shadow-sm">
      <?php echo dazu_course_icon_svg($abbr); ?>
    </div>
  </div>

  <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
    <div class="space-y-2">
      <div class="flex items-center gap-1.5 text-xs">
        <div class="flex items-center text-amber-500">
          <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        </div>
        <span class="font-bold text-slate-800"><?php echo number_format($ratingVal, 1); ?></span>
        <span class="text-slate-400 font-semibold">(<?php echo $reviews; ?>)</span>
        <span class="text-slate-300">•</span>
        <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]"><?php echo htmlspecialchars($duration); ?></span>
      </div>
      <h3 class="font-extrabold text-slate-900 text-base tracking-tight group-hover:text-[#15A24A] transition-colors duration-150 line-clamp-1"><?php echo $title; ?></h3>
      <p class="text-slate-500 text-xs font-semibold leading-relaxed line-clamp-2"><?php echo $overview; ?></p>
      <?php if ($weeks > 0 || $hours > 0 || $schedule !== ''): ?>
        <div class="flex items-center gap-2 flex-wrap">
          <?php if ($weeks > 0): ?>
            <span class="text-[10px] font-bold text-slate-500 bg-slate-50 border border-slate-200 rounded-full px-2 py-0.5">
              <?php echo $weeks; ?> Week<?php echo $weeks === 1 ? '' : 's'; ?>
            </span>
          <?php endif; ?>
          <?php if ($hours > 0): ?>
            <span class="text-[10px] font-bold text-slate-500 bg-slate-50 border border-slate-200 rounded-full px-2 py-0.5">
              <?php echo $hours; ?> Hr<?php echo $hours === 1 ? '' : 's'; ?>
            </span>
          <?php endif; ?>
          <?php if ($schedule !== ''): ?>
            <span class="text-[10px] font-bold text-slate-500 bg-slate-50 border border-slate-200 rounded-full px-2 py-0.5">
              <?php echo htmlspecialchars($schedule); ?>
            </span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
      <div>
        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tuition Fee</span>
        <span class="text-[#15A24A] font-extrabold text-sm sm:text-base"><?php echo $isFree ? 'FREE' : 'Ksh ' . $fee; ?></span>
      </div>
      <a href="<?php echo htmlspecialchars($applyUrl); ?>"
         onclick="event.stopPropagation();"
         class="px-4 py-2 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-bold rounded-lg transition-colors shadow-sm flex items-center gap-1"
         id="course-apply-btn-<?php echo $cardId; ?>">
        Apply Now
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
      </a>
    </div>
  </div>
</article>
