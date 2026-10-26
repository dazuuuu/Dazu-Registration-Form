<?php
// Tutor: create or edit an assignment (spec 8.1). Save draft, or publish and notify the chosen students.
require __DIR__ . '/../../components/assignments/tutor_boot.php';

use Models\AssignmentModel;

require_once __DIR__ . '/../../../app/v1/services/SubjectService.php';

$id = (int) ($_GET['id'] ?? 0);
$a = $id ? $am->getForTutor($id, $tutorId) : null;
if ($id && (!$a || $a['status'] === 'archived')) asg_back('index.php', null, 'Assignment not found.');
$courses = $am->tutorCourses($tutorId);
$templates = $am->rubricTemplates($tutorId);
$errors = [];

// Current values (from the saved assignment, or defaults for a new one).
$f = $a ? [
    'course_id' => (int) $a['course_id'], 'title' => $a['title'], 'description' => (string) $a['description'],
    'details' => (string) $a['details'] ?: nl2br(asg_e((string) $a['instructions'])), 'instructions' => (string) ($a['details'] ? $a['instructions'] : ''),
    'due_date' => AssignmentModel::local($a['due_at'], 'Y-m-d'), 'due_time' => AssignmentModel::local($a['due_at'], 'H:i'),
    'types' => $a['types'], 'link_sites' => array_filter(explode(',', (string) $a['link_sites'])),
    'grading_mode' => $a['grading_mode'], 'max_score' => (int) $a['max_score'], 'pass_mark_percent' => (int) $a['pass_mark_percent'],
    'sequential_lock' => (int) $a['sequential_lock'], 'close_after_due' => (int) $a['close_after_due'], 'max_extension_days' => (int) $a['max_extension_days'],
    'questions' => array_map(fn($q) => [(int) $q['id'], $q['text']], $am->questions($id)),
    'rubric' => array_map(fn($r) => [(int) $r['id'], $r['name'], (float) $r['max_marks']], $am->rubric($id)),
    'recipients' => $am->recipientIds($id),
    'kind' => (string) ($a['kind'] ?? 'daily'), 'weight' => (float) ($a['weight'] ?? 1), 'allow_resubmission' => ($a['allow_resubmission'] ?? null) === null ? 1 : (int) $a['allow_resubmission'],
    'subject_id' => (int) ($a['subject_id'] ?? 0),
    'class_session_id' => (int) ($a['class_session_id'] ?? 0), 'quiz' => array_map(fn($q) => [(int) $q['id'], $q['qtype'], $q['prompt'], $q['options'], (int) $q['correct_option'], (float) $q['points']], $am->quizQuestions($id)),
    'overrides' => [], 'groups_on' => (int) !empty($a['group_size']), 'groups' => $am->groupMap($id),
    'show_at' => !empty($a['publish_at']) ? 'later' : 'now', 'publish_date' => AssignmentModel::local($a['publish_at'] ?? null, 'Y-m-d'), 'publish_time' => AssignmentModel::local($a['publish_at'] ?? null, 'H:i') ?: '08:00',
    'release' => !empty($a['release_grades_at']) ? 'later' : 'now', 'release_date' => AssignmentModel::local($a['release_grades_at'] ?? null, 'Y-m-d'), 'release_time' => AssignmentModel::local($a['release_grades_at'] ?? null, 'H:i') ?: '09:00',
] + array_combine(['submit_code', 'submit_audio', 'submit_quiz', 'no_submission', 'check_links', 'block_bad_links', 'require_own_work', 'ask_ai_note', 'remind_enabled', 'remind_whatsapp'], array_map(fn($c) => (int) ($a[$c] ?? ($c === 'check_links' ? 1 : 0)), ['submit_code', 'submit_audio', 'submit_quiz', 'no_submission', 'check_links', 'block_bad_links', 'require_own_work', 'ask_ai_note', 'remind_enabled', 'remind_whatsapp'])) : [
    'course_id' => (int) ($_GET['course'] ?? (count($courses) === 1 ? array_key_first($courses) : 0)), 'title' => '', 'description' => '', 'details' => '', 'instructions' => '',
    'due_date' => '', 'due_time' => '17:00', 'types' => ['link'], 'link_sites' => [], 'grading_mode' => 'marks', 'max_score' => 100,
    'pass_mark_percent' => $am->gradeScale()['pass'], 'sequential_lock' => 1, 'close_after_due' => 1, 'max_extension_days' => 7,
    'questions' => [], 'rubric' => [], 'recipients' => [],
    'kind' => in_array($_GET['kind'] ?? '', array_keys(AssignmentModel::KINDS), true) ? $_GET['kind'] : 'daily', 'weight' => 1.0, 'allow_resubmission' => 1, 'subject_id' => 0,
    'show_at' => 'now', 'publish_date' => '', 'publish_time' => '08:00', 'release' => 'now', 'release_date' => '', 'release_time' => '09:00',
    'class_session_id' => 0, 'quiz' => [], 'overrides' => [], 'groups_on' => 0, 'groups' => [],
    'submit_code' => 0, 'submit_audio' => 0, 'submit_quiz' => 0, 'no_submission' => 0, 'check_links' => 1, 'block_bad_links' => 0,
    'require_own_work' => 0, 'ask_ai_note' => 0, 'remind_enabled' => 1, 'remind_whatsapp' => 0,
];
$tutorClasses = $am->tutorClasses($me);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back('edit.php' . ($id ? '?id=' . $id : ''));
    $p = $_POST;
    // A template is never shown to students: Use it from the Templates tab to make a real assignment.
    $publish = ($p['action'] ?? '') === 'publish' && !(int) ($a['is_template'] ?? 0);
    $courseId = (int) ($p['course_id'] ?? 0);
    $types = array_values(array_intersect(array_keys(AssignmentModel::SUBMIT_TYPES), (array) ($p['types'] ?? [])));
    $questions = [];
    foreach ((array) ($p['q_text'] ?? []) as $i => $t) {
        $t = trim((string) $t);
        if ($t !== '') $questions[] = [(int) ($p['q_id'][$i] ?? 0), mb_substr($t, 0, 2000)];
    }
    $rubric = [];
    foreach ((array) ($p['r_name'] ?? []) as $i => $n) {
        $n = trim((string) $n);
        if ($n !== '') $rubric[] = [(int) ($p['r_id'][$i] ?? 0), mb_substr($n, 0, 150), round(max(0, (float) ($p['r_max'][$i] ?? 0)), 2)];
    }
    $f = [
        'course_id' => $courseId, 'title' => trim((string) ($p['title'] ?? '')), 'description' => trim((string) ($p['description'] ?? '')),
        'details' => asg_clean_html($p['details'] ?? ''), 'instructions' => trim((string) ($p['instructions'] ?? '')),
        'due_date' => trim((string) ($p['due_date'] ?? '')), 'due_time' => trim((string) ($p['due_time'] ?? '')) ?: '17:00',
        'types' => $types, 'link_sites' => array_values(array_intersect(array_keys(ASG_LINK_SITES), (array) ($p['link_sites'] ?? []))),
        'grading_mode' => in_array($p['grading_mode'] ?? '', array_keys(AssignmentModel::GRADING_MODES), true) ? $p['grading_mode'] : 'marks',
        'max_score' => (int) ($p['max_score'] ?? 100), 'pass_mark_percent' => (int) ($p['pass_mark_percent'] ?? 50),
        'sequential_lock' => !empty($p['sequential_lock']) ? 1 : 0, 'close_after_due' => !empty($p['close_after_due']) ? 1 : 0,
        'max_extension_days' => (int) ($p['max_extension_days'] ?? 7),
        'questions' => $questions, 'rubric' => $rubric, 'recipients' => array_values(array_unique(array_map('intval', (array) ($p['students'] ?? [])))),
        'kind' => isset(AssignmentModel::KINDS[$p['kind'] ?? '']) ? $p['kind'] : 'daily', 'weight' => round((float) ($p['weight'] ?? 1), 2),
        'allow_resubmission' => !empty($p['allow_resubmission']) ? 1 : 0, 'subject_id' => (int) ($p['subject_id'] ?? 0),
        'show_at' => ($p['show_at'] ?? '') === 'later' ? 'later' : 'now', 'publish_date' => trim((string) ($p['publish_date'] ?? '')), 'publish_time' => trim((string) ($p['publish_time'] ?? '')) ?: '08:00',
        'release' => ($p['release'] ?? '') === 'later' ? 'later' : 'now', 'release_date' => trim((string) ($p['release_date'] ?? '')), 'release_time' => trim((string) ($p['release_time'] ?? '')) ?: '09:00',
    ];
    foreach (['submit_code', 'submit_audio', 'submit_quiz', 'no_submission', 'check_links', 'block_bad_links', 'require_own_work', 'ask_ai_note', 'remind_enabled', 'remind_whatsapp'] as $c) $f[$c] = !empty($p[$c]) ? 1 : 0;
    if ($f['no_submission']) { $types = []; foreach (['submit_code', 'submit_audio', 'submit_quiz'] as $c) $f[$c] = 0; }
    $f['class_session_id'] = (int) ($p['class_session_id'] ?? 0);
    if ($f['class_session_id'] && !in_array($f['class_session_id'], array_map(fn($c) => (int) $c['id'], $tutorClasses), true)) $f['class_session_id'] = 0;
    $f['quiz'] = [];
    foreach ((array) ($p['qz_prompt'] ?? []) as $i => $qp) {
        $qp = trim((string) $qp);
        if ($qp === '') continue;
        $qt = ($p['qz_type'][$i] ?? '') === 'truefalse' ? 'truefalse' : 'mcq';
        $opts = $qt === 'mcq' ? array_values(array_filter(array_map(fn($o) => mb_substr(trim((string) $o), 0, 200), explode("\n", (string) ($p['qz_options'][$i] ?? ''))), 'strlen')) : ['True', 'False'];
        $correct = (int) ($p['qz_correct'][$i] ?? 1) - 1;
        if ($qt === 'mcq' && (count($opts) < 2 || count($opts) > 6)) $errors[] = 'Quiz question ' . (count($f['quiz']) + 1) . ' needs 2 to 6 choices (one per line).';
        if ($correct < 0 || $correct >= count($opts)) $correct = 0;
        $f['quiz'][] = [(int) ($p['qz_id'][$i] ?? 0), $qt, mb_substr($qp, 0, 1000), $opts, $correct, max(0.5, min(100, round((float) ($p['qz_points'][$i] ?? 1), 2)))];
    }
    if ($f['submit_quiz'] && !$f['quiz']) $errors[] = 'Add at least one quiz question, or turn Quiz questions off.';
    $f['groups_on'] = !empty($p['groups_on']) ? 1 : 0;
    $f['groups'] = [];
    foreach ((array) ($p['group'] ?? []) as $gu => $gn) if ((int) $gn > 0 && in_array((int) $gu, $f['recipients'], true)) $f['groups'][(int) $gu] = (int) $gn;
    if ($f['groups_on'] && $f['no_submission']) $errors[] = 'Group work needs a way to submit. Turn off "No submission" or groups.';
    if ($f['groups_on'] && max(array_count_values($f['groups']) ?: [0]) > 3) $errors[] = 'A group can have up to 3 students.';
    $f['overrides'] = [];
    foreach ((array) ($p['ov_student'] ?? []) as $i => $ou) {
        $ou = (int) $ou; $od = AssignmentModel::toUtc((string) ($p['ov_due'][$i] ?? ''));
        if ($ou && $od) $f['overrides'][$ou] = $od;
    }
    // An assignment already shown to students cannot be scheduled again.
    $canSchedule = !$a || $a['status'] === 'draft';
    $publishUtc = $canSchedule && $f['show_at'] === 'later' ? AssignmentModel::toUtc($f['publish_date'] . ' ' . $f['publish_time']) : null;
    $releaseUtc = $f['release'] === 'later' ? AssignmentModel::toUtc($f['release_date'] . ' ' . $f['release_time']) : null;
    if ($canSchedule && $f['show_at'] === 'later' && (!$publishUtc || AssignmentModel::ts($publishUtc) <= time())) $errors[] = 'Set a future date and time to show it to students.';
    if ($f['release'] === 'later' && !$releaseUtc) $errors[] = 'Set the date and time to release the grades.';
    if ($f['weight'] <= 0 || $f['weight'] > 100) $errors[] = 'Weight must be more than 0 and up to 100.';

    // Course and students: only the tutor's own. A course of an older assignment with no course may stay empty.
    $keepNoCourse = $a && !$a['course_id'] && !$courseId;
    if (!$keepNoCourse && !isset($courses[$courseId])) $errors[] = 'Choose one of your courses.';
    // Subject: required when the course has active subjects; it must be this tutor's own, in this course. Checked on the server.
    if (isset($courses[$courseId])) {
        $subjErr = subj_check_choice($pdo, $courseId, $tutorId, $f['subject_id'], (int) ($a['subject_id'] ?? 0), !$a);
        if ($subjErr) $errors[] = $subjErr;
    } elseif ($f['subject_id']) {
        $f['subject_id'] = 0;
    }
    $allowed = $am->tutorStudents($tutorId, $keepNoCourse ? null : $courseId);
    $forged = array_diff($f['recipients'], array_keys($allowed));
    if ($forged) {
        $am->log($id ?: null, $me, null, 'refused_recipients', ['user_ids' => array_values($forged)]);
        $errors[] = 'Some of the chosen students are not students you teach in this course. Nothing was saved.';
    }
    $dueUtc = $f['due_date'] !== '' ? AssignmentModel::toUtc($f['due_date'] . ' ' . $f['due_time']) : null;
    if ($f['title'] === '' || mb_strlen($f['title']) > 120) $errors[] = 'Give the assignment a title (up to 120 characters).';
    if ($f['description'] === '' || mb_strlen($f['description']) > 500) $errors[] = 'Write a short description (up to 500 characters).';
    if (!$dueUtc) $errors[] = 'Set the due date and time.';
    if (trim(strip_tags($f['details'])) === '') $errors[] = 'Write the assignment details.';
    if (!$types && !$f['submit_code'] && !$f['submit_audio'] && !$f['submit_quiz'] && !$f['no_submission']) $errors[] = 'Tick at least one way students can submit.';
    if (array_diff(array_keys($f['overrides']), $f['recipients'])) $errors[] = 'A different due date can only be given to a student who sees the assignment.';
    if ($f['grading_mode'] === 'marks') {
        if ($f['max_score'] < 1 || $f['max_score'] > 1000) $errors[] = 'Marks out of must be between 1 and 1000.';
        if ($rubric && abs(array_sum(array_column($rubric, 2)) - $f['max_score']) > 0.001) {
            $errors[] = 'The rubric criteria add up to ' . (float) array_sum(array_column($rubric, 2)) . ' but Marks out of is ' . $f['max_score'] . '. They must match.';
        }
    }
    if ($f['pass_mark_percent'] < 1 || $f['pass_mark_percent'] > 100) $errors[] = 'Pass mark must be between 1 and 100%.';
    if ($f['max_extension_days'] < 0 || $f['max_extension_days'] > 60) $errors[] = 'Extension limit must be between 0 and 60 days.';
    if ($publish || ($a && $a['status'] === 'published')) {
        if (!$f['recipients']) $errors[] = 'Tick at least one student who should see it.';
        if ($dueUtc && AssignmentModel::ts($dueUtc) <= time() && (!$a || $a['status'] === 'draft')) $errors[] = 'The due date is in the past.';
    }
    $newImages = asg_files_list($_FILES['att_images'] ?? null);
    $newPdfs = asg_files_list($_FILES['att_pdfs'] ?? null);
    $newLinks = [];
    foreach ((array) ($p['att_link_url'] ?? []) as $i => $u) {
        if (trim((string) $u) === '') continue;
        $clean = asg_clean_link($u);
        if (!$clean) { $errors[] = 'Attachment link "' . mb_substr((string) $u, 0, 60) . '" must start with http:// or https://.'; continue; }
        $newLinks[] = [$clean, trim((string) ($p['att_link_label'][$i] ?? '')) ?: parse_url($clean, PHP_URL_HOST)];
    }
    $removeAtt = array_map('intval', (array) ($p['remove_att'] ?? []));
    $existingAtt = $id ? count(array_filter($am->attachments($id), fn($x) => !in_array((int) $x['id'], $removeAtt, true))) : 0;
    if ($existingAtt + count($newImages) + count($newPdfs) + count($newLinks) > ASG_MAX_ATTACHMENTS) $errors[] = 'Up to ' . ASG_MAX_ATTACHMENTS . ' attachments.';

    if (!$errors) {
        $data = [
            'course_id' => $courseId ?: null, 'title' => $f['title'], 'description' => $f['description'], 'details' => $f['details'],
            'instructions' => $f['instructions'] ?: null, 'due_at' => $dueUtc, 'sequential_lock' => $f['sequential_lock'],
            'close_after_due' => $f['close_after_due'], 'max_extension_days' => $f['max_extension_days'],
            'submit_link' => (int) in_array('link', $types, true), 'submit_text' => (int) in_array('text', $types, true),
            'submit_images' => (int) in_array('images', $types, true), 'submit_file' => (int) in_array('file', $types, true),
            'link_sites' => $f['link_sites'] && in_array('link', $types, true) ? implode(',', $f['link_sites']) : null,
            'grading_mode' => $f['grading_mode'], 'max_score' => $f['grading_mode'] === 'marks' ? $f['max_score'] : 100,
            'pass_mark_percent' => $f['pass_mark_percent'],
            'kind' => $f['kind'], 'weight' => $f['weight'], 'allow_resubmission' => $f['allow_resubmission'],
            'release_grades_at' => $releaseUtc, 'class_session_id' => $f['class_session_id'] ?: null, 'subject_id' => $f['subject_id'] ?: null,
        ];
        foreach (['submit_code', 'submit_audio', 'submit_quiz', 'no_submission', 'check_links', 'block_bad_links', 'require_own_work', 'ask_ai_note', 'remind_enabled', 'remind_whatsapp'] as $c) $data[$c] = $f[$c];
        $data['group_size'] = $f['groups_on'] ? 3 : null;
        // Only Publish schedules; Save draft keeps (or makes) it a plain draft that the cron never publishes.
        if ($canSchedule) $data['publish_at'] = $publish ? $publishUtc : null;
        $saved = $am->save($tutorId, $id ?: null, $data, $questions, $f['grading_mode'] === 'marks' ? $rubric : [], $f['recipients'], $me);
        $id = $saved['id'];
        subj_log_assignment_change($pdo, $id, $courseId, $me, (int) ($a['subject_id'] ?? 0), (int) $f['subject_id']);
        $am->saveQuizQuestions($id, $f['submit_quiz'] ? $f['quiz'] : []);
        // Groups can change only until somebody has sent work, so nobody's work moves to another group.
        if (!$am->hasSubmissions($id)) $am->saveGroups($id, $f['groups_on'] ? $f['groups'] : []);
        // A different due date for one student (Late is judged against it).
        foreach ($f['overrides'] as $ou => $od) $am->setStudentDue($id, $ou, $od, $me, 'due_override');

        // Attachments: removals, then new files (private storage) and links.
        foreach ($removeAtt as $rid) $am->removeAttachment($id, $rid);
        $attErrors = [];
        foreach ([[$newImages, 'image', (array) ($p['att_image_label'] ?? [])], [$newPdfs, 'pdf', (array) ($p['att_pdf_label'] ?? [])]] as [$list, $kind, $labels]) {
            foreach ($list as $i => $file) {
                $r = $kind === 'image' ? asg_store_image($file, 'attachments/' . $id) : asg_store_document($file, 'attachments/' . $id, ['pdf']);
                if (!$r['ok']) { $attErrors[] = $r['error']; continue; }
                $label = trim((string) ($labels[0] ?? '')) !== '' && count($list) === 1 ? trim((string) $labels[0]) : pathinfo((string) $file['name'], PATHINFO_FILENAME);
                $am->addAttachment($id, $kind, $r['path'], null, (string) $file['name'], $label);
            }
        }
        foreach ($newLinks as [$u, $label]) $am->addAttachment($id, 'link', null, $u, null, $label);

        if (!empty($p['save_template']) && $rubric && trim((string) ($p['template_name'] ?? '')) !== '') {
            $am->saveRubricTemplate($tutorId, trim((string) $p['template_name']), array_map(fn($r) => ['name' => $r[1], 'max' => $r[2]], $rubric));
        }

        $fresh = $am->get($id);
        $msg = 'Saved as a draft. Students cannot see it yet.';
        if ($publish && $fresh['status'] === 'draft' && $publishUtc) {
            $am->log($id, $me, null, 'schedule', ['publish_at' => $publishUtc]);
            $msg = 'Scheduled. Students will see it on ' . AssignmentModel::local($publishUtc) . ' EAT and get the email then.';
        } elseif ($publish && $fresh['status'] === 'draft') {
            $uids = $am->publish($id, $me);
            (new AssignmentNotifier($pdo, $am))->published($am->get($id), $uids);
            $msg = 'Published. ' . count($uids) . ' student' . (count($uids) === 1 ? ' has' : 's have') . ' been emailed and notified.';
        } elseif ($fresh['status'] === 'published') {
            $uids = $am->unnotified($id);
            if ($uids) (new AssignmentNotifier($pdo, $am))->published($fresh, $uids);
            $msg = 'Changes saved.' . ($uids ? ' ' . count($uids) . ' newly added student' . (count($uids) === 1 ? ' was' : 's were') . ' notified.' : '');
        }
        if ($attErrors) SessionModel::setFlash('error', htmlspecialchars('Some attachments were not added: ' . implode(' ', $attErrors)));
        asg_back($am->get($id)['status'] === 'published' ? 'view.php?id=' . $id : 'edit.php?id=' . $id, $msg);
    }
}

$attachments = $id ? $am->attachments($id) : [];
$isDraft = !$a || $a['status'] === 'draft';
$subjectsByCourse = [];
foreach ($courses as $cid => $ct) {
    foreach (subj_list($pdo, (int) $cid, $tutorId) as $sj) {
        if ((int) $sj['is_active'] || (int) $sj['id'] === (int) $f['subject_id']) $subjectsByCourse[(int) $cid][] = $sj;
    }
}
$orderNo = $a ? $am->number($a) : count(array_filter($am->listAll($tutorId), fn($x) => (int) $x['course_id'] === (int) $f['course_id'] && ($x['kind'] ?? 'daily') === $f['kind'])) + 1;
$page_title = $a ? 'Edit assignment' : 'New assignment';
$selCourse = $f['course_id'];
// Students listed per course (a student in two of the tutor's courses appears under each); 0 = older assignment with no course.
$byCourse = [];
foreach ($courses as $cid => $ct) $byCourse[(int) $cid] = $am->tutorStudents($tutorId, (int) $cid);
if ($a && !$a['course_id']) $byCourse[0] = $am->tutorStudents($tutorId);
ob_start();
?>
<a href="<?php echo $a ? 'view.php?id=' . $id : 'index.php'; ?>" class="btn btn-link btn-sm ps-0 mb-2">← <?php echo $a ? 'Back to the assignment' : 'Assignments'; ?></a>
<?php if ($errors): ?><div class="alert alert-danger"><strong>Please fix:</strong><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo asg_e($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="asgForm"><?php echo csrf_field(); ?>
<div class="row g-3">
<div class="col-lg-8">
    <div class="card-tutor p-3 p-md-4">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h5 class="mb-0"><?php echo $a ? asg_e($a['title']) : 'New assignment'; ?></h5>
            <span class="badge <?php echo $isDraft ? 'bg-secondary' : 'bg-success'; ?>"><?php echo $isDraft ? 'DRAFT' : 'PUBLISHED'; ?></span>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Course</label>
            <select name="course_id" id="courseSel" class="form-select" required>
                <option value="">Choose a course…</option>
                <?php if ($a && !$a['course_id']): ?><option value="0" selected>No course (older assignment)</option><?php endif; ?>
                <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>" <?php echo (int) $cid === (int) $selCourse ? 'selected' : ''; ?>><?php echo asg_e($ct); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label fw-semibold">Kind</label>
            <div class="d-flex gap-3">
                <?php foreach (AssignmentModel::KINDS as $k => $lbl): ?>
                <div class="form-check"><input class="form-check-input" type="radio" name="kind" value="<?php echo $k; ?>" id="kind<?php echo $k; ?>" <?php echo $f['kind'] === $k ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="kind<?php echo $k; ?>"><?php echo asg_e($lbl); ?></label></div>
                <?php endforeach; ?>
            </div>
            <div class="small text-muted">Daily, weekly and monthly work are numbered and locked in their own order.</div></div>
        <div class="mb-3" id="subjectBox" data-must="<?php echo !$a || (int) ($a['subject_id'] ?? 0) > 0 ? '1' : '0'; ?>"><label class="form-label fw-semibold" for="subjectSel">Subject <span class="text-muted fw-normal" id="subjectReq"></span></label>
            <div class="input-group">
                <select name="subject_id" id="subjectSel" class="form-select"><option value="">No subject</option>
                    <?php foreach ($subjectsByCourse as $cid => $list): foreach ($list as $sj): ?><option value="<?php echo (int) $sj['id']; ?>" data-course="<?php echo (int) $cid; ?>" <?php echo (int) $sj['id'] === (int) $f['subject_id'] ? 'selected' : ''; ?>><?php echo asg_e($sj['name'] . ($sj['code'] ? ' (' . $sj['code'] . ')' : '') . ((int) $sj['is_active'] ? '' : ' - inactive')); ?></option><?php endforeach; endforeach; ?>
                </select>
                <button type="button" class="btn btn-outline-secondary" id="subjNew"><i class="fas fa-plus me-1"></i>New subject</button>
            </div>
            <div class="small text-muted">The subject mark is worked out by itself from the marks of the work under it. <a href="subjects.php" target="_blank">Manage subjects</a></div>
            <div class="border rounded p-2 mt-2 d-none" id="subjForm">
                <div class="row g-2">
                    <div class="col-sm-6"><label class="form-label small mb-0">Name</label><input id="subjName" class="form-control form-control-sm" maxlength="120" placeholder="e.g. Python Programming"></div>
                    <div class="col-6 col-sm-3"><label class="form-label small mb-0">Weight</label><input id="subjWeight" type="number" step="0.5" min="0.5" max="100" class="form-control form-control-sm" placeholder="default"></div>
                    <div class="col-6 col-sm-3"><label class="form-label small mb-0">Monthly projects</label><input id="subjMonthly" type="number" min="1" max="12" value="1" class="form-control form-control-sm"></div>
                </div>
                <div class="d-flex gap-2 mt-2 align-items-center"><button type="button" class="btn btn-brand btn-sm" id="subjSave">Add subject</button><button type="button" class="btn btn-light btn-sm" id="subjCancel">Cancel</button><span class="small text-danger" id="subjErr"></span></div>
            </div>
        </div>
        <div class="mb-3"><label class="form-label fw-semibold">Title</label>
            <input name="title" class="form-control" maxlength="120" required value="<?php echo asg_e($f['title']); ?>" placeholder="e.g. Build a login form"></div>
        <div class="mb-3"><label class="form-label fw-semibold">Description <span class="text-muted fw-normal">(short summary, shown at the top and in the email)</span></label>
            <textarea name="description" class="form-control" rows="2" maxlength="500" required><?php echo asg_e($f['description']); ?></textarea></div>
        <div class="row g-2 mb-3">
            <div class="col-sm-7"><label class="form-label fw-semibold">Due date</label><input type="date" name="due_date" class="form-control" required value="<?php echo asg_e($f['due_date']); ?>"></div>
            <div class="col-sm-5"><label class="form-label fw-semibold">Time <span class="text-muted fw-normal">(East Africa Time)</span></label><input type="time" name="due_time" class="form-control" required value="<?php echo asg_e($f['due_time']); ?>"></div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Assignment details</label>
            <div class="asg-rte-bar" role="toolbar" aria-label="Formatting">
                <button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button>
                <button type="button" data-cmd="underline"><u>U</u></button><button type="button" data-cmd="insertUnorderedList">List</button>
                <button type="button" data-cmd="insertOrderedList">1. List</button><button type="button" data-cmd="createLink">Link</button>
            </div>
            <div class="asg-rte" id="rte" contenteditable="true" aria-label="Assignment details"><?php echo $f['details']; ?></div>
            <input type="hidden" name="details" id="detailsInput">
            <div class="form-text">Bold, italic, underline, lists and links only. Anything else is removed when you save.</div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Questions <span class="text-muted fw-normal">(optional; each gets its own answer box when Written answers is on)</span></label>
            <div id="qList">
                <?php foreach ($f['questions'] as $i => [$qid, $qt]): ?>
                <div class="input-group mb-2 q-row"><span class="input-group-text fw-bold q-no">Q<?php echo $i + 1; ?></span>
                    <input type="hidden" name="q_id[]" value="<?php echo (int) $qid; ?>"><input name="q_text[]" class="form-control" value="<?php echo asg_e($qt); ?>">
                    <button type="button" class="btn btn-outline-secondary q-del">Remove</button></div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-success" id="qAdd"><i class="fas fa-plus me-1"></i>Add question</button>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Attachments <span class="text-muted fw-normal">(images JPG/PNG up to 5 MB, PDFs up to 10 MB, links; up to 10)</span></label>
            <?php if ($attachments): ?><div class="mb-2"><?php foreach ($attachments as $att): ?>
                <label class="asg-pill"><i class="fas <?php echo $att['kind'] === 'link' ? 'fa-link' : ($att['kind'] === 'pdf' ? 'fa-file-pdf' : 'fa-image'); ?>"></i><?php echo asg_e($att['label']); ?>
                    <input type="checkbox" name="remove_att[]" value="<?php echo (int) $att['id']; ?>" class="form-check-input ms-1" title="Tick to remove"> <span class="small text-muted">remove</span></label>
            <?php endforeach; ?></div><?php endif; ?>
            <?php if ($a && $a['attachment_path']): ?><div class="small mb-2">Earlier attachment: <a href="<?php echo asg_e(settings_asset_url($a['attachment_path'])); ?>" target="_blank" rel="noopener">open</a></div><?php endif; ?>
            <div class="row g-2">
                <div class="col-md-6"><div class="small fw-semibold mb-1"><i class="fas fa-image me-1"></i>Images</div><input type="file" name="att_images[]" accept=".jpg,.jpeg,.png" multiple class="form-control form-control-sm"></div>
                <div class="col-md-6"><div class="small fw-semibold mb-1"><i class="fas fa-file-pdf me-1"></i>PDFs</div><input type="file" name="att_pdfs[]" accept=".pdf" multiple class="form-control form-control-sm"></div>
            </div>
            <div class="small fw-semibold mt-2 mb-1"><i class="fas fa-link me-1"></i>Links</div>
            <div id="linkList"></div>
            <button type="button" class="btn btn-sm btn-outline-success" id="linkAdd"><i class="fas fa-plus me-1"></i>Link</button>
            <div class="form-text">Files are named after the uploaded file; links use the label you type.</div>
        </div>
        <div class="mb-3"><label class="form-label fw-semibold">Instructions <span class="text-muted fw-normal">(how to do and send the work)</span></label>
            <textarea name="instructions" class="form-control" rows="3" placeholder="e.g. Work alone. Put your code on GitHub and paste the link. Make sure the repository is public."><?php echo asg_e($f['instructions']); ?></textarea></div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($isDraft): ?>
                <button name="action" value="draft" class="btn btn-outline-dark"><i class="fas fa-floppy-disk me-1"></i>Save draft</button>
                <button name="action" value="publish" class="btn btn-brand"><i class="fas fa-paper-plane me-1"></i>Publish and notify students</button>
            <?php else: ?>
                <button name="action" value="save" class="btn btn-brand"><i class="fas fa-floppy-disk me-1"></i>Save changes</button>
                <span class="small text-muted align-self-center">Students you add now are emailed. Students you untick lose access.</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="col-lg-4">
    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold mb-1">Who sees it</h6>
        <div class="small text-muted fst-italic mb-2">Only students you teach in this course are listed.</div>
        <input type="search" id="stuSearch" class="form-control form-control-sm mb-2" placeholder="Search students">
        <div class="form-check mb-1"><input class="form-check-input" type="checkbox" id="stuAll"><label class="form-check-label fw-semibold" for="stuAll">Select all (<span id="stuVisible">0</span>)</label></div>
        <div id="stuList" style="max-height:260px;overflow:auto">
            <?php foreach ($byCourse as $cid => $list): foreach ($list as $uid => $s): $key = $cid . '_' . (int) $uid; ?>
            <div class="form-check stu" data-course="<?php echo (int) $cid; ?>" data-name="<?php echo asg_e(mb_strtolower($s['full_name'] . ' ' . $s['registration_no'])); ?>">
                <input class="form-check-input" type="checkbox" name="students[]" value="<?php echo (int) $uid; ?>" id="stu<?php echo $key; ?>" <?php echo (int) $cid === (int) $selCourse && in_array((int) $uid, $f['recipients'], true) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="stu<?php echo $key; ?>"><?php echo asg_e($s['full_name']); ?> <span class="small text-muted"><?php echo asg_e($s['registration_no']); ?></span></label>
            </div>
            <?php endforeach; endforeach; ?>
            <div class="small text-muted" id="stuNone" style="display:none">No students of yours in this course.</div>
        </div>
        <div class="small fw-bold text-success mt-2"><span id="stuCount">0</span> selected</div>
        <details class="mt-2 small"><summary class="fw-semibold text-success">Give one student a different due date</summary>
            <div id="ovList" class="mt-2">
                <div class="d-flex gap-2 mb-1"><select name="ov_student[]" class="form-select form-select-sm"><option value="">Student…</option>
                    <?php foreach (($byCourse[(int) $selCourse] ?? []) as $ouid => $os): ?><option value="<?php echo (int) $ouid; ?>"><?php echo asg_e($os['full_name']); ?></option><?php endforeach; ?></select>
                    <input type="datetime-local" name="ov_due[]" class="form-control form-control-sm"></div>
            </div>
            <div class="text-muted">Late is judged against this date. You can also change one student's date later from the assignment page.</div>
        </details>
    </div>

    <?php if ($isDraft): ?>
    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold mb-1">Show to students</h6>
        <div class="small text-muted mb-2">Choose when it appears. A scheduled assignment stays hidden until then, and students get the email at that time.</div>
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <div class="form-check"><input class="form-check-input" type="radio" name="show_at" value="now" id="showNow" <?php echo $f['show_at'] === 'now' ? 'checked' : ''; ?>><label class="form-check-label small" for="showNow">Now</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="show_at" value="later" id="showLater" <?php echo $f['show_at'] === 'later' ? 'checked' : ''; ?>><label class="form-check-label small" for="showLater">On a date and time (EAT)</label></div>
            <input type="date" name="publish_date" class="form-control form-control-sm w-auto" value="<?php echo asg_e($f['publish_date']); ?>">
            <input type="time" name="publish_time" class="form-control form-control-sm w-auto" value="<?php echo asg_e($f['publish_time']); ?>">
        </div>
    </div>
    <?php endif; ?>

    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold mb-2">Students submit as</h6>
        <?php foreach (['link' => 'Link (GitHub, Google Docs, Canva, video…)', 'text' => 'Written answers', 'images' => 'Photos or screenshots', 'file' => 'File upload (PDF, Word, Excel, PowerPoint, ZIP)'] as $k => $lbl): ?>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="types[]" value="<?php echo $k; ?>" id="ty<?php echo $k; ?>" <?php echo in_array($k, $f['types'], true) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="ty<?php echo $k; ?>"><?php echo asg_e($lbl); ?></label></div>
        <?php endforeach; ?>
        <?php foreach (['submit_code' => 'Code box (fixed-width text, never run)', 'submit_audio' => 'Audio recording (up to 2 minutes)', 'submit_quiz' => 'Quiz questions (marked by the system)', 'no_submission' => 'No submission (practical: you record the mark)'] as $k => $lbl): ?>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="<?php echo $k; ?>" value="1" id="sw<?php echo $k; ?>" <?php echo $f[$k] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="sw<?php echo $k; ?>"><?php echo asg_e($lbl); ?></label></div>
        <?php endforeach; ?>
        <div id="quizBox" class="mt-2 <?php echo $f['submit_quiz'] ? '' : 'd-none'; ?>">
            <div class="small fw-semibold mb-1">Quiz questions <span class="text-muted fw-normal">(multiple choice: one choice per line; wrong answers are hidden until grades are released)</span></div>
            <div id="qzList">
            <?php foreach ($f['quiz'] ?: [[0, 'mcq', '', [], 0, 1]] as $q): ?>
                <div class="asg-box mb-2 qz-row"><input type="hidden" name="qz_id[]" value="<?php echo (int) $q[0]; ?>">
                    <div class="d-flex gap-2 mb-1"><select name="qz_type[]" class="form-select form-select-sm w-auto"><option value="mcq">Multiple choice</option><option value="truefalse" <?php echo $q[1] === 'truefalse' ? 'selected' : ''; ?>>True or false</option></select>
                        <input name="qz_points[]" type="number" min="0.5" step="0.5" class="form-control form-control-sm" style="max-width:90px" value="<?php echo asg_e((string) $q[5]); ?>" title="Points">
                        <input name="qz_correct[]" type="number" min="1" class="form-control form-control-sm qz-c" style="max-width:110px" value="<?php echo (int) $q[4] + 1; ?>" title="Correct choice number">
                        <button type="button" class="btn btn-sm btn-outline-secondary qz-del" aria-label="Remove">×</button></div>
                    <input name="qz_prompt[]" class="form-control form-control-sm mb-1" placeholder="Question" value="<?php echo asg_e($q[2]); ?>">
                    <textarea name="qz_options[]" class="form-control form-control-sm" rows="3" placeholder="Choices, one per line (multiple choice only)"><?php echo asg_e($q[1] === 'mcq' ? implode("\n", $q[3]) : ''); ?></textarea>
                    <div class="small text-muted">The correct choice is the number in the third box (1 = first line; for True or false, 1 = True).</div></div>
            <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-success" id="qzAdd">Add a quiz question</button>
        </div>
        <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" role="switch" name="groups_on" value="1" id="swGroups" <?php echo $f['groups_on'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="swGroups">Students work in groups <span class="text-muted small">· one submission per group (up to 3 students), same mark and feedback</span></label></div>
        <div id="groupBox" class="asg-box mt-2 small <?php echo $f['groups_on'] ? '' : 'd-none'; ?>">
            <div class="text-muted mb-1">Give each chosen student a group number. Students with no number work alone.<?php echo $id && $am->hasSubmissions($id) ? ' Work has been sent, so groups can no longer change.' : ''; ?></div>
            <?php foreach (($byCourse[(int) $selCourse] ?? []) as $guid => $gs): ?>
                <div class="d-flex justify-content-between align-items-center py-1 grp-row" data-uid="<?php echo (int) $guid; ?>"><span><?php echo asg_e($gs['full_name']); ?></span>
                    <select name="group[<?php echo (int) $guid; ?>]" class="form-select form-select-sm w-auto"><option value="0">Alone</option>
                        <?php for ($gn = 1; $gn <= 20; $gn++): ?><option value="<?php echo $gn; ?>" <?php echo ($f['groups'][(int) $guid] ?? 0) === $gn ? 'selected' : ''; ?>>Group <?php echo $gn; ?></option><?php endfor; ?></select></div>
            <?php endforeach; ?>
        </div>
        <details class="mt-2 small" id="sitesBox" <?php echo $f['link_sites'] ? 'open' : ''; ?>><summary>Only accept links from some sites</summary>
            <div class="text-muted my-1">Leave all unticked to accept any https link.</div>
            <?php foreach (ASG_LINK_SITES as $host => $lbl): ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="link_sites[]" value="<?php echo asg_e($host); ?>" id="site<?php echo md5($host); ?>" <?php echo in_array($host, $f['link_sites'], true) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="site<?php echo md5($host); ?>"><?php echo asg_e($lbl); ?></label></div>
            <?php endforeach; ?>
        </details>
    </div>

    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold mb-1">Order</h6>
        <div class="small text-muted mb-2">This <?php echo $a ? 'is' : 'will be'; ?> <?php echo AssignmentModel::noun($f['kind']); ?> <?php echo (int) $orderNo; ?> in this course. Drag to reorder on the Assignments page.</div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="sequential_lock" value="1" id="seqLock" <?php echo $f['sequential_lock'] ? 'checked' : ''; ?>>
            <label class="form-check-label small" for="seqLock">Locked until the student submits the one before</label></div>
    </div>

    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold mb-2">Rules</h6>
        <?php foreach (['require_own_work' => ['Own-work tick before submitting', '"This is my own work."'], 'ask_ai_note' => ['Ask "Did you use AI? Tell us how."', 'Optional note from the student'],
                        'check_links' => ['Check links before accepting', 'Warns the student if a Google Docs or Drive link is private'], 'block_bad_links' => ['Do not accept links that cannot be opened', '"Could not check" never blocks'],
                        'remind_enabled' => ['Remind students who have not submitted', 'Email 24 hours and 1 hour before the due date'], 'remind_whatsapp' => ['Also send a WhatsApp or SMS reminder', 'Sent only when a gateway is set up']] as $k => [$lbl, $hint]): ?>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="<?php echo $k; ?>" value="1" id="sw<?php echo $k; ?>" <?php echo $f[$k] ? 'checked' : ''; ?>>
                <label class="form-check-label small" for="sw<?php echo $k; ?>"><?php echo asg_e($lbl); ?> <span class="text-muted">· <?php echo asg_e($hint); ?></span></label></div>
        <?php endforeach; ?>
    </div>

    <?php if ($tutorClasses): ?>
    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold mb-1">Connect it</h6>
        <label class="form-label small mb-1">After this class</label>
        <select name="class_session_id" class="form-select form-select-sm"><option value="0">None</option>
            <?php foreach ($tutorClasses as $c): ?><option value="<?php echo (int) $c['id']; ?>" <?php echo (int) $c['id'] === (int) $f['class_session_id'] ? 'selected' : ''; ?>><?php echo asg_e(AssignmentModel::local($c['start_utc'], 'D j M, H:i') . ' · ' . $c['title']); ?></option><?php endforeach; ?>
        </select>
        <div class="small text-muted mt-1">Students see the class on the assignment page.</div>
    </div>
    <?php endif; ?>

    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold mb-2">Grading</h6>
        <?php foreach (AssignmentModel::GRADING_MODES as $k => $lbl): ?>
            <div class="form-check"><input class="form-check-input" type="radio" name="grading_mode" value="<?php echo $k; ?>" id="gm<?php echo $k; ?>" <?php echo $f['grading_mode'] === $k ? 'checked' : ''; ?>>
                <label class="form-check-label" for="gm<?php echo $k; ?>"><?php echo asg_e($lbl); ?></label></div>
        <?php endforeach; ?>
        <div id="marksBox" class="mt-2">
            <div class="input-group input-group-sm mb-2"><span class="input-group-text">Marks out of</span><input type="number" name="max_score" id="maxScore" min="1" max="1000" class="form-control" value="<?php echo (int) $f['max_score']; ?>"></div>
            <div class="small fw-semibold mb-1">Rubric criteria <span class="text-muted fw-normal">(optional; must add up to Marks out of)</span></div>
            <div id="rList">
                <?php foreach ($f['rubric'] as [$rid, $rn, $rm]): ?>
                <div class="input-group input-group-sm mb-1 r-row"><input type="hidden" name="r_id[]" value="<?php echo (int) $rid; ?>">
                    <input name="r_name[]" class="form-control" value="<?php echo asg_e($rn); ?>" placeholder="Criterion">
                    <input name="r_max[]" type="number" step="0.5" min="0" class="form-control r-max" style="max-width:80px" value="<?php echo asg_e((string) (float) $rm); ?>">
                    <button type="button" class="btn btn-outline-secondary r-del" aria-label="Remove">×</button></div>
                <?php endforeach; ?>
            </div>
            <div class="small mb-2" id="rSum"></div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" id="rAdd">Add rubric criteria</button>
                <?php if ($templates): ?>
                <select class="form-select form-select-sm w-auto" id="rTpl"><option value="">Load a saved rubric…</option>
                    <?php foreach ($templates as $t): ?><option value="<?php echo asg_e($t['criteria_json']); ?>"><?php echo asg_e($t['name']); ?></option><?php endforeach; ?></select>
                <?php endif; ?>
            </div>
            <div class="form-check mt-2 small"><input class="form-check-input" type="checkbox" name="save_template" value="1" id="saveTpl"><label class="form-check-label" for="saveTpl">Save these criteria as a reusable rubric named</label></div>
            <input name="template_name" class="form-control form-control-sm mt-1" placeholder="e.g. Coding project rubric">
        </div>
        <div class="input-group input-group-sm mt-2"><span class="input-group-text">Pass mark</span><input type="number" name="pass_mark_percent" min="1" max="100" class="form-control" value="<?php echo (int) $f['pass_mark_percent']; ?>"><span class="input-group-text">%</span></div>
        <div class="input-group input-group-sm mt-2"><span class="input-group-text">Weight</span><input type="number" name="weight" min="0.25" max="100" step="0.25" class="form-control" value="<?php echo asg_e((string) (float) $f['weight']); ?>"><span class="input-group-text">in the course average</span></div>
        <div class="small fw-semibold mt-3">Release grades</div>
        <div class="small text-muted mb-1">Students will not see marks until you release them.</div>
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <div class="form-check"><input class="form-check-input" type="radio" name="release" value="now" id="relNow" <?php echo $f['release'] === 'now' ? 'checked' : ''; ?>><label class="form-check-label small" for="relNow">As I mark</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="release" value="later" id="relLater" <?php echo $f['release'] === 'later' ? 'checked' : ''; ?>><label class="form-check-label small" for="relLater">On a date and time (EAT)</label></div>
            <input type="date" name="release_date" class="form-control form-control-sm w-auto" value="<?php echo asg_e($f['release_date']); ?>">
            <input type="time" name="release_time" class="form-control form-control-sm w-auto" value="<?php echo asg_e($f['release_time']); ?>">
        </div>
        <hr>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="allow_resubmission" value="1" id="allowResub" <?php echo $f['allow_resubmission'] ? 'checked' : ''; ?>>
            <label class="form-check-label small" for="allowResub">Allow resubmission when I return the work</label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="close_after_due" value="1" id="closeDue" <?php echo $f['close_after_due'] ? 'checked' : ''; ?>>
            <label class="form-check-label small" for="closeDue">Closes after the due date <span class="text-muted">(off = accept late work, marked Late)</span></label></div>
        <div class="input-group input-group-sm mt-2"><span class="input-group-text">Students may ask for up to</span><input type="number" name="max_extension_days" min="0" max="60" class="form-control" value="<?php echo (int) $f['max_extension_days']; ?>"><span class="input-group-text">days extra</span></div>
    </div>
</div>
</div>
</form>

<template id="qTpl"><div class="input-group mb-2 q-row"><span class="input-group-text fw-bold q-no"></span><input type="hidden" name="q_id[]" value="0"><input name="q_text[]" class="form-control" placeholder="Type the question"><button type="button" class="btn btn-outline-secondary q-del">Remove</button></div></template>
<template id="rTplRow"><div class="input-group input-group-sm mb-1 r-row"><input type="hidden" name="r_id[]" value="0"><input name="r_name[]" class="form-control" placeholder="Criterion"><input name="r_max[]" type="number" step="0.5" min="0" class="form-control r-max" style="max-width:80px" placeholder="Marks"><button type="button" class="btn btn-outline-secondary r-del" aria-label="Remove">×</button></div></template>
<template id="linkTpl"><div class="input-group input-group-sm mb-1"><input name="att_link_label[]" class="form-control" placeholder="Label, e.g. MDN forms guide"><input name="att_link_url[]" type="url" class="form-control" placeholder="https://"><button type="button" class="btn btn-outline-secondary" onclick="this.parentNode.remove()">×</button></div></template>
<?php
$content = ob_get_clean();
$extra_js = <<<'JS'
<script>
(function () {
    var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    // Formatting toolbar
    $$('.asg-rte-bar button').forEach(function (b) {
        b.addEventListener('click', function () {
            var cmd = b.dataset.cmd, val = null;
            if (cmd === 'createLink') { val = prompt('Link address (https://…)'); if (!val || !/^https?:\/\//i.test(val)) return; }
            document.execCommand(cmd, false, val); $('#rte').focus();
        });
    });
    $('#asgForm').addEventListener('submit', function () { $('#detailsInput').value = $('#rte').innerHTML; });

    // Questions
    function renumber() { $$('#qList .q-row').forEach(function (r, i) { $('.q-no', r).textContent = 'Q' + (i + 1); }); }
    $('#qAdd').addEventListener('click', function () { $('#qList').appendChild($('#qTpl').content.cloneNode(true)); renumber(); $$('#qList input[name="q_text[]"]').pop().focus(); });
    $('#qList').addEventListener('click', function (e) { if (e.target.classList.contains('q-del')) { e.target.closest('.q-row').remove(); renumber(); } });
    $('#linkAdd').addEventListener('click', function () { $('#linkList').appendChild($('#linkTpl').content.cloneNode(true)); });

    // Rubric
    function rubricSum() {
        var sum = $$('#rList .r-max').reduce(function (t, i) { return t + (parseFloat(i.value) || 0); }, 0), max = parseFloat($('#maxScore').value) || 0, rows = $$('#rList .r-row').length;
        $('#rSum').innerHTML = rows ? (Math.abs(sum - max) < 0.001 ? '<span class="text-success"><i class="fas fa-check"></i> Adds up to ' + max + '</span>' : '<span class="text-danger"><i class="fas fa-triangle-exclamation"></i> Adds up to ' + sum + ', needs ' + max + '</span>') : '';
    }
    function addCrit(name, max) { var n = $('#rTplRow').content.cloneNode(true); $('input[name="r_name[]"]', n).value = name || ''; $('input[name="r_max[]"]', n).value = max || ''; $('#rList').appendChild(n); rubricSum(); }
    $('#rAdd').addEventListener('click', function () { addCrit(); });
    $('#rList').addEventListener('click', function (e) { if (e.target.classList.contains('r-del')) { e.target.closest('.r-row').remove(); rubricSum(); } });
    $('#rList').addEventListener('input', rubricSum); $('#maxScore').addEventListener('input', rubricSum);
    if ($('#rTpl')) $('#rTpl').addEventListener('change', function () {
        if (!this.value) return;
        if ($$('#rList .r-row').length && !confirm('Replace the current criteria?')) { this.value = ''; return; }
        $('#rList').innerHTML = ''; JSON.parse(this.value).forEach(function (c) { addCrit(c.name, c.max); }); this.value = '';
    });
    rubricSum();
    function gradingMode() { var m = ($('input[name=grading_mode]:checked') || {}).value; $('#marksBox').style.display = m === 'marks' ? '' : 'none'; }
    $$('input[name=grading_mode]').forEach(function (r) { r.addEventListener('change', gradingMode); }); gradingMode();
    function sites() { $('#sitesBox').style.display = $('#tylink').checked ? '' : 'none'; }
    $('#tylink').addEventListener('change', sites); sites();

    // Student picker: only the chosen course's students; search; select all; count.
    function picker() {
        var course = $('#courseSel').value, q = $('#stuSearch').value.trim().toLowerCase(), shown = 0;
        $$('#stuList .stu').forEach(function (r) {
            var inCourse = r.dataset.course === course, match = !q || r.dataset.name.indexOf(q) !== -1;
            if (!inCourse) $('input', r).checked = false;
            r.style.display = inCourse && match ? '' : 'none';
            if (inCourse && match) shown++;
        });
        $('#stuVisible').textContent = shown;
        $('#stuNone').style.display = shown ? 'none' : '';
        var vis = $$('#stuList .stu').filter(function (r) { return r.style.display !== 'none'; });
        $('#stuAll').checked = vis.length > 0 && vis.every(function (r) { return $('input', r).checked; });
        $('#stuCount').textContent = $$('#stuList input:checked').length;
    }
    // Subject box: shows the subjects of the chosen course; New subject adds one without leaving the form.
    var subjSel = $('#subjectSel'), subjMust = $('#subjectBox').dataset.must === '1';
    var subjAll = $$('#subjectSel option[data-course]').map(function (o) { return o.cloneNode(true); });
    function subjects() {
        var course = $('#courseSel').value, keep = subjSel.value, n = 0;
        $$('#subjectSel option[data-course]').forEach(function (o) { o.remove(); });
        subjAll.forEach(function (o) { if (o.dataset.course === course) { subjSel.appendChild(o.cloneNode(true)); n++; } });
        subjSel.value = keep; if (subjSel.value !== keep) subjSel.value = '';
        subjSel.required = n > 0 && subjMust;
        $('#subjectReq').textContent = n === 0 ? '(this course has no subjects yet)' : (subjMust ? '(required for this course)' : '(optional for this older assignment)');
        $('#subjNew').disabled = !course;
    }
    $('#courseSel').addEventListener('change', subjects); subjects();
    $('#subjNew').addEventListener('click', function () { $('#subjForm').classList.remove('d-none'); $('#subjName').focus(); });
    $('#subjCancel').addEventListener('click', function () { $('#subjForm').classList.add('d-none'); $('#subjErr').textContent = ''; });
    $('#subjSave').addEventListener('click', function () {
        var fd = new FormData();
        fd.append('csrf_token', $('#asgForm input[name="csrf_token"]').value); fd.append('action', 'create_ajax'); fd.append('course_id', $('#courseSel').value);
        fd.append('name', $('#subjName').value); fd.append('weight', $('#subjWeight').value); fd.append('monthly_expected', $('#subjMonthly').value);
        fetch('subjects.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j.ok) { $('#subjErr').textContent = j.error || 'Could not add the subject.'; return; }
            var o = document.createElement('option'); o.value = j.id; o.dataset.course = $('#courseSel').value; o.textContent = j.label;
            subjAll.push(o.cloneNode(true)); subjSel.appendChild(o); subjSel.value = String(j.id); subjSel.required = subjMust;
            $('#subjectReq').textContent = subjMust ? '(required for this course)' : '(optional for this older assignment)'; $('#subjName').value = ''; $('#subjWeight').value = ''; $('#subjErr').textContent = ''; $('#subjForm').classList.add('d-none');
        }).catch(function () { $('#subjErr').textContent = 'Could not add the subject. Try again.'; });
    });
    $('#courseSel').addEventListener('change', picker); $('#stuSearch').addEventListener('input', picker);
    $('#stuList').addEventListener('change', picker);
    $('#stuAll').addEventListener('change', function () {
        var on = this.checked;
        $$('#stuList .stu').forEach(function (r) { if (r.style.display !== 'none') $('input', r).checked = on; }); picker();
    });
    picker();
    // Quiz questions: show when the switch is on; add and remove rows.
    var qz = $('#swsubmit_quiz');
    var sg = $('#swGroups');
    if (sg) sg.addEventListener('change', function () { $('#groupBox').classList.toggle('d-none', !this.checked); });
    if (qz) qz.addEventListener('change', function () { $('#quizBox').classList.toggle('d-none', !this.checked); });
    $('#qzAdd').addEventListener('click', function () {
        var row = $('#qzList .qz-row').cloneNode(true);
        $$('input, textarea', row).forEach(function (i) { i.value = i.name === 'qz_points[]' || i.name === 'qz_correct[]' ? '1' : (i.name === 'qz_id[]' ? '0' : ''); });
        $('#qzList').appendChild(row);
    });
    $('#qzList').addEventListener('click', function (e) {
        if (e.target.classList.contains('qz-del') && $$('#qzList .qz-row').length > 1) e.target.closest('.qz-row').remove();
    });
})();
</script>
JS;
require __DIR__ . '/../../templates/tutor/layout.php';
