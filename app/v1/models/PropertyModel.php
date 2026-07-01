<?php
// app/models/PropertyModel.php

class PropertyModel {
    private $db;

    public function __construct($pdo) { $this->db = $pdo; }

    public function generateSlug(string $title, ?int $ignoreId = null): string {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
        if ($base === '') $base = 'property';
        $slug = $base; $n = 2;
        while (true) {
            $sql = "SELECT COUNT(*) FROM properties WHERE slug = :s" . ($ignoreId ? " AND id <> :id" : "");
            $st = $this->db->prepare($sql);
            $st->bindValue(':s', $slug);
            if ($ignoreId) $st->bindValue(':id', $ignoreId, PDO::PARAM_INT);
            $st->execute();
            if ((int) $st->fetchColumn() === 0) break;
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }

    private function clean($v) { return ($v ?? '') !== '' ? $v : null; }

    public function create(array $d): int {
        $st = $this->db->prepare(
            "INSERT INTO properties
             (title, slug, type, is_featured, is_hot_offer, cover_image, country, county_id,
              location_text, bedrooms, price, description, latitude, longitude, status)
             VALUES
             (:title, :slug, :type, :feat, :hot, :cover, :country, :county,
              :loc, :beds, :price, :desc, :lat, :lng, :status)"
        );
        $st->execute([
            ':title'   => trim($d['title']),
            ':slug'    => $this->generateSlug($d['title']),
            ':type'    => in_array($d['type'] ?? '', ['residential', 'commercial'], true) ? $d['type'] : 'residential',
            ':feat'    => !empty($d['is_featured']) ? 1 : 0,
            ':hot'     => !empty($d['is_hot_offer']) ? 1 : 0,
            ':cover'   => $this->clean($d['cover_image'] ?? null),
            ':country' => trim($d['country'] ?? 'Kenya'),
            ':county'  => $this->clean($d['county_id'] ?? null),
            ':loc'     => $this->clean($d['location_text'] ?? null),
            ':beds'    => $this->clean($d['bedrooms'] ?? null),
            ':price'   => $this->clean($d['price'] ?? null),
            ':desc'    => $this->clean($d['description'] ?? null),
            ':lat'     => $this->clean($d['latitude'] ?? null),
            ':lng'     => $this->clean($d['longitude'] ?? null),
            ':status'  => in_array($d['status'] ?? '', ['published', 'draft'], true) ? $d['status'] : 'published',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $d): bool {
        $st = $this->db->prepare(
            "UPDATE properties SET
                title = :title, slug = :slug, type = :type, is_featured = :feat, is_hot_offer = :hot,
                cover_image = :cover, country = :country, county_id = :county, location_text = :loc,
                bedrooms = :beds, price = :price, description = :desc, latitude = :lat, longitude = :lng,
                status = :status
             WHERE id = :id"
        );
        return $st->execute([
            ':title'   => trim($d['title']),
            ':slug'    => $this->generateSlug($d['title'], $id),
            ':type'    => in_array($d['type'] ?? '', ['residential', 'commercial'], true) ? $d['type'] : 'residential',
            ':feat'    => !empty($d['is_featured']) ? 1 : 0,
            ':hot'     => !empty($d['is_hot_offer']) ? 1 : 0,
            ':cover'   => $this->clean($d['cover_image'] ?? null),
            ':country' => trim($d['country'] ?? 'Kenya'),
            ':county'  => $this->clean($d['county_id'] ?? null),
            ':loc'     => $this->clean($d['location_text'] ?? null),
            ':beds'    => $this->clean($d['bedrooms'] ?? null),
            ':price'   => $this->clean($d['price'] ?? null),
            ':desc'    => $this->clean($d['description'] ?? null),
            ':lat'     => $this->clean($d['latitude'] ?? null),
            ':lng'     => $this->clean($d['longitude'] ?? null),
            ':status'  => in_array($d['status'] ?? '', ['published', 'draft'], true) ? $d['status'] : 'published',
            ':id'      => $id,
        ]);
    }

    public function delete(int $id): bool {
        // images/videos cascade via FK; delete explicitly too so it works without FK enforcement.
        $this->db->prepare("DELETE FROM property_images WHERE property_id = :id")->execute([':id' => $id]);
        $this->db->prepare("DELETE FROM property_videos WHERE property_id = :id")->execute([':id' => $id]);
        return $this->db->prepare("DELETE FROM properties WHERE id = :id")->execute([':id' => $id]);
    }

    public function getById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM properties WHERE id = :id");
        $st->execute([':id' => $id]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) return null;
        $p['images'] = $this->getImages($id);
        $p['videos'] = $this->getVideos($id);
        return $p;
    }

    public function getBySlug(string $slug): ?array {
        $st = $this->db->prepare(
            "SELECT p.*, c.name AS county_name, c.region AS county_region, c.country AS county_country
             FROM properties p LEFT JOIN counties c ON c.id = p.county_id
             WHERE p.slug = :s"
        );
        $st->execute([':s' => $slug]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) return null;
        $p['images'] = $this->getImages((int) $p['id']);
        $p['videos'] = $this->getVideos((int) $p['id']);
        return $p;
    }

    /**
     * Filterable listing.
     * $f keys: type, county_id, bedrooms, is_featured, is_hot_offer, status, search
     */
    public function getAll(array $f = [], int $limit = 30, int $offset = 0): array {
        $where = []; $args = [];
        if (!empty($f['status']))      { $where[] = "p.status = :status";        $args[':status'] = $f['status']; }
        if (!empty($f['type']))        { $where[] = "p.type = :type";            $args[':type'] = $f['type']; }
        if (!empty($f['county_id']))   { $where[] = "p.county_id = :cid";         $args[':cid'] = (int) $f['county_id']; }
        if (!empty($f['bedrooms']))    { $where[] = "p.bedrooms = :beds";         $args[':beds'] = (int) $f['bedrooms']; }
        if (!empty($f['is_featured'])) { $where[] = "p.is_featured = 1"; }
        if (!empty($f['is_hot_offer'])){ $where[] = "p.is_hot_offer = 1"; }
        if (array_key_exists('is_sold', $f) && $f['is_sold'] !== '' && $f['is_sold'] !== null) {
            $where[] = "p.is_sold = :sold"; $args[':sold'] = (int) $f['is_sold'];
        }
        if (!empty($f['search']))      { $where[] = "(p.title LIKE :q OR p.location_text LIKE :q)"; $args[':q'] = '%' . $f['search'] . '%'; }

        $sql = "SELECT p.*, c.name AS county_name, c.region AS county_region
                FROM properties p LEFT JOIN counties c ON c.id = p.county_id";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY p.is_hot_offer DESC, p.is_featured DESC, p.created_at DESC LIMIT :lim OFFSET :off";

        $st = $this->db->prepare($sql);
        foreach ($args as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFeatured(int $limit = 6): array {
        return $this->getAll(['status' => 'published', 'is_featured' => 1], $limit, 0);
    }

    /** Minimal rows for plotting markers (published + has coordinates). */
    public function getForMap(array $f = []): array {
        $where = ["p.status = 'published'", "p.latitude IS NOT NULL", "p.longitude IS NOT NULL"];
        $args = [];
        if (!empty($f['type']))      { $where[] = "p.type = :type";    $args[':type'] = $f['type']; }
        if (!empty($f['county_id'])) { $where[] = "p.county_id = :cid"; $args[':cid'] = (int) $f['county_id']; }
        $sql = "SELECT p.id, p.title, p.slug, p.type, p.price, p.bedrooms, p.cover_image,
                       p.latitude, p.longitude, p.location_text, p.is_sold, c.name AS county_name
                FROM properties p LEFT JOIN counties c ON c.id = p.county_id
                WHERE " . implode(' AND ', $where);
        $st = $this->db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function incrementViews(int $id): void {
        $this->db->prepare("UPDATE properties SET view_count = view_count + 1 WHERE id = :id")->execute([':id' => $id]);
    }

    /** Mark a property as sold (keeps it visible with a SOLD badge). */
    public function markSold(int $id): bool {
        return $this->db->prepare(
            "UPDATE properties SET is_sold = 1, sold_at = CURRENT_TIMESTAMP WHERE id = :id"
        )->execute([':id' => $id]);
    }

    /** Put a sold property back on the market. */
    public function markAvailable(int $id): bool {
        return $this->db->prepare(
            "UPDATE properties SET is_sold = 0, sold_at = NULL WHERE id = :id"
        )->execute([':id' => $id]);
    }

    // ---- gallery images ----
    public function getImages(int $propertyId): array {
        $st = $this->db->prepare("SELECT * FROM property_images WHERE property_id = :id ORDER BY sort_order, id");
        $st->execute([':id' => $propertyId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    public function addImage(int $propertyId, string $path, int $sort = 0): int {
        $st = $this->db->prepare("INSERT INTO property_images (property_id, image_path, sort_order) VALUES (:p, :path, :s)");
        $st->execute([':p' => $propertyId, ':path' => $path, ':s' => $sort]);
        return (int) $this->db->lastInsertId();
    }
    public function deleteImage(int $imageId, int $propertyId): bool {
        $st = $this->db->prepare("DELETE FROM property_images WHERE id = :i AND property_id = :p");
        return $st->execute([':i' => $imageId, ':p' => $propertyId]);
    }

    // ---- video urls ----
    public function getVideos(int $propertyId): array {
        $st = $this->db->prepare("SELECT * FROM property_videos WHERE property_id = :id ORDER BY sort_order, id");
        $st->execute([':id' => $propertyId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    public function setVideos(int $propertyId, array $urls): void {
        $this->db->prepare("DELETE FROM property_videos WHERE property_id = :id")->execute([':id' => $propertyId]);
        $ins = $this->db->prepare("INSERT INTO property_videos (property_id, url, sort_order) VALUES (:p, :u, :s)");
        $i = 0;
        foreach ($urls as $u) {
            $u = trim($u);
            if ($u === '') continue;
            $ins->execute([':p' => $propertyId, ':u' => $u, ':s' => $i++]);
        }
    }

    /** Convert a YouTube watch/share URL into an embeddable URL (for the details page). */
    public static function youtubeEmbed(string $url): ?string {
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        return null;
    }
}