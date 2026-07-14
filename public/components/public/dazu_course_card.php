<?php
/**
 * Course card — exact port from dazu-updated-ui (Home.tsx / Courses.tsx).
 * Set $courseCardVariant = 'listing' on courses page for Apply + Syllabus buttons.
 */
if (!isset($course) || !function_exists('dazu_course_apply_url')) {
    require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
}

$courseCardVariant = $courseCardVariant ?? 'home';
$img = dazu_course_image_url($course);
$ratingVal = (float) ($course['rating'] ?? 4.8);
$reviews = dazu_course_rating_count($course['abbreviation'] ?? '');
$specs = dazu_course_card_specs($course);
$durationRibbon = dazu_course_duration_ribbon($course);
$categoryShort = dazu_course_category_short($course);
$priceLabel = dazu_course_price_label($course);
$title = htmlspecialchars($course['title'] ?? '');
$overview = htmlspecialchars($course['overview'] ?? $course['description'] ?? '');
$applyUrl = dazu_course_apply_url($course);
$viewUrl = dazu_course_apply_url($course);
if ((int) ($course['id'] ?? 0) > 0) {
    $viewUrl = url('courses/view.php?id=' . (int) $course['id']);
}
$lecturesShort = preg_replace('/\s+Lectures$/', ' Lec', $specs['lectures']);
$instructorFirst = explode(' ', $specs['instructor'])[0] ?? $specs['instructor'];
?>
<article class="dazu-course-card group relative bg-white border border-slate-200/90 rounded-2xl overflow-hidden flex flex-col justify-between h-full cursor-pointer hover:border-[#15A24A] transition-all duration-300 select-none">
  <a href="<?= htmlspecialchars($viewUrl) ?>" class="flex flex-col flex-1 min-h-0 no-underline text-inherit">
    <div class="relative h-28 sm:h-48 w-full overflow-hidden bg-slate-100 shrink-0">
      <img src="<?= htmlspecialchars($img) ?>" alt="<?= $title ?>"
           class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
           referrerpolicy="no-referrer" loading="lazy">
      <div class="absolute top-2 left-2 sm:top-4 sm:left-4 bg-red-600 text-white font-black text-[7px] sm:text-[9px] tracking-widest px-2 py-1 sm:px-3 sm:py-1.5 rounded-md shadow-md uppercase select-none z-10">
        <?= htmlspecialchars($durationRibbon) ?>
      </div>
      <span class="absolute top-2 right-2 sm:top-4 sm:right-4 text-[7px] sm:text-[9px] font-extrabold text-white bg-[#15A24A] px-1.5 py-1 sm:px-2.5 sm:py-1.5 rounded-md uppercase tracking-widest z-10">
        <?= htmlspecialchars($categoryShort) ?>
      </span>
    </div>

    <div class="p-3 sm:p-5 flex-1 flex flex-col justify-between space-y-3 sm:space-y-4">
      <div class="space-y-2 sm:space-y-3">
        <div class="flex flex-wrap items-center gap-1 text-xs">
          <div class="flex items-center text-amber-500 gap-0.5">
            <?php for ($s = 0; $s < 5; $s++): ?>
              <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5 fill-current<?= $s > 0 ? ' hidden sm:inline' : '' ?>" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
            <?php endfor; ?>
          </div>
          <span class="font-extrabold text-slate-800 text-[10px] sm:text-[11px] ml-0.5"><?= number_format($ratingVal, 1) ?></span>
          <span class="text-slate-400 font-bold text-[10px] sm:text-[11px]">(<?= $reviews ?>)</span>
        </div>

        <h3 class="font-black text-slate-900 text-sm sm:text-base tracking-tight leading-tight group-hover:text-[#15A24A] transition-colors duration-200 line-clamp-1">
          <?= $title ?>
        </h3>

        <?php if ($courseCardVariant === 'listing' && $overview !== ''): ?>
          <p class="text-slate-500 text-[10px] sm:text-xs font-semibold leading-relaxed line-clamp-2"><?= $overview ?></p>
        <?php endif; ?>

        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[9px] sm:text-[11px] font-extrabold text-slate-500 pt-0.5 pb-0.5<?= $courseCardVariant === 'listing' ? ' border-t border-slate-50 pt-1 sm:pt-2 pb-1' : '' ?>">
          <span class="flex items-center gap-1">
            <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5 text-[#15A24A]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            <?= htmlspecialchars($lecturesShort) ?>
          </span>
          <span class="flex items-center gap-1">
            <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5 text-[#15A24A]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <?= htmlspecialchars($specs['students']) ?>
          </span>
        </div>
      </div>

      <div class="pt-2 sm:pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 shrink-0">
        <div class="flex items-center gap-1.5">
          <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-black text-[#15A24A] text-[9px] sm:text-xs uppercase">
            <?= htmlspecialchars($specs['initials']) ?>
          </div>
          <span class="text-[10px] sm:text-[11px] font-black text-slate-700 tracking-tight leading-none truncate max-w-[80px]">
            <?= htmlspecialchars($instructorFirst) ?>
          </span>
        </div>
        <div class="text-left sm:text-right">
          <span class="block text-[7px] sm:text-[8px] font-extrabold text-slate-400 uppercase tracking-widest leading-none">TUITION</span>
          <span class="text-[#15A24A] font-extrabold text-[10px] sm:text-sm tracking-tight"><?= htmlspecialchars($priceLabel) ?></span>
        </div>
      </div>
    </div>
  </a>

  <?php if ($courseCardVariant === 'listing'): ?>
    <div class="px-3 sm:px-5 pb-3 sm:pb-5 pt-0 flex items-center gap-1.5 w-full">
      <a href="<?= htmlspecialchars($applyUrl) ?>"
         class="flex-1 px-2.5 py-1.5 bg-[#15A24A] hover:bg-[#128a3e] text-white text-[9px] sm:text-xs font-black tracking-wider uppercase rounded-lg transition-colors text-center no-underline">
        Apply
      </a>
      <a href="<?= htmlspecialchars($viewUrl) ?>"
         class="flex-1 px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[9px] sm:text-xs font-black tracking-wider uppercase rounded-lg transition-colors text-center no-underline border border-slate-200">
        Syllabus
      </a>
    </div>
  <?php endif; ?>
</article>
