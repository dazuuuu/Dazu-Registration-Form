<?php
require_once __DIR__ . '/../models/NewsletterModel.php';
require_once __DIR__ . '/../services/NewsletterNotifier.php';

class NewsletterController {
    private $newsletterModel;

    public function __construct($pdo) {
        $this->newsletterModel = new NewsletterModel($pdo);
    }

    /**
     * Subscribe an email address (public).
     * @return array{success:bool, message:string}
     */
    public function subscribe(array $data): array {
        $email = trim($data['email'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }

        $source = !empty($data['source']) ? trim(strip_tags($data['source'])) : null;
        $result = $this->newsletterModel->subscribe($email, $source);

        if ($result['status'] === 'already_subscribed') {
            return ['success' => true, 'message' => "You're already subscribed — thanks for sticking around!"];
        }

        try {
            $notifier = new NewsletterNotifier();
            $mailResult = $notifier->sendWelcome($email);
            if (empty($mailResult['ok'])) {
                error_log('[newsletter] Welcome email failed for ' . $email . ': ' . ($mailResult['message'] ?? 'Unknown error'));
            }
        } catch (Throwable $e) {
            error_log('[newsletter] Welcome email error for ' . $email . ': ' . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Thanks for subscribing! Check your inbox for a confirmation.'];
    }
}
