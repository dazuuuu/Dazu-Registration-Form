/* Superadmin dashboard PIN: "View more" opens the PIN box, a correct PIN unlocks (page reloads with real figures). */
(function () {
  'use strict';
  var box = document.getElementById('saPin'), bg = document.getElementById('saPinBg');
  if (!box) return;
  var input = document.getElementById('saPinInput'), err = document.getElementById('saPinErr'), go = document.getElementById('saPinGo');

  function open() { box.hidden = false; bg.hidden = false; err.textContent = ''; input.value = ''; setTimeout(function () { input.focus(); }, 30); }
  function close() { box.hidden = true; bg.hidden = true; }
  function post(action, extra) {
    var body = new URLSearchParams(Object.assign({ action: action, csrf_token: box.dataset.csrf }, extra || {}));
    return fetch(box.dataset.url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected response.' }; }); });
  }

  function submit() {
    var pin = input.value.trim();
    if (!/^\d{4,8}$/.test(pin)) { err.textContent = 'Enter your 4 to 8 digit PIN.'; return; }
    go.disabled = true;
    post('verify', { pin: pin }).then(function (d) {
      if (d.ok) { location.reload(); return; }
      err.textContent = d.error || 'Wrong PIN.'; input.value = ''; input.focus(); go.disabled = false;
    }).catch(function () { err.textContent = 'Network problem. Try again.'; go.disabled = false; });
  }

  document.querySelectorAll('[data-sa-unlock]').forEach(function (b) { b.addEventListener('click', open); });
  go.addEventListener('click', submit);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter') submit(); });
  input.addEventListener('input', function () { input.value = input.value.replace(/\D/g, ''); err.textContent = ''; });
  document.getElementById('saPinCancel').addEventListener('click', close);
  bg.addEventListener('click', close);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

  var lock = document.getElementById('saLockBtn');
  if (lock) lock.addEventListener('click', function () { post('lock').then(function () { location.reload(); }); });

  // Blur again by itself when the unlock time runs out.
  var left = parseInt(box.dataset.left, 10) || 0;
  if (left > 0) setTimeout(function () { location.reload(); }, left * 1000 + 500);
})();
