<?php
/**
 * Enrolment banner on the course page (simple enrolment, migration 086).
 *
 * Included three times by courses/view.php and courses/share.php: $ikPart = 'prepare' (before output), 'top' (above the
 * Apply form) and 'after' (below it). The Apply form always shows as normal ($ikShowForm stays true).
 * When this course's enrolment is open, 'top' shows "Enrolment open" with a "Book a slot" button. Otherwise nothing.
 * Requires: $pdo, $course.
 */
require_once __DIR__ . '/../../../app/v1/services/EnrolmentService.php';
$ikPart = $ikPart ?? 'prepare';

if ($ikPart === 'prepare') {
    $ikShowForm = true;
    $enState = !empty($course['id']) ? en_state($pdo, (int) $course['id']) : ['open' => false];
    return;
}
if ($ikPart !== 'top' || empty($enState['open'])) return;
$o = $enState['opening'];
$full = $enState['left'] !== null && $enState['left'] <= 0;
?>
<div class="bg-white border-2 border-[#44A024] rounded-2xl p-5 md:p-6 mb-6">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div class="space-y-1">
      <span class="inline-block text-[10px] font-extrabold uppercase tracking-widest text-white bg-[#44A024] px-2.5 py-1 rounded-full">Enrolment open</span>
      <h2 class="text-lg font-extrabold text-slate-900"><?php echo en_e($o['title']); ?></h2>
      <p class="text-xs font-semibold text-slate-500">
        <?php if ($o['classes_start']): ?>Classes start <?php echo en_e(en_date($o['classes_start'])); ?>. <?php endif; ?>
        <?php if ($o['closes_at']): ?>Book by <?php echo en_e(en_date($o['closes_at'])); ?>. <?php endif; ?>
        <?php if ($enState['left'] !== null): ?><?php echo $full ? 'All slots are taken, but you can still book: we will allocate you a slot.' : $enState['left'] . ' slot' . ($enState['left'] === 1 ? '' : 's') . ' left.'; ?><?php endif; ?>
      </p>
    </div>
    <a href="<?php echo en_e(url('intake/book.php?course=' . (int) $course['id'])); ?>" class="px-6 py-3 text-xs font-extrabold uppercase tracking-widest text-white bg-[#44A024] hover:bg-[#36851b] rounded-xl">Book a slot</a>
  </div>
</div>
