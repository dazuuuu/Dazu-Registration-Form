<?php
class StoreCategoryModel {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    private function generateSlug($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
    
    public function create($name, $description = null, $image_path = null) {
        $slug = $this->generateSlug($name);
        
        // Check if slug exists
        $slugExists = $this->slugExists($slug);
        if ($slugExists) {
            $slug = $slug . '-' . time();
        }
        
        $sql = "INSERT INTO store_categories (name, slug, description, image_path) 
                VALUES (:name, :slug, :description, :image_path)";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $description,
            ':image_path' => $image_path
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    private function slugExists($slug) {
        $sql = "SELECT id FROM store_categories WHERE slug = :slug";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch();
    }
    
    public function getAll($activeOnly = true) {
        $sql = "SELECT * FROM store_categories";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        $sql = "SELECT * FROM store_categories WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getBySlug($slug) {
        $sql = "SELECT * FROM store_categories WHERE slug = :slug AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['name', 'description', 'image_path', 'sort_order', 'is_active'];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (isset($data['name'])) {
            $fields[] = "slug = :slug";
            $params[':slug'] = $this->generateSlug($data['name']);
        }
        
        if (empty($fields)) return false;
        
        $sql = "UPDATE store_categories SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function delete($id) {
        // Check if category has products
        $sql = "SELECT COUNT(*) FROM store_products WHERE category_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            return ['success' => false, 'message' => "Cannot delete category with {$count} products"];
        }
        
        $sql = "DELETE FROM store_categories WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':id' => $id]);
        
        return ['success' => $result, 'message' => $result ? 'Category deleted' : 'Delete failed'];
    }
    
    public function getProductCount($category_id) {
        $sql = "SELECT COUNT(*) FROM store_products WHERE category_id = :category_id AND status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':category_id' => $category_id]);
        return $stmt->fetchColumn();
    }
}
?>