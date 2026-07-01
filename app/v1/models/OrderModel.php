<?php
// app/models/OrderModel.php

class OrderModel {
    private $db;

    // Per-method fulfillment progression. Last value in each list is "final".
    private const FLOW = [
        'walkin'   => ['processing', 'ready_for_pickup', 'picked_up'],
        'delivery' => ['processing', 'out_for_delivery', 'delivered', 'arrived'],
    ];
    private const FINAL = ['picked_up' => true, 'arrived' => true];

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    // ---- ID generation ------------------------------------------------------

    public function generateOrderNumber(): string {
        do {
            $n = 'ISM-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        } while ($this->valueExists('store_orders', 'order_number', $n));
        return $n;
    }

    public function generateParcelId(): string {
        do {
            $rand = str_pad(strtoupper(base_convert((string) random_int(0, 60466175), 10, 36)), 5, '0', STR_PAD_LEFT);
            $p = 'PCL-' . $rand;
        } while ($this->valueExists('store_order_items', 'parcel_id', $p));
        return $p;
    }

    private function valueExists(string $table, string $col, string $val): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM $table WHERE $col = :v LIMIT 1");
        $stmt->execute([':v' => $val]);
        return (bool) $stmt->fetchColumn();
    }

    // ---- Creation -----------------------------------------------------------

    /**
     * @param array $customer  ['name','phone','method','location','notes']
     * @param array $items      each ['product_id','name','price','quantity']
     * @return array ['id','order_number','subtotal','total']
     */
    public function createPendingOrder(int $userId, array $customer, array $items): array {
        if (empty($items)) {
            throw new InvalidArgumentException('Cannot create an order with no items');
        }
        $method = in_array($customer['method'] ?? '', ['walkin', 'delivery'], true) ? $customer['method'] : 'walkin';

        $subtotal = 0.0;
        foreach ($items as $it) {
            $subtotal += (float) $it['price'] * (int) $it['quantity'];
        }
        $total = $subtotal; // delivery is settled directly with the courier, not charged here

        $this->db->beginTransaction();
        try {
            $orderNo = $this->generateOrderNumber();
            $stmt = $this->db->prepare(
                "INSERT INTO store_orders
                 (order_number, user_id, customer_name, contact_phone, fulfillment_method, pickup_location, delivery_notes, currency, subtotal, total, payment_status, payment_method)
                 VALUES (:no, :u, :name, :phone, :method, :loc, :notes, 'KES', :sub, :tot, 'pending', 'mpesa')"
            );
            $stmt->execute([
                ':no'     => $orderNo,
                ':u'      => $userId,
                ':name'   => $customer['name'] ?? '',
                ':phone'  => $customer['phone'] ?? '',
                ':method' => $method,
                ':loc'    => $customer['location'] ?? null,
                ':notes'  => $customer['notes'] ?? null,
                ':sub'    => $subtotal,
                ':tot'    => $total,
            ]);
            $orderId = (int) $this->db->lastInsertId();

            $insItem = $this->db->prepare(
                "INSERT INTO store_order_items
                 (order_id, parcel_id, product_id, product_name, unit_price, quantity, line_total, fulfillment_status)
                 VALUES (:o, :pcl, :pid, :pn, :up, :q, :lt, 'processing')"
            );
            foreach ($items as $it) {
                $insItem->execute([
                    ':o'   => $orderId,
                    ':pcl' => $this->generateParcelId(),
                    ':pid' => $it['product_id'] ?? null,
                    ':pn'  => $it['name'],
                    ':up'  => $it['price'],
                    ':q'   => (int) $it['quantity'],
                    ':lt'  => (float) $it['price'] * (int) $it['quantity'],
                ]);
            }

            $this->db->commit();
            return ['id' => $orderId, 'order_number' => $orderNo, 'subtotal' => $subtotal, 'total' => $total];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function attachStk(int $orderId, ?string $merchantRequestId, ?string $checkoutRequestId): void {
        $this->db->prepare(
            "UPDATE store_orders SET mpesa_merchant_request_id = :m, mpesa_checkout_request_id = :c WHERE id = :id"
        )->execute([':m' => $merchantRequestId, ':c' => $checkoutRequestId, ':id' => $orderId]);
    }

    // ---- Reads --------------------------------------------------------------

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM store_orders WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByOrderNumber(string $no): ?array {
        $stmt = $this->db->prepare("SELECT * FROM store_orders WHERE order_number = :no");
        $stmt->execute([':no' => $no]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByCheckoutRequestId(string $checkoutId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM store_orders WHERE mpesa_checkout_request_id = :c");
        $stmt->execute([':c' => $checkoutId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getItems(int $orderId): array {
        $stmt = $this->db->prepare("SELECT * FROM store_order_items WHERE order_id = :o ORDER BY id ASC");
        $stmt->execute([':o' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderWithItems($idOrNumber): ?array {
        $order = is_numeric($idOrNumber) ? $this->getById((int) $idOrNumber) : $this->getByOrderNumber((string) $idOrNumber);
        if (!$order) return null;
        $order['items'] = $this->getItems((int) $order['id']);
        return $order;
    }

    // ---- Payment ------------------------------------------------------------

    /**
     * Mark an order paid: records the M-Pesa receipt, decrements stock, and clears
     * the buyer's active cart. Idempotent — a repeated callback won't double-deduct.
     *
     * @param array $mpesa ['receipt','phone','payer_name']
     */
    public function markPaidByCheckoutId(string $checkoutId, array $mpesa): array {
        $order = $this->getByCheckoutRequestId($checkoutId);
        if (!$order) return ['ok' => false, 'message' => 'Order not found'];
        if ($order['payment_status'] === 'paid') {
            return ['ok' => true, 'already' => true, 'order' => $order];
        }

        $this->db->beginTransaction();
        try {
            // Conditional update guards against a concurrent duplicate callback.
            $upd = $this->db->prepare(
                "UPDATE store_orders
                 SET payment_status = 'paid', mpesa_receipt = :r, mpesa_phone = :p, mpesa_payer_name = :n, paid_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND payment_status <> 'paid'"
            );
            $upd->execute([
                ':r'  => $mpesa['receipt'] ?? null,
                ':p'  => $mpesa['phone'] ?? null,
                ':n'  => $mpesa['payer_name'] ?? null,
                ':id' => $order['id'],
            ]);
            if ($upd->rowCount() === 0) {
                // Someone else already flipped it to paid — bail without deducting again.
                $this->db->commit();
                return ['ok' => true, 'already' => true, 'order' => $this->getById((int) $order['id'])];
            }

            // Decrement stock per item (clamped at zero; never goes negative).
            $get = $this->db->prepare("SELECT stock_quantity FROM store_products WHERE id = :id");
            $set = $this->db->prepare("UPDATE store_products SET stock_quantity = :q WHERE id = :id");
            foreach ($this->getItems((int) $order['id']) as $it) {
                if (empty($it['product_id'])) continue;
                $get->execute([':id' => $it['product_id']]);
                $cur = $get->fetchColumn();
                if ($cur === false) continue;
                $set->execute([':q' => max(0, (int) $cur - (int) $it['quantity']), ':id' => $it['product_id']]);
            }

            $this->clearUserActiveCart((int) $order['user_id']);

            $this->db->commit();
            return ['ok' => true, 'order' => $this->getById((int) $order['id'])];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function markFailedByCheckoutId(string $checkoutId): void {
        $this->db->prepare(
            "UPDATE store_orders SET payment_status = 'failed' WHERE mpesa_checkout_request_id = :c AND payment_status = 'pending'"
        )->execute([':c' => $checkoutId]);
    }

    public function markFailedById(int $orderId): void {
        $this->db->prepare(
            "UPDATE store_orders SET payment_status = 'failed' WHERE id = :id AND payment_status = 'pending'"
        )->execute([':id' => $orderId]);
    }

    private function clearUserActiveCart(int $userId): void {
        $where = 'user_id = :u';
        try {
            $this->db->query('SELECT saved_for_later FROM store_cart LIMIT 0');
            $where .= ' AND saved_for_later = 0';
        } catch (Throwable $e) { /* column absent */ }
        $this->db->prepare("DELETE FROM store_cart WHERE $where")->execute([':u' => $userId]);
    }

    // ---- Fulfillment (admin advances parcel status) -------------------------

    public function allowedStatusesFor(string $method): array {
        return self::FLOW[$method] ?? self::FLOW['walkin'];
    }

    public function isFinalStatus(string $status): bool {
        return isset(self::FINAL[$status]);
    }

    /**
     * Admin sets a parcel's status. Validates the status is legal for the
     * order's fulfillment method. Stamps fulfilled_at on a final status.
     */
    public function updateParcelStatus(string $parcelId, string $newStatus): array {
        $stmt = $this->db->prepare(
            "SELECT i.id, o.fulfillment_method
             FROM store_order_items i JOIN store_orders o ON o.id = i.order_id
             WHERE i.parcel_id = :p"
        );
        $stmt->execute([':p' => $parcelId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['ok' => false, 'message' => 'Parcel not found'];

        $allowed = $this->allowedStatusesFor($row['fulfillment_method']);
        if ($newStatus !== 'cancelled' && !in_array($newStatus, $allowed, true)) {
            return ['ok' => false, 'message' => "Status '$newStatus' is not valid for a {$row['fulfillment_method']} order"];
        }

        $final = $this->isFinalStatus($newStatus) ? 'CURRENT_TIMESTAMP' : 'NULL';
        $this->db->prepare(
            "UPDATE store_order_items SET fulfillment_status = :s, fulfilled_at = $final WHERE parcel_id = :p"
        )->execute([':s' => $newStatus, ':p' => $parcelId]);

        return ['ok' => true, 'final' => $this->isFinalStatus($newStatus)];
    }

    // ---- Customer views -----------------------------------------------------

    /** Paid orders that still have at least one parcel not in a final state. */
    public function getActiveForUser(int $userId): array {
        return $this->classifyUserOrders($userId)['active'];
    }

    /** Paid orders fully fulfilled, plus failed/cancelled ones. */
    public function getHistoryForUser(int $userId): array {
        return $this->classifyUserOrders($userId)['history'];
    }

    private function classifyUserOrders(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM store_orders WHERE user_id = :u ORDER BY created_at DESC, id DESC"
        );
        $stmt->execute([':u' => $userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $active = [];
        $history = [];
        foreach ($orders as $o) {
            $o['items'] = $this->getItems((int) $o['id']);

            if ($o['payment_status'] !== 'paid') {
                if (in_array($o['payment_status'], ['failed', 'cancelled'], true)) {
                    $history[] = $o; // dead orders go to history; 'pending' stays hidden
                }
                continue;
            }

            $allFinal = !empty($o['items']);
            foreach ($o['items'] as $it) {
                if (!$this->isFinalStatus($it['fulfillment_status']) && $it['fulfillment_status'] !== 'cancelled') {
                    $allFinal = false;
                    break;
                }
            }
            if ($allFinal) { $history[] = $o; } else { $active[] = $o; }
        }
        return ['active' => $active, 'history' => $history];
    }

    // ---- Admin views --------------------------------------------------------

    public function adminList(?string $paymentStatus = null, int $limit = 100, int $offset = 0): array {
        $sql = "SELECT * FROM store_orders";
        $params = [];
        if ($paymentStatus) {
            $sql .= " WHERE payment_status = :ps";
            $params[':ps'] = $paymentStatus;
        }
        $sql .= " ORDER BY created_at DESC, id DESC LIMIT :lim OFFSET :off";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orders as &$o) { $o['items'] = $this->getItems((int) $o['id']); }
        return $orders;
    }

    /** Count of paid orders with at least one parcel still needing fulfillment. */
    public function countNeedingAttention(): int {
        $sql = "SELECT COUNT(DISTINCT o.id)
                FROM store_orders o
                JOIN store_order_items i ON i.order_id = o.id
                WHERE o.payment_status = 'paid'
                  AND i.fulfillment_status NOT IN ('picked_up', 'arrived', 'cancelled')";
        return (int) $this->db->query($sql)->fetchColumn();
    }

    /** Newest paid order (used to detect "a new order just arrived"). */
    public function latestPaidOrder(): ?array {
        $stmt = $this->db->query(
            "SELECT id, order_number, customer_name, total, created_at
             FROM store_orders WHERE payment_status = 'paid' ORDER BY id DESC LIMIT 1"
        );
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}