<?php
/**
 * Course filter bar — search + category pills + result count.
 *
 * Expects $courseCategories, $tabsId, $countId, $gridId.
 * Optional:
 *   $activeCategoryId  int   0 = "All" (the default)
 *   $totalCourseCount  int   courses in the grid, shown on the All pill
 *   $showAllTab        bool  default true
 *   $urlSync           bool  reflect the active category in ?category=
 *   $showSearch        bool  render the search box (default false)
 *   $searchId          string id for the search input
 *   $emptyId           string id of the "no results" block next to the grid
 *
 * Category id 0 is the "All" pseudo-category.
 */
$courseCategories = $courseCategories ?? [];
$activeCategoryId = (int) ($activeCategoryId ?? 0);
$tabsId = $tabsId ?? 'dazu-course-cat-tabs';
$countId = $countId ?? 'dazu-courses-count';
$gridId = $gridId ?? '';
$showAllTab = $showAllTab ?? true;
$urlSync = !empty($urlSync);
$showSearch = !empty($showSearch);
$searchId = $searchId ?? '';
$emptyId = $emptyId ?? '';
if (empty($courseCategories) || $gridId === '') {
    return;
}

// dazu_recount_course_categories() already drops zero-count categories, but this
// component is included directly too — guard rather than assume.
$courseCategories = array_values(array_filter(
    $courseCategories,
    static fn($c) => (int) ($c['id'] ?? 0) > 0 && (int) ($c['course_count'] ?? 0) > 0
));
if (empty($courseCategories)) {
    return;
}

$totalCourseCount = (int) ($totalCourseCount ?? array_sum(array_map(
    static fn($c) => (int) ($c['course_count'] ?? 0),
    $courseCategories
)));

// An unknown or now-empty ?category= must fall back to All, never a blank grid.
$validIds = array_map(static fn($c) => (int) $c['id'], $courseCategories);
if ($activeCategoryId !== 0 && !in_array($activeCategoryId, $validIds, true)) {
    $activeCategoryId = 0;
}
if (!$showAllTab && $activeCategoryId === 0) {
    $activeCategoryId = (int) $courseCategories[0]['id'];
}
static $dazuCatStylesPrinted = false;
?>
<?php if (!$dazuCatStylesPrinted): $dazuCatStylesPrinted = true; ?>
<style>
/* Kept inline rather than in assets/css: sw.js caches every .css file, so a new
   stylesheet is one more thing that has to survive a deploy. This markup is
   rendered by PHP, which the service worker never intercepts. */
.dazu-course-filters { display: flex; flex-direction: column; gap: 1.25rem; }

.dazu-course-filters__row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

/* --- search --- */
.dazu-course-search { position: relative; flex: 1 1 260px; max-width: 340px; }
.dazu-course-search svg {
  position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
  width: 1.05rem; height: 1.05rem; color: #94a3b8; pointer-events: none;
}
.dazu-course-search input {
  width: 100%;
  padding: 0.85rem 1rem 0.85rem 2.85rem;
  background: #f8fafc;
  border: 1px solid #e8edf3;
  border-radius: 14px;
  font: inherit;
  font-size: 0.875rem;
  font-weight: 500;
  color: #0f172a;
  transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
}
.dazu-course-search input::placeholder { color: #94a3b8; font-weight: 400; }
.dazu-course-search input:focus {
  outline: none; background: #fff;
  border-color: #15a24a;
  box-shadow: 0 0 0 3px rgba(21, 162, 74, .14);
}
.dazu-course-search input::-webkit-search-cancel-button { cursor: pointer; }

/* --- category pills --- */
.dazu-course-cat-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex: 1 1 auto;
  flex-wrap: nowrap;
  overflow-x: auto;
  padding-bottom: 2px;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
}
@media (min-width: 768px) {
  .dazu-course-cat-row { flex-wrap: wrap; overflow-x: visible; }
}
.dazu-course-cat-row::-webkit-scrollbar { height: 4px; }
.dazu-course-cat-row::-webkit-scrollbar-track { background: transparent; }
.dazu-course-cat-row::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }

.dazu-course-cat-tab {
  flex: 0 0 auto;
  padding: 0.85rem 1.25rem;
  background: #fff;
  border: 1px solid #e8edf3;
  border-radius: 12px;
  font: inherit;
  font-size: 0.78rem;
  font-weight: 800;
  line-height: 1;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: #1e293b;
  white-space: nowrap;
  cursor: pointer;
  transition: border-color .15s ease, background-color .15s ease, color .15s ease, box-shadow .15s ease;
}
.dazu-course-cat-tab:hover { border-color: #15a24a; color: #0f172a; }
.dazu-course-cat-tab:focus-visible {
  outline: none;
  border-color: #15a24a;
  box-shadow: 0 0 0 3px rgba(21, 162, 74, .28);
}
.dazu-course-cat-tab.is-active {
  background: #15a24a;
  border-color: #15a24a;
  color: #fff;
  box-shadow: 0 6px 16px -8px rgba(21, 162, 74, .7);
}

/* --- meta row --- */
.dazu-course-filters__meta {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 1rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid #eef2f7;
}
.dazu-course-resultline {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: .05em;
  text-transform: uppercase;
  color: #64748b;
}
.dazu-course-resultline b { color: #15a24a; font-weight: 800; }

.dazu-course-list-item.dazu-filter-hidden { display: none !important; }

/* --- no results --- */
.dazu-course-empty {
  padding: 3.5rem 1.5rem;
  border: 1px dashed #e2e8f0;
  border-radius: 16px;
  background: #f8fafc;
  text-align: center;
}
.dazu-course-empty[hidden] { display: none; }
.dazu-course-empty h3 {
  margin: 0;
  font-family: inherit !important;
  font-size: 0.95rem; font-weight: 800; color: #0f172a;
  letter-spacing: normal !important;
}
.dazu-course-empty p { margin: .35rem 0 0; font-size: .8rem; color: #64748b; }
.dazu-course-empty button {
  margin-top: 1rem; padding: .55rem 1rem;
  background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
  font: inherit; font-size: .75rem; font-weight: 800; color: #0f172a; cursor: pointer;
}
.dazu-course-empty button:hover { border-color: #15a24a; color: #15a24a; }

@media (prefers-reduced-motion: reduce) {
  .dazu-course-cat-tab, .dazu-course-search input { transition: none; }
}
</style>
<?php endif; ?>

<div class="dazu-course-filters">
  <div class="dazu-course-filters__row">
    <?php if ($showSearch && $searchId !== ''): ?>
      <div class="dazu-course-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <label class="sr-only" for="<?php echo htmlspecialchars($searchId, ENT_QUOTES); ?>">Search courses</label>
        <input type="search" id="<?php echo htmlspecialchars($searchId, ENT_QUOTES); ?>"
               placeholder="Search courses, skills, tools..." autocomplete="off" spellcheck="false">
      </div>
    <?php endif; ?>

    <div class="dazu-course-cat-row"
         id="<?php echo htmlspecialchars($tabsId, ENT_QUOTES); ?>"
         role="tablist"
         aria-label="Filter courses by category"
         data-grid-id="<?php echo htmlspecialchars($gridId, ENT_QUOTES); ?>"
         data-count-id="<?php echo htmlspecialchars($countId, ENT_QUOTES); ?>"
         <?php if ($searchId !== '' && $showSearch): ?>data-search-id="<?php echo htmlspecialchars($searchId, ENT_QUOTES); ?>"<?php endif; ?>
         <?php if ($emptyId !== ''): ?>data-empty-id="<?php echo htmlspecialchars($emptyId, ENT_QUOTES); ?>"<?php endif; ?>
         <?php if ($urlSync): ?>data-url-sync="1"<?php endif; ?>>

      <?php if ($showAllTab): $allActive = ($activeCategoryId === 0); ?>
        <button type="button" role="tab" aria-selected="<?php echo $allActive ? 'true' : 'false'; ?>"
                tabindex="<?php echo $allActive ? '0' : '-1'; ?>"
                data-category-id="0"
                class="dazu-course-cat-tab<?php echo $allActive ? ' is-active' : ''; ?>">All</button>
      <?php endif; ?>

      <?php foreach ($courseCategories as $cat):
          $catId = (int) $cat['id'];
          $isActive = ($catId === $activeCategoryId);
      ?>
        <button type="button" role="tab" aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
                tabindex="<?php echo $isActive ? '0' : '-1'; ?>"
                data-category-id="<?php echo $catId; ?>"
                class="dazu-course-cat-tab<?php echo $isActive ? ' is-active' : ''; ?>"><?php echo htmlspecialchars($cat['name']); ?></button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="dazu-course-filters__meta">
    <p class="dazu-course-resultline">
      Showing <b id="<?php echo htmlspecialchars($countId, ENT_QUOTES); ?>"><?php echo (int) ($visibleCount ?? $totalCourseCount); ?></b>
      of <?php echo $totalCourseCount; ?> available track<?php echo $totalCourseCount === 1 ? '' : 's'; ?>
    </p>
  </div>
</div>