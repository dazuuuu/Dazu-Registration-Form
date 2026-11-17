<?php
/**
 * The admin's Secretaries list (the normal admin only, see secretary_admin_require()): every change an admin makes to a secretary after she was added.
 * Every change is checked here (known switch, known value, real secretary) and written to secretary_changes with who made it and when.
 * A secretary is never deleted: "Left" turns her access off and keeps her record. Her ID number (staff_no) is never written by anything in this file.
 */
require_once __DIR__ . '/SecretaryService.php';

class SecretaryAdminService
{
    /** The switches shown on the list (the rest are on the form). */
    public const LIST_SWITCHES = ['blogs_approval', 'staff_calendar', 'id_card', 'enquiries'];

    private PDO $db;
    private $mailFn;

    public function __construct(PDO $db, ?callable $mailFn = null)
    {
        $this->db = $db;
        $this->mailFn = $mailFn;
    }

    private function now(): string
    {
        return (new DateTime('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d H:i:s');
    }

    /** All secretaries, newest first, with her name, switches, whether she is suspended, and the last change (who and when, name only). */
    public function all(): array
    {
        $rows = $this->db->query(
            "SELECT s.id, s.user_id, s.staff_no, s.job_title, s.branch, s.status, s.photo_path, u.email, u.is_active, u.account_status,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS full_name,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(ap.first_name,''), ' ', COALESCE(ap.last_name,''))), ''), au.username) AS added_by_name
             FROM secretaries s JOIN users u ON u.id = s.user_id LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN users au ON au.id = s.added_by LEFT JOIN user_profiles ap ON ap.user_id = s.added_by
             ORDER BY s.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $last = $this->db->prepare(
            "SELECT c.field_key, c.new_value, c.changed_at, c.changed_by_name AS by_name
             FROM secretary_changes c WHERE c.secretary_id = ? ORDER BY c.id DESC LIMIT 1"
        );
        foreach ($rows as &$r) {
            $r['switches'] = secretary_switches($this->db, (int) $r['id']);
            $r['suspended'] = $r['account_status'] === 'suspended';
            $last->execute([(int) $r['id']]);
            $r['last_change'] = $last->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        return $rows;
    }

    /** One secretary for the Edit form (everything on the form, plus who added her). */
    public function find(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT s.*, u.email, u.is_active, u.account_status, up.first_name, up.last_name FROM secretaries s JOIN users u ON u.id = s.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id WHERE s.id = ?"
        );
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        $r['switches'] = secretary_switches($this->db, $id);
        $r['added_by_name'] = $r['added_by'] ? secretary_person_name($this->db, (int) $r['added_by']) : '';
        return $r;
    }

    private function log(int $secId, string $key, ?string $old, ?string $new, int $by): void
    {
        $this->db->prepare('INSERT INTO secretary_changes (secretary_id, field_key, old_value, new_value, changed_by, changed_by_name, changed_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                 ->execute([$secId, $key, $old, $new, $by ?: null, $by ? secretary_person_name($this->db, $by) : null, $this->now()]);
    }

    private function exists(int $id): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM secretaries WHERE id = ?');
        $st->execute([$id]);
        return (bool) $st->fetchColumn();
    }

    /** Save one switch at once. Returns ['ok', 'value', 'changed', 'by', 'at'] or ['ok' => false, 'error']. */
    public function setSwitch(int $secId, string $key, string $value, int $by): array
    {
        $defs = secretary_switch_defs();
        if (!isset($defs[$key])) return ['ok' => false, 'error' => 'Unknown switch.'];
        if (!isset($defs[$key][2][$value])) return ['ok' => false, 'error' => 'That value is not allowed for this switch.'];
        if (!$this->exists($secId)) return ['ok' => false, 'error' => 'Secretary not found.'];
        try {
            $this->db->beginTransaction();
            $st = $this->db->prepare('SELECT switch_value FROM secretary_switches WHERE secretary_id = ? AND switch_key = ?');
            $st->execute([$secId, $key]);
            $old = $st->fetchColumn();
            if ($old === false) {
                try {
                    $this->db->exec('SAVEPOINT sw_ins');
                    $this->db->prepare('INSERT INTO secretary_switches (secretary_id, switch_key, switch_value) VALUES (?, ?, ?)')->execute([$secId, $key, $value]);
                    $this->db->exec('RELEASE SAVEPOINT sw_ins');
                } catch (PDOException $e) {   // two changes at the same moment: the other one made the row first
                    $this->db->exec('ROLLBACK TO SAVEPOINT sw_ins');
                    $this->db->prepare('UPDATE secretary_switches SET switch_value = ? WHERE secretary_id = ? AND switch_key = ?')->execute([$value, $secId, $key]);
                }
            } elseif ($old !== $value) {
                $this->db->prepare('UPDATE secretary_switches SET switch_value = ? WHERE secretary_id = ? AND switch_key = ?')->execute([$value, $secId, $key]);
            }
            $changed = ($old === false ? 'off' : $old) !== $value || $old === false;
            if ($changed) $this->log($secId, $key, $old === false ? null : (string) $old, $value, $by);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[SecretaryAdminService::setSwitch] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not save. Please try again.'];
        }
        return ['ok' => true, 'value' => $value, 'changed' => $changed, 'by' => secretary_person_name($this->db, $by), 'at' => $this->now()];
    }

    /** Active, On leave or Left. Left (or a suspension) turns her login off at once; she is never deleted. */
    public function setStatus(int $secId, string $status, int $by): array
    {
        if (!isset(secretary_statuses()[$status])) return ['ok' => false, 'error' => 'Choose Active, On leave or Left.'];
        $st = $this->db->prepare('SELECT s.status, s.user_id, u.account_status FROM secretaries s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
        $st->execute([$secId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['ok' => false, 'error' => 'Secretary not found.'];
        try {
            $this->db->beginTransaction();
            if ($r['status'] !== $status) {
                $this->db->prepare('UPDATE secretaries SET status = ? WHERE id = ?')->execute([$status, $secId]);
                $this->applyAccess((int) $r['user_id'], $status, $r['account_status'] === 'suspended');
                $this->log($secId, 'status', $r['status'], $status, $by);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[SecretaryAdminService::setStatus] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not save. Please try again.'];
        }
        return ['ok' => true, 'status' => $status, 'changed' => $r['status'] !== $status];
    }

    /** Suspend (or lift the suspension): blocks her login at once. Her status and records stay as they are. */
    public function setSuspended(int $secId, bool $suspend, int $by): array
    {
        $st = $this->db->prepare('SELECT s.status, s.user_id, u.account_status FROM secretaries s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
        $st->execute([$secId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['ok' => false, 'error' => 'Secretary not found.'];
        $was = $r['account_status'] === 'suspended';
        try {
            $this->db->beginTransaction();
            if ($was !== $suspend) {
                $this->db->prepare('UPDATE users SET account_status = ? WHERE id = ?')->execute([$suspend ? 'suspended' : 'active', (int) $r['user_id']]);
                $this->applyAccess((int) $r['user_id'], (string) $r['status'], $suspend);
                $this->log($secId, 'suspended', $was ? 'on' : 'off', $suspend ? 'on' : 'off', $by);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[SecretaryAdminService::setSuspended] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not save. Please try again.'];
        }
        return ['ok' => true, 'suspended' => $suspend, 'changed' => $was !== $suspend];
    }

    /** users.is_active follows her: off when she has Left or is suspended, on otherwise. (On leave blocks nothing: she works as usual and the list only shows a badge. Left is also checked by the guard on every request.) */
    private function applyAccess(int $userId, string $status, bool $suspended): void
    {
        $this->db->prepare("UPDATE users SET is_active = ?, account_status = ? WHERE id = ?")
                 ->execute([($status !== 'left' && !$suspended) ? 1 : 0, $suspended ? 'suspended' : 'active', $userId]);
    }

    /** New temporary password by email; she must choose her own at the next sign-in. Not for someone who has Left. */
    public function resetPassword(int $secId, int $by): array
    {
        $r = (new SecretaryService($this->db, $this->mailFn))->resendCredentials($secId);
        if (!empty($r['ok'])) $this->log($secId, 'password', null, 'reset', $by);
        return $r;
    }

    /**
     * Save the Edit form. $d is the validated form (SecretaryService::validate). Her ID number is never touched.
     * Switch changes are saved and logged one by one; the status is saved with setStatus.
     */
    public function update(int $secId, array $d, ?string $photoPath, ?string $status, int $by): array
    {
        $cur = $this->find($secId);
        if (!$cur) return ['ok' => false, 'error' => 'Secretary not found.'];
        try {
            $this->db->beginTransaction();
            $this->db->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$d['email'], (int) $cur['user_id']]);
            $this->db->prepare('UPDATE user_profiles SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?')->execute([$d['first_name'], $d['last_name'], $d['phone'], (int) $cur['user_id']]);
            $this->db->prepare('UPDATE secretaries SET job_title = ?, phone = ?, branch = ?, birth_day = ?, birth_month = ?, birth_year = ?, start_date = ?, emergency_name = ?, emergency_phone = ?'
                . ($photoPath ? ', photo_path = ?' : '') . ' WHERE id = ?')
                ->execute(array_merge([$d['job_title'], $d['phone'], $d['branch'], $d['birth_day'], $d['birth_month'], $d['birth_year'], $d['start_date'], $d['emergency_name'] ?: null, $d['emergency_phone'] ?: null],
                    $photoPath ? [$photoPath] : [], [$secId]));
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[SecretaryAdminService::update] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not save the changes. Nothing was changed.'];
        }
        foreach ($d['switches'] as $k => $v) {
            if (($cur['switches'][$k] ?? 'off') !== $v) $this->setSwitch($secId, $k, $v, $by);
        }
        if ($status !== null && $status !== $cur['status']) $this->setStatus($secId, $status, $by);
        return ['ok' => true];
    }
}
