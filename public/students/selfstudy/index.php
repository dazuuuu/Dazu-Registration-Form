<?php
// public/students/selfstudy/index.php — Self-study: the short courses a tutor has shared with this student. Read only.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudy.php';

Middleware::requireStudent();
$pdo = \Models\Connection::getInstance()->getConnection();
$uid = (int) SessionModel::get('user_id');
require __DIR__ . '/_gate.php';

$courses = ss_courses_for_student($pdo, $uid);      // one query: courses, tutor and saved progress
$counts = ss_counts($courses);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$chips = ['all' => 'All', 'in_progress' => 'In progress', 'not_started' => 'Not started', 'completed' => 'Completed'];
$today = (new DateTime('now', new DateTimeZone('Africa/Nairobi')))->format('l, j F');

$page_title = 'Self-study';
ob_start();
?>
<style>
.ss-top{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin:4px 0 18px}
.ss-chips{display:flex;gap:8px;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;max-width:100%;padding:2px}
.ss-chips::-webkit-scrollbar{display:none}
.ss-chip{flex:0 0 auto;border:1px solid var(--line,#e6e6e0);background:#fff;color:#1f2326;border-radius:999px;padding:8px 18px;font:500 14px/1 inherit;cursor:pointer;white-space:nowrap}
.ss-chip.on{background:#048C44;border-color:#048C44;color:#fff}
.ss-chip:focus-visible{outline:2px solid #037A3B;outline-offset:2px}
.ss-search{position:relative;flex:0 1 340px;min-width:240px}
.ss-search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#8a8f93;pointer-events:none}
.ss-search input{width:100%;border:1px solid var(--line,#e6e6e0);border-radius:12px;padding:11px 14px 11px 38px;background:#fff;font-size:14px}
.ss-search input:focus{outline:2px solid #E3F3EA;border-color:#048C44}
.ss-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}
@media(max-width:991.98px){.ss-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:599.98px){.ss-grid{grid-template-columns:minmax(0,1fr)}.ss-top{flex-direction:column;align-items:stretch}.ss-search{flex:1 1 auto;min-width:0;width:100%}}
.ss-card{background:#fff;border-radius:20px;box-shadow:0 2px 14px rgba(20,30,25,.07);overflow:hidden;display:flex;flex-direction:column}
.ss-card[hidden]{display:none}
.ss-cover{position:relative;aspect-ratio:16/9;background:#073d22;overflow:hidden}
.ss-cover img{width:100%;height:100%;object-fit:cover;display:block}
.ss-cover.none{background:linear-gradient(135deg,#037A3B,#0b4a2a)}
.ss-cover.none.l-intermediate{background:linear-gradient(135deg,#2c5ea8,#173766)}
.ss-cover.none.l-advanced{background:linear-gradient(135deg,#373435,#0b6b37)}
.ss-cover.none i{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:44px;color:rgba(255,255,255,.55)}
.ss-tag{position:absolute;left:14px;top:14px;color:#fff;font:700 11px/1 inherit;letter-spacing:.06em;text-transform:uppercase;padding:8px 10px;border-radius:8px}
.ss-tag.beginner{background:#048C44}.ss-tag.intermediate{background:#C98400}.ss-tag.advanced{background:#373435}
.ss-body{padding:16px 18px 18px;display:flex;flex-direction:column;gap:8px;flex:1}
.ss-meta{display:flex;justify-content:space-between;gap:8px;font-size:12.5px;color:#6b7075}
.ss-meta span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
.ss-card h3{font-size:17px;font-weight:700;margin:2px 0 0;color:#1f2326}
.ss-desc{font-size:13.5px;color:#5f6468;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.8em;line-height:1.4;margin:0}
.ss-prog{display:flex;justify-content:space-between;font-size:12.5px;color:#6b7075;margin-top:6px}
.ss-prog b{color:#1f2326}
.ss-bar{height:6px;background:#e9ebe8;border-radius:99px;overflow:hidden}
.ss-bar i{display:block;height:100%;background:#048C44;border-radius:99px}
.ss-foot{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:auto;padding-top:12px;font-size:13px;color:#6b7075;min-height:56px}
.ss-btn{background:#048C44;color:#fff;border-radius:12px;padding:10px 22px;font-weight:600;font-size:14px;text-decoration:none;box-shadow:0 4px 10px rgba(4,140,68,.25);white-space:nowrap}
.ss-btn:hover{background:#037A3B;color:#fff}
.ss-done{background:#E3F3EA;color:#037A3B;border-radius:999px;padding:9px 16px;font-weight:600;font-size:13.5px;white-space:nowrap}
.ss-empty{background:#fff;border-radius:20px;box-shadow:0 2px 14px rgba(20,30,25,.07);padding:48px 20px;text-align:center;color:#6b7075}
.ss-empty i{font-size:34px;color:#048C44;opacity:.45;display:block;margin-bottom:10px}
.ss-note{font-size:13px;color:#6b7075;margin-top:18px}
</style>

<div class="ss-top">
    <div>
        <h1 class="h4 fw-bold mb-0">Self-study</h1>
        <div class="text-muted small">Short courses you can study at your own pace &middot; <?php echo $e($today); ?></div>
    </div>
</div>

<?php if (!$courses): ?>
    <div class="ss-empty"><i class="fas fa-laptop-code"></i><b>Nothing here yet</b><br>Your tutor has not shared any self-study courses with you yet.</div>
<?php else: ?>
<div class="ss-top">
    <div class="ss-chips" role="tablist" aria-label="Filter courses">
        <?php foreach ($chips as $k => $label): ?>
            <button type="button" class="ss-chip <?php echo $k === 'all' ? 'on' : ''; ?>" data-f="<?php echo $e($k); ?>" aria-pressed="<?php echo $k === 'all' ? 'true' : 'false'; ?>"><?php echo $e($label); ?> <span data-n="<?php echo $e($k); ?>"><?php echo (int) $counts[$k]; ?></span></button>
        <?php endforeach; ?>
    </div>
    <label class="ss-search"><i class="fas fa-search"></i><input type="search" id="ssQ" placeholder="Search self-study courses" aria-label="Search self-study courses" autocomplete="off"></label>
</div>

<div class="ss-grid" id="ssGrid">
<?php foreach ($courses as $c): $cv = ss_cover_attrs($c); $btn = ss_button($c);
      $mods = (int) $c['module_count']; ?>
    <article class="ss-card" data-state="<?php echo $e($c['state']); ?>" data-title="<?php echo $e(mb_strtolower($c['title'])); ?>">
        <div class="ss-cover <?php echo $cv ? '' : 'none l-' . $e($c['level']); ?>">
            <?php if ($cv): ?>
                <img src="<?php echo $e($cv['src']); ?>"<?php echo $cv['srcset'] ? ' srcset="' . $e($cv['srcset']) . '" sizes="' . $e($cv['sizes']) . '"' : ''; ?> alt="" loading="lazy" decoding="async">
            <?php else: ?><i class="fas fa-laptop-code" aria-hidden="true"></i><?php endif; ?>
            <span class="ss-tag <?php echo $e($c['level']); ?>"><?php echo $e(SS_LEVELS[$c['level']]); ?></span>
        </div>
        <div class="ss-body">
            <div class="ss-meta"><span><i class="fas fa-user me-1"></i><?php echo $e($c['tutor_name']); ?></span><span>Self-paced<?php echo $mods > 0 ? ' &middot; ' . $mods . ' module' . ($mods === 1 ? '' : 's') : ''; ?></span></div>
            <h3><?php echo $e($c['title']); ?></h3>
            <p class="ss-desc"><?php echo $e($c['short_description']); ?></p>
            <div class="ss-prog"><span>Progress</span><b><?php echo (int) $c['percent']; ?>%</b></div>
            <div class="ss-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $c['percent']; ?>"><i style="width:<?php echo (int) $c['percent']; ?>%"></i></div>
            <div class="ss-foot">
                <span><?php echo $e($c['last_text']); ?></span>
                <?php if ($c['state'] === 'completed'): ?>
                    <a class="ss-done" href="course.php?id=<?php echo (int) $c['id']; ?>"><i class="fas fa-check me-1"></i>Completed</a>
                <?php else: ?>
                    <a class="ss-btn" href="course.php?id=<?php echo (int) $c['id']; ?>"><?php echo $e($btn); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </article>
<?php endforeach; ?>
</div>
<div class="ss-empty" id="ssNone" hidden><i class="fas fa-search"></i>No courses match. Try another word or filter.</div>
<p class="ss-note">You only see the courses your tutor has shared with you. Your progress is saved automatically, so you always come back to where you stopped.</p>

<script>
(function () {
    var cards = [].slice.call(document.querySelectorAll('.ss-card')), chips = [].slice.call(document.querySelectorAll('.ss-chip')),
        q = document.getElementById('ssQ'), none = document.getElementById('ssNone'), f = 'all';
    function run() {
        var term = q.value.trim().toLowerCase(), n = { all: 0, in_progress: 0, not_started: 0, completed: 0 }, shown = 0;
        cards.forEach(function (c) {
            var hit = !term || c.dataset.title.indexOf(term) !== -1;
            if (hit) { n.all++; n[c.dataset.state]++; }
            var show = hit && (f === 'all' || c.dataset.state === f);
            c.hidden = !show; if (show) shown++;
        });
        Object.keys(n).forEach(function (k) { var el = document.querySelector('[data-n="' + k + '"]'); if (el) el.textContent = n[k]; });
        none.hidden = shown > 0;
    }
    chips.forEach(function (b) { b.addEventListener('click', function () {
        f = b.dataset.f; chips.forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); }); run(); }); });
    q.addEventListener('input', run);
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
