<?php
// app/v1/helpers/brand.php — the brand images (logo, favicon) are served through public/brand.php from app/v1/assets/brand,
// so they never depend on a public/assets/... folder being uploaded, on git ignore rules, or on where the web root sits.

if (!function_exists('brand_file')) {
    /** Absolute path of a brand image, or null when it is not there. Names: logo, mark. */
    function brand_file(string $name): ?string
    {
        $map = ['logo' => 'dazu-logo.png', 'mark' => 'favicon.png'];
        if (!isset($map[$name])) {
            return null;
        }
        $file = dirname(__DIR__) . '/assets/brand/' . $map[$name];
        return is_file($file) ? $file : null;
    }

    /** Web URL of a brand image: brand_url('logo') for the full logo, brand_url('mark') for the square icon. */
    function brand_url(string $name = 'logo'): string
    {
        $file = brand_file($name);
        $v = $file ? '&v=' . filemtime($file) : '';
        return url('brand.php?f=' . rawurlencode($name) . $v);
    }
}
