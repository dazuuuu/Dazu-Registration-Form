<?php
// Partial — included by other pages, never opened directly.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit('Not found'); }
// public/components/admin/shell_items.php — turns the admin menu (components/admin/sidebar.php, unchanged) into the items of the
// shared app shell used by the tutor and student portals. Every link of the menu becomes an item (icon, label, active, its group
// as the heading); the menu's POST buttons (portal previews) are returned as forms for the account menu.
// In: $adminSidebarHtml. Out: $adminShellItems, $adminShellForms.
$adminShellItems = [];
$adminShellForms = '';
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="utf-8"?><div id="sbroot">' . $adminSidebarHtml . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
libxml_clear_errors();
$xp = new DOMXPath($dom);
$groupLabel = function (DOMNode $n) use ($xp): ?string {
    for ($p = $n->parentNode; $p && $p instanceof DOMElement; $p = $p->parentNode) {
        if (preg_match('/\bcollapse\b/', (string) $p->getAttribute('class')) && ($id = $p->getAttribute('id'))) {
            $t = $xp->query('//a[@href="#' . $id . '"]//span')->item(0);
            if ($t) return trim($t->textContent);
        }
    }
    return null;
};
$seen = [];
$lastSection = null;
foreach ($xp->query('//*[@id="sbroot"]//a[contains(concat(" ", normalize-space(@class), " "), " nav-link ")] | //*[@id="sbroot"]//form[.//button[contains(@class,"nav-link")]]') as $el) {
    if ($el->nodeName === 'form') {
        $btn = $xp->query('.//button', $el)->item(0);
        $ic = $xp->query('.//i', $btn)->item(0);
        $label = trim($xp->query('.//span', $btn)->item(0)->textContent ?? '');
        $inner = '';
        foreach ($el->childNodes as $c) if ($c->nodeName === 'input') $inner .= $dom->saveHTML($c);
        $adminShellForms .= '<li><form method="POST" action="' . htmlspecialchars($el->getAttribute('action'), ENT_QUOTES) . '" target="_blank" class="m-0">' . $inner
            . '<button type="submit" class="dropdown-item"><i class="' . htmlspecialchars($ic ? $ic->getAttribute('class') : 'fas fa-eye', ENT_QUOTES) . ' me-2"></i>' . htmlspecialchars($label) . '</button></form></li>';
        continue;
    }
    $href = $el->getAttribute('href');
    if ($href === '' || $href[0] === '#' || isset($seen[$href])) continue;
    $seen[$href] = true;
    $span = $xp->query('.//span', $el)->item(0);
    $label = $span ? trim($span->textContent) : trim($el->textContent);
    if ($label === '') continue;
    $badge = null;
    foreach ($xp->query('.//span', $el) as $k => $s) if ($k > 0 && preg_match('/^\d+$/', trim($s->textContent))) $badge = (int) trim($s->textContent);
    $ic = $xp->query('.//i', $el)->item(0);
    $icon = 'fa-circle';
    if ($ic) foreach (preg_split('/\s+/', $ic->getAttribute('class')) as $cls) if (str_starts_with($cls, 'fa-')) { $icon = $cls; break; }
    $section = $groupLabel($el);
    $active = (bool) preg_match('/\bactive\b/', (string) $el->getAttribute('class'));
    $adminShellItems[] = ['k' . count($adminShellItems), $label, $icon ?: 'fa-circle', $href, $active, false, $section !== $lastSection ? $section : null, null, $badge];
    $lastSection = $section;
}
// Phone tab bar: Home plus three main places (links may be clean addresses without .php); everything else is under "More".
$tabWant = ['admin/dashboard' => 'Home', 'admin/registrations' => 'Students', 'admin/courses' => 'Courses', 'admin/tutors' => 'Tutors'];
foreach ($tabWant as $needle => $short) {
    foreach ($adminShellItems as &$it) {
        if (!$it[5] && str_contains($it[3], $needle)) { $it[5] = true; $it[7] = $short; break; }
    }
    unset($it);
}
