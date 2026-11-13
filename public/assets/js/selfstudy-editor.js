/* Self-study tutor editor: Content tab (outline, text editor, video, files) and Questions tab (question banks, answers to mark).
   Nothing here is trusted: the server checks the permission, cleans the text with an allow-list and validates every question again. */
(function () {
    'use strict';
    var cfgEl = document.getElementById('sscCfg'); if (!cfgEl) return;
    var cfg = JSON.parse(cfgEl.textContent), RO = !!cfg.readonly, MODE = cfg.mode;
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    var outline = cfg.outline, cur = null, ver = 0, dirty = false, saving = false, timer = null, savedAt = null, conflict = false, selTopic = 0, savedRange = null, qState = {};

    /* ---------------- the server ---------------- */
    function api(action, data) {
        var body, headers = {};
        if (data instanceof FormData) { data.append('action', action); data.append('course', cfg.course); data.append('t', cfg.csrf); body = data; }
        else { data = data || {}; data.action = action; data.course = cfg.course; data.t = cfg.csrf; body = JSON.stringify(data); headers['Content-Type'] = 'application/json'; }
        return fetch(cfg.endpoint, { method: 'POST', credentials: 'same-origin', headers: headers, body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Something went wrong.' }; }); })
            .catch(function () { return { ok: false, error: 'No connection. Your changes are kept on this page; try again in a moment.' }; });
    }
    function setOutline(o) { if (o) { outline = o; renderTree(); } }
    function ago(ts) {
        var s = Math.max(0, Math.floor(Date.now() / 1000 - ts));
        if (s < 10) return 'just now'; if (s < 60) return s + ' seconds ago';
        var m = Math.floor(s / 60); if (m < 60) return m + (m === 1 ? ' minute ago' : ' minutes ago');
        var h = Math.floor(m / 60); if (h < 24) return h + (h === 1 ? ' hour ago' : ' hours ago');
        var d = Math.floor(h / 24); return d + (d === 1 ? ' day ago' : ' days ago');
    }
    function paintSaved() { var el = $('#sstSavedAt'); if (!el) return; el.textContent = conflict ? 'Not saved: someone else changed this topic' : (dirty ? 'Unsaved changes…' : (savedAt ? 'Saved ' + ago(savedAt) : '')); el.classList.toggle('bad', conflict); }
    setInterval(paintSaved, 20000);
    window.addEventListener('beforeunload', function (e) { if (dirty && !RO) { e.preventDefault(); e.returnValue = ''; } });
    var otog = $('#sscOToggle'); if (otog) otog.addEventListener('click', function () { var o = $('#sscOutline'); o.classList.toggle('open'); otog.setAttribute('aria-expanded', o.classList.contains('open') ? 'true' : 'false'); });

    /* ---------------- the outline ---------------- */
    function qText(n) { return n > 0 ? n + (n === 1 ? ' question' : ' questions') : 'no questions yet'; }
    function bankInfo(b) {
        var warn = b.need && b.usable < b.need; var t = b.need ? (b.count ? b.usable + ' / ' + b.need : 'no questions yet') : qText(b.count);
        return '<span class="ssc-cnt' + (warn ? ' warn' : '') + '">' + esc(t) + '</span>';
    }
    function bankLink(key) { return MODE === 'questions' ? 'data-bank="' + key + '"' : 'href="' + esc(cfg.questionsUrl) + '&bank=' + key + '"'; }
    function mini(act, id, kind, icon, title, extra) { return '<button type="button" class="ssc-mini' + (extra ? ' ' + extra : '') + '" data-act="' + act + '" data-id="' + id + '" data-kind="' + kind + '" title="' + esc(title) + '" aria-label="' + esc(title) + '"><i class="fas ' + icon + '"></i></button>'; }
    function tools(kind, it, canUp, canDown) {
        if (RO || MODE === 'questions') return '';
        return '<span class="ssc-tools-i">' + mini('up', it.id, kind, 'fa-chevron-up', 'Move up', canUp ? '' : 'off') + mini('down', it.id, kind, 'fa-chevron-down', 'Move down', canDown ? '' : 'off') + mini('rename', it.id, kind, 'fa-pen', 'Rename')
            + (it.progress ? mini('archive', it.id, kind, 'fa-box-archive', 'Archive (students have started it)') : mini('delete', it.id, kind, 'fa-trash', 'Delete')) + '</span>';
    }
    function topicRow(t, k, last, mid, first) {
        var sel = MODE === 'content' ? t.id === selTopic : qState.bank === 'topic:' + t.id;
        var attr = MODE === 'content' ? 'data-topic="' + t.id + '"' : 'data-bank="topic:' + t.id + '"';
        return '<div class="ssc-top' + (sel ? ' sel' : '') + '" ' + attr + ' data-tid="' + t.id + '" data-mid="' + mid + '"' + (RO || MODE === 'questions' ? '' : ' draggable="true"') + '><i class="fas fa-grip-vertical ssc-grip" aria-hidden="true"></i><i class="fas fa-file-lines ssc-ic"></i>'
            + '<span class="ssc-t">' + esc(t.number + ' ' + t.title) + '</span>' + (sel && MODE === 'content' ? '<span class="ssc-edit">editing</span>' : '<span class="ssc-cnt">' + (t.questions ? qText(t.questions) : '') + '</span>') + tools('topic', t, !first, !last) + '</div>';
    }
    function renderTree() {
        var o = outline, h = '';
        h += '<a class="ssc-row" ' + bankLink('kb') + '><i class="fas fa-clipboard-check ssc-ic g"></i><span class="ssc-t">Knowledge Check</span>' + bankInfo(o.kb) + '</a>';
        if (o.intro) h += '<div class="ssc-row ssc-top' + ((MODE === 'content' ? o.intro.id === selTopic : qState.bank === 'topic:' + o.intro.id) ? ' sel' : '') + '" ' + (MODE === 'content' ? 'data-topic="' + o.intro.id + '"' : 'data-bank="topic:' + o.intro.id + '"') + '><i class="fas fa-file-lines ssc-ic g"></i><span class="ssc-t">Course Introduction</span><span class="ssc-cnt">' + (o.intro.questions ? qText(o.intro.questions) : '') + '</span></div>';
        else if (MODE === 'content') h += '<div class="ssc-row ssc-top" data-intro="1"><i class="fas fa-file-lines ssc-ic g"></i><span class="ssc-t">Course Introduction</span><span class="ssc-cnt">' + (RO ? 'not written' : 'click to write') + '</span></div>';
        o.modules.forEach(function (m, mi) {
            h += '<div class="ssc-mod" data-mid="' + m.id + '"><div class="ssc-modh" data-modh="' + m.id + '"' + (RO || MODE === 'questions' ? '' : ' draggable="true"') + '><i class="fas fa-grip-vertical ssc-grip" aria-hidden="true"></i><i class="fas fa-book ssc-ic g"></i><span class="ssc-t b">' + esc(m.title) + '</span><span class="ssc-cnt">' + m.topics.length + (m.topics.length === 1 ? ' topic' : ' topics') + '</span>' + tools('module', m, mi > 0, mi < o.modules.length - 1) + '</div>';
            m.topics.forEach(function (t, ti) { h += topicRow(t, ti, ti === m.topics.length - 1, m.id, ti === 0); });
            h += '<a class="ssc-row ssc-quiz" ' + bankLink('module:' + m.id) + '><i class="fas fa-clipboard-check ssc-ic g"></i><span class="ssc-t">Module ' + m.no + ' quiz</span>' + bankInfo(m.quiz) + '</a></div>';
        });
        h += '<a class="ssc-row" ' + bankLink('checkpoint') + '><i class="fas fa-clipboard-check ssc-ic g"></i><span class="ssc-t b">Checkpoint Exam</span>' + bankInfo(o.checkpoint) + '</a>';
        h += '<a class="ssc-row" ' + bankLink('final') + '><i class="fas fa-clipboard-check ssc-ic g"></i><span class="ssc-t b">Final Exam</span>' + bankInfo(o.final) + '</a>';
        $('#sscTree').innerHTML = h;
        var arch = $('#sscArchived');
        if (arch && MODE === 'content') {
            var a = o.archived.modules.map(function (m) { return '<div class="ssc-arow"><span>' + esc(m.title) + ' <small>module</small></span>' + (RO ? '' : '<button type="button" class="sst-btn" data-act="restore" data-kind="module" data-id="' + m.id + '">Restore</button>') + '</div>'; })
                .concat(o.archived.topics.map(function (t) { return '<div class="ssc-arow"><span>' + esc(t.title) + ' <small>topic in ' + esc(t.module) + '</small></span>' + (RO ? '' : '<button type="button" class="sst-btn" data-act="restore" data-kind="topic" data-id="' + t.id + '">Restore</button>') + '</div>'; }));
            arch.innerHTML = a.length ? '<details class="ssc-arch"><summary>Archived (' + a.length + ')</summary>' + a.join('') + '</details>' : '';
        }
    }

    function ask(opts, ok) {                                    // a small inline form instead of a pop-up box (easy to use on a phone)
        var box = $('#sscTree'), old = $('#sscAsk'); if (old) old.remove();
        var f = document.createElement('form'); f.id = 'sscAsk'; f.className = 'ssc-ask';
        f.innerHTML = '<label>' + esc(opts.label) + '</label>' + (opts.modules ? '<select class="sst-in">' + opts.modules.map(function (m) { return '<option value="' + m.id + '"' + (m.id === opts.module ? ' selected' : '') + '>' + esc(m.title) + '</option>'; }).join('') + '</select>' : '')
            + '<input class="sst-in" type="text" maxlength="200" value="' + esc(opts.value || '') + '"><div class="sst-err"></div><div class="ssc-arow2"><button class="sst-btn sst-primary" type="submit">' + esc(opts.ok || 'Add') + '</button><button class="sst-btn" type="button" data-x="1">Cancel</button></div>';
        box.parentNode.insertBefore(f, box);
        var inp = $('input', f); inp.focus(); inp.select();
        f.addEventListener('submit', function (e) { e.preventDefault(); var sel = $('select', f); ok(inp.value.trim(), sel ? parseInt(sel.value, 10) : null, function (err) { $('.sst-err', f).textContent = err; }, function () { f.remove(); }); });
        $('[data-x]', f).addEventListener('click', function () { f.remove(); });
    }
    function swap(arr, i, j) { var t = arr[i]; arr[i] = arr[j]; arr[j] = t; }
    function doReorder(kind, ids, mod) {
        return api('reorder', { kind: kind, ids: ids, module: mod }).then(function (r) { if (r.ok) setOutline(r.outline); else alert(r.error || 'Could not reorder.'); });
    }
    function findTopic(id) { for (var i = 0; i < outline.modules.length; i++) for (var j = 0; j < outline.modules[i].topics.length; j++) if (outline.modules[i].topics[j].id === id) return { m: outline.modules[i], t: outline.modules[i].topics[j], i: j }; return null; }

    var tree = $('#sscTree');
    tree.addEventListener('click', function (e) {
        var b = e.target.closest('[data-act]');
        if (b) {
            e.preventDefault(); e.stopPropagation();
            var act = b.dataset.act, kind = b.dataset.kind, id = parseInt(b.dataset.id, 10);
            if (act === 'up' || act === 'down') {
                if (b.classList.contains('off')) return;
                if (kind === 'module') { var ids = outline.modules.map(function (m) { return m.id; }), i = ids.indexOf(id), j = act === 'up' ? i - 1 : i + 1; if (j < 0 || j >= ids.length) return; swap(ids, i, j); doReorder('module', ids); }
                else { var f = findTopic(id); if (!f) return; var tids = f.m.topics.map(function (t) { return t.id; }), k = tids.indexOf(id), l = act === 'up' ? k - 1 : k + 1; if (l < 0 || l >= tids.length) return; swap(tids, k, l); doReorder('topic', tids, f.m.id); }
            } else if (act === 'rename') {
                var cur0 = kind === 'module' ? outline.modules.filter(function (m) { return m.id === id; })[0] : findTopic(id).t;
                ask({ label: 'New name for this ' + kind, value: cur0.title, ok: 'Rename' }, function (v, _m, err, close) { api('rename', { kind: kind, id: id, title: v }).then(function (r) { if (!r.ok) return err(r.error); setOutline(r.outline); if (kind === 'topic' && id === selTopic && $('#sscTitle')) $('#sscTitle').value = v; close(); }); });
            } else if (act === 'archive' || act === 'delete') {
                var msg = act === 'archive' ? 'Archive this ' + kind + '? Students will no longer see it, but their progress is kept. You can restore it later.' : 'Delete this ' + kind + (kind === 'module' ? ' with its topics and questions' : ' and its questions') + '? This cannot be undone.';
                if (!window.confirm(msg)) return;
                api(act, { kind: kind, id: id, confirm: 1 }).then(function (r) { if (!r.ok) { alert(r.error); return; } if (kind === 'topic' && id === selTopic) closeTopic(); setOutline(r.outline); });
            } else if (act === 'restore') { api('restore', { kind: kind, id: id }).then(function (r) { if (!r.ok) alert(r.error); else setOutline(r.outline); }); }
            return;
        }
        var t = e.target.closest('[data-topic]'); if (t) { if (innerW() < 992) $('#sscOutline').classList.remove('open'); selectTopic(parseInt(t.dataset.topic, 10)); return; }
        var intro = e.target.closest('[data-intro]'); if (intro && !RO) { api('add_topic', { module: null }).then(function (r) { if (!r.ok) return alert(r.error); setOutline(r.outline); selectTopic(r.id); }); return; }
        var bk = e.target.closest('[data-bank]'); if (bk) { if (innerW() < 992) $('#sscOutline').classList.remove('open'); openBank(bk.dataset.bank, $('#sscQBox')); }
    });
    function innerW() { return window.innerWidth || 1200; }

    /* drag and drop (desktop); the arrows above do the same on a phone */
    var drag = null;
    tree.addEventListener('dragstart', function (e) { var t = e.target.closest('[data-tid]'), m = e.target.closest('[data-modh]'); if (t) drag = { kind: 'topic', id: parseInt(t.dataset.tid, 10) }; else if (m) drag = { kind: 'module', id: parseInt(m.dataset.modh, 10) }; else return; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', String(drag.id)); } catch (x) {} });
    tree.addEventListener('dragover', function (e) { if (!drag) return; var t = e.target.closest('[data-tid],[data-modh]'); if (!t) return; e.preventDefault(); $$('.over', tree).forEach(function (x) { x.classList.remove('over'); }); t.classList.add('over'); });
    tree.addEventListener('dragend', function () { drag = null; $$('.over', tree).forEach(function (x) { x.classList.remove('over'); }); });
    tree.addEventListener('drop', function (e) {
        if (!drag) return; e.preventDefault();
        var tt = e.target.closest('[data-tid]'), mm = e.target.closest('[data-modh]'), d = drag; drag = null; $$('.over', tree).forEach(function (x) { x.classList.remove('over'); });
        if (d.kind === 'module' && mm) {
            var ids = outline.modules.map(function (m) { return m.id; }).filter(function (x) { return x !== d.id; }), at = ids.indexOf(parseInt(mm.dataset.modh, 10)); if (parseInt(mm.dataset.modh, 10) === d.id) return;
            ids.splice(at < 0 ? ids.length : at, 0, d.id); doReorder('module', ids);
        } else if (d.kind === 'topic' && (tt || mm)) {
            var mid = tt ? parseInt(tt.dataset.mid, 10) : parseInt(mm.dataset.modh, 10), mod = outline.modules.filter(function (m) { return m.id === mid; })[0]; if (!mod) return;
            var tids = mod.topics.map(function (t) { return t.id; }).filter(function (x) { return x !== d.id; });
            var pos = tt ? tids.indexOf(parseInt(tt.dataset.tid, 10)) : tids.length; if (tt && parseInt(tt.dataset.tid, 10) === d.id) return;
            tids.splice(pos < 0 ? tids.length : pos, 0, d.id); doReorder('topic', tids, mid);
        }
    });

    var addM = $('#sscAddModule'), addT = $('#sscAddTopic');
    if (addM) addM.addEventListener('click', function () { ask({ label: 'Module name', ok: 'Add module' }, function (v, _m, err, close) { api('add_module', { title: v }).then(function (r) { if (!r.ok) return err(r.error); setOutline(r.outline); close(); }); }); });
    if (addT) addT.addEventListener('click', function () {
        if (!outline.modules.length) { alert('Add a module first, then add topics to it.'); return; }
        var f = selTopic ? findTopic(selTopic) : null;
        ask({ label: 'Topic name and module', ok: 'Add topic', modules: outline.modules, module: f ? f.m.id : outline.modules[0].id }, function (v, mid, err, close) { api('add_topic', { module: mid, title: v }).then(function (r) { if (!r.ok) return err(r.error); setOutline(r.outline); close(); selectTopic(r.id); }); });
    });

    /* ---------------- the topic editor ---------------- */
    var body = $('#sscBody'), title = $('#sscTitle'), video = $('#sscVideo'), vlen = $('#sscVLen'), thumb = $('#sscThumb'), verr = $('#sscVErr'), errBox = $('#sscErr');
    function markDirty() { if (RO || conflict) return; dirty = true; paintSaved(); clearTimeout(timer); timer = setTimeout(function () { save(false); }, 3500); }
    function ytId(u) { u = (u || '').trim(); var m = u.match(/^[A-Za-z0-9_-]{11}$/) ? [0, u] : u.match(/^https?:\/\/(?:www\.|m\.|music\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/|v\/)|youtube-nocookie\.com\/embed\/|youtu\.be\/)([A-Za-z0-9_-]{11})/i); return m ? m[1] : null; }
    function paintThumb() { var id = ytId(video.value); verr.textContent = video.value.trim() && !id ? 'Only YouTube links are accepted.' : ''; thumb.hidden = !id; if (id) thumb.src = 'https://i.ytimg.com/vi/' + id + '/mqdefault.jpg'; }
    function fillFiles(files) {
        $('#sscFiles').innerHTML = files.map(function (f) { return '<span class="ssc-file"><i class="fas fa-paperclip"></i><a href="' + esc(cfg.download + f.id) + '">' + esc(f.name) + '</a> · ' + Math.max(1, Math.round(f.size / 1024)) + ' KB <small>Shows in the Resources tab</small>' + (RO ? '' : '<button type="button" data-rmfile="' + f.id + '" aria-label="Remove ' + esc(f.name) + '">&times;</button>') + '</span>'; }).join('');
    }
    function closeTopic() { cur = null; selTopic = 0; $('#sscEditor').hidden = true; $('#sscNone').hidden = false; $('#sscQBox').hidden = true; dirty = false; paintSaved(); renderTree(); }
    function selectTopic(id) {
        if (id === selTopic && cur) return;
        var go = function () {
            api('load_topic', { id: id }).then(function (r) {
                if (!r.ok) { alert(r.error); return; }
                var t = r.topic; cur = t; selTopic = t.id; ver = t.version; conflict = false; dirty = false; savedAt = t.updated_at;
                $('#sscNone').hidden = true; $('#sscEditor').hidden = false; $('#sscConflict').hidden = true;
                title.value = t.title; title.disabled = RO || t.is_intro; body.innerHTML = t.html; video.value = t.video_id ? 'https://www.youtube.com/watch?v=' + t.video_id : ''; vlen.value = t.video_length || ''; paintThumb(); fillFiles(t.files);
                var other = $('#sscOther'); other.hidden = !(t.updated_by_other && savedAt && Date.now() / 1000 - savedAt < 900); if (!other.hidden) other.innerHTML = '<i class="fas fa-user-pen"></i> ' + esc(t.updated_by_other) + ' saved this topic ' + esc(ago(savedAt)) + '. Reload if they are still working on it.';
                paintSaved(); renderTree(); try { history.replaceState(null, '', '#t=' + t.id); } catch (e) {}
                openBank('topic:' + t.id, $('#sscQBox'));
            });
        };
        if (dirty && !RO && !conflict) save(true).then(go); else go();
    }
    function payload(force) { return { id: selTopic, title: title.value, html: body.innerHTML.replace(/^<br>$/, ''), video: video.value, video_length: vlen.value, version: ver, force: force ? 1 : 0 }; }
    function save(manual, force) {
        clearTimeout(timer);
        if (RO || !cur || saving || (!dirty && !manual && !force)) return Promise.resolve();
        saving = true; errBox.textContent = ''; var snap = body.innerHTML + '|' + title.value + '|' + video.value + '|' + vlen.value;
        return api('save_topic', payload(force)).then(function (r) {
            saving = false;
            if (r.ok) {
                ver = r.version; savedAt = r.saved_at; var now = body.innerHTML + '|' + title.value + '|' + video.value + '|' + vlen.value; dirty = snap !== now; if (dirty) markDirty();
                var f = findTopic(selTopic); if (f && f.t.title !== r.title) { f.t.title = r.title; renderTree(); }
                paintSaved(); return;
            }
            if (r.conflict) {
                conflict = true; ver = r.version; paintSaved();
                var box = $('#sscConflict'); box.hidden = false;
                box.innerHTML = '<b><i class="fas fa-triangle-exclamation"></i> ' + esc(r.by || 'Another tutor') + ' saved this topic ' + esc(ago(r.saved_at)) + ', after you opened it.</b> Your changes have NOT been saved, so nothing of theirs was overwritten.<div class="ssc-cbtn"><button type="button" class="sst-btn" id="sscTheirs">Load their version (drops my changes)</button><button type="button" class="sst-btn sst-danger" id="sscMine">Save mine anyway (overwrites theirs)</button></div>';
                $('#sscTheirs').onclick = function () { conflict = false; dirty = false; var id = selTopic; selTopic = 0; selectTopic(id); };
                $('#sscMine').onclick = function () { conflict = false; box.hidden = true; save(true, true); };
                return;
            }
            errBox.textContent = r.error || 'Not saved.'; if (/YouTube/.test(r.error || '')) verr.textContent = r.error; paintSaved();
        });
    }
    var saveNow = $('#sstSaveNow'); if (saveNow) saveNow.addEventListener('click', function () { if (MODE === 'content') save(true); else paintSaved(); });
    if (MODE === 'content') {
        document.execCommand('defaultParagraphSeparator', false, 'p');
        if (RO) { body.setAttribute('contenteditable', 'false'); video.disabled = true; vlen.disabled = true; $$('#sscTools button').forEach(function (b) { b.disabled = true; }); }
        [title, video, vlen].forEach(function (el) { el.addEventListener('input', markDirty); });
        video.addEventListener('input', paintThumb);
        body.addEventListener('input', function () { if (body.innerHTML === '<br>') body.innerHTML = ''; markDirty(); });
        function keepRange() { var s = window.getSelection(); if (s.rangeCount && body.contains(s.anchorNode)) savedRange = s.getRangeAt(0).cloneRange(); }
        function restoreRange() { body.focus(); if (savedRange) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(savedRange); } }
        body.addEventListener('keyup', keepRange); body.addEventListener('mouseup', keepRange); body.addEventListener('blur', keepRange);
        function exec(c, v) { restoreRange(); document.execCommand(c, false, v || null); markDirty(); }
        function insertHtml(h) { restoreRange(); document.execCommand('insertHTML', false, h); markDirty(); }
        function uploadImage(file) {
            if (!file) return; errBox.textContent = '';
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { errBox.textContent = 'Only JPG, PNG and WebP pictures are accepted.'; return; }
            if (file.size > 10 * 1048576) { errBox.textContent = 'The picture is larger than 10 MB.'; return; }
            var fd = new FormData(); fd.append('file', file); errBox.textContent = 'Uploading the picture…';
            api('upload_image', fd).then(function (r) { if (!r.ok) { errBox.textContent = r.error; return; } errBox.textContent = ''; insertHtml('<img src="' + esc(r.url) + '" alt=""><p><br></p>'); });
        }
        $('#sscTools').addEventListener('mousedown', function (e) { if (e.target.closest('button')) { keepRange(); e.preventDefault(); } });
        $('#sscTools').addEventListener('click', function (e) {
            var b = e.target.closest('button'); if (!b || RO) return; var c = b.dataset.cmd;
            if (c === 'h1' || c === 'h2') { var tag = c === 'h1' ? 'h2' : 'h3'; restoreRange(); var now = (document.queryCommandValue('formatBlock') || '').toLowerCase(); exec('formatBlock', now === tag ? 'p' : tag); }
            else if (c === 'bold') exec('bold'); else if (c === 'italic') exec('italic');
            else if (c === 'ul') exec('insertUnorderedList'); else if (c === 'ol') exec('insertOrderedList');
            else if (c === 'table') insertHtml('<table><tbody><tr><th>Heading</th><th>Heading</th></tr><tr><td>Cell</td><td>Cell</td></tr><tr><td>Cell</td><td>Cell</td></tr></tbody></table><p><br></p>');
            else if (c === 'code') { var s = (window.getSelection().toString() || '').trim(); insertHtml('<pre><code>' + esc(s || 'your code here') + '</code></pre><p><br></p>'); }
            else if (c === 'image') $('#sscImgInput').click();
            else if (c === 'video') { $('#sscVideoBox').scrollIntoView({ behavior: 'smooth', block: 'center' }); video.focus(); }
            else if (c === 'attach') $('#sscFileInput').click();
        });
        $('#sscImgInput').addEventListener('change', function (e) { uploadImage(e.target.files[0]); e.target.value = ''; });
        body.addEventListener('paste', function (e) {
            if (RO) return; var cd = e.clipboardData; if (!cd) return;
            var img = [].slice.call(cd.files || []).filter(function (f) { return /^image\//.test(f.type); })[0];
            if (img) { e.preventDefault(); uploadImage(img); return; }
            e.preventDefault(); document.execCommand('insertText', false, cd.getData('text/plain'));          // plain text only: no messy formatting from Word or web pages
        });
        body.addEventListener('drop', function (e) { var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]; if (f && /^image\//.test(f.type) && !RO) { e.preventDefault(); uploadImage(f); } });
        function attach(file) {
            if (!file || !cur) return; errBox.textContent = 'Uploading the file…'; var fd = new FormData(); fd.append('file', file); fd.append('id', cur.id);
            api('attach_file', fd).then(function (r) { if (!r.ok) { errBox.textContent = r.error; return; } errBox.textContent = ''; cur.files.push(r.file); fillFiles(cur.files); });
        }
        $('#sscFileInput').addEventListener('change', function (e) { attach(e.target.files[0]); e.target.value = ''; });
        var ab = $('#sscAttachBtn'); if (ab) ab.addEventListener('click', function () { $('#sscFileInput').click(); });
        $('#sscFiles').addEventListener('click', function (e) { var b = e.target.closest('[data-rmfile]'); if (!b || !window.confirm('Remove this file? Students will no longer see it.')) return; var id = parseInt(b.dataset.rmfile, 10); api('remove_file', { id: id }).then(function (r) { if (!r.ok) { errBox.textContent = r.error; return; } cur.files = cur.files.filter(function (f) { return f.id !== id; }); fillFiles(cur.files); }); });
    }

    /* ---------------- the question banks ---------------- */
    var TYPE = { multiple_choice: 'Multiple choice', short_answer: 'Short answer', long_answer: 'Long answer' };
    function openBank(key, box) {
        qState.bank = key; qState.box = box; if (!box) return; box.hidden = false; box.innerHTML = '<div class="ssc-loading">Loading questions…</div>';
        if (MODE === 'questions') renderTree();
        api('questions', { bank: key }).then(function (r) { if (!r.ok) { box.innerHTML = '<div class="sst-err">' + esc(r.error) + '</div>'; return; } qState.data = r; qState.edit = null; renderBank(); });
    }
    function summary(q) {
        if (q.type === 'multiple_choice') return q.options.map(function (o, i) { var l = String.fromCharCode(65 + i); return i === q.correct ? '<b class="ok">' + l + ') ' + esc(o) + ' <i class="fas fa-check"></i></b>' : l + ') ' + esc(o); }).join(' <span class="dot">·</span> ');
        if (q.type === 'short_answer') return 'Accepted answers: ' + q.accepted.map(function (a) { return '<b>' + esc(a) + '</b>'; }).join(', ') + '. Capital letters, extra spaces and full stops are ignored.';
        return 'Does not block the student. You read the answer and leave a comment. Not allowed in quizzes and exams.';
    }
    function renderBank() {
        var d = qState.data, box = qState.box, h = '', n = d.questions.length;
        h += '<div class="ssc-qh"><h3>' + esc(d.label) + '</h3><span class="ssc-qn">' + n + (n === 1 ? ' question' : ' questions') + '</span></div>';
        if (d.draw) h += '<p class="sst-hint">Students get ' + d.draw + (n > d.draw ? ' of ' + n : '') + ' of the bank at random, shuffled · no pass mark.' + (n < d.draw && n ? ' With ' + n + ' in the bank they get all of them; add more so each student gets a different set.' : '') + '</p>';
        if (d.need) h += '<p class="sst-hint">Each attempt draws ' + d.need + ' questions at random from this bank.</p>';
        if (d.warning) h += '<div class="ssc-warn"><i class="fas fa-triangle-exclamation"></i> ' + esc(d.warning) + (d.place !== 'module_quiz' ? ' Publish stays off until it has enough.' : '') + '</div>';
        if (d.untagged) h += '<div class="ssc-warn"><i class="fas fa-triangle-exclamation"></i> ' + d.untagged + (d.untagged === 1 ? ' question has' : ' questions have') + ' no topic tag. Tag each one so the result screen can send the student back to the right topic.</div>';
        if (d.place === 'knowledge_check') h += '<p class="sst-hint">The Knowledge Check is multiple choice, has no pass mark, never blocks a student, and can be retaken.</p>';
        d.questions.forEach(function (q, i) {
            if (qState.edit === q.id) { h += form(q); return; }
            var tag = q.tag ? (d.tags.filter(function (t) { return t.id === q.tag; })[0] || { label: 'topic' }).label : '';
            h += '<div class="ssc-q" data-q="' + q.id + '"><div class="ssc-qt"><span class="qn">Q' + (i + 1) + '</span><span class="tp">' + TYPE[q.type] + '</span>' + (tag ? '<span class="tg">' + esc(tag) + '</span>' : '') + '<span class="mk ' + (q.type === 'long_answer' ? 'hand' : '') + '">' + (q.type === 'long_answer' ? 'You mark this by hand' : 'Auto-marked') + '</span>'
                + (RO ? '' : '<span class="act"><button type="button" data-qa="up" data-id="' + q.id + '" aria-label="Move up"' + (i === 0 ? ' disabled' : '') + '><i class="fas fa-chevron-up"></i></button><button type="button" data-qa="down" data-id="' + q.id + '" aria-label="Move down"' + (i === n - 1 ? ' disabled' : '') + '><i class="fas fa-chevron-down"></i></button><button type="button" data-qa="edit" data-id="' + q.id + '" aria-label="Edit"><i class="fas fa-pen"></i></button><button type="button" data-qa="del" data-id="' + q.id + '" aria-label="' + (q.answered ? 'Archive' : 'Delete') + '"><i class="fas ' + (q.answered ? 'fa-box-archive' : 'fa-trash') + '"></i></button></span>')
                + '</div><p class="q">' + esc(q.text) + '</p><p class="s">' + summary(q) + '</p>' + (q.answered ? '<p class="s note">Students have answered this question, so removing it archives it.</p>' : '') + '</div>';
        });
        if (qState.edit === 0) h += form(null);
        if (!RO && qState.edit === null) h += '<div class="ssc-adds">' + d.allowed.map(function (t) { return '<button type="button" class="sst-btn" data-add="' + t + '"><i class="fas fa-plus"></i> ' + TYPE[t] + '</button>'; }).join('') + '</div>';
        if (!n && qState.edit === null) h += '<p class="mut">No questions yet.' + (RO ? '' : ' Add the first one with a button above.') + '</p>';
        box.innerHTML = h;
    }
    function form(q) {
        var d = qState.data, t = q ? q.type : qState.newType, opts = q ? q.options.slice() : (t === 'multiple_choice' ? ['', '', '', ''] : []), acc = q ? q.accepted.slice() : (t === 'short_answer' ? [''] : []);
        var h = '<form class="ssc-form" id="sscQForm" data-type="' + t + '"><div class="ssc-qt"><span class="tp">' + TYPE[t] + '</span>' + (t === 'long_answer' ? '<span class="mk hand">You mark this by hand</span>' : '<span class="mk">Auto-marked</span>') + '</div>'
            + '<label class="sst-l">Question</label><textarea class="sst-in" name="text" rows="2" maxlength="1000">' + esc(q ? q.text : '') + '</textarea>';
        if (t === 'multiple_choice') {
            h += '<label class="sst-l">Options (2 to 6) and the one correct answer</label><div id="sscOpts">' + opts.map(function (o, i) { return optRow(o, i, q ? q.correct === i : i === 0); }).join('') + '</div><button type="button" class="sst-btn" data-optadd="1"><i class="fas fa-plus"></i> Add option</button>';
        } else if (t === 'short_answer') {
            h += '<label class="sst-l">Accepted answers</label><div id="sscAcc">' + acc.map(accRow).join('') + '</div><button type="button" class="sst-btn" data-accadd="1"><i class="fas fa-plus"></i> Add another accepted answer</button><p class="sst-hint">Capital letters, extra spaces and full stops are ignored when the system marks it.</p>';
        } else h += '<p class="sst-hint">A long answer does not block the student. You will read it under "Answers to mark" and leave a comment. Long answers are only allowed in topic questions.</p>';
        if (d.needs_tag) h += '<label class="sst-l">Topic this question comes from (needed for the "Read again" link)</label><select class="sst-in" name="tag"><option value="">Choose a topic…</option>' + d.tags.map(function (tg) { return '<option value="' + tg.id + '"' + (q && q.tag === tg.id ? ' selected' : '') + '>' + esc(tg.label) + '</option>'; }).join('') + '</select>';
        return h + '<div class="sst-err" id="sscQErr" role="alert"></div><div class="ssc-fb"><button class="sst-btn sst-primary" type="submit">Save question</button><button type="button" class="sst-btn" data-qcancel="1">Cancel</button></div><input type="hidden" name="id" value="' + (q ? q.id : 0) + '"></form>';
    }
    function optRow(v, i, ok) { return '<div class="ssc-opt"><input type="radio" name="correct" value="' + i + '"' + (ok ? ' checked' : '') + ' aria-label="This is the correct answer"><input class="sst-in" type="text" maxlength="200" value="' + esc(v) + '" placeholder="Option ' + String.fromCharCode(65 + i) + '"><button type="button" data-optrm="1" aria-label="Remove option"><i class="fas fa-xmark"></i></button></div>'; }
    function accRow(v) { return '<div class="ssc-opt"><input class="sst-in" type="text" maxlength="200" value="' + esc(v) + '" placeholder="An accepted answer"><button type="button" data-accrm="1" aria-label="Remove answer"><i class="fas fa-xmark"></i></button></div>'; }
    function collect(f) {
        var t = f.dataset.type, q = { id: parseInt(f.elements.id.value, 10) || 0, type: t, text: f.elements.text.value };
        if (t === 'multiple_choice') { var rows = $$('#sscOpts .ssc-opt', f); q.options = rows.map(function (r) { return $('input[type=text]', r).value; }); rows.forEach(function (r, i) { if ($('input[type=radio]', r).checked) q.correct = i; }); }
        if (t === 'short_answer') q.accepted = $$('#sscAcc input', f).map(function (i) { return i.value; });
        if (f.elements.tag) q.tag = parseInt(f.elements.tag.value, 10) || 0;
        return q;
    }
    document.addEventListener('click', function (e) {
        var box = qState.box; if (!box || !box.contains(e.target)) return;
        var b;
        if ((b = e.target.closest('[data-add]'))) { qState.edit = 0; qState.newType = b.dataset.add; renderBank(); var f = $('#sscQForm'); if (f) f.elements.text.focus(); }
        else if (e.target.closest('[data-qcancel]')) { qState.edit = null; renderBank(); }
        else if ((b = e.target.closest('[data-qa]'))) {
            var id = parseInt(b.dataset.id, 10), a = b.dataset.qa;
            if (a === 'edit') { qState.edit = id; renderBank(); }
            else if (a === 'del') { var q = qState.data.questions.filter(function (x) { return x.id === id; })[0]; if (!window.confirm(q.answered ? 'Students have answered this question, so it will be archived (hidden, their answers kept).' : 'Delete this question?')) return;
                api('delete_question', { id: id, bank: qState.bank }).then(function (r) { if (!r.ok) return alert(r.error); qState.data = r; qState.edit = null; setOutline(r.outline); renderBank(); }); }
            else { var ids = qState.data.questions.map(function (x) { return x.id; }), i = ids.indexOf(id), j = a === 'up' ? i - 1 : i + 1; if (j < 0 || j >= ids.length) return; swap(ids, i, j);
                api('reorder_questions', { bank: qState.bank, ids: ids }).then(function (r) { if (r.ok) { qState.data = r; renderBank(); } else alert(r.error); }); }
        }
        else if (e.target.closest('[data-optadd]')) { var c = $('#sscOpts'); if (c.children.length >= 6) { $('#sscQErr').textContent = 'A question can have at most 6 options.'; return; } c.insertAdjacentHTML('beforeend', optRow('', c.children.length, false)); }
        else if ((b = e.target.closest('[data-optrm]'))) { var cc = $('#sscOpts'); if (cc.children.length <= 2) { $('#sscQErr').textContent = 'A multiple choice question needs at least 2 options.'; return; } b.closest('.ssc-opt').remove(); $$('#sscOpts .ssc-opt').forEach(function (r, i) { $('input[type=radio]', r).value = i; $('input[type=text]', r).placeholder = 'Option ' + String.fromCharCode(65 + i); }); if (!$('#sscOpts input[type=radio]:checked')) $('#sscOpts input[type=radio]').checked = true; }
        else if (e.target.closest('[data-accadd]')) { $('#sscAcc').insertAdjacentHTML('beforeend', accRow('')); }
        else if ((b = e.target.closest('[data-accrm]'))) { if ($('#sscAcc').children.length > 1) b.closest('.ssc-opt').remove(); else $('input', b.closest('.ssc-opt')).value = ''; }
    });
    document.addEventListener('submit', function (e) {
        var f = e.target; if (f.id !== 'sscQForm') return; e.preventDefault();
        var btn = $('button[type=submit]', f); btn.disabled = true;
        api('save_question', { bank: qState.bank, question: collect(f) }).then(function (r) {
            btn.disabled = false; if (!r.ok) { $('#sscQErr').textContent = r.error; return; }
            qState.data = r; qState.edit = null; setOutline(r.outline); renderBank();
        });
    });

    /* ---------------- answers to mark (Questions tab) ---------------- */
    function loadMarks() {
        var card = $('#sscMark'); if (!card) return;
        api('answers_to_mark', {}).then(function (r) { if (r.ok) paintMarks(r.answers); });
    }
    function paintMarks(list) {
        var card = $('#sscMark'), open = list.filter(function (a) { return !a.marked; }).length;
        card.hidden = !list.length; $('#sscMarkN').textContent = open ? open + ' waiting' : 'all marked';
        $('#sscMarkList').innerHTML = list.map(function (a) {
            return '<div class="ssc-ans" data-aid="' + a.id + '"><div class="ssc-qt"><span class="qn">' + esc(a.student) + '</span>' + (a.topic_title ? '<span class="tg">' + esc(a.topic_title) + '</span>' : '') + '<span class="mk ' + (a.marked ? '' : 'hand') + '">' + (a.marked ? 'Commented' : 'Waiting for your comment') + '</span></div>'
                + '<p class="q">' + esc(a.question_text) + '</p><div class="ssc-given">' + esc(a.answer) + '</div>'
                + (RO ? (a.tutor_comment ? '<p class="s">Your comment: ' + esc(a.tutor_comment) + '</p>' : '') : '<textarea class="sst-in" rows="2" maxlength="1000" placeholder="Write a short comment for the student">' + esc(a.tutor_comment || '') + '</textarea><div class="sst-err"></div><button type="button" class="sst-btn sst-primary" data-mark="' + a.id + '">' + (a.marked ? 'Update comment' : 'Save comment') + '</button>') + '</div>';
        }).join('');
    }
    var ml = $('#sscMarkList'); if (ml) ml.addEventListener('click', function (e) {
        var b = e.target.closest('[data-mark]'); if (!b) return; var card = b.closest('.ssc-ans'), ta = $('textarea', card); b.disabled = true;
        api('mark_answer', { id: parseInt(b.dataset.mark, 10), comment: ta.value }).then(function (r) { b.disabled = false; if (!r.ok) { $('.sst-err', card).textContent = r.error; return; } paintMarks(r.answers); });
    });

    /* ---------------- start ---------------- */
    renderTree();
    if (MODE === 'content') {
        var m = (location.hash.match(/t=(\d+)/) || [])[1], first = outline.intro ? outline.intro.id : (outline.modules[0] && outline.modules[0].topics[0] ? outline.modules[0].topics[0].id : 0);
        var start = m ? parseInt(m, 10) : first; if (start) selectTopic(start);
    } else {
        loadMarks();
        if (cfg.bank) openBank(cfg.bank, $('#sscQBox')); else openBank('kb', $('#sscQBox'));
    }
})();
