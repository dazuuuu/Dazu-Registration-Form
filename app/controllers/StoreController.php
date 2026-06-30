<?php
require_once __DIR__ . '/../models/StoreCategoryModel.php';
require_once __DIR__ . '/../models/StoreProductModel.php';

class StoreController {
    private $categoryModel;
    private $productModel;
    
    public function __construct($pdo) {
        $this->categoryModel = new StoreCategoryModel($pdo);
        $this->productModel = new StoreProductModel($pdo);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    // Category methods
    public function createCategory($name, $description = null, $image = null) {
        if (empty($name)) {
            return ['success' => false, 'message' => 'Category name is required'];
        }
        
        $image_path = null;
        if ($image && $image['error'] === 0) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Realestate/public/uploads/store/categories/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($image['tmp_name'], $target_file)) {
                $image_path = '/Realestate/public/uploads/store/categories/' . $filename;
            }
        }
        
        $categoryId = $this->categoryModel->create($name, $description, $image_path);
        
        if ($categoryId) {
            return ['success' => true, 'message' => 'Category created successfully', 'id' => $categoryId];
        }
        
        return ['success' => false, 'message' => 'Failed to create category'];
    }
    
    public function getAllCategories($activeOnly = true) {
        return $this->categoryModel->getAll($activeOnly);
    }
    
    public function updateCategory($id, $data, $image = null) {
        $updateData = [];
        
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['sort_order'])) $updateData['sort_order'] = $data['sort_order'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];
        
        if ($image && $image['error'] === 0) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Realestate/public/uploads/store/categories/';
            $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($image['tmp_name'], $target_file)) {
                $updateData['image_path'] = '/Realestate/public/uploads/store/categories/' . $filename;
            }
        }
        
        $result = $this->categoryModel->update($id, $updateData);
        
        return ['success' => $result, 'message' => $result ? 'Category updated' : 'Update failed'];
    }
    
    public function deleteCategory($id) {
        return $this->categoryModel->delete($id);
    }
    
    // Product methods
    public function createProduct($data, $featured_image = null) {
        if (empty($data['name']) || empty($data['price'])) {
            return ['success' => false, 'message' => 'Name and price are required'];
        }
        
        $featured_image_path = null;
        if ($featured_image && $featured_image['error'] === 0) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Realestate/public/uploads/store/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($featured_image['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($featured_image['tmp_name'], $target_file)) {
                $featured_image_path = '/Realestate/public/uploads/store/products/' . $filename;
            }
        }
        
        $productData = [
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':price' => $data['price'],
            ':compare_price' => !empty($data['compare_price']) ? $data['compare_price'] : null,
            ':sku' => $data['sku'] ?? null,
            ':stock_quantity' => $data['stock_quantity'] ?? 0,
            ':category_id' => !empty($data['category_id']) ? $data['category_id'] : null,
            ':featured_image' => $featured_image_path,
            ':gallery_images' => null,
            ':status' => $data['status'] ?? 'draft',
            ':is_featured' => isset($data['is_featured']) ? 1 : 0,
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':sort_order' => $data['sort_order'] ?? 0,
            ':created_by' => $_SESSION['user_id'] ?? null
        ];
        
        $productId = $this->productModel->create($productData);
        
        if ($productId) {
            return ['success' => true, 'message' => 'Product created successfully', 'product_id' => $productId];
        }
        
        return ['success' => false, 'message' => 'Failed to create product'];
    }
    
    public function getAllProducts($status = 'active', $limit = 12, $offset = 0, $category_id = null, $sort = 'newest') {
        return $this->productModel->getAll($status, $limit, $offset, $category_id, $sort);
    }
    
    public function getProductDetails($identifier) {
        $product = is_numeric($identifier) ? 
                   $this->productModel->getById($identifier) : 
                   $this->productModel->getBySlug($identifier);
        
        if ($product) {
            $this->productModel->incrementViews($product['id']);
            $product['related_products'] = $this->productModel->getRelatedProducts($product['id'], $product['category_id']);
        }
        
        return $product;
    }
    
    public function updateProduct($id, $data, $featured_image = null) {
        $updateData = [];
        
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['price'])) $updateData['price'] = $data['price'];
        if (isset($data['compare_price'])) $updateData['compare_price'] = $data['compare_price'];
        if (isset($data['sku'])) $updateData['sku'] = $data['sku'];
        if (isset($data['stock_quantity'])) $updateData['stock_quantity'] = $data['stock_quantity'];
        if (isset($data['category_id'])) $updateData['category_id'] = $data['category_id'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['is_featured'])) $updateData['is_featured'] = $data['is_featured'] ? 1 : 0;
        if (isset($data['meta_title'])) $updateData['meta_title'] = $data['meta_title'];
        if (isset($data['meta_description'])) $updateData['meta_description'] = $data['meta_description'];
        if (isset($data['sort_order'])) $updateData['sort_order'] = $data['sort_order'];
        
        if ($featured_image && $featured_image['error'] === 0) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Realestate/public/uploads/store/products/';
            $ext = pathinfo($featured_image['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($featured_image['tmp_name'], $target_file)) {
                $updateData['featured_image'] = '/Realestate/public/uploads/store/products/' . $filename;
            }
        }
        
        $result = $this->productModel->update($id, $updateData);
        
        if ($result) {
            return ['success' => true, 'message' => 'Product updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update product'];
    }
    
    public function deleteProduct($id) {
        $result = $this->productModel->delete($id);
        
        if ($result) {
            return ['success' => true, 'message' => 'Product deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete product'];
    }
    
    public function getFeaturedProducts($limit = 8) {
        return $this->productModel->getFeatured($limit);
    }
    
    public function searchProducts($keyword) {
        return $this->productModel->search($keyword);
    }
    
    public function getCategoryProductCount($category_id) {
        return $this->categoryModel->getProductCount($category_id);
    }
}
?>