<?php
/**
 * Course category filter cards — click to show courses in that category.
 * Expects $courseCategories (array with id, name, icon_path, course_count).
 */
$courseCategories = $courseCategories ?? [];
$activeCategoryId = (int) ($activeCategoryId ?? 0);
if (empty($courseCategories)) {
    return;
}
?>
<div class="flex gap-4 overflow-x-auto pb-2 snap-x" id="dazu-course-cat-tabs" role="tablist">
  <?php foreach ($courseCategories as $i => $cat):
      $catId = (int) $cat['id'];
      $isActive = $activeCategoryId ? ($catId === $activeCategoryId) : ($i === 0);
      $iconUrl = dazu_category_icon_url($cat['icon_path'] ?? null);
      $count = (int) ($cat['course_count'] ?? 0);
  ?>
    <button type="button" role="tab" aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
            data-category-id="<?php echo $catId; ?>"
            class="dazu-course-cat-tab flex-shrink-0 snap-start min-w-[200px] sm:min-w-[220px] rounded-none border p-4 text-left transition-all <?php echo $isActive ? 'bg-[#15A24A] border-[#15A24A] text-white shadow-md' : 'bg-white border-slate-200 text-slate-900 hover:border-[#15A24A]/50'; ?>">
      <div class="flex items-start gap-4">
        <span class="dazu-cat-icon-wrap w-12 h-12 rounded-xl flex items-center justify-center shrink-0 <?php echo $isActive ? 'bg-white/20' : 'bg-green-50'; ?>">
          <?php if ($iconUrl !== ''): ?>
            <img src="<?php echo htmlspecialchars($iconUrl); ?>" alt="" class="dazu-cat-icon w-7 h-7 object-contain <?php echo $isActive ? 'is-active' : ''; ?>">
          <?php else: ?>
            <svg class="dazu-cat-icon w-7 h-7 <?php echo $isActive ? 'text-white' : 'text-[#15A24A]'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
          <?php endif; ?>
        </span>
        <div class="min-w-0">
          <p class="text-sm font-extrabold leading-tight"><?php echo htmlspecialchars($cat['name']); ?></p>
          <p class="text-[11px] font-semibold mt-1 opacity-80"><?php echo $count; ?> Course<?php echo $count === 1 ? '' : 's'; ?></p>
        </div>
      </div>
    </button>
  <?php endforeach; ?>
</div>

<style>
#dazu-course-cat-tabs::-webkit-scrollbar { height: 6px; }
#dazu-course-cat-tabs::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
.dazu-cat-icon:not(.is-active) {
  filter: brightness(0) saturate(100%) invert(36%) sepia(85%) saturate(650%) hue-rotate(103deg) brightness(95%) contrast(92%);
}
.dazu-cat-icon.is-active { filter: brightness(0) invert(1); }
</style>

<script>
(function () {
  var tabs = document.querySelectorAll('.dazu-course-cat-tab');
  var wraps = document.querySelectorAll('.dazu-course-list-item');
  if (!tabs.length || !wraps.length) return;

  function showCategory(catId) {
    catId = String(catId);
    tabs.forEach(function (tab) {
      var active = tab.getAttribute('data-category-id') === catId;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.classList.toggle('bg-[#15A24A]', active);
      tab.classList.toggle('border-[#15A24A]', active);
      tab.classList.toggle('text-white', active);
      tab.classList.toggle('shadow-md', active);
      tab.classList.toggle('bg-white', !active);
      tab.classList.toggle('border-slate-200', !active);
      tab.classList.toggle('text-slate-900', !active);
      var icon = tab.querySelector('.dazu-cat-icon');
      var iconWrap = tab.querySelector('.dazu-cat-icon-wrap');
      if (icon) icon.classList.toggle('is-active', active);
      if (iconWrap) {
        iconWrap.classList.toggle('bg-white/20', active);
        iconWrap.classList.toggle('bg-green-50', !active);
      }
    });
    wraps.forEach(function (wrap) {
      wrap.classList.toggle('hidden', wrap.getAttribute('data-category-id') !== catId);
    });
    var countEl = document.getElementById('dazu-courses-count');
    if (countEl) {
      var visible = 0;
      wraps.forEach(function (w) { if (!w.classList.contains('hidden')) visible++; });
      countEl.textContent = visible;
    }
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      showCategory(tab.getAttribute('data-category-id'));
    });
  });
})();
</script>
