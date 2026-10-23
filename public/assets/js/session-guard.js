// public/assets/js/session-guard.js — added to signed-in pages by app/v1/helpers/session_guard.php.
// Signs the user out after 30 minutes without activity, and when the last open tab of the site is closed.
(function () {
    var el = document.querySelector('script[data-session-guard]');
    if (!el) return;
    var cfg;
    try { cfg = JSON.parse(el.getAttribute('data-session-guard')); } catch (e) { return; }

    var idleMs = (cfg.idle || 1800) * 1000;
    var lastActive = Date.now();
    var lastPing = Date.now();
    var leaving = false;

    function post(action, beacon) {
        var body = new URLSearchParams({ action: action, page: cfg.page || '' });
        if (beacon && navigator.sendBeacon) return navigator.sendBeacon(cfg.ping, body);
        return fetch(cfg.ping, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true }).catch(function () {});
    }

    // Activity: reset the idle clock; tell the server at most once a minute.
    function active() {
        lastActive = Date.now();
        try { localStorage.setItem('dz_last_active', String(lastActive)); } catch (e) {}
        if (lastActive - lastPing > 60000) { lastPing = lastActive; post('ping'); }
    }
    // Tell the server this page is open. It cancels a "tab closed" report from the page before, also when the new
    // page belongs to another session (a portal preview opened in the same window keeps the admin signed in).
    post('ping');

    ['mousedown', 'keydown', 'scroll', 'touchstart', 'pointermove'].forEach(function (ev) {
        window.addEventListener(ev, active, { passive: true });
    });

    setInterval(function () {
        var shared = 0;
        try { shared = parseInt(localStorage.getItem('dz_last_active') || '0', 10); } catch (e) {}
        var last = Math.max(lastActive, shared);   // activity in any open tab keeps every tab signed in
        if (Date.now() - last >= idleMs && !leaving) {
            leaving = true;
            window.location.href = cfg.logout;
        }
    }, 5000);

    // Open-tab registry: each tab beats every 2s; when the last one goes away the server is told.
    var tabId = Math.random().toString(36).slice(2);
    function tabs() { try { return JSON.parse(localStorage.getItem('dz_tabs') || '{}'); } catch (e) { return {}; } }
    function saveTabs(t) { try { localStorage.setItem('dz_tabs', JSON.stringify(t)); } catch (e) {} }
    function beat() { var t = tabs(); t[tabId] = Date.now(); saveTabs(t); }
    beat();
    setInterval(beat, 2000);

    // Coming back to the page (switching back to the app, or a page restored from the back/forward cache):
    // tell the server at once so a "closed" report from when it went to the background is cancelled.
    window.addEventListener('pageshow', function (e) { if (e.persisted) post('ping'); });
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') { beat(); post('ping'); } });

    // In the installed app, phones fire pagehide just for switching to another app, which would sign the user out.
    // There only the idle limit applies.
    var installedApp = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;

    window.addEventListener('pagehide', function () {
        if (installedApp) return;
        var t = tabs(), now = Date.now(), others = 0;
        delete t[tabId];
        Object.keys(t).forEach(function (id) { if (now - t[id] < 6000) others++; else delete t[id]; });
        saveTabs(t);
        // A reload or click to the next page reaches the server within seconds and cancels this.
        if (!others && !leaving) post('close', true);
    });
})();
