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
            'contact_phone' => '+254 750 116 600',
            'contact_phone_tel' => '+254750116600',
            'contact_email' => 'admin@dazuhub.com',
            'contact_address' => 'Witeithie House, Room 529, Thika Town',
            'contact_hours' => 'Monday - Saturday: 8:00 AM - 5:00 PM',
            'contact_map_embed' => '',
            'contact_map_link' => '',
            'contact_map_title' => 'Witeithie House, Thika',
            'contact_map_subtitle' => 'Room 529 (5th Floor)',
            'contact_map_blurb' => 'Visit our Thika campus for physical labs, or contact admissions for Nairobi classes and online learning options.',
            'site_name' => 'Dazu Training Institute',
            'social_facebook' => '',
            'social_tiktok' => '',
            'social_youtube' => '',
            'social_instagram' => '',
            'footer_copyright' => '',
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
            // Footer lines follow the contact details until the footer itself is edited; once saved, an empty value means "hide it".
            foreach (['phone', 'email', 'address', 'hours'] as $f) {
                $raw = $settings->get('footer_' . $f, "\0unset");
                $defaults['footer_' . $f] = $raw === "\0unset" ? $defaults['contact_' . $f] : (string) $raw;
            }
            foreach (['company', 'courses', 'bottom'] as $f) {
                $defaults['footer_show_' . $f] = $settings->get('footer_show_' . $f, '1') !== '0';
            }
        } catch (Throwable $e) {
            // use defaults
        }
        foreach (['phone', 'email', 'address', 'hours'] as $f) {
            $defaults['footer_' . $f] = $defaults['footer_' . $f] ?? $defaults['contact_' . $f];
        }
        foreach (['company', 'courses', 'bottom'] as $f) {
            $defaults['footer_show_' . $f] = $defaults['footer_show_' . $f] ?? true;
        }

        if ($defaults['contact_map_embed'] === '') {
            $defaults['contact_map_embed'] = 'https://www.google.com/maps?q=' . rawurlencode($defaults['contact_address']) . '&output=embed';
        }
        if ($defaults['contact_map_link'] === '') {
            $defaults['contact_map_link'] = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($defaults['contact_address']);
        }

        $digits = preg_replace('/\D+/', '', $defaults['footer_phone'] !== '' ? $defaults['footer_phone'] : $defaults['contact_phone']) ?: '';
        if (strlen($digits) === 10 && $digits[0] === '0') {
            $defaults['contact_phone_tel'] = '+254' . substr($digits, 1);
        } elseif (str_starts_with($digits, '254')) {
            $defaults['contact_phone_tel'] = '+' . $digits;
        } elseif ($digits !== '') {
            $defaults['contact_phone_tel'] = '+' . $digits;
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
