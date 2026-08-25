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
     class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 bg-white text-[10px] font-extrabold uppercase tracking-wider text-slate-600 hover:border-[#15A24A] hover:text-[#15A24A]">
    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M6 4h8l4 4v12H6z"/></svg>
    Download brochure
  </a>
<?php else: ?>
  <a href="<?php echo htmlspecialchars($brochureUrl); ?>"
     class="mt-4 flex items-center justify-center gap-2 w-full px-4 py-3 bg-white border-2 border-[#15A24A] text-[#15A24A] hover:bg-green-50 text-xs font-extrabold uppercase tracking-wider rounded-xl">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
    Download course brochure
  </a>
<?php endif; ?>
