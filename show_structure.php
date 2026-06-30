<?php
/**
 * structure.php
 * ---------------------------------------------------------------------------
 * Lightweight, READ-ONLY directory scanner.
 * Drop it in your website root, open it in the browser (or run via CLI),
 * and it prints a clean tree of your project plus a quick tech-stack summary.
 *
 * It writes the same output to "structure.txt" next to this file so you can
 * just attach/paste that file.
 *
 * SAFE: it only reads names + sizes. It never opens, edits or deletes files.
 *
 * IMPORTANT: delete this file from a public/production server after use.
 * A public directory listing is information you don't want exposed.
 * ---------------------------------------------------------------------------
 */

// ----------------------------- CONFIG --------------------------------------

// Folders to skip entirely (noise / huge / irrelevant to code review).
$EXCLUDE_DIRS = [
    '.git', '.svn', '.hg',
    'node_modules', 'vendor', 'bower_components',
    '.idea', '.vscode', '.cache', 'cache', 'tmp', 'temp',
    'logs', 'log', '.next', 'dist', 'build', 'coverage',
];

// Specific files to skip.
$EXCLUDE_FILES = [
    '.DS_Store', 'Thumbs.db', 'structure.php', 'structure.txt',
];

// How deep to recurse. Increase if your project is deeply nested.
$MAX_DEPTH = 12;

// ---------------------------------------------------------------------------

$ROOT = __DIR__;
$isCli = (php_sapi_name() === 'cli');

$stats = [
    'dirs'  => 0,
    'files' => 0,
    'bytes' => 0,
    'ext'   => [],   // extension => count
];

/**
 * Recursively build the tree lines.
 */
function scan($dir, $prefix, $depth, &$out, &$stats, $cfg)
{
    if ($depth > $cfg['maxDepth']) {
        $out[] = $prefix . '└── … (max depth reached)';
        return;
    }

    $entries = @scandir($dir);
    if ($entries === false) {
        $out[] = $prefix . '└── [unreadable: permission denied]';
        return;
    }

    // Separate dirs and files, drop excluded + dot-current/parent.
    $dirs = [];
    $files = [];
    foreach ($entries as $e) {
        if ($e === '.' || $e === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $e;
        if (is_dir($path)) {
            if (in_array($e, $cfg['excludeDirs'], true)) {
                $out[] = $prefix . '├── ' . $e . '/  (skipped)';
                continue;
            }
            $dirs[] = $e;
        } else {
            if (in_array($e, $cfg['excludeFiles'], true)) continue;
            $files[] = $e;
        }
    }

    sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    $items = array_merge(
        array_map(fn($d) => ['name' => $d, 'dir' => true], $dirs),
        array_map(fn($f) => ['name' => $f, 'dir' => false], $files)
    );

    $count = count($items);
    foreach ($items as $i => $item) {
        $isLast = ($i === $count - 1);
        $connector = $isLast ? '└── ' : '├── ';
        $childPrefix = $prefix . ($isLast ? '    ' : '│   ');
        $path = $dir . DIRECTORY_SEPARATOR . $item['name'];

        if ($item['dir']) {
            $stats['dirs']++;
            $out[] = $prefix . $connector . $item['name'] . '/';
            scan($path, $childPrefix, $depth + 1, $out, $stats, $cfg);
        } else {
            $stats['files']++;
            $size = @filesize($path);
            $size = $size === false ? 0 : $size;
            $stats['bytes'] += $size;

            $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
            $ext = $ext === '' ? '(no ext)' : $ext;
            $stats['ext'][$ext] = ($stats['ext'][$ext] ?? 0) + 1;

            $out[] = $prefix . $connector . $item['name']
                   . '  (' . human_size($size) . ')';
        }
    }
}

function human_size($bytes)
{
    if ($bytes < 1024) return $bytes . ' B';
    $units = ['KB', 'MB', 'GB'];
    $i = -1;
    do { $bytes /= 1024; $i++; } while ($bytes >= 1024 && $i < count($units) - 1);
    return round($bytes, 1) . ' ' . $units[$i];
}

// ----------------------------- RUN -----------------------------------------

$cfg = [
    'excludeDirs'  => $EXCLUDE_DIRS,
    'excludeFiles' => $EXCLUDE_FILES,
    'maxDepth'     => $MAX_DEPTH,
];

$lines = [];
$lines[] = basename($ROOT) . '/';
scan($ROOT, '', 1, $lines, $stats, $cfg);

// Summary
$summary = [];
$summary[] = '';
$summary[] = str_repeat('=', 50);
$summary[] = 'SUMMARY';
$summary[] = str_repeat('=', 50);
$summary[] = 'Scanned root : ' . $ROOT;
$summary[] = 'Generated    : ' . date('Y-m-d H:i:s');
$summary[] = 'Directories  : ' . $stats['dirs'];
$summary[] = 'Files        : ' . $stats['files'];
$summary[] = 'Total size   : ' . human_size($stats['bytes']);
$summary[] = '';
$summary[] = 'File types (by extension):';

arsort($stats['ext']);
foreach ($stats['ext'] as $ext => $n) {
    $summary[] = sprintf('   %-12s %d', $ext, $n);
}

$report = implode("\n", array_merge($lines, $summary)) . "\n";

// Write to file (best effort).
$written = @file_put_contents($ROOT . '/structure.txt', $report);

// Output to screen.
if ($isCli) {
    echo $report;
    echo $written !== false
        ? "\n[Saved to structure.txt]\n"
        : "\n[Could not write structure.txt — check folder permissions]\n";
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8">';
    echo '<title>Project structure</title>';
    echo '<body style="background:#0f1117;color:#d4d7dd;font:14px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;padding:24px;">';
    echo '<pre style="white-space:pre;">' . htmlspecialchars($report, ENT_QUOTES) . '</pre>';
    echo $written !== false
        ? '<p style="color:#7fd1a0;">Saved to <b>structure.txt</b> — you can attach that file.</p>'
        : '<p style="color:#e88;">Could not write structure.txt — folder not writable. Just copy the text above.</p>';
    echo '<p style="color:#e8c07a;">Remember to delete structure.php and structure.txt from a public server when done.</p>';
    echo '</body>';
}