/* Dazu Intern Portal — small helpers on every intern page.
 *  - Live "working time" counters: <span data-live-since="UNIX_SECONDS">.
 *  - Clock in / out that also works offline: <form data-clock-form> posts to intern/attendance/clock.php; with no
 *    connection the action is saved on the phone (with the time the button was pressed) and sent when the internet returns.
 *  - First-time install screen on phones (#ipInstall); "Not now" hides it for 14 days.
 *  - Phone alerts switch in Settings: [data-push-off] removes this device's push subscription. */
(function () {
    var el = document.querySelector('script[data-clock]');
    var CLOCK = el ? el.getAttribute('data-clock') : '';
    var CSRF = el ? el.getAttribute('data-csrf') : '';
    var QKEY = 'dz_intern_clock_queue';

    // Live counters
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
        document.querySelectorAll('[data-live-since]').forEach(function (s) {
            var start = parseInt(s.getAttribute('data-live-since'), 10) * 1000;
            var base = parseInt(s.getAttribute('data-live-base') || '0', 10);
            var m = Math.max(0, Math.floor((Date.now() - start) / 60000)) + base;
            s.textContent = Math.floor(m / 60) + 'h ' + pad(m % 60) + 'm';
        });
    }
    tick(); setInterval(tick, 20000);

    // Offline clock queue
    function queue() { try { return JSON.parse(localStorage.getItem(QKEY) || '[]'); } catch (e) { return []; } }
    function saveQueue(q) { try { localStorage.setItem(QKEY, JSON.stringify(q)); } catch (e) {} }
    function send(item) {
        var body = new URLSearchParams({ action: item.action, at: String(item.at), csrf_token: CSRF, ajax: '1' });
        if (item.lat != null) { body.set('lat', item.lat); body.set('lng', item.lng); }
        return fetch(CLOCK, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); });
    }
    function flush() {
        var q = queue();
        if (!q.length || !navigator.onLine) return;
        var item = q[0];
        send(item).then(function (res) {
            saveQueue(queue().slice(1));
            showNote(res && res.ok ? 'Saved: ' + res.message : 'Not saved: ' + (res && res.error ? res.error : 'try again'), !(res && res.ok));
            if (res && res.ok) setTimeout(function () { location.reload(); }, 1200); else flush();
        }).catch(function () {});
    }
    function showNote(text, bad) {
        var n = document.getElementById('ipClockNote');
        if (!n) {
            n = document.createElement('div');
            n.id = 'ipClockNote';
            n.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);top:14px;z-index:2200;padding:10px 16px;border-radius:12px;font-size:.88rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.18)';
            document.body.appendChild(n);
        }
        n.style.background = bad ? '#fde8e6' : '#e7f4e1';
        n.style.color = bad ? '#9f2a20' : '#2f7a14';
        n.textContent = text;
        clearTimeout(n._t); n._t = setTimeout(function () { n.remove(); }, 5000);
    }
    window.addEventListener('online', flush);
    flush();
    if (queue().length) showNote('You have a saved clock action. It will be sent when you are back online.', false);

    document.querySelectorAll('form[data-clock-form]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = f.querySelector('button'); if (btn) btn.disabled = true;
            var item = { action: f.querySelector('[name=action]').value, at: Math.floor(Date.now() / 1000), lat: null, lng: null };
            function go() {
                if (!navigator.onLine) {
                    var q = queue(); q.push(item); saveQueue(q);
                    showNote('No internet. Your clock ' + item.action + ' at ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' is saved and will be sent automatically.', false);
                    if (btn) btn.disabled = false;
                    return;
                }
                send(item).then(function (res) {
                    showNote(res.ok ? res.message : res.error, !res.ok);
                    if (res.ok) setTimeout(function () { location.reload(); }, 900); else if (btn) btn.disabled = false;
                }).catch(function () {
                    var q = queue(); q.push(item); saveQueue(q);
                    showNote('The connection dropped. Your clock action is saved and will be sent automatically.', false);
                    if (btn) btn.disabled = false;
                });
            }
            if (f.hasAttribute('data-location') && navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function (p) { item.lat = p.coords.latitude.toFixed(6); item.lng = p.coords.longitude.toFixed(6); go(); }, go, { timeout: 8000, maximumAge: 60000 });
            } else go();
        });
    });

    // First-time install screen (phones only, not when already installed)
    var ipi = document.getElementById('ipInstall');
    var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
    var later = 0; try { later = parseInt(localStorage.getItem('dz_intern_install_later') || '0', 10); } catch (e) {}
    if (ipi && !standalone && window.innerWidth < 768 && Date.now() > later) ipi.hidden = false;
    if (ipi) {
        var hide = function (days) {
            try { localStorage.setItem('dz_intern_install_later', String(Date.now() + days * 86400000)); } catch (e) {}
            ipi.hidden = true;
        };
        ipi.querySelector('[data-ipi-later]').addEventListener('click', function () { hide(14); });
        ipi.querySelector('[data-ipi-install]').addEventListener('click', function () {
            // portal-pwa.js shows the browser's install dialog (or how-to steps); hide this screen once installed.
            window.addEventListener('appinstalled', function () { hide(3650); });
        });
    }

    // Settings: turn phone alerts off on this device
    document.querySelectorAll('[data-push-off]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!('serviceWorker' in navigator)) return;
            navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (sub) {
                if (!sub) { showNote('Phone alerts are already off on this device.', false); return; }
                var endpoint = sub.endpoint;
                return sub.unsubscribe().then(function () {
                    return fetch(b.getAttribute('data-push-off'), { method: 'POST', credentials: 'same-origin',
                        body: new URLSearchParams({ action: 'unsubscribe', endpoint: endpoint, csrf_token: CSRF }) });
                }).then(function () { showNote('Phone alerts are off on this device.', false); });
            }).catch(function () { showNote('Could not change phone alerts.', true); });
        });
    });
})();
