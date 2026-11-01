<?php
// The office's tools on a receipt page (admin and superadmin): add, change or remove the NB of this one receipt, and build the
// receipt again with the current Settings → Receipts look. The amounts stay as they were when it was paid.
require_once __DIR__ . '/../services/ReceiptGenerator.php';

if (!function_exists('receipt_note_post')) {
    /** Handles the POST, then redirects back to $self. Returns only when it is not a receipt POST. */
    function receipt_note_post(PDO $pdo, int $paymentId, string $self): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['receipt_action'])) return;
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['rcpt_flash'] = 'Security check failed. Please try again.';
        } else {
            try {
                if ($_POST['receipt_action'] === 'note') {
                    $nb = mb_substr(trim((string) ($_POST['receipt_note'] ?? '')), 0, 600);
                    $pdo->prepare('UPDATE payments SET receipt_note = ? WHERE id = ?')->execute([$nb !== '' ? $nb : null, $paymentId]);
                }
                $_SESSION['rcpt_flash'] = ReceiptGenerator::rebuild($pdo, $paymentId) ? 'Receipt updated.' : 'This receipt cannot be updated.';
            } catch (Throwable $e) {
                error_log('[receipt_note] ' . $e->getMessage());
                $_SESSION['rcpt_flash'] = 'Run System Updates (migration 096) first.';
            }
        }
        header('Location: ' . $self);
        exit;
    }

    /** The NB box shown above the receipt (not printed). */
    function receipt_note_panel(array $payment): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['rcpt_flash'] ?? '';
        unset($_SESSION['rcpt_flash']);
        return '<div class="rcpt-tools" style="max-width:640px;margin:0 auto 18px;background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px 16px;font-family:Arial,sans-serif;font-size:13px">'
            . ($flash ? '<div style="background:#eef7e9;color:#245c12;border-radius:6px;padding:8px 10px;margin-bottom:10px">' . $e($flash) . '</div>' : '')
            . '<form method="post">' . csrf_field() . '<input type="hidden" name="receipt_action" value="note">'
            . '<label style="font-weight:700;display:block;margin-bottom:6px">NB on this receipt</label>'
            . '<textarea name="receipt_note" rows="2" maxlength="600" style="width:100%;box-sizing:border-box;border:1px solid #ccc;border-radius:6px;padding:8px;font:inherit" placeholder="e.g. Balance to be cleared before the exam on 15 Dec.">'
            . $e($payment['receipt_note'] ?? '') . '</textarea>'
            . '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">'
            . '<button style="background:#44A024;color:#fff;border:0;border-radius:6px;padding:8px 14px;font-weight:700;cursor:pointer">Save NB</button>'
            . '<button name="receipt_action" value="rebuild" style="background:#fff;border:1px solid #ccc;border-radius:6px;padding:8px 14px;cursor:pointer">Apply the current receipt design</button>'
            . '</div><div style="color:#777;margin-top:6px">Leave it empty and save to remove the NB. The student sees the change on their receipt.</div></form></div>'
            . '<style>@media print{.rcpt-tools{display:none}}</style>';
    }
}
