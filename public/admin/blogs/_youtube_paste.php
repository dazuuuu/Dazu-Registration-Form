<script>
// Summernote helper: a pasted YouTube link becomes an embedded player the admin can play inside the editor.
function dzYoutubeId(text) {
    const m = (text || '').trim().match(/^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([\w-]{11})\S*$/i);
    return m ? m[1] : null;
}
function dzYoutubeNode(id) {
    const wrap = document.createElement('div');
    wrap.className = 'dz-yt-embed';
    wrap.setAttribute('contenteditable', 'false');
    wrap.style.cssText = 'position:relative;padding-bottom:56.25%;height:0;margin:16px 0;border-radius:12px;overflow:hidden;background:#000';
    wrap.innerHTML = '<iframe src="https://www.youtube.com/embed/' + id + '?rel=0" style="position:absolute;inset:0;width:100%;height:100%;border:0" '
        + 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen title="YouTube video"></iframe>';
    return wrap;
}
function dzYoutubePaste($editor) {
    return function (e) {
        const clip = (e.originalEvent || e).clipboardData;
        const id = dzYoutubeId(clip ? clip.getData('text/plain') : '');
        if (!id) return;
        e.preventDefault();
        $editor.summernote('insertNode', dzYoutubeNode(id));
        $editor.summernote('insertParagraph');
    };
}
</script>
