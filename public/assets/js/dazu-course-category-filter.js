/**
 * Course category tab filter — default shows ALL courses (category id 0).
 */
(function () {
  function showCategory(tabsRoot, grid, countEl, catId) {
    catId = String(catId);
    var tabs = tabsRoot.querySelectorAll('.dazu-course-cat-tab');
    var wraps = grid.querySelectorAll('.dazu-course-list-item');
    tabs.forEach(function (tab) {
      var active = tab.getAttribute('data-category-id') === catId;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.classList.toggle('is-active', active);
    });
    var visible = 0;
    wraps.forEach(function (wrap) {
      var itemCat = String(wrap.getAttribute('data-category-id') || '');
      var show = catId === '0' || itemCat === catId;
      wrap.classList.toggle('dazu-filter-hidden', !show);
      if (show) visible++;
    });
    if (countEl) countEl.textContent = String(visible);
  }

  function initRow(tabsRoot) {
    if (tabsRoot.dataset.filterInit === '1') return;
    var gridId = tabsRoot.getAttribute('data-grid-id');
    if (!gridId) return;
    var grid = document.getElementById(gridId);
    if (!grid) return;
    var countId = tabsRoot.getAttribute('data-count-id');
    var countEl = countId ? document.getElementById(countId) : null;
    var tabs = tabsRoot.querySelectorAll('.dazu-course-cat-tab');
    if (!tabs.length) return;

    tabsRoot.dataset.filterInit = '1';

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        showCategory(tabsRoot, grid, countEl, tab.getAttribute('data-category-id'));
      });
    });

    var allTab = tabsRoot.querySelector('.dazu-course-cat-tab[data-category-id="0"]');
    var startId = allTab ? '0' : (tabsRoot.querySelector('.dazu-course-cat-tab.is-active') || tabs[0]).getAttribute('data-category-id');
    showCategory(tabsRoot, grid, countEl, startId);
  }

  function initAll() {
    document.querySelectorAll('.dazu-course-cat-row[data-grid-id]').forEach(initRow);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
