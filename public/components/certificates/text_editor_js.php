<script>
// Counters (amber 20 characters before the limit; typing stops at the limit), live preview of the real certificate
// (the form is sent with action=preview; nothing is saved), and an "Unsaved changes" warning until Save.
(function () {
  var form = document.getElementById('tf'), pv = document.getElementById('pv'), wrap = document.getElementById('wrap'), t, dirty = false;
  function fit() { var s = wrap.clientWidth / 1123; pv.style.transform = 'scale(' + s + ')'; wrap.style.height = (794 * s) + 'px'; }
  window.addEventListener('resize', fit); fit();
  function counters() {
    document.querySelectorAll('.count[data-for]').forEach(function (c) {
      var ta = document.getElementById(c.dataset.for), max = +ta.dataset.max, n = ta.value.length;
      c.textContent = n + ' / ' + max; c.className = 'count' + (n >= max ? ' full' : n >= max - 20 ? ' amber' : '');
    });
  }
  function preview() {
    var fd = new FormData(form); fd.set('action', 'preview');
    fetch(form.getAttribute('action') || location.href, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.text(); }).then(function (h) { pv.srcdoc = h; });
  }
  form.addEventListener('input', function (ev) {
    var ta = ev.target;
    if (ta.dataset && ta.dataset.max && ta.value.length > +ta.dataset.max) ta.value = ta.value.slice(0, +ta.dataset.max);
    if (ta.id === 'extra') { var l = ta.value.split('\n'); if (l.length > 2) ta.value = l.slice(0, 2).join('\n'); }
    counters(); dirty = true; var d = document.getElementById('dirty'); if (d) d.hidden = false;
    clearTimeout(t); t = setTimeout(preview, 350);
  });
  form.addEventListener('change', function () { dirty = true; var d = document.getElementById('dirty'); if (d) d.hidden = false; clearTimeout(t); t = setTimeout(preview, 150); });
  form.addEventListener('click', function (ev) {      // "Reset to default": the automatic sentence comes back (nothing is saved until Save)
    var a = ev.target.closest && ev.target.closest('[data-reset]'); if (!a) return;
    ev.preventDefault(); var ta = document.getElementById(a.dataset.reset); if (!ta) return;
    ta.value = ta.dataset.default || ''; ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus();
  });
  form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  counters(); preview();
})();
</script>
