<?php
require_once __DIR__ . '/../models/CartModel.php';

class CartController {
    private $cartModel;
    
    public function __construct($pdo) {
        $this->cartModel = new CartModel($pdo);
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
        return $this->cartModel->addItem($product_id, $quantity);
    }
    
    public function getCart() {
        return $this->cartModel->getCartItems();
    }
    
    public function updateQuantity($item_id, $quantity) {
        return $this->cartModel->updateQuantity($item_id, $quantity);
    }
    
    public function removeFromCart($item_id) {
        return $this->cartModel->removeItem($item_id);
    }
    
    public function clearCart() {
        return $this->cartModel->clearCart();
    }
    
    public function saveForLater($product_id) {
        return $this->cartModel->saveForLater($product_id);
    }
    
    public function moveToCart($product_id) {
        return $this->cartModel->moveToCart($product_id);
    }
    
    public function getSavedItems() {
        return $this->cartModel->getSavedItems();
    }
    
    public function getCartCount() {
        $cart = $this->getCart();
        return $cart['count'];
    }
    
    public function getCartTotal() {
        $cart = $this->getCart();
        return $cart['total'];
    }
    
    public function mergeCartOnLogin($userId) {
        return $this->cartModel->mergeCartOnLogin($userId);
    }
}
?>