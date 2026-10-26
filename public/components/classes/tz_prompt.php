<?php
// First-login time zone check for the tutor and student portals: reads the browser's zone
// (Intl.DateTimeFormat().resolvedOptions().timeZone), asks the person to confirm it, saves it via auth/timezone.php.
require_once __DIR__ . '/../../../app/v1/services/ClassCalendarService.php';
$tzDb = \Models\Connection::getInstance()->getConnection();
if (empty($_SESSION['preview']) && cal_installed($tzDb) && !cal_tz_confirmed($tzDb, (int) ($_SESSION['user_id'] ?? 0))):
    $tzNow = cal_user_tz($tzDb, (int) $_SESSION['user_id']);
?>
<div id="tzPrompt" class="alert alert-light border shadow-sm d-flex flex-wrap gap-2 align-items-center" style="position:fixed;left:50%;transform:translateX(-50%);bottom:calc(96px + env(safe-area-inset-bottom,0px));z-index:1060;max-width:560px;width:calc(100% - 24px);border-radius:16px">
    <i class="fas fa-earth-africa text-success fs-4"></i>
    <div class="flex-grow-1 small"><strong>Is your time zone <span id="tzGuess"><?php echo cal_e($tzNow); ?></span>?</strong><br>Class times are shown in it.</div>
    <select id="tzPick" class="form-select form-select-sm" style="max-width:220px"><?php foreach (DateTimeZone::listIdentifiers() as $z): ?><option <?php echo $z === $tzNow ? 'selected' : ''; ?>><?php echo cal_e($z); ?></option><?php endforeach; ?></select>
    <button type="button" class="btn btn-sm btn-success" id="tzSave">Confirm</button>
</div>
<script>
(function () {
    var box = document.getElementById('tzPrompt'), pick = document.getElementById('tzPick'), guess = document.getElementById('tzGuess');
    try {
        var b = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (b && Array.prototype.some.call(pick.options, function (o) { return o.value === b; })) { pick.value = b; guess.textContent = b; }
    } catch (e) {}
    pick.addEventListener('change', function () { guess.textContent = pick.value; });
    document.getElementById('tzSave').addEventListener('click', function () {
        var body = new URLSearchParams({ timezone: pick.value, ajax: '1', csrf_token: <?php echo json_encode(csrf_token()); ?> });
        fetch(<?php echo json_encode(url('auth/timezone.php')); ?>, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); }).then(function (r) { if (r.ok) { box.remove(); location.reload(); } });
    });
})();
</script>
<?php endif; ?>
