/* Dazu: discourage copying images (logos, cover images, blog pictures).
   Right-click / long-press on a picture shows nothing, pictures cannot be dragged out, and the
   "save image" bubble on phones is turned off. Text, links and form fields work as normal.
   Note: no website can fully stop a determined person (for example a screenshot); this stops the easy ways. */
(function () {
  var css = 'img,picture,svg image,video{-webkit-user-drag:none;user-drag:none;-webkit-touch-callout:none;user-select:none;-webkit-user-select:none}';
  var st = document.createElement('style'); st.textContent = css; (document.head || document.documentElement).appendChild(st);

  function isPicture(el) {
    for (var n = el, depth = 0; n && n.nodeType === 1 && depth < 4; n = n.parentElement, depth++) {
      var tag = n.tagName;
      if (tag === 'IMG' || tag === 'PICTURE' || tag === 'VIDEO' || tag === 'CANVAS' || (tag === 'svg' && n.querySelector('image'))) return true;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || n.isContentEditable) return false;
      var bg = window.getComputedStyle(n).backgroundImage;
      if (bg && bg !== 'none' && bg.indexOf('url(') > -1) return true;
    }
    return false;
  }
  document.addEventListener('contextmenu', function (e) { if (isPicture(e.target)) e.preventDefault(); }, true);
  document.addEventListener('dragstart', function (e) { if (isPicture(e.target)) e.preventDefault(); }, true);
  // Copy with a picture selected: copy only the text.
  document.addEventListener('copy', function (e) {
    var sel = window.getSelection(); if (!sel || !sel.rangeCount) return;
    var frag = sel.getRangeAt(0).cloneContents();
    if (frag.querySelector && frag.querySelector('img,picture,video')) { e.preventDefault(); e.clipboardData.setData('text/plain', sel.toString()); }
  }, true);
})();
