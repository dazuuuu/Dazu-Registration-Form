<?php
namespace Models;

class ClassScheduleModel
{
    private \PDO $db;

    public function __construct(\PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function getByMode(string $mode, bool $activeOnly = true): array
    {
        $mode = $mode === 'physical' ? 'physical' : 'online';
        $sql = 'SELECT * FROM class_schedule_entries WHERE study_mode = :mode';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':mode' => $mode]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getAllGrouped(bool $activeOnly = false): array
    {
        return [
            'online' => $this->getByMode('online', $activeOnly),
            'physical' => $this->getByMode('physical', $activeOnly),
        ];
    }

    public function create(array $data): array
    {
        $mode = ($data['study_mode'] ?? '') === 'physical' ? 'physical' : 'online';
        $day = trim((string) ($data['day_label'] ?? ''));
        $time = trim((string) ($data['time_label'] ?? ''));
        if ($day === '' || $time === '') {
            return ['ok' => false, 'error' => 'Day and time are required.'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO class_schedule_entries (study_mode, day_label, time_label, notes, sort_order, is_active)
             VALUES (:mode, :day, :time, :notes, :sort, :active)'
        );
        $stmt->execute([
            ':mode' => $mode,
            ':day' => $day,
            ':time' => $time,
            ':notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ':sort' => (int) ($data['sort_order'] ?? 0),
            ':active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    public function update(int $id, array $data): array
    {
        $existing = $this->getById($id);
        if (!$existing) {
            return ['ok' => false, 'error' => 'Schedule entry not found.'];
        }

        $mode = ($data['study_mode'] ?? $existing['study_mode']) === 'physical' ? 'physical' : 'online';
        $day = trim((string) ($data['day_label'] ?? $existing['day_label']));
        $time = trim((string) ($data['time_label'] ?? $existing['time_label']));
        if ($day === '' || $time === '') {
            return ['ok' => false, 'error' => 'Day and time are required.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE class_schedule_entries
             SET study_mode = :mode, day_label = :day, time_label = :time, notes = :notes,
                 sort_order = :sort, is_active = :active
             WHERE id = :id'
        );
        $stmt->execute([
            ':mode' => $mode,
            ':day' => $day,
            ':time' => $time,
            ':notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ':sort' => (int) ($data['sort_order'] ?? $existing['sort_order']),
            ':active' => !empty($data['is_active']) ? 1 : 0,
            ':id' => $id,
        ]);

        return ['ok' => true];
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM class_schedule_entries WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM class_schedule_entries WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM class_schedule_entries')->fetchColumn();
    }

    /** Replace all entries with the default Mon–Fri schedule slots. */
    public function restoreDefaults(): array
    {
        require_once __DIR__ . '/../helpers/class_schedule_defaults.php';
        $this->db->exec('DELETE FROM class_schedule_entries');
        foreach (class_schedule_default_entries() as $entry) {
            $this->create($entry);
        }
        return ['ok' => true];
    }

    /** Insert defaults only when table is empty. */
    public function seedDefaultsIfEmpty(): bool
    {
        if ($this->countAll() > 0) {
            return false;
        }
        require_once __DIR__ . '/../helpers/class_schedule_defaults.php';
        foreach (class_schedule_default_entries() as $entry) {
            $this->create($entry);
        }
        return true;
    }
}
