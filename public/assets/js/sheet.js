/* Excel-style sheets: bottom tabs, click-a-cell formula bar, and live search across the visible sheet. */
(function () {
  'use strict';
  var sheets = Array.prototype.slice.call(document.querySelectorAll('.xl-sheet'));
  var tabs = document.querySelectorAll('.xl-tab');
  var search = document.querySelector('[data-sheet-search]');
  var count = document.querySelector('[data-sheet-count]');

  function activeSheet() { return document.querySelector('.xl-sheet.active') || sheets[0] || document.querySelector('.xl-shell'); }
  function dataRows(scope) {
    return scope ? Array.prototype.slice.call(scope.querySelectorAll('tbody tr')).filter(function (r) { return !r.classList.contains('xl-empty'); }) : [];
  }

  function applySearch() {
    var scope = activeSheet();
    if (!scope) return;
    var q = search ? search.value.toLowerCase().trim() : '';
    var rows = dataRows(scope), shown = 0, n = 0;
    rows.forEach(function (r) {
      if (r.dataset.norow) return;                      // placeholder rows such as "No tutors yet"
      n++;
      var hit = q === '' || r.textContent.toLowerCase().replace(/\s+/g, ' ').indexOf(q) !== -1;
      r.classList.toggle('xl-hide', !hit);
      if (hit) { shown++; var rn = r.querySelector('.rn'); if (rn) rn.textContent = shown; }
    });
    var tbody = scope.querySelector('tbody'), none = scope.querySelector('tr.xl-empty');
    if (tbody && q !== '' && n > 0 && shown === 0) {
      if (!none) {
        none = document.createElement('tr'); none.className = 'xl-empty';
        none.innerHTML = '<td colspan="99" class="text-muted text-center py-4">Nothing matches your search.</td>';
        tbody.appendChild(none);
      }
    } else if (none) { none.remove(); }
    if (count) count.textContent = q === '' ? n + ' row' + (n === 1 ? '' : 's') : shown + ' of ' + n;
  }

  function show(name) {
    sheets.forEach(function (s) { s.classList.toggle('active', s.id === 'sheet-' + name); });
    tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.sheet === name); });
    try { history.replaceState(null, '', '#' + name); } catch (e) {}
    document.dispatchEvent(new CustomEvent('sheet:show', { detail: name }));
    applySearch();
  }

  tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.sheet); }); });
  if (search) search.addEventListener('input', applySearch);

  // formula bar
  var ref = document.getElementById('xlRef'), val = document.getElementById('xlVal');
  document.querySelectorAll('table.xl tbody td').forEach(function (td) {
    td.addEventListener('click', function (e) {
      if (e.target.closest('a,button,input,select,form')) return;
      document.querySelectorAll('td.sel').forEach(function (c) { c.classList.remove('sel'); });
      td.classList.add('sel');
      if (!ref || !val) return;
      var rn = td.parentNode.querySelector('.rn');
      ref.textContent = String.fromCharCode(65 + td.cellIndex - 1) + (rn ? rn.textContent.trim() : '');
      val.textContent = td.dataset.full || td.textContent.replace(/\s+/g, ' ').trim();
    });
  });

  var start = (location.hash || '').replace('#', '');
  if (start && document.getElementById('sheet-' + start)) show(start); else applySearch();
})();
