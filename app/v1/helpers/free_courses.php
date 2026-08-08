<?php
// app/v1/helpers/free_courses.php — public-side visibility switch for the free-courses
// section, backed by the site_settings row seeded in
// databases/migrations/035_add_course_dates_and_free_toggle.sql.
// Admin toggles it from public/admin/free-courses/index.php.

if (!function_exists('dazu_free_courses_enabled')) {
    function dazu_free_courses_enabled(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        // Defaults to enabled: a missing/un-migrated setting should never silently
        // hide the section for every visitor.
        $cache = true;

        try {
            if (!class_exists('SettingModel')) {
                require_once app_path('models/SettingModel.php');
            }
            global $pdo;
            if (!isset($pdo)) {
                require_once app_path('config/db_connect.php');
            }
            $settings = new SettingModel($pdo);
            $value = $settings->get('free_courses_enabled');
            if ($value !== null && $value !== '') {
                $cache = $value !== '0';
            }
        } catch (\Throwable $e) {
            // Table/column not migrated yet, or DB unreachable — keep the safe default.
        }

        return $cache;
    }
}
