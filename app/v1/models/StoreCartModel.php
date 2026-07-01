<?php
class StoreCartModel {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    public function addItem($user_id, $product_id, $quantity = 1) {
        // Get product price
        $sql = "SELECT price, stock_quantity FROM store_products WHERE id = :id AND status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        if ($product['stock_quantity'] < $quantity) {
            return ['success' => false, 'message' => 'Not enough stock available'];
        }
        
        // Check if item already in cart
        $sql = "SELECT id, quantity FROM store_cart WHERE user_id = :user_id AND product_id = :product_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':product_id' => $product_id
        ]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $newQuantity = $existing['quantity'] + $quantity;
            $sql = "UPDATE store_cart SET quantity = :quantity, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([':quantity' => $newQuantity, ':id' => $existing['id']]);
        } else {
            $sql = "INSERT INTO store_cart (user_id, product_id, quantity, price) 
                    VALUES (:user_id, :product_id, :quantity, :price)";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':user_id' => $user_id,
                ':product_id' => $product_id,
                ':quantity' => $quantity,
                ':price' => $product['price']
            ]);
        }
        
        if ($result) {
            return ['success' => true, 'message' => 'Item added to cart'];
        }
        
        return ['success' => false, 'message' => 'Failed to add item to cart'];
    }
    
    public function getCartItems($user_id) {
        $sql = "SELECT c.*, p.name, p.slug, p.featured_image, p.stock_quantity
                FROM store_cart c
                INNER JOIN store_products p ON c.product_id = p.id
                WHERE c.user_id = :user_id
                ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $subtotal = 0;
        foreach ($items as &$item) {
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $subtotal += $item['subtotal'];
        }
        
        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'count' => count($items)
        ];
    }
    
    public function updateQuantity($user_id, $item_id, $quantity) {
        if ($quantity <= 0) {
            return $this->removeItem($user_id, $item_id);
        }
        
        $sql = "UPDATE store_cart SET quantity = :quantity, updated_at = NOW() 
                WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':quantity' => $quantity,
            ':id' => $item_id,
            ':user_id' => $user_id
        ]);
        
        return ['success' => $result, 'message' => $result ? 'Cart updated' : 'Update failed'];
    }
    
    public function removeItem($user_id, $item_id) {
        $sql = "DELETE FROM store_cart WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':id' => $item_id, ':user_id' => $user_id]);
        
        return ['success' => $result, 'message' => $result ? 'Item removed' : 'Remove failed'];
    }
    
    public function clearCart($user_id) {
        $sql = "DELETE FROM store_cart WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':user_id' => $user_id]);
    }
    
    public function saveForLater($user_id, $product_id) {
        // First remove from cart
        $sql = "DELETE FROM store_cart WHERE user_id = :user_id AND product_id = :product_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id, ':product_id' => $product_id]);
        
        // Add to saved for later
        $sql = "INSERT INTO store_saved_for_later (user_id, product_id) 
                VALUES (:user_id, :product_id)";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':user_id' => $user_id, ':product_id' => $product_id]);
        
        return ['success' => $result, 'message' => $result ? 'Saved for later' : 'Failed to save'];
    }
    
    public function moveToCart($user_id, $product_id) {
        // Remove from saved
        $sql = "DELETE FROM store_saved_for_later WHERE user_id = :user_id AND product_id = :product_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id, ':product_id' => $product_id]);
        
        // Add to cart with quantity 1
        return $this->addItem($user_id, $product_id, 1);
    }
    
    public function getSavedItems($user_id) {
        $sql = "SELECT s.*, p.name, p.slug, p.featured_image, p.price, p.compare_price
                FROM store_saved_for_later s
                INNER JOIN store_products p ON s.product_id = p.id
                WHERE s.user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCartCount($user_id) {
        try {
            $sql = "SELECT COALESCE(SUM(quantity), 0) as total FROM store_cart WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (Exception $e) {
            error_log('getCartCount SQL error: ' . $e->getMessage());
            return 0;
        }
    }
    
}

?>