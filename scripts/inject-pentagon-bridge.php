<?php
/**
 * Inject pentagon-bridge.js into static HTML pages after UI sync.
 *
 * Usage: php scripts/inject-pentagon-bridge.php [public_dir]
 */
$publicDir = $argv[1] ?? dirname(__DIR__) . '/public';
$publicDir = rtrim(str_replace('\\', '/', $publicDir), '/');

if (!is_dir($publicDir)) {
    fwrite(STDERR, "Directory not found: {$publicDir}\n");
    exit(1);
}

require_once dirname(__DIR__) . '/app/v1/config/env.php';

$webRoot = dazu_env('APP_WEB_ROOT');
if ($webRoot === null || $webRoot === '') {
    $webRoot = '';
}
$webRoot = rtrim(str_replace('\\', '/', (string) $webRoot), '/');

$jsPath = ($webRoot !== '' ? $webRoot . '/' : '/') . 'js/pentagon-bridge.js';
$apiBase = ($webRoot !== '' ? $webRoot . '/' : '/') . 'api';
$bridgeTag = '<script src="' . htmlspecialchars($jsPath, ENT_QUOTES)
    new RecursiveDirectoryIterator($publicDir, FilesystemIterator::SKIP_DOTS)
);

$count = 0;
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') {
        continue;
    }

    $path = $file->getPathname();
    // Skip vendor/plugin HTML stubs
    if (str_contains($path, '/wp-content/plugins/') || str_contains($path, '/wp-includes/')) {
        continue;
    }

    $html = file_get_contents($path);
    if ($html === false) {
        continue;
    }

    $changed = false;

    if (!str_contains($html, 'pentagon-api-base')) {
        if (preg_match('/<head[^>]*>/i', $html)) {
            $html = preg_replace('/<head([^>]*)>/i', '<head$1>' . "\n" . $metaTag, $html, 1);
            $changed = true;
        }
    }

    if (!str_contains($html, 'pentagon-bridge.js')) {
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $bridgeTag . "\n</body>", $html);
            $changed = true;
        }
    }

    // Add dynamic package grid placeholder on packages listing if missing
    if (str_contains($path, '/packages/index.html') && !str_contains($html, 'id="packages-container"')) {
        $placeholder = '<div id="packages-container" style="max-width:1200px;margin:2rem auto;padding:0 1rem"></div>';
        $html = str_ireplace('</body>', $placeholder . "\n</body>", $html);
        $changed = true;
    }

    if ($changed) {
        file_put_contents($path, $html);
        $count++;
    }
}

echo "Updated {$count} HTML file(s) in {$publicDir}\n";
