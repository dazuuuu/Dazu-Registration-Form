<?php
/** Course card — ported from Dazu-frontend Home.tsx */
if (!isset($course) || !function_exists('dazu_course_apply_url')) {
    require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
}
$img = dazu_course_image_url($course);
$ratingVal = (float) ($course['rating'] ?? 4.8);
$reviews = dazu_course_rating_count($course['abbreviation'] ?? '');
$isFree = !empty($course['is_free']);
$enrollWindow = $isFree ? dazu_course_enrollment_window($course) : ['label' => null];
$title = htmlspecialchars($course['title'] ?? '');
$overview = htmlspecialchars($course['overview'] ?? $course['description'] ?? '');
$applyUrl = dazu_course_apply_url($course);
$cardId = (int) ($course['id'] ?? 0);
?>
<article class="dazu-course-card group relative bg-white border border-slate-200 rounded-2xl overflow-hidden flex flex-col h-full min-h-[360px] sm:min-h-[420px] cursor-pointer hover:border-[#15A24A] transition-all duration-300 select-none shadow-sm"
         onclick="window.location.href='<?php echo htmlspecialchars($applyUrl); ?>'">
  <div class="relative h-32 sm:h-44 w-full overflow-hidden bg-slate-100">
    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo $title; ?>"
         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
         referrerpolicy="no-referrer" loading="lazy">
    <?php if ($isFree): ?>
      <span class="absolute top-3 right-3 text-[10px] font-bold text-white bg-rose-500 px-2.5 py-1 rounded-full uppercase tracking-wider shadow-sm">Free</span>
    <?php endif; ?>
    <?php
    // Enrolment badge: only on a course whose enrolment is open. Every other course shows no badge at all.
    $enBadge = null;
    if (!$isFree && $cardId) {
        try {
            require_once __DIR__ . '/../../../app/v1/models/Connection.php';
            require_once __DIR__ . '/../../../app/v1/services/EnrolmentService.php';
            $enBadge = en_badge(\Models\Connection::getInstance()->getConnection(), $cardId);
        } catch (Throwable $enErr) {
            $enBadge = null;
        }
    }
    if ($enBadge): ?>
      <span class="absolute top-3 left-3 max-w-[85%] text-[10px] font-bold px-2.5 py-1 rounded-full tracking-wide shadow-sm leading-tight" style="background:#44A024;color:#fff"><?php echo htmlspecialchars($enBadge); ?></span>
    <?php endif; ?>
  </div>

  <div class="p-3 sm:p-5 flex-1 flex flex-col">
    <div class="space-y-2">
      <div class="flex items-center gap-1.5 text-xs">
        <div class="flex items-center text-amber-500">
          <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        </div>
        <span class="font-bold text-slate-800"><?php echo number_format($ratingVal, 1); ?></span>
        <span class="text-slate-400 font-semibold">(<?php echo $reviews; ?>)</span>
      </div>
      <h3 class="font-extrabold text-slate-900 text-sm sm:text-base tracking-tight group-hover:text-[#15A24A] transition-colors duration-150 line-clamp-2 min-h-[2.5rem]"><?php echo $title; ?></h3>
      <p class="text-slate-500 text-xs font-semibold leading-relaxed line-clamp-2 sm:line-clamp-3 min-h-[3.5rem] sm:min-h-[4.5rem]"><?php echo $overview; ?></p>
      <?php if (!empty($enrollWindow['label'])): ?>
        <div class="flex items-center gap-1.5 text-[10px] font-bold text-rose-600 bg-rose-50 border border-rose-100 rounded-full px-2.5 py-1 w-fit">
          <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          <?php echo htmlspecialchars($enrollWindow['label']); ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="mt-auto pt-3 sm:pt-4 border-t border-slate-100 grid grid-cols-2 gap-1.5 sm:gap-2">
      <a href="<?php echo htmlspecialchars($applyUrl); ?>"
         onclick="event.stopPropagation();"
         class="px-2 sm:px-3 py-2 sm:py-2.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-[9px] sm:text-[11px] font-extrabold rounded-lg transition-colors flex items-center justify-center gap-1.5 uppercase tracking-wide sm:tracking-wider">
        View Course
      </a>
      <a href="<?php echo htmlspecialchars($applyUrl); ?>"
         onclick="event.stopPropagation();"
         class="px-2 sm:px-3 py-2 sm:py-2.5 bg-[#15A24A] hover:bg-[#128a3e] text-white text-[9px] sm:text-[11px] font-extrabold rounded-lg transition-colors shadow-sm flex items-center justify-center gap-1.5 uppercase tracking-wide sm:tracking-wider"
         id="course-apply-btn-<?php echo $cardId; ?>">
        Apply
        <svg class="hidden sm:block h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
      </a>
    </div>
  </div>
</article>
