<?php
// app/v1/services/CoursePageService.php
// Course landing pages (migration 101): a standalone page for one course at the site root, e.g. dazu.ac.ke/software-engineering.
// Made in Admin → Courses → Course pages: pick a course and cp_generate() writes the page from the course record (cover, overview,
// modules, requirements, schedule, fee); the admin can then edit every part. Not in the navigation (used in ads); listed in the sitemap.
// Each page carries its own meta title, description, keywords and the course cover as the share picture (Facebook, WhatsApp, X, LinkedIn).
//
// Content format:
//   sections_json: [{"title": "...", "body": "..."}]  body: paragraphs; lines starting "- " become a list; "Q: ..." then "A: ..." an FAQ.
//   cards_json:    [{"title": "...", "text": "..."}]  the carousel.

require_once __DIR__ . '/../helpers/course_share.php';
require_once __DIR__ . '/../helpers/dazu_theme.php';

if (!function_exists('cp_installed')) {

    function cp_installed(PDO $db): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { $db->query('SELECT 1 FROM course_pages LIMIT 1'); $ok = true; } catch (Throwable $e) { $ok = false; }
        }
        return $ok;
    }

    /** Addresses a page can never take: every folder and file at the site root, and the clean addresses the router uses. */
    function cp_reserved(): array
    {
        $r = ['about', 'apply', 'blogs', 'blog', 'contact', 'courses', 'gallery', 'projects', 'services', 'store', 'testimonials', 'verify', 'login',
              'forgot-password', 'reset-password', 'robots', 'sitemap', 'index', 'admin', 'superadmin', 'tutor', 'students', 'intern', 'api', 'auth', 'assets', 'uploads'];
        foreach (glob(dirname(__DIR__, 3) . '/public/*') ?: [] as $f) $r[] = strtolower(preg_replace('/\.php$/', '', basename($f)));
        return array_values(array_unique($r));
    }

    function cp_slugify(string $t): string
    {
        $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t), '-'));
        return substr($s, 0, 100) ?: 'course';
    }

    /** A free address for a course page: the course name, then -course, -2, -3… */
    function cp_unique_slug(PDO $db, string $want, int $exceptId = 0): string
    {
        $base = cp_slugify($want);
        $taken = fn($s) => in_array($s, cp_reserved(), true) || (function () use ($db, $s, $exceptId) {
            $st = $db->prepare('SELECT id FROM course_pages WHERE slug = ? AND id <> ?');
            $st->execute([$s, $exceptId]);
            return (bool) $st->fetchColumn();
        })();
        if (!$taken($base)) return $base;
        if (!$taken($base . '-course')) return $base . '-course';
        for ($i = 2; ; $i++) if (!$taken($base . '-' . $i)) return $base . '-' . $i;
    }

    function cp_url(array $page): string
    {
        return course_share_absolute_url(url($page['slug']));
    }

    function cp_get(PDO $db, int $id): ?array
    {
        $st = $db->prepare('SELECT * FROM course_pages WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    function cp_get_by_slug(PDO $db, string $slug): ?array
    {
        if (!cp_installed($db) || !preg_match('/^[a-z0-9-]{1,120}$/', $slug)) return null;
        $st = $db->prepare('SELECT * FROM course_pages WHERE slug = ? AND is_published = 1');
        $st->execute([$slug]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ---------------- the smart content writer ---------------- */

    /** The field a course belongs to (title + category): words for the wording, careers and keywords. */
    function cp_field(array $course): array
    {
        $t = mb_strtolower(($course['title'] ?? '') . ' ' . ($course['category_name'] ?? ''));
        $f = [
            ['/secur|cyber|cuber|hack|forensic|network/', 'cybersecurity', 'protect systems, networks and data from real attacks',
             ['Cybersecurity Analyst', 'SOC Analyst', 'Network Security Technician', 'Penetration Tester (junior)', 'IT Support Specialist'], ['cybersecurity course', 'ethical hacking', 'network security']],
            ['/data|analy|excel|power ?bi|statist|machine learning/', 'data', 'turn raw data into clear answers and reports',
             ['Data Analyst', 'Business Intelligence Analyst', 'Reporting Officer', 'Junior Data Scientist', 'M&E Officer'], ['data analysis course', 'data science', 'power bi', 'python for data']],
            ['/\bai\b|artificial|generative|automation|prompt/', 'artificial intelligence', 'use AI tools and automation to work faster and smarter',
             ['AI Automation Specialist', 'Prompt Engineer', 'AI Content Specialist', 'Digital Innovation Officer'], ['ai course', 'generative ai', 'ai automation']],
            ['/design|\bui\b|\bux\b|graphic|figma/', 'design', 'design clear, beautiful and user-friendly products',
             ['UI/UX Designer', 'Product Designer', 'Graphic Designer', 'Web Designer'], ['ui ux course', 'figma', 'product design']],
            ['/web|software|program|python|java|develop|coding|mpesa|hosting|wordpress|app/', 'software development', 'build real websites, apps and systems',
             ['Software Developer', 'Web Developer', 'Backend Developer', 'Frontend Developer', 'IT Support Specialist'], ['software development course', 'web development', 'coding bootcamp']],
            ['/market|social media|virtual assist|content|seo|digital|video/', 'digital skills', 'grow brands and businesses online',
             ['Digital Marketer', 'Social Media Manager', 'Content Creator', 'Virtual Assistant'], ['digital marketing course', 'social media management']],
        ];
        foreach ($f as [$re, $name, $promise, $careers, $kw]) if (preg_match($re, $t)) return compact('name', 'promise', 'careers', 'kw');
        return ['name' => 'technology', 'promise' => 'gain practical, job-ready technology skills', 'careers' => ['IT Assistant', 'Technology Officer', 'Freelancer'], 'kw' => ['tech course']];
    }

    /** The course's modules: curriculum units, else subjects, else the syllabus lines. */
    function cp_modules(PDO $db, array $course): array
    {
        $q = function (string $sql) use ($db, $course) {
            try { $st = $db->prepare($sql); $st->execute([(int) $course['id']]); return $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return []; }
        };
        $rows = $q('SELECT title, description FROM curriculum_units WHERE course_id = ? AND is_active = 1 ORDER BY sort_order, id');
        if (!$rows) $rows = array_map(fn($r) => ['title' => $r['name'], 'description' => ''], $q('SELECT name FROM course_subjects WHERE course_id = ? AND is_active = 1 ORDER BY sort_order, id'));
        if (!$rows) $rows = array_map(fn($t) => ['title' => $t, 'description' => ''], dazu_course_syllabus($course));
        $out = [];
        foreach ($rows as $r) {
            $t = trim(preg_replace('/^\s*(unit|module|week|topic)\s*\d+\s*[:.\-–]*\s*/i', '', strip_tags((string) $r['title'])));
            if ($t !== '' && mb_strlen($t) <= 120) $out[] = ['title' => $t, 'description' => trim(strip_tags((string) ($r['description'] ?? '')))];
        }
        return array_slice($out, 0, 10);
    }

    function cp_sentences(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
        $parts = preg_split('/(?<=[.!?])\s+/', $text) ?: [];
        return trim(implode(' ', array_slice($parts, 0, $max)));
    }

    function cp_cut(string $t, int $n): string
    {
        return mb_strlen($t) <= $n ? $t : rtrim(mb_substr($t, 0, $n - 1), " ,.;:-") . '…';
    }

    /** Write the whole page from the course record. */
    function cp_generate(PDO $db, array $course): array
    {
        $title = trim((string) $course['title']);
        $nice = preg_match('/[A-Z]/', $title) ? $title : mb_convert_case($title, MB_CASE_TITLE, 'UTF-8');   // "cubersecurity" → "Cubersecurity"
        $f = cp_field($course);
        $overview = trim(strip_tags((string) ($course['overview'] ?? '')));
        $desc = trim(strip_tags((string) ($course['description'] ?? '')));
        $about = $overview !== '' ? $overview : ($desc !== '' && !str_contains($desc, "\n") ? $desc : '');
        $months = (int) ($course['duration_months'] ?? 0);
        $dur = $months > 0 ? $months . ' month' . ($months > 1 ? 's' : '') : 'a few weeks';
        $mods = cp_modules($db, $course);
        $modNames = array_column($mods, 'title');
        $fee = (float) ($course['course_fee'] ?? 0);
        $free = !empty($course['is_free']) || $fee <= 0;
        $req = dazu_course_requirements($course);

        $lead = $about !== '' ? cp_sentences($about, 2) : 'Learn to ' . $f['promise'] . ' with hands-on classes, real projects and tutors who work in the field.';
        $sections = [];
        $sections[] = ['title' => 'About the ' . $nice . ' course', 'body' =>
            ($about !== '' ? cp_sentences($about, 5) . "\n\n" : '')
            . 'At Dazu Training Institute you learn ' . $f['name'] . ' by doing. Every topic is taught with practical exercises, assignments and projects that your tutor marks, '
            . 'so by the end of the ' . $dur . ' you can ' . $f['promise'] . ' and show the work you have done.'];
        if ($modNames) {
            $sections[] = ['title' => 'What you will learn', 'body' => "In this course you will cover:\n" . implode("\n", array_map(fn($m) => '- ' . $m, $modNames))];
        }
        $sections[] = ['title' => 'Who this course is for', 'body' =>
            "- School leavers and students who want a practical skill that leads to work\n- Professionals moving into " . $f['name'] . "\n- Business owners who want to " . $f['promise'] . "\n- Anyone who learns best by building real projects"];
        $sections[] = ['title' => 'Careers this course prepares you for', 'body' => implode("\n", array_map(fn($c) => '- ' . $c, $f['careers']))];
        $how = ['- Duration: ' . $dur, '- Study online (live classes) or in person at our Nairobi and Thika campuses',
                '- Assignments, monthly projects and a final project marked by your tutor', '- Certificate of Completion from Dazu Training Institute'];
        if (!empty($course['study_schedule'])) array_splice($how, 1, 0, ['- Class days: ' . trim((string) $course['study_schedule'])]);
        $how[] = $free ? '- Fee: free' : '- Fee: KSh ' . number_format($fee) . ($months > 1 ? ' in total, payable monthly' : '');
        $sections[] = ['title' => 'How the course runs', 'body' => implode("\n", $how)];
        $sections[] = ['title' => 'What you need', 'body' => implode("\n", array_map(fn($r) => '- ' . $r, $req))];
        $sections[] = ['title' => 'Frequently asked questions', 'body' => implode("\n", [
            'Q: Do I need experience before joining?', 'A: No. The course starts from the basics and builds up step by step, with your tutor guiding you.',
            'Q: Can I study online?', 'A: Yes. You can join live online classes or attend in person at our campus, whichever suits you.',
            'Q: Will I get a certificate?', 'A: Yes. When you complete the course you receive a Certificate of Completion that employers can verify online.',
            'Q: How do I join?', 'A: Fill in the short form on this page. We will contact you with your admission details and the next intake date.',
        ])];

        // Carousel: one card per module (up to 8), else what makes the course different.
        $cards = [];
        foreach (array_slice($mods, 0, 8) as $i => $m) {
            $cards[] = ['title' => $m['title'], 'text' => $m['description'] !== '' ? cp_cut(cp_sentences($m['description'], 2), 150)
                : 'Hands-on lessons and practice in ' . mb_strtolower($m['title']) . ', taught step by step with real examples.'];
        }
        if (count($cards) < 3) {
            foreach ([['Learn by doing', 'Every class has practical work, so you build skills you can use from day one.'],
                      ['Expert tutors', 'Learn from tutors who work in ' . $f['name'] . ' and mark every project you hand in.'],
                      ['Real projects', 'Finish with a portfolio of projects that shows employers what you can do.'],
                      ['Online or on campus', 'Join live online or attend in person in Nairobi or Thika.'],
                      ['Recognised certificate', 'Get a Certificate of Completion that anyone can verify at our website.']] as [$t, $x]) $cards[] = ['title' => $t, 'text' => $x];
        }

        $metaTitle = cp_cut($nice . ' Course in Kenya | Dazu Training Institute', 70);
        if (mb_strlen($metaTitle) > 60) $metaTitle = cp_cut($nice . ' Course | Dazu Training', 60);
        $metaDesc = cp_cut('Join the ' . $nice . ' course at Dazu Training Institute: ' . $dur . ', hands-on, online or in Nairobi and Thika. ' . cp_sentences($lead, 1), 160);
        $kw = array_unique(array_filter(array_merge([mb_strtolower($nice), mb_strtolower($nice) . ' course', mb_strtolower($nice) . ' course in kenya'], $f['kw'],
            array_map('mb_strtolower', array_slice($modNames, 0, 4)), ['dazu training institute', 'nairobi', 'thika', 'online course'])));

        return [
            'meta_title' => $metaTitle, 'meta_description' => $metaDesc, 'keywords' => cp_cut(implode(', ', $kw), 255),
            'hero_heading' => cp_cut(preg_match('/^(diploma|certificate|higher diploma)\b/i', $nice) ? 'Earn your ' . $nice : 'Become job-ready in ' . $nice, 150), 'hero_text' => cp_cut($lead, 400),
            'video_url' => cp_course_video($db, $course), 'sections' => $sections, 'cards' => $cards,
        ];
    }

    /** A video for the course: the course overview video a tutor added, if any. */
    function cp_course_video(PDO $db, array $course): ?string
    {
        try {
            $st = $db->prepare("SELECT video_url, video_path FROM tutor_course_overviews WHERE course_id = ? AND (COALESCE(video_url,'') <> '' OR COALESCE(video_path,'') <> '') ORDER BY updated_at DESC LIMIT 1");
            $st->execute([(int) $course['id']]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if ($r) return trim((string) ($r['video_url'] ?: $r['video_path'])) ?: null;
        } catch (Throwable $e) {
        }
        return null;
    }

    /* ---------------- save ---------------- */

    /** Create the page for a course (written by cp_generate). @return array{ok:bool, id?:int, error?:string} */
    function cp_create(PDO $db, int $courseId, int $actor): array
    {
        require_once __DIR__ . '/../models/CourseModel.php';
        $course = (new \Models\CourseModel($db))->getById($courseId);
        if (!$course) return ['ok' => false, 'error' => 'Course not found.'];
        $st = $db->prepare('SELECT id FROM course_pages WHERE course_id = ?');
        $st->execute([$courseId]);
        if ($id = (int) $st->fetchColumn()) return ['ok' => true, 'id' => $id];
        $g = cp_generate($db, $course);
        $db->prepare('INSERT INTO course_pages (course_id, slug, meta_title, meta_description, keywords, hero_heading, hero_text, video_url, sections_json, cards_json, created_by, updated_by)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$courseId, cp_unique_slug($db, $course['title']), $g['meta_title'], $g['meta_description'], $g['keywords'], $g['hero_heading'], $g['hero_text'], $g['video_url'],
                      json_encode($g['sections'], JSON_UNESCAPED_UNICODE), json_encode($g['cards'], JSON_UNESCAPED_UNICODE), $actor ?: null, $actor ?: null]);
        return ['ok' => true, 'id' => (int) $db->lastInsertId()];
    }

    /** "Title" lines + body, as typed in the editor → sections. */
    function cp_parse_sections(array $titles, array $bodies): array
    {
        $out = [];
        foreach ($titles as $i => $t) {
            $t = mb_substr(trim((string) $t), 0, 120);
            $b = mb_substr(trim(str_replace("\r", '', (string) ($bodies[$i] ?? ''))), 0, 4000);
            if ($t !== '' || $b !== '') $out[] = ['title' => $t, 'body' => $b];
        }
        return array_slice($out, 0, 15);
    }

    /** "Title | text" per line → cards. */
    function cp_parse_cards(string $text): array
    {
        $out = [];
        foreach (explode("\n", str_replace("\r", '', $text)) as $l) {
            if (trim($l) === '') continue;
            [$t, $x] = array_pad(array_map('trim', explode('|', $l, 2)), 2, '');
            if ($t !== '') $out[] = ['title' => mb_substr($t, 0, 80), 'text' => mb_substr($x, 0, 220)];
        }
        return array_slice($out, 0, 12);
    }

    /** Save the editor. @return array{ok:bool, error?:string} */
    function cp_save(PDO $db, int $id, array $in, int $actor): array
    {
        $page = cp_get($db, $id);
        if (!$page) return ['ok' => false, 'error' => 'Page not found.'];
        $slug = cp_slugify((string) ($in['slug'] ?? $page['slug']));
        if (in_array($slug, cp_reserved(), true)) return ['ok' => false, 'error' => '"' . $slug . '" is used by another part of the website. Choose another address.'];
        if (cp_unique_slug($db, $slug, $id) !== $slug) return ['ok' => false, 'error' => 'Another course page already uses dazu.ac.ke/' . $slug . '.'];
        $video = trim((string) ($in['video_url'] ?? ''));
        if ($video !== '' && !preg_match('#^(https://|/)#', $video)) return ['ok' => false, 'error' => 'The video must be a full https:// link (YouTube, Vimeo or an .mp4 file).'];
        $metaTitle = trim((string) ($in['meta_title'] ?? ''));
        $metaDesc = trim((string) ($in['meta_description'] ?? ''));
        $heading = trim((string) ($in['hero_heading'] ?? ''));
        if ($metaTitle === '' || $metaDesc === '' || $heading === '') return ['ok' => false, 'error' => 'The meta title, meta description and main heading are needed.'];
        $db->prepare('UPDATE course_pages SET slug = ?, meta_title = ?, meta_description = ?, keywords = ?, hero_heading = ?, hero_text = ?, video_url = ?, sections_json = ?,
                      cards_json = ?, is_published = ?, updated_by = ? WHERE id = ?')
           ->execute([$slug, mb_substr($metaTitle, 0, 70), mb_substr($metaDesc, 0, 170), mb_substr(trim((string) ($in['keywords'] ?? '')), 0, 255), mb_substr($heading, 0, 150),
                      mb_substr(trim((string) ($in['hero_text'] ?? '')), 0, 400), $video !== '' ? mb_substr($video, 0, 500) : null,
                      json_encode(cp_parse_sections((array) ($in['section_title'] ?? []), (array) ($in['section_body'] ?? [])), JSON_UNESCAPED_UNICODE),
                      json_encode(cp_parse_cards((string) ($in['cards'] ?? '')), JSON_UNESCAPED_UNICODE), !empty($in['is_published']) ? 1 : 0, $actor ?: null, $id]);
        return ['ok' => true];
    }

    /** Write the content again from the course (keeps the address and the published switch). */
    function cp_regenerate(PDO $db, int $id, int $actor): bool
    {
        require_once __DIR__ . '/../models/CourseModel.php';
        $page = cp_get($db, $id);
        $course = $page ? (new \Models\CourseModel($db))->getById((int) $page['course_id']) : null;
        if (!$course) return false;
        $g = cp_generate($db, $course);
        return $db->prepare('UPDATE course_pages SET meta_title = ?, meta_description = ?, keywords = ?, hero_heading = ?, hero_text = ?, video_url = COALESCE(video_url, ?),
                             sections_json = ?, cards_json = ?, updated_by = ? WHERE id = ?')
            ->execute([$g['meta_title'], $g['meta_description'], $g['keywords'], $g['hero_heading'], $g['hero_text'], $g['video_url'],
                       json_encode($g['sections'], JSON_UNESCAPED_UNICODE), json_encode($g['cards'], JSON_UNESCAPED_UNICODE), $actor ?: null, $id]);
    }

    /* ---------------- printing ---------------- */

    /** A section body as HTML: paragraphs, "- " lists, "Q:"/"A:" FAQ (escaped). */
    function cp_body_html(string $body): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = ''; $list = []; $para = []; $faq = []; $q = null;
        $flush = function () use (&$html, &$list, &$para, &$faq, $e) {
            if ($para) { $html .= '<p class="text-slate-600 leading-relaxed mb-4">' . $e(implode(' ', $para)) . '</p>'; $para = []; }
            if ($list) {
                $html .= '<ul class="grid sm:grid-cols-2 gap-x-6 gap-y-2 mb-4">' . implode('', array_map(fn($l) => '<li class="flex gap-2 text-slate-700"><span class="text-[#048C44] font-bold">✓</span><span>' . $e($l) . '</span></li>', $list)) . '</ul>';
                $list = [];
            }
            if ($faq) {
                $html .= '<div class="space-y-3">' . implode('', array_map(fn($f) => '<details class="group bg-slate-50 border border-slate-200 rounded-xl p-4"><summary class="font-bold text-slate-900 cursor-pointer list-none flex justify-between gap-3">'
                    . $e($f[0]) . '<span class="text-[#048C44] group-open:rotate-45 transition">+</span></summary><p class="text-slate-600 mt-2 leading-relaxed">' . $e($f[1]) . '</p></details>', $faq)) . '</div>';
                $faq = [];
            }
        };
        foreach (explode("\n", str_replace("\r", '', $body)) as $l) {
            $t = trim($l);
            if ($t === '') { $flush(); continue; }
            if (preg_match('/^[-•*]\s+(.*)$/u', $t, $m)) { if ($para || $faq) $flush(); $list[] = $m[1]; continue; }
            if (preg_match('/^Q:\s*(.*)$/i', $t, $m)) { if ($para || $list) $flush(); $q = $m[1]; continue; }
            if (preg_match('/^A:\s*(.*)$/i', $t, $m) && $q !== null) { $faq[] = [$q, $m[1]]; $q = null; continue; }
            if ($list || $faq) $flush();
            $para[] = $t;
        }
        $flush();
        return $html;
    }

    /** The video player: YouTube / Vimeo embed, an .mp4 file, else null. */
    /** The page's own picture, else the course cover (absolute, for sharing). */
    function cp_cover_url(array $page, ?array $course): string
    {
        $img = trim((string) ($page['cover_image'] ?? ''));
        if ($img !== '') return course_share_absolute_url(url(ltrim($img, '/')));
        return $course ? course_og_image_url($course) : '';
    }

    function cp_video_html(?string $url, string $title): ?string
    {
        $url = trim((string) $url);
        if ($url === '') return null;
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $frame = fn($src) => '<iframe src="' . $e($src) . '" title="' . $e($title) . '" class="w-full h-full" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})#', $url, $m)) return $frame('https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0');
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) return $frame('https://player.vimeo.com/video/' . $m[1]);
        if (preg_match('#\.(mp4|webm|mov)(\?|$)#i', $url)) {
            $src = str_starts_with($url, '/') ? url(ltrim($url, '/')) : $url;
            return '<video src="' . $e($src) . '" controls playsinline preload="metadata" class="w-full h-full object-cover bg-black"></video>';
        }
        return null;
    }
}
