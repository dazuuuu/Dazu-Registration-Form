<?php
// app/controllers/CheckoutController.php
require_once __DIR__ . '/../models/OrderModel.php';
require_once __DIR__ . '/../services/MpesaService.php';

class CheckoutController {
    private $pdo;
    private $orders;
    private $mpesa;

    public function __construct($pdo, MpesaService $mpesa) {
        $this->pdo = $pdo;
        $this->orders = new OrderModel($pdo);
        $this->mpesa = $mpesa;
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) session_start();
    }

    /** Active cart rows joined to products (authoritative price + stock). */
    public function loadCart(int $userId): array {
        $cond = 'c.user_id = :u';
        try {
            $this->pdo->query('SELECT saved_for_later FROM store_cart LIMIT 0');
            $cond .= ' AND c.saved_for_later = 0';
        } catch (Throwable $e) { /* no saved column */ }

        $stmt = $this->pdo->prepare(
            "SELECT c.product_id, c.quantity, p.name, p.price, p.stock_quantity, p.status
             FROM store_cart c JOIN store_products p ON p.id = c.product_id
             WHERE $cond"
        );
        $stmt->execute([':u' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function err(string $message): array {
        return ['success' => false, 'message' => $message];
    }

    /**
     * Validate the checkout form + cart, create a pending order, send STK push.
     * @param array $form ['customer_name','contact_phone','fulfillment_method','pickup_location','delivery_notes']
     */
    public function initiate(int $userId, array $form): array {
        $name   = trim($form['customer_name'] ?? '');
        $phone  = trim($form['contact_phone'] ?? '');
        $method = in_array($form['fulfillment_method'] ?? '', ['walkin', 'delivery'], true) ? $form['fulfillment_method'] : '';
        $loc    = trim($form['pickup_location'] ?? '');
        $notes  = trim($form['delivery_notes'] ?? '');

        if ($name === '')   return $this->err('Please enter the name of the person collecting the order');
        if ($phone === '')  return $this->err('A contact phone number is required');
        if ($method === '') return $this->err('Choose walk-in pickup or delivery');
        if ($loc === '')    return $this->err($method === 'delivery' ? 'Please enter the delivery address' : 'Please enter your preferred pickup point');

        $norm = $this->mpesa->normalizePhone($phone);
        if (!$norm) return $this->err('That phone number doesn\'t look valid. Use a Safaricom number like 07XXXXXXXX');

        $cart = $this->loadCart($userId);
        if (empty($cart)) return $this->err('Your cart is empty');

        $items = [];
        foreach ($cart as $row) {
            if (($row['status'] ?? '') !== 'active') {
                return $this->err($row['name'] . ' is no longer available');
            }
            if ((int) $row['quantity'] > (int) $row['stock_quantity']) {
                return $this->err('Only ' . (int) $row['stock_quantity'] . ' of ' . $row['name'] . ' left in stock');
            }
            $items[] = [
                'product_id' => (int) $row['product_id'],
                'name'       => $row['name'],
                'price'      => (float) $row['price'],
                'quantity'   => (int) $row['quantity'],
            ];
        }

        // Create the pending order first so we have an order number for the STK ref.
        $order = $this->orders->createPendingOrder($userId, [
            'name' => $name, 'phone' => $phone, 'method' => $method, 'location' => $loc, 'notes' => $notes,
        ], $items);

        $amount = (int) round($order['total']);
        if ($amount < 1) {
            $this->orders->markFailedById((int) $order['id']);
            return $this->err('Order total must be at least KSh 1');
        }

        try {
            $resp = $this->mpesa->stkPush($norm, $amount, $order['order_number'], 'Payment ' . $order['order_number']);
        } catch (Throwable $e) {
            $this->orders->markFailedById((int) $order['id']);
            error_log('[checkout] STK push error: ' . $e->getMessage());
            // NOTE: the real message is shown to help you debug. Once live, replace
            // with a generic 'Could not reach M-Pesa right now. Please try again.'
            return $this->err('M-Pesa error: ' . $e->getMessage());
        }

        if (($resp['ResponseCode'] ?? '1') !== '0') {
            $this->orders->markFailedById((int) $order['id']);
            return $this->err($resp['errorMessage'] ?? $resp['ResponseDescription'] ?? 'M-Pesa declined the request');
        }

        $this->orders->attachStk((int) $order['id'], $resp['MerchantRequestID'] ?? null, $resp['CheckoutRequestID'] ?? null);

        return [
            'success'             => true,
            'message'             => 'Check your phone and enter your M-Pesa PIN to complete payment.',
            'order_number'        => $order['order_number'],
            'checkout_request_id' => $resp['CheckoutRequestID'] ?? null,
            'amount'              => $amount,
        ];
    }
}