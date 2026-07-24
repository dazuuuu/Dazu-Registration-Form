<?php
/**
 * Bonfire Adventures UI helpers — design tokens and asset URLs
 * ported from https://github.com/Muigai-Kiongo/bonfireadventures.com
 */

if (!function_exists('bonfire_asset')) {
    function bonfire_asset(string $path): string
    {
        return asset('bonfire/' . ltrim($path, '/'));
    }
}

if (!function_exists('bonfire_asset_v')) {
    function bonfire_asset_v(string $path): string
    {
        if (function_exists('dazu_asset_v')) {
            return dazu_asset_v('bonfire/' . ltrim($path, '/'));
        }
        return bonfire_asset($path);
    }
}

if (!function_exists('bonfire_colors')) {
    /** @return array<string, string> */
    function bonfire_colors(): array
    {
        return [
            'gold'       => '#D4AF37',
            'gold_hover' => '#b8972e',
            'forest'     => '#0A4322',
            'green'      => '#228B22',
            'orange'     => '#D37A1F',
            'cream'      => '#F8F6F1',
            'text'       => '#444444',
            'dark'       => '#111111',
        ];
    }
}
