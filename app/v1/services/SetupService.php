<?php
// app/v1/services/SetupService.php — first-run installer (DB, migrations, admin).

class SetupService
{
    public const LOCK_RELATIVE = 'storage/setup.lock';

    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?: require dirname(__DIR__) . '/config/database.php';
    }

    public function lockPath(): string
    {
        return dirname(__DIR__, 3) . '/' . self::LOCK_RELATIVE;
    }

    public function isComplete(): bool
    {
        return is_file($this->lockPath());
    }

    public function markComplete(): void
    {
        $dir = dirname($this->lockPath());
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $this->lockPath(),
            json_encode([
                'completed_at' => date('c'),
                'host' => $this->config['host'] ?? '',
                'dbname' => $this->config['dbname'] ?? '',
            ], JSON_PRETTY_PRINT)
        );
    }

    public function databaseName(): string
    {
        return (string) ($this->config['dbname'] ?? '');
    }

    public function host(): string
    {
        return (string) ($this->config['host'] ?? 'localhost');
    }

    public function connectServer(): PDO
    {
        $pdo = new PDO(
            "mysql:host={$this->config['host']};charset={$this->config['charset']}",
            $this->config['username'],
            $this->config['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    public function ensureDatabase(PDO $serverPdo): string
    {
        $dbName = $this->databaseName();
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            throw new RuntimeException('Invalid database name in configuration.');
        }
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $serverPdo->exec("USE `{$dbName}`");
        return $dbName;
    }

    public function connectDatabase(): PDO
    {
        $pdo = new PDO(
            "mysql:host={$this->config['host']};dbname={$this->config['dbname']};charset={$this->config['charset']}",
            $this->config['username'],
            $this->config['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    public function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute([':table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function adminCount(PDO $pdo): int
    {
        if (!$this->tableExists($pdo, 'users')) {
            return 0;
        }
        $sql = 'SELECT COUNT(*) FROM users WHERE role_id <= 2';
        if ($this->tableExists($pdo, 'roles')) {
            $sql = "SELECT COUNT(*) FROM users u
                    INNER JOIN roles r ON r.id = u.role_id
                    WHERE r.role_name IN ('superadmin', 'admin') OR u.role_id <= 2";
        }
        return (int) $pdo->query($sql)->fetchColumn();
    }

    public function createAdmin(PDO $pdo, string $username, string $email, string $password, string $firstName = '', string $lastName = ''): array
    {
        $username = trim($username);
        $email = trim($email);
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        if ($username === '' || $email === '' || $password === '') {
            return ['ok' => false, 'message' => 'Username, email, and password are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid email address.'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            return ['ok' => false, 'message' => 'Username must be 3–50 characters (letters, numbers, dot, underscore, or hyphen).'];
        }
        if (!$this->tableExists($pdo, 'users') || !$this->tableExists($pdo, 'roles')) {
            return ['ok' => false, 'message' => 'User tables are missing. Run migrations first.'];
        }

        $roleId = $this->superadminRoleId($pdo);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $existingByEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existingByEmail->execute([':email' => $email]);
        $emailId = $existingByEmail->fetchColumn();

        $existingByUsername = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $existingByUsername->execute([':username' => $username]);
        $usernameId = $existingByUsername->fetchColumn();

        if ($emailId && $usernameId && (int) $emailId !== (int) $usernameId) {
            return ['ok' => false, 'message' => 'That username and email belong to two different accounts.'];
        }

        $userId = $emailId ?: $usernameId;
        $action = $userId ? 'updated' : 'created';

        if ($userId) {
            $stmt = $pdo->prepare(
                'UPDATE users SET username = :username, email = :email, password_hash = :hash,
                 role_id = :role_id, is_active = 1, email_verified = 1 WHERE id = :id'
            );
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':hash' => $hash,
                ':role_id' => $roleId,
                ':id' => $userId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified)
                 VALUES (:username, :email, :hash, :role_id, 1, 1)'
            );
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':hash' => $hash,
                ':role_id' => $roleId,
            ]);
            $userId = (int) $pdo->lastInsertId();
        }

        if ($this->tableExists($pdo, 'user_profiles')) {
            $profile = $pdo->prepare(
                'INSERT INTO user_profiles (user_id, first_name, last_name)
                 VALUES (:user_id, :first_name, :last_name)
                 ON DUPLICATE KEY UPDATE
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name)'
            );
            $profile->execute([
                ':user_id' => $userId,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
            ]);
        }

        return [
            'ok' => true,
            'action' => $action,
            'user_id' => (int) $userId,
            'message' => $action === 'created'
                ? 'Administrator account created.'
                : 'Existing administrator account was updated.',
        ];
    }

    private function superadminRoleId(PDO $pdo): int
    {
        $stmt = $pdo->query("SELECT id FROM roles WHERE role_name = 'superadmin' LIMIT 1");
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
        $stmt = $pdo->query("SELECT id FROM roles WHERE role_name = 'admin' LIMIT 1");
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
        throw new RuntimeException('No admin role found. Migrations may have failed.');
    }
}
