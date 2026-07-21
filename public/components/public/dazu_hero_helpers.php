<?php
/**
 * Hero helpers.
 */

if (!function_exists('dazu_hero_image_url')) {
    /**
     * Resolve a hero image from public/assets/images/hero/.
     *
     * Pass the base name without an extension ("hero-1"); jpg, jpeg, png, webp and
     * avif are all accepted, so whatever gets dropped in the folder works.
     *
     * The mtime is appended as ?v=. sw.js serves images cache-first, so without it
     * replacing hero-1.jpg would keep showing the old photo to every returning
     * visitor — the same trap that hid the category filter JS.
     *
     * @param string $base     File name without extension.
     * @param string $fallback Absolute/asset URL used when nothing is found.
     */
    function dazu_hero_image_url(string $base, string $fallback = ''): string
    {
        $base = trim($base);
        // Never let a slide definition escape the hero folder.
        if ($base === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $base) || str_contains($base, '..')) {
            return $fallback;
        }

        foreach (['jpg', 'jpeg', 'png', 'webp', 'avif'] as $ext) {
            $rel  = 'images/hero/' . $base . '.' . $ext;
            $file = public_path('assets/' . $rel);
            if (is_file($file)) {
                $ver = @filemtime($file);
                return asset($rel) . ($ver ? '?v=' . $ver : '');
            }
        }

        return $fallback;
    }
}

if (!function_exists('dazu_hero_stats')) {
    /**
     * Real counts for the hero's floating cards.
     *
     * The reference design shows "7,500+ Video Courses" and "16,500+ Active
     * Students" on slide 1 and "5,369+" / "11,812+" on slide 2 — template filler
     * that contradicts itself between slides, contradicts the stats bar directly
     * below it ("1,200+ Students Trained"), and describes a video catalogue that
     * does not exist. These are the real numbers instead.
     *
     * A card with a count of 0 is not rendered rather than printing "0+".
     */
    function dazu_hero_stats(?PDO $pdo): array
    {
        $stats = ['courses' => 0, 'students' => 0];
        if (!$pdo instanceof PDO) {
            return $stats;
        }

        try {
            $stats['courses'] = (int) $pdo->query('SELECT COUNT(*) FROM courses WHERE is_active = 1')->fetchColumn();
        } catch (Throwable $e) {
            // table missing on a partial install — card just hides
        }

        try {
            $stats['students'] = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'approved'")->fetchColumn();
        } catch (Throwable $e) {
        }

        return $stats;
    }
}