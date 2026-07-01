<?php
// app/services/OrderNotifier.php
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/EmailTemplate.php';

class OrderNotifier {
    private $pdo;
    private $mailer;

    public function __construct($pdo, Mailer $mailer) {
        $this->pdo = $pdo;
        $this->mailer = $mailer;
    }

    /** Look up the buyer's email + name from the users table. */
    private function recipient(int $userId): ?array {
        // Be tolerant about the name column across schemas.
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
        if (!$u || empty($u['email'])) return null;

        $name = $u['name'] ?? $u['full_name'] ?? $u['username'] ?? '';
        return ['email' => $u['email'], 'name' => $name];
    }

    /** Purchase-success email (call after payment confirmed). $order must include items. */
    public function sendPurchaseConfirmation(array $order): array {
        $to = $this->recipient((int) $order['user_id']);
        if (!$to) return ['ok' => false, 'message' => 'No recipient email on file'];
        [$subject, $html] = EmailTemplate::render('purchased', $order);
        return $this->mailer->send($to['email'], $to['name'], $subject, $html);
    }

    /** Status-change email for a single parcel. $event: picked_up|delivered|arrived. */
    public function sendParcelStatus(array $order, array $item, string $event): array {
        if (!in_array($event, ['picked_up', 'delivered', 'arrived'], true)) {
            return ['ok' => false, 'message' => 'No email for this status'];
        }
        $to = $this->recipient((int) $order['user_id']);
        if (!$to) return ['ok' => false, 'message' => 'No recipient email on file'];
        [$subject, $html] = EmailTemplate::render($event, $order, $item);
        return $this->mailer->send($to['email'], $to['name'], $subject, $html);
    }
}