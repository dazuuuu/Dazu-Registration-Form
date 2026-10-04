<?php
// The installable portals: app name and what the banner promises. Used by components/pwa/*.php.
if (!function_exists('pwa_portal')) {
    function pwa_portal(string $portal): array
    {
        $all = [
            'tutor'      => ['name' => 'Dazu Tutor',    'alerts' => true,  'pitch' => 'never miss a submission or a newly assigned student'],
            'students'   => ['name' => 'Dazu Student',  'alerts' => true,  'pitch' => 'never miss an assignment, a mark or an announcement'],
            'admin'      => ['name' => 'Dazu Admin',    'alerts' => false, 'pitch' => 'open the admin portal in one tap, like any other app'],
            'superadmin' => ['name' => 'DazuHub Admin', 'alerts' => false, 'pitch' => 'open finance, students and payroll in one tap'],
            'employee'   => ['name' => 'Dazu Staff',    'alerts' => false, 'pitch' => 'open your staff portal in one tap'],
        ];
        return $all[$portal] + ['portal' => $portal];
    }
}
