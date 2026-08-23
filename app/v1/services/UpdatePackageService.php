<?php
// app/v1/services/UpdatePackageService.php — optional feature updates from databases/updates/

class UpdatePackageService
{
    private PDO $pdo;
    private string $updatesDir;

    private const ALREADY_EXISTS_CODES = [1050, 1060, 1061, 1068, 1022, 1826];
    private const MISSING_COLUMN_CODES = [1091, 1054];

    public function __construct(PDO $pdo, ?string $updatesDir = null)
    {
        $this->pdo = $pdo;
        $this->updatesDir = $updatesDir ?: dirname(__DIR__, 3) . '/databases/updates';
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS feature_updates (
                filename VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reverted_at TIMESTAMP NULL DEFAULT NULL
            ) ENGINE=InnoDB"
        );
    }

    /** @return array<string, array{applied_at:string,reverted_at:?string}> */
    public function appliedRecords(): array
    {
        $this->ensureTable();
        $rows = $this->pdo->query(
            'SELECT filename, applied_at, reverted_at FROM feature_updates'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $out[$row['filename']] = [
                'applied_at' => (string) $row['applied_at'],
                'reverted_at' => $row['reverted_at'] !== null ? (string) $row['reverted_at'] : null,
            ];
        }
        return $out;
    }

    public function updateFiles(): array
    {
        $files = glob(rtrim($this->updatesDir, '/') . '/*.sql') ?: [];
        $apply = [];
        foreach ($files as $path) {
            $name = basename($path);
            if (preg_match('/\.revert\.sql$/i', $name)) {
                continue;
            }
            $apply[] = $path;
        }
        sort($apply, SORT_STRING);
        return $apply;
    }

    public function revertPath(string $applyFilename): ?string
    {
        if (!preg_match('/^(.+)\.sql$/i', $applyFilename, $m)) {
            return null;
        }
        $path = rtrim($this->updatesDir, '/') . '/' . $m[1] . '.revert.sql';
        return is_file($path) ? $path : null;
    }

    public function pending(): array
    {
        $records = $this->appliedRecords();
        $pending = [];
        foreach ($this->updateFiles() as $path) {
            $filename = basename($path);
            $rec = $records[$filename] ?? null;
            if ($rec === null || $rec['reverted_at'] !== null) {
                $pending[] = [
                    'filename' => $filename,
                    'path' => $path,
                    'revert_path' => $this->revertPath($filename),
                ];
            }
        }
        return $pending;
    }

    /** @return list<array{filename:string,applied_at:string,revert_path:?string}> */
    public function appliedActive(): array
    {
        $records = $this->appliedRecords();
        $active = [];
        foreach ($this->updateFiles() as $path) {
            $filename = basename($path);
            $rec = $records[$filename] ?? null;
            if ($rec !== null && $rec['reverted_at'] === null) {
                $active[] = [
                    'filename' => $filename,
                    'applied_at' => $rec['applied_at'],
                    'revert_path' => $this->revertPath($filename),
                ];
            }
        }
        return $active;
    }

    public function pendingCount(): int
    {
        return count($this->pending());
    }

    public function applyPending(): array
    {
        $results = [];
        $ran = 0;

        foreach ($this->pending() as $update) {
            $filename = $update['filename'];
            try {
                $this->runSqlFile($update['path'], self::ALREADY_EXISTS_CODES);
                $this->recordApplied($filename);
                $results[] = ['filename' => $filename, 'status' => 'applied'];
                $ran++;
            } catch (Throwable $e) {
                $results[] = [
                    'filename' => $filename,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                break;
            }
        }

        return [
            'ran' => $ran,
            'results' => $results,
            'failed' => array_values(array_filter($results, static fn ($r) => $r['status'] === 'failed')),
        ];
    }

    public function revert(string $filename): array
    {
        $filename = basename($filename);
        $revertPath = $this->revertPath($filename);
        if ($revertPath === null) {
            return ['ok' => false, 'error' => 'No revert script found for this update.'];
        }

        $records = $this->appliedRecords();
        $rec = $records[$filename] ?? null;
        if ($rec === null || $rec['reverted_at'] !== null) {
            return ['ok' => false, 'error' => 'This update is not currently applied.'];
        }

        try {
            $this->runSqlFile($revertPath, self::MISSING_COLUMN_CODES);
            $stmt = $this->pdo->prepare(
                'UPDATE feature_updates SET reverted_at = CURRENT_TIMESTAMP WHERE filename = ?'
            );
            $stmt->execute([$filename]);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function recordApplied(string $filename): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(
            'INSERT INTO feature_updates (filename, applied_at, reverted_at)
             VALUES (?, CURRENT_TIMESTAMP, NULL)
             ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP, reverted_at = NULL'
        );
        $stmt->execute([$filename]);
    }

    private function runSqlFile(string $path, array $skippableCodes): void
    {
        foreach ($this->statementsFromFile($path) as $statement) {
            try {
                $this->pdo->exec($statement);
            } catch (PDOException $e) {
                $code = $e->errorInfo[1] ?? null;
                if (in_array($code, $skippableCodes, true)) {
                    continue;
                }
                throw $e;
            }
        }
    }

    private function statementsFromFile(string $path): array
    {
        require_once __DIR__ . '/MigrationService.php';
        $sql = MigrationService::stripSqlComments((string) file_get_contents($path));
        $lines = preg_split('/\r?\n/', $sql);
        $lines = array_filter($lines ?: [], static function ($line) {
            $trimmed = ltrim($line);
            return $trimmed !== ''
                && stripos($trimmed, 'CREATE DATABASE') !== 0
                && stripos($trimmed, 'USE ') !== 0;
        });
        return array_values(array_filter(array_map('trim', explode(';', implode("\n", $lines)))));
    }
}
