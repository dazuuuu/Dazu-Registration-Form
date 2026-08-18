<?php
// app/v1/helpers/course_catalog.php — shared course listing data for homepage & courses page

if (!function_exists('course_catalog_static')) {
    function course_catalog_static(): array
    {
        return [
            ['id'=>0,'title'=>'Software / Web Development','abbreviation'=>'WD',
             'overview'=>'Learn to build modern, responsive websites and full-stack applications.',
             'description'=>'','duration_months'=>6,'course_fee'=>45000,'cover_image'=>null,
             'fa_icon'=>'fa-code','card_image'=>'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=700&q=80',
             'rating'=>'4.8','tags'=>['HTML/CSS','JavaScript','React'],'is_active'=>1],
            ['id'=>0,'title'=>'Cybersecurity','abbreviation'=>'CY',
             'overview'=>'Protect systems and networks with practical security skills and ethical hacking.',
             'description'=>'','duration_months'=>5,'course_fee'=>42000,'cover_image'=>null,
             'fa_icon'=>'fa-lock','card_image'=>'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=700&q=80',
             'rating'=>'4.9','tags'=>['Network Security','Ethical Hacking'],'is_active'=>1],
            ['id'=>0,'title'=>'Data Analytics','abbreviation'=>'DA',
             'overview'=>'Turn raw data into insights using Python, SQL, and visualization tools.',
             'description'=>'','duration_months'=>5,'course_fee'=>40000,'cover_image'=>null,
             'fa_icon'=>'fa-chart-column','card_image'=>'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=700&q=80',
             'rating'=>'4.7','tags'=>['Python','SQL','Power BI'],'is_active'=>1],
            ['id'=>0,'title'=>'AI & Machine Learning','abbreviation'=>'AI',
             'overview'=>'Build intelligent systems with practical AI and machine learning projects.',
             'description'=>'','duration_months'=>6,'course_fee'=>50000,'cover_image'=>null,
             'fa_icon'=>'fa-brain','card_image'=>'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=700&q=80',
             'rating'=>'4.8','tags'=>['Python','TensorFlow','NLP'],'is_active'=>1],
        ];
    }
}

if (!function_exists('course_catalog_maps')) {
    function course_catalog_maps(): array
    {
        return [
            'faIconMap' => [
                'PY'=>'fa-code','PHP'=>'fa-code','ND'=>'fa-server','FE'=>'fa-code','FS'=>'fa-laptop-code',
                'AI'=>'fa-brain','WD'=>'fa-code','CY'=>'fa-lock','DA'=>'fa-chart-column',
            ],
            'stockImages' => [
                'WD'=>'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=700&q=80',
                'CY'=>'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=700&q=80',
                'DA'=>'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=700&q=80',
                'AI'=>'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=700&q=80',
                'PY'=>'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=700&q=80',
                'PHP'=>'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=700&q=80',
                'ND'=>'https://images.unsplash.com/photo-1627398242454-45a1465c2479?w=700&q=80',
                'FE'=>'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=700&q=80',
                'FS'=>'https://images.unsplash.com/photo-1517694712202-14dd9538aa7e?w=700&q=80',
            ],
        ];
    }
}

if (!function_exists('course_catalog_enrich')) {
    function course_catalog_enrich(array $course): array
    {
        $maps = course_catalog_maps();
        $abbr = strtoupper(trim($course['abbreviation'] ?? ''));
        $course['fa_icon']    = $course['fa_icon'] ?? ($maps['faIconMap'][$abbr] ?? 'fa-book');
        $course['card_image'] = !empty($course['cover_image'])
            ? $course['cover_image']
            : ($course['card_image'] ?? ($maps['stockImages'][$abbr] ?? $maps['stockImages']['WD']));
        $course['rating']     = $course['rating'] ?? '4.8';
        $course['tags']       = $course['tags'] ?? [];
        return $course;
    }
}

if (!function_exists('course_catalog_for_display')) {
    /**
     * @return array{0: array, 1: bool} courses list, whether static fallback is used
     */
    function course_catalog_for_display(\Models\CourseModel $courseModel): array
    {
        $live = [];
        try {
            // Same listing the admin grid uses (minus the paid/free split), so a
            // course that is Active in admin is not replaced by the demo catalog.
            $live = $courseModel->listFiltered(null, null, 'active', null);
        } catch (Throwable $e) {
            $live = [];
        }
        if ($live === []) {
            try {
                $live = $courseModel->getAll(true);
            } catch (Throwable $e) {
                $live = [];
            }
        }
        if (!empty($live)) {
            $out = [];
            foreach ($live as $c) {
                $out[] = course_catalog_enrich($c);
            }
            return [$out, false];
        }

        try {
            $all = $courseModel->getAll(false);
        } catch (Throwable $e) {
            $all = [];
        }
        if (!empty($all)) {
            $out = [];
            foreach ($all as $c) {
                $out[] = course_catalog_enrich($c);
            }
            return [$out, false];
        }

        // Empty database — do not show the four hardcoded marketing tracks.
        // Those hid newly posted courses whenever the live query returned nothing.
        return [[], false];
    }
}
