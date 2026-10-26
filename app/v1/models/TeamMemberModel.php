<?php
namespace Models;

class TeamMemberModel
{
    /** Contact / social fields, in the order their icons appear on a card. */
    public const SOCIALS = ['phone', 'email', 'whatsapp', 'tiktok', 'linkedin', 'facebook', 'instagram'];

    private const COLS = 'id, name, position AS role, biography AS bio, photo AS photo_path, sort_order, 1 AS is_active,
                          phone, email, whatsapp, tiktok, linkedin, facebook, instagram';

    public function __construct(private \PDO $db)
    {
        $this->ensureSocialColumns();
    }

    /** Adds the social columns if migration 056 has not been run yet. */
    private function ensureSocialColumns(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $have = $this->db->query('SHOW COLUMNS FROM team_members')->fetchAll(\PDO::FETCH_COLUMN);
            $want = ['whatsapp' => 'VARCHAR(50)', 'tiktok' => 'VARCHAR(255)', 'linkedin' => 'VARCHAR(255)', 'facebook' => 'VARCHAR(255)', 'instagram' => 'VARCHAR(255)'];
            foreach ($want as $col => $type) {
                if (!in_array($col, $have, true)) {
                    $this->db->exec("ALTER TABLE team_members ADD COLUMN $col $type NULL");
                }
            }
        } catch (\Throwable $e) {
            // Leave the page usable; saving socials will fail until the migration runs.
        }
    }

    public function all(bool $activeOnly = false): array
    {
        return $this->db->query('SELECT ' . self::COLS . ' FROM team_members ORDER BY sort_order ASC, id ASC')->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLS . ' FROM team_members WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function save(array $member, ?int $id = null): int
    {
        $data = [
            ':name' => trim((string) $member['name']),
            ':role' => trim((string) $member['role']),
            ':bio' => trim((string) ($member['bio'] ?? '')) ?: null,
            ':photo' => $member['photo_path'] ?? null,
            ':sort_order' => (int) ($member['sort_order'] ?? 0),
        ];
        foreach (self::SOCIALS as $s) {
            $data[':' . $s] = trim((string) ($member[$s] ?? '')) ?: null;
        }
        $socialSet = implode(', ', array_map(fn($s) => "$s=:$s", self::SOCIALS));
        if ($id) {
            $data[':id'] = $id;
            $this->db->prepare("UPDATE team_members SET name=:name, position=:role, biography=:bio, photo=:photo, sort_order=:sort_order, $socialSet WHERE id=:id")->execute($data);
            return $id;
        }
        $cols = implode(', ', self::SOCIALS);
        $vals = implode(', ', array_map(fn($s) => ":$s", self::SOCIALS));
        $this->db->prepare("INSERT INTO team_members (name, position, biography, photo, sort_order, $cols) VALUES (:name, :role, :bio, :photo, :sort_order, $vals)")->execute($data);
        return (int) $this->db->lastInsertId();
    }

    /** Save a new display order: $ids in the order they should appear. */
    public function reorder(array $ids): void
    {
        $stmt = $this->db->prepare('UPDATE team_members SET sort_order = ? WHERE id = ?');
        foreach (array_values($ids) as $i => $id) {
            $stmt->execute([$i + 1, (int) $id]);
        }
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM team_members WHERE id = :id')->execute([':id' => $id]);
    }
}
