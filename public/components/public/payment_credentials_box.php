<?php
// public/components/public/payment_credentials_box.php
require_once __DIR__ . '/../../../app/v1/helpers/payment_credentials.php';

$pay = payment_credentials();
$boxClass = $pay_box_class ?? 'dh-pay-box';
?>
<div class="<?php echo htmlspecialchars($boxClass); ?>" role="region" aria-label="M-Pesa payment instructions">
    <div class="dh-pay-box-head">
        <span class="dh-pay-bank"><?php echo htmlspecialchars($pay['bank']); ?></span>
        <span class="dh-pay-service"><?php echo htmlspecialchars($pay['service']); ?></span>
    </div>
    <div class="dh-pay-grid">
        <div class="dh-pay-item">
            <span class="dh-pay-label">Paybill Number</span>
            <strong class="dh-pay-value"><?php echo htmlspecialchars($pay['paybill']); ?></strong>
        </div>
        <div class="dh-pay-item">
            <span class="dh-pay-label">Account Number</span>
            <strong class="dh-pay-value"><?php echo htmlspecialchars($pay['account_number']); ?></strong>
        </div>
        <div class="dh-pay-item dh-pay-item--full">
            <span class="dh-pay-label">Account Name</span>
            <strong class="dh-pay-value"><?php echo htmlspecialchars($pay['account_name']); ?></strong>
        </div>
    </div>
    <p class="dh-pay-steps">
        M-Pesa → <strong>Lipa na M-PESA</strong> → <strong>Pay Bill</strong> →
        Paybill <strong><?php echo htmlspecialchars($pay['paybill']); ?></strong> →
        Account <strong><?php echo htmlspecialchars($pay['account_number']); ?></strong>
    </p>
</div>
