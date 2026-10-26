<?php
// The installable portals: app name and what the banner promises. Used by components/pwa/*.php.
if (!function_exists('pwa_portal')) {
    function pwa_portal(string $portal): array
    {
        $all = [
            'tutor'      => ['name' => 'Dazu Tutor',    'alerts' => true,  'pitch' => 'never miss a submission or a newly assigned student'],
            'students'   => ['name' => 'Dazu Student',  'alerts' => true,  'pitch' => 'never miss an assignment, a mark or an announcement'],
            'admin'      => ['name' => 'Dazu Admin',    'alerts' => true,  'pitch' => 'never miss an announcement, an application or an order'],
            'intern'     => ['name' => 'Dazu Intern', 'alerts' => true,  'pitch' => 'clock in, see tasks and get alerts, even on slow internet'],
            'superadmin' => ['name' => 'DazuHub Admin', 'alerts' => false, 'pitch' => 'open finance, students and payroll in one tap'],
            'employee'   => ['name' => 'Dazu Staff',    'alerts' => false, 'pitch' => 'open your staff portal in one tap'],
        ];
        return $all[$portal] + ['portal' => $portal];
    }
}
