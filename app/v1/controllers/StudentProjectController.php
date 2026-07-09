<?php
require_once __DIR__ . '/../models/ProjectModel.php';

class StudentProjectController {
    private ProjectModel $projectModel;

    public function __construct($pdo) {
        $this->projectModel = new ProjectModel($pdo);
    }

    public function getStudentCategoryId(): ?int {
        $category = $this->projectModel->getCategoryBySlug('student-projects');
        return $category ? (int) $category['id'] : null;
    }

    public function createSubmission(array $reg, array $data, array $files): array {
        if (empty($reg['can_post_project'])) {
            return ['success' => false, 'message' => 'Your tutor has not enabled project submissions yet.'];
        }

        $categoryId = $this->getStudentCategoryId();
        if (!$categoryId) {
            return ['success' => false, 'message' => 'Student Projects category is not configured. Contact admin.'];
        }

        if (empty($data['project_title']) || empty($data['details'])) {
            return ['success' => false, 'message' => 'Project title and details are required.'];
        }

        if (empty($data['year_of_study'])) {
            return ['success' => false, 'message' => 'Year of study is required.'];
        }

        $cover = $this->uploadImage($files['cover_image'] ?? null, 'projects/student-covers');
        if (!$cover['success']) {
            return ['success' => false, 'message' => $cover['message']];
        }

        $projectId = $this->projectModel->createStudentProject([
            'category_id' => $categoryId,
            'small_title' => trim($data['project_title']),
            'major_title' => trim($reg['full_name']),
            'description' => trim($data['details']),
            'cover_image' => $cover['path'],
            'status' => 'draft',
            'created_by' => (int) $reg['user_id'],
            'student_name' => trim($reg['full_name']),
            'project_link' => trim($data['project_link'] ?? '') ?: null,
            'skillset' => trim($data['skillset'] ?? '') ?: null,
            'year_of_study' => trim($data['year_of_study']),
            'registration_id' => (int) $reg['id'],
        ]);

        if (!$projectId) {
            return ['success' => false, 'message' => 'Failed to save your project.'];
        }

        if (!empty($files['gallery_images'])) {
            $galleryFiles = $this->normalizeGalleryFiles($files['gallery_images']);
            foreach ($galleryFiles as $file) {
                $uploaded = $this->uploadImage($file, 'projects/student-gallery');
                if ($uploaded['success']) {
                    $this->projectModel->addGalleryImage($projectId, $uploaded['path']);
                }
            }
        }

        if (!empty($data['skillset'])) {
            $tags = array_map('trim', explode(',', $data['skillset']));
            $this->projectModel->addProjectTags($projectId, $tags);
        }

        return [
            'success' => true,
            'message' => 'Project submitted successfully. Your tutor will review it before it appears publicly.',
            'project_id' => $projectId,
        ];
    }

    public function approve(int $projectId, int $tutorId): array {
        if (!$this->projectModel->tutorCanManageStudentProject($projectId, $tutorId)) {
            return ['success' => false, 'message' => 'You are not allowed to manage this project.'];
        }

        if ($this->projectModel->publishProject($projectId)) {
            return ['success' => true, 'message' => 'Project approved and published on the projects page.'];
        }

        return ['success' => false, 'message' => 'Failed to approve project.'];
    }

    public function reject(int $projectId, int $tutorId): array {
        if (!$this->projectModel->tutorCanManageStudentProject($projectId, $tutorId)) {
            return ['success' => false, 'message' => 'You are not allowed to manage this project.'];
        }

        if ($this->projectModel->deleteProject($projectId)) {
            return ['success' => true, 'message' => 'Project submission removed.'];
        }

        return ['success' => false, 'message' => 'Failed to remove project.'];
    }

    private function uploadImage(?array $file, string $subdir): array {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Project image is required.'];
        }

        if (!function_exists('public_path')) {
            require_once __DIR__ . '/../config/paths.php';
        }
        if (!function_exists('upload_image')) {
            require_once __DIR__ . '/../helpers/uploads.php';
        }

        $result = upload_image($file, public_path('uploads/' . $subdir), 'uploads/' . $subdir);
        if (!$result['ok']) {
            return ['success' => false, 'message' => $result['error'] ?? 'Image upload failed.'];
        }

        return ['success' => true, 'path' => url($result['path'])];
    }

    private function normalizeGalleryFiles(array $files): array {
        if (!is_array($files['name'] ?? null)) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $normalized[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i] ?? 0,
            ];
        }

        return $normalized;
    }
}
