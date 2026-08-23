<?php
// Public helpers for class schedule display on course cards and detail pages.

if (!function_exists('class_schedule_entries')) {
    function class_schedule_entries(string $mode, bool $activeOnly = true): array
    {
        static $cache = [];
        $mode = $mode === 'physical' ? 'physical' : 'online';
        $key = $mode . ($activeOnly ? ':active' : ':all');
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        try {
            if (!class_exists('Models\\ClassScheduleModel')) {
                require_once __DIR__ . '/../models/ClassScheduleModel.php';
            }
            global $pdo;
            if (!isset($pdo)) {
                require_once __DIR__ . '/../config/db_connect.php';
            }
            $model = new \Models\ClassScheduleModel($pdo);
            $cache[$key] = $model->getByMode($mode, $activeOnly);
        } catch (Throwable $e) {
            $cache[$key] = [];
        }

        return $cache[$key];
    }
}

if (!function_exists('class_schedule_has_any')) {
    function class_schedule_has_any(): bool
    {
        return class_schedule_entries('online') !== [] || class_schedule_entries('physical') !== [];
    }
}

if (!function_exists('class_schedule_summary')) {
    /** One-line summary for course cards. */
    function class_schedule_summary(string $mode): string
    {
        $entries = class_schedule_entries($mode);
        if ($entries === []) {
            return '';
        }
        $parts = [];
        foreach (array_slice($entries, 0, 2) as $row) {
            $parts[] = trim($row['day_label'] . ' ' . $row['time_label']);
        }
        $text = implode(' · ', $parts);
        if (count($entries) > 2) {
            $text .= ' +' . (count($entries) - 2) . ' more';
        }
        return $text;
    }
}
