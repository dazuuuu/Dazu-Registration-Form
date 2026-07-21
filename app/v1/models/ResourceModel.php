<?php
// app/v1/models/ResourceModel.php
namespace Models;

class ResourceModel {
    private \PDO $db;

    public function __construct(\PDO $pdo) {
        $this->db = $pdo;
    }

    public function getProfile(int $userId): ?array {
        $stmt = $this->db->prepare(
            "SELECT user_id, first_name, last_name, phone, profile_photo_path,
                    profile_photo_submitted_at, profile_photo_status
             FROM user_profiles WHERE user_id = :uid"
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array{ok:bool, error?:string}
     */
    public function submitProfilePhoto(int $userId, array $file): array {
        if (!function_exists('upload_image')) {
            require_once app_path('helpers/uploads.php');
        }
        $absDir = public_path('uploads/profile_photos');
        $result = upload_image($file, $absDir, 'uploads/profile_photos');
        if (!$result['ok']) {
            return $result;
        }

        $stmt = $this->db->prepare(
            "UPDATE user_profiles
             SET profile_photo_path = :path, profile_photo_submitted_at = NOW(), profile_photo_status = 'pending'
             WHERE user_id = :uid"
        );
        $stmt->execute([':path' => $result['path'], ':uid' => $userId]);

        return ['ok' => true];
    }

    public function listPendingPhotos(): array {
        $stmt = $this->db->query(
            "SELECT up.user_id, up.first_name, up.last_name, up.profile_photo_path,
                    up.profile_photo_submitted_at, up.profile_photo_status,
                    u.email, u.username, r.role_name
             FROM user_profiles up
             JOIN users u ON u.id = up.user_id
             JOIN roles r ON r.id = u.role_id
             WHERE up.profile_photo_status = 'pending'
             ORDER BY up.profile_photo_submitted_at ASC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function listSubmittedPhotos(string $status = ''): array {
        $sql = "SELECT up.user_id, up.first_name, up.last_name, up.profile_photo_path,
                       up.profile_photo_submitted_at, up.profile_photo_status,
                       u.email, u.username, r.role_name
                FROM user_profiles up
                JOIN users u ON u.id = up.user_id
                JOIN roles r ON r.id = u.role_id
                WHERE up.profile_photo_path IS NOT NULL AND up.profile_photo_path != ''";
        if ($status !== '') {
            $sql .= " AND up.profile_photo_status = " . $this->db->quote($status);
        }
        $sql .= " ORDER BY up.profile_photo_submitted_at DESC";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function updatePhotoStatus(int $userId, string $status): bool {
        if (!in_array($status, ['approved', 'rejected', 'pending'], true)) {
            return false;
        }
        $stmt = $this->db->prepare(
            "UPDATE user_profiles SET profile_photo_status = :status WHERE user_id = :uid"
        );
        return $stmt->execute([':status' => $status, ':uid' => $userId]);
    }

    /**
     * @return array{ok:bool, error?:string, id?:int}
     */
    public function uploadDocument(int $userId, string $docType, array $file, int $adminId, ?int $registrationId = null): array {
        if (!in_array($docType, ['id_card', 'certificate'], true)) {
            return ['ok' => false, 'error' => 'Invalid document type.'];
        }
        if (!function_exists('upload_pdf')) {
            require_once app_path('helpers/uploads.php');
        }
        $subdir = $docType === 'id_card' ? 'id_cards' : 'certificates';
        $absDir = public_path('uploads/resources/' . $subdir);
        $result = upload_pdf($file, $absDir, 'uploads/resources/' . $subdir);
        if (!$result['ok']) {
            return $result;
        }

        $existing = $this->getDocument($userId, $docType, $registrationId);
        if ($existing) {
            $stmt = $this->db->prepare(
                "UPDATE user_documents SET file_path = :path, uploaded_by = :admin, registration_id = :rid, updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                ':path'  => $result['path'],
                ':admin' => $adminId,
                ':rid'   => $registrationId,
                ':id'    => $existing['id'],
            ]);
            return ['ok' => true, 'id' => (int) $existing['id']];
        }

        $title = $docType === 'id_card' ? 'Student ID Card' : 'Certificate';
        $stmt = $this->db->prepare(
            "INSERT INTO user_documents (user_id, registration_id, doc_type, file_path, title, uploaded_by)
             VALUES (:uid, :rid, :type, :path, :title, :admin)"
        );
        $stmt->execute([
            ':uid'   => $userId,
            ':rid'   => $registrationId,
            ':type'  => $docType,
            ':path'  => $result['path'],
            ':title' => $title,
            ':admin' => $adminId,
        ]);
        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    public function getDocument(int $userId, string $docType, ?int $registrationId = null): ?array {
        if ($registrationId) {
            $stmt = $this->db->prepare(
                "SELECT * FROM user_documents
                 WHERE user_id = :uid AND doc_type = :type AND registration_id = :rid
                 ORDER BY updated_at DESC LIMIT 1"
            );
            $stmt->execute([':uid' => $userId, ':type' => $docType, ':rid' => $registrationId]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM user_documents
                 WHERE user_id = :uid AND doc_type = :type
                 ORDER BY updated_at DESC LIMIT 1"
            );
            $stmt->execute([':uid' => $userId, ':type' => $docType]);
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listDocumentsForUser(int $userId, ?int $registrationId = null): array {
        if ($registrationId) {
            $stmt = $this->db->prepare(
                "SELECT * FROM user_documents
                 WHERE user_id = :uid AND (registration_id = :rid OR registration_id IS NULL)
                 ORDER BY doc_type, updated_at DESC"
            );
            $stmt->execute([':uid' => $userId, ':rid' => $registrationId]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM user_documents WHERE user_id = :uid ORDER BY doc_type, updated_at DESC"
            );
            $stmt->execute([':uid' => $userId]);
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDocumentById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM user_documents WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
