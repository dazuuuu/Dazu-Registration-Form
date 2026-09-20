/* Superadmin client script: PWA install + service worker, "More" sheet, mobile-friendly tables. */
(function () {
  'use strict';

  // --- service worker ---
  if ('serviceWorker' in navigator && window.SA_SW) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(window.SA_SW.sw, { scope: window.SA_SW.scope }).catch(function () {});
    });
  }

  // --- install app ---
  // The browser only offers a one-tap install (beforeinstallprompt) on HTTPS in Chrome/Edge/Android. Everywhere else
  // the button explains how to install, so it is always visible unless the app is already installed.
  var deferred = null, installBtn = document.getElementById('saInstall');
  var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  var installers = Array.prototype.slice.call(document.querySelectorAll('#saInstall, [data-sa-install]'));
  function hideInstall() { installers.forEach(function (b) { b.hidden = true; b.style.display = 'none'; }); }
  if (standalone) hideInstall();

  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; });
  window.addEventListener('appinstalled', hideInstall);

  var help = document.getElementById('saHelp'), helpBg = document.getElementById('saHelpBg'), helpBody = document.getElementById('saHelpBody');
  function helpText() {
    var ua = navigator.userAgent || '';
    if (!window.isSecureContext) return 'Installing needs a secure (HTTPS) connection. Open this site with https:// and try again.';
    if (/iphone|ipad|ipod/i.test(ua)) return 'Tap the Share button in Safari, then choose "Add to Home Screen".';
    if (/android/i.test(ua)) return 'Tap the ⋮ menu in Chrome, then choose "Install app" (or "Add to Home screen").';
    return 'In Chrome or Edge, click the install icon at the right of the address bar, or open the ⋮ menu and choose "Install DazuHub Admin".';
  }
  function showHelp(open) { if (help) { help.hidden = !open; helpBg.hidden = !open; } }
  function onInstall(e) {
    if (e) e.preventDefault();
    if (deferred) {
      deferred.prompt();
      deferred.userChoice.finally(function () { deferred = null; });
    } else {
      helpBody.textContent = helpText();
      showHelp(true);
    }
  }
  installers.forEach(function (b) { b.addEventListener('click', onInstall); });
  var hc = document.getElementById('saHelpClose'); if (hc) hc.addEventListener('click', function () { showHelp(false); });
  if (helpBg) helpBg.addEventListener('click', function () { showHelp(false); });

  // --- "More" sheet ---
  var btn = document.getElementById('saMoreBtn'), sheet = document.getElementById('saSheet'), bg = document.getElementById('saSheetBg');
  function setSheet(open) {
    if (!sheet) return;
    sheet.hidden = !open; bg.hidden = !open;
    document.body.classList.toggle('sa-sheet-open', open);
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (btn) btn.addEventListener('click', function () { setSheet(sheet.hidden); });
  if (bg) bg.addEventListener('click', function () { setSheet(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setSheet(false); });
  if (sheet) sheet.addEventListener('click', function (e) { if (e.target.closest('a')) setSheet(false); });

  // --- tables scroll sideways on phones instead of breaking the layout ---
  document.querySelectorAll('.sa-content table').forEach(function (t) {
    if (t.closest('.table-responsive, .xl-scroll, .assign-sheet')) return;
    var w = document.createElement('div');
    w.className = 'table-responsive';
    t.parentNode.insertBefore(w, t);
    w.appendChild(t);
  });
})();
