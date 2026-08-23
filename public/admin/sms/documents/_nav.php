<?php
/** SmS Documents section navigation. Expects $documents_nav_active = templates|history|compose */
$documents_nav_active = $documents_nav_active ?? 'templates';
?>
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link<?= $documents_nav_active === 'templates' ? ' active' : '' ?>"
           href="<?= url('admin/sms/documents/index.php') ?>">
            <i class="fas fa-file-alt me-1"></i> Templates
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $documents_nav_active === 'history' ? ' active' : '' ?>"
           href="<?= url('admin/sms/documents/history.php') ?>">
            <i class="fas fa-history me-1"></i> Generated &amp; sent
        </a>
    </li>
    <?php if ($documents_nav_active === 'compose'): ?>
    <li class="nav-item">
        <span class="nav-link active"><i class="fas fa-pen-fancy me-1"></i> Compose</span>
    </li>
    <?php endif; ?>
</ul>
