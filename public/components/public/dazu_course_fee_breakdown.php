<?php
/**
 * Public fee-structure list (online + physical). Requires $course.
 * Optional: $fee_compact (bool) for listing cards.
 */
if (!isset($course) || !empty($course['is_free'])) {
    return;
}
require_once __DIR__ . '/../../../app/v1/helpers/course_fee_structure.php';
$modes = course_fee_structure_all($course);
$hasOnline = course_fee_mode_total($modes['online'] ?? []) > 0;
$hasPhysical = course_fee_mode_total($modes['physical'] ?? []) > 0;
$compact = !empty($fee_compact);
$showDownload = $fee_show_download ?? true;
$courseId = (int) ($course['id'] ?? 0);
$downloadUrl = $courseId > 0 ? course_fee_structure_download_url($courseId, true) : '';
$viewUrl = $courseId > 0 ? course_fee_structure_download_url($courseId, false) : '';
$fallbackTotal = (float) ($course['course_fee'] ?? 0);

$renderList = static function (array $mode, bool $compact, array $course): void {
    $mode = course_fee_mode_decode($mode);
    $semesters = max(1, (int) ($course['semester_count'] ?? 1));
    $per = course_fee_mode_per_semester($course, $mode);
    if ($per > 0) {
        $label = $semesters . ' semester' . ($semesters === 1 ? '' : 's') . ' x Ksh ' . number_format($per);
        if ($compact) {
            echo '<div class="flex items-center justify-between text-[10px] font-semibold text-slate-500">';
            echo '<span class="truncate pr-2">Tuition</span>';
            echo '<span class="text-slate-700 whitespace-nowrap">' . htmlspecialchars($label) . '</span>';
            echo '</div>';
        } else {
            echo '<div class="flex items-center justify-between px-3 py-2 text-xs font-medium text-slate-700 border-b border-slate-100 last:border-b-0">';
            echo '<span>Tuition</span>';
            echo '<span class="font-bold text-slate-900">KES ' . number_format($mode['tuition_total']) . '</span>';
            echo '</div>';
        }
    }
    foreach ($mode['additional'] as $item) {
        if ($compact) {
            echo '<div class="flex items-center justify-between text-[10px] font-semibold text-slate-500">';
            echo '<span class="truncate pr-2">' . htmlspecialchars($item['label']) . '</span>';
            echo '<span class="text-slate-700 whitespace-nowrap">Ksh ' . number_format($item['amount']) . '</span>';
            echo '</div>';
        } else {
            echo '<div class="flex items-center justify-between px-3 py-2 text-xs font-medium text-slate-700 border-b border-slate-100 last:border-b-0">';
            echo '<span>' . htmlspecialchars($item['label']) . '</span>';
            echo '<span class="font-bold text-slate-900">KES ' . number_format($item['amount']) . '</span>';
            echo '</div>';
        }
    }
};
?>
<?php if ($compact): ?>
  <div class="space-y-2">
    <?php if ($hasOnline): ?>
      <div>
        <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Online — Ksh <?php echo number_format(course_fee_mode_total($modes['online'])); ?></span>
        <?php $renderList($modes['online'], true, $course); ?>
      </div>
    <?php endif; ?>
    <?php if ($hasPhysical): ?>
      <div>
        <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Physical — Ksh <?php echo number_format(course_fee_mode_total($modes['physical'])); ?></span>
        <?php $renderList($modes['physical'], true, $course); ?>
      </div>
    <?php endif; ?>
    <?php if (!$hasOnline && !$hasPhysical): ?>
      <div class="flex items-center justify-between text-[10px] font-semibold text-slate-500">
        <span class="truncate pr-2">Course fee</span>
        <span class="text-slate-700 whitespace-nowrap">Ksh <?php echo number_format($fallbackTotal); ?></span>
      </div>
    <?php endif; ?>
    <?php if ($showDownload): ?>
    <div class="grid grid-cols-1 gap-2">
      <?php if ($downloadUrl !== ''): ?>
        <a href="<?php echo htmlspecialchars($downloadUrl); ?>" onclick="event.stopPropagation();"
           class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-green-200 bg-green-50 text-[10px] font-extrabold uppercase tracking-wider text-[#15A24A] hover:bg-green-100">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Download fee structure
        </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div>
    <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-2">Fee structure</span>
    <?php if ($hasOnline): ?>
      <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 mb-1">Online classes</p>
      <div class="border border-slate-200 rounded-xl overflow-hidden mb-3">
        <?php $renderList($modes['online'], false, $course); ?>
      </div>
    <?php endif; ?>
    <?php if ($hasPhysical): ?>
      <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 mb-1">Physical classes</p>
      <div class="border border-slate-200 rounded-xl overflow-hidden">
        <?php $renderList($modes['physical'], false, $course); ?>
      </div>
    <?php endif; ?>
    <?php if (!$hasOnline && !$hasPhysical): ?>
      <div class="border border-slate-200 rounded-xl overflow-hidden mb-3">
        <div class="flex items-center justify-between px-3 py-2 text-xs font-medium text-slate-700">
          <span>Course fee</span>
          <span class="font-bold text-slate-900">KES <?php echo number_format($fallbackTotal); ?></span>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($showDownload): ?>
    <div class="mt-2 flex flex-wrap gap-3">
      <?php if ($downloadUrl !== ''): ?>
        <a href="<?php echo htmlspecialchars($downloadUrl); ?>"
           class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border-2 border-[#15A24A] bg-white text-[10px] font-extrabold uppercase tracking-wider text-[#15A24A] hover:bg-green-50">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Download fee structure (PDF)
        </a>
      <?php endif; ?>
      <?php if ($viewUrl !== ''): ?>
        <a href="<?php echo htmlspecialchars($viewUrl); ?>"
           class="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-500 hover:text-slate-800 hover:underline">View</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
