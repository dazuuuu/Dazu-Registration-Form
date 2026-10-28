<?php
// Writes app/v1/file_manifest.json: the fingerprint (md5) of every code and icon file, so Superadmin → File check can list the files
// on the server that are missing or older than this copy. Run after every change, before uploading:  php app/v1/tools/make_file_manifest.php
// Never listed: passwords and server settings, uploads, private storage, caches.
if (PHP_SAPI !== 'cli') exit("Run from the command line.\n");
$root = dirname(__DIR__, 3);
$skip = '#^(app/v1/config/database\.php|app/v1/storage/|public/uploads/|app/v1/file_manifest\.json|\.git/|node_modules/|.*\.DS_Store$|.*/error_log$|\.env)#';
$ext = '/\.(php|js|css|sql|html|htaccess|json|svg|ico|png|webp|jpg|ttf|woff2?)$/i';
$files = [];
foreach (['app', 'public', 'redirect'] as $top) {
    if (!is_dir("$root/$top")) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$top", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        if (!$f->isFile() || preg_match($skip, $rel) || !preg_match($ext, $f->getFilename())) continue;
        $files[$rel] = md5_file($f->getPathname());
    }
}
ksort($files);
file_put_contents("$root/app/v1/file_manifest.json", json_encode(['made_at' => gmdate('Y-m-d H:i:s'), 'files' => $files], JSON_UNESCAPED_SLASHES));
echo count($files) . " files listed in app/v1/file_manifest.json\n";
