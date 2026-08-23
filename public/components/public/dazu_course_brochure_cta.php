<?php
/** Download the brochure uploaded for this course. Requires $course. Course details only. */
if (!isset($course) || !is_array($course)) {
    return;
}
require_once __DIR__ . '/../../../app/v1/helpers/course_fee_structure.php';
$brochureUrl = course_brochure_url($course);
if (!$brochureUrl) {
    return;
}
?>
<a href="<?php echo htmlspecialchars($brochureUrl); ?>" target="_blank" rel="noopener"
   class="mt-4 flex items-center justify-center gap-2 w-full px-4 py-3 bg-white border-2 border-[#171717] text-[#171717] hover:bg-slate-50 text-xs font-extrabold uppercase tracking-wider rounded-xl">
  <svg class="h-4 w-4 text-[#15A24A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
  Download course brochure
</a>
