<?php
// app/v1/models/ExpenseModel.php
// Branch purchase requests (a branch admin asks, the superadmin approves and gives money, the admin uploads the
// receipt) and the superadmin's expense tracker (one-off and repeating costs). Tables: migration 084.
// $uid / $actor are users.id. Dates are 'Y-m-d' (East Africa Time); money is KES with 2 decimals.
namespace Models;

use PDO;

class ExpenseModel
{
    public const BRANCHES = ['thika' => 'Thika', 'nairobi' => 'Nairobi', 'online' => 'Online', 'general' => 'Whole institute'];
    public const ADMIN_BRANCHES = ['thika' => 'Thika', 'nairobi' => 'Nairobi'];
    public const CATEGORIES = [
        'rent' => 'Rent', 'electricity' => 'Electricity', 'water' => 'Water', 'internet' => 'Internet and airtime',
        'food' => 'Food and tea', 'supplies' => 'Office supplies', 'equipment' => 'Equipment', 'transport' => 'Transport',
        'marketing' => 'Marketing and adverts', 'repairs' => 'Repairs and cleaning', 'fees' => 'Bank and M-Pesa charges',
        'purchase' => 'Branch purchase', 'other' => 'Other',
    ];
    public const REPEATS = ['once' => 'One time', 'daily' => 'Every day', 'weekly' => 'Every week', 'monthly' => 'Every month'];
    public const STATUS = [
        'pending' => ['Waiting for approval', 'warning'], 'approved' => ['Approved: receipt needed', 'info'],
        'declined' => ['Declined', 'secondary'], 'closed' => ['Done', 'success'],
    ];

    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public static function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
    }

    public static function money(?float $v): string
    {
        $v = (float) $v;
        return 'KES ' . number_format($v, abs($v - round($v)) < 0.005 ? 0 : 2);
    }

    public function log(?int $actor, string $action, string $entity, ?int $id, array $details = []): void
    {
        try {
            $this->db->prepare("INSERT INTO accounting_log (actor_user_id, action, entity, entity_id, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
                ->execute([$actor, $action, $entity, $id, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null]);
        } catch (\Throwable $e) {
            error_log('[ExpenseModel log] ' . $e->getMessage());
        }
    }

    /* ================= Branch of an admin ================= */

    public function branchOf(int $uid): ?string
    {
        $st = $this->db->prepare("SELECT branch FROM staff_branches WHERE user_id = ?");
        $st->execute([$uid]);
        return $st->fetchColumn() ?: null;
    }

    public function setBranch(int $uid, ?string $branch): void
    {
        if ($branch === null || !isset(self::ADMIN_BRANCHES[$branch])) {
            $this->db->prepare("DELETE FROM staff_branches WHERE user_id = ?")->execute([$uid]);
            return;
        }
        $this->db->prepare("INSERT INTO staff_branches (user_id, branch, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE branch = VALUES(branch), updated_at = NOW()")
            ->execute([$uid, $branch]);
    }

    /** user_id => branch for every admin with a branch. */
    public function branches(): array
    {
        return $this->db->query("SELECT user_id, branch FROM staff_branches")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /* ================= Purchase requests ================= */

    /** "today", "tomorrow" or a date → 'Y-m-d' (null when not a valid date from today on). */
    public static function neededOn(string $when, string $date = ''): ?string
    {
        $today = new \DateTimeImmutable(self::today());
        if ($when === 'today') return $today->format('Y-m-d');
        if ($when === 'tomorrow') return $today->modify('+1 day')->format('Y-m-d');
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $d && $d >= $today ? $d->format('Y-m-d') : null;
    }

    /** Clean the list of things to buy: [[item, qty, unit price]] → rows with a line total. Empty rows are skipped. */
    public static function cleanItems(array $names, array $qtys, array $prices): array
    {
        $out = [];
        foreach ($names as $i => $n) {
            $n = trim((string) $n);
            if ($n === '') continue;
            $q = max(1, (int) ($qtys[$i] ?? 1));
            $p = max(0, round((float) str_replace(',', '', (string) ($prices[$i] ?? 0)), 2));
            $out[] = ['item' => mb_substr($n, 0, 150), 'qty' => $q, 'price' => $p, 'total' => round($q * $p, 2)];
        }
        return array_slice($out, 0, 30);
    }

    /** The admin's request that still needs something: waiting for approval, or approved but no receipt yet. */
    public function openRequest(int $uid): ?array
    {
        $st = $this->db->prepare("SELECT * FROM expense_requests WHERE admin_user_id = ? AND status IN ('pending', 'approved') ORDER BY id DESC LIMIT 1");
        $st->execute([$uid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? $this->decorate($r) : null;
    }

    /** @return array{ok:bool, error?:string, id?:int} */
    public function createRequest(int $uid, array $items, string $where, ?string $neededOn, string $note): array
    {
        $branch = $this->branchOf($uid);
        if (!$branch) return ['ok' => false, 'error' => 'Your account has no branch yet. Ask the superadmin to set Thika or Nairobi for you.'];
        if ($this->openRequest($uid)) return ['ok' => false, 'error' => 'You already have a request open. Wait for a decision, or upload the receipt for it first.'];
        if (!$items) return ['ok' => false, 'error' => 'List at least one thing to buy.'];
        if (trim($where) === '') return ['ok' => false, 'error' => 'Say where you will buy it.'];
        if (!$neededOn) return ['ok' => false, 'error' => 'Choose when you need it (today, tomorrow or a date).'];
        $total = round(array_sum(array_column($items, 'total')), 2);
        if ($total <= 0) return ['ok' => false, 'error' => 'Give an estimated price for what you want to buy.'];
        $this->db->prepare("INSERT INTO expense_requests (admin_user_id, branch, items_json, where_buy, needed_on, estimated_total, note, status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())")
            ->execute([$uid, $branch, json_encode($items, JSON_UNESCAPED_UNICODE), mb_substr(trim($where), 0, 200), $neededOn, $total, trim($note) !== '' ? mb_substr(trim($note), 0, 2000) : null]);
        $id = (int) $this->db->lastInsertId();
        $this->log($uid, 'request', 'expense_request', $id, ['total' => $total]);
        return ['ok' => true, 'id' => $id];
    }

    public function request(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT er.*, u.email, u.username FROM expense_requests er JOIN users u ON u.id = er.admin_user_id WHERE er.id = ?"
        );
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? $this->decorate($r) : null;
    }

    /** Requests, newest first. $status '' = all; $uid limits to one admin. */
    public function requests(string $status = '', ?int $uid = null, int $limit = 200): array
    {
        $sql = "SELECT er.*, u.email, u.username FROM expense_requests er JOIN users u ON u.id = er.admin_user_id WHERE 1 = 1";
        $args = [];
        if ($status !== '') { $sql .= " AND er.status = ?"; $args[] = $status; }
        if ($uid !== null) { $sql .= " AND er.admin_user_id = ?"; $args[] = $uid; }
        $st = $this->db->prepare($sql . " ORDER BY FIELD(er.status, 'pending', 'approved', 'closed', 'declined'), er.id DESC LIMIT " . (int) $limit);
        $st->execute($args);
        return array_map([$this, 'decorate'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    private function decorate(array $r): array
    {
        $r['items'] = json_decode((string) $r['items_json'], true) ?: [];
        $r['balance'] = $r['amount_given'] !== null && $r['spent_amount'] !== null ? round((float) $r['amount_given'] - (float) $r['spent_amount'], 2) : null;
        return $r;
    }

    /** Superadmin decision. Approving needs the amount given to the admin. */
    public function decide(int $id, bool $approve, ?float $amountGiven, string $note, int $actor): array
    {
        $r = $this->request($id);
        if (!$r || $r['status'] !== 'pending') return ['ok' => false, 'error' => 'This request has already been decided.'];
        if ($approve && ($amountGiven === null || $amountGiven <= 0)) return ['ok' => false, 'error' => 'Enter how much money was given.'];
        $this->db->prepare("UPDATE expense_requests SET status = ?, amount_given = ?, decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ? AND status = 'pending'")
            ->execute([$approve ? 'approved' : 'declined', $approve ? round($amountGiven, 2) : null, $actor, trim($note) !== '' ? mb_substr(trim($note), 0, 2000) : null, $id]);
        $this->log($actor, $approve ? 'approve' : 'decline', 'expense_request', $id, ['given' => $amountGiven]);
        return ['ok' => true];
    }

    /**
     * The admin uploads the receipt for an approved request. $spent: what the admin typed (null = use what was read
     * from the receipt). The purchase is then recorded in the expense tracker for the branch, and the request closes.
     */
    public function closeWithReceipt(int $id, int $uid, array $stored, string $origName, ?float $detected, ?float $spent): array
    {
        $r = $this->request($id);
        if (!$r || (int) $r['admin_user_id'] !== $uid) return ['ok' => false, 'error' => 'Request not found.'];
        if ($r['status'] !== 'approved') return ['ok' => false, 'error' => 'A receipt can only be added once the request is approved.'];
        $amount = $spent ?? $detected;
        if ($amount === null || $amount <= 0) return ['ok' => false, 'error' => 'We could not read the amount from the receipt. Type how much was spent.'];
        $source = $spent !== null ? 'manual' : 'detected';
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE expense_requests SET status = 'closed', receipt_path = ?, receipt_name = ?, receipt_mime = ?, detected_amount = ?, spent_amount = ?,
                                spent_source = ?, receipt_at = NOW() WHERE id = ? AND status = 'approved'")
                ->execute([$stored['path'], mb_substr(basename($origName), 0, 255), $stored['mime'], $detected, round($amount, 2), $source, $id]);
            $title = implode(', ', array_map(fn($i) => $i['item'], $r['items']));
            $this->db->prepare("INSERT INTO expenses (title, category, branch, amount, repeat_every, start_date, note, receipt_path, receipt_name, receipt_mime, request_id, created_by, created_at)
                                VALUES (?, 'purchase', ?, ?, 'once', ?, ?, ?, ?, ?, ?, ?, NOW())")
                ->execute([mb_substr($title, 0, 200), $r['branch'], round($amount, 2), self::today(), 'Bought at ' . $r['where_buy'] . ' (request #' . $id . ')',
                           $stored['path'], mb_substr(basename($origName), 0, 255), $stored['mime'], $id, $uid]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->log($uid, 'receipt', 'expense_request', $id, ['spent' => $amount, 'source' => $source, 'given' => $r['amount_given']]);
        return ['ok' => true, 'spent' => round($amount, 2), 'balance' => round((float) $r['amount_given'] - $amount, 2), 'source' => $source];
    }

    /* ================= Expense tracker (superadmin) ================= */

    public function expenses(bool $withStopped = true): array
    {
        $sql = "SELECT e.*, er.admin_user_id FROM expenses e LEFT JOIN expense_requests er ON er.id = e.request_id";
        if (!$withStopped) $sql .= " WHERE e.stopped_at IS NULL";
        return $this->db->query($sql . " ORDER BY e.start_date DESC, e.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function expense(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM expenses WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array{ok:bool, error?:string, id?:int} */
    public function addExpense(array $d, ?array $receipt, ?string $receiptName, int $actor): array
    {
        $title = trim((string) ($d['title'] ?? ''));
        $amount = round((float) str_replace(',', '', (string) ($d['amount'] ?? 0)), 2);
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($d['start_date'] ?? ''));
        $end = !empty($d['end_date']) ? \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $d['end_date']) : null;
        if ($title === '') return ['ok' => false, 'error' => 'Say what the expense is.'];
        if ($amount <= 0) return ['ok' => false, 'error' => 'Enter the amount.'];
        if (!$start) return ['ok' => false, 'error' => 'Choose the date (or the first date for a repeating expense).'];
        if ($end && $end < $start) return ['ok' => false, 'error' => 'The end date is before the start date.'];
        $repeat = isset(self::REPEATS[$d['repeat_every'] ?? '']) ? $d['repeat_every'] : 'once';
        $this->db->prepare("INSERT INTO expenses (title, category, branch, amount, repeat_every, start_date, end_date, note, receipt_path, receipt_name, receipt_mime, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
            ->execute([mb_substr($title, 0, 200), isset(self::CATEGORIES[$d['category'] ?? '']) ? $d['category'] : 'other',
                       isset(self::BRANCHES[$d['branch'] ?? '']) ? $d['branch'] : 'general', $amount, $repeat, $start->format('Y-m-d'),
                       $repeat === 'once' ? null : ($end ? $end->format('Y-m-d') : null), trim((string) ($d['note'] ?? '')) ?: null,
                       $receipt['path'] ?? null, $receipt ? mb_substr(basename((string) $receiptName), 0, 255) : null, $receipt['mime'] ?? null, $actor]);
        $id = (int) $this->db->lastInsertId();
        $this->log($actor, 'add', 'expense', $id, ['amount' => $amount, 'repeat' => $repeat]);
        return ['ok' => true, 'id' => $id];
    }

    /** A repeating expense stops from today (earlier ones stay counted). A one-time expense is deleted. */
    public function stopOrDelete(int $id, int $actor): string
    {
        $e = $this->expense($id);
        if (!$e) return 'missing';
        if ($e['request_id']) return 'locked';
        if ($e['repeat_every'] === 'once') {
            $this->db->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);
            $this->log($actor, 'delete', 'expense', $id, ['title' => $e['title'], 'amount' => $e['amount']]);
            return 'deleted';
        }
        $this->db->prepare("UPDATE expenses SET end_date = LEAST(COALESCE(end_date, ?), ?), stopped_at = NOW() WHERE id = ?")->execute([self::today(), self::today(), $id]);
        $this->log($actor, 'stop', 'expense', $id);
        return 'stopped';
    }

    /**
     * Dates an expense falls on between $from and $to (inclusive), pure. A monthly expense on the 31st falls on the
     * last day of shorter months. Capped at 3660 dates so a daily expense over many years stays cheap.
     */
    public static function occurrences(array $e, string $from, string $to): array
    {
        $start = new \DateTimeImmutable($e['start_date']);
        $last = new \DateTimeImmutable($to);
        if (!empty($e['end_date']) && new \DateTimeImmutable($e['end_date']) < $last) $last = new \DateTimeImmutable($e['end_date']);
        $first = new \DateTimeImmutable($from);
        $out = [];
        if (($e['repeat_every'] ?? 'once') === 'once') {
            return $start >= $first && $start <= $last ? [$start->format('Y-m-d')] : [];
        }
        if ($e['repeat_every'] === 'monthly') {
            $day = (int) $start->format('j');
            for ($m = $start->modify('first day of this month'); $m <= $last && count($out) < 3660; $m = $m->modify('+1 month')) {
                $d = $m->setDate((int) $m->format('Y'), (int) $m->format('n'), min($day, (int) $m->format('t')));
                if ($d >= $start && $d >= $first && $d <= $last) $out[] = $d->format('Y-m-d');
            }
            return $out;
        }
        $step = $e['repeat_every'] === 'weekly' ? 7 : 1;
        $d = $start;
        if ($first > $start) {
            $skip = (int) floor(($first->getTimestamp() - $start->getTimestamp()) / 86400 / $step);
            $d = $start->modify('+' . ($skip * $step) . ' days');
            if ($d < $first) $d = $d->modify('+' . $step . ' days');
        }
        for (; $d <= $last && count($out) < 3660; $d = $d->modify('+' . $step . ' days')) $out[] = $d->format('Y-m-d');
        return $out;
    }

    /** Every expense line in a date range: [date, title, category, branch, amount, expense id]. */
    public function expenseLines(string $from, string $to, ?string $branch = null): array
    {
        $st = $this->db->prepare("SELECT * FROM expenses WHERE start_date <= ? AND (end_date IS NULL OR end_date >= ?)" . ($branch ? " AND branch = ?" : ""));
        $st->execute($branch ? [$to, $from, $branch] : [$to, $from]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $e) {
            foreach (self::occurrences($e, $from, $to) as $day) {
                $out[] = ['date' => $day, 'title' => $e['title'], 'category' => $e['category'], 'branch' => $e['branch'], 'amount' => (float) $e['amount'],
                          'id' => (int) $e['id'], 'repeat' => $e['repeat_every']];
            }
        }
        usort($out, fn($a, $b) => strcmp($a['date'], $b['date']));
        return $out;
    }

    /* ================= Receipt files (private storage) ================= */

    public static function storageDir(): string
    {
        $dir = dirname(__DIR__) . '/storage/accounting';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        return $dir;
    }

    /** Store a receipt: PDF, JPG or PNG up to 10 MB, real type checked. @return array{ok:bool, path?:string, mime?:string, abs?:string, error?:string} */
    public static function storeReceipt(array $file): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'error' => 'Choose the receipt file.'];
        if ($err !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'The receipt did not upload. Please try again.'];
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) return ['ok' => false, 'error' => 'The receipt is larger than 10 MB.'];
        if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'error' => 'The upload failed the security check.'];
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($fi, $file['tmp_name']);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext) return ['ok' => false, 'error' => 'Upload the receipt as a PDF or a photo (JPG or PNG).'];
        $sub = date('Y/m');
        $dir = self::storageDir() . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'Could not create the receipts folder.'];
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $ok = defined('UPLOAD_TEST_MODE') ? @copy($file['tmp_name'], "$dir/$name") : @move_uploaded_file($file['tmp_name'], "$dir/$name");
        if (!$ok) return ['ok' => false, 'error' => 'The receipt could not be saved.'];
        return ['ok' => true, 'path' => "$sub/$name", 'mime' => $mime, 'abs' => "$dir/$name"];
    }

    public static function receiptAbs(?string $rel): ?string
    {
        if (!$rel) return null;
        $base = realpath(self::storageDir());
        $abs = realpath($base . '/' . $rel);
        return $abs && $base && str_starts_with($abs, $base . DIRECTORY_SEPARATOR) && is_file($abs) ? $abs : null;
    }
}
