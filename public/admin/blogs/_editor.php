<?php
// public/admin/blogs/_editor.php
// Shared Summernote setup for the blog create/edit screens.
// Uses the "lite" build: the bs4 build expects Bootstrap 4 modals, so on this Bootstrap 5
// admin its link/video dialogs broke and left the editor unusable.
?>
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<style>
    .note-editor.note-frame { border-radius: 6px; }
    .note-editable .dz-yt { cursor: pointer; outline: 2px dashed transparent; outline-offset: 3px; }
    .note-editable .dz-yt:hover { outline-color: #9ca3af; }
    .note-editable .dz-yt.dz-selected { outline-color: #15A24A; }
    .note-editable .dz-yt iframe { pointer-events: none; }  /* clicks select the video instead of playing it */
    .note-editable .dz-cols > .dz-col { outline: 1px dashed #d1d5db; outline-offset: 4px; }
    #dzVideoBar { position: absolute; z-index: 2000; display: none; background: #1a1a1a; border-radius: 6px; padding: 4px; gap: 2px; }
    #dzVideoBar button { background: none; border: 0; color: #fff; font-size: 12px; padding: 4px 8px; border-radius: 4px; }
    #dzVideoBar button:hover { background: #374151; }
    #dzVideoBar .sep { width: 1px; background: #4b5563; margin: 2px 4px; }
</style>
<div id="dzVideoBar">
    <button type="button" data-w="25">25%</button>
    <button type="button" data-w="50">50%</button>
    <button type="button" data-w="75">75%</button>
    <button type="button" data-w="100">Full</button>
    <span class="sep"></span>
    <button type="button" data-a="left" title="Align left"><i class="fas fa-align-left"></i></button>
    <button type="button" data-a="center" title="Center"><i class="fas fa-align-center"></i></button>
    <button type="button" data-a="right" title="Align right"><i class="fas fa-align-right"></i></button>
    <span class="sep"></span>
    <button type="button" data-remove="1" title="Remove video"><i class="fas fa-trash"></i></button>
</div>
<script>
function dzYoutubeId(text) {
    const m = (text || '').trim().match(/^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([\w-]{11})\S*$/i);
    return m ? m[1] : null;
}

// Width is a % of the content column; the inner box keeps a 16:9 ratio at any width.
function dzYoutubeHtml(id) {
    return '<div class="dz-yt" contenteditable="false" style="width:100%;max-width:100%;margin:16px auto;">'
        + '<div style="position:relative;padding-bottom:56.25%;height:0;border-radius:12px;overflow:hidden;background:#000;">'
        + '<iframe src="https://www.youtube.com/embed/' + id + '?rel=0" style="position:absolute;inset:0;width:100%;height:100%;border:0" '
        + 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen title="YouTube video"></iframe>'
        + '</div></div><p><br></p>';
}

const DZ_COLUMNS_HTML =
    '<div class="dz-cols" style="display:flex;flex-wrap:wrap;gap:24px;align-items:center;margin:24px 0;">'
    + '<div class="dz-col" style="flex:1 1 280px;min-width:0;"><p>Write your text here…</p></div>'
    + '<div class="dz-col" style="flex:1 1 280px;min-width:0;"><p>Click here, then insert an image or video.</p></div>'
    + '</div><p><br></p>';

const DZ_FAQ_HTML =
    '<details class="dz-faq" open style="border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;margin:12px 0;">'
    + '<summary style="font-weight:700;cursor:pointer;">Your question here?</summary>'
    + '<p style="margin:8px 0 0;">Your answer here.</p>'
    + '</details><p><br></p>';

function dzInsertButton(icon, label, tooltip, build) {
    return function (context) {
        return $.summernote.ui.button({
            contents: '<i class="' + icon + '"></i> ' + label,
            tooltip: tooltip,
            click: function () {
                context.invoke('editor.saveRange');
                const html = build();
                if (!html) return;
                context.invoke('editor.restoreRange');
                context.invoke('editor.pasteHTML', html);
            }
        }).render();
    };
}

function dzInitEditor($el, extra) {
    extra = extra || {};
    const userCallbacks = extra.callbacks || {};
    delete extra.callbacks;

    $el.summernote(Object.assign({
        height: 400,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['fontname', ['fontname']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'dzVideo']],
            ['layout', ['dzColumns', 'dzFaq', 'hr']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        buttons: {
            dzVideo: dzInsertButton('fab fa-youtube', 'Video', 'Insert YouTube video', function () {
                const id = dzYoutubeId(prompt('Paste the YouTube link:') || '');
                if (!id) { alert('That does not look like a YouTube link.'); return null; }
                return dzYoutubeHtml(id);
            }),
            dzColumns: dzInsertButton('fas fa-columns', 'Columns', 'Text on one side, image/video on the other', () => DZ_COLUMNS_HTML),
            dzFaq: dzInsertButton('fas fa-question-circle', 'FAQ', 'Add a question & answer', () => DZ_FAQ_HTML)
        },
        callbacks: Object.assign({
            // Upload pictures as files (compressed on the server) instead of embedding them as base64 in the post.
            onImageUpload: function (files) {
                Array.prototype.forEach.call(files, function (f) {
                    const fd = new FormData();
                    fd.append('image', f);
                    fetch(url('api/blog/upload_image.php'), { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (r) { if (r.ok) $el.summernote('insertImage', r.url); else alert('Image not added: ' + r.error); })
                        .catch(function () { alert('The image could not be uploaded. Check your connection and try again.'); });
                });
            },
            onPaste: function (e) {
                const clip = (e.originalEvent || e).clipboardData;
                const id = dzYoutubeId(clip ? clip.getData('text/plain') : '');
                if (!id) return;
                e.preventDefault();
                $el.summernote('pasteHTML', dzYoutubeHtml(id));
            }
        }, userCallbacks)
    }, extra));

    // Click a video in the editor to resize/align/remove it.
    const $bar = $('#dzVideoBar');
    let selected = null;
    const editable = $el.next('.note-editor').find('.note-editable');
    const hideBar = () => { $bar.hide(); if (selected) selected.classList.remove('dz-selected'); selected = null; };

    editable.on('click', '.dz-yt', function (e) {
        e.stopPropagation();
        hideBar();
        selected = this;
        selected.classList.add('dz-selected');
        const r = this.getBoundingClientRect();
        $bar.css({ display: 'flex', top: r.top + window.scrollY - 40, left: r.left + window.scrollX });
    });
    $(document).on('click', function (e) { if (!$(e.target).closest('#dzVideoBar').length) hideBar(); });
    $(window).on('scroll resize', hideBar);

    $bar.off('click').on('click', 'button', function () {
        if (!selected) return;
        const b = this.dataset;
        if (b.w) selected.style.width = b.w + '%';
        if (b.a) selected.style.margin = { left: '16px auto 16px 0', center: '16px auto', right: '16px 0 16px auto' }[b.a];
        if (b.remove) { selected.remove(); hideBar(); }
        $el.summernote('triggerEvent', 'change', $el.summernote('code'));
        if (selected) $(selected).trigger('click');
    });
}
</script>
