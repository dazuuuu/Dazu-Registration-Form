<?php
// Shared look and small helpers for the Assignments pages (tutor, student and admin).
// Include once; sets $extra_css (appends) and defines asg_e(), asg_chip(), asg_square(), asg_status_word().
use Models\AssignmentModel;

if (!function_exists('asg_e')) {
    function asg_e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    /** Status pill: symbol + word, never colour alone. */
    function asg_chip(string $state, ?string $text = null): string
    {
        [$label, $icon, $cls] = AssignmentModel::STATES[$state] ?? [$state, 'fa-circle', 'st-open'];
        return '<span class="asg-chip ' . $cls . '"><i class="fas ' . $icon . '" aria-hidden="true"></i>' . asg_e($text ?? $label) . '</span>';
    }

    /** Progress-grid square; title shows the marks when graded. */
    function asg_square(array $item, ?string $href = null): string
    {
        $state = $item['state'];
        [$label, $icon, $cls] = AssignmentModel::STATES[$state];
        $tip = 'Assignment ' . $item['index'] . ': ' . $label;
        if ($state === 'graded') $tip .= ' (' . AssignmentModel::resultText($item) . ')';
        if (!empty($item['late'])) $tip .= ', late';
        $inner = $state === 'extension' ? 'E' : '<i class="fas ' . $icon . '" aria-hidden="true"></i>';
        $tag = $href ? 'a href="' . asg_e($href) . '"' : 'span';
        return '<' . $tag . ' class="asg-sq ' . $cls . '" title="' . asg_e($tip) . '" aria-label="' . asg_e($tip) . '">' . $inner . '</' . ($href ? 'a' : 'span') . '>';
    }

    function asg_files_url(string $kind, int $id, bool $download = false): string
    {
        return url('assignments/file.php?t=' . $kind . '&id=' . $id . ($download ? '&download=1' : ''));
    }

    function asg_link(string $href, string $text, string $class = ''): string
    {
        return '<a href="' . asg_e($href) . '" target="_blank" rel="noopener noreferrer" class="' . $class . '">' . asg_e($text) . '</a>';
    }
}

$extra_css = ($extra_css ?? '') . <<<'CSS'
<style>
.asg-chip{display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:999px;font-size:.7rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap;border:1px solid transparent}
.asg-chip i{font-size:.72rem}
.st-graded{background:#2f7d14;color:#fff}
.st-submitted{background:var(--g,#44A024);color:#fff}
.st-returned{background:#fff4e5;color:#9a4b00;border-color:#f3c48b}
.st-progress{background:var(--g-50,#eef8e9);color:var(--g-d,#36851b);border-color:#cfe9c2}
.st-open{background:#fff;color:var(--g-d,#36851b);border-color:var(--g,#44A024)}
.st-extension{background:#373435;color:#fff}
.st-locked{background:#ecedea;color:#5b5f58}
.st-closed{background:#fbe3e1;color:#b42318;border-color:#f3b8b2}
.st-skipped{background:#f1f1ef;color:#6b7067;border-color:#dcdcd8}
.asg-sq{width:34px;height:34px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;text-decoration:none;border:2px solid transparent}
.asg-sq.st-open{border-color:var(--g,#44A024)}
.asg-sq.st-progress{border-color:var(--g,#44A024)}
.asg-sq.st-closed{border-color:#d9534f}
a.asg-sq:hover{outline:3px solid rgba(68,160,36,.25)}
.asg-legend{display:flex;flex-wrap:wrap;gap:12px 18px;font-size:.78rem;color:var(--muted,#6b7067)}
.asg-legend span{display:inline-flex;align-items:center;gap:6px}
.asg-legend .asg-sq{width:22px;height:22px;font-size:.62rem;border-radius:6px}
.asg-bar{height:8px;border-radius:99px;background:#e6e8e3;overflow:hidden;min-width:60px}
.asg-bar>i{display:block;height:100%;background:var(--g,#44A024);border-radius:99px}
.asg-bigbar{height:26px;border-radius:99px;background:#f0f1ed;overflow:hidden}
.asg-bigbar>div{height:100%;background:var(--g,#44A024);color:#fff;font-size:.75rem;font-weight:700;display:flex;align-items:center;padding:0 14px;white-space:nowrap;min-width:max-content;border-radius:99px}
.asg-card{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid var(--line,#e9ebe6);border-radius:14px;padding:14px 16px;margin-bottom:10px}
.asg-card.is-open{border:2px solid var(--g,#44A024)}
.asg-card.is-locked{background:#efefec;color:#6b7067}
.asg-card .num{flex:0 0 38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;background:var(--g-50,#eef8e9);color:var(--g-d,#36851b)}
.asg-card .num.done{background:var(--g,#44A024);color:#fff}
.asg-card.is-locked .num{background:#dcdcd8;color:#5b5f58}
.asg-card.is-closed .num{background:#fbe3e1;color:#b42318}
.asg-card .k{font-size:.74rem;font-weight:700;color:var(--g-d,#36851b)}
.asg-card.is-locked .k{color:#6b7067}
.asg-card .t{font-weight:600;font-size:.98rem}
.asg-card .s{font-size:.8rem;color:var(--muted,#6b7067)}
.asg-section{font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted,#6b7067);margin:18px 0 6px}
.asg-box{background:#f4f5f1;border-radius:12px;padding:12px 14px}
.asg-details p:last-child{margin-bottom:0}
.asg-pill{display:inline-flex;align-items:center;gap:6px;background:var(--g-50,#eef8e9);color:var(--g-d,#36851b);border-radius:999px;padding:4px 12px;font-size:.78rem;font-weight:600;text-decoration:none;margin:0 6px 6px 0}
.asg-panel{border:2px solid var(--g,#44A024);border-radius:16px;background:#fff;padding:18px}
.asg-fb{border-left:5px solid var(--g,#44A024);background:#f4f5f1;border-radius:12px;padding:14px 16px;margin-bottom:12px}
.asg-fb.dark{border-left-color:#373435}
.asg-fb .h{font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:4px}
.asg-ring{width:150px;height:150px;border-radius:50%;margin:0 auto;display:flex;align-items:center;justify-content:center;flex-direction:column;background:radial-gradient(closest-side,#fff 78%,transparent 79% 100%),conic-gradient(var(--g,#44A024) calc(var(--p)*1%),#dcefd3 0)}
.asg-ring b{font-size:2.4rem;line-height:1;color:var(--g-d,#36851b)}
.asg-shots{display:flex;flex-wrap:wrap;gap:10px}
.asg-shots a{display:block;width:150px;height:110px;border-radius:10px;overflow:hidden;background:#e6e8e3}
.asg-shots img{width:100%;height:100%;object-fit:cover}
.asg-grid th,.asg-grid td{text-align:center;vertical-align:middle}
.asg-grid th:first-child,.asg-grid td:first-child{text-align:left}
.asg-rte-bar{display:flex;gap:6px;flex-wrap:wrap;padding:6px;border:1px solid var(--line,#e9ebe6);border-bottom:0;border-radius:10px 10px 0 0;background:#f8f8f6}
.asg-rte-bar button{border:1px solid #d8dad4;background:#fff;border-radius:7px;padding:2px 10px;font-size:.78rem;font-weight:700}
.asg-rte{min-height:140px;border:1px solid var(--line,#e9ebe6);border-radius:0 0 10px 10px;padding:10px 12px;background:#fff;overflow:auto}
.asg-rte:focus{outline:2px solid rgba(68,160,36,.35)}
.asg-drag{cursor:grab;color:#9a9d96}
tr.asg-dragging{opacity:.4}
.asg-tabs .btn{border-radius:999px;font-weight:600;font-size:.82rem}
</style>
CSS;
