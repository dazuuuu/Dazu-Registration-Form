<?php
// app/v1/lib/DazuPdo.php
//
// One place that opens the database. The site speaks MySQL-flavoured SQL; when the configured driver is
// "pgsql" every statement is translated to PostgreSQL by SqlDialect before it is sent.
//
// config/database.php keys: driver ('mysql' | 'pgsql'), host, port (optional), dbname, username, password.

require_once __DIR__ . '/SqlDialect.php';

class DazuPdo extends PDO
{
    private SqlDialect $dialect;
    private array $uniqueCache = [];

    public static function driver(array $cfg): string
    {
        $d = strtolower((string) ($cfg['driver'] ?? ''));
        if ($d === 'postgres' || $d === 'postgresql') {
            $d = 'pgsql';
        }
        if ($d === 'mysql' || $d === 'pgsql') {
            return $d;
        }
        // no explicit driver: the Postgres default port or the "postgres" user means Postgres
        if ((int) ($cfg['port'] ?? 0) === 5432 || ($cfg['username'] ?? '') === 'postgres') {
            return 'pgsql';
        }
        return 'mysql';
    }

    /** Open a connection. $withDb = false connects to the server only (to create the database). */
    public static function open(array $cfg, bool $withDb = true, array $extra = []): PDO
    {
        // Dev only: point a run at a throw-away database (name, driver, port, user, password), e.g. a scratch MySQL on port 3307.
        foreach (['DAZU_DB_NAME' => 'dbname', 'DAZU_DB_DRIVER' => 'driver', 'DAZU_DB_PORT' => 'port', 'DAZU_DB_USER' => 'username', 'DAZU_DB_HOST' => 'host'] as $env => $key) {
            if (getenv($env) !== false && getenv($env) !== '') $cfg[$key] = getenv($env);
        }
        if (getenv('DAZU_DB_PASS') !== false) $cfg['password'] = (string) getenv('DAZU_DB_PASS');
        $driver = self::driver($cfg);
        $opts = $extra + [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
        $host = $cfg['host'] ?? 'localhost';
        $port = !empty($cfg['port']) ? ';port=' . (int) $cfg['port'] : '';
        if ($driver === 'pgsql') {
            // Emulated prepares keep MySQL-style "?" binds working where PostgreSQL cannot infer a parameter type.
            $opts[PDO::ATTR_EMULATE_PREPARES] = true;
            $db = ($withDb ? $cfg['dbname'] : 'postgres');
            $pdo = new DazuPdo("pgsql:host={$host}{$port};dbname={$db}", $cfg['username'] ?? '', $cfg['password'] ?? '', $opts);
            $pdo->exec("SET TIME ZONE 'Africa/Nairobi'");
            return $pdo;
        }
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $dsn = "mysql:host={$host}{$port};" . ($withDb ? "dbname={$cfg['dbname']};" : '') . "charset={$charset}";
        return new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', $opts);
    }

    /** Create the database when it does not exist (and the user may). Silent when the host forbids it. */
    public static function ensureDatabase(array $cfg): void
    {
        $name = (string) $cfg['dbname'];
        try {
            $srv = self::open($cfg, false, [PDO::ATTR_TIMEOUT => 8]);
            if (self::driver($cfg) === 'pgsql') {
                $st = $srv->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
                $st->execute([$name]);
                if (!$st->fetchColumn()) {
                    $srv->exec('CREATE DATABASE "' . str_replace('"', '', $name) . "\" ENCODING 'UTF8' TEMPLATE template0");
                }
            } else {
                $srv->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }
        } catch (PDOException $e) {
            // Shared hosts often forbid CREATE DATABASE; a database made in the control panel is used as-is.
        }
    }

    /** MySQL functions the site's queries use, created once in a PostgreSQL database (CREATE OR REPLACE: safe to repeat). */
    public static function installCompat(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return;
        }
        $fns = [
            'CREATE OR REPLACE FUNCTION locate(text, text) RETURNS integer AS $$ SELECT strpos($2, $1) $$ LANGUAGE sql IMMUTABLE',
            'CREATE OR REPLACE FUNCTION locate(text, text, integer) RETURNS integer AS $$ SELECT CASE WHEN strpos(substr($2, $3), $1) = 0 THEN 0 ELSE strpos(substr($2, $3), $1) + $3 - 1 END $$ LANGUAGE sql IMMUTABLE',
            'CREATE OR REPLACE FUNCTION lpad(bigint, integer, text) RETURNS text AS $$ SELECT lpad($1::text, $2, $3) $$ LANGUAGE sql IMMUTABLE',
            'CREATE OR REPLACE FUNCTION lpad(integer, integer, text) RETURNS text AS $$ SELECT lpad($1::text, $2, $3) $$ LANGUAGE sql IMMUTABLE',
            'CREATE OR REPLACE FUNCTION find_in_set(text, text) RETURNS integer AS $$ SELECT COALESCE(array_position(string_to_array($2, \',\'), $1), 0) $$ LANGUAGE sql IMMUTABLE',
            'CREATE OR REPLACE FUNCTION substring_index(text, text, integer) RETURNS text AS $$ SELECT CASE WHEN $3 >= 0 THEN array_to_string((string_to_array($1, $2))[1:$3], $2) ELSE array_to_string((string_to_array($1, $2))[(cardinality(string_to_array($1, $2)) + $3 + 1):], $2) END $$ LANGUAGE sql IMMUTABLE',
            'CREATE OR REPLACE FUNCTION unix_timestamp() RETURNS bigint AS $$ SELECT EXTRACT(EPOCH FROM now())::bigint $$ LANGUAGE sql STABLE',
            'CREATE OR REPLACE FUNCTION unix_timestamp(timestamp) RETURNS bigint AS $$ SELECT EXTRACT(EPOCH FROM $1)::bigint $$ LANGUAGE sql STABLE',
            'CREATE OR REPLACE FUNCTION from_unixtime(bigint) RETURNS timestamp AS $$ SELECT to_timestamp($1)::timestamp $$ LANGUAGE sql STABLE',
        ];
        // MySQL treats a comparison as a number: SUM(status = 'x') counts rows.
        $fns[] = 'CREATE OR REPLACE FUNCTION dazu_bool_sum(bigint, boolean) RETURNS bigint AS $$ SELECT COALESCE($1, 0) + CASE WHEN $2 THEN 1 ELSE 0 END $$ LANGUAGE sql IMMUTABLE';
        $aggregates = [
            ['sum(boolean)', 'CREATE AGGREGATE sum(boolean) (SFUNC = dazu_bool_sum, STYPE = bigint)'],
        ];
        // MySQL compares dates/timestamps with strings (e.g. created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')).
        $ops = [];
        foreach (['timestamp' => 'ts', 'date' => 'dt'] as $type => $short) {
            foreach (['=' => 'eq', '<>' => 'ne', '<' => 'lt', '<=' => 'le', '>' => 'gt', '>=' => 'ge'] as $op => $name) {
                $fns[] = "CREATE OR REPLACE FUNCTION dazu_{$short}_{$name}_text($type, text) RETURNS boolean AS \$\$ SELECT \$1 $op \$2::$type \$\$ LANGUAGE sql STABLE";
                $fns[] = "CREATE OR REPLACE FUNCTION dazu_text_{$name}_{$short}(text, $type) RETURNS boolean AS \$\$ SELECT \$1::$type $op \$2 \$\$ LANGUAGE sql STABLE";
                $ops[] = ["$op|$type|text", "CREATE OPERATOR $op (LEFTARG = $type, RIGHTARG = text, FUNCTION = dazu_{$short}_{$name}_text)"];
                $ops[] = ["$op|text|$type", "CREATE OPERATOR $op (LEFTARG = text, RIGHTARG = $type, FUNCTION = dazu_text_{$name}_{$short})"];
            }
        }
        foreach ($fns as $f) {
            $pdo->rawExec($f);
        }
        foreach (array_merge($aggregates, $ops) as [$key, $ddl]) {
            try {
                $pdo->rawExec($ddl);
            } catch (PDOException $e) {
                // already exists: fine
            }
        }
    }

    public function __construct(string $dsn, ?string $user = null, ?string $pass = null, ?array $options = null)
    {
        parent::__construct($dsn, $user, $pass, $options);
        $this->dialect = new SqlDialect(fn(string $t) => $this->uniqueIndexes($t));
    }

    public function isPostgres(): bool
    {
        return true;
    }

    /** Unique indexes (incl. primary key) of a table, for ON DUPLICATE KEY translation. */
    private function uniqueIndexes(string $table): array
    {
        if (isset($this->uniqueCache[$table])) {
            return $this->uniqueCache[$table];
        }
        $sql = "SELECT i.indisprimary AS pk, a.attname AS col, k.ord
                FROM pg_index i
                JOIN pg_class c ON c.oid = i.indrelid
                JOIN pg_namespace n ON n.oid = c.relnamespace AND n.nspname = current_schema()
                JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS k(attnum, ord) ON true
                JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = k.attnum
                WHERE c.relname = " . $this->quote($table) . " AND i.indisunique AND i.indpred IS NULL
                ORDER BY i.indexrelid, k.ord";
        $rows = parent::query($sql)->fetchAll(PDO::FETCH_ASSOC);
        // group the rows per index (ord restarts at 1 for each index)
        $idx = [];
        $cur = null;
        $lastOrd = 0;
        foreach ($rows as $r) {
            if ($cur === null || (int) $r['ord'] <= $lastOrd) {
                $idx[] = ['primary' => (bool) $r['pk'], 'cols' => []];
                $cur = count($idx) - 1;
            }
            $idx[$cur]['cols'][] = $r['col'];
            $lastOrd = (int) $r['ord'];
        }
        // a table that is not created yet must not be cached as "no unique keys"
        if ($idx) {
            $this->uniqueCache[$table] = $idx;
        }
        return $idx;
    }

    /** Run SQL as written (no MySQL-to-PostgreSQL translation). */
    public function rawExec(string $sql): int|false
    {
        return parent::exec($sql);
    }

    public function exec(string $statement): int|false
    {
        $n = 0;
        foreach ($this->dialect->translate($statement) as $s) {
            $r = parent::exec($s);
            $n += (int) $r;
        }
        $this->uniqueCache = [];
        return $n;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $parts = $this->dialect->translate($query);
        if (!$parts) {
            $parts = ['SELECT 1 WHERE false'];
        }
        $last = array_pop($parts);
        foreach ($parts as $s) {
            parent::exec($s);
        }
        return $fetchMode === null ? parent::query($last) : parent::query($last, $fetchMode, ...$fetchModeArgs);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $parts = $this->dialect->translate($query);
        if (!$parts) {
            $parts = ['SELECT 1 WHERE false'];
        }
        $last = array_pop($parts);
        foreach ($parts as $s) {
            parent::exec($s);
        }
        return parent::prepare($last, $options);
    }

    public function translate(string $sql): array
    {
        return $this->dialect->translate($sql);
    }
}
