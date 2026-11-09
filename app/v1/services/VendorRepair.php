<?php
// app/v1/services/VendorRepair.php
// Superadmin → Vendor packages: check the Composer packages in app/v1/vendor and download a broken or missing one straight
// onto the server (like `composer install` after hosting), so the vendor folder never has to be uploaded by hand.
//  - The package list (name, version, download link) comes from vendor/composer/installed.json, else the copy below
//    (taken from composer.lock), so the exact same versions are installed.
//  - Every file is checked against app/v1/file_manifest.json (the checksum of each vendor file on the computer the code comes
//    from). A download is used only if every file the manifest lists for that package matches; otherwise nothing is changed.
//  - The old folder is swapped out only after the new one is complete and checked.
// vendor/composer (the autoloader Composer writes: 12 small files) cannot be downloaded; if it is missing it is reported.

if (!defined('VR_FALLBACK')) define('VR_FALLBACK', [
        ['name' => 'dompdf/dompdf', 'version' => 'v3.1.6', 'url' => 'https://api.github.com/repos/dompdf/dompdf/zipball/6d4b4eb8500f7a786da8868ba463a71b725a4005'],
        ['name' => 'dompdf/php-font-lib', 'version' => '1.0.2', 'url' => 'https://api.github.com/repos/dompdf/php-font-lib/zipball/a6e9a688a2a80016ac080b97be73d3e10c444c9a'],
        ['name' => 'dompdf/php-svg-lib', 'version' => '1.0.2', 'url' => 'https://api.github.com/repos/dompdf/php-svg-lib/zipball/8259ffb930817e72b1ff1caef5d226501f3dfeb1'],
        ['name' => 'masterminds/html5', 'version' => '2.11.0', 'url' => 'https://api.github.com/repos/Masterminds/html5-php/zipball/a1e7a2f88ee13635d86fc61cfbdf2306a76ddfc7'],
        ['name' => 'phpmailer/phpmailer', 'version' => 'v6.12.0', 'url' => 'https://api.github.com/repos/PHPMailer/PHPMailer/zipball/d1ac35d784bf9f5e61b424901d5a014967f15b12'],
        ['name' => 'sabberworm/php-css-parser', 'version' => 'v9.4.0', 'url' => 'https://api.github.com/repos/MyIntervals/PHP-CSS-Parser/zipball/fd3bf9fb173e0df649bc4e3e0d088a1b2417c08f'],
        ['name' => 'thecodingmachine/safe', 'version' => 'v3.4.0', 'url' => 'https://api.github.com/repos/thecodingmachine/safe/zipball/705683a25bacf0d4860c7dea4d7947bfd09eea19'],
]);

if (!function_exists('vr_packages')) {

    function vr_vendor(): string
    {
        return rtrim(defined('VENDOR_PATH') ? VENDOR_PATH : dirname(__DIR__) . '/vendor', '/');
    }

    /** [name => ['name','version','url']] from installed.json, else the built-in copy. */
    function vr_packages(): array
    {
        $out = [];
        $j = @json_decode((string) @file_get_contents(vr_vendor() . '/composer/installed.json'), true);
        foreach ((is_array($j) ? ($j['packages'] ?? $j) : []) as $p) {
            if (!empty($p['name']) && !empty($p['dist']['url']) && preg_match('#^https://(api\.github\.com|codeload\.github\.com|github\.com)/#', $p['dist']['url'])) {
                $out[$p['name']] = ['name' => $p['name'], 'version' => (string) ($p['version'] ?? ''), 'url' => $p['dist']['url']];
            }
        }
        foreach (VR_FALLBACK as $p) $out[$p['name']] = $out[$p['name']] ?? $p;
        ksort($out);
        return $out;
    }

    /** The manifest's vendor files: [path inside vendor => md5]. */
    function vr_manifest(): array
    {
        static $m = null;
        if ($m !== null) return $m;
        $m = [];
        $j = @json_decode((string) @file_get_contents(dirname(__DIR__) . '/file_manifest.json'), true);
        foreach (($j['files'] ?? []) as $rel => $md5) {
            if (str_starts_with($rel, 'app/v1/vendor/')) $m[substr($rel, 14)] = $md5;
        }
        return $m;
    }

    /** The manifest files of one package: [path inside the package => md5]. */
    function vr_expected(string $name): array
    {
        $out = [];
        foreach (vr_manifest() as $rel => $md5) if (str_starts_with($rel, $name . '/')) $out[substr($rel, strlen($name) + 1)] = $md5;
        return $out;
    }

    /** Compare a folder with the expected files. ['missing' => [...], 'different' => [...]] */
    function vr_compare(string $dir, array $expected): array
    {
        $missing = $different = [];
        foreach ($expected as $rel => $md5) {
            $f = $dir . '/' . $rel;
            if (!is_file($f)) $missing[] = $rel;
            elseif (md5_file($f) !== $md5) $different[] = $rel;
        }
        return ['missing' => $missing, 'different' => $different];
    }

    /** Every package with its state, plus the Composer autoloader folder. */
    function vr_check(): array
    {
        $rows = [];
        foreach (vr_packages() as $name => $p) {
            $exp = vr_expected($name);
            $dir = vr_vendor() . '/' . $name;
            $c = is_dir($dir) ? vr_compare($dir, $exp) : ['missing' => array_keys($exp), 'different' => []];
            $rows[$name] = $p + $c + ['present' => is_dir($dir), 'files' => count($exp), 'ok' => is_dir($dir) && !$c['missing'] && !$c['different']];
        }
        $auto = [];
        foreach (vr_manifest() as $rel => $md5) if (str_starts_with($rel, 'composer/') || $rel === 'autoload.php') $auto[$rel] = $md5;
        $autoload = vr_compare(vr_vendor(), $auto);
        return ['packages' => $rows, 'autoloader' => $autoload + ['ok' => !$autoload['missing']], 'manifest' => (bool) vr_manifest()];
    }

    function vr_download(string $url, string $to): ?string
    {
        $fh = @fopen($to, 'wb');
        if (!$fh) return 'Could not write a temporary file.';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 240,
                CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_USERAGENT => 'Dazu-Registration-System',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS]);
            $ok = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            if (PHP_VERSION_ID < 80000) curl_close($ch);   // no effect (and deprecated) from PHP 8
            fclose($fh);
            if (!$ok || $code !== 200) return 'Download failed (' . ($err ?: 'HTTP ' . $code) . '). The server may block outside connections; try again later.';
            return null;
        }
        fclose($fh);
        $ctx = stream_context_create(['http' => ['header' => "User-Agent: Dazu-Registration-System\r\n", 'timeout' => 240, 'follow_location' => 1]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) return 'Download failed: this server allows neither cURL nor URL downloads.';
        return file_put_contents($to, $data) ? null : 'Could not write the download.';
    }

    function vr_rmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }

    /**
     * Download one package and put it in place. Checked against the manifest before anything is replaced.
     * @return array{ok:bool, message:string}
     */
    function vr_install(string $name): array
    {
        $pk = vr_packages()[$name] ?? null;
        if (!$pk) return ['ok' => false, 'message' => 'Unknown package.'];
        if (!class_exists('ZipArchive')) return ['ok' => false, 'message' => 'The PHP zip extension is off on this server. Turn it on in the hosting panel (PHP extensions → zip).'];
        @set_time_limit(300);
        $vendor = vr_vendor();
        $tmp = $vendor . '/.repair-' . bin2hex(random_bytes(4));
        if (!@mkdir($tmp, 0755, true)) return ['ok' => false, 'message' => 'Could not create a folder in vendor/. Check its permissions.'];
        try {
            $zip = $tmp . '/package.zip';
            if ($err = vr_download($pk['url'], $zip)) return ['ok' => false, 'message' => $name . ': ' . $err];
            $z = new ZipArchive();
            if ($z->open($zip) !== true) return ['ok' => false, 'message' => $name . ': the download is not a valid zip.'];
            for ($i = 0; $i < $z->numFiles; $i++) {   // nothing may escape the folder
                $n = (string) $z->getNameIndex($i);
                if (str_contains($n, '..') || str_starts_with($n, '/')) { $z->close(); return ['ok' => false, 'message' => $name . ': unsafe file names in the download.']; }
            }
            $z->extractTo($tmp . '/x');
            $z->close();
            $tops = array_values(array_filter(glob($tmp . '/x/*') ?: [], 'is_dir'));
            if (count($tops) !== 1) return ['ok' => false, 'message' => $name . ': unexpected download layout.'];
            $new = $tops[0];
            $meta = @json_decode((string) @file_get_contents($new . '/composer.json'), true);
            if (strtolower((string) ($meta['name'] ?? '')) !== strtolower($name)) return ['ok' => false, 'message' => $name . ': the download is a different package.'];
            $exp = vr_expected($name);
            if ($exp) {
                $c = vr_compare($new, $exp);
                if ($c['missing'] || $c['different']) {
                    return ['ok' => false, 'message' => $name . ': the download does not match the files on the main computer (' . count($c['missing']) . ' missing, ' . count($c['different']) . ' different). Nothing was changed.'];
                }
            }
            // Swap: the old folder out, the checked one in.
            $dest = $vendor . '/' . $name;
            @mkdir(dirname($dest), 0755, true);
            $old = $dest . '.old-' . bin2hex(random_bytes(3));
            if (is_dir($dest) && !@rename($dest, $old)) return ['ok' => false, 'message' => $name . ': could not move the old folder aside.'];
            if (!@rename($new, $dest)) {
                if (is_dir($old)) @rename($old, $dest);
                return ['ok' => false, 'message' => $name . ': could not put the new folder in place.'];
            }
            vr_rmdir($old);
            error_log('Vendor repair: installed ' . $name . ' ' . $pk['version'] . ' by user ' . (int) ($_SESSION['user_id'] ?? 0));
            return ['ok' => true, 'message' => $name . ' ' . $pk['version'] . ' downloaded and checked (' . count($exp) . ' files).'];
        } finally {
            vr_rmdir($tmp);
        }
    }
}
