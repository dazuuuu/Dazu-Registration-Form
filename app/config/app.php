<?php
// app/config/app.php

return [
    'app_name' => 'Realestate',
    'app_url' => 'http://localhost/Realestate',
    'debug' => true,
    'timezone' => 'UTC',
    'session_name' => 'realestate_session',
    
    // Security
    'hash_cost' => 12,
    
    // Email (configure for production)
    'email' => [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_user' => 'your_email@gmail.com',
        'smtp_pass' => 'your_password'
    ]
];
?>