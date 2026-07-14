<?php
require_once __DIR__ . '/../models/EnquiryModel.php';
require_once __DIR__ . '/../services/EnquiryNotifier.php';

class EnquiryController {
    private $enquiryModel;
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->enquiryModel = new EnquiryModel($pdo);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Submit new enquiry (public)
     */
    public function submit($data) {
        // Validate required fields
        if (empty($data['name']) || empty($data['phone']) || empty($data['message'])) {
            return ['success' => false, 'message' => 'Please fill in all required fields'];
        }
        
        $email = trim($data['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address'];
        }
        if ($email === '') {
            $email = 'no-email@dazuaihub.local';
        }
        
        // Validate phone (basic)
        if (!preg_match('/^[0-9+\-\s()]+$/', $data['phone'])) {
            return ['success' => false, 'message' => 'Please enter a valid phone number'];
        }
        
        $enquiryData = [
            ':name' => htmlspecialchars(strip_tags($data['name'])),
            ':email' => $email,
            ':phone' => htmlspecialchars(strip_tags($data['phone'])),
            ':service' => !empty($data['service']) ? htmlspecialchars(strip_tags($data['service'])) : null,
            ':message' => htmlspecialchars(strip_tags($data['message'])),
            ':priority' => 'medium'
        ];
        
        $enquiryId = $this->enquiryModel->create($enquiryData);
        
        if ($enquiryId) {
            $this->sendNotificationEmail($enquiryData, (int) $enquiryId);
            
            return [
                'success' => true, 
                'message' => 'Thank you for contacting us. You will be contacted soon by our team.',
                'enquiry_id' => $enquiryId
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to submit enquiry. Please try again.'];
    }
    
    /**
     * Send email notification to admins and confirmation to submitter.
     */
    private function sendNotificationEmail($data, $enquiryId) {
        try {
            $notifier = new EnquiryNotifier($this->pdo);
            $payload = [
                'name' => $data[':name'] ?? '',
                'email' => $data[':email'] ?? '',
                'phone' => $data[':phone'] ?? '',
                'service' => $data[':service'] ?? 'General Inquiry',
                'message' => $data[':message'] ?? '',
            ];

            $adminResult = $notifier->notifyAdmins($this->pdo, $payload, $enquiryId);
            if (empty($adminResult['ok']) && empty($adminResult['skipped'])) {
                error_log('[enquiry] admin notify failed: ' . ($adminResult['message'] ?? 'unknown'));
            }

            $confirmResult = $notifier->sendConfirmation($payload['email'], $payload['name']);
            if (empty($confirmResult['ok']) && empty($confirmResult['skipped'])) {
                error_log('[enquiry] confirmation failed: ' . ($confirmResult['message'] ?? 'unknown'));
            }
        } catch (Throwable $e) {
            error_log('[enquiry] mail error: ' . $e->getMessage());
        }

        return true;
    }
    
    /**
     * Get all enquiries (admin)
     */
    public function getAll($status = null, $limit = null, $offset = 0, $search = null) {
        return $this->enquiryModel->getAll($status, $limit, $offset, $search);
    }
    
    /**
     * Get single enquiry (admin)
     */
    public function getById($id) {
        return $this->enquiryModel->getWithReplies($id);
    }
    
    /**
     * Update enquiry status (admin)
     */
    public function updateStatus($id, $status) {
        $validStatuses = ['new', 'read', 'contacted', 'closed'];
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }
        
        $result = $this->enquiryModel->updateStatus($id, $status);
        
        if ($result) {
            return ['success' => true, 'message' => 'Status updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update status'];
    }
    
    /**
     * Add reply to enquiry (admin)
     */
    public function addReply($id, $reply) {
        if (empty(trim($reply))) {
            return ['success' => false, 'message' => 'Reply cannot be empty'];
        }
        
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => 'Not authenticated'];
        }
        
        $result = $this->enquiryModel->addReply($id, $_SESSION['user_id'], trim($reply));
        
        if ($result) {
            return ['success' => true, 'message' => 'Reply added successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to add reply'];
    }
    
    /**
     * Add note (admin)
     */
    public function addNote($id, $note) {
        if (empty(trim($note))) {
            return ['success' => false, 'message' => 'Note cannot be empty'];
        }
        
        $result = $this->enquiryModel->addNote($id, trim($note));
        
        if ($result) {
            return ['success' => true, 'message' => 'Note added successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to add note'];
    }
    
    /**
     * Update priority (admin)
     */
    public function updatePriority($id, $priority) {
        $validPriorities = ['low', 'medium', 'high'];
        if (!in_array($priority, $validPriorities)) {
            return ['success' => false, 'message' => 'Invalid priority'];
        }
        
        $result = $this->enquiryModel->updatePriority($id, $priority);
        
        if ($result) {
            return ['success' => true, 'message' => 'Priority updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update priority'];
    }
    
    /**
     * Delete enquiry (admin)
     */
    public function delete($id) {
        $result = $this->enquiryModel->delete($id);
        
        if ($result) {
            return ['success' => true, 'message' => 'Enquiry deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete enquiry'];
    }
    
    /**
     * Get counts by status (admin)
     */
    public function getCounts() {
        return $this->enquiryModel->getCounts();
    }
    
    /**
     * Mark as read (admin)
     */
    public function markAsRead($id) {
        return $this->enquiryModel->markAsRead($id);
    }
}
?>