<?php
// Admin sidebar ordering (Settings → Sidebar Menu Order).
// The sidebar marks each top-level item with <!--nav:key-->; admin_nav_reorder() re-emits the blocks in the saved order.
// Items inside dropdowns (System, Website Content, …) are not reordered — they move with their parent.

if (!function_exists('admin_nav_items')) {
    /** Top-level sidebar items in their default order: key => [label, icon]. */
    function admin_nav_items(): array
    {
        return [
            'dashboard'        => ['Dashboard', 'fa-tachometer-alt'],
            'preview'          => ['Preview Student Portal', 'fa-eye'],
            'export'           => ['Export Site', 'fa-box-archive'],
            'import'           => ['Import Site', 'fa-file-import'],
            'updates'          => ['System Updates', 'fa-database'],
            'system'           => ['System (dropdown)', 'fa-cogs'],
            'certificates'     => ['Certificates', 'fa-certificate'],
            'id_cards'         => ['Student ID Cards', 'fa-id-card'],
            'website'          => ['Website Content (dropdown)', 'fa-globe'],
            'divider'          => ['— Divider line —', 'fa-minus'],
            'profile'          => ['My Profile', 'fa-user-circle'],
            'settings'         => ['Settings', 'fa-cog'],
            'contact_settings' => ['Contact Settings', 'fa-address-book'],
            'footer_settings'  => ['Footer & Social Links', 'fa-shoe-prints'],
            'schedule'         => ['Class Schedule', 'fa-calendar-days'],
        ];
    }

    /** Saved order (keys), with unknown keys dropped and any new items appended in default order. */
    function admin_nav_order(?SettingModel $settings): array
    {
        $defaults = array_keys(admin_nav_items());
        $saved = [];
        if ($settings) {
            $saved = json_decode((string) $settings->get('admin_nav_order', ''), true);
            $saved = is_array($saved) ? array_values(array_intersect($saved, $defaults)) : [];
        }
        return array_values(array_unique(array_merge($saved, $defaults)));
    }

    /** Re-emit the marked blocks of the sidebar HTML in the saved order. Unmarked text before the first marker stays first. */
    function admin_nav_reorder(string $html, array $order): string
    {
        $parts = preg_split('/<!--nav:([a-z_]+)-->/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!$parts || count($parts) < 3) {
            return $html;
        }
        $out = array_shift($parts);
        $blocks = [];
        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            $blocks[$parts[$i]] = $parts[$i + 1];
        }
        foreach ($order as $key) {
            if (isset($blocks[$key])) {
                $out .= $blocks[$key];
                unset($blocks[$key]);
            }
        }
        return $out . implode('', $blocks);
    }
}
