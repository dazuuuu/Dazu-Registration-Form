<?php
// public/components/timetable/view.php — one timetable card: title, PDF link/preview and the weekly table. Needs $t and $h.
$ttByDay = [];
foreach ($t['slots'] as $s) $ttByDay[(int) $s['day_of_week']][] = $s;
$ttToday = (int) (new DateTime('now', new DateTimeZone($t['timezone'] ?: 'Africa/Nairobi')))->format('N');
?>
<div class="card-tutor card p-3 p-md-4 mb-3">
  <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
    <div><h5 class="mb-0"><i class="fas fa-calendar-week me-2 text-success"></i><?= $h($t['title']) ?></h5>
      <div class="small text-muted"><?= !empty($t['tutor_name']) ? 'From ' . $h(trim($t['tutor_name'])) . ' · ' : '' ?>
        Updated <?= $h(date('j M Y', strtotime($t['updated_at'] ?: $t['created_at']))) ?> · Times in <?= $h($t['timezone']) ?></div></div>
    <?php if (!empty($t['pdf_path'])): ?><a class="btn btn-sm btn-outline-success align-self-start" href="<?= $h(url($t['pdf_path'])) ?>" target="_blank"><i class="fas fa-file-pdf me-1"></i>Open PDF</a><?php endif; ?>
  </div>
  <?php if ($t['slots']): ?>
  <div class="table-responsive"><table class="tt-table">
    <thead><tr><th>Day</th><th>Course</th><th>Time</th><th>Venue</th></tr></thead><tbody>
    <?php foreach (TimetableService::DAYS as $n => $day): foreach ($ttByDay[$n] ?? [] as $i => $s): ?>
      <tr<?= $n === $ttToday ? ' style="background:#f2faf6"' : '' ?>>
        <?php if ($i === 0): ?><td class="tt-day" rowspan="<?= count($ttByDay[$n]) ?>"><?= $day ?><?= $n === $ttToday ? ' <span class="badge bg-success">Today</span>' : '' ?></td><?php endif; ?>
        <td><?= $h($s['course']) ?></td>
        <td style="white-space:nowrap"><?= $h(substr($s['start_time'], 0, 5)) ?><?= $s['end_time'] ? '–' . $h(substr($s['end_time'], 0, 5)) : '' ?></td>
        <td><?php $v = (string) $s['venue']; echo preg_match('#^https?://#', $v) ? '<a href="' . $h($v) . '" target="_blank" rel="noopener">Join link</a>' : $h($v); ?></td>
      </tr>
    <?php endforeach; endforeach; ?>
    </tbody></table></div>
  <?php elseif (!empty($t['pdf_path'])): ?>
    <iframe src="<?= $h(url($t['pdf_path'])) ?>" style="width:100%;height:480px;border:1px solid #dfe7e3;border-radius:10px"></iframe>
  <?php endif; ?>
</div>
