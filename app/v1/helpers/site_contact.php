<?php
// app/v1/helpers/site_contact.php

require_once __DIR__ . '/../models/SettingModel.php';

function site_contact_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        require_once __DIR__ . '/../models/Connection.php';
        $pdo = \Models\Connection::getInstance()->getConnection();
        $settings = new SettingModel($pdo);
        $cache = [
            'contact_phone' => $settings->get('contact_phone', '+254 750 116600'),
            'contact_email' => $settings->get('contact_email', 'info@dazuhub.co.ke'),
            'site_name' => $settings->get('site_name', 'Dazu Hub Institute'),
        ];
    } catch (Throwable $e) {
        $cache = [
            'contact_phone' => '+254 750 116600',
            'contact_email' => 'info@dazuhub.co.ke',
            'site_name' => 'Dazu Hub Institute',
        ];
    }

    return $cache;
}
