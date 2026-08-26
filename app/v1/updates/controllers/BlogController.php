<?php
// app/controllers/BlogController.php

require_once __DIR__ . '/../models/BlogModel.php';

class BlogController {
    private $blogModel;
    
    public function __construct($pdo) {
        $this->blogModel = new BlogModel($pdo);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    // Generate slug
    private function generateSlug($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
    
    // Create blog post
    public function create($data, $featured_image = null) {
        if (empty($data['title']) || empty($data['content'])) {
            return ['success' => false, 'message' => 'Title and content are required'];
        }
        
        $slug = $this->generateSlug($data['title']);
        
        // Check if slug exists
        $existing = $this->blogModel->getBySlug($slug);
        if ($existing) {
            $slug = $slug . '-' . time();
        }
        
        // Handle category_id (convert empty string to null)
        $category_id = !empty($data['category_id']) ? $data['category_id'] : null;
        
        $blogData = [
            ':title' => $data['title'],
            ':slug' => $slug,
            ':excerpt' => $data['excerpt'] ?? substr(strip_tags($data['content']), 0, 200),
            ':content' => $data['content'],
            ':featured_image' => $featured_image,
            ':category_id' => $category_id,
            ':author_id' => $_SESSION['user_id'],
            ':status' => $data['status'] ?? 'draft',
            ':is_featured' => !empty($data['is_featured']) ? 1 : 0,
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':meta_keywords' => $data['meta_keywords'] ?? null,
            ':published_at' => ($data['status'] ?? '') === 'published' ? date('Y-m-d H:i:s') : null
        ];
        
        $blogId = $this->blogModel->create($blogData);
        
        if ($blogId) {
            // Handle tags
            if (!empty($data['tags'])) {
                $tagIds = [];
                foreach ($data['tags'] as $tagName) {
                    $tagName = trim($tagName);
                    if (empty($tagName)) continue;
                    
                    $tagSlug = $this->generateSlug($tagName);
                    
                    // Check if tag exists
                    $allTags = $this->blogModel->getAllTags();
                    $existingTag = null;
                    foreach ($allTags as $tag) {
                        if ($tag['slug'] === $tagSlug) {
                            $existingTag = $tag;
                            break;
                        }
                    }
                    
                    if ($existingTag) {
                        $tagIds[] = $existingTag['id'];
                    } else {
                        $newTagId = $this->blogModel->createTag($tagName, $tagSlug);
                        $tagIds[] = $newTagId;
                    }
                }
                
                if (!empty($tagIds)) {
                    $this->blogModel->addTags($blogId, $tagIds);
                }
            }
            
            // Handle sections
            if (!empty($data['sections'])) {
                foreach ($data['sections'] as $index => $section) {
                    $this->addSection($blogId, $section, $index);
                }
            }
            
            // Handle FAQs
            if (!empty($data['faqs'])) {
                foreach ($data['faqs'] as $index => $faq) {
                    $this->addFaq($blogId, $faq, $index);
                }
            }
            
            return ['success' => true, 'message' => 'Blog post created successfully', 'blog_id' => $blogId];
        }
        
        return ['success' => false, 'message' => 'Failed to create blog post'];
    }
    
    // Get blog with all details
    public function getBlogDetails($identifier) {
        $blog = is_numeric($identifier) ? 
                $this->blogModel->getById($identifier) : 
                $this->blogModel->getBySlug($identifier);
        
        if ($blog) {
            $blog['sections'] = $this->blogModel->getSections($blog['id']);
            $blog['faqs'] = $this->blogModel->getFaqs($blog['id']);
            $blog['tags'] = $this->blogModel->getTags($blog['id']);
            $blog['related_posts'] = $this->blogModel->getRelatedPosts($blog['id'], $blog['category_id']);
        }
        
        return $blog;
    }
    
    // Update blog
    public function update($id, $data, $featured_image = null) {
        $updateData = [];
        
        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
            $updateData['slug'] = $this->generateSlug($data['title']);
        }
        
        if (isset($data['excerpt'])) $updateData['excerpt'] = $data['excerpt'];
        if (isset($data['content'])) $updateData['content'] = $data['content'];
        if ($featured_image) $updateData['featured_image'] = $featured_image;
        
        // Handle category_id (convert empty string to null)
        if (isset($data['category_id'])) {
            $updateData['category_id'] = !empty($data['category_id']) ? $data['category_id'] : null;
        }
        
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['is_featured'])) $updateData['is_featured'] = $data['is_featured'];
        if (isset($data['meta_title'])) $updateData['meta_title'] = $data['meta_title'];
        if (isset($data['meta_description'])) $updateData['meta_description'] = $data['meta_description'];
        if (isset($data['meta_keywords'])) $updateData['meta_keywords'] = $data['meta_keywords'];
        
        $result = $this->blogModel->update($id, $updateData);
        
        if (isset($data['tags'])) {
            // Update tags
            $this->blogModel->removeTags($id);
            
            $tagIds = [];
            foreach ($data['tags'] as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) continue;
                
                $tagSlug = $this->generateSlug($tagName);
                $allTags = $this->blogModel->getAllTags();
                $existingTag = null;
                foreach ($allTags as $tag) {
                    if ($tag['slug'] === $tagSlug) {
                        $existingTag = $tag;
                        break;
                    }
                }
                
                if ($existingTag) {
                    $tagIds[] = $existingTag['id'];
                } else {
                    $newTagId = $this->blogModel->createTag($tagName, $tagSlug);
                    $tagIds[] = $newTagId;
                }
            }
            
            if (!empty($tagIds)) {
                $this->blogModel->addTags($id, $tagIds);
            }
        }
        
        if ($result || isset($data['tags'])) {
            return ['success' => true, 'message' => 'Blog post updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update blog post'];
    }
    
    /** Uploads one file into uploads/blog/sections/ and returns its site-relative URL, or throws on failure. */
    private function uploadSectionFile(array $file): ?string {
        if ($file['error'] !== 0) {
            $messages = [
                UPLOAD_ERR_INI_SIZE   => 'Image exceeds the server upload size limit.',
                UPLOAD_ERR_FORM_SIZE  => 'Image exceeds the allowed upload size.',
                UPLOAD_ERR_PARTIAL    => 'Image was only partially uploaded — please try again.',
                UPLOAD_ERR_NO_FILE    => null, // no file chosen; not an error
            ];
            if (array_key_exists($file['error'], $messages)) {
                if ($messages[$file['error']] === null) return null;
                throw new RuntimeException($messages[$file['error']]);
            }
            throw new RuntimeException('Image upload failed (error code ' . $file['error'] . ').');
        }
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . url('uploads/blog/sections/');
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $ext;
        $target_file = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return url('uploads/blog/sections/') . $filename;
        }
        throw new RuntimeException('Could not save the uploaded image to the server.');
    }

    private function extractYoutubeId(string $url): ?string {
        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Builds the persisted field set for a block, handling per-type uploads/parsing.
     * $existing (when updating) supplies fallbacks so omitted fields (e.g. no new
     * image chosen) keep their previous value instead of being wiped to null.
     */
    private function buildSectionFields(array $data, ?array $existing = null): array {
        $type = $data['section_type'] ?? ($existing['section_type'] ?? 'paragraph');

        $fields = [
            'section_type'   => $type,
            'title'          => $existing['title'] ?? null,
            'content'        => $existing['content'] ?? null,
            'media_url'      => $existing['media_url'] ?? null,
            'gallery_images' => $existing['gallery_images'] ?? null,
            'media_type'     => $existing['media_type'] ?? 'image',
            'video_id'       => $existing['video_id'] ?? null,
            'link_label'     => $existing['link_label'] ?? null,
            'cta_type'       => $existing['cta_type'] ?? null,
            'cta_value'      => $existing['cta_value'] ?? null,
            'cta_label'      => $existing['cta_label'] ?? null,
            'faq_items'      => $existing['faq_items'] ?? null,
        ];

        switch ($type) {
            case 'heading':
                $fields['title'] = trim($data['title'] ?? '');
                break;

            case 'paragraph':
            case 'quote':
                $fields['content'] = $data['content'] ?? '';
                $fields['title'] = $data['title'] ?? null; // quote attribution
                break;

            case 'image':
                if (isset($data['media_file']) && is_array($data['media_file'])) {
                    $uploaded = $this->uploadSectionFile($data['media_file']);
                    if ($uploaded) {
                        $fields['media_url'] = $uploaded;
                    }
                }
                $fields['media_type'] = 'image';
                $fields['title'] = $data['title'] ?? null; // caption / alt text
                break;

            case 'image_gallery':
                $images = [];
                if (!empty($data['gallery_images_touched'])) {
                    // Client sent the explicit retained set (may be empty if all were removed)
                    if (isset($data['existing_gallery_images']) && is_array($data['existing_gallery_images'])) {
                        $images = array_values($data['existing_gallery_images']);
                    }
                } elseif (!empty($existing['gallery_images'])) {
                    $decoded = json_decode($existing['gallery_images'], true);
                    if (is_array($decoded)) $images = $decoded;
                }
                if (isset($data['gallery_files']) && is_array($data['gallery_files']['name'] ?? null)) {
                    $count = count($data['gallery_files']['name']);
                    for ($i = 0; $i < $count; $i++) {
                        if (($data['gallery_files']['error'][$i] ?? 1) !== 0) continue;
                        $file = [
                            'name'     => $data['gallery_files']['name'][$i],
                            'tmp_name' => $data['gallery_files']['tmp_name'][$i],
                            'error'    => $data['gallery_files']['error'][$i],
                        ];
                        $uploaded = $this->uploadSectionFile($file);
                        if ($uploaded) $images[] = $uploaded;
                    }
                }
                $fields['gallery_images'] = !empty($images) ? json_encode(array_values($images)) : null;
                $fields['media_type'] = 'image';
                $fields['title'] = $data['title'] ?? null; // gallery heading
                break;

            case 'youtube':
                $url = trim($data['media_url'] ?? '');
                if ($url !== '') {
                    $fields['media_url'] = $url;
                    $fields['video_id'] = $this->extractYoutubeId($url);
                }
                $fields['media_type'] = 'youtube';
                $fields['title'] = $data['title'] ?? null; // caption
                break;

            case 'link':
                $fields['media_url'] = trim($data['media_url'] ?? ($existing['media_url'] ?? ''));
                $fields['link_label'] = $data['link_label'] ?? null;
                break;

            case 'cta':
                $fields['cta_type'] = $data['cta_type'] ?? null;
                $fields['cta_value'] = trim($data['cta_value'] ?? '');
                $fields['cta_label'] = $data['cta_label'] ?? null;
                break;

            case 'faq':
                $questions = $data['faq_question'] ?? [];
                $answers = $data['faq_answer'] ?? [];
                $items = [];
                foreach ($questions as $i => $q) {
                    $q = trim($q);
                    $a = trim($answers[$i] ?? '');
                    if ($q === '' || $a === '') continue;
                    $items[] = ['question' => $q, 'answer' => $a];
                }
                $fields['faq_items'] = !empty($items) ? json_encode($items) : ($existing['faq_items'] ?? null);
                break;
        }

        return $fields;
    }

    // Add section
    public function addSection($blog_id, $data, $sort_order = 0) {
        $fields = $this->buildSectionFields($data);

        $sectionData = [':blog_id' => $blog_id, ':sort_order' => $sort_order];
        foreach ($fields as $key => $value) {
            $sectionData[":$key"] = $value;
        }

        return $this->blogModel->addSection($sectionData);
    }

    // Update section
    public function updateSection($section_id, $data) {
        $sections = $this->blogModel->getSections($data['blog_id'] ?? 0);
        $existing = null;
        foreach ($sections as $s) {
            if ((int) $s['id'] === (int) $section_id) { $existing = $s; break; }
        }

        $fields = $this->buildSectionFields($data, $existing);
        if (isset($data['sort_order'])) {
            $fields['sort_order'] = (int) $data['sort_order'];
        }

        return $this->blogModel->updateSection($section_id, $fields);
    }

    // Delete section
    public function deleteSection($section_id) {
        return $this->blogModel->deleteSection($section_id);
    }

    // Reorder sections: $order is [section_id => sort_order]
    public function reorderSections(array $order): bool {
        $ok = true;
        foreach ($order as $sectionId => $sortOrder) {
            if (!$this->blogModel->updateSection((int) $sectionId, ['sort_order' => (int) $sortOrder])) {
                $ok = false;
            }
        }
        return $ok;
    }
    
    // Add FAQ
    public function addFaq($blog_id, $data, $sort_order = 0) {
        $faqData = [
            ':blog_id' => $blog_id,
            ':question' => $data['question'],
            ':answer' => $data['answer'],
            ':sort_order' => $sort_order
        ];
        
        return $this->blogModel->addFaq($faqData);
    }
    
    // Update FAQ
    public function updateFaq($faq_id, $data) {
        return $this->blogModel->updateFaq($faq_id, $data);
    }
    
    // Delete FAQ
    public function deleteFaq($faq_id) {
        return $this->blogModel->deleteFaq($faq_id);
    }
    
    // Delete blog
    public function delete($id) {
        $blog = $this->blogModel->getById($id);
        if ($blog && $blog['featured_image']) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $blog['featured_image'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $result = $this->blogModel->delete($id);
        
        if ($result) {
            return ['success' => true, 'message' => 'Blog post deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete blog post'];
    }
    
    // Increment view count
    public function incrementViews($id) {
        return $this->blogModel->incrementViews($id);
    }
    
    // Get all blogs for listing
    public function getAllBlogs($status = 'published', $limit = 12, $offset = 0) {
        return $this->blogModel->getAll($status, $limit, $offset);
    }
    
    // Get categories
    public function getCategories() {
        return $this->blogModel->getAllCategories();
    }
    
// Create category (updated without created_by)
public function createCategory($name, $description = null, $color = '#667eea') {
    if (empty($name)) {
        return ['success' => false, 'message' => 'Category name is required'];
    }
    
    $slug = $this->generateSlug($name);
    
    // Check if category already exists
    $categories = $this->blogModel->getAllCategories();
    foreach ($categories as $cat) {
        if ($cat['slug'] === $slug) {
            return ['success' => false, 'message' => 'Category already exists'];
        }
    }
    
    $category_id = $this->blogModel->createCategory($name, $slug, $description, $color);
    
    if ($category_id) {
        return ['success' => true, 'message' => 'Category created successfully', 'category_id' => $category_id];
    }
    
    return ['success' => false, 'message' => 'Failed to create category'];
}
    
    // Update category
    public function updateCategory($id, $data) {
        $slug = $this->generateSlug($data['name']);
        $data['slug'] = $slug;
        
        if ($this->blogModel->updateCategory($id, $data)) {
            return ['success' => true, 'message' => 'Category updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update category'];
    }
    
    // Delete category
    public function deleteCategory($id) {
        if ($this->blogModel->deleteCategory($id)) {
            return ['success' => true, 'message' => 'Category deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete category'];
    }
    
    // Get all tags
    public function getAllTags() {
        return $this->blogModel->getAllTags();
    }
    
    // Get featured blogs
    public function getFeaturedBlogs($limit = 3) {
        return $this->blogModel->getFeatured($limit);
    }
    
    // Search blogs
    public function searchBlogs($keyword) {
        return $this->blogModel->search($keyword);
    }
}
?>