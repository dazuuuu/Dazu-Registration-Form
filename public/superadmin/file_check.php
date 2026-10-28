<?php
// Superadmin: File check. Compares the files on this server with app/v1/file_manifest.json (made on the computer the code comes from)
// and lists every file that is missing or different (an older or a newer copy). A page that breaks after an upload is almost always
// a file that was not uploaded. Also shows the last PHP errors from the server's error_log files. Read only: it changes nothing.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
Middleware::requireSuperPortal();

$root = dirname(__DIR__, 2);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$manifest = is_file($root . '/app/v1/file_manifest.json') ? json_decode((string) file_get_contents($root . '/app/v1/file_manifest.json'), true) : null;
$missing = $different = [];
$checked = 0;
if ($manifest) {
    foreach ($manifest['files'] as $rel => $md5) {
        $abs = $root . '/' . $rel;
        $checked++;
        if (!is_file($abs)) $missing[] = $rel;
        elseif (md5_file($abs) !== $md5) $different[] = $rel;
    }
}
// The newest PHP error lines (cPanel writes an error_log file next to the script that failed).
$errors = [];
$logs = [];
foreach (['public', 'app'] as $top) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $top, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile() && $f->getFilename() === 'error_log') $logs[$f->getPathname()] = $f->getMTime();
}
if (($ini = ini_get('error_log')) && is_file($ini)) $logs[$ini] = filemtime($ini);
arsort($logs);
foreach (array_slice(array_keys($logs), 0, 5) as $log) {
    $fh = @fopen($log, 'r');
    if (!$fh) continue;
    fseek($fh, max(0, filesize($log) - 20000));
    $lines = array_filter(explode("\n", (string) stream_get_contents($fh)), fn($l) => preg_match('/PHP (Fatal|Parse|Warning)/', $l));
    fclose($fh);
    foreach (array_slice($lines, -6) as $l) $errors[] = [str_replace($root . '/', '', $log), str_replace($root . '/', '', $l)];
}
// Pages that crashed, recorded by the site itself (app/v1/bootstrap.php): newest first.
$fatal = [];
$fl = function_exists('storage_path') ? storage_path('logs/fatal.log') : '';
if ($fl && is_file($fl)) $fatal = array_reverse(array_slice(array_filter(explode("\n", (string) file_get_contents($fl))), -15));
$group = function (array $list): array {
    $g = [];
    foreach ($list as $rel) $g[dirname($rel)][] = basename($rel);
    ksort($g);
    return $g;
};

$page_title = 'File check';
$sa_subtitle = 'Which files on this server are missing or not the latest version.';
ob_start();
?>
<?php if ($fatal): ?><div class="alert alert-danger">A page crashed recently. See "Pages that crashed" below for the exact error.</div><?php endif; ?>
<?php if (!$manifest): ?>
  <div class="alert alert-warning">Upload <code>app/v1/file_manifest.json</code> (it comes with every update) and open this page again.</div>
<?php else: ?>
  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="sa-card" style="min-height:0"><div class="sa-k-label">Files checked</div><div class="sa-k-val" style="padding-left:0"><?php echo (int) $checked; ?></div><div class="sa-k-sub" style="padding-left:0">List made <?php echo $e($manifest['made_at']); ?> UTC</div></div></div>
    <div class="col-md-4"><div class="sa-card" style="min-height:0"><div class="sa-k-label">Missing</div><div class="sa-k-val" style="padding-left:0;color:<?php echo $missing ? '#e5484d' : '#16a34a'; ?>"><?php echo count($missing); ?></div></div></div>
    <div class="col-md-4"><div class="sa-card" style="min-height:0"><div class="sa-k-label">Different (old) copy</div><div class="sa-k-val" style="padding-left:0;color:<?php echo $different ? '#e5484d' : '#16a34a'; ?>"><?php echo count($different); ?></div></div></div>
  </div>
  <?php if (!$missing && !$different): ?><div class="alert alert-success">Every file matches. Nothing to upload.</div><?php endif; ?>
  <?php foreach (['Missing: upload these' => $missing, 'Different: upload these again' => $different] as $title => $list): if (!$list) continue; ?>
    <div class="card p-3 mb-3"><h6 class="fw-bold mb-2"><?php echo $e($title); ?></h6>
      <?php foreach ($group($list) as $dir => $names): ?>
        <div class="mb-2"><div class="small fw-semibold"><?php echo $e($dir); ?>/</div><div class="small text-muted"><?php echo $e(implode(', ', $names)); ?></div></div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<div class="card p-3 mb-3"><h6 class="fw-bold mb-2">Pages that crashed (newest first)</h6>
  <?php if (!$fatal): ?><div class="small text-muted">No page has crashed since this check was installed.</div><?php endif; ?>
  <?php foreach ($fatal as $l): ?><div class="small mb-2" style="word-break:break-word;font-family:ui-monospace,monospace"><?php echo $e($l); ?></div><?php endforeach; ?>
</div>
<div class="card p-3"><h6 class="fw-bold mb-2">Latest PHP errors on this server</h6>
  <?php if (!$errors): ?><div class="small text-muted">No PHP errors found in the error_log files.</div><?php endif; ?>
  <?php foreach ($errors as [$log, $line]): ?><div class="small mb-2" style="word-break:break-word"><span class="text-muted"><?php echo $e($log); ?>:</span><br><?php echo $e($line); ?></div><?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/superadmin/layout.php';
