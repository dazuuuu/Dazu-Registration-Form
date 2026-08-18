<?php
/**
 * Public fee-structure list. Requires $course.
 * Optional: $fee_compact (bool) for course listing cards.
 */
if (!isset($course) || !empty($course['is_free'])) {
    return;
}
require_once __DIR__ . '/../../../app/v1/helpers/course_fee_structure.php';
$feeItems = course_fee_structure_items($course);
if ($feeItems === []) {
    return;
}
$compact = !empty($fee_compact);
$courseId = (int) ($course['id'] ?? 0);
$downloadUrl = $courseId > 0 ? course_fee_structure_download_url($courseId, true) : '';
$viewUrl = $courseId > 0 ? course_fee_structure_download_url($courseId, false) : '';
?>
<?php if ($compact): ?>
  <div class="space-y-1">
    <?php foreach ($feeItems as $item): ?>
      <div class="flex items-center justify-between text-[10px] font-semibold text-slate-500">
        <span class="truncate pr-2"><?php echo htmlspecialchars($item['label']); ?></span>
        <span class="text-slate-700 whitespace-nowrap">Ksh <?php echo number_format($item['amount']); ?></span>
      </div>
    <?php endforeach; ?>
    <?php if ($downloadUrl !== ''): ?>
      <a href="<?php echo htmlspecialchars($downloadUrl); ?>"
         onclick="event.stopPropagation();"
         class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-[#15A24A] hover:underline pt-1">
        Download fee structure
      </a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div>
    <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-2">Fee structure</span>
    <div class="border border-slate-200 rounded-xl overflow-hidden">
      <?php foreach ($feeItems as $item): ?>
        <div class="flex items-center justify-between px-3 py-2 text-xs font-medium text-slate-700 border-b border-slate-100 last:border-b-0">
          <span><?php echo htmlspecialchars($item['label']); ?></span>
          <span class="font-bold text-slate-900">KES <?php echo number_format($item['amount']); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($downloadUrl !== ''): ?>
      <div class="mt-2 flex flex-wrap gap-3">
        <a href="<?php echo htmlspecialchars($downloadUrl); ?>"
           class="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-wider text-[#15A24A] hover:underline">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"/></svg>
          Download fee structure
        </a>
        <?php if ($viewUrl !== ''): ?>
          <a href="<?php echo htmlspecialchars($viewUrl); ?>"
             class="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-500 hover:text-slate-800 hover:underline">
            View
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
