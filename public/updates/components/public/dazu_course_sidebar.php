<?php
/** Left column — selected program details. Requires enriched $course */
if (!isset($course)) return;
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../../app/v1/models/SettingModel.php';
$category = dazu_course_category($course['abbreviation'] ?? '');
$syllabus = dazu_course_syllabus($course);
$requirements = dazu_course_requirements($course);
$months = (int) ($course['duration_months'] ?? 0);
$weeks = (int) ($course['weeks_of_study'] ?? 0);
$hours = (int) ($course['hours_of_study'] ?? 0);
$schedule = trim((string) ($course['study_schedule'] ?? ''));
$onlineSchedule = '8am, 12pm, 6pm';
$physicalSchedule = '5am';
$scheduleNote = '';
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $siteSettings = new SettingModel($pdo);
        $onlineSchedule = trim((string) ($siteSettings->get('class_schedule_online', '8am, 12pm, 6pm') ?? '8am, 12pm, 6pm'));
        $physicalSchedule = trim((string) ($siteSettings->get('class_schedule_physical', '5am') ?? '5am'));
        $scheduleNote = trim((string) ($siteSettings->get('class_schedule_note', '') ?? ''));
    } catch (Throwable $e) {
        $scheduleNote = '';
    }
}
$isFree = !empty($course['is_free']);
$fee = number_format((float) ($course['course_fee'] ?? 0));
$enrollWindow = $isFree ? dazu_course_enrollment_window($course) : ['label' => null];
?>
<div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8" id="learn">
  <span class="text-green-600 text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 bg-green-50 border border-green-200 rounded-full inline-block mb-4">Selected Program Details</span>

  <div class="flex items-center gap-3 border-b border-slate-100 pb-5 mb-6">
    <div class="p-3 bg-green-50 border border-green-100 text-[#15A24A] rounded-xl">
      <?php echo dazu_course_icon_markup($course['abbreviation'] ?? ''); ?>
    </div>
    <div>
      <h2 class="text-xl font-extrabold text-slate-950 tracking-tight uppercase"><?php echo htmlspecialchars($course['title']); ?></h2>
      <span class="text-xs text-slate-400 font-bold uppercase tracking-wider"><?php echo htmlspecialchars($category); ?></span>
    </div>
  </div>

  <div class="space-y-4">
    <div>
      <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Overview</span>
      <p class="text-xs text-slate-600 font-medium leading-relaxed"><?php echo htmlspecialchars($course['overview'] ?? $course['description'] ?? ''); ?></p>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="p-3 bg-slate-50 border border-slate-200/60 rounded-xl">
        <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Duration</span>
        <span class="text-xs font-bold text-slate-900 flex items-center gap-1">
          <svg class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <?php echo $months; ?> Months
        </span>
      </div>
      <div class="p-3 bg-slate-50 border border-slate-200/60 rounded-xl">
        <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Tuition Fee</span>
        <span class="text-xs font-bold <?php echo $isFree ? 'text-green-600' : 'text-slate-900'; ?> flex items-center gap-1">
          <svg class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
          <?php echo $isFree ? 'FREE' : 'KES ' . $fee; ?>
        </span>
      </div>
      <?php if ($weeks > 0): ?>
      <div class="p-3 bg-slate-50 border border-slate-200/60 rounded-xl">
        <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Weeks of Study</span>
        <span class="text-xs font-bold text-slate-900 flex items-center gap-1">
          <svg class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <?php echo $weeks; ?> Week<?php echo $weeks === 1 ? '' : 's'; ?>
        </span>
      </div>
      <?php endif; ?>
      <?php if ($hours > 0): ?>
      <div class="p-3 bg-slate-50 border border-slate-200/60 rounded-xl">
        <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Hours of Study</span>
        <span class="text-xs font-bold text-slate-900 flex items-center gap-1">
          <svg class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3M12 3a9 9 0 100 18 9 9 0 000-18z"/></svg>
          <?php echo $hours; ?> Hr<?php echo $hours === 1 ? '' : 's'; ?>
        </span>
      </div>
      <?php endif; ?>
      <?php if (!empty($enrollWindow['label'])): ?>
      <div class="p-3 bg-slate-50 border border-slate-200/60 rounded-xl col-span-2">
        <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Application Window</span>
        <span class="text-xs font-bold text-slate-900 flex items-center gap-1">
          <svg class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          <?php echo htmlspecialchars($enrollWindow['label']); ?>
        </span>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($schedule !== '' || $onlineSchedule !== '' || $physicalSchedule !== ''): ?>
    <details class="group border border-slate-200 rounded-xl bg-white overflow-hidden">
      <summary class="list-none cursor-pointer flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
        <span class="inline-flex items-center gap-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-600">
          <svg class="h-4 w-4 text-[#15A24A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          View class schedule
        </span>
        <svg class="h-4 w-4 text-slate-400 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </summary>
      <div class="border-t border-slate-100 px-4 py-4 space-y-3 text-xs text-slate-700">
        <?php if ($onlineSchedule !== ''): ?>
          <div class="rounded-xl border border-green-100 bg-green-50 px-3 py-3">
            <span class="block text-[9px] font-extrabold uppercase tracking-widest text-green-700 mb-1">Online classes</span>
            <span class="block font-bold text-slate-950"><?php echo htmlspecialchars($onlineSchedule); ?></span>
          </div>
        <?php endif; ?>
        <?php if ($physicalSchedule !== ''): ?>
          <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
            <span class="block text-[9px] font-extrabold uppercase tracking-widest text-slate-500 mb-1">Physical classes</span>
            <span class="block font-bold text-slate-950"><?php echo htmlspecialchars($physicalSchedule); ?></span>
          </div>
        <?php endif; ?>
        <?php if ($schedule !== ''): ?>
          <div class="text-slate-500 font-medium"><?php echo htmlspecialchars($schedule); ?></div>
        <?php endif; ?>
        <?php if ($scheduleNote !== ''): ?>
          <div class="text-[11px] text-slate-500 leading-relaxed border-t border-slate-100 pt-3"><?php echo htmlspecialchars($scheduleNote); ?></div>
        <?php endif; ?>
      </div>
    </details>
    <?php endif; ?>

    <?php $fee_compact = false; $fee_show_download = false; include __DIR__ . '/dazu_course_fee_breakdown.php'; ?>
    <?php $brochure_compact = false; include __DIR__ . '/dazu_course_brochure_cta.php'; ?>

    <div>
      <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-2">What you will learn</span>
      <div class="space-y-1.5 max-h-[220px] overflow-y-auto pr-1">
        <?php foreach ($syllabus as $item): ?>
          <div class="flex items-start gap-2 text-xs font-medium text-slate-700">
            <svg class="h-3.5 w-3.5 text-[#15A24A] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span><?php echo htmlspecialchars(is_string($item) ? $item : (string) $item); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-2">Requirements</span>
      <div class="space-y-1.5">
        <?php foreach ($requirements as $item): ?>
          <div class="flex items-start gap-2 text-xs font-medium text-slate-700">
            <svg class="h-3.5 w-3.5 text-[#15A24A] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span><?php echo htmlspecialchars($item); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<div class="bg-slate-900 text-white rounded-2xl p-6 space-y-3 mt-6">
  <div class="flex items-center gap-2">
    <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
    <span class="text-xs uppercase font-extrabold tracking-wider">Admissions Verification</span>
  </div>
  <p class="text-xs text-slate-400 leading-relaxed">Your payment and enrollment data is encrypted. Certificates and industrial preparation are issued upon successful completion.</p>
</div>
