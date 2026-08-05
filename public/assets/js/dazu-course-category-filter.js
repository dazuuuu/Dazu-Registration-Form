/**
 * Course category tab filter — init all .dazu-course-cat-row blocks on DOM ready.
 *
 * Category id "0" is the "All courses" pseudo-category: it shows every card.
 * Any other id shows only cards carrying that data-category-id.
 *
 * Read from the .dazu-course-cat-row element:
 *   data-grid-id    required  id of the course grid
 *   data-count-id   optional  element whose textContent is the visible count
 *   data-search-id  optional  text input that narrows the grid
 *   data-empty-id   optional  block shown when nothing matches
 *   data-url-sync   optional  "1" to reflect the active category in ?category=
 */
(function () {
  'use strict';

  var ALL = '0';

  function normalise(v) {
    return String(v == null ? '' : v).trim().toLowerCase();
  }

  function apply(ctx) {
    var catId = String(ctx.category);
    var query = ctx.query;

    ctx.tabs.forEach(function (tab) {
      var active = tab.getAttribute('data-category-id') === catId;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      // Roving tabindex: the tablist is a single Tab stop, arrows move within it.
      tab.setAttribute('tabindex', active ? '0' : '-1');
      tab.classList.toggle('is-active', active);
    });

    var visible = 0;
    ctx.items.forEach(function (wrap) {
      var show = (catId === ALL) || wrap.getAttribute('data-category-id') === catId;
      if (show && query !== '') {
        show = (wrap.getAttribute('data-search') || '').indexOf(query) !== -1;
      }
      wrap.classList.toggle('dazu-filter-hidden', !show);
      if (show) visible++;
    });

    if (ctx.countEl) ctx.countEl.textContent = String(visible);

    if (ctx.emptyEl) {
      ctx.emptyEl.hidden = (visible !== 0);
      var msg = ctx.emptyEl.querySelector('[data-empty-message]');
      if (msg) {
        msg.textContent = (query !== '' && ctx.searchEl)
          ? 'Nothing matched \u201C' + ctx.searchEl.value.trim() + '\u201D here. Try another word, or pick a different category.'
          : 'There are no tracks in this category yet.';
      }
    }
  }

  function knownCategory(tabsRoot, catId) {
    catId = String(catId);
    var tabs = tabsRoot.querySelectorAll('.dazu-course-cat-tab');
    for (var i = 0; i < tabs.length; i++) {
      if (tabs[i].getAttribute('data-category-id') === catId) return catId;
    }
    return ALL;
  }

  function syncUrl(enabled, catId) {
    if (!enabled || !window.history || !window.history.replaceState) return;
    try {
      var url = new URL(window.location.href);
      if (catId === ALL) {
        url.searchParams.delete('category');
      } else {
        url.searchParams.set('category', catId);
      }
      window.history.replaceState(null, '', url.toString());
    } catch (e) {
      /* No URL support — filtering still works, the address bar just won't update. */
    }
  }

  function initRow(tabsRoot) {
    if (tabsRoot.dataset.filterInit === '1') return;
    var gridId = tabsRoot.getAttribute('data-grid-id');
    if (!gridId) return;
    var grid = document.getElementById(gridId);
    if (!grid) return;

    var countId = tabsRoot.getAttribute('data-count-id');
    var countEl = countId ? document.getElementById(countId) : null;
    var searchId = tabsRoot.getAttribute('data-search-id');
    var emptyId = tabsRoot.getAttribute('data-empty-id');
    var urlSync = tabsRoot.getAttribute('data-url-sync') === '1';
    var tabs = Array.prototype.slice.call(tabsRoot.querySelectorAll('.dazu-course-cat-tab'));
    if (!tabs.length) return;

    tabsRoot.dataset.filterInit = '1';

    var ctx = {
      tabs: tabs,
      items: Array.prototype.slice.call(grid.querySelectorAll('.dazu-course-list-item')),
      countEl: countEl,
      emptyEl: emptyId ? document.getElementById(emptyId) : null,
      searchEl: searchId ? document.getElementById(searchId) : null,
      category: ALL,
      query: ''
    };

    function select(catId, moveFocus) {
      ctx.category = knownCategory(tabsRoot, catId);
      apply(ctx);
      syncUrl(urlSync, ctx.category);
      if (moveFocus) {
        var target = tabsRoot.querySelector('.dazu-course-cat-tab[data-category-id="' + ctx.category + '"]');
        if (target) target.focus();
      }
    }

    tabs.forEach(function (tab, index) {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        select(tab.getAttribute('data-category-id'), false);
      });

      tab.addEventListener('keydown', function (e) {
        var next = null;
        switch (e.key) {
          case 'ArrowRight':
          case 'ArrowDown': next = tabs[(index + 1) % tabs.length]; break;
          case 'ArrowLeft':
          case 'ArrowUp':   next = tabs[(index - 1 + tabs.length) % tabs.length]; break;
          case 'Home':      next = tabs[0]; break;
          case 'End':       next = tabs[tabs.length - 1]; break;
          default: return;
        }
        e.preventDefault();
        select(next.getAttribute('data-category-id'), true);
      });
    });

    // Start from ?category=, else whatever the server marked active, else All.
    var initial = ALL;
    if (urlSync) {
      try {
        var fromUrl = new URL(window.location.href).searchParams.get('category');
        if (fromUrl) initial = knownCategory(tabsRoot, fromUrl);
      } catch (e) { /* ignore */ }
    }
    if (initial === ALL) {
      var preset = tabsRoot.querySelector('.dazu-course-cat-tab.is-active');
      if (preset) initial = preset.getAttribute('data-category-id');
    }

    if (ctx.searchEl) {
      var timer = null;
      ctx.query = normalise(ctx.searchEl.value);   // browsers restore inputs on back/reload
      ctx.searchEl.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
          ctx.query = normalise(ctx.searchEl.value);
          apply(ctx);
        }, 120);
      });
      ctx.searchEl.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && ctx.searchEl.value !== '') {
          e.preventDefault();
          ctx.searchEl.value = '';
          ctx.query = '';
          apply(ctx);
        }
      });
    }

    if (ctx.emptyEl) {
      var reset = ctx.emptyEl.querySelector('[data-empty-reset]');
      if (reset) {
        reset.addEventListener('click', function () {
          if (ctx.searchEl) ctx.searchEl.value = '';
          ctx.query = '';
          select(ALL, false);
        });
      }
    }

    ctx.category = knownCategory(tabsRoot, initial);
    apply(ctx);
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