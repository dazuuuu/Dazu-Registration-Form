<?php
// public/components/staffcalendar/reminders_bell.php — the small "Reminders" bell for the Staff Calendar page: a button with the unread count and a list that opens under it.
// It is a PARTIAL. It is not included by any existing page yet: adding it needs one line in public/components/staffcalendar/page.php (see the report), which this change did not touch.
// Needs $db, $actor and $sc_portal ('tutor' or 'superadmin') in scope, as on the calendar page. Shows nothing when reminders are not installed.
require_once __DIR__ . '/../../../app/v1/services/StaffReminders.php';
if (!srm_installed($db)) return;
if (!function_exists('csrf_token_value_for_bell')) {
    /** The page's security token value, for the small requests below. */
    function csrf_token_value_for_bell(): string { return preg_match('/value="([^"]+)"/', (string) csrf_field(), $m) ? $m[1] : ''; }
}
$rb_e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rb_unread = srm_inbox_unread($db, (int) $actor['uid']);
$rb_json = url($sc_portal . '/staffcalendar/reminders.php');
?>
<style>
.rb{position:relative;display:inline-block}
.rb-btn{position:relative;display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #d8ddd5;border-radius:999px;padding:7px 14px;font-weight:700;font-size:.86rem;color:#037A3B;cursor:pointer;font-family:inherit}
.rb-count{background:#048C44;color:#fff;border-radius:999px;font-size:.7rem;min-width:18px;height:18px;line-height:18px;text-align:center;padding:0 5px}.rb-count[hidden]{display:none}
.rb-panel{position:absolute;right:0;top:calc(100% + 6px);width:min(360px,calc(100vw - 32px));max-height:70vh;overflow:auto;background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(29,27,28,.18);z-index:60}
.rb-panel[hidden]{display:none}
.rb-head{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #e6e9e4;font-weight:800}
.rb-head button{background:none;border:0;color:#037A3B;font-weight:700;font-size:.8rem;cursor:pointer}
.rb-item{display:block;padding:10px 14px;border-top:1px solid #e6e9e4;text-decoration:none;color:#1d1b1c;font-size:.88rem;overflow-wrap:anywhere}.rb-item:first-of-type{border-top:0}
.rb-item.new{background:#E3F3EA}.rb-item b{display:block}.rb-item small{color:#6b7067}
.rb-empty{padding:16px 14px;color:#6b7067;font-size:.88rem}
@media (max-width:767.98px){.rb-panel{position:fixed;left:16px;right:16px;top:70px;width:auto}}
</style>
<div class="rb" id="rb">
    <button type="button" class="rb-btn" id="rbBtn" aria-haspopup="true" aria-expanded="false"><i class="fas fa-bell"></i> Reminders <span class="rb-count" id="rbCount"<?php echo $rb_unread > 0 ? '' : ' hidden'; ?>><?php echo (int) $rb_unread; ?></span></button>
    <div class="rb-panel" id="rbPanel" hidden></div>
</div>
<script>
(function () {
    var url = <?php echo json_encode($rb_json, JSON_UNESCAPED_SLASHES); ?>, tok = <?php echo json_encode(csrf_token_value_for_bell()); ?>;
    var btn = document.getElementById('rbBtn'), panel = document.getElementById('rbPanel'), count = document.getElementById('rbCount');
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function setCount(n) { count.textContent = n; count.hidden = !(n > 0); }
    function draw(d) {
        setCount(d.unread);
        var h = '<div class="rb-head"><span>Reminders</span>' + (d.unread > 0 ? '<button type="button" data-all="1">Mark all as read</button>' : '') + '</div>';
        if (!d.items.length) h += '<div class="rb-empty">No reminders yet.</div>';
        d.items.forEach(function (r) { h += '<a class="rb-item' + (r.read ? '' : ' new') + '" data-id="' + r.id + '" href="' + esc(r.url) + '"><b>' + esc(r.title) + '</b>' + esc(r.body) + '</a>'; });
        panel.innerHTML = h;
    }
    function load(post) {
        var o = post ? { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: post } : { credentials: 'same-origin' };
        return fetch(url + (post ? '' : '?json=1'), o).then(function (r) { return r.json(); }).then(draw).catch(function () {});
    }
    btn.addEventListener('click', function () { var open = panel.hidden; panel.hidden = !open; btn.setAttribute('aria-expanded', open ? 'true' : 'false'); if (open) load(); });
    panel.addEventListener('click', function (ev) {
        var all = ev.target.closest('[data-all]'); if (all) { load('op=read&id=0&csrf_token=' + encodeURIComponent(tok)); return; }
        var a = ev.target.closest('a[data-id]'); if (a) { try { navigator.sendBeacon(url, new URLSearchParams({ op: 'read', id: a.dataset.id, csrf_token: tok })); } catch (e) {} }
    });
    document.addEventListener('click', function (ev) { if (!panel.hidden && !document.getElementById('rb').contains(ev.target)) { panel.hidden = true; btn.setAttribute('aria-expanded', 'false'); } });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { panel.hidden = true; btn.setAttribute('aria-expanded', 'false'); } });
})();
</script>
