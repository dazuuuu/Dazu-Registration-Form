<?php
// public/components/progress/selfstudy_section.php — the "Self-study" section of the Student Progress card (read-only).
// It only shows what the self-study steps already saved. Its comment (Working well / Slowing down / Not working) is worked out with the same rules as the tutor's
// Students page and is shown ONLY here: it never changes the status word at the top of the card, which stays based on assignments and projects.
// $rows come from ssd_cards(), which already applied the visibility (admin: every course of the student; tutor: only the courses they own, were given access to, or added the student to).
require_once __DIR__ . '/../../../app/v1/services/SelfStudyProgress.php';

if (!function_exists('sp_selfstudy_html')) {
    function sp_selfstudy_html(array $rows, int $studentId, string $portal): string
    {
        if (!$rows) return '';                                       // no self-study course: the section is hidden
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $h = <<<'CSS'
<style>
.sps{background:#fff;border-radius:20px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:18px}
.sps-h{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;justify-content:space-between;margin-bottom:10px}
.sps-h h4{margin:0;font-size:19px;font-weight:700;display:flex;align-items:center;gap:10px}
.sps-new{background:#048C44;color:#fff;font-size:11px;font-weight:700;letter-spacing:.06em;border-radius:999px;padding:3px 10px}
.sps-n{font-size:13.5px;color:#6b7280}
.sps-hd,.sps-row{display:grid;grid-template-columns:minmax(0,1.9fr) minmax(0,1.5fr) minmax(0,.7fr) minmax(0,1.3fr) minmax(0,1.6fr);gap:16px;align-items:center}
.sps-hd{padding:10px 0;border-bottom:1px solid #ebebe6;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#6b7280;font-weight:600}
.sps-row{padding:14px 4px;border-bottom:1px solid #ebebe6;color:#1f2326;text-decoration:none;border-radius:12px}
.sps-row:last-child{border-bottom:0}
a.sps-row:hover{background:#f6f9f6;color:#1f2326}
.sps-c{display:flex;align-items:center;gap:12px;min-width:0}
.sps-c img,.sps-c .ph{width:48px;height:27px;border-radius:7px;object-fit:cover;background:#073d22;flex:none}
.sps-c b{font-size:15px;line-height:1.25}
.sps-m .l{display:flex;justify-content:space-between;font-size:13px;color:#6b7280;margin-bottom:5px}.sps-m .l b{color:#1f2326}
.sps-m .b{height:7px;background:#e8e8e4;border-radius:99px;overflow:hidden}.sps-m .b i{display:block;height:100%;background:#048C44;border-radius:99px}
.sps-m .b i.slowing{background:#d9930d}.sps-m .b i.notworking{background:#c0281d}
.sps-q{font-weight:700;font-size:14.5px}.sps-q .lab,.sps-x .lab{display:none}
.sps-x{font-size:14px;color:#3b4a40}
.sps-w .bd{display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:4px 11px;font-size:12.5px;font-weight:700;background:#E3F3EA;color:#037A3B}
.sps-w .bd i{width:8px;height:8px;border-radius:50%;background:currentColor}
.sps-w .bd.slowing{background:#fff4de;color:#8a5a00}.sps-w .bd.notworking{background:#fdecea;color:#b3261e}.sps-w .bd.notstarted{background:#eef0ee;color:#4a4f53}
.sps-w p{margin:5px 0 0;font-size:13.5px;color:#4a5055;line-height:1.4}
@media(max-width:991.98px){
  .sps{padding:16px}
  .sps-hd{display:none}
  .sps-row{grid-template-columns:minmax(0,1fr);gap:10px;border:1px solid #ebebe6;border-radius:16px;padding:14px;margin-bottom:12px}
  .sps-row:last-child{border-bottom:1px solid #ebebe6}
  .sps-q,.sps-x{display:flex;gap:8px}.sps-q .lab,.sps-x .lab{display:inline;color:#6b7280;font-weight:400}
}
</style>
CSS;
        $h .= '<section class="sps" aria-label="Self-study"><div class="sps-h"><h4>Self-study <span class="sps-new">NEW</span></h4><span class="sps-n">Shown here only. It does not change marks or the course average.</span></div>'
            . '<div class="sps-hd" aria-hidden="true"><span>Course</span><span>Modules</span><span>Latest quiz</span><span>Exams</span><span>Comment</span></div>';
        foreach ($rows as $r) {
            $img = ss_cover_attrs($r['cover']);
            $pic = $img ? '<img src="' . $e(ss_img_url($r['cover']['cover_400'] ?: ($r['cover']['cover_800'] ?: $r['cover']['cover_original']))) . '" alt="" loading="lazy">' : '<span class="ph"></span>';
            $k = $r['status']['key']; $pct = (int) $r['percent'];
            $inner = '<div class="sps-c">' . $pic . '<b>' . $e($r['title']) . '</b></div>'
                . '<div class="sps-m"><div class="l"><span>' . (int) $r['modules_passed'] . ' of ' . (int) $r['modules_total'] . ' modules passed</span><b>' . $pct . '%</b></div><div class="b"><i class="' . $e($k) . '" style="width:' . $pct . '%"></i></div></div>'
                . '<div class="sps-q"><span class="lab">Latest quiz</span>' . ($r['latest_quiz'] !== null ? (int) $r['latest_quiz'] . '%' : '<span style="font-weight:400;color:#9aa0a4">No quiz yet</span>') . '</div>'
                . '<div class="sps-x"><span class="lab">Exams</span>' . $e($r['exams']) . '</div>'
                . '<div class="sps-w"><span class="bd ' . $e($k) . '"><i></i>' . $e($r['status']['word']) . '</span><p>' . $e($r['status']['reason']) . '</p></div>';
            $h .= $r['can_open']
                ? '<a class="sps-row" href="' . $e(url($portal . '/selfstudy/students.php?id=' . (int) $r['course_id'] . '&open=' . $studentId)) . '" title="Open this student on the self-study Students page">' . $inner . '</a>'
                : '<div class="sps-row">' . $inner . '</div>';
        }
        return $h . '</section>';
    }
}
