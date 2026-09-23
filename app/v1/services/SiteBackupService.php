<?php
// app/v1/services/SiteBackupService.php
// Full-site export/import for moving this website to another host: the whole database plus everything in public/uploads
// and the private storage folder (mail key, SMS receipts), packed into one zip. The database login is never included: the new host keeps its own.
//
// database.sql format: one statement per line (values are escaped, so a row never spans lines), which lets the importer
// stream it without loading it into memory.

class SiteBackupService
{
    public const FORMAT = 'dazu-site-backup';
    public const VERSION = 1;
    /** Only these file types are ever written when a backup is restored: never scripts. */
    private const RESTORE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'mp4', 'webm', 'mov', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'html', 'key'];
    private const MEDIA_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'mp4', 'webm', 'mov', 'zip', 'pdf'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function importsDir(): string
    {
        return rtrim(STORAGE_PATH, '/') . '/imports';
    }

    public static function backupsDir(): string
    {
        return rtrim(STORAGE_PATH, '/') . '/backups';
    }

    /** Zips found in the server-side imports folder (for backups too big to upload through the browser). */
    public static function serverImports(): array
    {
        $out = [];
        foreach (glob(self::importsDir() . '/*.zip') ?: [] as $f) {
            $out[basename($f)] = filesize($f);
        }
        ksort($out);
        return $out;
    }

    private static function ensurePrivateDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ht = $dir . '/.htaccess';
        if (is_dir($dir) && !is_file($ht)) {
            @file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
    }

    /** Build the backup zip at $zipPath. Returns the manifest. */
    public function export(string $zipPath): array
    {
        @set_time_limit(0);
        $sqlFile = tempnam(sys_get_temp_dir(), 'dazu_sql_');
        $tables = $this->dumpDatabase($sqlFile);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($sqlFile);
            throw new RuntimeException('Could not create the backup file.');
        }
        $manifest = [
            'format'     => self::FORMAT,
            'version'    => self::VERSION,
            'created_at' => date('c'),
            'tables'     => $tables,
            'web_root'   => defined('WEB_ROOT') ? WEB_ROOT : '',
            'site_url'   => $this->siteUrl(),
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFile($sqlFile, 'database.sql');
        $count = $this->addTree($zip, public_path('uploads'), 'files/uploads');
        $count += $this->addTree($zip, STORAGE_PATH, 'files/storage', ['backups', 'imports']);
        $zip->close();
        @unlink($sqlFile);

        $manifest['files'] = $count;
        return $manifest;
    }

    private function addTree(ZipArchive $zip, string $dir, string $prefix, array $skipTop = []): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $n = 0;
        $base = rtrim(str_replace('\\', '/', realpath($dir)), '/');
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $rel = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($base)), '/');
            if ($skipTop && in_array(explode('/', $rel)[0], $skipTop, true)) {
                continue;
            }
            $entry = $prefix . '/' . $rel;
            $zip->addFile($file->getPathname(), $entry);
            if (in_array(strtolower($file->getExtension()), self::MEDIA_EXT, true)) {
                $zip->setCompressionName($entry, ZipArchive::CM_STORE);
            }
            $n++;
        }
        return $n;
    }

    public function siteUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return '';
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($https ? 'https://' : 'http://') . $host;
    }

    /** Base tables of a database (views are skipped). */
    public static function listTables(PDO $pdo): array
    {
        return $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Emit the SQL that recreates one table and its rows, one statement per call of $emit (values are escaped, so a
     * statement never spans lines). $scan, if given, is called with every string value. Returns the row count.
     */
    public static function streamTable(PDO $src, string $table, callable $emit, ?callable $scan = null): int
    {
        $q = '`' . str_replace('`', '``', $table) . '`';
        $create = $src->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM)[1];
        $emit("DROP TABLE IF EXISTS $q");
        $emit(preg_replace('/\s*\R\s*/', ' ', $create));

        $names = [];
        $binary = [];
        foreach ($src->query("SHOW COLUMNS FROM $q")->fetchAll(PDO::FETCH_ASSOC) as $i => $c) {
            $names[] = '`' . str_replace('`', '``', $c['Field']) . '`';
            $binary[$i] = (bool) preg_match('/blob|binary/i', $c['Type']);
        }
        $head = "INSERT INTO $q (" . implode(',', $names) . ') VALUES ';

        $st = $src->query("SELECT * FROM $q");
        $batch = [];
        $size = 0;
        $rows = 0;
        while (($row = $st->fetch(PDO::FETCH_NUM)) !== false) {
            $vals = [];
            foreach ($row as $i => $v) {
                if ($v === null) {
                    $vals[] = 'NULL';
                } elseif ($binary[$i] ?? false) {
                    $vals[] = $v === '' ? "''" : '0x' . bin2hex($v);
                } else {
                    $v = (string) $v;
                    if ($scan && $v !== '') {
                        $scan($v);
                    }
                    $vals[] = $src->quote($v);
                }
            }
            $line = '(' . implode(',', $vals) . ')';
            $batch[] = $line;
            $size += strlen($line);
            $rows++;
            if ($size > 500000 || count($batch) >= 200) {
                $emit($head . implode(',', $batch));
                $batch = [];
                $size = 0;
            }
        }
        $st->closeCursor();
        if ($batch) {
            $emit($head . implode(',', $batch));
        }
        return $rows;
    }

    /** Write every base table (structure and rows) to $file. Returns the table count. */
    public function dumpDatabase(string $file): int
    {
        $out = fopen($file, 'wb');
        if (!$out) {
            throw new RuntimeException('Could not write the database dump.');
        }
        $this->pdo->exec("SET time_zone = '+00:00'");
        fwrite($out, "-- Dazu site backup\nSET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        $tables = self::listTables($this->pdo);
        foreach ($tables as $t) {
            self::streamTable($this->pdo, $t, static function (string $sql) use ($out) {
                fwrite($out, $sql . ";\n");
            });
        }
        fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($out);
        return count($tables);
    }

    /** Session settings every import/copy connection needs before running dumped SQL. */
    public function prepareTarget(): void
    {
        foreach (["SET NAMES utf8mb4", "SET time_zone = '+00:00'", 'SET FOREIGN_KEY_CHECKS = 0', 'SET UNIQUE_CHECKS = 0', "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'"] as $sql) {
            $this->pdo->exec($sql);
        }
    }

    /** Copy one table from another database into this one (structure and rows), fixing links with $rewrite. Returns rows copied. */
    public function copyTableFrom(PDO $src, string $table, ?callable $rewrite = null, ?callable $scan = null): int
    {
        $src->exec("SET time_zone = '+00:00'");
        $this->prepareTarget();
        $pdo = $this->pdo;
        return self::streamTable($src, $table, static function (string $sql) use ($pdo, $rewrite) {
            if ($rewrite && str_starts_with($sql, 'INSERT INTO')) {
                $sql = $rewrite($sql);
            }
            $pdo->exec($sql);
        }, $scan);
    }

    /**
     * Restore a backup zip over this site. Returns ['ok', 'error'?, 'tables', 'files', 'safety'].
     * A database-only safety copy of the current data is written first.
     */
    public function import(string $zipPath): array
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'error' => 'That file is not a valid zip.'];
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (!is_array($manifest) || ($manifest['format'] ?? '') !== self::FORMAT || $zip->locateName('database.sql') === false) {
            $zip->close();
            return ['ok' => false, 'error' => 'This is not a site backup made by this system.'];
        }
        if ((int) ($manifest['version'] ?? 0) > self::VERSION) {
            $zip->close();
            return ['ok' => false, 'error' => 'This backup was made by a newer version of the system.'];
        }

        // Safety copy of what is here now.
        self::ensurePrivateDir(self::backupsDir());
        $safety = self::backupsDir() . '/pre-import-' . date('Ymd-His') . '.sql';
        try {
            $this->dumpDatabase($safety);
        } catch (Throwable $e) {
            $zip->close();
            return ['ok' => false, 'error' => 'Could not take the safety copy first, so nothing was changed.'];
        }

        // Database.
        $sqlTmp = tempnam(sys_get_temp_dir(), 'dazu_imp_');
        $in = $zip->getStream('database.sql');
        $tmpOut = fopen($sqlTmp, 'wb');
        stream_copy_to_stream($in, $tmpOut);
        fclose($in);
        fclose($tmpOut);

        $rewrite = $this->rewriter($manifest);
        $tables = 0;
        try {
            $fh = fopen($sqlTmp, 'rb');
            while (($line = fgets($fh)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '' || str_starts_with($line, '--')) {
                    continue;
                }
                if (str_starts_with($line, 'CREATE TABLE')) {
                    $tables++;
                } elseif ($rewrite && str_starts_with($line, 'INSERT INTO')) {
                    $line = $rewrite($line);
                }
                $this->pdo->exec($line);
            }
            fclose($fh);
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable $e) {
            @unlink($sqlTmp);
            $zip->close();
            error_log('[SiteBackupService::import] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'The database import stopped part-way: ' . $e->getMessage() . ' A copy of the previous data was saved before the import started.', 'safety' => $safety];
        }
        @unlink($sqlTmp);

        // Files.
        $files = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (substr($name, -1) === '/' || !preg_match('#^files/(uploads|storage)/(.+)$#', $name, $m)) {
                continue;
            }
            $rel = $m[2];
            if (str_contains($rel, '..') || str_contains($rel, '\\') || str_contains($rel, "\0") || $rel[0] === '/') {
                continue;
            }
            if (!in_array(strtolower(pathinfo($rel, PATHINFO_EXTENSION)), self::RESTORE_EXT, true) || $rel[0] === '.' || str_contains($rel, '/.')) {
                continue;
            }
            $dest = $m[1] === 'uploads' ? public_path('uploads/' . $rel) : rtrim(STORAGE_PATH, '/') . '/' . $rel;
            if (!is_dir(dirname($dest))) {
                @mkdir(dirname($dest), 0775, true);
            }
            $src = $zip->getStream($name);
            $dst = $src ? @fopen($dest, 'wb') : false;
            if ($src && $dst) {
                stream_copy_to_stream($src, $dst);
                $files++;
            }
            if ($src) fclose($src);
            if ($dst) fclose($dst);
        }
        $zip->close();

        return ['ok' => true, 'tables' => $tables, 'files' => $files, 'safety' => $safety];
    }

    /** Fix stored links when the new host serves the site from a different address or folder. */
    public function rewriter(array $manifest): ?callable
    {
        $oldRoot = rtrim((string) ($manifest['web_root'] ?? ''), '/');
        $newRoot = defined('WEB_ROOT') ? rtrim(WEB_ROOT, '/') : '';
        $oldUrl = rtrim((string) ($manifest['site_url'] ?? ''), '/');
        $newUrl = rtrim($this->siteUrl(), '/');
        $pairs = [];
        if ($oldUrl !== '' && $newUrl !== '' && $oldUrl !== $newUrl) {
            $pairs[$oldUrl] = $newUrl;
            $pairs[str_replace('/', '\\\\/', $oldUrl)] = str_replace('/', '\\\\/', $newUrl);
        }
        $rootChange = $oldRoot !== $newRoot;
        if (!$pairs && !$rootChange) {
            return null;
        }
        return static function (string $line) use ($pairs, $rootChange, $oldRoot, $newRoot): string {
            if ($pairs) {
                $line = strtr($line, $pairs);
            }
            if ($rootChange) {
                // Stored paths look like <root>/uploads/…; swap only the root part, and only at the start of a path.
                $from = preg_quote($oldRoot, '#');
                $line = preg_replace('#(?<![A-Za-z0-9_./-])' . $from . '/uploads/#', str_replace('\\', '\\\\', $newRoot) . '/uploads/', $line);
            }
            return $line;
        };
    }
}
