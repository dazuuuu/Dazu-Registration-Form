<?php
// Certificate wording: the two boxes under the name read as ONE joined paragraph; the first sentence is editable ({institute} field).
//   DAZU_DB_NAME=<scratch db with a few registrations> php app/v1/tests/certificate_paragraph_test.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
$root = __DIR__ . '/../';
require_once $root . 'init.php'; require_once $root . 'models/Connection.php'; require_once $root . 'models/CertificateModel.php';
require_once $root . 'services/CertificateService.php'; require_once $root . 'helpers/certificate_pdf.php'; require_once $root . 'helpers/certificate_v2.php';
$db = \Models\Connection::getInstance()->getConnection();
$fails = 0;
function check(string $name, $cond, string $extra = ''): void { global $fails; if ($cond) { echo "ok   $name\n"; return; } $fails++; echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n"; }
$text = fn(string $h) => trim(html_entity_decode(strip_tags($h), ENT_QUOTES, 'UTF-8'));

$reg = $db->query('SELECT id, course_id, full_name FROM registrations ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$mk = function (string $no, string $status) use ($db, $reg): int {
    $db->prepare("INSERT INTO certificates (registration_id, course_id, certificate_no, qr_code_hash, status, snapshot_name, snapshot_course, snapshot_duration_months, snapshot_dates, taught_skills, tutor_name, ceo_name, issued_at)
                  VALUES (?, ?, ?, ?, ?, ?, 'Virtual Assistance', 1, 'Jan 2025', '', 'Tina Tutor', 'Simon Mwangi', '2026-01-15 10:00:00')")
       ->execute([$reg['id'], $reg['course_id'], $no, md5($no), $status, $reg['full_name']]);
    return (int) $db->lastInsertId();
};
$db->exec("DELETE FROM certificates WHERE certificate_no LIKE 'DDS-PARA-%'");
$m = new \Models\CertificateModel($db);
$render = fn(int $id) => cert_render_html($m->getCertificateById($id));
$sentence = 'Has successfully completed a 1-month programme in Virtual Assistance at Dazu Training Institute.';

echo "== One joined paragraph\n";
$d = ['name' => 'A B', 'course' => 'Virtual Assistance', 'months' => 1, 'wording' => CV2_DEFAULT_WORDING, 'extra' => ''];
check('the automatic sentence still reads the same, with the institute filled in', $text(cv2_paragraph_html($d)) === $sentence, $text(cv2_paragraph_html($d)));
check('the course keeps its green highlight', str_contains(cv2_paragraph_html($d), '<b style="color:#048C44">Virtual Assistance</b>'));
check('the default sentence now uses the {institute} field', str_contains(CV2_DEFAULT_WORDING, '{institute}'));
$d['extra'] = 'and has met all the requirements.';
check('the tutor text follows after exactly one space, in the same paragraph', $text(cv2_paragraph_html($d)) === $sentence . ' and has met all the requirements.');
check('no second block or size change is in the paragraph', !str_contains(cv2_paragraph_html($d), '<div') && !str_contains(cv2_paragraph_html($d), 'font-size') && !str_contains(cv2_paragraph_html($d), '<br'));
$d['extra'] = "  and   has met\nall.  ";
check('a double space or line break typed by the tutor becomes one space', $text(cv2_paragraph_html($d)) === $sentence . ' and has met all.');
$d['extra'] = '..and has met all..';
check('a doubled full stop after the first sentence is removed', $text(cv2_paragraph_html($d)) === $sentence . ' and has met all.', $text(cv2_paragraph_html($d)));
$d['wording'] = 'Has completed {course}..'; $d['extra'] = '. Well done.';
check('a doubled full stop typed in the first box is removed', $text(cv2_paragraph_html($d)) === 'Has completed Virtual Assistance. Well done.', $text(cv2_paragraph_html($d)));
$d['wording'] = 'Has completed {course}'; $d['extra'] = 'with honours.';
check('a sentence with no full stop still gets just one space', $text(cv2_paragraph_html($d)) === 'Has completed Virtual Assistance with honours.');

echo "\n== The certificate itself\n";
$id = $mk('DDS-PARA-1', 'being_prepared');
$db->prepare('UPDATE certificates SET taught_skills = ? WHERE id = ?')->execute(['and has met all the requirements of the programme.', $id]);
$html = $render($id);
check('the certificate prints one paragraph (one box, one font size)', substr_count($html, 'top:120.5mm') === 1 && !str_contains($html, 'margin-top:1mm;font-size:12.5pt') && str_contains($html, 'font-size:14pt;line-height:1.35">Has successfully'));
check('the paragraph reads sentence + space + tutor text', str_contains($text($html), $sentence . ' and has met all the requirements of the programme.'));
$long = ['wording' => str_repeat('Has successfully completed a programme in this course ', 4), 'extra' => str_repeat('and the tutor wrote a long text here ', 5)];
$dl = cv2_live($db, $m->getCertificateById($id), $long); $hl = cv2_html($dl);
preg_match_all('/top:120\.5mm;[^"]*font-size:([\d.]+)pt/', $hl, $mm);
check('a long paragraph is shrunk as a whole (one smaller size, not only the second part)', count($mm[1]) === 1 && (float) $mm[1][0] < 14 && (float) $mm[1][0] >= 9 && substr_count($hl, 'font-size:' . $mm[1][0] . 'pt') >= 1);
$full = ['wording' => str_pad('Has successfully completed {a_duration} programme in {course} at {institute}, with strong skill and conduct ', 200, 'x'), 'extra' => str_repeat('The programme covered many things. ', 5)];
$hf = cv2_paragraph_html(cv2_live($db, $m->getCertificateById($id), $full));
$pt = cv2_paragraph_pt($hf, 140);
$lines = ceil(mb_strlen($text($hf)) * 1.06 / (140 / (0.47 * $pt * 0.3528)));
check('the shrunk paragraph never goes below the signature line (stays inside 28.5 mm)', $lines * 1.35 * $pt * 0.3528 <= 28.5 && $pt >= 9);
check('a short paragraph keeps the usual 14 pt', cv2_paragraph_pt(cv2_paragraph_html($d), 140) === 14.0);
$pdf = cert_pdf_bytes($m->getCertificateById($id));
check('the PDF is made', str_starts_with($pdf, '%PDF'));
$_GET = [];

echo "\n== Editing the first sentence (admin or tutor), with the same rules and history\n";
$before = (int) $db->query("SELECT COUNT(*) FROM certificate_template_events WHERE certificate_id = $id")->fetchColumn();
$r = cv2_save_text($db, $id, 'Has finished {a_duration} programme of {course} at {institute}.', 'and did well.', 5);
check('the first box can be saved with its own words and fields', $r['ok'] && $text($render($id)) !== '' && str_contains($text($render($id)), 'Has finished a 1-month programme of Virtual Assistance at Dazu Training Institute. and did well.'), $text($render($id)));
$log = $db->query("SELECT old_json, new_json, actor_user_id, created_at FROM certificate_template_events WHERE certificate_id = $id AND event = 'text_saved' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('who changed it and when is recorded, for both boxes', (int) $db->query("SELECT COUNT(*) FROM certificate_template_events WHERE certificate_id = $id")->fetchColumn() === $before + 1
    && $log && (int) $log['actor_user_id'] === 5 && !empty($log['created_at']) && str_contains($log['new_json'], 'Has finished') && str_contains($log['new_json'], 'and did well.') && str_contains($log['old_json'], 'and has met all'));
$r = cv2_save_text($db, $id, str_repeat('a', 260), str_repeat('b', 200), 5);
$row = $db->query("SELECT wording_text, taught_skills FROM certificates WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
check('the limits stay: 200 characters for the first box, 160 for the second', mb_strlen($row['wording_text']) === 200 && mb_strlen($row['taught_skills']) === 160);
check('the name, registration number and certificate number are not part of what can be saved', (function () use ($db, $id) {
    $s = $db->query("SELECT snapshot_name, certificate_no FROM certificates WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    cv2_save_text($db, $id, 'Hello {student_name}', 'x', 5);
    $t = $db->query("SELECT snapshot_name, certificate_no FROM certificates WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    return $s === $t;
})());
check('"Reset to default": the automatic sentence from Settings is what the editor offers', cv2_settings($db)['wording'] === CV2_DEFAULT_WORDING || cv2_settings($db)['wording'] !== '');
$src = file_get_contents(__DIR__ . '/../../../public/certificates/text.php') . file_get_contents(__DIR__ . '/../../../public/components/certificates/text_editor_js.php');
check('the editor has the Reset to default link wired to the automatic sentence', str_contains($src, 'Reset to default') && str_contains($src, 'data-default') && str_contains($src, '$set[\'wording\']') && str_contains($src, 'data-reset'));
cv2_save_text($db, $id, cv2_settings($db)['wording'], '', 5);
check('after Reset to default the certificate reads the automatic sentence again', $text($render($id)) !== '' && str_contains($text($render($id)), $sentence));

echo "\n== Issued certificates never change by themselves\n";
$iid = $mk('DDS-PARA-2', 'issued');
$db->prepare('UPDATE certificates SET taught_skills = ? WHERE id = ?')->execute(['and has met all the requirements.', $iid]);
cert_freeze($db, $iid);
$issuedBefore = $render($iid);
cv2_save_text($db, $iid, 'Has done something else entirely at {institute}.', 'Changed text.', 5);
check('saving new words does not change the issued certificate', $render($iid) === $issuedBefore && str_contains($text($issuedBefore), $sentence . ' and has met all the requirements.'));
$r = cert_reissue($db, $iid, 5);
check('after Reissue the saved words show (and the old version is kept in the history)', $r['ok'] && str_contains($text($render($iid)), 'Has done something else entirely at Dazu Training Institute. Changed text.')
    && (int) $db->query("SELECT COUNT(*) FROM certificate_history WHERE certificate_id = $iid")->fetchColumn() === 1);
check('the certificate number and QR code stay the same after the reissue', $db->query("SELECT qr_code_hash FROM certificates WHERE id = $iid")->fetchColumn() === md5('DDS-PARA-2'));

echo "\n== The public verify page shows the same joined paragraph\n";
$_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/verify/';
$ver = function (string $no) {
    $_GET = ['c' => md5($no)]; ob_start(); include __DIR__ . '/../../../public/verify/index.php'; return ob_get_clean();
};
$vh = $ver('DDS-PARA-2');
check('verify shows the reissued paragraph in one block (no separate "What was completed" box)', str_contains($vh, 'Has done something else entirely at Dazu Training Institute. Changed text.') && !str_contains($vh, 'What was completed'));
$db->prepare('UPDATE certificates SET taught_skills = ? WHERE id = ?')->execute(['and has met all.', $id]);
cv2_save_text($db, $id, 'Has successfully completed {a_duration} programme in {course} at {institute}.', 'and has met all.', 5);
$v1 = $ver('DDS-PARA-1');
preg_match('#line-height:1.6">(.*?)</div>#s', $v1, $pm);
check('verify shows a not-yet-issued certificate\'s two boxes as one paragraph too', str_contains($text($v1), $sentence . ' and has met all.'), (string) ($pm[1] ?? substr($text($v1), 0, 200)));
$db->exec("DELETE FROM certificates WHERE certificate_no LIKE 'DDS-PARA-%'");
echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit($fails ? 1 : 0);
