/* Self-study quiz / exam screens. The server keeps the clock and marks everything; this file only shows the countdown,
   moves between questions and saves each answer as the student goes. */
(function () {
    'use strict';
    var ask = document.getElementById('ssqAsk'), askForm = document.getElementById('ssqAskForm');
    if (ask && askForm) ask.addEventListener('click', function () { askForm.hidden = !askForm.hidden; if (!askForm.hidden) document.getElementById('ssqReason').focus(); });

    var el = document.getElementById('ssqCfg');
    if (!el) return;
    var cfg = JSON.parse(el.textContent);
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
    var blocks = $$('.qb'), at = 0, err = $('#ssqErr'), next = $('#ssqNext'), back = $('#ssqBack'), saved = $('#ssqSaved'), pending = 0, finishing = false;

    function post(body) {
        body.t = cfg.csrf; body.attempt = cfg.attempt;
        return fetch(cfg.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), keepalive: true })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Something went wrong.' }; }); })
            .catch(function () { return { ok: false, offline: true, error: 'No connection. Your answers are kept; try again in a moment.' }; });
    }
    function finished() { location.href = cfg.resultUrl; }

    /* ---- the countdown: the server's seconds are the truth; the page only counts down between checks ---- */
    if (cfg.secondsLeft !== null) {
        var left = cfg.secondsLeft, clock = $('#ssqClock'), timer = $('#ssqTimer'), t0 = Date.now(), base = left, submitted = false;
        var paint = function () {
            var s = Math.max(0, base - Math.floor((Date.now() - t0) / 1000));
            var m = Math.floor(s / 60), r = s % 60;
            if (clock) clock.textContent = (m < 10 ? '0' : '') + m + ':' + (r < 10 ? '0' : '') + r;
            if (timer) timer.classList.toggle('low', s <= 60);
            if (s <= 0 && !submitted) { submitted = true; finishing = true; post({ action: 'submit' }).then(finished); }
        };
        paint(); setInterval(paint, 500);
        var sync = function () {
            post({ action: 'sync' }).then(function (r) {
                if (!r || !r.ok) return;
                if (r.done) { finished(); return; }
                if (r.seconds_left !== null) { base = r.seconds_left; t0 = Date.now(); }
            });
        };
        setInterval(sync, 20000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) sync(); });
    }

    /* ---- one question at a time ---- */
    function show(i) {
        at = i;
        blocks.forEach(function (b, k) { b.hidden = k !== i; });
        $$('.ss-seg i').forEach(function (s, k) { s.classList.toggle('on', k <= i); });
        $('#ssqPill').textContent = 'Question ' + (i + 1) + ' of ' + blocks.length;
        var last = i === blocks.length - 1;
        next.textContent = last ? 'Submit' : 'Next question';
        back.hidden = i === 0;
        err.textContent = '';
    }
    function value(b) {
        var r = b.querySelector('input[type=radio]:checked'); if (r) return r.value;
        var t = b.querySelector('.ss-txt'); return t ? t.value : '';
    }
    function save(b) {
        var v = value(b); if (String(v).trim() === '') return Promise.resolve({ ok: true });
        pending++; if (saved) saved.textContent = 'Saving…';
        return post({ action: 'answer', question: parseInt(b.dataset.q, 10), answer: v }).then(function (r) {
            pending--;
            if (r.done) { finished(); return r; }
            if (saved) saved.textContent = r.ok ? 'Saved.' : (r.error || 'Not saved.');
            return r;
        });
    }
    blocks.forEach(function (b) {
        $$('input[type=radio]', b).forEach(function (r) { r.addEventListener('change', function () { save(b); }); });
        var t = $('.ss-txt', b);
        if (t) { t.addEventListener('change', function () { save(b); }); t.addEventListener('blur', function () { save(b); }); }
    });
    back.addEventListener('click', function () { save(blocks[at]).then(function () { if (at > 0) show(at - 1); }); });
    next.addEventListener('click', function () {
        var b = blocks[at];
        save(b).then(function () {
            if (at < blocks.length - 1) { show(at + 1); return; }
            var blank = blocks.filter(function (x) { return String(value(x)).trim() === ''; }).length;
            if (blank && !window.confirm(blank + ' question' + (blank === 1 ? ' is' : 's are') + ' not answered. Submit anyway?')) return;
            if (finishing) return;
            finishing = true; next.disabled = true;
            post({ action: 'submit' }).then(function (r) { if (r.ok || r.done) finished(); else { err.textContent = r.error || 'Something went wrong.'; next.disabled = false; finishing = false; } });
        });
    });
    show(0);
})();
