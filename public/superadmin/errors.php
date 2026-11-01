<?php
// Superadmin: the error log on its own page. Deliberately plain (no layout, no menus, almost no other files), so it still works when
// other pages are broken. Shows the crashes the site recorded (storage/logs/fatal.log) and the newest PHP errors from cPanel's error_log files.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
Middleware::requireSuperPortal();
header('Cache-Control: no-store');
$root = dirname(__DIR__, 2);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fatal = [];
$fl = storage_path('logs/fatal.log');
if (is_file($fl)) $fatal = array_reverse(array_slice(array_filter(explode("\n", (string) file_get_contents($fl))), -30));
$php = [];
$logs = [];
foreach (['public', 'app'] as $top) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $top, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile() && $f->getFilename() === 'error_log') $logs[$f->getPathname()] = $f->getMTime();
}
arsort($logs);
foreach (array_slice(array_keys($logs), 0, 5) as $log) {
    $fh = @fopen($log, 'r');
    if (!$fh) continue;
    fseek($fh, max(0, filesize($log) - 20000));
    foreach (array_slice(array_filter(explode("\n", (string) stream_get_contents($fh)), fn($l) => preg_match('/PHP (Fatal|Parse|Warning)/', $l)), -8) as $l) $php[] = [str_replace($root . '/', '', $log), str_replace($root . '/', '', $l)];
    fclose($fh);
}
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Error log</title>
<style>body{margin:0;background:#f4f5f9;font-family:system-ui,sans-serif;color:#1f2937}main{max-width:1000px;margin:0 auto;padding:24px 16px}
h1{font-size:20px}h2{font-size:15px;margin:24px 0 8px}.row{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;margin-bottom:8px;font:13px/1.5 ui-monospace,monospace;word-break:break-word}
.muted{color:#6b7280;font-size:13px}a{color:#15A24A}</style></head><body><main>
<h1>Error log</h1><p class="muted">Newest first. <a href="">Refresh</a> · <a href="<?php echo $e(url('superadmin/file_check.php')); ?>">File check</a> · <a href="<?php echo $e(url('superadmin/dashboard.php')); ?>">Dashboard</a></p>
<h2>Pages that crashed</h2>
<?php if (!$fatal): ?><p class="muted">None recorded.</p><?php endif; ?>
<?php foreach ($fatal as $l): ?><div class="row"><?php echo $e($l); ?></div><?php endforeach; ?>
<h2>PHP errors from the server's error_log files</h2>
<?php if (!$php): ?><p class="muted">None found.</p><?php endif; ?>
<?php foreach ($php as [$f, $l]): ?><div class="row"><span class="muted"><?php echo $e($f); ?></span><br><?php echo $e($l); ?></div><?php endforeach; ?>
</main></body></html>
