<?php
namespace Models;

class CertificateModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getCertificates($filters = []) {
        $query = "
            SELECT c.*, 
                   r.user_id, r.course_id as reg_course, r.full_name,
                   u.email,
                   cr.title as course_name
            FROM certificates c
            JOIN registrations r ON c.registration_id = r.id
            LEFT JOIN users u ON r.user_id = u.id
            JOIN courses cr ON r.course_id = cr.id
            ORDER BY c.created_at DESC
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCertificateById($id) {
        $query = "
            SELECT c.*, 
                   r.user_id, r.course_id as reg_course, r.full_name,
                   u.email,
                   cr.title as course_name
            FROM certificates c
            JOIN registrations r ON c.registration_id = r.id
            LEFT JOIN users u ON r.user_id = u.id
            JOIN courses cr ON r.course_id = cr.id
            WHERE c.id = ?
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function createCertificate($data) {
        $query = "
            INSERT INTO certificates (
                registration_id, course_id, certificate_no, qr_code_hash, status, 
                snapshot_name, snapshot_course, snapshot_duration_months, snapshot_dates,
                issued_at
            ) VALUES (
                :registration_id, :course_id, :certificate_no, :qr_code_hash, :status,
                :snapshot_name, :snapshot_course, :snapshot_duration_months, :snapshot_dates,
                :issued_at
            )
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':registration_id' => $data['registration_id'],
            ':course_id' => $data['course_id'],
            ':certificate_no' => $data['certificate_no'],
            ':qr_code_hash' => $data['qr_code_hash'] ?? bin2hex(random_bytes(16)),
            ':status' => $data['status'] ?? 'requested',
            ':snapshot_name' => $data['snapshot_name'],
            ':snapshot_course' => $data['snapshot_course'],
            ':snapshot_duration_months' => $data['snapshot_duration_months'] ?? 1,
            ':snapshot_dates' => $data['snapshot_dates'] ?? '',
            ':issued_at' => $data['issued_at'] ?? null,
        ]);
        return $this->db->lastInsertId();
    }
    
    public function updateCertificate($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
        if (empty($fields)) return true;
        
        $query = "UPDATE certificates SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function getCompletedRegistrationsWithoutCertificate() {
        // Find approved students who don't have a certificate yet.
        // For now, we'll just allow any approved registration.
        $query = "
            SELECT r.*, c.title as course_name
            FROM registrations r
            JOIN users u ON r.user_id = u.id
            JOIN courses c ON r.course_id = c.id
            LEFT JOIN certificates cert ON r.id = cert.registration_id
            WHERE r.status = 'approved' AND cert.id IS NULL
            ORDER BY r.created_at DESC
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
