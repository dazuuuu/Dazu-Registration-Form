<?php
// Default Mon–Fri class schedule slots (online + physical).

if (!function_exists('class_schedule_default_entries')) {
    /** @return array<int, array{study_mode:string,day_label:string,time_label:string,notes:?string,sort_order:int,is_active:int}> */
    function class_schedule_default_entries(): array
    {
        return [
            ['study_mode' => 'online', 'day_label' => 'Monday – Friday', 'time_label' => '6:00 AM – 8:00 AM', 'notes' => 'Morning online session', 'sort_order' => 1, 'is_active' => 1],
            ['study_mode' => 'online', 'day_label' => 'Monday – Friday', 'time_label' => '9:00 AM – 11:00 AM', 'notes' => 'Morning online session', 'sort_order' => 2, 'is_active' => 1],
            ['study_mode' => 'online', 'day_label' => 'Monday – Friday', 'time_label' => '12:00 PM – 2:00 PM', 'notes' => 'Afternoon online session', 'sort_order' => 3, 'is_active' => 1],
            ['study_mode' => 'online', 'day_label' => 'Monday – Friday', 'time_label' => '3:00 PM – 5:00 PM', 'notes' => 'Afternoon online session', 'sort_order' => 4, 'is_active' => 1],
            ['study_mode' => 'online', 'day_label' => 'Monday – Friday', 'time_label' => '6:00 PM – 8:00 PM', 'notes' => 'Evening online session', 'sort_order' => 5, 'is_active' => 1],
            ['study_mode' => 'physical', 'day_label' => 'Monday – Friday', 'time_label' => '6:00 PM – 8:00 PM', 'notes' => 'Campus lab (Room 529)', 'sort_order' => 1, 'is_active' => 1],
            ['study_mode' => 'physical', 'day_label' => 'Saturday', 'time_label' => '9:00 AM – 1:00 PM', 'notes' => 'Weekend campus session', 'sort_order' => 2, 'is_active' => 1],
        ];
    }
}

if (!function_exists('class_schedule_online_time_presets')) {
    /** @return string[] */
    function class_schedule_online_time_presets(): array
    {
        return [
            '6:00 AM – 8:00 AM',
            '9:00 AM – 11:00 AM',
            '12:00 PM – 2:00 PM',
            '3:00 PM – 5:00 PM',
            '6:00 PM – 8:00 PM',
        ];
    }
}

if (!function_exists('class_schedule_day_presets')) {
    /** @return string[] */
    function class_schedule_day_presets(): array
    {
        return [
            'Monday – Friday',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
        ];
    }
}
