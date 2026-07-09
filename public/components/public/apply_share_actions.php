<?php
// Compact share actions for the standalone apply form (no visible URL).
$apply_share_url = $apply_share_url ?? url('apply/index.php');
$apply_share_title = $apply_share_title ?? 'Apply for a course at Dazu Hub Tech Skills Institute';
$apply_share_id = $apply_share_id ?? 'dhApplyShareUrl';
?>
<div class="hp-apply-share-row" role="group" aria-label="Share application form">
    <span class="hp-apply-share-label">Share form</span>
    <input type="text" class="hp-apply-share-url" id="<?php echo htmlspecialchars($apply_share_id); ?>" readonly
           value="<?php echo htmlspecialchars($apply_share_url); ?>" tabindex="-1" aria-hidden="true">
    <button type="button" class="hp-apply-share-btn" data-dh-apply-share
            data-url="<?php echo htmlspecialchars($apply_share_url); ?>"
            data-title="<?php echo htmlspecialchars($apply_share_title); ?>"
            title="Share application form" aria-label="Share application form">
        <i class="fas fa-share-nodes" aria-hidden="true"></i>
    </button>
    <a href="<?php echo htmlspecialchars(whatsapp_chat_url($apply_share_title . ' ' . $apply_share_url)); ?>"
       class="hp-apply-share-btn hp-apply-share-btn--wa" target="_blank" rel="noopener"
       title="Share on WhatsApp" aria-label="Share on WhatsApp">
        <i class="fab fa-whatsapp" aria-hidden="true"></i>
    </a>
</div>
