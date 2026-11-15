<?php
// app/v1/lib/SqlDialect.php
//
// Translates the site's MySQL-flavoured SQL (queries and migrations) to PostgreSQL.
// The code base is written for MySQL; DazuPdo runs every statement through translate() when the
// configured driver is pgsql, so the rest of the site does not need to know which database it talks to.
//
// translate() returns a list of statements, because one MySQL statement can need several in
// PostgreSQL (e.g. CREATE TABLE with inline KEY definitions, or ON UPDATE CURRENT_TIMESTAMP).

final class SqlDialect
{
    /** @var callable|null fn(string $table): array<int,array{primary:bool,cols:string[]}> — unique indexes of a table */
    private $uniqueLookup;

    public function __construct(?callable $uniqueLookup = null)
    {
        $this->uniqueLookup = $uniqueLookup;
    }

    /** @return string[] */
    public function translate(string $sql): array
    {
        $sql = trim($sql);
        $sql = rtrim($sql, ';');
        if ($sql === '') {
            return [];
        }
        $head = strtoupper(strtok(ltrim($sql), " \t\r\n("));

        // Statements that only make sense on MySQL.
        if (preg_match('/^(SET\s+(NAMES|FOREIGN_KEY_CHECKS|SESSION|SQL_MODE|TIME_ZONE|@)|USE\s|DEALLOCATE\s|PREPARE\s|EXECUTE\s|SET\s+@)/i', $sql)) {
            return [];
        }
        if ($head === 'SHOW') {
            return [$this->show($sql)];
        }

        $sql = $this->identifiers($sql);

        switch ($head) {
            case 'CREATE':
                if (preg_match('/^CREATE\s+(UNIQUE\s+)?INDEX\b/i', $sql)) {
                    return [$this->createIndex($sql)];
                }
                if (preg_match('/^CREATE\s+TABLE\b/i', $sql)) {
                    return $this->createTable($sql);
                }
                return [$this->functions($sql)];
            case 'ALTER':
                return $this->alterTable($sql);
            case 'RENAME':
                if (preg_match('/^RENAME\s+TABLE\s+(\S+)\s+TO\s+(\S+)/i', $sql, $m)) {
                    return ['ALTER TABLE ' . $m[1] . ' RENAME TO ' . $m[2]];
                }
                return [$sql];
            case 'INSERT':
                return [$this->insert($sql)];
            case 'UPDATE':
                return [$this->functions($this->updateJoin($sql))];
            case 'DELETE':
                return [$this->functions($this->deleteJoin($sql))];
            case 'DROP':
                if (preg_match('/^DROP\s+INDEX\s+(\S+)\s+ON\s+\S+/i', $sql, $m)) {
                    return ['DROP INDEX IF EXISTS ' . $m[1]];
                }
                return [$sql];
            default:
                if ($head === 'SELECT' && preg_match('/\b(MAX|MIN|COUNT|SUM|AVG)\s*\(/i', $sql)) {
                    $sql = preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', $sql);   // MySQL allows FOR UPDATE with an aggregate, PostgreSQL does not
                }
                return [$this->functions($sql)];
        }
    }

    /* ---------------------------------------------------------------- helpers */

    /** Apply $fn to the parts of $sql outside single-quoted string literals. */
    private function outside(string $sql, callable $fn): string
    {
        $parts = preg_split("/('(?:[^'\\\\]|\\\\.|'')*')/s", $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $p) {
            if ($i % 2 === 0) {
                $parts[$i] = $fn($p);
            }
        }
        return implode('', $parts);
    }

    private function identifiers(string $sql): string
    {
        return $this->outside($sql, static fn($s) => str_replace('`', '"', $s));
    }

    /** Index of the matching ")" for the "(" at $open, ignoring strings. */
    private function matchParen(string $s, int $open): int
    {
        $depth = 0;
        $n = strlen($s);
        for ($i = $open; $i < $n; $i++) {
            $c = $s[$i];
            if ($c === "'") {
                for ($i++; $i < $n; $i++) {
                    if ($s[$i] === '\\') { $i++; continue; }
                    if ($s[$i] === "'") {
                        if (($s[$i + 1] ?? '') === "'") { $i++; continue; }
                        break;
                    }
                }
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return -1;
    }

    /** Split on top-level commas. */
    private function splitTop(string $s): array
    {
        $out = [];
        $depth = 0;
        $cur = '';
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            $c = $s[$i];
            if ($c === "'") {
                $j = $i;
                for ($i++; $i < $n; $i++) {
                    if ($s[$i] === '\\') { $i++; continue; }
                    if ($s[$i] === "'") {
                        if (($s[$i + 1] ?? '') === "'") { $i++; continue; }
                        break;
                    }
                }
                $cur .= substr($s, $j, $i - $j + 1);
                continue;
            }
            if ($c === '(') $depth++;
            if ($c === ')') $depth--;
            if ($c === ',' && $depth === 0) {
                $out[] = trim($cur);
                $cur = '';
                continue;
            }
            $cur .= $c;
        }
        if (trim($cur) !== '') {
            $out[] = trim($cur);
        }
        return $out;
    }

    /* ---------------------------------------------------------------- SHOW */

    private function show(string $sql): string
    {
        if (preg_match("/^SHOW\s+(?:FULL\s+)?TABLES(?:\s+LIKE\s+('[^']*'))?/i", $sql, $m)) {
            return "SELECT table_name AS \"Tables_in_db\" FROM information_schema.tables WHERE table_schema = current_schema()"
                . (!empty($m[1]) ? ' AND table_name LIKE ' . $m[1] : '');
        }
        if (preg_match("/^SHOW\s+(?:FULL\s+)?(?:COLUMNS|FIELDS)\s+FROM\s+[`\"]?(\w+)[`\"]?(?:\s+LIKE\s+('[^']*'))?/i", $sql, $m)) {
            return "SELECT column_name AS \"Field\", data_type AS \"Type\", is_nullable AS \"Null\", column_default AS \"Default\""
                . " FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = '" . $m[1] . "'"
                . (!empty($m[2]) ? ' AND column_name LIKE ' . $m[2] : '') . ' ORDER BY ordinal_position';
        }
        if (preg_match("/^SHOW\s+(?:INDEX|INDEXES|KEYS)\s+FROM\s+[`\"]?(\w+)[`\"]?(?:\s+WHERE\s+Key_name\s*=\s*('[^']*'))?/i", $sql, $m)) {
            return "SELECT indexname AS \"Key_name\" FROM pg_indexes WHERE schemaname = current_schema() AND tablename = '" . $m[1] . "'"
                . (!empty($m[2]) ? ' AND indexname = ' . $m[2] : '');
        }
        if (preg_match('/^SHOW\s+DATABASES/i', $sql)) {
            return 'SELECT datname AS "Database" FROM pg_database';
        }
        return 'SELECT 1 WHERE false';
    }

    /* ---------------------------------------------------------------- types / column definitions */

    /**
     * Convert one column definition ("name TYPE ... ") to PostgreSQL.
     * @param string[] $touch collects columns that had ON UPDATE CURRENT_TIMESTAMP
     */
    private function column(string $def, array &$touch): string
    {
        $def = preg_replace('/\s+AFTER\s+"?\w+"?\s*$/i', '', $def);
        $def = preg_replace('/\s+FIRST\s*$/i', '', $def);
        $def = preg_replace("/\s+COMMENT\s+'(?:[^']|'')*'/i", '', $def);
        $def = preg_replace('/\s+(?:CHARACTER\s+SET|CHARSET)\s+\w+/i', '', $def);
        $def = preg_replace('/\s+COLLATE\s+\w+/i', '', $def);

        if (preg_match('/^("?\w+"?)\s/', $def, $nm) && preg_match('/\sON\s+UPDATE\s+CURRENT_TIMESTAMP(?:\(\d*\))?/i', $def)) {
            $touch[] = trim($nm[1], '"');
        }
        $def = preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP(?:\(\d*\))?/i', '', $def);

        $auto = (bool) preg_match('/\sAUTO_INCREMENT\b/i', $def);
        $def = preg_replace('/\s+AUTO_INCREMENT(?:\s*=\s*\d+)?/i', '', $def);

        // type conversion: the first word after the column name
        $def = preg_replace_callback('/^("?\w+"?)\s+(\w+)(\s*\([^)]*\))?(\s+UNSIGNED)?(\s+ZEROFILL)?/i', function ($m) use ($auto) {
            $name = $m[1];
            $t = strtoupper($m[2]);
            $args = $m[3] ?? '';
            switch ($t) {
                case 'BOOLEAN': case 'BOOL': case 'TINYINT': case 'SMALLINT': $t = $auto ? 'SMALLSERIAL' : 'SMALLINT'; $args = ''; break;
                case 'MEDIUMINT': case 'INT': case 'INTEGER': $t = $auto ? 'SERIAL' : 'INTEGER'; $args = ''; break;
                case 'BIGINT': $t = $auto ? 'BIGSERIAL' : 'BIGINT'; $args = ''; break;
                case 'DATETIME': $t = 'TIMESTAMP'; $args = ''; break;
                case 'TIMESTAMP': $args = ''; break;
                case 'LONGTEXT': case 'MEDIUMTEXT': case 'TINYTEXT': case 'JSON': $t = 'TEXT'; $args = ''; break;
                case 'LONGBLOB': case 'MEDIUMBLOB': case 'BLOB': case 'TINYBLOB': case 'VARBINARY': case 'BINARY': $t = 'BYTEA'; $args = ''; break;
                case 'DOUBLE': $t = 'DOUBLE PRECISION'; $args = ''; break;
                case 'FLOAT': $args = ''; break;
                case 'YEAR': $t = 'SMALLINT'; break;
                case 'ENUM': case 'SET': $t = 'VARCHAR(100)'; $args = ''; break;
                case 'CHAR': case 'VARCHAR': case 'DECIMAL': case 'NUMERIC': break;
            }
            return $name . ' ' . $t . $args;
        }, $def, 1);

        // BOOLEAN is TINYINT(1) in MySQL: keep it numeric so "is_active = 1" and 0/1 inserts keep working
        $def = preg_replace('/DEFAULT\s+TRUE(?![A-Za-z0-9_])/i', 'DEFAULT 1', $def);
        $def = preg_replace('/DEFAULT\s+FALSE(?![A-Za-z0-9_])/i', 'DEFAULT 0', $def);
        // MySQL zero dates and CURRENT_TIMESTAMP(0)
        $def = preg_replace("/DEFAULT\s+'0000-00-00(?: 00:00:00)?'/i", 'DEFAULT NULL', $def);
        $def = preg_replace('/CURRENT_TIMESTAMP\(\d*\)/i', 'CURRENT_TIMESTAMP', $def);
        // Booleans stay SMALLINT, so DEFAULT 1/0 is fine. ENUM defaults are plain strings.
        return trim($def);
    }

    private function indexCols(string $cols): string
    {
        // strip prefix lengths: col(191) and ASC/DESC keeps
        return preg_replace('/(\w+"?)\s*\(\d+\)/', '$1', $cols);
    }

    /* ---------------------------------------------------------------- CREATE TABLE */

    private function createTable(string $sql): array
    {
        if (!preg_match('/^(CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?)("?\w+"?)\s*\(/is', $sql, $m, PREG_OFFSET_CAPTURE)) {
            return [$sql];
        }
        $prefix = $m[1][0];
        $table = $m[2][0];
        $tname = trim($table, '"');
        $open = strpos($sql, '(', $m[2][1]);
        $close = $this->matchParen($sql, $open);
        if ($close < 0) {
            return [$sql];
        }
        $defs = $this->splitTop(substr($sql, $open + 1, $close - $open - 1));
        $body = [];
        $extra = [];
        $touch = [];
        foreach ($defs as $d) {
            if (preg_match('/^(?:FULLTEXT|SPATIAL)\b/i', $d)) {
                continue;
            }
            if (preg_match('/^UNIQUE\s+(?:KEY|INDEX)\s+(?:"?\w+"?\s*)?\((.*)\)$/is', $d, $u)) {
                $body[] = 'UNIQUE (' . $this->indexCols($u[1]) . ')';
                continue;
            }
            if (preg_match('/^UNIQUE\s*\((.*)\)$/is', $d, $u)) {
                $body[] = 'UNIQUE (' . $this->indexCols($u[1]) . ')';
                continue;
            }
            if (preg_match('/^(?:KEY|INDEX)\s+("?\w+"?)\s*\((.*)\)$/is', $d, $k)) {
                $extra[] = 'CREATE INDEX IF NOT EXISTS "' . $tname . '_' . trim($k[1], '"') . '" ON ' . $table . ' (' . $this->indexCols($k[2]) . ')';
                continue;
            }
            if (preg_match('/^(?:PRIMARY\s+KEY|CONSTRAINT|FOREIGN\s+KEY|CHECK)\b/i', $d)) {
                $body[] = $this->indexCols($d);
                continue;
            }
            $body[] = $this->column($d, $touch);
        }
        $out = [$prefix . $table . " (\n  " . implode(",\n  ", $body) . "\n)"];
        foreach ($extra as $e) {
            $out[] = $e;
        }
        foreach (array_unique($touch) as $col) {
            array_push($out, ...$this->touchTrigger($tname, $col));
        }
        return $out;
    }

    private function touchTrigger(string $table, string $col): array
    {
        return [
            self::TOUCH_FUNCTION,
            'DROP TRIGGER IF EXISTS "trg_' . $table . '_' . $col . '" ON "' . $table . '"',
            'CREATE TRIGGER "trg_' . $table . '_' . $col . '" BEFORE UPDATE ON "' . $table . '" FOR EACH ROW EXECUTE FUNCTION dazu_touch_column(\'' . $col . '\')',
        ];
    }

    public const TOUCH_FUNCTION =
        'CREATE OR REPLACE FUNCTION dazu_touch_column() RETURNS trigger AS $$ BEGIN '
        . 'NEW := jsonb_populate_record(NEW, jsonb_build_object(TG_ARGV[0], now())); RETURN NEW; END; $$ LANGUAGE plpgsql';

    /* ---------------------------------------------------------------- CREATE INDEX / ALTER */

    private function createIndex(string $sql): string
    {
        $sql = preg_replace('/^(CREATE\s+(?:UNIQUE\s+)?INDEX)\s+(?!IF\s+NOT\s+EXISTS)/i', '$1 IF NOT EXISTS ', $sql);
        return $this->indexCols($sql);
    }

    private function alterTable(string $sql): array
    {
        if (!preg_match('/^ALTER\s+TABLE\s+("?\w+"?)\s+(.*)$/is', $sql, $m)) {
            return [$this->functions($sql)];
        }
        $table = $m[1];
        $tname = trim($table, '"');
        $actions = $this->splitTop($m[2]);
        $alter = [];
        $extra = [];
        $touch = [];
        foreach ($actions as $a) {
            if (preg_match('/^(?:ENGINE|DEFAULT\s+CHARSET|CONVERT\s+TO|AUTO_INCREMENT|COLLATE|CHARSET)\b/i', $a)) {
                continue;
            }
            if (preg_match('/^ADD\s+(UNIQUE\s+)?(?:KEY|INDEX)\s+("?\w+"?)\s*\((.*)\)$/is', $a, $k)) {
                $extra[] = 'CREATE ' . (trim($k[1]) !== '' ? 'UNIQUE ' : '') . 'INDEX IF NOT EXISTS "' . $tname . '_' . trim($k[2], '"') . '" ON ' . $table . ' (' . $this->indexCols($k[3]) . ')';
                continue;
            }
            if (preg_match('/^ADD\s+UNIQUE\s*(?:"?\w+"?\s*)?\((.*)\)$/is', $a, $k)) {
                $alter[] = 'ADD UNIQUE (' . $this->indexCols($k[1]) . ')';
                continue;
            }
            if (preg_match('/^ADD\s+(?:CONSTRAINT|FOREIGN\s+KEY|PRIMARY\s+KEY)\b/i', $a)) {
                $alter[] = $this->indexCols($a);
                continue;
            }
            if (preg_match('/^ADD\s+(?:COLUMN\s+)?(.*)$/is', $a, $c)) {
                $alter[] = 'ADD COLUMN IF NOT EXISTS ' . $this->column($c[1], $touch);
                continue;
            }
            if (preg_match('/^(?:MODIFY|ALTER)\s+(?:COLUMN\s+)?(.*)$/is', $a, $c) && !preg_match('/^ALTER\s+(?:COLUMN\s+)?"?\w+"?\s+(?:SET|DROP)\b/i', $a)) {
                $col = $this->column($c[1], $touch);
                if (preg_match('/^("?\w+"?)\s+(.*)$/s', $col, $p)) {
                    $name = $p[1];
                    $rest = $p[2];
                    $type = $rest;
                    $default = null;
                    $notNull = null;
                    if (preg_match('/\sDEFAULT\s+(.+?)(?=\s+(?:NOT\s+NULL|NULL)\b|$)/is', ' ' . $rest, $d)) {
                        $default = trim($d[1]);
                    }
                    if (preg_match('/\bNOT\s+NULL\b/i', $rest)) $notNull = true;
                    elseif (preg_match('/\bNULL\b/i', $rest)) $notNull = false;
                    $type = trim(preg_replace('/\s+(?:NOT\s+NULL|NULL|DEFAULT\s+.+)$/is', '', $rest));
                    $type = preg_replace('/\s+(?:NOT\s+NULL|NULL)\b.*$/is', '', $type);
                    $type = preg_replace('/^(SMALL|BIG)?SERIAL$/i', '$1INT', $type);
                    $type = preg_replace('/^SERIAL$/i', 'INTEGER', $type);
                    $alter[] = 'ALTER COLUMN ' . $name . ' TYPE ' . $type . ' USING ' . $name . '::' . preg_replace('/\(.*$/', '', $type);
                    if ($default !== null) $alter[] = 'ALTER COLUMN ' . $name . ' SET DEFAULT ' . $default;
                    if ($notNull === true) $alter[] = 'ALTER COLUMN ' . $name . ' SET NOT NULL';
                    if ($notNull === false) $alter[] = 'ALTER COLUMN ' . $name . ' DROP NOT NULL';
                }
                continue;
            }
            if (preg_match('/^CHANGE\s+(?:COLUMN\s+)?("?\w+"?)\s+("?\w+"?)\s+(.*)$/is', $a, $c)) {
                if (trim($c[1], '"') !== trim($c[2], '"')) {
                    $alter[] = 'RENAME COLUMN ' . $c[1] . ' TO ' . $c[2];
                }
                continue;
            }
            if (preg_match('/^DROP\s+(?:COLUMN\s+)?("?\w+"?)$/i', $a, $c) && !preg_match('/^DROP\s+(?:INDEX|KEY|PRIMARY|FOREIGN)/i', $a)) {
                $alter[] = 'DROP COLUMN IF EXISTS ' . $c[1];
                continue;
            }
            if (preg_match('/^DROP\s+(?:INDEX|KEY)\s+("?\w+"?)/i', $a, $c)) {
                $extra[] = 'DROP INDEX IF EXISTS "' . $tname . '_' . trim($c[1], '"') . '"';
                continue;
            }
            if (preg_match('/^DROP\s+FOREIGN\s+KEY\s+("?\w+"?)/i', $a, $c)) {
                $alter[] = 'DROP CONSTRAINT IF EXISTS ' . $c[1];
                continue;
            }
            $alter[] = $a;
        }
        $out = [];
        if ($alter) {
            $out[] = 'ALTER TABLE ' . $table . ' ' . implode(', ', $alter);
        }
        foreach ($extra as $e) {
            $out[] = $e;
        }
        foreach (array_unique($touch) as $col) {
            array_push($out, ...$this->touchTrigger($tname, $col));
        }
        return $out;
    }

    /* ---------------------------------------------------------------- INSERT ... ON DUPLICATE KEY / IGNORE */

    private function insert(string $sql): string
    {
        $ignore = false;
        if (preg_match('/^INSERT\s+IGNORE\b/i', $sql)) {
            $ignore = true;
            $sql = preg_replace('/^INSERT\s+IGNORE\b/i', 'INSERT', $sql);
        }
        $dup = null;
        $pos = $this->findOutside($sql, '/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i');
        if ($pos !== null) {
            $dup = substr($sql, $pos[0] + $pos[1]);
            $sql = rtrim(substr($sql, 0, $pos[0]));
        }
        $sql = $this->functions($sql);

        $table = null;
        $cols = [];
        if (preg_match('/^INSERT\s+INTO\s+("?\w+"?)\s*\(([^)]*)\)/is', $sql, $m)) {
            $table = trim($m[1], '"');
            $cols = array_map(static fn($c) => trim(trim($c), '"'), explode(',', $m[2]));
        } elseif (preg_match('/^INSERT\s+INTO\s+("?\w+"?)/is', $sql, $m)) {
            $table = trim($m[1], '"');
        }

        if ($dup !== null) {
            $target = $this->conflictTarget($table, $cols);
            if ($target === null) {
                return $sql . ' ON CONFLICT DO NOTHING';
            }
            $assign = $this->outside($dup, function ($s) use ($table) {
                $s = preg_replace('/\bVALUES\s*\(\s*"?(\w+)"?\s*\)/i', 'EXCLUDED."$1"', $s);
                return $s;
            });
            $assign = $this->functions($assign);
            // "col = col + 1" is ambiguous in PostgreSQL: qualify the right-hand side with the table.
            $assign = preg_replace_callback('/("?\w+"?)\s*=\s*("?\w+"?)(\s*[+\-*\/]|\s*,|\s*$)/', function ($m) use ($table) {
                return $m[1] === $m[2] ? $m[1] . ' = "' . $table . '".' . $m[2] . $m[3] : $m[0];
            }, $assign);
            return $sql . ' ON CONFLICT (' . implode(', ', array_map(static fn($c) => '"' . $c . '"', $target)) . ') DO UPDATE SET ' . trim($assign);
        }
        if ($ignore) {
            return $sql . ' ON CONFLICT DO NOTHING';
        }
        return $sql;
    }

    private function conflictTarget(?string $table, array $cols): ?array
    {
        if (!$table || !$cols || !$this->uniqueLookup) {
            return null;
        }
        $best = null;
        foreach (($this->uniqueLookup)($table) as $idx) {
            if (array_diff($idx['cols'], $cols)) {
                continue;
            }
            if ($best === null || (!$idx['primary'] && $best['primary'])) {
                $best = $idx;
            }
        }
        return $best ? $best['cols'] : null;
    }

    /** @return array{int,int}|null [offset, length] of the first regex match outside string literals */
    private function findOutside(string $sql, string $re): ?array
    {
        $found = null;
        $parts = preg_split("/('(?:[^'\\\\]|\\\\.|'')*')/s", $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
        $off = 0;
        foreach ($parts as $i => $p) {
            if ($i % 2 === 0 && $found === null && preg_match($re, $p, $m, PREG_OFFSET_CAPTURE)) {
                $found = [$off + $m[0][1], strlen($m[0][0])];
            }
            $off += strlen($p);
        }
        return $found;
    }

    /* ---------------------------------------------------------------- multi-table UPDATE / DELETE */

    /** Offset of the first top-level (outside parentheses and strings) keyword match, or null. */
    private function findTop(string $s, string $kwRegex): ?int
    {
        $depth = 0;
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            $c = $s[$i];
            if ($c === "'") {
                for ($i++; $i < $n; $i++) {
                    if ($s[$i] === '\\') { $i++; continue; }
                    if ($s[$i] === "'") {
                        if (($s[$i + 1] ?? '') === "'") { $i++; continue; }
                        break;
                    }
                }
                continue;
            }
            if ($c === '(') { $depth++; continue; }
            if ($c === ')') { $depth--; continue; }
            if ($depth === 0 && ($i === 0 || !preg_match('/\w/', $s[$i - 1])) && preg_match('/\G' . $kwRegex . '/i', $s, $m, 0, $i)) {
                return $i;
            }
        }
        return null;
    }

    /** "b ON cond JOIN c ON cond2" -> [["b", "c"], ["(cond)", "(cond2)"]]. Handles (subquery) alias joins. */
    private function joinParts(string $joins): array
    {
        $tables = [];
        $conds = [];
        $chunks = [];
        $rest = $joins;
        while (($p = $this->findTop($rest, '(?:(?:INNER|LEFT(?:\s+OUTER)?|CROSS)\s+)?JOIN\b')) !== null) {
            $chunks[] = substr($rest, 0, $p);
            $rest = preg_replace('/^(?:(?:INNER|LEFT(?:\s+OUTER)?|CROSS)\s+)?JOIN\b/i', '', substr($rest, $p));
        }
        $chunks[] = $rest;
        foreach ($chunks as $ch) {
            $ch = trim($ch);
            if ($ch === '') {
                continue;
            }
            if ($ch[0] === '(') {
                $close = $this->matchParen($ch, 0);
                $tbl = substr($ch, 0, $close + 1);
                $after = trim(substr($ch, $close + 1));
            } elseif (preg_match('/^("?\w+"?)(.*)$/s', $ch, $m)) {
                $tbl = $m[1];
                $after = trim($m[2]);
            } else {
                continue;
            }
            if (preg_match('/^(?:AS\s+)?(?!ON\b)("?\w+"?)\s+ON\s+(.*)$/is', $after, $m)) {
                $tables[] = $tbl . ' ' . $m[1];
                $conds[] = '(' . trim($m[2]) . ')';
            } elseif (preg_match('/^ON\s+(.*)$/is', $after, $m)) {
                $tables[] = $tbl;
                $conds[] = '(' . trim($m[1]) . ')';
            }
        }
        return [$tables, $conds];
    }

    private function updateJoin(string $sql): string
    {
        $kw = '(?!(?:INNER|LEFT|CROSS|JOIN|SET)\b)';
        if (!preg_match('/^UPDATE\s+("?\w+"?)(?:\s+(?:AS\s+)?' . $kw . '("?\w+"?))?\s+((?:(?:INNER|LEFT(?:\s+OUTER)?|CROSS)\s+)?JOIN\b.*)$/is', $sql, $m)) {
            return $sql;
        }
        $rest = $m[3];
        $setPos = $this->findTop($rest, 'SET\b');
        if ($setPos === null) {
            return $sql;
        }
        [$tables, $conds] = $this->joinParts(substr($rest, 0, $setPos));
        $tail = substr($rest, $setPos + 3);
        $where = '';
        $wp = $this->findTop($tail, 'WHERE\b');
        if ($wp !== null) {
            $where = trim(substr($tail, $wp + 5));
            $tail = substr($tail, 0, $wp);
        }
        $assign = [];
        foreach ($this->splitTop($tail) as $a) {
            $assign[] = preg_replace('/^("?\w+"?\.)?("?\w+"?)\s*=/', '$2 =', trim($a), 1);
        }
        if ($where !== '') {
            $conds[] = '(' . $where . ')';
        }
        return 'UPDATE ' . $m[1] . (!empty($m[2]) ? ' ' . $m[2] : '') . ' SET ' . implode(', ', $assign)
            . ' FROM ' . implode(', ', $tables) . ' WHERE ' . implode(' AND ', $conds);
    }

    private function deleteJoin(string $sql): string
    {
        $kw = '(?!(?:INNER|LEFT|CROSS|JOIN|WHERE)\b)';
        if (!preg_match('/^DELETE\s+("?\w+"?)\s+FROM\s+("?\w+"?)(?:\s+(?:AS\s+)?' . $kw . '("?\w+"?))?\s+((?:(?:INNER|LEFT(?:\s+OUTER)?|CROSS)\s+)?JOIN\b.*)$/is', $sql, $m)) {
            return $sql;
        }
        $rest = $m[4];
        $where = '';
        $wp = $this->findTop($rest, 'WHERE\b');
        if ($wp !== null) {
            $where = trim(substr($rest, $wp + 5));
            $rest = substr($rest, 0, $wp);
        }
        [$tables, $conds] = $this->joinParts($rest);
        if ($where !== '') {
            $conds[] = '(' . $where . ')';
        }
        return 'DELETE FROM ' . $m[2] . (!empty($m[3]) ? ' ' . $m[3] : '') . ' USING ' . implode(', ', $tables) . ' WHERE ' . implode(' AND ', $conds);
    }

    /* ---------------------------------------------------------------- functions and operators in queries */

    public function functions(string $sql): string
    {
        $sql = $this->outside($sql, function ($s) {
            $s = preg_replace('/\s+FROM\s+DUAL(?![A-Za-z0-9_])/i', '', $s);
            $s = preg_replace('/\bJSON_OBJECT\s*\(/i', 'json_build_object(', $s);
            $s = preg_replace('/\bJSON_ARRAY\s*\(/i', 'json_build_array(', $s);
            $s = preg_replace('/\bJSON_ARRAYAGG\s*\(/i', 'json_agg(', $s);
            $s = preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $s);
            $s = preg_replace('/\bCURDATE\s*\(\s*\)/i', 'CURRENT_DATE', $s);
            $s = preg_replace('/\bRAND\s*\(\s*\)/i', 'random()', $s);
            $s = preg_replace('/\bDATABASE\s*\(\s*\)/i', 'current_schema()', $s);
            $s = preg_replace('/\bNOW\s*\(\s*\)/i', 'NOW()', $s);
            $s = preg_replace('/\bUTC_TIMESTAMP\s*\(\s*\)/i', "(NOW() AT TIME ZONE 'UTC')", $s);
            $s = preg_replace('/\bLAST_INSERT_ID\s*\(\s*\)/i', 'lastval()', $s);
            $s = preg_replace('/\b(\w+(?:\.\w+)?)\s+(NOT\s+)?REGEXP\s+/i', '$1 $2~* ', $s);
            $s = preg_replace('/\b(\w+(?:\.\w+)?)\s+(NOT\s+)?RLIKE\s+/i', '$1 $2~* ', $s);
            $s = preg_replace('/\bLIKE\b/i', 'ILIKE', $s);
            $s = preg_replace('/\b(YEAR|MONTH|DAY|HOUR|MINUTE|SECOND|WEEK|QUARTER)\s*\(([^()]*(?:\([^()]*\)[^()]*)*)\)/i', 'EXTRACT($1 FROM $2)::int', $s);
            $s = preg_replace('/\bCAST\s*\((.+?)\s+AS\s+(?:UNSIGNED|SIGNED)(?:\s+INTEGER)?\s*\)/is', 'CAST($1 AS INTEGER)', $s);
            $s = preg_replace('/\bCAST\s*\((.+?)\s+AS\s+CHAR(?:\(\d+\))?\s*\)/is', 'CAST($1 AS TEXT)', $s);
            $s = preg_replace('/\bLIMIT\s+(\d+|\?|:\w+)\s*,\s*(\d+|\?|:\w+)/i', 'LIMIT $2 OFFSET $1', $s);
            // INTERVAL n UNIT -> INTERVAL 'n unit'; INTERVAL ? UNIT -> (? * INTERVAL '1 unit')
            $s = preg_replace('/\bINTERVAL\s+(\d+)\s+(MICROSECOND|SECOND|MINUTE|HOUR|DAY|WEEK|MONTH|YEAR)S?\b/i', "INTERVAL '$1 $2'", $s);
            $s = preg_replace('/\bINTERVAL\s+(\?|:\w+|\([^()]*\))\s+(SECOND|MINUTE|HOUR|DAY|WEEK|MONTH|YEAR)S?\b/i', "($1 * INTERVAL '1 $2')", $s);
            // unquoted camelCase aliases would be lower-cased by PostgreSQL
            $s = preg_replace_callback('/\bAS\s+([A-Za-z_][A-Za-z0-9_]*)\b/', static function ($m) {
                return (preg_match('/[a-z]/', $m[1]) && preg_match('/[A-Z]/', $m[1])) ? 'AS "' . $m[1] . '"' : $m[0];
            }, $s);
            return $s;
        });
        $sql = $this->callRewrite($sql, 'IF', fn(array $a) => count($a) === 3 ? 'CASE WHEN ' . $a[0] . ' THEN ' . $a[1] . ' ELSE ' . $a[2] . ' END' : null);
        $sql = $this->callRewrite($sql, 'FIELD', fn(array $a) => count($a) >= 2 ? 'COALESCE(array_position(ARRAY[' . implode(', ', array_slice($a, 1)) . ']::text[], CAST(' . $a[0] . ' AS TEXT)), 0)' : null);
        $sql = $this->callRewrite($sql, 'DATE_ADD', fn(array $a) => count($a) === 2 ? '(' . $a[0] . ' + ' . $a[1] . ')' : null);
        $sql = $this->callRewrite($sql, 'DATE_SUB', fn(array $a) => count($a) === 2 ? '(' . $a[0] . ' - ' . $a[1] . ')' : null);
        $sql = $this->callRewrite($sql, 'DATEDIFF', fn(array $a) => count($a) === 2 ? '(CAST(' . $a[0] . ' AS DATE) - CAST(' . $a[1] . ' AS DATE))' : null);
        $sql = $this->callRewrite($sql, 'DATE_FORMAT', fn(array $a) => count($a) === 2 ? 'to_char(' . $a[0] . ', ' . $this->dateFormat($a[1]) . ')' : null);
        $sql = $this->callRewrite($sql, 'TIMESTAMPDIFF', function (array $a) {
            if (count($a) !== 3) return null;
            $div = ['SECOND' => 1, 'MINUTE' => 60, 'HOUR' => 3600, 'DAY' => 86400, 'WEEK' => 604800];
            $u = strtoupper(trim($a[0]));
            if (!isset($div[$u])) return null;
            return 'FLOOR(EXTRACT(EPOCH FROM (' . $a[2] . ' - ' . $a[1] . ')) / ' . $div[$u] . ')::bigint';
        });
        $sql = $this->callRewrite($sql, 'DATE', fn(array $a) => count($a) === 1 ? 'CAST(' . $a[0] . ' AS DATE)' : null);
        $sql = $this->groupConcat($sql);
        return $sql;
    }

    private function dateFormat(string $fmt): string
    {
        $map = ['%Y' => 'YYYY', '%y' => 'YY', '%m' => 'MM', '%c' => 'FMMM', '%d' => 'DD', '%e' => 'FMDD', '%H' => 'HH24', '%k' => 'FMHH24',
                '%h' => 'HH12', '%I' => 'HH12', '%l' => 'FMHH12', '%i' => 'MI', '%s' => 'SS', '%S' => 'SS', '%p' => 'AM',
                '%b' => 'Mon', '%M' => 'FMMonth', '%a' => 'Dy', '%W' => 'FMDay', '%j' => 'DDD', '%T' => 'HH24:MI:SS', '%%' => '%'];
        return strtr($fmt, $map);
    }

    /** Replace NAME(args...) calls using $fn(array $args): ?string (null leaves the call alone). */
    private function callRewrite(string $sql, string $name, callable $fn): string
    {
        $offset = 0;
        $re = '/(?<![\w."])' . preg_quote($name, '/') . '\s*\(/i';
        while (preg_match($re, $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            if ($this->insideString($sql, $start)) {
                $offset = $start + 1;
                continue;
            }
            $open = $start + strlen($m[0][0]) - 1;
            $close = $this->matchParen($sql, $open);
            if ($close < 0) {
                break;
            }
            $inner = substr($sql, $open + 1, $close - $open - 1);
            $args = $this->splitTop($inner);
            $rep = $fn(array_map(fn($x) => $this->callRewrite($x, $name, $fn), $args));
            if ($rep === null) {
                $offset = $open + 1;
                continue;
            }
            $sql = substr($sql, 0, $start) . $rep . substr($sql, $close + 1);
            $offset = $start + strlen($rep);
        }
        return $sql;
    }

    private function insideString(string $sql, int $pos): bool
    {
        $in = false;
        for ($i = 0; $i < $pos; $i++) {
            if ($sql[$i] === "'") {
                if ($in && ($sql[$i + 1] ?? '') === "'") { $i++; continue; }
                if ($in && $i > 0 && $sql[$i - 1] === '\\') continue;
                $in = !$in;
            }
        }
        return $in;
    }

    private function groupConcat(string $sql): string
    {
        $offset = 0;
        while (preg_match('/\bGROUP_CONCAT\s*\(/i', $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $open = $start + strlen($m[0][0]) - 1;
            $close = $this->matchParen($sql, $open);
            if ($close < 0) {
                break;
            }
            $inner = substr($sql, $open + 1, $close - $open - 1);
            $distinct = false;
            if (preg_match('/^\s*DISTINCT\s+/i', $inner)) {
                $distinct = true;
                $inner = preg_replace('/^\s*DISTINCT\s+/i', '', $inner);
            }
            $sep = "','";
            if (preg_match("/\s+SEPARATOR\s+('(?:[^']|'')*')\s*$/i", $inner, $sm)) {
                $sep = $sm[1];
                $inner = substr($inner, 0, -strlen($sm[0]));
            }
            $order = '';
            $pos = $this->findOutside($inner, '/\s+ORDER\s+BY\s+/i');
            if ($pos !== null) {
                $order = ' ' . trim(substr($inner, $pos[0]));
                $inner = substr($inner, 0, $pos[0]);
            }
            if ($distinct) {
                // PostgreSQL needs the ORDER BY of a DISTINCT aggregate to be the aggregated expression itself (the TEXT cast)
                $cast = 'CAST(' . trim($inner) . ' AS TEXT)';
                // an ORDER BY on some other expression is not allowed with DISTINCT in PostgreSQL: values come out sorted
                $order = ' ORDER BY ' . $cast;
            }
            $rep = 'string_agg(' . ($distinct ? 'DISTINCT ' : '') . 'CAST(' . trim($inner) . ' AS TEXT), ' . $sep . $order . ')';
            $sql = substr($sql, 0, $start) . $rep . substr($sql, $close + 1);
            $offset = $start + strlen($rep);
        }
        return $sql;
    }
}
