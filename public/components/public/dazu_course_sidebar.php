<?php
/** Left column — selected program details. Requires enriched $course; optional $allCourses for switcher */
if (!isset($course)) return;
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_catalog.php';

$category = dazu_course_category($course['abbreviation'] ?? '');
$syllabus = dazu_course_syllabus($course);
$months = (int) ($course['duration_months'] ?? 0);
$fee = number_format((float) ($course['course_fee'] ?? 0));
$currentCourseKey = (int) ($course['id'] ?? 0) > 0
    ? 'id:' . (int) $course['id']
    : 'abbr:' . strtoupper(trim($course['abbreviation'] ?? ''));
?>
<div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8" id="learn">
  <span class="text-green-600 text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 bg-green-50 border border-green-200 rounded-full inline-block mb-4">Selected Program Details</span>

  <?php if (!empty($allCourses) && count($allCourses) > 1): ?>
    <div class="mb-6 space-y-2">
      <label for="dazu-course-switcher" class="block text-[10px] font-extrabold uppercase tracking-widest text-slate-500">Change Course</label>
      <select id="dazu-course-switcher"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:outline-none focus:border-[#15A24A] focus:bg-white cursor-pointer">
        <?php foreach ($allCourses as $opt):
            $optKey = (int) ($opt['id'] ?? 0) > 0
                ? 'id:' . (int) $opt['id']
                : 'abbr:' . strtoupper(trim($opt['abbreviation'] ?? ''));
            $optUrl = dazu_course_apply_url($opt);
            if ((int) ($opt['id'] ?? 0) > 0) {
                $optUrl = url('courses/view.php?id=' . (int) $opt['id']);
            }
        ?>
          <option value="<?php echo htmlspecialchars($optUrl); ?>"<?php echo $optKey === $currentCourseKey ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($opt['title'] ?? ''); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="flex items-center gap-3 border-b border-slate-100 pb-5 mb-6">
    <div class="p-3 bg-green-50 border border-green-100 text-[#15A24A] rounded-xl">
      <?php echo dazu_course_icon_markup($course['abbreviation'] ?? ''); ?>
    </div>
    <div>
      <h2 id="dazu-course-title" class="text-xl font-extrabold text-slate-950 tracking-tight uppercase"><?php echo htmlspecialchars($course['title']); ?></h2>
      <span id="dazu-course-category" class="text-xs text-slate-400 font-bold uppercase tracking-wider"><?php echo htmlspecialchars($category); ?></span>
    </div>
  </div>

  <div class="space-y-4">
    <div>
      <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Overview</span>
      <p id="dazu-course-overview" class="text-xs text-slate-600 font-medium leading-relaxed"><?php echo htmlspecialchars($course['overview'] ?? $course['description'] ?? ''); ?></p>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="p-3 bg-slate-50 border border-slate-200/60 rounded-xl">
        <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Duration</span>
        <span id="dazu-course-duration" class="text-xs font-bold text-slate-900 flex items-center gap-1">
          <svg class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <?php echo $months; ?> Months
        </span>
      </div>
      <div class="p-3 bg-slate-50 border border-slate-200/60 rounded-xl">
        <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-1">Tuition Fee</span>
        <span id="dazu-course-fee" class="text-xs font-bold text-slate-900 flex items-center gap-1">
          <svg class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
          KES <?php echo $fee; ?>
        </span>
      </div>
    </div>

    <div>
      <span class="block text-slate-400 uppercase tracking-widest text-[9px] font-extrabold mb-2">What you will learn</span>
      <div id="dazu-course-syllabus" class="space-y-1.5 max-h-[220px] overflow-y-auto pr-1">
        <?php foreach ($syllabus as $item): ?>
          <div class="flex items-start gap-2 text-xs font-medium text-slate-700">
            <svg class="h-3.5 w-3.5 text-[#15A24A] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span><?php echo htmlspecialchars(is_string($item) ? $item : (string) $item); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="p-3 bg-green-50/60 border border-green-100 rounded-xl text-[11px] font-medium text-green-800 leading-normal flex items-start gap-2">
      <svg class="h-3.5 w-3.5 text-green-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <div><strong>Campus Location:</strong> Witeithie House, Room 529, Thika Town, or via interactive virtual setups.</div>
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
