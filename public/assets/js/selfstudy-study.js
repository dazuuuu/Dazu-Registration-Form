/* Self-study study screen: outline, one-at-a-time topic questions, Knowledge Check, and the YouTube tracker.
   Nothing here decides a rule: the server checks every call (progress.php). This file only shows and reports. */
(function () {
    'use strict';
    var cfgEl = document.getElementById('ssCfg');
    var cfg = cfgEl ? JSON.parse(cfgEl.textContent) : {};
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

    function post(body, opts) {
        body.t = cfg.csrf; body.course = cfg.course;
        var json = JSON.stringify(body);
        if (opts && opts.beacon && navigator.sendBeacon) { return navigator.sendBeacon(cfg.endpoint, new Blob([json], { type: 'application/json' })); }
        return fetch(cfg.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: json, keepalive: !!(opts && opts.keepalive) })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Something went wrong. Please try again.' }; }); })
            .catch(function () { return { ok: false, error: 'No connection. Your progress is safe; try again in a moment.' }; });
    }

    /* ---------- outline: drawer, tabs, drop-downs, search ---------- */
    $$('[data-ss-drawer]').forEach(function (b) { b.addEventListener('click', function () { document.body.classList.toggle('ss-open'); }); });
    var ovl = $('.ss-ovl'); if (ovl) ovl.addEventListener('click', function () { document.body.classList.remove('ss-open'); });
    $$('.ss-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            $$('.ss-tab').forEach(function (x) { x.classList.toggle('on', x === t); x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
            $$('.ss-pane').forEach(function (p) { p.hidden = p.id !== t.dataset.pane; });
        });
    });
    $$('.ss-mod').forEach(function (m) {
        var h = $('.ss-modh', m);
        h.addEventListener('click', function () { if (m.classList.contains('locked')) return; m.classList.toggle('open'); h.setAttribute('aria-expanded', m.classList.contains('open') ? 'true' : 'false'); });
    });
    var q = $('#ssFind');
    if (q) q.addEventListener('input', function () {
        var term = q.value.trim().toLowerCase();
        $$('#ssOutline [data-t]').forEach(function (r) { r.hidden = term !== '' && r.dataset.t.indexOf(term) === -1; });
        $$('#ssOutline .ss-mod').forEach(function (m) {
            if (term === '') { m.hidden = false; return; }
            var own = m.dataset.t.indexOf(term) !== -1, kids = $$('.ss-mbody [data-t]', m).filter(function (r) { return !r.hidden; });
            m.hidden = !own && kids.length === 0;
            if (own) $$('.ss-mbody [data-t]', m).forEach(function (r) { r.hidden = false; });
            if (!m.hidden && !m.classList.contains('locked')) m.classList.add('open');
        });
    });

    /* ---------- topic questions: one at a time ---------- */
    var qs = $$('.ss-q [data-q]');
    var video = cfg.video || null;
    var videoDone = !video || video.completed;
    var submitBtn = $('#ssNext'), backBtn = $('#ssBack'), err = $('#ssErr');
    var answers = {}, at = 0;
    function show(i) {
        at = i;
        qs.forEach(function (el, k) { el.hidden = k !== i; });
        $$('.ss-seg i').forEach(function (s, k) { s.classList.toggle('on', k <= i); });
        var pill = $('#ssPill'); if (pill) pill.textContent = 'Question ' + (i + 1) + ' of ' + qs.length;
        var last = i === qs.length - 1;
        if (submitBtn) { submitBtn.textContent = last ? 'Submit' : 'Next question'; refresh(); }
        if (backBtn) backBtn.hidden = i === 0;
        if (err) err.textContent = '';
    }
    function current() { var el = qs[at]; return el ? el.dataset.q : null; }
    function refresh() {
        if (!submitBtn) return;
        var has = answers[current()] !== undefined && String(answers[current()]).trim() !== '';
        var last = at === qs.length - 1;
        submitBtn.disabled = !has || (last && !videoDone);
    }
    qs.forEach(function (el) {
        $$('input[type=radio]', el).forEach(function (r) { r.addEventListener('change', function () { answers[el.dataset.q] = r.value; refresh(); }); });
        var t = $('.ss-txt', el); if (t) t.addEventListener('input', function () { answers[el.dataset.q] = t.value; refresh(); });
    });
    if (backBtn) backBtn.addEventListener('click', function () { if (at > 0) show(at - 1); });
    if (submitBtn && qs.length) {
        show(0);
        submitBtn.addEventListener('click', function () {
            if (at < qs.length - 1) { show(at + 1); return; }
            submitBtn.disabled = true; err.textContent = '';
            if (cfg.preview) { err.style.color = '#8a5a00'; err.textContent = 'Preview: your answers are not saved or marked.'; setTimeout(function () { if (cfg.next) location.href = cfg.topicUrl.replace('{id}', cfg.next); else submitBtn.disabled = false; }, 900); return; }
            post({ action: 'submit', topic: cfg.topic, answers: answers }).then(function (r) {
                if (r.ok) { location.href = r.next ? cfg.topicUrl.replace('{id}', r.next) : location.href; return; }
                err.textContent = r.error || 'Something went wrong.'; refresh();
                if (r.locked) location.href = cfg.resumeUrl;
            });
        });
    }
    var doneBtn = $('#ssMarkDone');
    if (doneBtn) doneBtn.addEventListener('click', function () {
        doneBtn.disabled = true;
        if (cfg.preview) { if (cfg.next) location.href = cfg.topicUrl.replace('{id}', cfg.next); else doneBtn.disabled = false; return; }
        post({ action: 'done', topic: cfg.topic }).then(function (r) {
            if (r.ok) { location.href = r.next ? cfg.topicUrl.replace('{id}', r.next) : location.href; return; }
            var e2 = $('#ssErr2'); if (e2) e2.textContent = r.error || 'Something went wrong.'; doneBtn.disabled = false;
        });
    });

    /* ---------- Knowledge Check ---------- */
    var kbForm = $('#ssKb');
    if (kbForm) {
        var kbAns = {}, kbBtn = $('#ssKbSubmit'), kbErr = $('#ssKbErr');
        $$('input[type=radio]', kbForm).forEach(function (r) { r.addEventListener('change', function () { kbAns[r.name.replace('q', '')] = r.value; kbBtn.disabled = Object.keys(kbAns).length < $$('[data-kq]', kbForm).length; }); });
        kbBtn.disabled = true;
        kbBtn.addEventListener('click', function () {
            kbBtn.disabled = true;
            if (cfg.preview) { kbErr.textContent = 'Preview: your answers are not saved or marked.'; kbBtn.disabled = false; return; }
            post({ action: 'kb', answers: kbAns }).then(function (r) {
                if (r.ok) { location.href = cfg.kbUrl; return; }
                kbErr.textContent = r.error || 'Something went wrong.'; kbBtn.disabled = false;
            });
        });
    }

    /* ---------- the video: no skipping ahead, pauses when you leave, saved every few seconds ---------- */
    if (!video || video.completed || cfg.done) { markWatched(video && video.completed); return; }
    var status = $('#ssVStatus'), player = null, maxPos = video.furthest || 0, serverFurthest = video.furthest || 0, dur = video.duration || 0;
    var playing = false, timer = null, inView = true, sentAt = 0;

    function markWatched(ok) { if (ok) { videoDone = true; if (doneBtn) doneBtn.disabled = false; if (status) { status.className = 'ss-vs ok'; status.querySelector('span').innerHTML = '<b>Video watched.</b> You can answer the topic questions now.'; } refresh(); } }
    function paint(pct) {
        if (!status) return;
        status.className = 'ss-vs';
        status.querySelector('span').innerHTML = '<b>Watched ' + pct + '%.</b> Watch to the end to finish this video. Skipping ahead is switched off, and the video pauses if you leave this page.';
    }
    function tick(force, beacon) {
        if (!player || !player.getCurrentTime) return;
        var now = Date.now();
        if (!force && now - sentAt < 3500) return;
        sentAt = now;
        var d = player.getDuration ? Math.floor(player.getDuration()) : dur;
        if (d > 0) dur = d;
        var body = { action: 'video', topic: cfg.topic, pos: Math.floor(maxPos), dur: dur };
        if (beacon) { post(body, { beacon: true }); return; }
        post(body).then(function (r) {
            if (!r.ok) { if (r.locked) location.href = cfg.resumeUrl; return; }
            serverFurthest = r.furthest;
            if (maxPos > r.furthest + 3) { maxPos = r.furthest; try { player.seekTo(r.furthest, true); } catch (e) {} }   // the server only accepts what real time allows
            if (r.completed) { markWatched(true); stop(); } else { paint(r.percent); }
        });
    }
    function start() { if (!timer) timer = setInterval(function () { poll(); tick(false); }, 1000); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    function poll() {
        if (!player || !player.getCurrentTime || !playing) return;
        var t = player.getCurrentTime();
        try { if (player.getPlaybackRate() !== 1) player.setPlaybackRate(1); } catch (e) {}
        if (t > maxPos + 2) { player.seekTo(maxPos, true); return; }       // dragged ahead: jump back
        if (t > maxPos) maxPos = t;
    }
    function pause() { try { if (player && player.pauseVideo && playing) player.pauseVideo(); } catch (e) {} }
    function guard() { document.addEventListener('visibilitychange', function () { if (document.hidden) { pause(); tick(true, true); } }); window.addEventListener('blur', pause);
        window.addEventListener('pagehide', function () { tick(true, true); });
        if ('IntersectionObserver' in window) new IntersectionObserver(function (e) { inView = e[0].isIntersecting; if (!inView) pause(); }, { threshold: 0.4 }).observe($('.ss-video')); }
    window.onYouTubeIframeAPIReady = function () {
        player = new YT.Player('ssFrame', { events: {
            onReady: function () { try { var d = Math.floor(player.getDuration()); if (d > 0) dur = d; if (maxPos > 3) player.seekTo(maxPos, true); player.setPlaybackRate(1); } catch (e) {} },
            onStateChange: function (ev) {
                playing = ev.data === 1;
                if (playing) { if (!inView || document.hidden) { pause(); return; } start(); }
                else if (ev.data === 2 || ev.data === 0) { poll(); tick(true); }
            } } });
    };
    guard();
    var s = document.createElement('script'); s.src = 'https://www.youtube.com/iframe_api'; s.async = true; document.head.appendChild(s);
    paint(video.percent || 0);
})();
