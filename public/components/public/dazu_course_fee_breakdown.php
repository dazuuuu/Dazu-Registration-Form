<?php
/**
 * Public school fees + fee-structure download. Requires $course.
 * Only use on course detail pages — not course cards.
 */
if (!isset($course) || !empty($course['is_free'])) {
    return;
}
require_once __DIR__ . '/../../../app/v1/helpers/course_fee_structure.php';
if (!course_has_school_fees($course)) {
    return;
}
$fees = course_school_fees($course);
$courseId = (int) ($course['id'] ?? 0);
$downloadUrl = $courseId > 0 ? course_fee_structure_download_url($courseId, true) : '';
$viewUrl = $courseId > 0 ? course_fee_structure_download_url($courseId, false) : '';
?>
<div>
  <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-2">School fees</span>
  <div class="border border-slate-200 rounded-xl overflow-hidden">
    <?php if ($fees['online'] > 0): ?>
      <div class="flex items-center justify-between px-3 py-2.5 text-xs font-medium text-slate-700 border-b border-slate-100">
        <span>Online classes</span>
        <span class="font-bold text-slate-900">KES <?php echo number_format($fees['online']); ?></span>
      </div>
    <?php endif; ?>
    <?php if ($fees['physical'] > 0): ?>
      <div class="flex items-center justify-between px-3 py-2.5 text-xs font-medium text-slate-700">
        <span>Physical classes</span>
        <span class="font-bold text-slate-900">KES <?php echo number_format($fees['physical']); ?></span>
      </div>
    <?php endif; ?>
  </div>
  <?php if ($downloadUrl !== ''): ?>
    <div class="mt-3 flex flex-wrap gap-3">
      <a href="<?php echo htmlspecialchars($downloadUrl); ?>"
         class="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-wider text-[#15A24A] hover:underline">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"/></svg>
        Download fee structure
      </a>
      <?php if ($viewUrl !== ''): ?>
        <a href="<?php echo htmlspecialchars($viewUrl); ?>" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-500 hover:text-slate-800 hover:underline">View</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
