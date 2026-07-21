<?php
// app/v1/helpers/payment_credentials.php — M-Pesa Lipa na M-PESA (NCBA) payment details

if (!function_exists('payment_credentials')) {
    function payment_credentials(): array
    {
        return [
            'bank'           => 'NCBA',
            'service'        => 'Lipa na M-PESA',
            'paybill'        => '880100',
            'account_number' => '681158',
            'account_name'   => 'DAZU HUB',
        ];
    }
}
