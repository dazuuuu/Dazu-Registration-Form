<?php
// app/v1/controllers/TestimonialController.php — updated submit with student portal + video

require_once __DIR__ . '/../models/TestimonialModel.php';
require_once __DIR__ . '/../helpers/testimonials.php';

class TestimonialController {
    private $testimonialModel;

    public function __construct($pdo) {
        $this->testimonialModel = new TestimonialModel($pdo);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function submit($data) {
        if (!testimonials_students_open()) {
            return ['success' => false, 'message' => 'Student testimonial submissions are currently closed.'];
        }

        $type = ($data['testimonial_type'] ?? 'text') === 'video' ? 'video' : 'text';
        $name = trim($data['customer_name'] ?? '');

        if ($name === '') {
            return ['success' => false, 'message' => 'Please provide your name.'];
        }

        if ($type === 'text' && trim($data['testimonial_text'] ?? '') === '') {
            return ['success' => false, 'message' => 'Please write your testimonial.'];
        }

        if ($type === 'video' && trim($data['video_url'] ?? '') === '') {
            return ['success' => false, 'message' => 'Please provide a YouTube or video link.'];
        }

        $text = trim($data['testimonial_text'] ?? '');
        if ($type === 'video' && $text === '') {
            $text = 'Video testimonial from ' . $name;
        }

        $rating = (int) ($data['rating'] ?? 5);
        if ($rating < 1 || $rating > 5) {
            $rating = 5;
        }

        $settings = testimonial_settings();
        $status = $settings['auto_publish'] ? 'approved' : 'pending';

        $testimonialData = [
            'customer_name' => htmlspecialchars(strip_tags($name)),
            'customer_email' => !empty($data['customer_email']) ? filter_var(trim($data['customer_email']), FILTER_SANITIZE_EMAIL) : null,
            'customer_phone' => !empty($data['customer_phone']) ? htmlspecialchars(strip_tags(trim($data['customer_phone']))) : null,
            'customer_initial' => testimonial_initials($name),
            'rating' => $rating,
            'testimonial_text' => htmlspecialchars(strip_tags($text)),
            'testimonial_type' => $type,
            'video_url' => $type === 'video' ? trim($data['video_url'] ?? '') : null,
            'video_provider' => $type === 'video' ? ($data['video_provider'] ?? 'youtube') : 'none',
            'service_tag' => !empty($data['service_tag']) ? htmlspecialchars(strip_tags(trim($data['service_tag']))) : null,
            'role' => !empty($data['role']) ? htmlspecialchars(strip_tags(trim($data['role']))) : null,
            'status' => $status,
        ];

        $testimonialId = $this->testimonialModel->create($testimonialData);

        if ($testimonialId) {
            $msg = $status === 'approved'
                ? 'Thank you! Your testimonial is now live on our homepage.'
                : 'Thank you! Your testimonial was submitted and will appear after admin review.';

            return ['success' => true, 'message' => $msg, 'testimonial_id' => $testimonialId];
        }

        return ['success' => false, 'message' => 'Failed to submit. Please try again.'];
    }

    public function getAll($status = null, $limit = null, $offset = 0) {
        return $this->testimonialModel->getAll($status, $limit, $offset);
    }

    public function getApproved($limit = null, $featured = false) {
        return $this->testimonialModel->getAll('approved', $limit, 0, $featured);
    }

    public function getById($id) {
        return $this->testimonialModel->getById($id);
    }

    public function update($id, $data) {
        $result = $this->testimonialModel->update($id, $data);
        return $result
            ? ['success' => true, 'message' => 'Testimonial updated successfully']
            : ['success' => false, 'message' => 'Failed to update testimonial'];
    }

    public function delete($id) {
        $result = $this->testimonialModel->delete($id);
        return $result
            ? ['success' => true, 'message' => 'Testimonial deleted successfully']
            : ['success' => false, 'message' => 'Failed to delete testimonial'];
    }

    public function approve($id) {
        $result = $this->testimonialModel->approve($id);
        return $result
            ? ['success' => true, 'message' => 'Testimonial approved and published']
            : ['success' => false, 'message' => 'Failed to approve testimonial'];
    }

    public function reject($id) {
        $result = $this->testimonialModel->reject($id);
        return $result
            ? ['success' => true, 'message' => 'Testimonial rejected']
            : ['success' => false, 'message' => 'Failed to reject testimonial'];
    }

    public function toggleFeatured($id) {
        $result = $this->testimonialModel->toggleFeatured($id);
        return $result
            ? ['success' => true, 'message' => 'Featured status updated']
            : ['success' => false, 'message' => 'Failed to update featured status'];
    }

    public function updateSortOrder($id, $sort_order) {
        return $this->testimonialModel->updateSortOrder($id, $sort_order)
            ? ['success' => true]
            : ['success' => false];
    }

    public function getCounts() {
        return $this->testimonialModel->getCounts();
    }

    public function getStats() {
        return [
            'average_rating' => $this->testimonialModel->getAverageRating(),
            'total_testimonials' => $this->testimonialModel->getApprovedCount(),
        ];
    }
}
