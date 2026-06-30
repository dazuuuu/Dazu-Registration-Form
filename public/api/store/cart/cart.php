<?php
/**
 * /public/api/store/cart/cart.php
 *
 * Single consolidated cart endpoint. Every cart operation goes through here so
 * the front-end only ever talks to one URL and always receives clean JSON.
 *
 * Action is taken from `action` in the query string, POST body, or JSON body.
 * Actions:
 *   count           -> { success, count }
 *   get             -> { success, items[], count, subtotal, total }
 *   add             -> body: product_id, quantity        (login required)
 *   update          -> body: item_id, quantity           (login required)
 *   remove          -> body: item_id                      (login required)
 *   clear           -> (login required)
 *   save_for_later  -> body: product_id                   (login required, needs saved_for_later column)
 *   move_to_cart    -> body: product_id                   (login required, needs saved_for_later column)
 *   saved           -> { success, items[] }               (login required)
 *
 * Notes:
 *  - Mutating actions require a logged-in user; otherwise { require_login: true }.
 *  - Output is always JSON. Stray warnings/notices are buffered so they can never
 *    corrupt the JSON body (the #1 cause of "response.json() failed" in the old code).
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start(); // capture anything that might leak before our JSON

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** Emit JSON and stop, discarding any buffered stray output. */
function cart_out($data, int $code = 200): void {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// --- locate db_connect.php (layout-tolerant: try the likely depths) ----------
$dbCandidates = [
    __DIR__ . '/../../../../app/config/db_connect.php', // /public/api/store/cart -> /app
    __DIR__ . '/../../../app/config/db_connect.php',
];
$dbLoaded = false;
foreach ($dbCandidates as $cand) {
    if (is_file($cand)) { require_once $cand; $dbLoaded = true; break; }
}
if (!$dbLoaded || !isset($pdo)) {
    cart_out(['success' => false, 'message' => 'Cart is unavailable right now'], 500);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- gather input (query + form + JSON body) ---------------------------------
$body = $_GET + $_POST;
$raw = file_get_contents('php://input');
if ($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $body = array_merge($body, $decoded);
    }
}

$action = strtolower(trim((string) ($body['action'] ?? '')));
if ($action === '') {
    cart_out(['success' => false, 'message' => 'No cart action specified'], 400);
}

$userId = $_SESSION['user_id'] ?? null;

/** Detect the optional saved_for_later column without assuming the DB engine. */
function cart_has_saved_column(PDO $pdo): bool {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $pdo->query('SELECT saved_for_later FROM store_cart LIMIT 0');
        $has = true;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/** Total item count for the active cart (excludes saved-for-later when supported). */
function cart_count(PDO $pdo, int $userId): int {
    $where = 'user_id = :u';
    if (cart_has_saved_column($pdo)) {
        $where .= ' AND saved_for_later = 0';
    }
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) AS t FROM store_cart WHERE $where");
    $stmt->execute([':u' => $userId]);
    return (int) $stmt->fetchColumn();
}

/** Fetch an active product or null. */
function cart_get_product(PDO $pdo, $productId): ?array {
    $stmt = $pdo->prepare("SELECT id, name, price, stock_quantity, status FROM store_products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    return $p ?: null;
}

/**
 * Add a product to a user's active cart (shared by the 'add' action and the
 * post-login auto-add). Returns ['ok'=>bool, 'message'=>string, 'code'=>int].
 */
function cart_add_item(PDO $pdo, int $userId, int $productId, int $qty): array {
    $qty = max(1, $qty);
    $product = cart_get_product($pdo, $productId);
    if (!$product || $product['status'] !== 'active') {
        return ['ok' => false, 'message' => 'Product not available', 'code' => 404];
    }
    $stock = (int) $product['stock_quantity'];
    if ($stock <= 0) {
        return ['ok' => false, 'message' => 'This product is out of stock', 'code' => 200];
    }
    $hasSaved = cart_has_saved_column($pdo);
    $find = 'user_id = :u AND product_id = :p' . ($hasSaved ? ' AND saved_for_later = 0' : '');
    $stmt = $pdo->prepare("SELECT id, quantity FROM store_cart WHERE $find");
    $stmt->execute([':u' => $userId, ':p' => $productId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $newQty = min($stock, (int) $existing['quantity'] + $qty);
        $stmt = $pdo->prepare("UPDATE store_cart SET quantity = :q, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([':q' => $newQty, ':id' => $existing['id']]);
    } else {
        $qty = min($stock, $qty);
        if ($hasSaved) {
            $stmt = $pdo->prepare("INSERT INTO store_cart (user_id, product_id, quantity, price, saved_for_later) VALUES (:u, :p, :q, :pr, 0)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO store_cart (user_id, product_id, quantity, price) VALUES (:u, :p, :q, :pr)");
        }
        $stmt->execute([':u' => $userId, ':p' => $productId, ':q' => $qty, ':pr' => $product['price']]);
    }
    return ['ok' => true, 'message' => $product['name'] . ' added to cart'];
}

/**
 * If the visitor selected a product before logging in, add it now. This runs on
 * ANY cart call once they're authenticated — including the /api/count.php request
 * the navbar fires on every page load — so the item lands in the cart right after
 * login with no change needed to the login page itself.
 */
if ($userId && !empty($_SESSION['pending_cart_add']) && is_array($_SESSION['pending_cart_add'])) {
    $pending = $_SESSION['pending_cart_add'];
    unset($_SESSION['pending_cart_add']); // consume once, even if it fails
    if (!empty($pending['product_id'])) {
        try {
            cart_add_item($pdo, (int) $userId, (int) $pending['product_id'], (int) ($pending['quantity'] ?? 1));
        } catch (Throwable $e) {
            error_log('[cart pending] ' . $e->getMessage());
        }
    }
}

try {
    switch ($action) {

        // ---------------------------------------------------------------- count
        case 'count': {
            if (!$userId) {
                cart_out(['success' => true, 'count' => 0]);
            }
            cart_out(['success' => true, 'count' => cart_count($pdo, (int) $userId)]);
        }

        // ------------------------------------------------------------------ get
        case 'get':
        case 'saved': {
            if (!$userId) {
                cart_out(['success' => true, 'items' => [], 'count' => 0, 'subtotal' => 0, 'total' => 0]);
            }
            $savedFlag = $action === 'saved' ? 1 : 0;
            $cond = 'c.user_id = :u';
            if (cart_has_saved_column($pdo)) {
                $cond .= ' AND c.saved_for_later = ' . $savedFlag;
            } elseif ($savedFlag === 1) {
                cart_out(['success' => true, 'items' => []]); // feature not enabled
            }
            $sql = "SELECT c.id, c.product_id, c.quantity, c.price,
                           p.name, p.slug, p.featured_image, p.stock_quantity, p.status
                    FROM store_cart c
                    JOIN store_products p ON p.id = c.product_id
                    WHERE $cond
                    ORDER BY c.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':u' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $items = [];
            $subtotal = 0.0;
            $count = 0;
            foreach ($rows as $r) {
                $line = (float) $r['price'] * (int) $r['quantity'];
                $subtotal += $line;
                $count += (int) $r['quantity'];
                $items[] = [
                    'id'             => (int) $r['id'],
                    'product_id'     => (int) $r['product_id'],
                    'name'           => $r['name'],
                    'slug'           => $r['slug'],
                    'featured_image' => $r['featured_image'],
                    'price'          => (float) $r['price'],
                    'quantity'       => (int) $r['quantity'],
                    'line_total'     => $line,
                    'stock_quantity' => (int) $r['stock_quantity'],
                ];
            }
            if ($action === 'saved') {
                cart_out(['success' => true, 'items' => $items]);
            }
            cart_out(['success' => true, 'items' => $items, 'count' => $count, 'subtotal' => $subtotal, 'total' => $subtotal]);
        }

        // ------------------------------------------------------------------ add
        case 'add': {
            $productId = (int) ($body['product_id'] ?? 0);
            $qty = max(1, (int) ($body['quantity'] ?? 1));
            if ($productId <= 0) {
                cart_out(['success' => false, 'message' => 'Product ID required'], 400);
            }

            if (!$userId) {
                // Remember what they picked so it's added automatically after login.
                $_SESSION['pending_cart_add'] = ['product_id' => $productId, 'quantity' => $qty];
                cart_out([
                    'success'       => false,
                    'message'       => 'Please login to add items to cart',
                    'require_login' => true,
                    'login_url'     => '/Ismano/public/auth/login.php',
                ]);
            }

            $res = cart_add_item($pdo, (int) $userId, $productId, $qty);
            if (!$res['ok']) {
                cart_out(['success' => false, 'message' => $res['message']], $res['code'] ?? 200);
            }
            cart_out([
                'success' => true,
                'message' => $res['message'],
                'count'   => cart_count($pdo, (int) $userId),
            ]);
        }

        // --------------------------------------------------------------- update
        case 'update': {
            if (!$userId) {
                cart_out(['success' => false, 'message' => 'Please login', 'require_login' => true]);
            }
            $itemId = (int) ($body['item_id'] ?? 0);
            $qty = (int) ($body['quantity'] ?? 0);
            if ($itemId <= 0 || $qty < 1) {
                cart_out(['success' => false, 'message' => 'Item ID and a valid quantity are required'], 400);
            }
            // Ownership check + stock clamp.
            $stmt = $pdo->prepare("SELECT c.id, p.stock_quantity FROM store_cart c JOIN store_products p ON p.id = c.product_id WHERE c.id = :id AND c.user_id = :u");
            $stmt->execute([':id' => $itemId, ':u' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                cart_out(['success' => false, 'message' => 'Cart item not found'], 404);
            }
            $qty = min($qty, max(1, (int) $row['stock_quantity']));
            $stmt = $pdo->prepare("UPDATE store_cart SET quantity = :q, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :u");
            $stmt->execute([':q' => $qty, ':id' => $itemId, ':u' => $userId]);
            cart_out(['success' => true, 'message' => 'Cart updated', 'quantity' => $qty, 'count' => cart_count($pdo, (int) $userId)]);
        }

        // --------------------------------------------------------------- remove
        case 'remove': {
            if (!$userId) {
                cart_out(['success' => false, 'message' => 'Please login', 'require_login' => true]);
            }
            $itemId = (int) ($body['item_id'] ?? 0);
            if ($itemId <= 0) {
                cart_out(['success' => false, 'message' => 'Item ID required'], 400);
            }
            $stmt = $pdo->prepare("DELETE FROM store_cart WHERE id = :id AND user_id = :u");
            $stmt->execute([':id' => $itemId, ':u' => $userId]);
            cart_out(['success' => true, 'message' => 'Item removed', 'count' => cart_count($pdo, (int) $userId)]);
        }

        // ---------------------------------------------------------------- clear
        case 'clear': {
            if (!$userId) {
                cart_out(['success' => false, 'message' => 'Please login', 'require_login' => true]);
            }
            $where = 'user_id = :u';
            if (cart_has_saved_column($pdo)) {
                $where .= ' AND saved_for_later = 0';
            }
            $stmt = $pdo->prepare("DELETE FROM store_cart WHERE $where");
            $stmt->execute([':u' => $userId]);
            cart_out(['success' => true, 'message' => 'Cart cleared', 'count' => 0]);
        }

        // ------------------------------------------------- save_for_later / move
        case 'save_for_later':
        case 'move_to_cart': {
            if (!$userId) {
                cart_out(['success' => false, 'message' => 'Please login', 'require_login' => true]);
            }
            if (!cart_has_saved_column($pdo)) {
                cart_out(['success' => false, 'message' => 'Saved-for-later is not enabled. Run migration 008 to add the saved_for_later column.']);
            }
            $productId = (int) ($body['product_id'] ?? 0);
            if ($productId <= 0) {
                cart_out(['success' => false, 'message' => 'Product ID required'], 400);
            }
            $flag = $action === 'save_for_later' ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE store_cart SET saved_for_later = :f, updated_at = CURRENT_TIMESTAMP WHERE user_id = :u AND product_id = :p");
            $stmt->execute([':f' => $flag, ':u' => $userId, ':p' => $productId]);

            // If moving to cart and the row didn't exist as saved, nothing happens — that's fine.
            cart_out([
                'success' => true,
                'message' => $flag ? 'Saved for later' : 'Moved to cart',
                'count'   => cart_count($pdo, (int) $userId),
            ]);
        }

        default:
            cart_out(['success' => false, 'message' => 'Unknown cart action: ' . $action], 400);
    }
} catch (Throwable $e) {
    error_log('[cart api] ' . $e->getMessage());
    cart_out(['success' => false, 'message' => 'A server error occurred'], 500);
}