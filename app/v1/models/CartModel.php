<?php
class CartModel {
    private $db;
    private $sessionId;
    
    public function __construct($pdo) {
        $this->db = $pdo;
        $this->sessionId = $this->getOrCreateSessionId();
    }
    
    private function getOrCreateSessionId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['cart_session_id'])) {
            return $_SESSION['cart_session_id'];
        }
        
        $sessionId = session_id() . '_' . uniqid();
        
        // Save to database
        $sql = "INSERT INTO cart_sessions (session_id, user_id) VALUES (:session_id, :user_id)";
        $stmt = $this->db->prepare($sql);
        $userId = $_SESSION['user_id'] ?? null;
        $stmt->execute([':session_id' => $sessionId, ':user_id' => $userId]);
        
        $_SESSION['cart_session_id'] = $sessionId;
        return $sessionId;
    }
    
    public function getCartSessionId() {
        return $this->sessionId;
    }
    
    public function addItem($productId, $quantity = 1) {
        // Get product price
        $sql = "SELECT price FROM products WHERE id = :id AND status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        // Check if item already in cart
        $sql = "SELECT id, quantity FROM cart_items WHERE cart_session_id = :session_id AND product_id = :product_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':session_id' => $this->sessionId,
            ':product_id' => $productId
        ]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update quantity
            $newQuantity = $existing['quantity'] + $quantity;
            $sql = "UPDATE cart_items SET quantity = :quantity, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([':quantity' => $newQuantity, ':id' => $existing['id']]);
        } else {
            // Add new item
            $sql = "INSERT INTO cart_items (cart_session_id, product_id, quantity, price) 
                    VALUES (:session_id, :product_id, :quantity, :price)";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':session_id' => $this->sessionId,
                ':product_id' => $productId,
                ':quantity' => $quantity,
                ':price' => $product['price']
            ]);
        }
        
        if ($result) {
            return ['success' => true, 'message' => 'Item added to cart'];
        }
        
        return ['success' => false, 'message' => 'Failed to add item to cart'];
    }
    
    public function getCartItems() {
        $sql = "SELECT ci.*, p.name, p.slug, p.featured_image, p.stock_quantity
                FROM cart_items ci
                INNER JOIN products p ON ci.product_id = p.id
                WHERE ci.cart_session_id = :session_id
                ORDER BY ci.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':session_id' => $this->sessionId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = 0;
        foreach ($items as &$item) {
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $total += $item['subtotal'];
        }
        
        return [
            'items' => $items,
            'total' => $total,
            'count' => count($items)
        ];
    }
    
    public function updateQuantity($itemId, $quantity) {
        if ($quantity <= 0) {
            return $this->removeItem($itemId);
        }
        
        $sql = "UPDATE cart_items SET quantity = :quantity, updated_at = NOW() 
                WHERE id = :id AND cart_session_id = :session_id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':quantity' => $quantity,
            ':id' => $itemId,
            ':session_id' => $this->sessionId
        ]);
        
        return ['success' => $result, 'message' => $result ? 'Cart updated' : 'Update failed'];
    }
    
    public function removeItem($itemId) {
        $sql = "DELETE FROM cart_items WHERE id = :id AND cart_session_id = :session_id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':id' => $itemId, ':session_id' => $this->sessionId]);
        
        return ['success' => $result, 'message' => $result ? 'Item removed' : 'Remove failed'];
    }
    
    public function clearCart() {
        $sql = "DELETE FROM cart_items WHERE cart_session_id = :session_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':session_id' => $this->sessionId]);
    }
    
    public function saveForLater($productId) {
        // First remove from cart
        $sql = "DELETE FROM cart_items WHERE cart_session_id = :session_id AND product_id = :product_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':session_id' => $this->sessionId, ':product_id' => $productId]);
        
        // Add to saved for later
        $sql = "INSERT INTO saved_for_later (cart_session_id, product_id) 
                VALUES (:session_id, :product_id)";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':session_id' => $this->sessionId, ':product_id' => $productId]);
        
        return ['success' => $result, 'message' => $result ? 'Saved for later' : 'Failed to save'];
    }
    
    public function moveToCart($productId) {
        // Remove from saved
        $sql = "DELETE FROM saved_for_later WHERE cart_session_id = :session_id AND product_id = :product_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':session_id' => $this->sessionId, ':product_id' => $productId]);
        
        // Add to cart
        return $this->addItem($productId, 1);
    }
    
    public function getSavedItems() {
        $sql = "SELECT s.*, p.name, p.slug, p.featured_image, p.price
                FROM saved_for_later s
                INNER JOIN products p ON s.product_id = p.id
                WHERE s.cart_session_id = :session_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':session_id' => $this->sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function mergeCartOnLogin($userId) {
        // Update cart session with user_id
        $sql = "UPDATE cart_sessions SET user_id = :user_id WHERE session_id = :session_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':session_id' => $this->sessionId]);
        
        // Check if user has existing cart from another session
        $sql = "SELECT id FROM cart_sessions WHERE user_id = :user_id AND session_id != :session_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':session_id' => $this->sessionId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Merge carts
            $sql = "UPDATE cart_items SET cart_session_id = :new_session 
                    WHERE cart_session_id = :old_session";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':new_session' => $this->sessionId,
                ':old_session' => $existing['id']
            ]);
            
            // Delete old session
            $sql = "DELETE FROM cart_sessions WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $existing['id']]);
        }
    }
}
?>