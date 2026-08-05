<?php
/**
 * Shared helpers for the course filter bar.
 */

if (!function_exists('dazu_course_search_blob')) {
    /**
     * Lowercased haystack for the filter bar's search box.
     *
     * Covers title, course code, category, overview, description and tags, so
     * "sql", "pandas" or "DAT" all find the Data Analytics track — the placeholder
     * promises skills and tools, not just titles.
     *
     * Note: `tags` is only populated on the static fallback rows in
     * course_catalog_static(); live DB courses have no tags column, so for those
     * the skill/tool words come from the overview and description.
     */
    function dazu_course_search_blob(array $course): string
    {
        $tags = $course['tags'] ?? [];
        if (!is_array($tags)) {
            $tags = [];
        }

        $parts = [
            (string) ($course['title'] ?? ''),
            (string) ($course['abbreviation'] ?? ''),
            (string) ($course['category_name'] ?? ''),
            strip_tags((string) ($course['overview'] ?? '')),
            strip_tags((string) ($course['description'] ?? '')),
            implode(' ', array_filter($tags, 'is_scalar')),
        ];

        $blob = trim((string) preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts))));

        // mbstring is present on the target server, but a hard dependency here would
        // fatal the whole page rather than degrade.
        return function_exists('mb_strtolower') ? mb_strtolower($blob, 'UTF-8') : strtolower($blob);
    }
}