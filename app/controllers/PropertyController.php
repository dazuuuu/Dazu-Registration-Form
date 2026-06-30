<?php
// app/controllers/PropertyController.php
require_once __DIR__ . '/../models/PropertyModel.php';

class PropertyController {
    private $pdo;
    private $properties;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->properties = new PropertyModel($pdo);
    }

    /** Expose the model for anything not wrapped here. */
    public function model(): PropertyModel { return $this->properties; }

    // ---- reads ----
    public function paginate(array $filters = [], int $limit = 30, int $offset = 0): array {
        return $this->properties->getAll($filters, $limit, $offset);
    }
    public function featured(int $n = 6): array { return $this->properties->getFeatured($n); }
    public function findBySlug(string $slug): ?array { return $this->properties->getBySlug($slug); }
    public function find(int $id): ?array { return $this->properties->getById($id); }
    public function mapPoints(array $f = []): array { return $this->properties->getForMap($f); }

    /**
     * Create a property plus its gallery + videos in one transaction.
     * @param array $data        scalar fields for properties row
     * @param array $galleryPaths stored web paths (already uploaded)
     * @param array $videoUrls    raw URLs
     */
    public function create(array $data, array $galleryPaths = [], array $videoUrls = []): int {
        $this->pdo->beginTransaction();
        try {
            $id = $this->properties->create($data);
            $i = 0;
            foreach ($galleryPaths as $p) { if ($p) $this->properties->addImage($id, $p, $i++); }
            if (!empty($videoUrls)) $this->properties->setVideos($id, $videoUrls);
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update a property. New gallery images are appended; videos replace the set
     * only when $videoUrls is provided (null = leave as-is).
     */
    public function update(int $id, array $data, array $newGalleryPaths = [], ?array $videoUrls = null): bool {
        $this->pdo->beginTransaction();
        try {
            $ok = $this->properties->update($id, $data);
            $base = 1000;
            foreach ($newGalleryPaths as $p) { if ($p) $this->properties->addImage($id, $p, $base++); }
            if ($videoUrls !== null) $this->properties->setVideos($id, $videoUrls);
            $this->pdo->commit();
            return $ok;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool {
        // Remove image files from disk first (best-effort), then the DB rows.
        if (function_exists('delete_property_image')) {
            foreach ($this->properties->getImages($id) as $img) {
                delete_property_image($img['image_path']);
            }
            $p = $this->properties->getById($id);
            if ($p && !empty($p['cover_image'])) delete_property_image($p['cover_image']);
        }
        return $this->properties->delete($id);
    }

    // ---- sold ----
    public function markSold(int $id): bool { return $this->properties->markSold($id); }
    public function markAvailable(int $id): bool { return $this->properties->markAvailable($id); }

    // ---- gallery ----
    public function addImage(int $id, string $path, int $sort = 0): int { return $this->properties->addImage($id, $path, $sort); }
    public function removeImage(int $imageId, int $propertyId): bool {
        if (function_exists('delete_property_image')) {
            foreach ($this->properties->getImages($propertyId) as $img) {
                if ((int) $img['id'] === $imageId) { delete_property_image($img['image_path']); break; }
            }
        }
        return $this->properties->deleteImage($imageId, $propertyId);
    }
}