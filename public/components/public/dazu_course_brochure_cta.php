<?php
/** Download the brochure uploaded for this course. Requires $course. */
if (!isset($course) || !is_array($course)) {
    return;
}
require_once __DIR__ . '/../../../app/v1/helpers/course_fee_structure.php';
$brochureUrl = course_brochure_url($course);
if (!$brochureUrl) {
    return;
}
$compact = !empty($brochure_compact);
?>
<?php if ($compact): ?>
  <a href="<?php echo htmlspecialchars($brochureUrl); ?>" onclick="event.stopPropagation();"
     class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-[#15A24A] hover:underline">
    Download brochure
  </a>
<?php else: ?>
  <a href="<?php echo htmlspecialchars($brochureUrl); ?>"
     class="mt-4 flex items-center justify-center gap-2 w-full px-4 py-3 bg-white border-2 border-[#15A24A] text-[#15A24A] hover:bg-green-50 text-xs font-extrabold uppercase tracking-wider rounded-xl">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
    Download course brochure
  </a>
<?php endif; ?>
