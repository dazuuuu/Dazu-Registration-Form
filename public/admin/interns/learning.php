<?php
// Admin / HR: learning modules for interns (videos, PDFs, links, quizzes, assignments) and who they are assigned to.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/InternService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/interns/learning.php');
$moduleId = (int) ($_GET['m'] ?? $_POST['module_id'] ?? 0);

/** Quiz text → JSON. "Q: question" starts a question, "- option" an answer, "* option" the right answer. */
function in_parse_quiz(string $text): array
{
    $out = [];
    $cur = null;
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^q[:.]\s*(.+)$/i', $line, $m)) {
            if ($cur) $out[] = $cur;
            $cur = ['q' => $m[1], 'options' => [], 'answer' => 0];
        } elseif ($cur && preg_match('/^([-*])\s*(.+)$/', $line, $m)) {
            if ($m[1] === '*') $cur['answer'] = count($cur['options']);
            $cur['options'][] = $m[2];
        }
    }
    if ($cur) $out[] = $cur;
    return array_values(array_filter($out, fn($q) => count($q['options']) >= 2));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $self . ($moduleId ? '?m=' . $moduleId : '');
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } else {
        try {
            switch ($_POST['act'] ?? '') {
                case 'module':
                    $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 200);
                    if ($title === '') { $_SESSION['error_msg'] = 'Enter a module title.'; break; }
                    $db->prepare('INSERT INTO intern_learning_modules (title, description, created_by) VALUES (?, ?, ?)')->execute([$title, trim((string) ($_POST['description'] ?? '')) ?: null, (int) $_SESSION['user_id']]);
                    $moduleId = (int) $db->lastInsertId();
                    $back = $self . '?m=' . $moduleId;
                    in_audit($db, null, 'learning_module_added', $title);
                    $_SESSION['success_msg'] = 'Module added. Now add its items and assign it.';
                    break;
                case 'item':
                    $type = in_array($_POST['type'] ?? '', ['video', 'pdf', 'quiz', 'assignment', 'link'], true) ? $_POST['type'] : 'link';
                    $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 200);
                    $urlIn = trim((string) ($_POST['url'] ?? ''));
                    if ($title === '') { $_SESSION['error_msg'] = 'Enter the item title.'; break; }
                    if ($urlIn !== '' && !preg_match('#^https?://#i', $urlIn)) { $_SESSION['error_msg'] = 'The link must start with http:// or https://'; break; }
                    $quiz = $type === 'quiz' ? in_parse_quiz((string) ($_POST['quiz'] ?? '')) : [];
                    if ($type === 'quiz' && !$quiz) { $_SESSION['error_msg'] = 'Write at least one question with two answers (see the example).'; break; }
                    $path = null;
                    if (($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $r = in_store_file($_FILES['file'], 'learning/' . $moduleId);
                        if (!$r['ok']) { $_SESSION['error_msg'] = $r['error']; break; }
                        $path = $r['path'];
                    }
                    if (in_array($type, ['video', 'pdf', 'link'], true) && !$urlIn && !$path) { $_SESSION['error_msg'] = 'Add a link or upload a file.'; break; }
                    $pos = (int) $db->query('SELECT COALESCE(MAX(position), 0) + 1 FROM intern_learning_items WHERE module_id = ' . $moduleId)->fetchColumn();
                    $db->prepare('INSERT INTO intern_learning_items (module_id, type, title, url, file_path, quiz_json, instructions, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                       ->execute([$moduleId, $type, $title, $urlIn ?: null, $path, $quiz ? json_encode($quiz, JSON_UNESCAPED_UNICODE) : null, trim((string) ($_POST['instructions'] ?? '')) ?: null, $pos]);
                    $_SESSION['success_msg'] = 'Item added.';
                    break;
                case 'item_delete':
                    $st = $db->prepare('SELECT file_path FROM intern_learning_items WHERE id = ? AND module_id = ?');
                    $st->execute([(int) $_POST['item_id'], $moduleId]);
                    if (($p = $st->fetchColumn()) !== false) {
                        in_file_delete($p ?: null);
                        $db->prepare('DELETE FROM intern_learning_items WHERE id = ?')->execute([(int) $_POST['item_id']]);
                        $db->prepare('DELETE FROM intern_learning_progress WHERE item_id = ?')->execute([(int) $_POST['item_id']]);
                    }
                    $_SESSION['success_msg'] = 'Item removed.';
                    break;
                case 'assign':
                    $ids = array_map('intval', (array) ($_POST['interns'] ?? []));
                    if ($dept = (int) ($_POST['department_id'] ?? 0)) {
                        $st = $db->prepare("SELECT id FROM interns WHERE department_id = ? AND status = 'active'");
                        $st->execute([$dept]);
                        $ids = array_merge($ids, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
                    }
                    $title = (string) $db->query('SELECT title FROM intern_learning_modules WHERE id = ' . $moduleId)->fetchColumn();
                    $ins = $db->prepare('INSERT IGNORE INTO intern_learning_assignments (module_id, intern_id) VALUES (?, ?)');
                    $n = 0;
                    foreach (array_unique($ids) as $iid) {
                        $ins->execute([$moduleId, $iid]);
                        if ($ins->rowCount() && ($in = in_get($db, $iid))) {
                            $n++;
                            in_notify_intern($db, $in, 'intern_learning', 'New learning module: ' . $title, 'Open Learning to start it.', 'intern/learning/index.php', false);
                        }
                    }
                    in_audit($db, null, 'learning_assigned', $title . ' → ' . $n);
                    $_SESSION['success_msg'] = $n ? 'Assigned to ' . $n . ' intern' . ($n === 1 ? '' : 's') . '.' : 'Nobody new was assigned.';
                    break;
                case 'unassign':
                    $db->prepare('DELETE FROM intern_learning_assignments WHERE module_id = ? AND intern_id = ?')->execute([$moduleId, (int) $_POST['intern_id']]);
                    $_SESSION['success_msg'] = 'Removed from the module.';
                    break;
            }
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = 'Could not save. Run System Updates (migration 075) first.';
        }
    }
    header('Location: ' . $back);
    exit;
}

$modules = [];
try {
    $modules = $db->query('SELECT m.*, (SELECT COUNT(*) FROM intern_learning_items i WHERE i.module_id = m.id) AS items,
                                  (SELECT COUNT(*) FROM intern_learning_assignments a WHERE a.module_id = m.id) AS assigned
                           FROM intern_learning_modules m ORDER BY m.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}
$module = null;
$items = $assigned = [];
if ($moduleId) {
    foreach ($modules as $m) if ((int) $m['id'] === $moduleId) $module = $m;
    if ($module) {
        $st = $db->prepare('SELECT * FROM intern_learning_items WHERE module_id = ? ORDER BY position, id');
        $st->execute([$moduleId]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC);
        $st = $db->prepare('SELECT intern_id FROM intern_learning_assignments WHERE module_id = ?');
        $st->execute([$moduleId]);
        $assigned = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
$interns = in_list($db, null, '', 'active');
$departments = in_departments($db, true);
$icons = ['video' => 'fa-circle-play', 'pdf' => 'fa-file-pdf', 'quiz' => 'fa-circle-question', 'assignment' => 'fa-file-pen', 'link' => 'fa-link'];
$page_title = 'Intern learning';
$breadcrumbs = [['label' => 'Interns', 'url' => url('admin/interns/index.php')], ['label' => 'Learning', 'active' => true]];
$inTab = 'learning';
ob_start();
?>
<div class="container-fluid py-3">
<?php require __DIR__ . '/_nav.php'; ?>
<div class="row g-3">
    <div class="col-lg-4">
        <form method="POST" class="in-card"><?php echo csrf_field(); ?><input type="hidden" name="act" value="module">
            <h5>New module</h5>
            <input name="title" class="form-control mb-2" placeholder="e.g. Cybersecurity Fundamentals" required maxlength="200">
            <textarea name="description" class="form-control mb-2" rows="2" placeholder="What the intern will learn (optional)"></textarea>
            <button class="btn btn-success">Add module</button>
        </form>
        <div class="in-card p-0">
            <?php if (!$modules): ?><div class="text-center text-muted py-4 small">No modules yet.</div><?php endif; ?>
            <div class="list-group list-group-flush">
                <?php foreach ($modules as $m): ?>
                    <a class="list-group-item list-group-item-action <?php echo (int) $m['id'] === $moduleId ? 'active' : ''; ?>" href="<?php echo $self . '?m=' . (int) $m['id']; ?>">
                        <div class="fw-semibold"><?php echo in_e($m['title']); ?></div>
                        <div class="small <?php echo (int) $m['id'] === $moduleId ? '' : 'text-muted'; ?>"><?php echo (int) $m['items']; ?> items · <?php echo (int) $m['assigned']; ?> interns</div></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <?php if (!$module): ?><div class="in-card text-muted">Choose or add a module.</div>
        <?php else: ?>
        <div class="in-card">
            <h5><?php echo in_e($module['title']); ?></h5>
            <?php if ($module['description']): ?><p class="small text-muted"><?php echo nl2br(in_e($module['description'])); ?></p><?php endif; ?>
            <ul class="list-group mb-3">
                <?php foreach ($items as $it): ?>
                <li class="list-group-item d-flex gap-2 align-items-center">
                    <i class="fas <?php echo $icons[$it['type']]; ?> text-success"></i>
                    <span class="flex-grow-1"><?php echo in_e($it['title']); ?> <span class="small text-muted">· <?php echo ucfirst($it['type']); ?><?php echo $it['type'] === 'quiz' ? ' · ' . count(json_decode((string) $it['quiz_json'], true) ?: []) . ' questions' : ''; ?></span></span>
                    <form method="POST" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="act" value="item_delete"><input type="hidden" name="module_id" value="<?php echo $moduleId; ?>"><input type="hidden" name="item_id" value="<?php echo (int) $it['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this item?')"><i class="fas fa-trash"></i></button></form>
                </li>
                <?php endforeach; ?>
                <?php if (!$items): ?><li class="list-group-item text-muted small">No items yet.</li><?php endif; ?>
            </ul>
            <form method="POST" enctype="multipart/form-data" class="border rounded-3 p-3"><?php echo csrf_field(); ?>
                <input type="hidden" name="act" value="item"><input type="hidden" name="module_id" value="<?php echo $moduleId; ?>">
                <div class="row g-2">
                    <div class="col-md-3"><select name="type" id="liType" class="form-select form-select-sm">
                        <option value="video">Video</option><option value="pdf">PDF</option><option value="link">Link</option><option value="quiz">Quiz</option><option value="assignment">Assignment</option></select></div>
                    <div class="col-md-9"><input name="title" class="form-control form-control-sm" placeholder="Title" required maxlength="200"></div>
                    <div class="col-md-6" data-for="video pdf link assignment"><input name="url" type="url" class="form-control form-control-sm" placeholder="Link (YouTube, Drive, website…)"></div>
                    <div class="col-md-6" data-for="video pdf link assignment"><input type="file" name="file" class="form-control form-control-sm"></div>
                    <div class="col-12" data-for="assignment quiz video pdf link"><textarea name="instructions" rows="2" class="form-control form-control-sm" placeholder="Instructions (optional)"></textarea></div>
                    <div class="col-12" data-for="quiz"><textarea name="quiz" rows="6" class="form-control form-control-sm font-monospace" placeholder="Q: What does a firewall do?&#10;- Speeds up the internet&#10;* Controls network traffic&#10;- Stores passwords&#10;&#10;Q: Next question…"></textarea>
                        <div class="form-text">Start each question with <code>Q:</code>. Put <code>-</code> before each answer and <code>*</code> before the right one. Pass mark: 50%.</div></div>
                </div>
                <button class="btn btn-sm btn-success mt-2">Add item</button>
            </form>
        </div>
        <form method="POST" class="in-card"><?php echo csrf_field(); ?><input type="hidden" name="act" value="assign"><input type="hidden" name="module_id" value="<?php echo $moduleId; ?>">
            <h5>Assigned interns</h5>
            <div class="row g-2 mb-2">
                <?php foreach ($interns as $i): $on = in_array((int) $i['id'], $assigned, true); ?>
                    <div class="col-md-6"><label class="form-check"><input type="checkbox" name="interns[]" value="<?php echo (int) $i['id']; ?>" class="form-check-input" <?php echo $on ? 'checked disabled' : ''; ?>>
                        <?php echo in_e($i['full_name']); ?> <span class="small text-muted"><?php echo in_e($i['department'] ?? ''); ?></span></label></div>
                <?php endforeach; ?>
                <?php if (!$interns): ?><div class="text-muted small">No active interns.</div><?php endif; ?>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <select name="department_id" class="form-select form-select-sm" style="width:auto"><option value="">…or a whole department</option>
                    <?php foreach ($departments as $d): ?><option value="<?php echo (int) $d['id']; ?>"><?php echo in_e($d['name']); ?></option><?php endforeach; ?></select>
                <button class="btn btn-sm btn-success">Assign</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
</div>
<script>
(function () {
    var t = document.getElementById('liType');
    if (!t) return;
    function sync() { document.querySelectorAll('[data-for]').forEach(function (el) { el.style.display = el.dataset.for.split(' ').indexOf(t.value) > -1 ? '' : 'none'; }); }
    t.addEventListener('change', sync); sync();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
