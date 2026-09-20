/* Students sheet: one search bar that understands names, courses and a few keywords (oldest, newest, balance, cleared). */
(function () {
  'use strict';
  var body = document.getElementById('stBody'), input = document.getElementById('stSearch');
  if (!body || !input) return;
  var rows = Array.prototype.slice.call(body.querySelectorAll('tr[data-search]'));
  var empty = document.getElementById('stEmpty'), count = document.getElementById('stCount'), hint = document.getElementById('stHint');
  var hintDefault = hint ? hint.innerHTML : '';
  var heads = document.querySelectorAll('#stTable th[data-sort]');
  var manual = null;   // {key, dir} set by clicking a heading

  var KW = {
    oldest: ['sort', 'date', 1, 'oldest first'], first: ['sort', 'date', 1, 'oldest first'],
    newest: ['sort', 'date', -1, 'newest first'], latest: ['sort', 'date', -1, 'newest first'], recent: ['sort', 'date', -1, 'newest first'],
    balance: ['owing', 'bal', -1, 'owing money, highest balance first'], owing: ['owing', 'bal', -1, 'owing money, highest balance first'],
    owe: ['owing', 'bal', -1, 'owing money, highest balance first'], debt: ['owing', 'bal', -1, 'owing money, highest balance first'],
    arrears: ['owing', 'bal', -1, 'owing money, highest balance first'], unpaid: ['owing', 'bal', -1, 'owing money, highest balance first'],
    outstanding: ['owing', 'bal', -1, 'owing money, highest balance first'],
    cleared: ['cleared', 'name', 1, 'fully paid'], paid: ['cleared', 'name', 1, 'fully paid'], clear: ['cleared', 'name', 1, 'fully paid'],
    names: ['sort', 'name', 1, 'A to Z'], alphabetical: ['sort', 'name', 1, 'A to Z'], 'a-z': ['sort', 'name', 1, 'A to Z'], 'z-a': ['sort', 'name', -1, 'Z to A'],
    highest: ['dir', -1], largest: ['dir', -1], lowest: ['dir', 1], smallest: ['dir', 1]
  };

  function parse(q) {
    var words = q.toLowerCase().split(/\s+/).filter(Boolean), terms = [], out = { terms: terms, sort: null, filter: null, notes: [] }, dirOverride = null;
    words.forEach(function (w) {
      var k = KW[w];
      if (!k) { terms.push(w); return; }
      if (k[0] === 'dir') { dirOverride = k[1]; return; }
      if (k[0] === 'sort') { out.sort = { key: k[1], dir: k[2] }; out.notes.push(k[3]); }
      else { out.filter = k[0]; out.sort = { key: k[1], dir: k[2] }; out.notes.push(k[3]); }
    });
    if (dirOverride !== null && out.sort) out.sort.dir = dirOverride;
    return out;
  }

  function val(r, key) {
    if (key === 'name' || key === 'course') return r.dataset[key] || '';
    return parseFloat(r.dataset[key]) || 0;
  }
  function fmt(n) { return Math.round(n).toLocaleString('en-US'); }

  function render() {
    var p = parse(input.value.trim());
    var sort = p.sort || manual;
    var list = rows.filter(function (r) {
      if (p.filter === 'owing' && !(parseFloat(r.dataset.bal) > 0)) return false;
      if (p.filter === 'cleared' && parseFloat(r.dataset.bal) > 0) return false;
      return p.terms.every(function (t) { return r.dataset.search.indexOf(t) !== -1; });
    });
    if (sort) {
      list.sort(function (a, b) {
        var x = val(a, sort.key), y = val(b, sort.key);
        return (x < y ? -1 : x > y ? 1 : 0) * sort.dir;
      });
    }
    var fee = 0, paid = 0, bal = 0;
    var frag = document.createDocumentFragment();
    list.forEach(function (r, i) {
      r.style.display = ''; r.querySelector('.rn').textContent = i + 1;
      fee += parseFloat(r.dataset.fee) || 0; paid += parseFloat(r.dataset.paid) || 0; bal += parseFloat(r.dataset.bal) || 0;
      frag.appendChild(r);
    });
    rows.forEach(function (r) { if (list.indexOf(r) === -1) r.style.display = 'none'; });
    body.insertBefore(frag, empty);
    empty.style.display = list.length === 0 && rows.length ? '' : 'none';

    count.textContent = list.length === rows.length ? rows.length + ' students' : list.length + ' of ' + rows.length;
    document.getElementById('stFootN').textContent = 'Total (' + list.length + ')';
    document.getElementById('stFootFee').textContent = fmt(fee);
    document.getElementById('stFootPaid').textContent = fmt(paid);
    document.getElementById('stFootBal').textContent = fmt(bal);
    if (hint) hint.innerHTML = p.notes.length ? 'Showing: <b>' + p.notes.join(' · ') + '</b>' + (p.terms.length ? ' matching “' + p.terms.join(' ').replace(/</g, '&lt;') + '”' : '') : hintDefault;

    heads.forEach(function (h) {
      var on = sort && h.dataset.sort === sort.key, ic = h.querySelector('i');
      h.classList.toggle('on', !!on);
      ic.className = 'fas ' + (on ? (sort.dir === 1 ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort');
    });
  }

  heads.forEach(function (h) {
    h.addEventListener('click', function () {
      var key = h.dataset.sort;
      manual = { key: key, dir: manual && manual.key === key ? -manual.dir : (key === 'name' || key === 'course' || key === 'date' ? 1 : -1) };
      input.value = ''; render();
    });
  });
  input.addEventListener('input', render);

  // Manage modal
  var modalEl = document.getElementById('manageModal');
  document.querySelectorAll('.js-manage').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('mName').textContent = b.dataset.name;
      document.getElementById('mTutors').textContent = b.dataset.tutors || 'none yet';
      document.querySelectorAll('.mReg').forEach(function (i) { i.value = b.dataset.reg; });
      document.getElementById('mDetails').href = window.SA_DETAIL + b.dataset.reg;
      new bootstrap.Modal(modalEl).show();
    });
  });
  render();
})();
