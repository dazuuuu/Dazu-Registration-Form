<?php
// app/v1/services/CourseImportService.php — builds a tutor's course modules from one spreadsheet (CSV or Excel .xlsx).
// One row per item. Type column:
//   UNIT        → a module: Title, Description, What students learn (shown as the module's instructions), Video link.
//   LESSON      → reading material in the unit given in the Unit column: Title, Lesson notes, Video link.
//   QUESTION    → a question in that unit: Title (the question), Description, Answer type (text/choice/file/link),
//                 Options (separate with |), Correct option (number), Marks.
//   ASSIGNMENT  → goes into a last module "Final assignments", which opens only after every other module is done.
// The same content is added to every course the tutor picks, so all students of those courses get it.
// Nothing is saved when any row has an error.

if (!class_exists('CourseImportService')) {
class CourseImportService
{
    public const HEADERS = ['Type', 'Unit', 'Subject', 'Title', 'Description', 'What students learn', 'Lesson notes', 'Video link', 'Answer type', 'Options', 'Correct option', 'Marks'];
    private const MODES = ['text' => 'text', 'written' => 'text', 'choice' => 'choice', 'multiple choice' => 'choice', 'mcq' => 'choice',
                           'file' => 'file', 'upload' => 'file', 'link' => 'github', 'github' => 'github', 'url' => 'github'];

    /** The template: headers, how-to lines (starting with #) and example rows. */
    public static function templateRows(): array
    {
        return [
            self::HEADERS,
            ['# HOW TO FILL', 'Delete these # lines and the example rows, then add your own. Keep the first row (the headers) exactly as it is.', '', '', '', '', '', '', '', '', '', ''],
            ['# Type', 'UNIT = a new unit/module. LESSON = notes inside a unit. QUESTION = a question inside a unit. ASSIGNMENT = final assignment (opens after the whole course is done).', '', '', '', '', '', '', '', '', '', ''],
            ['# Unit', 'The unit number the row belongs to (1, 2, 3…). Leave it empty for ASSIGNMENT rows.', '', '', '', '', '', '', '', '', '', ''],
            ['# Subject', 'Only on UNIT rows. The subject this unit belongs to, e.g. Python Programming. If it does not exist yet in the course it is created automatically. Leave empty for no subject.', '', '', '', '', '', '', '', '', '', ''],
            ['# Answer type', 'For QUESTION / ASSIGNMENT: text, choice, file or link. For choice: Options like  A | B | C  and Correct option = 2.', '', '', '', '', '', '', '', '', '', ''],
            ['UNIT', '1', 'Python Programming', 'Introduction to Python', 'Get set up and write your first programs.', 'Install Python; run a script; use variables and print()', '', 'https://www.youtube.com/watch?v=example', '', '', '', ''],
            ['LESSON', '1', '', 'Installing Python', '', '', 'Download Python from python.org, install it and tick "Add to PATH". Open a terminal and type python --version.', '', '', '', '', ''],
            ['QUESTION', '1', '', 'Which command shows the Python version?', '', '', '', '', 'choice', 'python --version | python -v | version python', '1', '5'],
            ['QUESTION', '1', '', 'Write a program that prints your name and age.', 'Paste your code.', '', '', '', 'text', '', '', '10'],
            ['UNIT', '2', 'Python Programming', 'Control flow', 'Make decisions and repeat work.', 'if / elif / else; for and while loops', '', '', '', '', '', ''],
            ['LESSON', '2', '', 'If statements', '', '', 'Use if to run code only when a condition is true...', '', '', '', '', ''],
            ['QUESTION', '2', '', 'Upload a script that prints the numbers 1 to 10.', '', '', '', '', 'file', '', '', '10'],
            ['ASSIGNMENT', '', '', 'Final project: a simple calculator', 'Build a calculator that adds, subtracts, multiplies and divides. Share your GitHub link.', '', '', '', 'link', '', '', '50'],
        ];
    }

    /** CSV text of the template (with a UTF-8 mark so Excel shows symbols correctly). */
    public static function templateCsv(): string
    {
        $fh = fopen('php://temp', 'w+');
        foreach (self::templateRows() as $r) fputcsv($fh, $r, ',', '"', '');
        rewind($fh);
        return "\xEF\xBB\xBF" . stream_get_contents($fh);
    }

    /** The template as a real, minimal single-sheet .xlsx (built by hand, no library — mirrors readXlsx() below). */
    public static function templateXlsx(): string
    {
        if (!class_exists('ZipArchive')) throw new RuntimeException('This server cannot build Excel files.');
        $rows = self::templateRows();
        $shared = [];
        $idx = [];
        foreach ($rows as $r) foreach ($r as $v) {
            $v = (string) $v;
            if ($v === '') continue;
            if (!isset($idx[$v])) { $idx[$v] = count($shared); $shared[] = $v; }
        }
        $xmlEsc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $colLetter = function (int $i): string {
            $s = '';
            $i++;
            while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); }
            return $s;
        };
        $sheetRows = '';
        foreach ($rows as $rn => $r) {
            $cells = '';
            foreach ($r as $cn => $v) {
                $v = (string) $v;
                if ($v === '') continue;
                $cells .= '<c r="' . $colLetter($cn) . ($rn + 1) . '" t="s"><v>' . (int) $idx[$v] . '</v></c>';
            }
            $sheetRows .= '<row r="' . ($rn + 1) . '">' . $cells . '</row>';
        }
        $sst = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($shared) . '" uniqueCount="' . count($shared) . '">';
        foreach ($shared as $s) $sst .= '<si><t xml:space="preserve">' . $xmlEsc($s) . '</t></si>';
        $sst .= '</sst>';
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheetRows . '</sheetData></worksheet>';
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Course" sheetId="1" r:id="rId1"/></sheets></workbook>';
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>';
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>';

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $z = new ZipArchive();
        $z->open($tmp, ZipArchive::OVERWRITE);
        $z->addFromString('[Content_Types].xml', $contentTypes);
        $z->addFromString('_rels/.rels', $rootRels);
        $z->addFromString('xl/workbook.xml', $workbook);
        $z->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
        $z->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $z->addFromString('xl/sharedStrings.xml', $sst);
        $z->close();
        $bin = (string) file_get_contents($tmp);
        unlink($tmp);
        return $bin;
    }

    /** Rows of a .csv or .xlsx file (first sheet). @return array<int,array<int,string>> */
    public static function readFile(string $path, string $name): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') return self::readXlsx($path);
        $text = (string) file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (!mb_check_encoding($text, 'UTF-8')) $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        $delim = substr_count(strtok($text, "\n") ?: '', ';') > substr_count(strtok($text, "\n") ?: '', ',') ? ';' : ',';
        $fh = fopen('php://temp', 'w+'); fwrite($fh, $text); rewind($fh);
        $rows = [];
        while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) $rows[] = array_map(fn($v) => trim((string) $v), $r);
        return $rows;
    }

    private static function readXlsx(string $path): array
    {
        if (!class_exists('ZipArchive')) throw new RuntimeException('This server cannot read Excel files. Save the sheet as CSV and upload that.');
        $z = new ZipArchive();
        if ($z->open($path) !== true) throw new RuntimeException('This is not a valid Excel file.');
        $shared = [];
        if (($ss = $z->getFromName('xl/sharedStrings.xml')) !== false) {
            $x = simplexml_load_string($ss);
            foreach ($x->si as $si) { $t = ''; if (isset($si->t)) $t = (string) $si->t; else foreach ($si->r as $run) $t .= (string) $run->t; $shared[] = $t; }
        }
        $sheet = $z->getFromName('xl/worksheets/sheet1.xml');
        $z->close();
        if ($sheet === false) throw new RuntimeException('The Excel file has no first sheet.');
        $x = simplexml_load_string($sheet);
        $rows = [];
        foreach ($x->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)/', (string) $c['r'], $m);
                $col = 0; foreach (str_split($m[1] ?? 'A') as $ch) $col = $col * 26 + (ord($ch) - 64);
                $t = (string) $c['t'];
                $v = $t === 's' ? ($shared[(int) $c->v] ?? '') : ($t === 'inlineStr' ? (string) $c->is->t : (string) $c->v);
                $cells[$col - 1] = trim($v);
            }
            if ($cells) { $max = max(array_keys($cells)); $line = []; for ($i = 0; $i <= $max; $i++) $line[] = $cells[$i] ?? ''; $rows[] = $line; }
        }
        return $rows;
    }

    /**
     * Turn rows into a plan. @return array{ok:bool, errors:string[], units:array, assignments:array, counts:array}
     */
    public static function plan(array $rows): array
    {
        $errors = [];
        if (!$rows) return ['ok' => false, 'errors' => ['The file is empty.'], 'units' => [], 'assignments' => [], 'counts' => []];
        $head = array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), array_shift($rows));
        $idx = [];
        foreach (self::HEADERS as $h) {
            $i = array_search(strtolower($h), $head, true);
            if ($i === false) $errors[] = 'The column "' . $h . '" is missing from the first row. Use the template.';
            $idx[$h] = $i;
        }
        if ($errors) return ['ok' => false, 'errors' => $errors, 'units' => [], 'assignments' => [], 'counts' => []];
        $get = fn(array $r, string $h) => trim((string) ($r[$idx[$h]] ?? ''));
        $units = [];
        $assignments = [];
        foreach ($rows as $n => $r) {
            $line = $n + 2;
            if (!array_filter($r, fn($v) => trim((string) $v) !== '')) continue;
            $type = strtoupper($get($r, 'Type'));
            if ($type === '' || str_starts_with($type, '#')) continue;
            $title = $get($r, 'Title');
            $unit = $get($r, 'Unit');
            if ($title === '') { $errors[] = "Row $line: the Title is empty."; continue; }
            if (mb_strlen($title) > 200) { $errors[] = "Row $line: the Title is longer than 200 characters."; continue; }
            if ($type === 'UNIT') {
                if ($unit === '') { $errors[] = "Row $line: give the unit a number in the Unit column."; continue; }
                if (isset($units[$unit]['title'])) { $errors[] = "Row $line: unit $unit appears twice."; continue; }
                $units[$unit] = ($units[$unit] ?? ['items' => []]) + ['title' => $title, 'description' => $get($r, 'Description'),
                    'subject' => $get($r, 'Subject'), 'learn' => $get($r, 'What students learn'), 'video' => $get($r, 'Video link'), 'line' => $line];
                continue;
            }
            if (!in_array($type, ['LESSON', 'QUESTION', 'ASSIGNMENT'], true)) { $errors[] = "Row $line: Type must be UNIT, LESSON, QUESTION or ASSIGNMENT."; continue; }
            if ($type === 'LESSON') {
                $item = ['type' => 'material', 'title' => $title, 'content' => trim($get($r, 'Lesson notes') . ($get($r, 'Description') !== '' ? "\n\n" . $get($r, 'Description') : '')),
                         'link_url' => $get($r, 'Video link')];
            } else {
                $modeIn = strtolower($get($r, 'Answer type') ?: 'text');
                if (!isset(self::MODES[$modeIn])) { $errors[] = "Row $line: Answer type must be text, choice, file or link."; continue; }
                $mode = self::MODES[$modeIn];
                $item = ['type' => 'question', 'answer_mode' => $mode, 'title' => $title, 'content' => $get($r, 'Description'),
                         'max_score' => max(1, (int) ($get($r, 'Marks') ?: 10)), 'link_url' => $get($r, 'Video link')];
                if ($mode === 'choice') {
                    $opts = array_values(array_filter(array_map('trim', explode('|', $get($r, 'Options'))), 'strlen'));
                    $correct = (int) $get($r, 'Correct option');
                    if (count($opts) < 2) { $errors[] = "Row $line: a choice question needs at least two Options separated by |."; continue; }
                    if ($correct < 1 || $correct > count($opts)) { $errors[] = "Row $line: Correct option must be a number from 1 to " . count($opts) . '.'; continue; }
                    $item['options'] = implode("\n", $opts);
                    $item['correct_option'] = $correct;
                }
            }
            if ($type === 'ASSIGNMENT') { $assignments[] = $item; continue; }
            if ($unit === '') { $errors[] = "Row $line: say which unit this belongs to in the Unit column."; continue; }
            $units[$unit]['items'][] = $item + ['line' => $line];
        }
        foreach ($units as $no => $u) if (!isset($u['title'])) $errors[] = 'Unit ' . $no . ' has lessons or questions but no UNIT row.';
        uksort($units, fn($a, $b) => is_numeric($a) && is_numeric($b) ? $a <=> $b : strnatcmp((string) $a, (string) $b));
        $count = fn(string $t) => array_sum(array_map(fn($u) => count(array_filter($u['items'] ?? [], fn($i) => $i['type'] === $t)), $units));
        $counts = ['units' => count($units), 'lessons' => $count('material'), 'questions' => $count('question'), 'assignments' => count($assignments)];
        if (!$errors && !$units && !$assignments) $errors[] = 'No UNIT, LESSON, QUESTION or ASSIGNMENT rows were found.';
        return ['ok' => !$errors, 'errors' => $errors, 'units' => array_values($units), 'assignments' => $assignments, 'counts' => $counts];
    }

    /** Save the plan into each course. $replace removes modules made by earlier imports first. @return int modules created */
    public static function apply(PDO $db, \Models\TutorCourseModel $tc, int $tutorUserId, array $courseIds, array $plan, bool $replace, int $tutorId = 0): int
    {
        require_once __DIR__ . '/SubjectService.php';
        $batch = date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $made = 0;
        $db->beginTransaction();
        try {
            foreach ($courseIds as $courseId) {
                $subjectIds = [];   // subject name => course_subjects.id, cached per course
                if ($replace) {
                    $st = $db->prepare("SELECT id FROM course_modules WHERE course_id = ? AND tutor_id = ? AND import_batch IS NOT NULL");
                    $st->execute([$courseId, $tutorUserId]);
                    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $mid) $tc->deleteModule($tutorUserId, (int) $mid);
                }
                $mods = $plan['units'];
                if ($plan['assignments']) $mods[] = ['title' => 'Final assignments', 'description' => 'These open when you have finished every other module of the course.',
                                                      'learn' => '', 'video' => '', 'items' => $plan['assignments'], 'final' => true];
                foreach ($mods as $u) {
                    $mid = $tc->saveModule($tutorUserId, (int) $courseId, null, ['title' => mb_substr($u['title'], 0, 200), 'description' => $u['description'] ?? '',
                        'instructions' => ($u['learn'] ?? '') !== '' ? "What you will learn:\n" . $u['learn'] : '', 'video_url' => $u['video'] ?? '']);
                    $subjectId = null;
                    $subjectName = trim((string) ($u['subject'] ?? ''));
                    if ($subjectName !== '' && $tutorId > 0) {
                        $key = mb_strtolower($subjectName);
                        $subjectIds[$key] ??= subj_find_or_create($db, $tutorId, $tutorUserId, (int) $courseId, $subjectName);
                        $subjectId = $subjectIds[$key];
                    }
                    $db->prepare("UPDATE course_modules SET import_batch = ?, unlock_after_all = ?, subject_id = ? WHERE id = ?")->execute([$batch, !empty($u['final']) ? 1 : 0, $subjectId, $mid]);
                    foreach ($u['items'] as $it) {
                        $r = $tc->saveItem($mid, null, $it, null, false);
                        if (!$r['ok']) throw new RuntimeException('"' . $it['title'] . '": ' . $r['error']);
                    }
                    $made++;
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return $made;
    }
}
}
