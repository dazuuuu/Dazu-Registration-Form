/* Self-study "Who sees it" and "Sharing" tabs. This file only shows and collects the choices;
   the server decides who may be added or removed and refuses anything else (access_api.php). */
(function () {
    'use strict';
    var el = document.getElementById('ssaCfg'); if (!el) return;
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
    var msg = $('#ssaMsg');
    function say(text, ok) { if (!msg) return; msg.hidden = !text; msg.textContent = text || ''; msg.className = 'ssa-msg ' + (ok ? 'ok' : 'bad'); if (text) msg.scrollIntoView({ block: 'nearest' }); }
    var changed = false;
    window.addEventListener('beforeunload', function (e) { if (changed) { e.preventDefault(); e.returnValue = ''; } });

    /* ---------------- the students ---------------- */
    var groups = cfg.groups || [], sel = {}, mode = 'student', q = '', closed = {};
    groups.forEach(function (g) { g.students.forEach(function (s) { if (s.selected) sel[s.id] = true; }); });
    var initial = Object.keys(sel).length;
    function match(s) { return !q || (s.name + ' ' + (s.number || '')).toLowerCase().indexOf(q) !== -1; }
    function avatar(s) { return s.photo ? '<img class="ssa-av" src="' + esc(s.photo) + '" alt="" loading="lazy">' : '<span class="ssa-av ini">' + esc(s.initials) + '</span>'; }
    function renderGroups() {
        var box = $('#ssaGroups'); if (!box) return; var h = '';
        groups.forEach(function (g) {
            var shown = g.students.filter(match); if (!shown.length) return;
            var free = shown.filter(function (s) { return !s.locked; }), nSel = g.students.filter(function (s) { return sel[s.id]; }).length, freeSel = free.filter(function (s) { return sel[s.id]; }).length;
            var state = free.length && freeSel === free.length ? 'all' : (freeSel > 0 || (nSel > 0 && free.length === 0) ? 'some' : 'none'); if (!free.length && nSel === g.students.length) state = 'all';
            var open = !closed[g.id] && mode === 'student';
            h += '<div class="ssa-group' + (open ? ' open' : '') + '" data-gid="' + g.id + '"><div class="ssa-gh"><label class="ssa-cb ' + state + '"><input type="checkbox" data-gcheck="' + g.id + '"' + (state === 'all' ? ' checked' : '') + (free.length ? '' : ' disabled') + ' aria-label="Select the whole group ' + esc(g.title) + '"><span class="bx"><i class="fas fa-check"></i></span></label>'
                + '<button type="button" class="ssa-gt" data-gtoggle="' + g.id + '" aria-expanded="' + (open ? 'true' : 'false') + '"><b>' + esc(g.title) + '</b><span class="ssa-pill">' + nSel + ' of ' + g.students.length + ' selected</span><i class="fas fa-chevron-down"></i></button></div>';
            if (open) {
                h += '<div class="ssa-rows">';
                shown.forEach(function (s) {
                    h += '<label class="ssa-row' + (s.locked ? ' locked' : '') + '"><span class="ssa-cb ' + (sel[s.id] ? 'all' : 'none') + '"><input type="checkbox" data-sid="' + s.id + '"' + (sel[s.id] ? ' checked' : '') + (s.locked ? ' disabled' : '') + '><span class="bx"><i class="fas fa-check"></i></span></span>'
                        + avatar(s) + '<span class="ssa-nm">' + esc(s.name) + (s.by && (s.locked || cfg.caps.see_all) && !s.mine ? '<small>added by ' + esc(s.by) + (s.locked ? ' · only they can remove' : '') + '</small>' : '') + '</span><span class="ssa-no">' + esc(s.number || '') + '</span></label>';
                });
                h += '</div>';
            }
            h += '</div>';
        });
        box.innerHTML = h || '<p class="mut ssa-none">' + (q ? 'No student matches your search.' : (cfg.isAdmin ? 'There are no students yet.' : 'You have no students yet. Students are linked to you in My Students.')) + '</p>';
        $$('.ssa-cb.some input', box).forEach(function (i) { i.indeterminate = true; });
        renderWho();
    }
    function liveAudience() {
        var a = cfg.audience, by = {}, inPick = {}, i;
        (a.courses || []).forEach(function (c) { by[c.id] = { id: c.id, title: c.title, n: c.n }; });
        groups.forEach(function (g) {
            var active = 0, picked = 0; g.students.forEach(function (s) { if (s.selected) active++; if (sel[s.id]) picked++; });
            var hidden = (by[g.id] ? by[g.id].n : 0) - active; if (hidden < 0) hidden = 0;
            by[g.id] = { id: g.id, title: g.title, n: hidden + picked };
        });
        var list = Object.keys(by).map(function (k) { return by[k]; }).filter(function (c) { return c.n > 0; }).sort(function (x, y) { return x.title.localeCompare(y.title); });
        return { total: list.reduce(function (t, c) { return t + c.n; }, 0), courses: list };
    }
    function renderWho() {
        var box = $('#ssaWho'); if (!box) return; var a = liveAudience(), n = a.total;
        var h = '<div class="ssa-big">' + n + (n === 1 ? ' student' : ' students') + '</div>';
        h += '<p class="ssa-sub">from ' + a.courses.length + (a.courses.length === 1 ? ' course' : ' courses') + ' · ' + (cfg.status === 'published' ? 'they can see it now' : 'they see it as soon as you publish') + '</p>';
        h += '<div class="ssa-chips">' + a.courses.map(function (c) { return '<span class="ssa-chip">' + esc(c.title) + ' · ' + c.n + '</span>'; }).join('') + '</div>';
        if (cfg.caps.see_all && (cfg.audience.adders || []).length) h += '<ul class="ssa-adders">' + cfg.audience.adders.map(function (x) { return '<li>' + esc(x.name) + ' added ' + x.n + (x.n === 1 ? ' student' : ' students') + '</li>'; }).join('') + '</ul>';
        h += '<p class="sst-hint">Removing a student later hides the course from them but keeps their progress, in case you add them back.</p>';
        box.innerHTML = h;
        var pub = $('#sstPublishStudents'); if (pub) { pub.textContent = 'Publish to ' + n + (n === 1 ? ' student' : ' students'); pub.disabled = !(cfg.readyOther && n > 0); pub.title = pub.disabled ? (n > 0 ? 'Finish the other items in the checklist first (Details, Content, Questions).' : 'Choose at least one student.') : ''; }
    }
    function setSel(id, on) { if (on) sel[id] = true; else delete sel[id]; changed = true; }
    var pick = $('#ssaGroups');
    if (pick) {
        pick.addEventListener('change', function (e) {
            var t = e.target, g;
            if (t.dataset.sid) { setSel(parseInt(t.dataset.sid, 10), t.checked); renderGroups(); }
            else if (t.dataset.gcheck) { g = groups.filter(function (x) { return x.id === parseInt(t.dataset.gcheck, 10); })[0]; var free = g.students.filter(function (s) { return !s.locked && match(s); }); var allOn = free.every(function (s) { return sel[s.id]; }); free.forEach(function (s) { setSel(s.id, !allOn); }); renderGroups(); }
        });
        pick.addEventListener('click', function (e) { var b = e.target.closest('[data-gtoggle]'); if (!b) return; var id = b.dataset.gtoggle; if (mode === 'course') return; closed[id] = !closed[id]; renderGroups(); });
        $$('.ssa-switch button').forEach(function (b) { b.addEventListener('click', function () { mode = b.dataset.mode; $$('.ssa-switch button').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); $('#ssaGroups').classList.toggle('bycourse', mode === 'course'); renderGroups(); }); });
        $('#ssaQ').addEventListener('input', function (e) { q = e.target.value.trim().toLowerCase(); renderGroups(); });
        $('#ssaAll').addEventListener('click', function () { groups.forEach(function (g) { g.students.forEach(function (s) { if (!s.locked && match(s)) setSel(s.id, true); }); }); renderGroups(); });
        $('#ssaClear').addEventListener('click', function () { groups.forEach(function (g) { g.students.forEach(function (s) { if (!s.locked) setSel(s.id, false); }); }); renderGroups(); });
        var send = function (publish, btn) {
            say('', true); if (btn) btn.disabled = true;
            api('save_students', { ids: Object.keys(sel).map(Number), publish: publish ? 1 : 0 }).then(function (r) {
                if (btn) btn.disabled = false;
                if (!r.ok) { say(r.error || 'Not saved.', false); return; }
                changed = false;
                var t = 'Saved. ' + r.total + (r.total === 1 ? ' student has' : ' students have') + ' this course.' + (r.added ? ' ' + r.added + ' added.' : '') + (r.removed ? ' ' + r.removed + ' removed (their progress is kept).' : '');
                if (publish && r.published === false) { say(t + ' It was not published: ' + r.publish_error, false); return; }
                if (publish && r.published) t = 'Published to ' + r.total + (r.total === 1 ? ' student.' : ' students.') + (r.notified ? ' They were told.' : '');
                say(t, true); setTimeout(function () { location.reload(); }, 900);
            });
        };
        var sv = $('#sstSaveStudents'), pb = $('#sstPublishStudents');
        if (sv) sv.addEventListener('click', function () { send(false, sv); });
        if (pb) pb.addEventListener('click', function () { send(true, pb); });
        renderGroups();
    }

    /* ---------------- sharing with other tutors ---------------- */
    var share = $('#sstShare'), tutors = cfg.tutors || [];
    var LAB = { none: 'No access', view: 'Can view', edit: 'Can edit' };
    function renderShare() {
        if (!share) return;
        var can = cfg.caps.share, h = '<h4>Share with other tutors</h4><p class="sst-hint">Only the owner can edit unless they give <b>Can edit</b>. A tutor with <b>Can view</b> can open the course read-only, see how students are doing, and choose which of their <b>own</b> students see it. Every tutor with access can only add and remove their own students, and cannot change the sharing.</p>';
        if (!tutors.length) h += '<p class="mut">' + (cfg.caps.pick ? 'There are no other tutors.' : 'You cannot see the sharing of this course.') + '</p>';
        tutors.forEach(function (t) {
            h += '<div class="ssa-tut" data-tid="' + t.id + '"><span class="ssa-av ini big">' + esc(t.initials) + '</span><span class="ssa-tn"><b>' + esc(t.name) + '</b><small>' + esc(t.sub) + '</small></span>'
                + '<span class="ssa-seg3" role="radiogroup" aria-label="Access for ' + esc(t.name) + '">' + ['none', 'view', 'edit'].map(function (a) { return '<button type="button" role="radio" aria-checked="' + (t.access === a) + '" class="' + (t.access === a ? 'on ' + a : '') + '" data-a="' + a + '"' + (can ? '' : ' disabled') + '>' + LAB[a] + '</button>'; }).join('') + '</span></div>';
        });
        h += '<p class="sst-hint">' + (can ? 'If you change a tutor to No access, the students they added stay on the course, so students never lose their course by surprise. You can remove them in Who sees it.' : 'Only the owner and admin can change the sharing.') + ' Admin can always see every self-study course.</p>';
        share.innerHTML = h;
    }
    if (share) {
        share.addEventListener('click', function (e) {
            var b = e.target.closest('[data-a]'); if (!b || b.disabled) return; var row = b.closest('[data-tid]'), id = parseInt(row.dataset.tid, 10), a = b.dataset.a;
            var t = tutors.filter(function (x) { return x.id === id; })[0]; if (t.access === a) return;
            $$('button', row).forEach(function (x) { x.disabled = true; });
            api('set_editor', { tutor: id, access: a }).then(function (r) {
                if (r.ok) { t.access = a; say(t.name + ': ' + LAB[a] + (a === 'none' ? '. The students they added stay on the course.' : '. They were told.'), true); } else say(r.error || 'Not saved.', false);
                renderShare();
            });
        });
        renderShare();
    }
})();
