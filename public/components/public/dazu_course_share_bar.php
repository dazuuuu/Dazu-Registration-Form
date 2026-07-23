<?php
/** Share bar — requires $share_url, $share_title */
$shareUrl = $share_url ?? '';
$shareTitle = $share_title ?? 'Dazu hub Institute Course';
$inputId = $input_id ?? 'dazuShareUrl';
?>
<div class="mb-8 bg-white border border-slate-200 rounded-2xl p-5 md:p-6 shadow-sm">
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex items-start gap-3">
      <div class="p-2.5 bg-green-50 border border-green-100 text-green-600 rounded-xl shrink-0">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
      </div>
      <div>
        <span class="text-[10px] uppercase tracking-widest font-extrabold text-green-600">Share this course</span>
        <p class="text-sm font-bold text-slate-900 mt-0.5"><?php echo htmlspecialchars($shareTitle); ?></p>
        <p class="text-xs text-slate-500 mt-1">Send this link to friends — their application will be linked to this course.</p>
      </div>
    </div>
    <div class="flex flex-col sm:flex-row gap-2 w-full md:w-auto md:min-w-[320px]">
      <input type="text" id="<?php echo htmlspecialchars($inputId); ?>" readonly value="<?php echo htmlspecialchars($shareUrl); ?>"
             class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 truncate">
      <button type="button" class="dazu-copy-share px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl shrink-0"
              data-target="<?php echo htmlspecialchars($inputId); ?>">Copy Link</button>
    </div>
  </div>
</div>
<script>
(function(){
  document.querySelectorAll('.dazu-copy-share').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = btn.getAttribute('data-target');
      var el = document.getElementById(id);
      if (!el) return;
      var url = el.value;
      var done = function(){ btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = 'Copy Link'; }, 2000); };
      if (navigator.clipboard) navigator.clipboard.writeText(url).then(done);
      else { el.select(); document.execCommand('copy'); done(); }
    });
  });
})();
</script>
