<?php
require_once __DIR__ . '/../models/StoreCartModel.php';

class StoreCartController {
    private $cartModel;
    
    public function __construct($pdo) {
        $this->cartModel = new StoreCartModel($pdo);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function addToCart($product_id, $quantity = 1) {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            return [
                'success' => false, 
                'message' => 'Please login to add items to cart',
                'require_login' => true
            ];
        }
        
        $quantity = max(1, intval($quantity));
        return $this->cartModel->addItem($_SESSION['user_id'], $product_id, $quantity);
    }
    
    public function getCart() {
        if (!isset($_SESSION['user_id'])) {
            return ['items' => [], 'subtotal' => 0, 'total' => 0, 'count' => 0];
        }
        
        return $this->cartModel->getCartItems($_SESSION['user_id']);
    }
    
    public function updateQuantity($item_id, $quantity) {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => 'Please login'];
        }
        
        return $this->cartModel->updateQuantity($_SESSION['user_id'], $item_id, $quantity);
    }
    
    public function removeFromCart($item_id) {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => 'Please login'];
        }
        
        return $this->cartModel->removeItem($_SESSION['user_id'], $item_id);
    }
    
    public function clearCart() {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => 'Please login'];
        }
        
        return $this->cartModel->clearCart($_SESSION['user_id']);
    }
    
    public function saveForLater($product_id) {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => 'Please login', 'require_login' => true];
        }
        
        return $this->cartModel->saveForLater($_SESSION['user_id'], $product_id);
    }
    
    public function moveToCart($product_id) {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => 'Please login', 'require_login' => true];
        }
        
        return $this->cartModel->moveToCart($_SESSION['user_id'], $product_id);
    }
    
    public function getSavedItems() {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }
        
        return $this->cartModel->getSavedItems($_SESSION['user_id']);
    }
    
    public function getCartCount() {
        if (!isset($_SESSION['user_id'])) {
            return 0;
        }
        
        try {
            return $this->cartModel->getCartCount($_SESSION['user_id']);
        } catch (Exception $e) {
            error_log('getCartCount error: ' . $e->getMessage());
            return 0;
        }
    }
}
?>