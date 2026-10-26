<?php

class CampusMediaModel
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare(
            "INSERT INTO campus_media
                (media_type, file_path, title, description, sort_order, status, created_by)
             VALUES
                (:media_type, :file_path, :title, :description, :sort_order, :status, :created_by)"
        );
        $ok = $stmt->execute([
            ':media_type' => $data['media_type'] ?? 'image',
            ':file_path' => $data['file_path'] ?? '',
            ':title' => $data['title'] ?? null,
            ':description' => $data['description'] ?? null,
            ':sort_order' => (int) ($data['sort_order'] ?? 0),
            ':status' => $data['status'] ?? 'active',
            ':created_by' => $data['created_by'] ?? null,
        ]);

        return $ok ? (int) $this->db->lastInsertId() : false;
    }

    public function getAll(?string $status = null, int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT cm.*, u.username AS creator_name
                FROM campus_media cm
                LEFT JOIN users u ON u.id = cm.created_by";
        $params = [];

        if ($status !== null && $status !== '') {
            $sql .= " WHERE cm.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY cm.sort_order ASC, cm.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        try {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1146) {
                return [];
            }
            throw $e;
        }
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM campus_media WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['title', 'description', 'sort_order', 'status'];
        $fields = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $field === 'sort_order' ? (int) $data[$field] : $data[$field];
            }
        }

        if (!$fields) {
            return false;
        }

        $stmt = $this->db->prepare("UPDATE campus_media SET " . implode(', ', $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function delete(int $id): ?array
    {
        $item = $this->getById($id);
        if (!$item) {
            return null;
        }

        $stmt = $this->db->prepare("DELETE FROM campus_media WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return $item;
    }
}
