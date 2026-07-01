<?php
// app/config/mail.php
// Fill in your SMTP details. For Gmail use an App Password (not your login password):
//   https://support.google.com/accounts/answer/185833
// For testing without sending real mail, use Mailtrap (https://mailtrap.io).

return [
    'transport'  => 'smtp',          // 'smtp' or 'mail' (PHP mail() — not recommended)

    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',           // 'tls' (587) or 'ssl' (465) or '' for none
    'username'   => '',
    'password'   => '',

    'from_email' => 'no-reply@dazuhub.co.ke',
    'from_name'  => 'Dazu Hub Institute',
    // Absolute filesystem path to the logo embedded in emails.
    'logo_path'  => $_SERVER['DOCUMENT_ROOT'] . '/Registration-System/public/assets/images/logo/logo.png',
    // Used in email footers / links.

];

