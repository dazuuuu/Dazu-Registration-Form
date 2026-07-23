<?php
// app/v1/helpers/site_contact.php — public contact info from admin site_settings

if (!function_exists('site_contact_settings')) {
    function site_contact_settings(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $defaults = [
            'contact_phone' => site_phone_display(),
            'contact_phone_tel' => site_phone_tel(),
            'contact_email' => 'info@dazuaihub.com',
            'contact_address' => 'Witeithie House, Room 529, Thika Town',
            'contact_hours' => 'Monday - Saturday: 8:00 AM - 5:00 PM',
            'contact_map_embed' => '',
            'contact_map_link' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('Witeithie House, Room 529, Thika Town'),
            'contact_map_title' => 'Witeithie House, Thika',
            'contact_map_subtitle' => 'Room 529 (5th Floor)',
            'contact_map_blurb' => 'Located directly within Thika Town, near principal transport stages, making daily commutes seamless for local students.',
            'site_name' => 'Dazu Hub Institute',
        ];

        try {
            if (!class_exists('SettingModel')) {
                require_once app_path('models/SettingModel.php');
            }
            global $pdo;
            if (!isset($pdo)) {
                require_once app_path('config/db_connect.php');
            }
            $settings = new SettingModel($pdo);
            foreach (array_keys($defaults) as $key) {
                $val = $settings->get($key);
                if ($val !== null && $val !== '') {
                    $defaults[$key] = $val;
                }
            }
        } catch (Throwable $e) {
            // use defaults
        }

        if ($defaults['contact_map_embed'] === '') {
            $defaults['contact_map_embed'] = 'https://www.google.com/maps?q=' . rawurlencode($defaults['contact_address']) . '&output=embed';
        }

        $cache = $defaults;
        return $cache;
    }
}

if (!function_exists('site_contact_map_embed')) {
    function site_contact_map_embed(): string
    {
        return site_contact_settings()['contact_map_embed'];
    }
}
