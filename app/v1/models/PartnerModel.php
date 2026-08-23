<?php
namespace Models;

class PartnerModel
{
    public function __construct(private \PDO $db) {}

    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM partners';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM partners WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function save(array $data, ?int $id = null): int
    {
        $params = [
            ':title' => trim((string) ($data['title'] ?? '')) ?: null,
            ':image' => (string) ($data['image_path'] ?? ''),
            ':sort' => (int) ($data['sort_order'] ?? 0),
            ':active' => !empty($data['is_active']) ? 1 : 0,
        ];
        if ($id) {
            $params[':id'] = $id;
            $this->db->prepare(
                'UPDATE partners SET title = :title, image_path = :image, sort_order = :sort, is_active = :active WHERE id = :id'
            )->execute($params);
            return $id;
        }
        $this->db->prepare(
            'INSERT INTO partners (title, image_path, sort_order, is_active) VALUES (:title, :image, :sort, :active)'
        )->execute($params);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM partners WHERE id = :id')->execute([':id' => $id]);
    }
}
