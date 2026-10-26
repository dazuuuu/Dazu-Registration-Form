<?php
// app/v1/services/MigrationService.php — shared SQL migration runner.

class MigrationService
{
    private PDO $pdo;
    private string $migrationsDir;

    private const ALREADY_EXISTS_CODES = [1050, 1060, 1061, 1068, 1022, 1826];

    public function __construct(PDO $pdo, ?string $migrationsDir = null)
    {
        $this->pdo = $pdo;
        $this->migrationsDir = $migrationsDir ?: (defined('DATABASES_PATH') ? DATABASES_PATH : dirname(__DIR__) . '/databases') . '/required_migrations';
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                filename VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );
    }

    public function appliedFilenames(): array
    {
        $this->ensureTable();
        $applied = $this->pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        return array_flip($applied ?: []);
    }

    public function appliedRows(): array
    {
        $this->ensureTable();
        $rows = $this->pdo
            ->query('SELECT filename, applied_at FROM schema_migrations ORDER BY filename ASC')
            ->fetchAll(PDO::FETCH_ASSOC);

        $applied = [];
        foreach ($rows ?: [] as $row) {
            $applied[(string) $row['filename']] = (string) ($row['applied_at'] ?? '');
        }
        return $applied;
    }

    public function migrationFiles(): array
    {
        $files = glob(rtrim($this->migrationsDir, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    public function pending(): array
    {
        $applied = $this->appliedFilenames();
        $pending = [];
        foreach ($this->migrationFiles() as $path) {
            $filename = basename($path);
            if (!isset($applied[$filename])) {
                $pending[] = [
                    'filename' => $filename,
                    'path' => $path,
                ];
            }
        }
        return $pending;
    }

    public function pendingCount(): int
    {
        return count($this->pending());
    }

    public function statusRows(): array
    {
        $applied = $this->appliedRows();
        $rows = [];
        foreach ($this->migrationFiles() as $path) {
            $filename = basename($path);
            $rows[] = [
                'filename' => $filename,
                'path' => $path,
                'status' => isset($applied[$filename]) ? 'applied' : 'pending',
                'applied_at' => $applied[$filename] ?? null,
                'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int) filemtime($path)) : null,
            ];
        }
        return $rows;
    }

    /**
     * Versions (from databases/releases.php) with their state, oldest first: version, title, date, notes, files,
     * installed (all its files applied), installed_at. SQL files no release lists are grouped as a patch after the latest.
     */
    public function releases(): array
    {
        $listPath = dirname(rtrim($this->migrationsDir, '/')) . '/releases.php';
        $list = is_file($listPath) ? (array) require $listPath : [];
        $applied = $this->appliedRows();
        $onDisk = array_map('basename', $this->migrationFiles());
        $listed = [];
        foreach ($list as $r) foreach ((array) ($r['files'] ?? []) as $f) $listed[$f] = true;
        $loose = array_values(array_filter($onDisk, static fn ($f) => !isset($listed[$f])));
        if ($loose) {
            $last = $list ? (string) end($list)['version'] : '1.0';
            $parts = explode('.', $last);
            $next = count($parts) >= 3 ? implode('.', array_slice($parts, 0, -1)) . '.' . ((int) end($parts) + 1) : $last . '.1';
            $list[] = ['version' => $next, 'title' => 'Maintenance update', 'date' => null,
                       'notes' => ['Fixes and improvements'], 'files' => $loose];
        }
        $out = [];
        foreach ($list as $r) {
            $files = array_values(array_filter((array) $r['files'], static fn ($f) => in_array($f, $onDisk, true)));
            $done = array_filter($files, static fn ($f) => isset($applied[$f]));
            $out[] = [
                'version' => (string) $r['version'], 'title' => (string) ($r['title'] ?? ''), 'date' => $r['date'] ?? null,
                'notes' => (array) ($r['notes'] ?? []), 'files' => $files,
                'installed' => count($done) === count($files),
                'installed_at' => $done ? max(array_map(static fn ($f) => $applied[$f], $done)) : null,
            ];
        }
        return $out;
    }

    /** Releases still to install, oldest first. */
    public function pendingReleases(): array
    {
        return array_values(array_filter($this->releases(), static fn ($r) => !$r['installed']));
    }

    /** Version this site is on: the newest release installed with every release before it. */
    public function currentVersion(): ?string
    {
        $current = null;
        foreach ($this->releases() as $r) {
            if (!$r['installed']) break;
            $current = $r['version'];
        }
        return $current;
    }

    public function applyPending(?callable $onProgress = null): array
    {
        $results = [];
        $ran = 0;

        foreach ($this->pending() as $migration) {
            $filename = $migration['filename'];
            $skipped = [];
            $onProgress && $onProgress("Applying {$filename} ... ");

            try {
                foreach ($this->statementsFromFile($migration['path']) as $statement) {
                    try {
                        $this->pdo->exec($statement);
                    } catch (PDOException $e) {
                        $code = $e->errorInfo[1] ?? null;
                        if (in_array($code, self::ALREADY_EXISTS_CODES, true)) {
                            $skipped[] = $e->errorInfo[2] ?? $e->getMessage();
                            continue;
                        }
                        throw $e;
                    }
                }

                $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
                $stmt->execute([$filename]);

                $results[] = [
                    'filename' => $filename,
                    'status' => 'applied',
                    'skipped' => $skipped,
                ];
                $ran++;
                $onProgress && $onProgress($skipped ? "OK (with skips)\n" : "OK\n");
                foreach ($skipped as $message) {
                    $onProgress && $onProgress("  (skipped, already applied: {$message})\n");
                }
            } catch (Throwable $e) {
                $results[] = [
                    'filename' => $filename,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'skipped' => $skipped,
                ];
                $onProgress && $onProgress("FAILED\n  {$e->getMessage()}\n");
                break;
            }
        }

        return [
            'ran' => $ran,
            'results' => $results,
            'failed' => array_values(array_filter($results, static fn ($r) => $r['status'] === 'failed')),
        ];
    }

    private function statementsFromFile(string $path): array
    {
        $sql = self::stripSqlComments((string) file_get_contents($path));
        $lines = preg_split('/\r?\n/', $sql);
        $lines = array_filter($lines ?: [], static function ($line) {
            $trimmed = ltrim($line);
            return $trimmed !== ''
                && stripos($trimmed, 'CREATE DATABASE') !== 0
                && stripos($trimmed, 'USE ') !== 0;
        });
        return array_values(array_filter(array_map('trim', explode(';', implode("\n", $lines)))));
    }

    public static function stripSqlComments(string $sql): string
    {
        $out = '';
        $inString = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($inString) {
                $out .= $ch;
                if ($ch === "'" && ($sql[$i + 1] ?? '') === "'") {
                    $out .= $sql[++$i];
                } elseif ($ch === "'") {
                    $inString = false;
                }
                continue;
            }
            if ($ch === "'") {
                $inString = true;
                $out .= $ch;
                continue;
            }
            if ($ch === '-' && ($sql[$i + 1] ?? '') === '-') {
                $nl = strpos($sql, "\n", $i);
                $i = ($nl === false) ? $len : $nl - 1;
                continue;
            }
            $out .= $ch;
        }
        return $out;
    }
}
