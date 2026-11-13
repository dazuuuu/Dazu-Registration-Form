/* Self-study Students page: filter chips, the small panel of one student, and the reset buttons.
   Nothing here is trusted: every call is checked again on the server (students_api.php). */
(function () {
    'use strict';
    var el = document.getElementById('stsCfg'); if (!el) return;
    var cfg = JSON.parse(el.textContent);
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function api(action, data) {
        data = data || {}; data.action = action; data.course = cfg.course; data.t = cfg.csrf;
        return fetch(cfg.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Something went wrong.' }; }); })
            .catch(function () { return { ok: false, error: 'No connection. Nothing was changed; try again in a moment.' }; });
    }
    var msg = $('#stsMsg');
    function say(t, ok) { if (!msg) return; msg.hidden = !t; msg.textContent = t || ''; msg.className = 'sts-msg ' + (ok ? 'ok' : 'bad'); if (t) msg.scrollIntoView({ block: 'nearest' }); }
    function when(ts) { return ts ? new Date(ts * 1000).toLocaleString('en-GB', { timeZone: 'Africa/Nairobi', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''; }

    /* ---- filter chips ---- */
    var chips = $$('.sts-chips button'), rows = $$('.sts-row'), none = $('#stsNone');
    chips.forEach(function (b) {
        b.addEventListener('click', function () {
            var f = b.dataset.f, shown = 0;
            chips.forEach(function (x) { x.classList.toggle('on', x === b); });
            rows.forEach(function (r) { var ok = f === 'all' || r.dataset.st === f; r.hidden = !ok; if (ok) shown++; });
            if (none) none.hidden = shown > 0;
        });
    });

    /* ---- the student panel ---- */
    var sheet = $('#stsSheet'), panel = $('#stsPanel'), current = 0;
    function close() { sheet.hidden = true; document.body.classList.remove('sts-open'); }
    sheet.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !sheet.hidden) close(); });
    function quizLine(q) {
        if (q.passed) return '<span class="ok">Passed' + (q.best != null ? ' ' + q.best + '%' : '') + '</span>';
        if (!q.open) return '<span class="mut">Not open yet</span>';
        return (q.best != null ? 'Best ' + q.best + '% · ' : 'Not tried · ') + q.week_used + ' of ' + q.week_limit + ' tries this week';
    }
    function render(d) {
        var h = '<div class="sts-ph"><h3 id="stsPT">' + esc(d.student.name) + '</h3><span class="ssa-chip">' + d.student.percent + '% done</span></div>';
        h += '<h5>Modules and quizzes</h5>';
        h += d.modules.map(function (m) { return '<div class="sts-pm"><div><b>' + esc(m.title) + '</b><small>' + m.topics_done + ' of ' + m.topics + ' topics done</small></div><div class="q"><b>' + esc(m.quiz.label) + '</b><small>' + quizLine(m.quiz) + '</small></div></div>'; }).join('') || '<p class="mut">No modules yet.</p>';
        h += '<h5>Exams</h5>' + d.exams.map(function (x) {
            var s = x.passed ? '<span class="ok">Passed' + (x.best != null ? ' ' + x.best + '%' : '') + '</span>' : (!x.open ? '<span class="mut">Not open yet</span>' : (x.best != null ? 'Best ' + x.best + '% · ' : 'Not tried · ') + x.day_used + ' of ' + x.day_limit + ' tries today · ' + x.month_used + ' of ' + x.month_limit + ' this month');
            return '<div class="sts-pm"><div><b>' + esc(x.label) + '</b></div><div class="q"><small>' + s + '</small></div></div>';
        }).join('');
        h += '<h5>Long answers waiting to be marked</h5>';
        h += d.long.length ? d.long.map(function (a) { return '<div class="sts-la"><b>' + esc(a.question) + '</b><p>' + esc(a.answer) + '</p></div>'; }).join('') + '<a class="sst-btn" href="' + esc(cfg.questionsUrl) + '">Write the comments</a>' : '<p class="mut">None waiting.</p>';
        h += '<h5>Resets</h5>';
        h += d.history.length ? d.history.map(function (r) { return '<div class="sts-hs"><b>' + esc(r.item) + '</b> · ' + esc(when(r.when)) + '<small>By ' + esc(r.by) + ' · cleared ' + r.cleared + (r.cleared === 1 ? ' try' : ' tries') + '</small><small>Why: ' + esc(r.reason) + (r.note ? ' · Note: ' + esc(r.note) : '') + '</small></div>'; }).join('') : '<p class="mut">No reset yet.</p>';
        h += '<h5>Reset a quiz or exam</h5><form id="stsSelf" class="sts-self"><p class="sst-hint">For a student who asks in person. It clears only the tries counted in the current ' + 'day, week or month for that one item, never marks or what they passed. It is saved with your name and the reason.' + (cfg.isAdmin ? '' : ' Limits: ' + cfg.settings.reset_quiz_month + ' a month for module quizzes, ' + cfg.settings.reset_exam_month + ' for exams, per student.') + '</p>'
            + '<select class="sst-in" name="item">' + cfg.items.map(function (i) { return '<option value="' + esc(i.key) + '" data-kind="' + esc(i.kind) + '">' + esc(i.label) + '</option>'; }).join('') + '</select>'
            + '<input class="sst-in" name="reason" maxlength="200" placeholder="Why is the reset needed? (required)"><input class="sst-in" name="note" maxlength="300" placeholder="Note (optional)"><div class="sst-err" id="stsSelfErr" role="alert"></div><button class="sst-btn sst-primary" type="submit">Reset</button></form>';
        panel.innerHTML = h;
    }
    function open(sid) {
        current = sid; sheet.hidden = false; document.body.classList.add('sts-open'); panel.innerHTML = '<p class="mut">Loading…</p>';
        api('student', { student: sid }).then(function (r) { if (!r.ok) { panel.innerHTML = '<div class="sst-err">' + esc(r.error) + '</div>'; return; } render(r); });
    }
    rows.forEach(function (r) {
        r.addEventListener('click', function () { open(parseInt(r.dataset.sid, 10)); });
        r.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(parseInt(r.dataset.sid, 10)); } });
    });
    panel.addEventListener('submit', function (e) {
        var f = e.target; if (f.id !== 'stsSelf') return; e.preventDefault();
        var opt = f.elements.item.options[f.elements.item.selectedIndex], key = opt.value, kind = opt.dataset.kind, mod = key.indexOf('module_quiz:') === 0 ? parseInt(key.split(':')[1], 10) : null;
        if (!window.confirm('Reset "' + opt.textContent + '" for this student?')) return;
        var btn = $('button[type=submit]', f); btn.disabled = true;
        api('reset', { student: current, kind: kind, module: mod, reason: f.elements.reason.value, note: f.elements.note.value }).then(function (r) {
            btn.disabled = false;
            if (!r.ok) { $('#stsSelfErr').textContent = r.error; return; }
            say('Reset done. ' + r.cleared + (r.cleared === 1 ? ' try was' : ' tries were') + ' cleared and the student was told.', true); close(); setTimeout(function () { location.reload(); }, 900);
        });
    });

    if (cfg.open && $$('.sts-row[data-sid="' + cfg.open + '"]').length) open(cfg.open);          // arriving from the Student Progress card

    /* ---- reset requests ---- */
    $$('.sts-rq').forEach(function (box) {
        box.addEventListener('click', function (e) {
            var b = e.target.closest('[data-do]'); if (!b) return; var id = parseInt(b.dataset.id, 10), what = b.dataset.do;
            if (what === 'notnow') { $('.nn', box).hidden = !$('.nn', box).hidden; return; }
            if (what === 'reset') {
                if (!window.confirm('Reset the tries of this quiz or exam for this student? Marks and anything they passed are not touched.')) return;
                b.disabled = true;
                api('reset', { request: id, note: '' }).then(function (r) {
                    if (!r.ok) { b.disabled = false; say(r.error, false); return; }
                    say('Reset done. ' + r.cleared + (r.cleared === 1 ? ' try was' : ' tries were') + ' cleared and the student was told.', true); setTimeout(function () { location.reload(); }, 900);
                });
            } else if (what === 'notnow-send') {
                b.disabled = true;
                api('notnow', { request: id, note: $('textarea', box).value }).then(function (r) {
                    if (!r.ok) { b.disabled = false; say(r.error, false); return; }
                    say('Done. The student was told.', true); setTimeout(function () { location.reload(); }, 800);
                });
            }
        });
    });
})();
