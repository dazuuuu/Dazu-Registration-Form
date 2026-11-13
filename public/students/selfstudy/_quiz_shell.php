<?php
// Shared page frame for the quiz, exam and result screens: logo, course title and a close button. Standalone, like the study screen.
function ssq_open(string $pageTitle, string $courseTitle, string $closeUrl, string $timerHtml = ''): void
{
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $e($pageTitle); ?> — Dazu Training Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $e(url('assets/css/selfstudy-study.css')); ?>?v=2">
    <link rel="stylesheet" href="<?php echo $e(url('assets/css/selfstudy-quiz.css')); ?>?v=1">
</head>
<body class="ss-study ssq">
<header class="ss-top">
    <img class="logo" src="<?php echo $e(url('assets/images/logo/logo.png')); ?>" alt="Dazu Training Institute">
    <span class="sep"></span>
    <h1><?php echo $e($courseTitle); ?></h1>
    <?php echo $timerHtml; ?>
    <a class="ss-ic" href="<?php echo $e($closeUrl); ?>" aria-label="Close and go back to the course"><i class="fas fa-xmark"></i></a>
</header>
<?php
}
function ssq_close(string $cfgJson = ''): void
{
    if ($cfgJson !== '') echo '<script type="application/json" id="ssqCfg">' . $cfgJson . '</script>' . "\n";
    echo '<script src="' . htmlspecialchars(url('assets/js/selfstudy-quiz.js')) . '?v=1" defer></script>' . "\n</body>\n</html>\n";
}
