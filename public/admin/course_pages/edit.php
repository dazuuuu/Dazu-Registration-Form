<?php
// Admin → Course pages → Edit: the page builder. Address, SEO (meta title, description, keywords) with a share-card preview,
// hero heading/text, preview video, the sections, the carousel cards, Live/Hidden. "Write again from the course" redoes the content.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/services/CoursePageService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$me = (int) ($_SESSION['user_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
$page = cp_installed($db) ? cp_get($db, $id) : null;
if (!$page) { header('Location: ' . url('admin/course_pages/index.php')); exit; }
$self = url('admin/course_pages/edit.php?id=' . $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'regenerate') {
        cp_regenerate($db, $id, $me);
        $_SESSION['success_msg'] = 'Content written again from the course. The address and Live/Hidden did not change.';
    } else {
        $r = cp_save($db, $id, $_POST, $me);
        $_SESSION[$r['ok'] ? 'success_msg' : 'error_msg'] = $r['ok'] ? 'Saved. The page is updated.' : $r['error'];
    }
    header('Location: ' . $self);
    exit;
}
$course = (new \Models\CourseModel($db))->getById((int) $page['course_id']);
$sections = json_decode((string) $page['sections_json'], true) ?: [];
$cards = json_decode((string) $page['cards_json'], true) ?: [];
$link = cp_url($page);
$base = course_share_absolute_url(url(''));
$cover = $course ? course_og_image_url($course) : '';
$page_title = 'Course page · ' . ($course['title'] ?? '');
ob_start();
?>
<style>.cp-count{font-size:.75rem;color:#6b7280}.cp-count.over{color:#b45309;font-weight:600}.cp-card{border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;max-width:420px;background:#fff}.cp-card img{width:100%;aspect-ratio:1.91/1;object-fit:cover;display:block}</style>
<div class="container-fluid py-3">
  <?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $k => $c): if (!empty($_SESSION[$k])): ?><div class="alert alert-<?php echo $c; ?>"><?php echo $e($_SESSION[$k]); unset($_SESSION[$k]); ?></div><?php endif; endforeach; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><a href="<?php echo url('admin/course_pages/index.php'); ?>" class="small">‹ Course pages</a><h3 class="mb-0"><?php echo $e($course['title'] ?? 'Course'); ?></h3></div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?php echo $e($link); ?>"><i class="fas fa-up-right-from-square me-1"></i>Open page</a>
      <button type="button" class="btn btn-outline-success btn-sm" onclick="navigator.clipboard.writeText(<?php echo $e(json_encode($link)); ?>);this.innerText='Link copied'"><i class="fas fa-copy me-1"></i>Copy link</button>
      <form method="POST" onsubmit="return confirm('Write the headings, sections, cards and SEO again from the course? Your edits to them will be replaced.')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="regenerate"><button class="btn btn-outline-dark btn-sm"><i class="fas fa-wand-magic-sparkles me-1"></i>Write again from the course</button></form>
    </div>
  </div>
  <form method="POST" id="cpf"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save">
  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card p-3 mb-3">
        <h6 class="fw-bold">Address and visibility</h6>
        <div class="input-group input-group-sm mb-2"><span class="input-group-text"><?php echo $e($base); ?></span><input name="slug" class="form-control" value="<?php echo $e($page['slug']); ?>" maxlength="100" pattern="[a-zA-Z0-9-]+" required></div>
        <label class="form-check small"><input type="checkbox" class="form-check-input" name="is_published" value="1" <?php echo $page['is_published'] ? 'checked' : ''; ?>> Live (untick to hide the page; the link then shows "Page not found")</label>
      </div>
      <div class="card p-3 mb-3">
        <h6 class="fw-bold">Top of the page</h6>
        <label class="form-label small fw-semibold mb-1">Main heading</label><input name="hero_heading" class="form-control form-control-sm mb-2" maxlength="150" required value="<?php echo $e($page['hero_heading']); ?>">
        <label class="form-label small fw-semibold mb-1">Intro text</label><textarea name="hero_text" class="form-control form-control-sm mb-2" rows="2" maxlength="400"><?php echo $e($page['hero_text']); ?></textarea>
        <label class="form-label small fw-semibold mb-1">Preview video <span class="text-muted fw-normal">(YouTube, Vimeo or an .mp4 link; empty = the course cover picture)</span></label>
        <input name="video_url" class="form-control form-control-sm" maxlength="500" placeholder="https://www.youtube.com/watch?v=…" value="<?php echo $e($page['video_url']); ?>">
      </div>
      <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center"><h6 class="fw-bold mb-0">Sections</h6><button type="button" class="btn btn-sm btn-outline-success" id="addSec">+ Add section</button></div>
        <div class="small text-muted mb-2">Lines starting with <code>- </code> become a list. <code>Q:</code> then <code>A:</code> lines become questions and answers. Leave a section empty to remove it.</div>
        <div id="secs">
        <?php foreach (array_merge($sections, [['title' => '', 'body' => '']]) as $s): ?>
          <div class="border rounded p-2 mb-2 sec"><input name="section_title[]" class="form-control form-control-sm fw-semibold mb-1" placeholder="Section heading" maxlength="120" value="<?php echo $e($s['title']); ?>">
            <textarea name="section_body[]" class="form-control form-control-sm" rows="<?php echo max(3, min(10, substr_count((string) $s['body'], "\n") + 2)); ?>" placeholder="Text"><?php echo $e($s['body']); ?></textarea></div>
        <?php endforeach; ?>
        </div>
      </div>
      <div class="card p-3 mb-3">
        <h6 class="fw-bold mb-1">Carousel cards</h6>
        <div class="small text-muted mb-2">One card per line: <code>Title | short text</code>. Shown after "What you will learn".</div>
        <textarea name="cards" class="form-control form-control-sm" rows="8"><?php echo $e(implode("\n", array_map(fn($c) => $c['title'] . ' | ' . $c['text'], $cards))); ?></textarea>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card p-3 mb-3" style="position:sticky;top:80px">
        <h6 class="fw-bold">SEO and sharing</h6>
        <label class="form-label small fw-semibold mb-1">Meta title</label><input name="meta_title" id="mt" class="form-control form-control-sm" maxlength="70" required value="<?php echo $e($page['meta_title']); ?>">
        <div class="cp-count mb-2" data-for="mt" data-best="60"></div>
        <label class="form-label small fw-semibold mb-1">Meta description</label><textarea name="meta_description" id="md" class="form-control form-control-sm" rows="3" maxlength="170" required><?php echo $e($page['meta_description']); ?></textarea>
        <div class="cp-count mb-2" data-for="md" data-best="160"></div>
        <label class="form-label small fw-semibold mb-1">Keywords <span class="text-muted fw-normal">(comma separated)</span></label><textarea name="keywords" class="form-control form-control-sm mb-3" rows="2" maxlength="255"><?php echo $e($page['keywords']); ?></textarea>
        <div class="small fw-semibold mb-1">How the link looks when shared</div>
        <div class="cp-card"><img src="<?php echo $e($cover); ?>" alt=""><div class="p-2"><div class="small text-muted text-uppercase"><?php echo $e(parse_url($link, PHP_URL_HOST)); ?></div><div class="fw-bold" id="pvT"></div><div class="small text-muted" id="pvD"></div></div></div>
        <div class="small text-muted mt-1">Google shows the same title and description.</div>
        <button class="btn btn-success w-100 mt-3"><i class="fas fa-save me-1"></i>Save</button>
      </div>
    </div>
  </div>
  </form>
</div>
<script>
(function () {
  function count() { document.querySelectorAll('.cp-count').forEach(function (c) { var f = document.getElementById(c.dataset.for), n = f.value.length, b = +c.dataset.best;
    c.textContent = n + ' / ' + b + ' characters' + (n > b ? ' (may be cut off in Google)' : ''); c.classList.toggle('over', n > b); });
    document.getElementById('pvT').textContent = document.getElementById('mt').value; document.getElementById('pvD').textContent = document.getElementById('md').value; }
  document.getElementById('cpf').addEventListener('input', count); count();
  document.getElementById('addSec').onclick = function () { var s = document.querySelector('#secs .sec:last-child').cloneNode(true); s.querySelectorAll('input,textarea').forEach(function (x) { x.value = ''; }); document.getElementById('secs').appendChild(s); };
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
