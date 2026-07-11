<?php
/**
 * Terminal auth layout — opening markup
 * Expects: $auth_page_title, $auth_window_title (optional)
 */
$auth_window_title = $auth_window_title ?? 'dazu@hub — auth.dazu.hub';
$auth_page_mode = $auth_page_mode ?? 'login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($auth_page_title ?? 'Sign in'); ?> — Dazu Hub Institute</title>
    <link rel="stylesheet" href="<?php echo asset('css/auth-terminal.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-terminal-body" data-auth-page="<?php echo htmlspecialchars($auth_page_mode); ?>">
<div class="auth-terminal-bg" aria-hidden="true"></div>
<div class="auth-terminal-grid" aria-hidden="true"></div>
<div class="auth-terminal-scanline" aria-hidden="true"></div>

<div class="auth-terminal-page">
    <aside class="auth-terminal-code-panel" aria-hidden="true">
        <div id="authCodePanel"></div>
    </aside>

    <div class="auth-terminal-main">
        <nav class="auth-terminal-nav">
            <a href="<?php echo url('/'); ?>"><i class="fas fa-arrow-left"></i> cd ..</a>
            <span class="auth-terminal-foot">Dazu Hub Institute</span>
        </nav>

        <div class="auth-terminal-window" role="region" aria-label="Authentication terminal">
            <header class="auth-terminal-titlebar">
                <div class="auth-terminal-dots" aria-hidden="true">
                    <span class="red"></span>
                    <span class="yellow"></span>
                    <span class="green"></span>
                </div>
                <span class="auth-terminal-title"><?php echo htmlspecialchars($auth_window_title); ?></span>
                <span class="auth-terminal-status">LIVE</span>
            </header>
            <div class="auth-terminal-screen">
