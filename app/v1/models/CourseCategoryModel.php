<?php
namespace Models;

class CourseCategoryModel {
    private \PDO $db;

    public function __construct(\PDO $pdo) {
        $this->db = $pdo;
    }

    public function getAll(bool $activeOnly = true): array {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM courses co WHERE co.category_id = c.id AND co.is_active = 1) AS course_count
                FROM course_categories c';
        if ($activeOnly) {
            $sql .= ' WHERE c.is_active = 1';
        }
        $sql .= ' ORDER BY c.sort_order ASC, c.name ASC';
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM course_categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getBySlug(string $slug): ?array {
        $stmt = $this->db->prepare('SELECT * FROM course_categories WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public function create(array $data): array {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'error' => 'Category name is required.'];
        }
        $slug = $this->slugify($data['slug'] ?? $name);
        if ($this->getBySlug($slug)) {
            $slug .= '-' . time();
        }

        $stmt = $this->db->prepare(
            'INSERT INTO course_categories (name, slug, icon_path, sort_order, is_active)
             VALUES (:name, :slug, :icon_path, :sort_order, :is_active)'
        );
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':icon_path' => $data['icon_path'] ?? null,
            ':sort_order' => (int) ($data['sort_order'] ?? 0),
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    /** @return array{ok:bool,error?:string} */
    public function update(int $id, array $data): array {
        $existing = $this->getById($id);
        if (!$existing) {
            return ['ok' => false, 'error' => 'Category not found.'];
        }

        $name = trim($data['name'] ?? $existing['name']);
        $slug = $this->slugify($data['slug'] ?? $name);
        $conflict = $this->getBySlug($slug);
        if ($conflict && (int) $conflict['id'] !== $id) {
            return ['ok' => false, 'error' => 'That slug is already used by another category.'];
        }

        $iconPath = $data['icon_path'] ?? $existing['icon_path'];
        $stmt = $this->db->prepare(
            'UPDATE course_categories SET name = :name, slug = :slug, icon_path = :icon_path,
             sort_order = :sort_order, is_active = :is_active WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':icon_path' => $iconPath,
            ':sort_order' => (int) ($data['sort_order'] ?? $existing['sort_order']),
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':id' => $id,
        ]);

        return ['ok' => true];
    }

    public function delete(int $id): bool {
        $this->db->prepare('UPDATE courses SET category_id = NULL WHERE category_id = :id')->execute([':id' => $id]);
        $stmt = $this->db->prepare('DELETE FROM course_categories WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    private function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-') ?: 'category';
    }
}
