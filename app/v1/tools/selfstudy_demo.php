<?php
// Self-study demo data for local testing (needs the Student Progress demo: php app/v1/tools/progress_demo.php).
//   php app/v1/tools/selfstudy_demo.php            create (does nothing if it exists)
//   php app/v1/tools/selfstudy_demo.php --reset    remove the demo and create it again
// Shares six courses with amina@progress-demo.local (password Passw0rd!) like the mockup, plus four that must NOT show:
// a draft, an archived one, one shared and then removed, and one never shared (their titles start with HIDDEN).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$opts = getopt('', ['reset']);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SelfStudy.php';
require_once __DIR__ . '/../services/SelfStudyStudy.php';

$db = \Models\Connection::getInstance()->getConnection();
if (!ss_installed($db)) { fwrite(STDERR, "Run the migrations first: php app/v1/databases/migrate.php\n"); exit(1); }
$tutorUid = (int) $db->query("SELECT id FROM users WHERE email = 'tutor@progress-demo.local'")->fetchColumn();
$tutorId = $tutorUid ? (int) $db->query("SELECT id FROM tutors WHERE user_id = $tutorUid")->fetchColumn() : 0;
$studentId = (int) $db->query("SELECT id FROM users WHERE email = 'amina@progress-demo.local'")->fetchColumn();
if (!$tutorId || !$studentId) { fwrite(STDERR, "Create the Student Progress demo first: php app/v1/tools/progress_demo.php\n"); exit(1); }

$ids = $db->query("SELECT id FROM self_study_courses WHERE owner_tutor_id = $tutorId")->fetchAll(PDO::FETCH_COLUMN);
if ($ids) {
    if (!isset($opts['reset'])) { echo "The demo already exists. Use --reset to rebuild it.\n"; exit(0); }
    $in = implode(',', array_map('intval', $ids));
    foreach (['self_study_answers', 'self_study_topic_progress', 'self_study_files', 'self_study_questions', 'self_study_topics', 'self_study_modules', 'self_study_course_rules'] as $t) { try { $db->exec("DELETE FROM $t WHERE course_id IN ($in)"); } catch (Throwable $e) {} }
    $db->exec("DELETE FROM self_study_progress WHERE course_id IN ($in)");
    $db->exec("DELETE FROM self_study_access WHERE course_id IN ($in)");
    $db->exec("DELETE FROM self_study_courses WHERE id IN ($in)");
    foreach ($ids as $id) { foreach (glob(public_path('uploads/selfstudy/' . (int) $id . '/*')) ?: [] as $f) @unlink($f); @rmdir(public_path('uploads/selfstudy/' . (int) $id)); }
    echo "Old demo removed.\n";
}

/** A 1600x900 picture in the mockup's style (bars, a line chart, code brackets, waves). */
function demo_cover(string $kind, array $c1, array $c2): string
{
    $w = 1600; $h = 900;
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {   // diagonal-ish vertical gradient
        $t = $y / $h;
        imageline($im, 0, $y, $w, $y, imagecolorallocate($im, (int) ($c1[0] + ($c2[0] - $c1[0]) * $t), (int) ($c1[1] + ($c2[1] - $c1[1]) * $t), (int) ($c1[2] + ($c2[2] - $c1[2]) * $t)));
    }
    $soft = imagecolorallocatealpha($im, 255, 255, 255, 105);
    imagefilledellipse($im, 1250, 300, 700, 700, $soft);
    $mint = imagecolorallocate($im, 106, 207, 150); $sky = imagecolorallocate($im, 140, 185, 240); $gold = imagecolorallocate($im, 245, 200, 120);
    if ($kind === 'bars') {
        foreach ([160, 330, 440, 540, 300, 400] as $i => $bh) imagefilledrectangle($im, 120 + $i * 150, 780 - $bh, 230 + $i * 150, 780, $mint);
    } elseif ($kind === 'line') {
        $pts = [[200, 260], [520, 470], [760, 230], [1150, 420], [1450, 190]];
        imagesetthickness($im, 7);
        for ($i = 1; $i < count($pts); $i++) imageline($im, $pts[$i - 1][0], $pts[$i - 1][1], $pts[$i][0], $pts[$i][1], $sky);
        foreach ($pts as $p) imagefilledellipse($im, $p[0], $p[1], 70, 70, $sky);
    } elseif ($kind === 'code') {
        imagesetthickness($im, 40);
        imageline($im, 320, 420, 210, 520, $gold); imageline($im, 210, 520, 320, 620, $gold);
        imageline($im, 560, 420, 670, 520, $gold); imageline($im, 670, 520, 560, 620, $gold);
        imageline($im, 520, 380, 420, 660, $gold);
    } else {
        imagesetthickness($im, 10);
        for ($x = 0; $x < $w - 6; $x += 6) { imageline($im, $x, 520 + (int) (60 * sin($x / 190)), $x + 6, 520 + (int) (60 * sin(($x + 6) / 190)), $mint); }
    }
    $f = tempnam(sys_get_temp_dir(), 'ssd');
    imagejpeg($im, $f, 90);
    imagedestroy($im);
    return $f;
}

$G = [[6, 100, 50], [8, 130, 70]]; $B = [[40, 90, 160], [22, 50, 100]]; $A = [[190, 120, 10], [70, 45, 15]]; $D = [[50, 50, 52], [20, 120, 60]];
// title, description, level, status, modules, cover [kind, colours], [percent, last studied days ago, completed] or null = no progress row, access: shared | removed | none
$list = [
    ['Python for Data Analysis', 'Learn Python from zero and use it to clean, explore and understand real data.', 'beginner', 'published', 5, ['bars', $G], [34, 1, false], 'shared'],
    ['Network Security Basics', 'Understand how networks are attacked and how to defend them step by step.', 'intermediate', 'published', 6, ['line', $B], null, 'shared'],
    ['Excel for Business Reports', 'Build clear reports with formulas, tables and charts that managers can read.', 'beginner', 'published', 4, ['code', $A], [100, 6, true], 'shared'],
    ['Machine Learning Foundations', 'Train, test and improve your first machine learning models with confidence.', 'advanced', 'published', 8, ['wave', $D], [12, 1, false], 'shared'],
    ['SQL for Beginners', 'Ask questions of a database and get answers with simple SQL queries.', 'beginner', 'published', 4, ['bars', $G], [58, 1, false], 'shared'],
    ['Data Visualization Stories', 'Turn numbers into charts and short stories people remember.', 'intermediate', 'published', 5, ['line', $B], null, 'shared'],
    ['HIDDEN draft course', 'A draft: the student must never see this.', 'beginner', 'draft', 3, null, null, 'shared'],
    ['HIDDEN archived course', 'Archived: the student must never see this.', 'beginner', 'archived', 3, null, null, 'shared'],
    ['HIDDEN removed course', 'Access was removed: the student must never see this.', 'beginner', 'published', 3, null, [40, 2, false], 'removed'],
    ['HIDDEN not shared course', 'Published but never shared with this student.', 'beginner', 'published', 3, null, null, 'none'],
];
$ts = fn(int $d): string => gmdate('Y-m-d H:i:s', time() - $d * 86400 - 3600);
foreach ($list as $n => [$title, $desc, $level, $status, $mods, $cover, $prog, $access]) {
    $db->prepare('INSERT INTO self_study_courses (owner_tutor_id, title, short_description, level, status, module_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
       ->execute([$tutorId, $title, $desc, $level, $status, $mods, $ts(30 - $n)]);
    $cid = (int) $db->lastInsertId();
    if ($cover) {
        $f = demo_cover($cover[0], $cover[1][0], $cover[1][1]);
        $r = ss_cover_from_file($f, 'jpg', $cid);
        @unlink($f);
        if ($r['ok']) $db->prepare('UPDATE self_study_courses SET cover_original = ?, cover_400 = ?, cover_800 = ?, cover_1600 = ? WHERE id = ?')
            ->execute([$r['paths']['cover_original'], $r['paths']['cover_400'], $r['paths']['cover_800'], $r['paths']['cover_1600'], $cid]);
        else echo "Cover failed for $title: {$r['error']}\n";
    }
    if ($access !== 'none') $db->prepare('INSERT INTO self_study_access (course_id, student_id, added_by, added_at, removed_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$cid, $studentId, $tutorUid, $ts(20), $access === 'removed' ? $ts(2) : null]);
    if ($prog) $db->prepare('INSERT INTO self_study_progress (course_id, student_id, percent_complete, last_studied_at, completed_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$cid, $studentId, $prog[0], $ts($prog[1]), $prog[2] ? $ts($prog[1]) : null]);
}

// ---- Study screen content (needs migration 105): the first course gets modules, topics, questions and a file, like the study screen mockup.
if (function_exists('ss2_installed') && ss2_installed($db)) {
    $cid = (int) $db->query("SELECT id FROM self_study_courses WHERE owner_tutor_id = $tutorId AND title = 'Python for Data Analysis'")->fetchColumn();
    if ($cid) {
        $mod = function (string $t, int $pos, int $pass = 50) use ($db, $cid): int { $db->prepare('INSERT INTO self_study_modules (course_id, title, position, pass_mark) VALUES (?, ?, ?, ?)')->execute([$cid, $t, $pos, $pass]); return (int) $db->lastInsertId(); };
        $top = function (string $t, int $pos, ?int $m, bool $intro, ?string $vid, ?int $dur, string $html) use ($db, $cid): int {
            $db->prepare('INSERT INTO self_study_topics (course_id, module_id, title, position, content_html, video_id, video_duration, is_intro) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$cid, $m, $t, $pos, $html, $vid, $dur, $intro ? 1 : 0]);
            return (int) $db->lastInsertId();
        };
        $q = function (string $place, ?int $topic, ?int $module, string $text, array $opts, int $ok) use ($db, $cid): void {
            $db->prepare("INSERT INTO self_study_questions (course_id, place, topic_id, module_id, qtype, question_text, options_json, correct_index) VALUES (?, ?, ?, ?, 'multiple_choice', ?, ?, ?)")->execute([$cid, $place, $topic, $module, $text, json_encode($opts), $ok]);
        };
        $m1 = $mod('Module 1: Getting Started with Python', 1); $m2 = $mod('Module 2: Working with Data', 2); $m3 = $mod('Module 3: Charts with Python', 3);
        $ti = $top('Course Introduction', 0, null, true, null, null, '<h2>Welcome</h2><p>This course teaches <b>Python</b> for data work, one small step at a time.</p>');
        $t10 = $top('Introduction', 1, $m1, false, null, null, '<p>Python is easy to read and powerful for data.</p><ul><li>Install it</li><li>Run your first line</li></ul>');
        $t11 = $top('Variables and types', 2, $m1, false, null, null, '<p>A variable stores a value.</p><pre><code>price = 120
name = "Amina"</code></pre>');
        $t20 = $top('Introduction', 3, $m2, false, null, null, '<p>Real data is rarely perfect.</p>');
        $t21 = $top('Reading CSV files', 4, $m2, false, null, null, '<p>Use <code>pd.read_csv()</code> to load a file.</p><table><tr><th>Method</th><th>Use</th></tr><tr><td>read_csv</td><td>Load a CSV</td></tr></table>');
        $t22 = $top('Cleaning missing values', 5, $m2, false, 'aqz-KE-bpKQ', 596, '<p>Real data is rarely perfect. Some cells are empty, and if we ignore them our averages and charts can be wrong.</p><h3>Why missing values matter</h3><ul><li>A single empty cell can change an average.</li><li>You can <b>drop</b> the row or <b>fill</b> it with a sensible value.</li></ul><pre><code>df.isnull().sum()  # count the empty cells
df = df.dropna()</code></pre>');
        $t30 = $top('Your first chart', 6, $m3, false, null, null, '<p>Charts turn numbers into pictures.</p>');
        foreach ([[$t10, 'Which symbol starts a comment in Python?', ['#', '//', '--', '/*'], 0], [$t11, 'Which is a valid variable name?', ['2price', 'price_2', 'my price', 'price-2'], 1]] as [$tt, $txt, $o, $ok]) $q('topic', $tt, null, $txt, $o, $ok);
        for ($i = 1; $i <= 7; $i++) $q('topic', $t22, null, "Which pandas method removes rows that have missing values? (variant $i)", ['df.fillna()', 'df.dropna()', 'df.isnull()', 'df.drop_duplicates()'], 1);
        $q('knowledge_check', null, null, 'What is Python mainly used for in this course?', ['Data analysis', 'Gardening', 'Welding'], 0);
        $q('knowledge_check', null, null, 'Which one is a data type?', ['Banana', 'Integer', 'Window'], 1);
        $q('module_quiz', null, $m1, 'Pick the string.', ['"hello"', '42', 'True'], 0);
        $db->prepare('INSERT INTO self_study_files (course_id, topic_id, file_name, file_path) VALUES (?, NULL, ?, ?)')->execute([$cid, 'Python cheat sheet.txt', $cid . '/cheat-sheet.txt']);
        @mkdir(storage_path('selfstudy/' . $cid), 0775, true);
        file_put_contents(storage_path('selfstudy/' . $cid . '/cheat-sheet.txt'), "Python cheat sheet

print('hello')
");
        // the student has already finished the Introduction and Module 1 topic 1, and is on topic 2
        $ts = fn(int $d) => gmdate('Y-m-d H:i:s', time() - $d * 86400);
        foreach ([$ti, $t10] as $x) $db->prepare('INSERT INTO self_study_topic_progress (student_id, topic_id, course_id, started_at, last_seen_at, questions_submitted, done_at) VALUES (?, ?, ?, ?, ?, 1, ?)')->execute([$studentId, $x, $cid, $ts(2), $ts(2), $ts(2)]);
        echo "Study screen content added to 'Python for Data Analysis' (the student is on Module 1, topic 2).
";
    }
}
echo "Self-study demo created: 6 courses the student sees, 4 that stay hidden.\n";
echo "Sign in as amina@progress-demo.local / Passw0rd! at /login, then Self-study in the menu.\n";
