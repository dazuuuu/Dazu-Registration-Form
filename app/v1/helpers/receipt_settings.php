<?php
// app/v1/helpers/receipt_settings.php — SmS receipt branding from site_settings

if (!function_exists('sms_receipt_settings')) {
    function sms_receipt_settings(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        require_once __DIR__ . '/site_contact.php';
        $contact = site_contact_settings();

        $defaults = [
            'company_name' => $contact['site_name'] ?? 'Dazu Hub Institute',
            'phone' => $contact['contact_phone'] ?? '',
            'location' => $contact['contact_address'] ?? '',
            'pobox' => '',
            'footer' => 'Thank you for your payment. Please retain this receipt for your records.',
            'logo_path' => '',
        ];

        try {
            if (!class_exists('SettingModel')) {
                require_once __DIR__ . '/../models/SettingModel.php';
            }
            global $pdo;
            if (!isset($pdo)) {
                require_once __DIR__ . '/../config/db_connect.php';
            }
            $settings = new SettingModel($pdo);
            foreach ([
                'sms_receipt_company_name' => 'company_name',
                'sms_receipt_phone' => 'phone',
                'sms_receipt_location' => 'location',
                'sms_receipt_pobox' => 'pobox',
                'sms_receipt_footer' => 'footer',
                'sms_receipt_logo_path' => 'logo_path',
            ] as $key => $field) {
                $val = $settings->get($key);
                if ($val !== null && $val !== '') {
                    $defaults[$field] = $val;
                }
            }
            if ($defaults['logo_path'] === '') {
                $siteLogo = $settings->get('logo_path');
                if ($siteLogo) {
                    $defaults['logo_path'] = $siteLogo;
                }
            }
        } catch (Throwable $e) {
            // use defaults
        }

        $cache = $defaults;
        return $cache;
    }
}

if (!function_exists('sms_receipt_logo_absolute')) {
    function sms_receipt_logo_absolute(?string $relPath): ?string
    {
        if (!$relPath) {
            return null;
        }
        if (!defined('PROJECT_ROOT')) {
            require_once __DIR__ . '/../config/paths.php';
        }
        if (preg_match('#^https?://#i', $relPath)) {
            return $relPath;
        }
        $public = PROJECT_ROOT . '/public/' . ltrim($relPath, '/');
        return is_file($public) ? $public : null;
    }
}
