<?php
// public/components/selfstudy/ui.php — pieces shared by the Self-study tutor pages: the status pill, the title bar with its tabs, the card image.
function sst_e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function sst_pill(string $status): string
{
    $t = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'][$status] ?? ucfirst($status);
    return '<span class="sst-pill ' . sst_e($status) . '">' . $t . '</span>';
}

/** The card cover with srcset, like the student cards. */
function sst_cover_img(array $c, string $class = ''): string
{
    $a = ss_cover_attrs($c);
    if (!$a) return '<div class="sst-nocover ' . $class . '"><i class="fas fa-image"></i></div>';
    return '<img class="' . $class . '" src="' . sst_e($a['src']) . '"' . ($a['srcset'] ? ' srcset="' . sst_e($a['srcset']) . '" sizes="' . sst_e($a['sizes']) . '"' : '') . ' alt="" loading="lazy" decoding="async">';
}

/**
 * A clear warning on a PUBLISHED course when a quiz or exam has fewer questions than one attempt needs (for example after a tutor deleted some):
 * students cannot start it until it is full again. Empty string when everything is fine.
 */
function sst_bank_warnings(PDO $db, int $courseId, string $base): string
{
    if ($courseId <= 0) return '';
    $st = ss2_rows($db, 'SELECT status FROM self_study_courses WHERE id = ?', [$courseId])[0]['status'] ?? '';
    if ($st !== 'published') return '';
    $o = ssc_outline($db, $courseId); $items = [];
    foreach ($o['modules'] as $m) if ($m['quiz']['usable'] < $m['quiz']['need']) $items[] = [$m['title'] . ' · quiz', $m['quiz']['need'], $m['quiz']['usable'], 'quiz', 'module:' . $m['id']];
    if ($o['checkpoint']['usable'] < $o['checkpoint']['need']) $items[] = ['Checkpoint Exam', $o['checkpoint']['need'], $o['checkpoint']['usable'], 'exam', 'checkpoint'];
    if ($o['final']['usable'] < $o['final']['need']) $items[] = ['Final Exam', $o['final']['need'], $o['final']['usable'], 'exam', 'final'];
    if (!$items) return '';
    $h = '<div class="sst-note" role="alert"><b><i class="fas fa-triangle-exclamation"></i> Some students cannot start these yet.</b><ul style="margin:6px 0 4px 18px;padding:0">';
    foreach ($items as [$name, $need, $have, $word, $bank]) $h .= '<li><a href="' . sst_e($base . 'questions.php?id=' . $courseId . '&bank=' . $bank) . '" style="color:inherit;font-weight:600">' . sst_e($name) . '</a>: this ' . $word . ' needs ' . (int) $need . ' questions and has ' . (int) $have . '.</li>';
    return $h . '</ul>Students see "being updated" and cannot start it, and it does not unlock the next module or exam. Anyone who already passed keeps their pass.</div>';
}

/**
 * The title bar (course name + status pill + buttons) and the tabs. $tab is details|content|questions|who|sharing.
 * Only Details works in this step; the other tabs open pages built in the next steps.
 */
function sst_titlebar(?array $c, string $tab, string $base, bool $canEdit, bool $withForm, bool $publishReady = false, string $mode = 'details'): void
{
    $id = (int) ($c['id'] ?? 0);
    $name = $c && trim((string) $c['title']) !== '' ? $c['title'] : 'New self-study course';
    $status = $c['status'] ?? 'draft';
    $pillNote = ['draft' => $mode === 'who' ? 'DRAFT · nobody can see it yet' : 'DRAFT · only you can see it', 'published' => 'PUBLISHED · students can see it', 'archived' => 'ARCHIVED · hidden from students'][$status];
    ?>
    <div class="sst-bar">
        <div class="sst-bar-t"><h2><?php echo sst_e($name); ?></h2><span class="sst-pill <?php echo sst_e($status); ?>"><?php echo sst_e($mode === 'editor' ? ucfirst($status) : $pillNote); ?></span><?php if ($mode === 'editor'): ?><span class="sst-saved" id="sstSavedAt" aria-live="polite"></span><?php endif; ?></div>
        <div class="sst-bar-b">
            <?php if ($id && $status === 'published'): ?><a class="sst-btn" href="<?php echo sst_e($base . 'students.php?id=' . $id); ?>"><i class="fas fa-users"></i> Students</a><?php endif; ?>
            <?php if ($id): ?><a class="sst-btn" href="<?php echo sst_e($base . 'preview.php?id=' . $id); ?>" target="_blank" rel="noopener"><i class="fas fa-eye"></i> Preview as student</a><?php endif; ?>
            <?php if ($mode === 'editor' && $canEdit): ?>
                <button class="sst-btn" type="button" id="sstSaveNow">Save draft</button>
                <button class="sst-btn sst-primary" type="submit" form="f_publish_only" id="sstPublish"<?php echo $publishReady ? '' : ' disabled'; ?>><?php echo $status === 'published' ? 'Published' : 'Publish'; ?></button>
            <?php elseif ($mode === 'who'): ?>
                <?php if ($status === 'draft' && $canEdit): ?>
                    <button class="sst-btn" type="button" id="sstSaveStudents">Save draft</button>
                    <button class="sst-btn sst-primary" type="button" id="sstPublishStudents"<?php echo $publishReady ? '' : ' disabled'; ?>>Publish to 0 students</button>
                <?php else: ?><button class="sst-btn sst-primary" type="button" id="sstSaveStudents">Save changes</button><?php endif; ?>
            <?php elseif ($withForm && $canEdit): ?>
                <button class="sst-btn" type="submit" name="do" value="save" form="sstForm">Save draft</button>
                <button class="sst-btn sst-primary" type="submit" name="do" value="publish" form="sstForm" id="sstPublish"<?php echo $publishReady ? '' : ' disabled'; ?>><?php echo $status === 'published' ? 'Save changes' : 'Publish'; ?></button>
            <?php endif; ?>
        </div>
    </div>
    <nav class="sst-tabs" aria-label="Course sections">
        <?php foreach (['details' => ['Details', 'edit.php'], 'content' => ['Content', 'content.php'], 'questions' => ['Questions', 'questions.php'], 'who' => ['Who sees it', 'who.php'], 'sharing' => ['Sharing', 'sharing.php']] as $k => [$label, $file]):
            $href = $id ? $base . $file . '?id=' . $id . ($k !== 'details' ? '&tab=' . $k : '') : ($k === 'details' ? '#' : '#'); ?>
            <a class="<?php echo $tab === $k ? 'on' : ''; ?><?php echo !$id && $k !== 'details' ? ' off' : ''; ?>" href="<?php echo sst_e($href); ?>"<?php echo !$id && $k !== 'details' ? ' aria-disabled="true" title="Save the details first"' : ''; ?>><?php echo $label; ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}
