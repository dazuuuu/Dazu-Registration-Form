/* Dazu loader: one branded loading screen for the whole site.
 * - A full-screen loader (logo inside a turning green ring, a short message, a moving bar) while a page first opens,
 *   when a certificate, ID card, letter, transcript, receipt or other PDF/document link is clicked, and when a form is sent.
 * - Images fade in over a soft shimmer instead of popping in.
 * Include once per page:  <script src=".../assets/js/dazu-loader.js?v=1" defer data-logo=".../logo.png"></script>
 * Opt out on a link or form with data-no-loader. */
(function () {
  if (window.__dazuLoader) return;
  window.__dazuLoader = true;
  var me = document.currentScript;
  var logo = (me && me.getAttribute('data-logo')) || '';

  var css = ''
    + '#dz-load{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;flex-direction:column;'
    + 'background:rgba(248,250,246,.94);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s;font-family:Poppins,system-ui,sans-serif}'
    + '#dz-load.on{opacity:1;visibility:visible}'
    + '#dz-load .dz-ring{position:relative;width:96px;height:96px}'
    + '#dz-load .dz-ring:before,#dz-load .dz-ring:after{content:"";position:absolute;inset:0;border-radius:50%;border:3px solid transparent}'
    + '#dz-load .dz-ring:before{border-top-color:#44A024;border-right-color:#44A024;animation:dzspin 1s cubic-bezier(.6,.2,.4,.8) infinite}'
    + '#dz-load .dz-ring:after{inset:9px;border-bottom-color:#373435;border-left-color:#373435;animation:dzspin 1.4s linear infinite reverse;opacity:.7}'
    + '#dz-load .dz-logo{position:absolute;inset:22px;display:flex;align-items:center;justify-content:center;animation:dzpulse 1.6s ease-in-out infinite}'
    + '#dz-load .dz-logo img{max-width:100%;max-height:100%;object-fit:contain}'
    + '#dz-load .dz-logo b{font-weight:800;color:#44A024;font-size:20px;letter-spacing:.05em}'
    + '#dz-load .dz-msg{margin-top:18px;font-size:.9rem;font-weight:600;color:#373435;letter-spacing:.02em;text-align:center;padding:0 16px}'
    + '#dz-load .dz-sub{margin-top:4px;font-size:.72rem;color:#6b7067;letter-spacing:.14em;text-transform:uppercase}'
    + '#dz-load .dz-bar{margin-top:16px;width:160px;height:3px;border-radius:3px;background:#e3e8df;overflow:hidden}'
    + '#dz-load .dz-bar i{display:block;width:40%;height:100%;border-radius:3px;background:linear-gradient(90deg,#44A024,#7cc95c);animation:dzbar 1.2s ease-in-out infinite}'
    + '@keyframes dzspin{to{transform:rotate(360deg)}}'
    + '@keyframes dzpulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(.9);opacity:.75}}'
    + '@keyframes dzbar{0%{transform:translateX(-100%)}100%{transform:translateX(260%)}}'
    + 'img.dz-img{opacity:0;transition:opacity .45s ease}img.dz-img.dz-in{opacity:1}'
    + '.dz-shim{background:linear-gradient(100deg,#eef1eb 30%,#f8faf6 50%,#eef1eb 70%) 0 0/300% 100%;animation:dzshim 1.3s linear infinite}'
    + '@keyframes dzshim{to{background-position:-150% 0}}'
    + '@media (prefers-reduced-motion:reduce){#dz-load *,.dz-shim{animation-duration:3s!important}}'
    + '@media print{#dz-load{display:none!important}}';
  var st = document.createElement('style');
  st.textContent = css;
  (document.head || document.documentElement).appendChild(st);

  var box, msgEl, timer;
  function build() {
    if (box) return box;
    box = document.createElement('div');
    box.id = 'dz-load';
    box.setAttribute('role', 'status');
    box.setAttribute('aria-live', 'polite');
    box.innerHTML = '<div class="dz-ring"><div class="dz-logo">' + (logo ? '<img src="' + logo + '" alt="">' : '<b>DTI</b>')
      + '</div></div><div class="dz-msg"></div><div class="dz-sub">Dazu Training Institute</div><div class="dz-bar"><i></i></div>';
    msgEl = box.querySelector('.dz-msg');
    (document.body || document.documentElement).appendChild(box);
    return box;
  }
  function show(msg, maxMs) {
    build();
    msgEl.textContent = msg || 'Loading…';
    box.classList.add('on');
    clearTimeout(timer);
    timer = setTimeout(hide, maxMs || 15000);   // never stuck: a download does not leave the page
  }
  function hide() { clearTimeout(timer); if (box) box.classList.remove('on'); }
  window.DazuLoader = { show: show, hide: hide };

  // What a link opens, read from its address and text.
  var DOCS = [
    [/certificate/i, 'Preparing your certificate…'],
    [/id_card|id-card|idcard|\/ids?\b|staff_id|tutor_preview/i, 'Preparing the ID card…'],
    [/letter|recommend/i, 'Preparing the letter…'],
    [/transcript/i, 'Preparing the transcript…'],
    [/receipt/i, 'Preparing the receipt…'],
    [/timetable|calendar\/print|classes\/print/i, 'Preparing the timetable…'],
    [/registration_form|reg_form|form_pdf|\/forms?\//i, 'Preparing the form…'],
    [/verify/i, 'Checking the record…'],
    [/\.pdf\b|[?&](pdf|dl|download)=|download|print/i, 'Preparing the document…']
  ];
  function docMessage(a) {
    var s = (a.getAttribute('href') || '') + ' ' + (a.textContent || '');
    for (var i = 0; i < DOCS.length; i++) if (DOCS[i][0].test(s)) return DOCS[i][1];
    return null;
  }

  document.addEventListener('click', function (ev) {
    if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
    var a = ev.target.closest && ev.target.closest('a[href]');
    if (!a || a.hasAttribute('data-no-loader') || a.closest('[data-no-loader]')) return;
    var href = a.getAttribute('href') || '';
    if (/^(#|javascript:|mailto:|tel:|whatsapp:|sms:)/i.test(href) || a.hasAttribute('data-bs-toggle')) return;
    if (a.host && a.host !== location.host) return;
    var msg = docMessage(a);
    if (!msg) return;                       // ordinary links: the page-open loader covers them
    var newTab = a.target === '_blank';
    var dl = a.hasAttribute('download') || /[?&](dl|download)=/i.test(href);
    show(msg, newTab || dl ? 3500 : 20000);
  }, true);

  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (ev.defaultPrevented || f.hasAttribute('data-no-loader') || f.target === '_blank') return;
    setTimeout(function () {
      if (ev.defaultPrevented) return;      // a validation script stopped it
      var get = (f.method || 'get').toLowerCase() === 'get';
      show(get ? 'Searching…' : (f.querySelector('input[type=file]') && f.querySelector('input[type=file]').value ? 'Uploading…' : 'Saving…'), 20000);
    }, 0);
  });

  // Coming back with the Back button, or a download that kept the page: hide it.
  window.addEventListener('pageshow', hide);
  window.addEventListener('blur', function () { if (box && box.classList.contains('on')) setTimeout(hide, 1500); });

  // Images: shimmer while loading, then fade in.
  function dressImage(img) {
    if (img.dataset.dz || img.closest('#dz-load') || img.hasAttribute('data-no-loader')) return;
    img.dataset.dz = '1';
    if (img.complete && img.naturalWidth) return;
    img.classList.add('dz-img');
    var holder = img.parentElement;
    if (holder) holder.classList.add('dz-shim');
    function done() { img.classList.add('dz-in'); if (holder) holder.classList.remove('dz-shim'); }
    img.addEventListener('load', done, { once: true });
    img.addEventListener('error', done, { once: true });
  }
  function scan(root) { (root.querySelectorAll ? root.querySelectorAll('img') : []).forEach(dressImage); }

  // PDF previews inside a page (iframe / embed / object): loader on top until it has loaded.
  function dressFrame(fr) {
    if (fr.dataset.dz) return;
    fr.dataset.dz = '1';
    var src = fr.getAttribute('src') || fr.getAttribute('data') || '';
    if (!/pdf|certificate|id_card|letter|transcript|receipt|preview/i.test(src)) return;
    var p = fr.parentElement;
    if (!p) return;
    if (getComputedStyle(p).position === 'static') p.style.position = 'relative';
    var cover = document.createElement('div');
    cover.className = 'dz-shim';
    cover.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;border-radius:inherit;font:600 .85rem Poppins,system-ui,sans-serif;color:#373435;z-index:2';
    cover.innerHTML = '<span style="display:inline-flex;align-items:center;gap:10px"><span style="width:22px;height:22px;border-radius:50%;border:3px solid #cfe6c4;border-top-color:#44A024;animation:dzspin .9s linear infinite"></span>Loading document…</span>';
    p.appendChild(cover);
    var off = function () { cover.remove(); };
    fr.addEventListener('load', off, { once: true });
    setTimeout(off, 12000);
  }

  function start() {
    scan(document);
    document.querySelectorAll('iframe,embed,object').forEach(dressFrame);
    if ('MutationObserver' in window) {
      new MutationObserver(function (ms) {
        ms.forEach(function (m) {
          m.addedNodes.forEach(function (n) {
            if (n.nodeType !== 1) return;
            if (n.tagName === 'IMG') dressImage(n); else scan(n);
          });
        });
      }).observe(document.body, { childList: true, subtree: true });
    }
  }

  // Page-open loader: only when the page is slow (over 350 ms), so fast pages never flash.
  if (document.readyState !== 'complete') {
    var slow = setTimeout(function () { show('Loading…', 15000); }, 350);
    window.addEventListener('load', function () { clearTimeout(slow); hide(); }, { once: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
