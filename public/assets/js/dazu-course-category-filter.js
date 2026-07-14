/**
 * Course category tab filter — PHP renders all courses on load; tabs filter on click.
 * Category id "0" means show every course.
 */
(function () {
  function courseItems(root) {
    if (!root) return [];
    var items = root.querySelectorAll('.dazu-course-list-item');
    if (items.length) return items;
    return root.querySelectorAll('[data-category-id]');
  }

  function resolveListing(tabsRoot) {
    var gridId = tabsRoot.getAttribute('data-grid-id');
    var listing = tabsRoot.closest('.dazu-courses-listing');
    var grid = gridId ? document.getElementById(gridId) : null;
    if (!grid && listing) {
      grid = listing.querySelector('.dazu-courses-grid');
    }
    var items = courseItems(grid);
    if (!items.length && listing) {
      items = courseItems(listing);
    }
    return { grid: grid, items: items };
  }

  function showCategory(tabsRoot, items, countEl, catId) {
    catId = String(catId);
    var tabs = tabsRoot.querySelectorAll('.dazu-course-cat-tab');
    tabs.forEach(function (tab) {
      var active = tab.getAttribute('data-category-id') === catId;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.classList.toggle('is-active', active);
    });
    var visible = 0;
    items.forEach(function (wrap) {
      var itemCat = String(wrap.getAttribute('data-category-id') || '');
      var show = catId === '0' || itemCat === catId;
      wrap.classList.toggle('dazu-filter-hidden', !show);
      if (show) visible++;
    });
    if (countEl) countEl.textContent = String(visible);
  }

  function initRow(tabsRoot) {
    if (tabsRoot.dataset.filterInit === '1') return;
    var countId = tabsRoot.getAttribute('data-count-id');
    var countEl = countId ? document.getElementById(countId) : null;
    var tabs = tabsRoot.querySelectorAll('.dazu-course-cat-tab');
    if (!tabs.length) return;

    var listing = resolveListing(tabsRoot);
    if (!listing.items.length) return;

    tabsRoot.dataset.filterInit = '1';

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        showCategory(tabsRoot, listing.items, countEl, tab.getAttribute('data-category-id'));
      });
    });
  }

  function initAll() {
    document.querySelectorAll('.dazu-course-cat-row[data-grid-id]').forEach(initRow);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    setTimeout(initAll, 0);
  }
})();
