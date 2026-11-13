/* Self-study course editor (Details tab): the crop frame, the live student card, the description counter, the checklist, and the unsaved-changes warning.
   The server checks everything again when the form is sent; this file only helps the tutor see the result as they go. */
(function () {
    'use strict';
    var cfgEl = document.getElementById('sstCfg');
    if (!cfgEl) return;
    var cfg = JSON.parse(cfgEl.textContent);
    var $ = function (s) { return document.querySelector(s); };
    var form = $('#sstForm'), title = $('#sstTitle'), desc = $('#sstDesc'), count = $('#sstCount'), file = $('#sstFile');
    var stage = $('#sstStage'), img = $('#sstImg'), frame = $('#sstFrame'), handle = $('#sstHandle'), drop = $('#sstDrop');
    var cropMsg = $('#sstCropMsg'), cropTxt = $('#sstCropTxt'), imgErr = $('#sstImgErr'), prev = $('#sstPrev'), prevNone = $('#sstPrevNone');
    var cx = $('#cropX'), cy = $('#cropY'), cw = $('#cropW');
    var st = { ow: cfg.ow, oh: cfg.oh, x: 0, y: 0, w: 0, hasImage: !!cfg.hasCover, objectUrl: null };
    var dirty = false, submitting = false;

    /* ---- confirm buttons (archive, delete, unpublish, move) ---- */
    [].slice.call(document.querySelectorAll('[data-confirm]')).forEach(function (b) { b.addEventListener('click', function (e) { if (!window.confirm(b.dataset.confirm)) e.preventDefault(); }); });
    [].slice.call(document.querySelectorAll('form')).forEach(function (f) { f.addEventListener('submit', function () { submitting = true; }); });
    window.addEventListener('beforeunload', function (e) { if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; } });
    if (!form) return;
    if (!cfg.readonly) { form.addEventListener('input', function () { dirty = true; }); form.addEventListener('change', function () { dirty = true; }); }

    /* ---- title, description, level: the live student card and the checklist ---- */
    var levelNames = cfg.levels || {};
    function levelValue() { var r = form.querySelector('input[name=level]:checked'); return r ? r.value : ''; }
    function checkItem(k, ok) { var li = document.querySelector('#sstCheck [data-k="' + k + '"]'); if (li) { li.classList.toggle('done', ok); li.classList.toggle('todo', !ok); } }
    function refresh() {
        var d = desc.value.length;
        count.textContent = d + ' / 160'; count.style.color = d > 160 ? '#b3261e' : '';
        $('#pTitle').textContent = title.value.trim() || 'Your course title';
        $('#pDesc').textContent = desc.value.trim() || 'Your short description appears here.';
        var lv = levelValue(), tag = $('#pLevel'); tag.textContent = levelNames[lv] || 'Beginner'; tag.className = 'sst-lvltag ' + lv;
        checkItem('text', title.value.trim() !== '' && desc.value.trim() !== '');
        checkItem('level', lv !== '');
        checkItem('image', st.hasImage);
        var all = [].slice.call(document.querySelectorAll('#sstCheck li')).every(function (li) { return li.classList.contains('done'); });
        var pub = $('#sstPublish'); if (pub) pub.disabled = !all || d > 160;
        var hint = $('#sstPubHint'); if (hint) hint.textContent = all ? 'Everything is ready. Press Publish.' : 'Publish switches on when every item above is ticked. The server checks them again when you press it.';
    }
    ['input', 'change'].forEach(function (ev) { form.addEventListener(ev, refresh); });

    /* ---- the crop frame: always 16:9, in the picture's own pixels ---- */
    function scale() { return img.clientWidth / st.ow; }
    function defaultFrame() {
        var w, h;
        if (st.ow / st.oh > 16 / 9) { h = st.oh; w = Math.round(h * 16 / 9); } else { w = st.ow; h = Math.round(w * 9 / 16); }
        st.w = w; st.x = Math.floor((st.ow - w) / 2); st.y = Math.floor((st.oh - h) / 2);
    }
    function clampFrame() {
        var maxW = Math.min(st.ow, Math.round(st.oh * 16 / 9));
        st.w = Math.max(Math.min(cfg.min, maxW), Math.min(st.w, maxW));
        var h = Math.round(st.w * 9 / 16);
        st.x = Math.max(0, Math.min(st.x, st.ow - st.w)); st.y = Math.max(0, Math.min(st.y, st.oh - h));
    }
    function paintFrame() {
        if (!st.hasImage || !st.ow) return;
        clampFrame();
        var s = scale(), h = Math.round(st.w * 9 / 16);
        frame.style.left = (st.x * s) + 'px'; frame.style.top = (st.y * s) + 'px'; frame.style.width = (st.w * s) + 'px'; frame.style.height = (h * s) + 'px';
        cx.value = st.x; cy.value = st.y; cw.value = st.w;
        cropTxt.textContent = st.w + ' × ' + h + ' · ' + (st.w >= 1600 ? 'sharp enough' : 'good (1600 pixels wide or more is sharper on big screens)');
        cropMsg.hidden = false;
        drawPreview();
    }
    function drawPreview() {
        if (!prev || !st.hasImage || !img.complete || !img.naturalWidth) return;
        prev.hidden = false; if (prevNone) prevNone.hidden = true;
        var h = Math.round(st.w * 9 / 16), g = prev.getContext('2d');
        g.drawImage(img, st.x, st.y, st.w, h, 0, 0, prev.width, prev.height);
    }
    function pointer(ev) { return { x: ev.clientX, y: ev.clientY }; }
    frame.addEventListener('pointerdown', function (ev) {
        if (ev.target === handle) return;
        ev.preventDefault(); frame.setPointerCapture(ev.pointerId);
        var start = pointer(ev), x0 = st.x, y0 = st.y, s = scale();
        function move(e) { st.x = x0 + (e.clientX - start.x) / s; st.y = y0 + (e.clientY - start.y) / s; st.x = Math.round(st.x); st.y = Math.round(st.y); paintFrame(); dirty = true; }
        function up() { frame.removeEventListener('pointermove', move); frame.removeEventListener('pointerup', up); frame.removeEventListener('pointercancel', up); }
        frame.addEventListener('pointermove', move); frame.addEventListener('pointerup', up); frame.addEventListener('pointercancel', up);
    });
    handle.addEventListener('pointerdown', function (ev) {
        ev.preventDefault(); ev.stopPropagation(); handle.setPointerCapture(ev.pointerId);
        var start = pointer(ev), w0 = st.w, s = scale();
        function move(e) { st.w = Math.round(w0 + (e.clientX - start.x) / s); paintFrame(); dirty = true; }
        function up() { handle.removeEventListener('pointermove', move); handle.removeEventListener('pointerup', up); handle.removeEventListener('pointercancel', up); }
        handle.addEventListener('pointermove', move); handle.addEventListener('pointerup', up); handle.addEventListener('pointercancel', up);
    });
    frame.addEventListener('keydown', function (ev) {
        var step = ev.shiftKey ? 40 : 10, used = true;
        if (ev.key === 'ArrowLeft') st.x -= step; else if (ev.key === 'ArrowRight') st.x += step; else if (ev.key === 'ArrowUp') st.y -= step; else if (ev.key === 'ArrowDown') st.y += step;
        else if (ev.key === '+' || ev.key === '=') st.w += step * 2; else if (ev.key === '-') st.w -= step * 2; else used = false;
        if (used) { ev.preventDefault(); paintFrame(); dirty = true; }
    });
    $('#sstReset').addEventListener('click', function () { if (!st.hasImage) return; defaultFrame(); paintFrame(); dirty = true; });
    window.addEventListener('resize', paintFrame);

    function showImage(src, ow, oh, frameInit) {
        st.ow = ow; st.oh = oh; st.hasImage = true;
        img.onload = function () { if (!st.ow && img.naturalWidth) { st.ow = img.naturalWidth; st.oh = img.naturalHeight; } stage.hidden = false; drop.hidden = true; if (frameInit) { st.x = frameInit.x; st.y = frameInit.y; st.w = frameInit.w; } else defaultFrame(); $('#sstReset').disabled = false; paintFrame(); refresh(); };
        img.src = src;
        if (img.complete && img.naturalWidth) img.onload();
    }
    file.addEventListener('change', function () {
        imgErr.textContent = '';
        var f = file.files && file.files[0]; if (!f) return;
        var okType = /^image\/(jpeg|png|webp)$/.test(f.type);
        if (!okType) { imgErr.textContent = 'Only JPG, PNG and WebP pictures are accepted.'; file.value = ''; return; }
        if (f.size > cfg.maxBytes) { imgErr.textContent = 'That picture is larger than 10 MB. Please choose a smaller one.'; file.value = ''; return; }
        var url = URL.createObjectURL(f), probe = new Image();
        probe.onload = function () {
            if (probe.naturalWidth < cfg.min) { imgErr.textContent = 'That picture is only ' + probe.naturalWidth + ' pixels wide. It must be at least ' + cfg.min + ' pixels wide so it stays sharp (1920 × 1080 is best).'; file.value = ''; URL.revokeObjectURL(url); return; }
            if (Math.min(probe.naturalWidth, Math.round(probe.naturalHeight * 16 / 9)) < cfg.min) { imgErr.textContent = 'That picture is too short for the wide card shape. At 16:9 it needs to be at least ' + Math.round(cfg.min * 9 / 16) + ' pixels tall.'; file.value = ''; URL.revokeObjectURL(url); return; }
            if (st.objectUrl) URL.revokeObjectURL(st.objectUrl);
            st.objectUrl = url; showImage(url, probe.naturalWidth, probe.naturalHeight, null); dirty = true;
        };
        probe.onerror = function () { imgErr.textContent = 'That file could not be read as a picture.'; file.value = ''; };
        probe.src = url;
    });
    if (cfg.hasCover) showImage(cfg.src, cfg.ow, cfg.oh, cfg.crop);
    refresh();
    dirty = false;
})();
