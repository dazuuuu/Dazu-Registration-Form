<?php
namespace Models;

class TeamMemberModel
{
    public function __construct(private \PDO $db) {}

    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, name, position AS role, biography AS bio, photo AS photo_path, sort_order, 1 AS is_active FROM team_members';
        return $this->db->query($sql . ' ORDER BY sort_order ASC, id ASC')->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, position AS role, biography AS bio, photo AS photo_path, sort_order, 1 AS is_active FROM team_members WHERE id = :id');
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
        if ($id) {
            $data[':id'] = $id;
            $this->db->prepare('UPDATE team_members SET name=:name, position=:role, biography=:bio, photo=:photo, sort_order=:sort_order WHERE id=:id')->execute($data);
            return $id;
        }
        $this->db->prepare('INSERT INTO team_members (name, position, biography, photo, sort_order) VALUES (:name, :role, :bio, :photo, :sort_order)')->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM team_members WHERE id = :id')->execute([':id' => $id]);
    }
}
