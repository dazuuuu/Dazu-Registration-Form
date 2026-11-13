<?php
// public/components/selfstudy/content.php — the Content tab: the course outline on the left, the open topic (text editor, video, files, topic questions) on the right.
// Owner and "Can edit" tutors can change things; "Can view" tutors and admin see it read-only. The API checks the same permission on every call.
require __DIR__ . '/boot.php';
require_once __DIR__ . '/ui.php';

$id = (int) ($_GET['id'] ?? 0);
$c = $sst_ready && ssc_installed($db) ? sst_course($db, $viewer, $id) : null;
if (!$sst_ready || !ssc_installed($db)) sst_deny(503, 'Self-study needs migration 108 first.', $sst_base);
if (!$c) sst_deny(404, 'That course was not found, or it was not shared with you.', $sst_base);
$canEdit = $c['can_edit'];
$ready = !array_filter(sst_checklist($db, $id), fn($i) => !$i['done']);
$page_title = 'Content: ' . $c['title'];
$extra_css = '<link rel="stylesheet" href="' . sst_e(url('assets/css/selfstudy-tutor.css')) . '?v=2">';
$cfg = ['csrf' => csrf_token(), 'endpoint' => $sst_base . 'api.php', 'course' => $id, 'readonly' => !$canEdit, 'mode' => 'content', 'download' => $sst_base . 'file.php?id=', 'questionsUrl' => $sst_base . 'questions.php?id=' . $id,
        'outline' => ssc_outline($db, $id), 'topicsPerAttempt' => SS2_TOPIC_QUESTIONS, 'siteUrl' => url('')];
ob_start();
?>
<div class="sst-page sst-ed">
<?php sst_titlebar($c, 'content', $sst_base, $canEdit, false, $ready, 'editor'); ?>
<?php echo sst_bank_warnings($db, $id, $sst_base); ?>
<?php if (!$canEdit): ?><div class="sst-note"><i class="fas fa-lock"></i> <?php echo $viewer['role'] === 'admin' ? 'You are looking at this course as admin. Only the owner and tutors with "Can edit" can change it.' : 'You can open this course but not change it ("Can view").'; ?></div><?php endif; ?>
<?php if ($canEdit): ?><form method="post" action="<?php echo sst_e($sst_base . 'action.php'); ?>" id="f_publish_only"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="do" value="publish_only"><input type="hidden" name="back" value="content"></form><?php endif; ?>
<div class="ssc-grid">
    <aside class="sst-card ssc-outline" id="sscOutline" aria-label="Course outline">
        <button type="button" class="ssc-otoggle" id="sscOToggle" aria-expanded="false"><span><i class="fas fa-list-ul"></i> Course outline</span><i class="fas fa-chevron-down"></i></button>
        <div class="ssc-obody" id="sscOBody">
            <h4>Course outline</h4>
            <div id="sscTree" role="tree"></div>
            <?php if ($canEdit): ?><div class="ssc-addrow"><button type="button" class="sst-btn" id="sscAddModule"><i class="fas fa-plus"></i> Add module</button><button type="button" class="sst-btn" id="sscAddTopic"><i class="fas fa-plus"></i> Add topic</button></div><?php endif; ?>
            <div id="sscArchived"></div>
        </div>
    </aside>
    <div class="ssc-main">
        <section class="sst-card" id="sscTopicCard">
            <div class="ssc-empty" id="sscNone"><i class="fas fa-pen-to-square"></i><p>Choose a topic on the left to write it<?php echo $canEdit ? ', or add a module and a topic.' : '.'; ?></p></div>
            <div id="sscEditor" hidden>
                <div class="sst-note ssc-conflict" id="sscConflict" hidden role="alert"></div>
                <div class="sst-note" id="sscOther" hidden></div>
                <label class="sst-l" for="sscTitle">Topic title</label>
                <input class="sst-in" type="text" id="sscTitle" maxlength="200" autocomplete="off">
                <div class="ssc-tools" id="sscTools" role="toolbar" aria-label="Text formatting">
                    <button type="button" data-cmd="h1" title="Heading 1" aria-label="Heading 1">H1</button>
                    <button type="button" data-cmd="h2" title="Heading 2" aria-label="Heading 2">H2</button>
                    <button type="button" data-cmd="bold" title="Bold" aria-label="Bold"><b>B</b></button>
                    <button type="button" data-cmd="italic" title="Italic" aria-label="Italic"><i>I</i></button>
                    <button type="button" data-cmd="ul" title="Bullet list" aria-label="Bullet list"><i class="fas fa-list-ul"></i></button>
                    <button type="button" data-cmd="ol" title="Numbered list" aria-label="Numbered list"><i class="fas fa-list-ol"></i></button>
                    <button type="button" data-cmd="table" title="Table" aria-label="Table"><i class="fas fa-table"></i></button>
                    <button type="button" data-cmd="code" title="Code block" aria-label="Code block"><i class="fas fa-code"></i></button>
                    <button type="button" data-cmd="image" title="Picture" aria-label="Picture"><i class="fas fa-image"></i></button>
                    <button type="button" data-cmd="video" title="YouTube video" aria-label="YouTube video"><i class="fas fa-video"></i></button>
                    <button type="button" data-cmd="attach" title="Attach a file" aria-label="Attach a file"><i class="fas fa-paperclip"></i></button>
                </div>
                <div class="ssc-body ss-doc" id="sscBody" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Topic text" data-placeholder="Write the topic here. Use the buttons above for headings, lists, tables, code and pictures."></div>
                <div class="ssc-video" id="sscVideoBox">
                    <i class="fas fa-video ssc-vic"></i>
                    <div class="ssc-vbody">
                        <label class="ssc-vt" for="sscVideo">YouTube video</label>
                        <input class="sst-in" type="url" id="sscVideo" placeholder="https://www.youtube.com/watch?v=…" autocomplete="off">
                        <div class="ssc-vrow"><label for="sscVLen">Video length (optional)</label><input class="sst-in" type="text" id="sscVLen" placeholder="5:58" inputmode="numeric" autocomplete="off"></div>
                        <div class="sst-err" id="sscVErr" role="alert"></div>
                    </div>
                    <img class="ssc-thumb" id="sscThumb" alt="Video thumbnail" hidden>
                    <p class="sst-hint ssc-vhint">Plays inside the course. Students must watch it from start to end, and skipping ahead is switched off. Only YouTube links are accepted.</p>
                </div>
                <div class="ssc-files"><div id="sscFiles"></div><?php if ($canEdit): ?><button type="button" class="sst-btn" id="sscAttachBtn"><i class="fas fa-paperclip"></i> Attach a file</button><?php endif; ?><span class="sst-hint">PDF, Word, PowerPoint, Excel, text, CSV, zip or picture, up to 20 MB. Shown in the students' Resources tab.</span></div>
                <input type="file" id="sscImgInput" accept="image/jpeg,image/png,image/webp" hidden>
                <input type="file" id="sscFileInput" hidden>
                <div class="sst-err" id="sscErr" role="alert"></div>
            </div>
        </section>
        <section class="sst-card" id="sscQBox" hidden></section>
    </div>
</div>
</div>
<script type="application/json" id="sscCfg"><?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo sst_e(url('assets/js/selfstudy-editor.js')); ?>?v=1" defer></script>
<?php
$content = ob_get_clean();
require $sst_layout;
