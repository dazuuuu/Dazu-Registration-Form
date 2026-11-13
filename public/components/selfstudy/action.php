<?php
// public/components/selfstudy/action.php — every Self-study course action (POST only, CSRF checked): save, publish, duplicate, archive, restore, delete, unpublish, move ownership.
// The permission is checked again here by the service, so hiding a button never is the only protection.
require __DIR__ . '/boot.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: ' . $sst_base . 'index.php'); exit; }
$id = (int) ($_POST['id'] ?? 0);
$back = $id ? $sst_base . 'edit.php?id=' . $id : $sst_base . 'edit.php';
if (!csrf_verify($_POST['csrf_token'] ?? null)) { sst_flash('error', 'Your session expired. Please try again.'); header('Location: ' . $back); exit; }
if (!$sst_ready) sst_deny(503, 'Self-study needs migration 107 first.', $sst_base);
$do = (string) ($_POST['do'] ?? '');
$now = time();

/** The details form, shared by "save" and "publish": saves the text, then the picture. Returns [ok, id, messages]. */
$saveAll = function () use ($db, $viewer, $id, $now): array {
    $course = $id ? sst_course($db, $viewer, $id) : null;
    if ($id && (!$course || !$course['can_edit'])) return [false, $id, 'You cannot change this course.'];
    if (!$id && $viewer['role'] !== 'tutor') return [false, 0, 'Only a tutor can create a self-study course.'];
    $in = ['title' => $_POST['title'] ?? '', 'short_description' => $_POST['short_description'] ?? '', 'level' => $_POST['level'] ?? '', 'checkpoint_minutes' => $_POST['checkpoint_minutes'] ?? 45, 'final_minutes' => $_POST['final_minutes'] ?? 60];
    $r = sst_save_details($db, $viewer, $id ?: null, $in, $now);
    if (!$r['ok']) { $_SESSION['sst_form'] = ['id' => $id, 'data' => ['title' => (string) $in['title'], 'short_description' => (string) $in['short_description'], 'level' => (string) $in['level']]]; return [false, $id, $r['error']]; }
    $cid = (int) $r['id'];
    $crop = isset($_POST['crop_w']) && $_POST['crop_w'] !== '' ? ['x' => (int) ($_POST['crop_x'] ?? 0), 'y' => (int) ($_POST['crop_y'] ?? 0), 'w' => (int) $_POST['crop_w']] : null;
    $cover = sst_apply_cover($db, $cid, $_FILES['cover'] ?? null, $crop, $now);
    if (!$cover['ok']) return [false, $cid, 'The details were saved, but the picture was not: ' . $cover['error']];
    return [true, $cid, 'Saved.' . (!empty($cover['message']) ? ' Picture ' . $cover['message'] . '.' : '')];
};

switch ($do) {
    case 'save':
        [$ok, $cid, $msg] = $saveAll();
        sst_flash($ok ? 'success' : 'error', $msg);
        header('Location: ' . $sst_base . 'edit.php' . ($cid ? '?id=' . $cid : ''));
        exit;
    case 'publish':
        [$ok, $cid, $msg] = $saveAll();
        if (!$ok) { sst_flash('error', $msg); header('Location: ' . $sst_base . 'edit.php' . ($cid ? '?id=' . $cid : '')); exit; }
        $p = sst_publish($db, $viewer, $cid, $now);                       // the server checks every item again
        sst_flash($p['ok'] ? 'success' : 'error', $p['ok'] ? 'Saved and published. Students it is shared with can see it now.' : $p['error']);
        header('Location: ' . $sst_base . 'edit.php?id=' . $cid);
        exit;
    case 'publish_only':                                              // from the Content / Questions tabs: nothing to save, only the checks and the switch
        $p = sst_publish($db, $viewer, $id, $now);
        sst_flash($p['ok'] ? 'success' : 'error', $p['ok'] ? 'Published. Students it is shared with can see it now.' : $p['error']);
        header('Location: ' . ((($_POST['back'] ?? '') === 'questions') ? $sst_base . 'questions.php?id=' . $id : $sst_base . 'content.php?id=' . $id));
        exit;
    case 'duplicate':
        $r = sst_duplicate($db, $viewer, $id, $now);
        sst_flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'A new draft was made. Students and progress were not copied.' : $r['error']);
        header('Location: ' . ($r['ok'] ? $sst_base . 'edit.php?id=' . (int) $r['id'] : $back));
        exit;
    case 'archive':
        $r = sst_archive($db, $viewer, $id, $now);
        sst_flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Archived. Students no longer see it; all progress is kept.' : $r['error']);
        break;
    case 'unarchive':
        $r = sst_unarchive($db, $viewer, $id, $now);
        sst_flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Restored.' : $r['error']);
        break;
    case 'unpublish':
        $r = sst_unpublish($db, $viewer, $id, $now);
        sst_flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Unpublished. It is a draft again; student progress is kept.' : $r['error']);
        break;
    case 'delete':
        $r = sst_delete($db, $viewer, $id);
        sst_flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'The draft was deleted.' : $r['error']);
        if ($r['ok']) { header('Location: ' . $sst_base . 'index.php'); exit; }
        break;
    case 'transfer':
        $r = sst_transfer($db, $viewer, $id, (int) ($_POST['tutor_id'] ?? 0), $now);
        sst_flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'The course now belongs to the chosen tutor.' : $r['error']);
        break;
    default:
        sst_flash('error', 'Unknown action.');
}
header('Location: ' . $back);
exit;
