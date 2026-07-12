<?php
/**
 * Course category filter cards — white card, green border, icon + name stacked.
 * Expects $courseCategories, $activeCategoryId, $tabsId, $countId, $gridId.
 */
$courseCategories = $courseCategories ?? [];
$activeCategoryId = (int) ($activeCategoryId ?? 0);
$tabsId = $tabsId ?? 'dazu-course-cat-tabs';
$countId = $countId ?? 'dazu-courses-count';
$gridId = $gridId ?? '';
if (empty($courseCategories) || $gridId === '') {
    return;
}
static $dazuCatStylesPrinted = false;
?>
<?php if (!$dazuCatStylesPrinted): $dazuCatStylesPrinted = true; ?>
<style>
.dazu-course-cat-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  justify-content: flex-start;
  overflow-x: auto;
  padding-bottom: 0.5rem;
  -webkit-overflow-scrolling: touch;
}
.dazu-course-cat-row::-webkit-scrollbar { height: 6px; }
.dazu-course-cat-row::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
.dazu-course-cat-tab {
  flex-shrink: 0;
  width: 130px;
  min-height: 130px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 1rem 0.75rem;
  background: #fff;
  border: 2px solid #e2e8f0;
  border-radius: 0;
  cursor: pointer;
  text-align: center;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.dazu-course-cat-tab:hover { border-color: rgba(21, 162, 74, 0.45); }
.dazu-course-cat-tab.is-active {
  border-color: #15A24A;
  box-shadow: 0 4px 14px rgba(21, 162, 74, 0.12);
}
.dazu-course-cat-icon-wrap {
  width: 2.75rem;
  height: 2.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
}
.dazu-course-cat-icon-img {
  width: 2rem;
  height: 2rem;
  object-fit: contain;
  filter: brightness(0) saturate(100%) invert(36%) sepia(85%) saturate(650%) hue-rotate(103deg) brightness(95%) contrast(92%);
}
.dazu-course-cat-icon-svg { width: 2rem; height: 2rem; color: #15A24A; }
.dazu-course-cat-name {
  font-size: 0.75rem;
  font-weight: 800;
  line-height: 1.25;
  color: #0f172a;
  display: block;
  width: 100%;
}
.dazu-course-cat-count { font-size: 0.625rem; font-weight: 600; color: #94a3b8; line-height: 1; }
.dazu-course-cat-tab.is-active .dazu-course-cat-name { color: #15A24A; }
.dazu-course-list-item.dazu-filter-hidden { display: none !important; }
</style>
<?php endif; ?>

<div class="dazu-course-cat-row" id="<?php echo htmlspecialchars($tabsId); ?>"
     role="tablist"
     data-grid-id="<?php echo htmlspecialchars($gridId); ?>"
     data-count-id="<?php echo htmlspecialchars($countId); ?>">
  <?php foreach ($courseCategories as $i => $cat):
      $catId = (int) $cat['id'];
      $isActive = $activeCategoryId ? ($catId === $activeCategoryId) : ($i === 0);
      $iconUrl = dazu_category_icon_url($cat['icon_path'] ?? null);
      $count = (int) ($cat['course_count'] ?? 0);
  ?>
    <button type="button" role="tab" aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
            data-category-id="<?php echo $catId; ?>"
            class="dazu-course-cat-tab<?php echo $isActive ? ' is-active' : ''; ?>">
      <span class="dazu-course-cat-icon-wrap">
        <?php if ($iconUrl !== ''): ?>
          <img src="<?php echo htmlspecialchars($iconUrl); ?>" alt="" class="dazu-course-cat-icon-img">
        <?php else: ?>
          <svg class="dazu-course-cat-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        <?php endif; ?>
      </span>
      <span class="dazu-course-cat-name"><?php echo htmlspecialchars($cat['name']); ?></span>
      <span class="dazu-course-cat-count"><?php echo $count; ?> course<?php echo $count === 1 ? '' : 's'; ?></span>
    </button>
  <?php endforeach; ?>
</div>
