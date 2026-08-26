<?php
class NewsletterModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    /**
     * Add (or reactivate) a subscriber.
     *
     * @return array{ok:bool, status:string} status: 'new'|'reactivated'|'already_subscribed'
     */
    public function subscribe(string $email, ?string $source = null): array {
        $stmt = $this->db->prepare("SELECT id, status FROM newsletter_subscribers WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['status'] === 'active') {
                return ['ok' => true, 'status' => 'already_subscribed'];
            }
            $stmt = $this->db->prepare(
                "UPDATE newsletter_subscribers
                 SET status = 'active', subscribed_at = NOW(), unsubscribed_at = NULL, source = :source
                 WHERE id = :id"
            );
            $stmt->execute([':source' => $source, ':id' => $existing['id']]);
            return ['ok' => true, 'status' => 'reactivated'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO newsletter_subscribers (email, source) VALUES (:email, :source)"
        );
        $stmt->execute([':email' => $email, ':source' => $source]);
        return ['ok' => true, 'status' => 'new'];
    }

    public function unsubscribe(string $email): bool {
        $stmt = $this->db->prepare(
            "UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE email = :email"
        );
        return $stmt->execute([':email' => $email]);
    }
}
