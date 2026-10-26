<?php
// Admin view of a person's passport photo, T-shirt size and academic documents (student or tutor pages).
// Expects: $docUserId (users.id). Read-only; the person uploads from their own portal (My Documents).
require_once __DIR__ . '/../../../app/v1/helpers/lms_files.php';

$docDb = \Models\Connection::getInstance()->getConnection();
$docUserId = (int) ($docUserId ?? 0);
$docRows = $docUserId ? docs_list($docDb, $docUserId) : [];
$docProf = $docUserId ? docs_profile($docDb, $docUserId) : [];
?>
<div class="card mb-3"><div class="card-header fw-semibold"><i class="fas fa-id-card text-success me-1"></i>Documents, photo &amp; T-shirt</div>
<?php if (!$docUserId): ?>
    <div class="card-body small text-muted">No portal account yet, so nothing has been submitted.</div>
<?php else: ?>
    <div class="card-body d-flex gap-3 align-items-center border-bottom">
        <?php if (!empty($docProf['profile_photo_path'])): ?>
            <a href="<?php echo htmlspecialchars(settings_asset_url($docProf['profile_photo_path'])); ?>" target="_blank"><img src="<?php echo htmlspecialchars(settings_asset_url($docProf['profile_photo_path'])); ?>" alt="Passport photo" class="rounded border" style="width:64px;height:76px;object-fit:cover"></a>
        <?php else: ?>
            <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted small text-center" style="width:64px;height:76px">No photo</div>
        <?php endif; ?>
        <div class="small">
            <div>T-shirt size: <strong class="<?php echo empty($docProf['tshirt_size']) ? 'text-danger' : ''; ?>"><?php echo htmlspecialchars($docProf['tshirt_size'] ?? '') ?: 'Not given'; ?></strong></div>
            <div><?php echo count($docRows); ?> document<?php echo count($docRows) === 1 ? '' : 's'; ?> submitted</div>
        </div>
    </div>
    <?php if ($docRows): ?>
    <ul class="list-group list-group-flush">
        <?php foreach ($docRows as $d): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center small gap-2">
            <span><i class="fas fa-file-pdf text-danger me-1"></i><?php echo htmlspecialchars($d['doc_name']); ?>
                <span class="text-muted">· <?php echo date('d M Y', strtotime($d['uploaded_at'])); ?></span></span>
            <span class="d-flex gap-1">
                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo url('documents/file.php?id=' . (int) $d['id']); ?>"><i class="fas fa-eye"></i></a>
                <a class="btn btn-sm btn-outline-success" href="<?php echo url('documents/file.php?download=1&id=' . (int) $d['id']); ?>"><i class="fas fa-download"></i></a>
            </span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
<?php endif; ?>
</div>
