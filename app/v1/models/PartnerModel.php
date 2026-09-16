<?php
// app/v1/models/PartnerModel.php

namespace Models;

use PDO;
use PDOException;

class PartnerModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(bool $activeOnly = false): array
    {
        try {
            $sql = 'SELECT * FROM partners';
            if ($activeOnly) {
                $sql .= ' WHERE is_active = 1';
            }
            $sql .= ' ORDER BY sort_order ASC, id ASC';
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('PartnerModel::getAll error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM partners WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('PartnerModel::getById error: ' . $e->getMessage());
            return null;
        }
    }

    public function create(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO partners (name, logo, website_url, sort_order, is_active)
                 VALUES (:name, :logo, :website_url, :sort_order, :is_active)'
            );
            $stmt->execute([
                ':name' => trim($data['name'] ?? ''),
                ':logo' => trim($data['logo'] ?? ''),
                ':website_url' => trim($data['website_url'] ?? '') ?: null,
                ':sort_order' => (int) ($data['sort_order'] ?? 0),
                ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('PartnerModel::create error: ' . $e->getMessage());
            return 0;
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $fields = [
                'name = :name',
                'website_url = :website_url',
                'sort_order = :sort_order',
                'is_active = :is_active',
            ];
            $params = [
                ':id' => $id,
                ':name' => trim($data['name'] ?? ''),
                ':website_url' => trim($data['website_url'] ?? '') ?: null,
                ':sort_order' => (int) ($data['sort_order'] ?? 0),
                ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ];

            if (!empty($data['logo'])) {
                $fields[] = 'logo = :logo';
                $params[':logo'] = trim($data['logo']);
            }

            $sql = 'UPDATE partners SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('PartnerModel::update error: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM partners WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log('PartnerModel::delete error: ' . $e->getMessage());
            return false;
        }
    }

    public function toggleActive(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE partners SET is_active = 1 - is_active WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log('PartnerModel::toggleActive error: ' . $e->getMessage());
            return false;
        }
    }
}
