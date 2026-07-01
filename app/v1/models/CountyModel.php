<?php
// app/controllers/CountyController.php
require_once __DIR__ . '/../models/CountyModel.php';

class CountyController {
    private $counties;

    public function __construct($pdo) { $this->counties = new CountyModel($pdo); }

    public function model(): CountyModel { return $this->counties; }

    public function all(bool $withCounts = false): array { return $this->counties->getAll($withCounts); }
    public function find(int $id): ?array { return $this->counties->getById($id); }
    public function findBySlug(string $slug): ?array { return $this->counties->getBySlug($slug); }
    public function countries(): array { return $this->counties->getCountries(); }

    public function create(array $data): int { return $this->counties->create($data); }
    public function update(int $id, array $data): bool { return $this->counties->update($id, $data); }
    public function delete(int $id): bool { return $this->counties->delete($id); }
}