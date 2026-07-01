<?php
// public/components/public/course_share_bar.php
// Copy-link bar for sharing a course registration page.
// Requires: $share_url, optional $share_title

if (empty($share_url)) return;
$share_title = $share_title ?? 'Register for this course at Dazu Hub Institute';
$input_id = $input_id ?? 'courseShareUrl';
?>
<div class="dh-share-bar dh-reveal">
    <div class="dh-share-bar-inner">
        <div class="dh-share-label"><i class="fas fa-share-nodes"></i> Share registration link</div>
        <div class="dh-share-row">
            <input type="text" class="dh-share-input" id="<?php echo htmlspecialchars($input_id); ?>" readonly
                   value="<?php echo htmlspecialchars($share_url); ?>" aria-label="Share link">
            <button type="button" class="dh-btn dh-btn-primary dh-share-copy" data-target="<?php echo htmlspecialchars($input_id); ?>">
                <i class="fas fa-copy"></i> Copy
            </button>
            <a href="https://wa.me/?text=<?php echo rawurlencode($share_title . ' ' . $share_url); ?>"
               class="dh-btn dh-btn-outline dh-share-wa" target="_blank" rel="noopener" title="Share on WhatsApp">
                <i class="fab fa-whatsapp"></i>
            </a>
        </div>
        <p class="dh-share-hint">Anyone with this link can view the course and register — no login required.</p>
    </div>
</div>
<script>
(function(){
    document.querySelectorAll('.dh-share-copy').forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = btn.getAttribute('data-target');
            var inp = document.getElementById(id);
            if(!inp) return;
            inp.select();
            inp.setSelectionRange(0, 99999);
            var done = function(){
                var orig = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied';
                setTimeout(function(){ btn.innerHTML = orig; }, 2000);
            };
            if(navigator.clipboard && navigator.clipboard.writeText){
                navigator.clipboard.writeText(inp.value).then(done);
            } else {
                document.execCommand('copy');
                done();
            }
        });
    });
})();
</script>
