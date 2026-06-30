<?php
// public/index.php — ISMAN Company · Engineering Services homepage
$page_title       = 'ISMAN Company — Engineering Services in Kenya';
$page_description = 'Stainless fabrication, welding, food processing lines, HVAC and commercial kitchens — engineered for Kenyan industry. Serving Nairobi and East Africa.';
$use_home_navbar  = true;

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* ------------------------------------------------------------------
   DATA SOURCES
   Projects, services and blogs are now pulled LIVE from the database
   using the exact same data sources as the dedicated listing pages:
     - projects : ProjectController->getProjects('published') / getCategories()
     - services : services table (status = 'published')
     - blogs    : blogs table (status = 'published')
   If the DB is unreachable or returns nothing, the homepage falls back
   to the original static showcase content so it never renders broken.
------------------------------------------------------------------ */
// The homepage is the site's front door, so it must never hard-fail. We load the
// DB layer defensively: if db_connect.php throws (e.g. the database is completely
// unreachable), $pdo / ProjectController simply won't be defined, and each section
// below catches that and renders its static showcase fallback instead.
$db_ready = false;
try {
    require_once __DIR__ . '/../app/config/db_connect.php';
    require_once __DIR__ . '/../app/controllers/ProjectController.php';
    $db_ready = isset($pdo);
} catch (Throwable $e) {
    $db_ready = false;
}

$brand = [
    'phone' => '072 411 4555',
    'email' => 'info@isman.co.ke',
    'loc'   => 'Nairobi, Kenya',
];

// Hero "live" panel (static — not DB-driven)
$heroBars = [
    ['label' => 'Commercial Kitchens', 'pct' => 85],
    ['label' => 'HVAC Systems',        'pct' => 72],
    ['label' => 'Hospital Equipment',  'pct' => 60],
];

// Hero full-bleed background slideshow (drives the NN/NN counter).
$heroSlides = [
    'https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?w=1920&q=80&auto=format&fit=crop', // welding sparks
    'https://images.unsplash.com/photo-1565793298595-6a879b1d9492?w=1920&q=80&auto=format&fit=crop', // metal fabrication
    'https://images.unsplash.com/photo-1581092160562-40aa08e78837?w=1920&q=80&auto=format&fit=crop', // industrial / HVAC
    'https://images.unsplash.com/photo-1556911220-bff31c812dba?w=1920&q=80&auto=format&fit=crop',     // stainless kitchen
];

// Headline stats row (static)
$stats = [
    ['num'=>350,'suffix'=>'+','label'=>'Projects Delivered'],
    ['num'=>15, 'suffix'=>'+','label'=>'Years Active'],
    ['num'=>98, 'suffix'=>'%','label'=>'Client Satisfaction'],
    ['num'=>24, 'suffix'=>'/7','label'=>'Support Available'],
];

// About-section stats (static)
$aboutStats = [
    ['num'=>15,'suffix'=>'+','label'=>'Years Experience'],
    ['num'=>500,'suffix'=>'+','label'=>'Projects Completed'],
    ['num'=>200,'suffix'=>'+','label'=>'Happy Clients'],
    ['num'=>8,'suffix'=>'','label'=>'Core Services'],
];

// Project gallery (static showcase)
$gallery = [
    'https://images.unsplash.com/photo-1556911220-bff31c812dba?w=600&q=80&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1581094794329-c8112a89af12?w=600&q=80&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1581092580497-e0d23cbdf1dc?w=600&q=80&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=600&q=80&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=600&q=80&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?w=600&q=80&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=600&q=80&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1565793298595-6a879b1d9492?w=600&q=80&auto=format&fit=crop',
];

// Testimonials (static — no testimonials table in the schema)
$testimonials = [
    ['quote'=>'ISMAN designed and installed our 450 sqm hotel kitchen in under 8 weeks. The SS304 fabrication quality exceeded international standards, and their team worked around our operational hours without a single disruption to guests.','name'=>'James Mwangi','role'=>'General Manager, Radisson Blu Nairobi','tag'=>'Commercial Kitchen','initial'=>'J'],
    ['quote'=>'The stainless balustrade work at Two Rivers was flawless. Precision welds, perfect alignment across three floors, and delivered ahead of schedule. We have used them on every project since.','name'=>'Aisha Noor','role'=>'Project Lead, Centum Investment','tag'=>'Stainless Railing','initial'=>'A'],
    ['quote'=>'Their hospital fit-out met every infection-control requirement we set. Documentation was thorough and the finish on the SS316 surfaces is exactly what a sterile environment needs.','name'=>'Dr. Peter Otieno','role'=>'Facilities Director, Kenyatta National Hospital','tag'=>'Hospital Fit-out','initial'=>'P'],
    ['quote'=>'We commissioned a full processing line and ISMAN handled design, fabrication and install end to end. HACCP-ready, on budget, and running at full throughput from day one.','name'=>'Grace Wambui','role'=>'Operations Manager, Brookside Dairy','tag'=>'Food Processing','initial'=>'G'],
];

/* ==================================================================
   STATIC FALLBACKS — used only if the DB is empty/unreachable.
   These keep the original hand-tuned showcase content available so the
   marketing homepage is never blank.
================================================================== */
$fallbackProjectFilters = [
    'all'         => 'All',
    'kitchen'     => 'Commercial Kitchen',
    'railing'     => 'Stainless Railing',
    'hospital'    => 'Hospital Fit-out',
    'food'        => 'Food Processing',
    'hvac'        => 'HVAC Systems',
    'welding'     => 'Welding & Fabrication',
];
$fallbackProjects = [
    ['year'=>'2024','cat'=>'kitchen','catLabel'=>'Commercial Kitchen','client'=>'Carlson Rezidor Hotel Group','title'=>'Radisson Blu Nairobi','desc'=>'Full turnkey 450 sqm commercial kitchen with SS304 cooking suites, extraction hoods, cold rooms and dishwashing stations.','img'=>'https://images.unsplash.com/photo-1556911220-bff31c812dba?w=800&q=80&auto=format&fit=crop'],
    ['year'=>'2023','cat'=>'railing','catLabel'=>'Stainless Railing','client'=>'Centum Investment','title'=>'Two Rivers Mall','desc'=>'Architectural-grade SS316 glass balustrade system across 3 floors of retail and entertainment space — 220 linear meters.','img'=>'https://images.unsplash.com/photo-1519567241046-7f570eee3ce6?w=800&q=80&auto=format&fit=crop'],
    ['year'=>'2024','cat'=>'hospital','catLabel'=>'Hospital Fit-out','client'=>'Ministry of Health','title'=>'Kenyatta National Hospital','desc'=>'SS316 pharmacy dispensing counters, sluice room equipment and sterile storage solutions — infection-control compliant.','img'=>'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=800&q=80&auto=format&fit=crop'],
    ['year'=>'2023','cat'=>'food','catLabel'=>'Food Processing','client'=>'Brookside Dairy','title'=>'Ruiru Processing Line','desc'=>'HACCP-ready stainless mixing tanks, conveyors and CIP pipework for a high-throughput dairy processing facility.','img'=>'https://images.unsplash.com/photo-1581094794329-c8112a89af12?w=800&q=80&auto=format&fit=crop'],
    ['year'=>'2024','cat'=>'hvac','catLabel'=>'HVAC Systems','client'=>'Garden City Mall','title'=>'Central Air Handling','desc'=>'Design, supply and install of ducted HVAC across 8,000 sqm of retail, with zoned controls and energy recovery.','img'=>'https://images.unsplash.com/photo-1581092160562-40aa08e78837?w=800&q=80&auto=format&fit=crop'],
    ['year'=>'2023','cat'=>'welding','catLabel'=>'Welding & Fabrication','client'=>'Bamburi Cement','title'=>'Structural Steel Platform','desc'=>'Custom mild-steel access platforms and guarding fabricated and TIG-welded to spec for a plant maintenance upgrade.','img'=>'https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?w=800&q=80&auto=format&fit=crop'],
];
$fallbackServices = [
    ['icon'=>'fa-pen-ruler','color'=>'#0D9488','title'=>'3D & 2D Design','desc'=>'Professional AutoCAD engineering design in 2D and 3D — full drawing packages, utility layouts and BOQ-ready submissions.','img'=>'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=600&q=80&auto=format&fit=crop','slug'=>''],
    ['icon'=>'fa-utensils','color'=>'#2DD4BF','title'=>'Commercial Kitchen','desc'=>'End-to-end design, fabrication and installation of professional SS304 commercial kitchen equipment for restaurants, hotels and institutions.','img'=>'https://images.unsplash.com/photo-1556911220-bff31c812dba?w=600&q=80&auto=format&fit=crop','slug'=>''],
    ['icon'=>'fa-wind','color'=>'#2563EB','title'=>'HVAC Systems & Air Conditioning','desc'=>'Heating, ventilation & air conditioning — custom-engineered, installed and maintained for commercial and industrial clients across Kenya.','img'=>'https://images.unsplash.com/photo-1581092160562-40aa08e78837?w=600&q=80&auto=format&fit=crop','slug'=>''],
    ['icon'=>'fa-fire-flame-curved','color'=>'#E8902C','title'=>'Gas & TIG Welding','desc'=>'Precision gas and TIG welding for stainless steel, mild steel and aluminium — structural, decorative and food-grade applications.','img'=>'https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?w=600&q=80&auto=format&fit=crop','slug'=>''],
    ['icon'=>'fa-faucet-drip','color'=>'#0D9488','title'=>'Plumbing','desc'=>'Commercial and industrial plumbing design, installation and maintenance — hot/cold water, drainage, gas and medical gas systems.','img'=>'https://images.unsplash.com/photo-1607472586893-edb57bdc0e39?w=600&q=80&auto=format&fit=crop','slug'=>''],
    ['icon'=>'fa-suitcase-medical','color'=>'#EC4899','title'=>'Hospital & Pharmacy','desc'=>'SS316 pharmacy counters, sluice rooms, theatre equipment and medical storage solutions — HACCP and infection-control compliant.','img'=>'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=600&q=80&auto=format&fit=crop','slug'=>''],
    ['icon'=>'fa-industry','color'=>'#E8902C','title'=>'Food Processing','desc'=>'HACCP-ready food processing equipment — mixing tanks, conveyors, cutting tables and silos — custom-fabricated in food-grade SS304.','img'=>'https://images.unsplash.com/photo-1581094794329-c8112a89af12?w=600&q=80&auto=format&fit=crop','slug'=>''],
    ['icon'=>'fa-stairs','color'=>'#2563EB','title'=>'Stainless Steel Railing','desc'=>'Architecturally designed SS304/SS316 balustrades, handrails and staircase railings — mirror, satin or powder-coated finishes.','img'=>'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=600&q=80&auto=format&fit=crop','slug'=>''],
];

/* Icon + colour palette reused for DB services.
   (The services table has no icon/colour columns, so we cycle a palette
   that matches the original 8-card look. If you later add `icon` / `color`
   columns, they are used automatically.) */
$svcIconPalette = [
    ['icon'=>'fa-pen-ruler',         'color'=>'#0D9488'],
    ['icon'=>'fa-utensils',          'color'=>'#2DD4BF'],
    ['icon'=>'fa-wind',              'color'=>'#2563EB'],
    ['icon'=>'fa-fire-flame-curved', 'color'=>'#E8902C'],
    ['icon'=>'fa-faucet-drip',       'color'=>'#0D9488'],
    ['icon'=>'fa-suitcase-medical',  'color'=>'#EC4899'],
    ['icon'=>'fa-industry',          'color'=>'#E8902C'],
    ['icon'=>'fa-stairs',            'color'=>'#2563EB'],
];

/* ---------- DYNAMIC: PROJECTS ---------- */
$projects       = [];
$projectFilters = ['all' => 'All'];
try {
    if (!$db_ready) { throw new RuntimeException('Database layer unavailable'); }
    $projectController = new ProjectController($pdo);
    $dbProjects   = $projectController->getProjects('published');
    $dbCategories = $projectController->getCategories();

    // getProjects() rows expose category_id + category_name; resolve slug via categories list.
    $catById = [];
    foreach ($dbCategories as $c) {
        $catById[$c['id']] = [
            'slug' => $c['category_slug'] ?? 'all',
            'name' => $c['category_name'] ?? 'Project',
        ];
    }

    // Cap at 6 for the homepage showcase grid and normalise to the card shape.
    foreach (array_slice($dbProjects, 0, 6) as $p) {
        $cid   = $p['category_id'] ?? null;
        $slug  = ($cid !== null && isset($catById[$cid])) ? $catById[$cid]['slug'] : 'all';
        $label = $p['category_name'] ?? (($cid !== null && isset($catById[$cid])) ? $catById[$cid]['name'] : 'Project');
        $projects[] = [
            'id'       => (int)($p['id'] ?? 0),
            'year'     => !empty($p['created_at']) ? date('Y', strtotime($p['created_at'])) : '',
            'cat'      => $slug,
            'catLabel' => $label,
            'client'   => trim((string)($p['major_title'] ?? '')), // major_title fills the eyebrow slot (no client column)
            'title'    => $p['small_title'] ?? 'Untitled Project',
            'desc'     => mb_strimwidth(strip_tags((string)($p['description'] ?? '')), 0, 160, '…'),
            'img'      => (string)($p['cover_image'] ?? ''),
        ];
    }

    // Build filter tabs only from categories that are actually visible in the grid,
    // so every tab is guaranteed to have at least one card.
    foreach ($projects as $np) {
        if (!empty($np['cat']) && $np['cat'] !== 'all') {
            $projectFilters[$np['cat']] = $np['catLabel'];
        }
    }
} catch (Throwable $e) {
    $projects = [];
}
if (empty($projects)) {
    $projects       = $fallbackProjects;
    $projectFilters = $fallbackProjectFilters;
}

/* ---------- DYNAMIC: SERVICES ---------- */
$services = [];
try {
    if (!$db_ready) { throw new RuntimeException('Database layer unavailable'); }
    $stmt = $pdo->prepare("
        SELECT s.*, u.username AS creator_name
        FROM services s
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.status = 'published'
        ORDER BY s.created_at DESC
        LIMIT 8
    ");
    $stmt->execute();
    $dbServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dbServices as $i => $s) {
        $pal = $svcIconPalette[$i % count($svcIconPalette)];
        $services[] = [
            'icon'  => !empty($s['icon'])  ? $s['icon']  : $pal['icon'],   // uses DB columns if present
            'color' => !empty($s['color']) ? $s['color'] : $pal['color'],
            'title' => $s['title'] ?? 'Service',
            'desc'  => mb_strimwidth(strip_tags((string)($s['short_description'] ?? '')), 0, 150, '…'),
            'img'   => (string)($s['cover_image'] ?? ''),
            'slug'  => (string)($s['slug'] ?? ''),
        ];
    }
} catch (Throwable $e) {
    $services = [];
}
if (empty($services)) {
    $services = $fallbackServices;
}

/* ---------- DYNAMIC: BLOGS ---------- */
$blogs = [];
try {
    if (!$db_ready) { throw new RuntimeException('Database layer unavailable'); }
    $stmt = $pdo->prepare("
        SELECT b.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color,
               u.username AS author_name
        FROM blogs b
        LEFT JOIN blog_categories c ON b.category_id = c.id
        LEFT JOIN users u ON b.author_id = u.id
        WHERE b.status = 'published'
        ORDER BY b.published_at DESC
        LIMIT 3
    ");
    $stmt->execute();
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $blogs = [];
}

// Service options for the enquiry form (built from whatever services we ended up with).
$serviceOptions = array_map(fn($s) => $s['title'], $services);

/* Tiny helper: render a number+suffix that the counter JS animates. */
function statNum(int $n, string $suffix): string {
    return '<span class="stat-count" data-target="'.$n.'">0</span><span class="stat-suffix">'.htmlspecialchars($suffix).'</span>';
}

/* Return a safe <img src>: the real URL if present, otherwise a branded
   inline placeholder (matches the JS onerror fallback) so empty DB images
   never produce a broken-image icon. */
function ismanImg(string $url): string {
    $url = trim($url);
    if ($url !== '') return htmlspecialchars($url);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="450" viewBox="0 0 600 450">'
         . '<rect width="600" height="450" fill="#d7e4e2"/>'
         . '<text x="300" y="235" text-anchor="middle" font-family="Montserrat,sans-serif" '
         . 'font-size="20" font-weight="700" fill="#0a766b">ISMAN ENGINEERING</text></svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

ob_start();
?>

<style>
/* =====================================================================
   ISMAN HOMEPAGE — scoped styles (prefixed to avoid collisions).
   Relies on theme.css tokens with literal fallbacks.
===================================================================== */
.hp-main{ overflow-x: clip; }
.hp-section{ padding-block: var(--space-24, 6rem); }
.hp-eyebrow-center{ display:flex; justify-content:center; }
.hp-pill{ display:inline-flex; align-items:center; gap:8px; padding:7px 18px; border-radius:var(--radius-full,9999px); background:rgba(13,148,136,.10); color:var(--brand-primary,#0D9488); font-size:.72rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; }
.hp-pill .dot{ width:7px; height:7px; border-radius:50%; background:var(--brand-primary,#0D9488); }
.hp-h2{ font-family:var(--font-display,sans-serif); font-weight:800; font-size:clamp(2rem,4vw,2.9rem); letter-spacing:-.01em; color:var(--color-text-heading,#0A1413); text-transform:uppercase; }
.hp-h2 .accent{ color:var(--brand-primary,#0D9488); }
.hp-lead{ max-width:620px; color:var(--color-text-muted,#7B8987); font-size:1.02rem; }

/* ---------- HERO (full-bleed photo + liquid glass) ---------- */
.hp-hero{ position:relative; isolation:isolate; overflow:hidden; min-height:100svh; display:flex; align-items:center;
  padding-block:calc(var(--navbar-height,116px) + var(--space-10,2.5rem)) var(--space-16,4rem);
  background:#04201d; color:#fff; }

/* background slideshow (drives the 02/04 counter) */
.hp-hero-bg{ position:absolute; inset:0; z-index:0; }
.hp-hero-slide{ position:absolute; inset:0; background-size:cover; background-position:center;
  opacity:0; transform:scale(1.07); transition:opacity 1.4s ease, transform 8s ease; will-change:opacity, transform; }
.hp-hero-slide.is-active{ opacity:1; transform:scale(1); }

/* readability scrim with subtle teal cast */
.hp-hero-scrim{ position:absolute; inset:0; z-index:1; pointer-events:none;
  background:
    linear-gradient(90deg, rgba(3,18,16,.92) 0%, rgba(3,18,16,.6) 42%, rgba(3,18,16,.3) 100%),
    linear-gradient(0deg, rgba(3,18,16,.9) 0%, rgba(3,18,16,.1) 38%, transparent 68%),
    radial-gradient(120% 90% at 82% 18%, rgba(13,148,136,.2), transparent 55%); }

/* slide counter */
.hp-hero-count{ position:absolute; top:calc(var(--navbar-height,116px) + 22px); right:38px; z-index:4;
  font-family:var(--font-display,sans-serif); font-size:.82rem; letter-spacing:.28em; color:rgba(255,255,255,.55); }
.hp-hero-count b{ color:#fff; font-weight:800; }

/* reusable liquid-glass surface */
.hp-glass{ position:relative; isolation:isolate;
  background:linear-gradient(135deg, rgba(255,255,255,.16), rgba(255,255,255,.05));
  -webkit-backdrop-filter:blur(18px) saturate(155%); backdrop-filter:blur(18px) saturate(155%);
  border:1px solid rgba(255,255,255,.22);
  box-shadow:0 12px 40px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.45), inset 0 -1px 0 rgba(0,0,0,.18); }
.hp-glass::before{ content:''; position:absolute; inset:0; border-radius:inherit; pointer-events:none; z-index:-1;
  background:linear-gradient(180deg, rgba(255,255,255,.22) 0%, rgba(255,255,255,0) 44%); }

.hp-hero-grid{ position:relative; z-index:3; width:100%; display:grid; grid-template-columns:1.08fr .92fr; gap:var(--space-12,3rem); align-items:stretch; }
.hp-hero-text{ display:flex; flex-direction:column; align-items:flex-start; }
.hp-hero-badge{ display:inline-flex; align-items:center; gap:10px; padding:9px 18px; border-radius:var(--radius-full,9999px); color:#fff; font-size:.74rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; margin-bottom:var(--space-6,1.5rem); }
.hp-hero-badge .dot{ width:8px; height:8px; border-radius:50%; background:var(--brand-secondary,#2DD4BF); box-shadow:0 0 0 4px rgba(45,212,191,.25); }
.hp-hero h1{ font-family:var(--font-display,sans-serif); font-weight:900; font-size:clamp(2.6rem,5.4vw,4.6rem); line-height:1.02; color:#fff; text-shadow:0 2px 30px rgba(0,0,0,.55); margin:0 0 6px; }
.hp-hero-sub{ font-family:var(--font-display,sans-serif); font-weight:800; font-size:clamp(2rem,4.6vw,3.7rem); line-height:1.05; color:var(--brand-secondary,#2DD4BF); text-shadow:0 2px 24px rgba(0,0,0,.45); margin-bottom:var(--space-6,1.5rem); }
.hp-hero-sub .dot-sep{ color:#fff; }
.hp-hero-copy{ max-width:520px; color:rgba(255,255,255,.92); font-weight:500; margin-bottom:var(--space-2,.5rem); text-shadow:0 1px 12px rgba(0,0,0,.45); }
.hp-hero-copy.muted{ color:rgba(255,255,255,.66); font-weight:400; font-size:.95rem; margin-bottom:var(--space-8,2rem); }
.hp-hero-actions{ display:flex; flex-wrap:wrap; gap:var(--space-4,1rem); margin-top:auto; }
.hp-hero-actions .btn{ text-transform:uppercase; letter-spacing:.06em; font-weight:700; padding:15px 28px; }
.hp-btn-glass{ display:inline-flex; align-items:center; gap:10px; padding:15px 28px; border-radius:var(--radius-md,12px); color:#fff; font-weight:700; letter-spacing:.06em; text-transform:uppercase; font-size:.9rem; cursor:pointer; transition:transform .2s ease, background .2s ease; }
.hp-btn-glass:hover{ transform:translateY(-2px); }
.hp-hero-right{ display:flex; flex-direction:column; justify-content:space-between; gap:var(--space-6,1.5rem); }

/* Hero data panel */
.hp-hero-panel{ position:relative; border-radius:var(--radius-lg,22px); padding:24px 26px; color:#fff;
  background:linear-gradient(150deg, rgba(13,148,136,.30), rgba(6,40,37,.42));
  -webkit-backdrop-filter:blur(20px) saturate(150%); backdrop-filter:blur(20px) saturate(150%);
  border:1px solid rgba(255,255,255,.18);
  box-shadow:0 18px 50px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.4), inset 0 -1px 0 rgba(0,0,0,.2); }
.hp-hero-panel::before{ content:''; position:absolute; inset:0; border-radius:inherit; pointer-events:none;
  background:linear-gradient(180deg, rgba(255,255,255,.18) 0%, transparent 38%); }
.hp-panel-head{ display:flex; align-items:center; gap:10px; margin-bottom:20px; }
.hp-panel-head .pmark{ width:30px; height:30px; }
.hp-panel-head .ptitle{ font-family:var(--font-display,sans-serif); font-weight:800; font-size:.95rem; letter-spacing:.02em; }
.hp-panel-head .ptitle span{ color:var(--brand-secondary,#2DD4BF); display:block; font-size:.66rem; letter-spacing:.18em; }
.hp-panel-stats{ display:grid; grid-template-columns:1fr 1fr; gap:18px 14px; margin-bottom:22px; }
.hp-panel-stat .pn{ font-family:var(--font-display,sans-serif); font-weight:800; font-size:1.9rem; line-height:1; color:#fff; }
.hp-panel-stat .pn .u{ color:var(--brand-secondary,#2DD4BF); }
.hp-panel-stat .pl{ font-size:.62rem; letter-spacing:.12em; text-transform:uppercase; color:rgba(255,255,255,.6); margin-top:4px; }
.hp-panel-active{ display:flex; align-items:center; gap:8px; font-size:.72rem; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:var(--brand-secondary,#2DD4BF); margin-bottom:14px; }
.hp-panel-active .dot{ width:7px; height:7px; border-radius:50%; background:var(--brand-secondary,#2DD4BF); box-shadow:0 0 0 4px rgba(45,212,191,.2); }
.hp-bar-row{ margin-bottom:12px; }
.hp-bar-top{ display:flex; justify-content:space-between; font-size:.78rem; color:rgba(255,255,255,.85); margin-bottom:6px; }
.hp-bar-track{ height:5px; border-radius:4px; background:rgba(255,255,255,.12); overflow:hidden; }
.hp-bar-fill{ height:100%; width:0; border-radius:4px; background:linear-gradient(90deg,var(--brand-primary,#0D9488),var(--brand-secondary,#2DD4BF)); transition:width 1.2s cubic-bezier(.2,.7,.2,1); }

/* Hero contact pills (glass) */
.hp-hero-contact{ display:flex; gap:14px; flex-wrap:wrap; justify-content:flex-end; }
.hp-hc-item{ display:flex; align-items:center; gap:14px; padding:14px 22px 14px 16px; border-radius:var(--radius-full,9999px); color:#fff; text-decoration:none; transition:transform .2s ease; }
.hp-hc-item:hover{ transform:translateY(-2px); }
.hp-hc-ic{ width:42px; height:42px; border-radius:50%; background:rgba(45,212,191,.18); border:1px solid rgba(45,212,191,.45); display:flex; align-items:center; justify-content:center; color:var(--brand-secondary,#2DD4BF); flex-shrink:0; }
.hp-hc-item b{ display:block; font-size:1rem; font-weight:700; }
.hp-scroll{ position:absolute; right:36px; bottom:26px; z-index:4; display:flex; align-items:center; gap:14px; font-family:var(--font-display,sans-serif); font-size:.62rem; letter-spacing:.34em; color:rgba(255,255,255,.7); font-weight:700; }
.hp-scroll::after{ content:''; width:1px; height:48px; background:linear-gradient(to top, rgba(255,255,255,.7), rgba(255,255,255,0)); }
.hp-scroll .dot{ position:absolute; right:0; top:-6px; width:5px; height:5px; border-radius:50%; background:var(--brand-secondary,#2DD4BF); animation:hpScroll 2.2s ease-in-out infinite; }
@keyframes hpScroll{ 0%{ transform:translateY(0); opacity:0; } 30%{ opacity:1; } 100%{ transform:translateY(40px); opacity:0; } }

/* ---------- RECENT PROJECTS ---------- */
.hp-proj{ background:linear-gradient(180deg,#eaf5f3,#ffffff 240px); border-top:3px solid var(--brand-secondary,#2DD4BF); }
.hp-proj-head{ text-align:center; max-width:680px; margin:0 auto var(--space-12,3rem); display:flex; flex-direction:column; align-items:center; gap:var(--space-4,1rem); }
.hp-filters{ display:flex; flex-wrap:wrap; justify-content:center; gap:10px; margin-bottom:var(--space-12,3rem); }
.hp-filter{ padding:9px 20px; border-radius:var(--radius-full,9999px); border:1px solid var(--color-border,#E2EAE8); background:var(--color-surface,#fff); color:var(--color-text-body,#44524F); font-size:.84rem; font-weight:600; cursor:pointer; transition:all .2s ease; }
.hp-filter:hover{ border-color:var(--brand-primary,#0D9488); color:var(--brand-primary,#0D9488); }
.hp-filter.is-active{ background:var(--brand-primary,#0D9488); border-color:var(--brand-primary,#0D9488); color:#fff; }
.hp-proj-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:var(--space-6,1.5rem); }
.hp-proj-card{ background:var(--color-surface,#fff); border:1px solid var(--color-border,#E2EAE8); border-radius:var(--radius-lg,18px); overflow:hidden; display:flex; flex-direction:column; transition:transform .3s ease, box-shadow .3s ease, opacity .3s ease; }
.hp-proj-card:hover{ transform:translateY(-6px); box-shadow:var(--shadow-lg); border-color:transparent; }
.hp-proj-card.is-hidden{ display:none; }
.hp-proj-thumb{ position:relative; aspect-ratio:4/3; overflow:hidden; background:var(--color-surface-alt,#F1F6F5); }
.hp-proj-thumb img{ width:100%; height:100%; object-fit:cover; transition:transform .6s ease; }
.hp-proj-card:hover .hp-proj-thumb img{ transform:scale(1.07); }
.hp-proj-year{ position:absolute; top:14px; left:14px; background:rgba(255,255,255,.95); color:var(--color-text-heading,#0A1413); font-size:.72rem; font-weight:700; padding:4px 11px; border-radius:8px; }
.hp-proj-cat{ position:absolute; bottom:14px; left:14px; background:rgba(255,255,255,.95); color:var(--brand-primary,#0D9488); font-size:.64rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; padding:5px 11px; border-radius:6px; }
.hp-proj-body{ padding:22px 22px 24px; display:flex; flex-direction:column; flex:1; }
.hp-proj-client{ font-size:.66rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--color-text-muted,#7B8987); margin-bottom:6px; }
.hp-proj-title{ font-family:var(--font-display,sans-serif); font-size:1.18rem; font-weight:800; color:var(--color-text-heading,#0A1413); margin-bottom:10px; }
.hp-proj-desc{ font-size:.9rem; color:var(--color-text-body,#44524F); margin-bottom:18px; flex:1; }
.hp-proj-link{ display:inline-flex; align-items:center; gap:8px; font-size:.84rem; font-weight:700; color:var(--brand-primary,#0D9488); margin-top:auto; }
.hp-proj-link i{ transition:transform .2s ease; }
.hp-proj-link:hover i{ transform:translateX(4px); }
.hp-proj-empty{ grid-column:1/-1; text-align:center; padding:48px; color:var(--color-text-muted,#7B8987); }

/* ---------- STATS ROW ---------- */
.hp-stats{ padding-block:var(--space-12,3rem); }
.hp-stats-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:var(--space-6,1.5rem); margin-bottom:var(--space-10,2.5rem); }
.hp-stat{ text-align:center; padding:22px 16px; border:1px solid var(--color-border,#E2EAE8); border-radius:var(--radius-md,12px); background:var(--color-surface,#fff); }
.hp-stat .v{ font-family:var(--font-display,sans-serif); font-weight:800; font-size:2.1rem; color:var(--brand-primary,#0D9488); line-height:1; }
.hp-stat .v .stat-suffix{ color:var(--brand-primary,#0D9488); }
.hp-stat .l{ font-size:.68rem; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--color-text-muted,#7B8987); margin-top:10px; }
.hp-stats-cta{ text-align:center; }

/* ---------- 8 CORE SERVICES ---------- */
.hp-svc-band{ position:relative; padding-block:var(--space-16,4rem); text-align:center; color:#fff; overflow:hidden; }
.hp-svc-band::before{ content:''; position:absolute; inset:0; background:linear-gradient(rgba(6,40,37,.86),rgba(6,40,37,.92)),
  url('https://images.unsplash.com/photo-1581092580497-e0d23cbdf1dc?w=1600&q=80&auto=format&fit=crop') center/cover; }
.hp-svc-band-inner{ position:relative; z-index:1; }
.hp-svc-eyebrow{ font-size:.72rem; font-weight:700; letter-spacing:.22em; text-transform:uppercase; color:var(--brand-secondary,#2DD4BF); }
.hp-svc-band h2{ font-family:var(--font-display,sans-serif); font-weight:800; font-size:clamp(2rem,4.2vw,3rem); color:#fff; text-transform:uppercase; margin:10px 0; }
.hp-svc-band h2 .accent{ color:var(--brand-secondary,#2DD4BF); }
.hp-svc-band p{ color:rgba(255,255,255,.7); max-width:560px; margin:0 auto; font-size:.95rem; }
.hp-svc-wrap{ background:var(--color-surface-alt,#F1F6F5); padding-block:var(--space-16,4rem) var(--space-12,3rem); }
.hp-svc-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:var(--space-5,1.25rem); }
.hp-svc-card{ background:var(--color-surface,#fff); border:1px solid var(--color-border,#E2EAE8); border-radius:var(--radius-md,12px); overflow:hidden; display:flex; flex-direction:column; transition:transform .3s ease, box-shadow .3s ease; }
.hp-svc-card:hover{ transform:translateY(-5px); box-shadow:var(--shadow-lg); border-color:transparent; }
.hp-svc-thumb{ position:relative; height:130px; overflow:hidden; background:var(--color-surface-alt,#F1F6F5); }
.hp-svc-thumb img{ width:100%; height:100%; object-fit:cover; transition:transform .6s ease; }
.hp-svc-card:hover .hp-svc-thumb img{ transform:scale(1.08); }
.hp-svc-ic{ position:absolute; left:14px; bottom:-16px; width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:.95rem; box-shadow:0 6px 14px rgba(6,52,47,.25); }
.hp-svc-body{ padding:26px 18px 20px; display:flex; flex-direction:column; flex:1; }
.hp-svc-body h3{ font-family:var(--font-display,sans-serif); font-size:.95rem; font-weight:700; color:var(--color-text-heading,#0A1413); margin-bottom:8px; }
.hp-svc-body p{ font-size:.8rem; color:var(--color-text-body,#44524F); line-height:1.55; margin-bottom:14px; flex:1; }
.hp-svc-link{ font-size:.78rem; font-weight:700; color:var(--brand-primary,#0D9488); display:inline-flex; align-items:center; gap:6px; margin-top:auto; }
.hp-svc-link i{ transition:transform .2s ease; }
.hp-svc-link:hover i{ transform:translateX(3px); }

/* Promo bar */
.hp-promo{ margin-top:var(--space-12,3rem); position:relative; border-radius:var(--radius-lg,18px); overflow:hidden;
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:18px; padding:26px 34px; color:#fff;
  background:linear-gradient(120deg, rgba(6,52,47,.95), rgba(10,72,66,.95)); }
.hp-promo::after{ content:''; position:absolute; inset:0; background:url('https://images.unsplash.com/photo-1565793298595-6a879b1d9492?w=1400&q=80&auto=format&fit=crop') center/cover; opacity:.10; }
.hp-promo-text{ position:relative; z-index:1; }
.hp-promo-text .small{ font-size:.66rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:var(--brand-secondary,#2DD4BF); }
.hp-promo-text .big{ font-family:var(--font-display,sans-serif); font-size:1.5rem; font-weight:800; margin:4px 0 2px; }
.hp-promo-text .big em{ color:var(--brand-accent,#E8902C); font-style:normal; }
.hp-promo-text .note{ font-size:.82rem; color:rgba(255,255,255,.65); }
.hp-promo .btn{ position:relative; z-index:1; }

/* ---------- ABOUT ---------- */
.hp-about{ background:var(--color-surface,#fff); }
.hp-about-grid{ display:grid; grid-template-columns:1fr 1.1fr; gap:var(--space-16,4rem); align-items:center; }
.hp-about-media{ position:relative; }
.hp-about-media > img{ width:100%; height:440px; object-fit:cover; border-radius:var(--radius-lg,18px); }
.hp-about-tag{ position:absolute; top:18px; left:18px; background:rgba(255,255,255,.95); border-radius:10px; padding:10px 14px; display:flex; align-items:center; gap:10px; box-shadow:var(--shadow-md); }
.hp-about-tag .ti{ width:30px; height:30px; border-radius:8px; background:var(--brand-primary,#0D9488); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.8rem; }
.hp-about-tag b{ display:block; font-size:.78rem; color:var(--color-text-heading,#0A1413); }
.hp-about-tag span{ font-size:.66rem; color:var(--color-text-muted,#7B8987); }
.hp-about-badge{ position:absolute; right:-18px; bottom:28px; background:var(--brand-primary,#0D9488); color:#fff; border-radius:var(--radius-md,12px); padding:18px 22px; text-align:center; box-shadow:var(--shadow-xl); }
.hp-about-badge .n{ font-family:var(--font-display,sans-serif); font-size:1.9rem; font-weight:800; line-height:1; }
.hp-about-badge .t{ font-size:.62rem; letter-spacing:.1em; text-transform:uppercase; opacity:.9; margin-top:4px; }
.hp-about-eyebrow{ font-size:.72rem; font-weight:700; letter-spacing:.22em; text-transform:uppercase; color:var(--brand-primary,#0D9488); margin-bottom:14px; }
.hp-about h2{ font-family:var(--font-display,sans-serif); font-weight:800; font-size:clamp(1.9rem,3.6vw,2.7rem); text-transform:uppercase; line-height:1.08; margin-bottom:18px; }
.hp-about h2 .accent{ color:var(--brand-primary,#0D9488); }
.hp-about p{ color:var(--color-text-body,#44524F); margin-bottom:16px; }
.hp-about-checks{ display:grid; grid-template-columns:1fr 1fr; gap:12px 18px; margin:22px 0 26px; }
.hp-check{ display:flex; align-items:center; gap:10px; font-size:.88rem; font-weight:500; color:var(--color-text-body,#44524F); }
.hp-check i{ width:22px; height:22px; border-radius:50%; background:rgba(13,148,136,.12); color:var(--brand-primary,#0D9488); display:flex; align-items:center; justify-content:center; font-size:.62rem; flex-shrink:0; }
.hp-about-actions{ display:flex; flex-wrap:wrap; gap:14px; }

/* About stats strip */
.hp-about-stats{ background:var(--color-surface,#fff); padding-bottom:var(--space-24,6rem); }
.hp-about-stats-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:var(--space-6,1.5rem); }
.hp-as{ text-align:center; padding:26px 16px; border:1px solid var(--color-border,#E2EAE8); border-radius:var(--radius-md,12px); }
.hp-as .v{ font-family:var(--font-display,sans-serif); font-weight:800; font-size:2.2rem; color:var(--brand-primary,#0D9488); line-height:1; }
.hp-as .v .stat-suffix{ color:var(--brand-primary,#0D9488); }
.hp-as .l{ font-size:.66rem; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--color-text-muted,#7B8987); margin-top:10px; }

/* ---------- GALLERY ---------- */
.hp-gallery{ background:var(--color-surface-mint,#DFF1ED); }
.hp-gal-head{ text-align:center; max-width:620px; margin:0 auto var(--space-12,3rem); display:flex; flex-direction:column; align-items:center; gap:var(--space-4,1rem); }
.hp-gal-grid{ display:grid; grid-template-columns:repeat(4,1fr); grid-auto-rows:170px; gap:14px; }
.hp-gal-item{ position:relative; overflow:hidden; border-radius:var(--radius-md,12px); background:var(--color-surface,#fff); box-shadow:var(--shadow-sm); }
.hp-gal-item img{ width:100%; height:100%; object-fit:cover; transition:transform .6s ease; }
.hp-gal-item:hover img{ transform:scale(1.08); }
.hp-gal-item::after{ content:'\f00e'; font-family:'Font Awesome 6 Free'; font-weight:900; position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#fff; background:rgba(6,52,47,.5); opacity:0; transition:opacity .3s ease; font-size:1.2rem; }
.hp-gal-item:hover::after{ opacity:1; }
.hp-gal-item.span2{ grid-column:span 2; grid-row:span 2; }
.hp-gal-item.tall{ grid-row:span 2; }

/* ---------- TESTIMONIALS ---------- */
.hp-testi{ position:relative; color:#fff; padding-block:var(--space-24,6rem); overflow:hidden;
  background:linear-gradient(150deg, #0a4a42 0%, #06342f 55%, #042824 100%); }
.hp-testi-head{ text-align:center; max-width:560px; margin:0 auto var(--space-12,3rem); display:flex; flex-direction:column; align-items:center; gap:14px; }
.hp-testi-head .hp-pill{ background:rgba(45,212,191,.14); color:var(--brand-secondary,#2DD4BF); }
.hp-testi-head h2{ color:#fff; font-family:var(--font-display,sans-serif); font-weight:800; text-transform:uppercase; font-size:clamp(1.9rem,3.6vw,2.7rem); }
.hp-testi-head p{ color:rgba(255,255,255,.65); font-size:.95rem; }
.hp-testi-stage{ position:relative; max-width:760px; margin:0 auto; }
.hp-testi-card{ background:var(--color-surface,#fff); color:var(--color-text-body,#44524F); border-radius:var(--radius-xl,28px); padding:46px 48px 40px; text-align:center; box-shadow:var(--shadow-xl); display:none; }
.hp-testi-card.is-active{ display:block; animation:hpFade .5s ease; }
@keyframes hpFade{ from{ opacity:0; transform:translateY(10px);} to{ opacity:1; transform:none;} }
.hp-testi-quote-mark{ font-family:Georgia,serif; font-size:3rem; line-height:.6; color:var(--brand-primary,#0D9488); }
.hp-testi-stars{ color:var(--brand-accent,#E8902C); font-size:.95rem; letter-spacing:3px; margin:14px 0 18px; }
.hp-testi-text{ font-size:1.05rem; font-style:italic; color:var(--color-text-heading,#0A1413); line-height:1.7; margin-bottom:26px; }
.hp-testi-author{ display:flex; flex-direction:column; align-items:center; gap:6px; }
.hp-testi-avatar{ width:48px; height:48px; border-radius:50%; background:var(--brand-primary,#0D9488); color:#fff; display:flex; align-items:center; justify-content:center; font-family:var(--font-display,sans-serif); font-weight:800; }
.hp-testi-name{ font-weight:700; color:var(--color-text-heading,#0A1413); }
.hp-testi-role{ font-size:.82rem; color:var(--color-text-muted,#7B8987); }
.hp-testi-tag{ margin-top:6px; font-size:.62rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--brand-primary,#0D9488); }
.hp-testi-nav{ position:absolute; top:50%; transform:translateY(-50%); width:42px; height:42px; border-radius:50%; border:1px solid rgba(255,255,255,.35); background:rgba(255,255,255,.06); color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .2s ease; }
.hp-testi-nav:hover{ background:#fff; color:var(--brand-dark,#06342F); }
.hp-testi-nav.prev{ left:-12px; }
.hp-testi-nav.next{ right:-12px; }
.hp-testi-dots{ display:flex; justify-content:center; gap:9px; margin-top:26px; }
.hp-testi-dot{ width:9px; height:9px; border-radius:50%; border:none; background:rgba(255,255,255,.3); cursor:pointer; transition:all .2s ease; }
.hp-testi-dot.is-active{ background:var(--brand-secondary,#2DD4BF); width:26px; border-radius:5px; }

/* ---------- LATEST BLOGS ---------- */
.hp-blog{ background:var(--color-surface-alt,#F1F6F5); }
.hp-blog-head{ text-align:center; max-width:640px; margin:0 auto var(--space-12,3rem); display:flex; flex-direction:column; align-items:center; gap:var(--space-4,1rem); }
.hp-blog-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:var(--space-6,1.5rem); }
.hp-blog-card{ background:var(--color-surface,#fff); border:1px solid var(--color-border,#E2EAE8); border-radius:var(--radius-lg,18px); overflow:hidden; display:flex; flex-direction:column; transition:transform .3s ease, box-shadow .3s ease, border-color .3s ease; }
.hp-blog-card:hover{ transform:translateY(-6px); box-shadow:var(--shadow-lg); border-color:transparent; }
.hp-blog-thumb{ position:relative; aspect-ratio:16/10; overflow:hidden; background:var(--color-surface-alt,#F1F6F5); }
.hp-blog-thumb img{ width:100%; height:100%; object-fit:cover; transition:transform .6s ease; }
.hp-blog-card:hover .hp-blog-thumb img{ transform:scale(1.07); }
.hp-blog-badge{ position:absolute; top:14px; left:14px; background:rgba(255,255,255,.95); color:var(--brand-primary,#0D9488); font-size:.64rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; padding:5px 11px; border-radius:6px; }
.hp-blog-body{ padding:22px 22px 24px; display:flex; flex-direction:column; flex:1; }
.hp-blog-meta{ display:flex; gap:16px; font-size:.72rem; color:var(--color-text-muted,#7B8987); margin-bottom:10px; }
.hp-blog-meta span{ display:inline-flex; align-items:center; gap:6px; }
.hp-blog-title{ font-family:var(--font-display,sans-serif); font-size:1.16rem; font-weight:800; line-height:1.3; margin-bottom:10px; }
.hp-blog-title a{ color:var(--color-text-heading,#0A1413); text-decoration:none; transition:color .2s ease; }
.hp-blog-title a:hover{ color:var(--brand-primary,#0D9488); }
.hp-blog-excerpt{ font-size:.9rem; color:var(--color-text-body,#44524F); margin-bottom:18px; flex:1; }
.hp-blog-link{ display:inline-flex; align-items:center; gap:8px; font-size:.84rem; font-weight:700; color:var(--brand-primary,#0D9488); margin-top:auto; text-decoration:none; }
.hp-blog-link i{ transition:transform .2s ease; }
.hp-blog-link:hover i{ transform:translateX(4px); }
.hp-blog-cta{ text-align:center; margin-top:var(--space-12,3rem); }

/* ---------- GET IN TOUCH ---------- */
.hp-contact{ background:var(--color-surface,#fff); }
.hp-contact-grid{ display:grid; grid-template-columns:1fr 1fr; gap:var(--space-16,4rem); align-items:start; }
.hp-contact-eyebrow{ font-size:.72rem; font-weight:700; letter-spacing:.22em; text-transform:uppercase; color:var(--brand-primary,#0D9488); margin-bottom:12px; }
.hp-contact h2{ font-family:var(--font-display,sans-serif); font-weight:800; text-transform:uppercase; font-size:clamp(1.9rem,3.6vw,2.7rem); margin-bottom:14px; }
.hp-contact h2 .accent{ color:var(--brand-primary,#0D9488); }
.hp-contact-lead{ color:var(--color-text-muted,#7B8987); margin-bottom:26px; max-width:420px; }
.hp-contact-info{ display:flex; flex-direction:column; gap:16px; margin-bottom:26px; }
.hp-ci{ display:flex; align-items:center; gap:14px; }
.hp-ci-ic{ width:42px; height:42px; border-radius:10px; background:rgba(13,148,136,.10); color:var(--brand-primary,#0D9488); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.hp-ci .lab{ font-size:.62rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--color-text-muted,#7B8987); }
.hp-ci .val{ font-weight:600; color:var(--color-text-heading,#0A1413); }
.hp-map{ border-radius:var(--radius-md,12px); overflow:hidden; border:1px solid var(--color-border,#E2EAE8); }
.hp-map iframe{ display:block; width:100%; height:230px; border:0; }
.hp-form-card{ background:var(--color-surface-alt,#F1F6F5); border:1px solid var(--color-border,#E2EAE8); border-radius:var(--radius-lg,18px); padding:32px; }
.hp-form-card h3{ font-family:var(--font-display,sans-serif); font-weight:800; text-transform:uppercase; font-size:1.2rem; margin-bottom:20px; }
.hp-form-row{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.hp-field{ margin-bottom:16px; }
.hp-field label{ display:block; font-size:.66rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--color-text-muted,#7B8987); margin-bottom:6px; }
.hp-field input, .hp-field select, .hp-field textarea{ width:100%; padding:12px 14px; border:1px solid var(--color-border,#E2EAE8); border-radius:var(--radius-sm,6px); background:var(--color-surface,#fff); font-family:var(--font-body,sans-serif); font-size:.9rem; color:var(--color-text-heading,#0A1413); transition:border-color .2s ease, box-shadow .2s ease; }
.hp-field input:focus, .hp-field select:focus, .hp-field textarea:focus{ outline:none; border-color:var(--brand-primary,#0D9488); box-shadow:0 0 0 3px rgba(13,148,136,.12); }
.hp-field textarea{ resize:vertical; min-height:96px; }
.hp-form-submit{ width:100%; justify-content:center; }
.hp-form-msg{ display:none; margin-top:14px; padding:12px 14px; border-radius:var(--radius-sm,6px); background:rgba(13,148,136,.10); color:var(--brand-primary,#0D9488); font-size:.86rem; font-weight:600; }
.hp-form-msg.is-shown{ display:block; }

/* ---------- RESPONSIVE ---------- */
@media (max-width:1024px){
  .hp-hero-grid, .hp-about-grid, .hp-contact-grid{ grid-template-columns:1fr; gap:var(--space-10,2.5rem); }
  .hp-hero{ min-height:auto; }
  .hp-hero-sub .dot-sep{ color:#fff; }
  .hp-hero-contact{ justify-content:flex-start; }
  .hp-hero-count{ right:20px; }
  .hp-proj-grid, .hp-svc-grid, .hp-gal-grid, .hp-blog-grid{ grid-template-columns:repeat(2,1fr); }
  .hp-stats-grid, .hp-about-stats-grid{ grid-template-columns:repeat(2,1fr); }
  .hp-about-badge{ right:14px; }
}
@media (max-width:640px){
  .hp-scroll{ display:none; }
  .hp-hero-count{ top:calc(var(--navbar-height,72px) + 14px); right:16px; }
  .hp-hero-contact{ flex-direction:column; align-items:stretch; }
  .hp-proj-grid, .hp-svc-grid, .hp-gal-grid, .hp-blog-grid, .hp-stats-grid, .hp-about-stats-grid, .hp-form-row{ grid-template-columns:1fr; }
  .hp-panel-stats{ grid-template-columns:1fr 1fr; }
  .hp-gal-grid{ grid-auto-rows:200px; }
  .hp-gal-item.span2, .hp-gal-item.tall{ grid-column:auto; grid-row:auto; }
  .hp-about-checks{ grid-template-columns:1fr; }
  .hp-testi-card{ padding:34px 24px; }
  .hp-testi-nav.prev{ left:4px; } .hp-testi-nav.next{ right:4px; }
  .hp-promo{ flex-direction:column; align-items:flex-start; text-align:left; }
}

/* Gallery Modal Styles - matching gallery page */
.gp-modal .modal-dialog { max-width: 900px; }
.gp-modal .modal-content {
    border: none;
    border-radius: 20px;
    overflow: hidden;
    background: var(--gp-dark, #06342F);
    position: relative;
}
.gp-modal-close {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 10;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2);
    color: #fff;
    font-size: .9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background .2s ease;
}
.gp-modal-close:hover { background: rgba(255,255,255,.25); }
.gp-modal-body {
    display: grid;
    grid-template-columns: 1fr 280px;
}
.gp-modal-media {
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 400px;
}
.gp-modal-media img { width: 100%; max-height: 600px; object-fit: contain; display: block; }
.gp-modal-media iframe,
.gp-modal-media video { width: 100%; min-height: 360px; display: block; border: none; }
.gp-modal-info {
    padding: 32px 26px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: var(--gp-dark, #06342F);
    color: #fff;
}
.gp-modal-cat {
    display: inline-block;
    font-size: .62rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--brand-secondary, #2DD4BF);
    background: rgba(45,212,191,.12);
    border: 1px solid rgba(45,212,191,.25);
    border-radius: 6px;
    padding: 4px 10px;
    align-self: flex-start;
}
.gp-modal-title {
    font-family: var(--font-display, 'Montserrat', sans-serif);
    font-size: 1.3rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.25;
    margin: 0;
}
.gp-modal-desc {
    font-size: .88rem;
    color: rgba(255,255,255,.68);
    line-height: 1.65;
    margin: 0;
    flex: 1;
}
.gp-modal-type {
    font-size: .72rem;
    font-weight: 600;
    color: rgba(255,255,255,.4);
    letter-spacing: .06em;
    margin-top: auto;
}
.gp-modal-type i { margin-right: 5px; }

@media (max-width: 768px) {
    .gp-modal-body { grid-template-columns: 1fr; }
    .gp-modal-info { padding: 20px 18px 26px; }
    .gp-modal-media { min-height: 240px; }
}
</style>

<main class="hp-main">

  <!-- ============ HERO ============ -->
  <section class="hp-hero" id="home">
    <div class="hp-hero-bg" aria-hidden="true">
      <?php foreach ($heroSlides as $i => $hs): ?>
      <div class="hp-hero-slide<?php echo $i === 0 ? ' is-active' : ''; ?>" style="background-image:url('<?php echo htmlspecialchars($hs); ?>')"></div>
      <?php endforeach; ?>
    </div>
    <div class="hp-hero-scrim" aria-hidden="true"></div>
    <span class="hp-hero-count" aria-hidden="true"><b id="heroSlideNo">01</b> / <?php echo str_pad((string)count($heroSlides), 2, '0', STR_PAD_LEFT); ?></span>

    <div class="container">
      <div class="hp-hero-grid">
        <div class="hp-hero-text reveal">
          <span class="hp-hero-badge hp-glass"><span class="dot"></span> 350+ Projects Delivered</span>
          <h1>ISMAN Engineering</h1>
          <p class="hp-hero-sub">Stainless <span class="dot-sep">·</span> Welding <span class="dot-sep">·</span> Food <span class="dot-sep">·</span> Kitchens</p>
          <p class="hp-hero-copy">Railings, welding, food processing lines &amp; commercial kitchens — engineered for Kenyan industry.</p>
          <p class="hp-hero-copy muted">Serving Nairobi and across East Africa with precision metalwork and industrial fit-outs.</p>
          <div class="hp-hero-actions">
            <a href="#contact" class="btn btn--primary"><i class="far fa-calendar-check"></i> Book a Consultation</a>
            <a href="#services" class="hp-btn-glass hp-glass">Explore Services <i class="fa-solid fa-arrow-right"></i></a>
          </div>
        </div>

        <div class="hp-hero-right">
          <div class="hp-hero-panel reveal reveal-delay-2" id="heroPanel">
            <div class="hp-panel-head">
              <svg class="pmark" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M24 4l3.2 4.1 5-1.7 1.1 5.2 5.2 1.1-1.7 5L46 24l-4.1 3.2 1.7 5-5.2 1.1-1.1 5.2-5-1.7L24 44l-3.2-4.1-5 1.7-1.1-5.2-5.2-1.1 1.7-5L2 24l4.1-3.2-1.7-5 5.2-1.1L10.7 9.4l5 1.7L24 4z" fill="#2DD4BF"/>
                <circle cx="24" cy="24" r="9" fill="#06342F"/>
              </svg>
              <div class="ptitle">ISMAN<span>ENGINEERING</span></div>
            </div>
            <div class="hp-panel-stats">
              <div class="hp-panel-stat"><div class="pn"><span class="stat-count" data-target="350">0</span><span class="u">+</span></div><div class="pl">Projects Completed</div></div>
              <div class="hp-panel-stat"><div class="pn"><span class="stat-count" data-target="15">0</span><span class="u">+</span></div><div class="pl">Years Experience</div></div>
              <div class="hp-panel-stat"><div class="pn"><span class="stat-count" data-target="98">0</span><span class="u">%</span></div><div class="pl">Client Satisfaction</div></div>
              <div class="hp-panel-stat"><div class="pn">24<span class="u">/7</span></div><div class="pl">Support</div></div>
            </div>
            <div class="hp-panel-active"><span class="dot"></span> Active Projects in Kenya</div>
            <?php foreach ($heroBars as $bar): ?>
            <div class="hp-bar-row">
              <div class="hp-bar-top"><span><?php echo htmlspecialchars($bar['label']); ?></span><span><?php echo (int)$bar['pct']; ?>%</span></div>
              <div class="hp-bar-track"><div class="hp-bar-fill" data-pct="<?php echo (int)$bar['pct']; ?>"></div></div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="hp-hero-contact reveal reveal-delay-3">
            <a class="hp-hc-item hp-glass" href="tel:<?php echo preg_replace('/\s+/', '', $brand['phone']); ?>">
              <span class="hp-hc-ic"><i class="fas fa-phone-volume"></i></span>
              <b><?php echo htmlspecialchars($brand['phone']); ?></b>
            </a>
            <a class="hp-hc-item hp-glass" href="mailto:<?php echo htmlspecialchars($brand['email']); ?>">
              <span class="hp-hc-ic"><i class="fas fa-envelope"></i></span>
              <b><?php echo htmlspecialchars($brand['email']); ?></b>
            </a>
          </div>
        </div>
      </div>
    </div>
    <span class="hp-scroll">SCROLL<span class="dot"></span></span>
  </section>

  <!-- ============ RECENT PROJECTS ============ -->
  <section class="hp-section hp-proj" id="projects">
    <div class="container">
      <div class="hp-proj-head reveal">
        <span class="hp-pill"><span class="dot"></span> Trusted by Industry Leaders</span>
        <h2 class="hp-h2">Recent <span class="accent">Projects</span></h2>
        <p class="hp-lead">A curated selection of our latest engineering projects delivered across Kenya and East Africa.</p>
      </div>

      <div class="hp-filters reveal" role="tablist" aria-label="Filter projects by category">
        <?php $first = true; foreach ($projectFilters as $slug => $label): ?>
          <button class="hp-filter <?php echo $first ? 'is-active' : ''; ?>" data-filter="<?php echo htmlspecialchars($slug); ?>" role="tab" aria-selected="<?php echo $first ? 'true':'false'; ?>"><?php echo htmlspecialchars($label); ?></button>
        <?php $first = false; endforeach; ?>
      </div>

      <div class="hp-proj-grid" id="projGrid">
        <?php foreach ($projects as $i => $p):
          $plink = !empty($p['id']) ? 'projects/readmore.php?id=' . (int)$p['id'] : '#contact'; ?>
          <article class="hp-proj-card reveal reveal-delay-<?php echo ($i % 3) + 1; ?>" data-cat="<?php echo htmlspecialchars($p['cat']); ?>">
            <div class="hp-proj-thumb">
              <img src="<?php echo ismanImg($p['img']); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" loading="lazy" onerror="ismanImgFallback(this)">
              <span class="hp-proj-year"><?php echo htmlspecialchars($p['year']); ?></span>
              <span class="hp-proj-cat"><?php echo htmlspecialchars($p['catLabel']); ?></span>
            </div>
            <div class="hp-proj-body">
              <?php if (!empty($p['client'])): ?><div class="hp-proj-client"><?php echo htmlspecialchars($p['client']); ?></div><?php endif; ?>
              <h3 class="hp-proj-title"><?php echo htmlspecialchars($p['title']); ?></h3>
              <p class="hp-proj-desc"><?php echo htmlspecialchars($p['desc']); ?></p>
              <a class="hp-proj-link" href="<?php echo $plink; ?>">View Case Study <i class="fa-solid fa-arrow-right"></i></a>
            </div>
          </article>
        <?php endforeach; ?>
        <div class="hp-proj-empty" id="projEmpty" hidden>No projects in this category yet — check back soon.</div>
      </div>
    </div>
  </section>

  <!-- ============ STATS ROW ============ -->
  <section class="hp-stats">
    <div class="container">
      <div class="hp-stats-grid reveal">
        <?php foreach ($stats as $s): ?>
          <div class="hp-stat"><div class="v"><?php echo statNum($s['num'], $s['suffix']); ?></div><div class="l"><?php echo htmlspecialchars($s['label']); ?></div></div>
        <?php endforeach; ?>
      </div>
      <div class="hp-stats-cta reveal">
        <a href="projects/" class="btn btn--primary"><i class="fa-solid fa-folder-open"></i> Explore All Projects</a>
      </div>
    </div>
  </section>

  <!-- ============ CORE SERVICES ============ -->
  <section id="services">
    <div class="hp-svc-band">
      <div class="container hp-svc-band-inner reveal">
        <p class="hp-svc-eyebrow">What we do</p>
        <h2>Our <span class="accent">Core</span> Services</h2>
        <p>End-to-end engineering solutions — from concept to commissioning and ongoing maintenance.</p>
      </div>
    </div>
    <div class="hp-svc-wrap">
      <div class="container">
        <div class="hp-svc-grid">
          <?php foreach ($services as $i => $svc):
            $slink = !empty($svc['slug']) ? 'services/readmore.php?slug=' . urlencode($svc['slug']) : '#contact'; ?>
            <article class="hp-svc-card reveal reveal-delay-<?php echo ($i % 4) + 1; ?>">
              <div class="hp-svc-thumb">
                <img src="<?php echo ismanImg($svc['img']); ?>" alt="<?php echo htmlspecialchars($svc['title']); ?>" loading="lazy" onerror="ismanImgFallback(this)">
                <span class="hp-svc-ic" style="background:<?php echo htmlspecialchars($svc['color']); ?>;"><i class="fa-solid <?php echo htmlspecialchars($svc['icon']); ?>"></i></span>
              </div>
              <div class="hp-svc-body">
                <h3><?php echo htmlspecialchars($svc['title']); ?></h3>
                <p><?php echo htmlspecialchars($svc['desc']); ?></p>
                <a class="hp-svc-link" href="<?php echo $slink; ?>">View Details <i class="fa-solid fa-arrow-right"></i></a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <div class="hp-promo reveal">
          <div class="hp-promo-text">
            <div class="small">Limited time offer</div>
            <div class="big">Save Up To <em>20% OFF</em> on All Services</div>
            <div class="note">Contact us today — offer valid while slots last.</div>
          </div>
          <a href="#contact" class="btn btn--accent"><i class="far fa-calendar-check"></i> Book Now</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ ABOUT ============ -->
  <section class="hp-section hp-about" id="about">
    <div class="container">
      <div class="hp-about-grid">
        <div class="hp-about-media reveal">
          <img src="https://images.unsplash.com/photo-1581092580497-e0d23cbdf1dc?w=900&q=80&auto=format&fit=crop" alt="ISMAN engineering workshop floor" loading="lazy" onerror="ismanImgFallback(this)">
          <div class="hp-about-tag">
            <span class="ti"><i class="fa-solid fa-certificate"></i></span>
            <span><b>ISO-Grade Fabrication</b><span>SS304 · SS316 · Custom Build</span></span>
          </div>
          <div class="hp-about-badge"><div class="n">15+</div><div class="t">Years of Excellence</div></div>
        </div>
        <div class="hp-about-text reveal reveal-delay-2">
          <p class="hp-about-eyebrow">About ISMAN</p>
          <h2>Engineering <span class="accent">Excellence</span> in Kenya</h2>
          <p>ISMAN Engineering Company is a leading provider of integrated engineering solutions in Kenya. We specialise in commercial kitchen systems, HVAC, plumbing, welding and more — delivering precision-engineered results that stand the test of time.</p>
          <p>Our mission is simple: we provide solutions and take away your worries. From initial concept and 3D design to full installation and ongoing maintenance, our expert team is with you every step of the way.</p>
          <div class="hp-about-checks">
            <div class="hp-check"><i class="fa-solid fa-check"></i> Certified and experienced engineering team</div>
            <div class="hp-check"><i class="fa-solid fa-check"></i> End-to-end service from design to maintenance</div>
            <div class="hp-check"><i class="fa-solid fa-check"></i> Trusted by hospitals, hotels and food industries</div>
            <div class="hp-check"><i class="fa-solid fa-check"></i> Competitive pricing with up to 20% savings</div>
          </div>
          <div class="hp-about-actions">
            <a href="#contact" class="btn btn--primary">Get in Touch <i class="fa-solid fa-arrow-right"></i></a>
            <a href="#projects" class="btn btn--outline">Company Profile</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- About stats strip -->
  <section class="hp-about-stats">
    <div class="container">
      <div class="hp-about-stats-grid reveal">
        <?php foreach ($aboutStats as $s): ?>
          <div class="hp-as"><div class="v"><?php echo statNum($s['num'], $s['suffix']); ?></div><div class="l"><?php echo htmlspecialchars($s['label']); ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ============ GALLERY (DYNAMIC FROM DB) ============ -->
  <?php
  // Fetch gallery items from database
  $galleryItems = [];
  $galleryError = null;
  
  try {
      if ($db_ready && isset($pdo)) {
          // First check if gallery table exists
          $tableCheck = $pdo->query("SHOW TABLES LIKE 'gallery'");
          if ($tableCheck->rowCount() > 0) {
              // Fetch active gallery items with images only (for homepage grid)
              $stmt = $pdo->prepare("
                  SELECT id, title, description, file_path, thumbnail_path, media_type, category 
                  FROM gallery 
                  WHERE status = 'active' 
                  AND media_type = 'image'
                  AND file_path IS NOT NULL 
                  AND file_path != ''
                  ORDER BY is_featured DESC, sort_order ASC, created_at DESC 
                  LIMIT 8
              ");
              $stmt->execute();
              $galleryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
          }
      }
  } catch (Throwable $e) {
      $galleryError = $e->getMessage();
      error_log('[Homepage Gallery] ' . $e->getMessage());
  }
  
  // Fallback images if no gallery items in DB
  $fallbackGallery = [
      'https://images.unsplash.com/photo-1556911220-bff31c812dba?w=600&q=80&auto=format&fit=crop',
      'https://images.unsplash.com/photo-1581094794329-c8112a89af12?w=600&q=80&auto=format&fit=crop',
      'https://images.unsplash.com/photo-1581092580497-e0d23cbdf1dc?w=600&q=80&auto=format&fit=crop',
      'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=600&q=80&auto=format&fit=crop',
      'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=600&q=80&auto=format&fit=crop',
      'https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?w=600&q=80&auto=format&fit=crop',
      'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=600&q=80&auto=format&fit=crop',
      'https://images.unsplash.com/photo-1565793298595-6a879b1d9492?w=600&q=80&auto=format&fit=crop',
  ];
  
  // Determine which items to display
  $displayGallery = !empty($galleryItems) ? $galleryItems : $fallbackGallery;
  $isFromDB = !empty($galleryItems);
  ?>
  
  <section class="hp-section hp-gallery" id="gallery">
    <div class="container">
      <div class="hp-gal-head reveal">
        <span class="hp-pill">Our Portfolio</span>
        <h2 class="hp-h2">Project <span class="accent">Gallery</span></h2>
        <p class="hp-lead">A snapshot of our completed engineering projects across Kenya — from HVAC to commercial kitchens and beyond.</p>
      </div>
      <div class="hp-gal-grid reveal">
        <?php 
        $totalItems = count($displayGallery);
        foreach ($displayGallery as $i => $item):
          // Determine class for grid sizing (first item wide, some tall for variety)
          $cls = '';
          if ($i === 0 && $totalItems >= 4) $cls = 'span2';
          elseif ($i === 3 && $totalItems > 4) $cls = 'tall';
          elseif ($i === 5 && $totalItems > 6) $cls = 'span2';
          
          // Get image URL
          if ($isFromDB) {
              $imgUrl = !empty($item['file_path']) ? $item['file_path'] : $fallbackGallery[$i % count($fallbackGallery)];
              $imgAlt = htmlspecialchars($item['title'] ?? 'Project image');
              $hasLink = !empty($item['id']);
              $linkUrl = $hasLink ? '/Realestate/public/gallery/' : '#gallery';
          } else {
              $imgUrl = $item;
              $imgAlt = 'ISMAN project gallery image ' . ($i + 1);
              $hasLink = false;
              $linkUrl = '#gallery';
          }
        ?>
          <a class="hp-gal-item <?php echo $cls; ?>" 
             href="<?php echo $imgUrl; ?>" 
             <?php echo $hasLink ? 'data-bs-toggle="modal" data-bs-target="#galleryModal-' . ($i + 1) . '"' : 'target="_blank" rel="noopener"'; ?> 
             aria-label="View project image">
            <img src="<?php echo htmlspecialchars($imgUrl); ?>" 
                 alt="<?php echo $imgAlt; ?>" 
                 loading="lazy" 
                 onerror="ismanImgFallback(this)">
          </a>
        <?php endforeach; ?>
      </div>
      
      <?php if (!empty($galleryItems) && count($galleryItems) > 8): ?>
      <div class="text-center mt-5 reveal">
        <a href="/Realestate/public/gallery/" class="btn btn--primary">
          <i class="fa-regular fa-images"></i> View Full Gallery
        </a>
      </div>
      <?php endif; ?>
    </div>
  </section>
  
  <?php if (!empty($galleryItems) && $isFromDB): ?>
  <!-- Gallery Modals (for DB items only) -->
  <?php foreach ($galleryItems as $i => $item): ?>
  <div class="modal fade gp-modal" id="galleryModal-<?php echo $i + 1; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <button type="button" class="gp-modal-close" data-bs-dismiss="modal" aria-label="Close">
          <i class="fas fa-times"></i>
        </button>
        <div class="gp-modal-body">
          <div class="gp-modal-media">
            <?php if ($item['media_type'] === 'video' && !empty($item['file_path'])): ?>
              <video controls class="w-100">
                <source src="<?php echo htmlspecialchars($item['file_path']); ?>" type="video/mp4">
              </video>
            <?php else: ?>
              <img src="<?php echo htmlspecialchars($item['file_path']); ?>" 
                   class="img-fluid" 
                   alt="<?php echo htmlspecialchars($item['title']); ?>"
                   onerror="this.onerror=null;this.src='https://placehold.co/1200x800?text=Image+Unavailable'">
            <?php endif; ?>
          </div>
          <div class="gp-modal-info">
            <?php if (!empty($item['category'])): ?>
              <span class="gp-modal-cat"><?php echo htmlspecialchars($item['category']); ?></span>
            <?php endif; ?>
            <h2 class="gp-modal-title"><?php echo htmlspecialchars($item['title']); ?></h2>
            <?php if (!empty($item['description'])): ?>
              <p class="gp-modal-desc"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
            <?php endif; ?>
            <span class="gp-modal-type">
              <i class="fas fa-<?php echo $item['media_type'] === 'video' ? 'video' : 'image'; ?>"></i> 
              <?php echo ucfirst($item['media_type'] ?? 'image'); ?>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

<!-- ============ TESTIMONIALS (DYNAMIC FROM DB) ============ -->
<?php
// Fetch approved testimonials for homepage
$homeTestimonials = [];
try {
    if ($db_ready && isset($pdo)) {
        $stmt = $pdo->prepare("
            SELECT * FROM testimonials 
            WHERE status = 'approved' 
            ORDER BY is_featured DESC, sort_order ASC, created_at DESC 
            LIMIT 6
        ");
        $stmt->execute();
        $homeTestimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('Homepage testimonials error: ' . $e->getMessage());
}

// Fallback if no testimonials in DB
if (empty($homeTestimonials)) {
    $homeTestimonials = [
        ['customer_name' => 'James Mwangi', 'customer_initial' => 'J', 'rating' => 5, 'testimonial_text' => 'ISMAN designed and installed our 450 sqm hotel kitchen in under 8 weeks...', 'service_tag' => 'Commercial Kitchen', 'role' => 'General Manager, Radisson Blu Nairobi'],
        ['customer_name' => 'Aisha Noor', 'customer_initial' => 'A', 'rating' => 5, 'testimonial_text' => 'The stainless balustrade work at Two Rivers was flawless...', 'service_tag' => 'Stainless Railing', 'role' => 'Project Lead, Centum Investment'],
    ];
}
?>

<section class="hp-testi">
    <div class="container">
        <div class="hp-testi-head reveal">
            <span class="hp-pill">Client Stories</span>
            <h2>What Our Clients Say</h2>
            <p>Direct feedback from the decision-makers we partner with every day.</p>
        </div>
        <div class="hp-testi-stage reveal">
            <button class="hp-testi-nav prev" id="testiPrev" aria-label="Previous testimonial"><i class="fa-solid fa-chevron-left"></i></button>
            <div id="testiTrack">
                <?php foreach ($homeTestimonials as $i => $t): ?>
                    <figure class="hp-testi-card <?php echo $i === 0 ? 'is-active' : ''; ?>" data-index="<?php echo $i; ?>">
                        <div class="hp-testi-quote-mark">&ldquo;</div>
                        <div class="hp-testi-stars" aria-label="5 out of 5 stars">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="fa-solid fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <blockquote class="hp-testi-text"><?php echo htmlspecialchars($t['testimonial_text']); ?></blockquote>
                        <figcaption class="hp-testi-author">
                            <span class="hp-testi-avatar"><?php echo htmlspecialchars($t['customer_initial'] ?? substr($t['customer_name'], 0, 1)); ?></span>
                            <span class="hp-testi-name"><?php echo htmlspecialchars($t['customer_name']); ?></span>
                            <span class="hp-testi-role"><?php echo htmlspecialchars($t['role'] ?? ''); ?></span>
                            <span class="hp-testi-tag"><?php echo htmlspecialchars($t['service_tag'] ?? ''); ?></span>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
            <button class="hp-testi-nav next" id="testiNext" aria-label="Next testimonial"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
        <div class="hp-testi-dots" id="testiDots">
            <?php foreach ($homeTestimonials as $i => $t): ?>
                <button class="hp-testi-dot <?php echo $i === 0 ? 'is-active' : ''; ?>" data-dot="<?php echo $i; ?>" aria-label="Go to testimonial <?php echo $i + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

  <?php if (!empty($blogs)): ?>
  <!-- ============ LATEST BLOGS ============ -->
  <section class="hp-section hp-blog" id="blog">
    <div class="container">
      <div class="hp-blog-head reveal">
        <span class="hp-pill"><span class="dot"></span> From the Workshop</span>
        <h2 class="hp-h2">Latest <span class="accent">Insights</span></h2>
        <p class="hp-lead">News, technical notes and project stories from the ISMAN engineering team.</p>
      </div>
      <div class="hp-blog-grid">
        <?php foreach ($blogs as $i => $b):
          $bImg     = (string)($b['featured_image'] ?? '');
          $bExcerpt = trim(strip_tags((string)($b['excerpt'] ?? '')));
          if ($bExcerpt === '') { $bExcerpt = trim(strip_tags((string)($b['content'] ?? ''))); }
          $bExcerpt = mb_strimwidth($bExcerpt, 0, 120, '…');
          $bDate    = $b['published_at'] ?? ($b['created_at'] ?? null);
          $bSlug    = (string)($b['slug'] ?? '');
          $bLink    = $bSlug !== '' ? 'blogs/readmore.php?slug=' . urlencode($bSlug) : '#'; ?>
        <article class="hp-blog-card reveal reveal-delay-<?php echo ($i % 3) + 1; ?>">
          <div class="hp-blog-thumb">
            <img src="<?php echo ismanImg($bImg); ?>" alt="<?php echo htmlspecialchars($b['title'] ?? 'Blog post'); ?>" loading="lazy" onerror="ismanImgFallback(this)">
            <?php if (!empty($b['category_name'])): ?>
              <span class="hp-blog-badge"><?php echo htmlspecialchars($b['category_name']); ?></span>
            <?php endif; ?>
          </div>
          <div class="hp-blog-body">
            <div class="hp-blog-meta">
              <?php if ($bDate): ?><span><i class="fa-regular fa-calendar"></i> <?php echo date('M d, Y', strtotime($bDate)); ?></span><?php endif; ?>
              <span><i class="fa-regular fa-eye"></i> <?php echo number_format((int)($b['view_count'] ?? 0)); ?></span>
            </div>
            <h3 class="hp-blog-title"><a href="<?php echo $bLink; ?>"><?php echo htmlspecialchars($b['title'] ?? 'Untitled'); ?></a></h3>
            <p class="hp-blog-excerpt"><?php echo htmlspecialchars($bExcerpt); ?></p>
            <a class="hp-blog-link" href="<?php echo $bLink; ?>">Read Article <i class="fa-solid fa-arrow-right"></i></a>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <div class="hp-blog-cta reveal">
        <a href="blogs/" class="btn btn--primary"><i class="fa-regular fa-newspaper"></i> View All Articles</a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============ GET IN TOUCH ============ -->
  <section class="hp-section hp-contact" id="contact">
    <div class="container">
      <div class="hp-contact-grid">
        <div class="hp-contact-info-col reveal">
          <p class="hp-contact-eyebrow">Get in Touch</p>
          <h2>Book a <span class="accent">Service</span> Today</h2>
          <p class="hp-contact-lead">Ready to start your engineering project? Contact our team and we'll get back to you within 24 hours. Save up to 20% on selected services.</p>
          <div class="hp-contact-info">
            <a class="hp-ci" href="tel:<?php echo preg_replace('/\s+/', '', $brand['phone']); ?>">
              <span class="hp-ci-ic"><i class="fas fa-phone-volume"></i></span>
              <span><span class="lab">Phone</span><span class="val"><?php echo htmlspecialchars($brand['phone']); ?></span></span>
            </a>
            <a class="hp-ci" href="mailto:<?php echo htmlspecialchars($brand['email']); ?>">
              <span class="hp-ci-ic"><i class="fas fa-envelope"></i></span>
              <span><span class="lab">Email</span><span class="val"><?php echo htmlspecialchars($brand['email']); ?></span></span>
            </a>
            <div class="hp-ci">
              <span class="hp-ci-ic"><i class="fas fa-location-dot"></i></span>
              <span><span class="lab">Location</span><span class="val"><?php echo htmlspecialchars($brand['loc']); ?></span></span>
            </div>
          </div>
          <div class="hp-map">
            <iframe title="ISMAN Company location — Nairobi, Kenya" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                    src="https://www.google.com/maps?q=Nairobi%2C%20Kenya&output=embed"></iframe>
          </div>
        </div>

        <div class="hp-form-card reveal reveal-delay-2">
          <h3>Book / Enquire Now</h3>
<form id="enquiryForm" novalidate>
    <div class="hp-form-row">
        <div class="hp-field">
            <label for="ef-name">Full Name *</label>
            <input type="text" id="ef-name" name="name" placeholder="John Kamau" required>
        </div>
        <div class="hp-field">
            <label for="ef-phone">Phone *</label>
            <input type="tel" id="ef-phone" name="phone" placeholder="072 411 XXXX" required>
        </div>
    </div>
    <div class="hp-field">
        <label for="ef-email">Email *</label>
        <input type="email" id="ef-email" name="email" placeholder="you@example.com" required>
    </div>
    <div class="hp-field">
        <label for="ef-service">Service Required</label>
        <select id="ef-service" name="service">
            <option value="" selected disabled>Select a service</option>
            <?php foreach ($serviceOptions as $opt): ?>
                <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
            <?php endforeach; ?>
            <option value="Other">Other / Not sure</option>
        </select>
    </div>
    <div class="hp-field">
        <label for="ef-message">Message * <span style="text-transform:none;letter-spacing:0;font-weight:400;">(max 500 chars)</span></label>
        <textarea id="ef-message" name="message" maxlength="500" placeholder="Tell us about your project…" required></textarea>
    </div>
    <button type="submit" class="btn btn--primary hp-form-submit" id="submitBtn">
        <i class="fa-solid fa-paper-plane"></i> Send Enquiry
    </button>
    <div id="enquiryMsg" class="hp-form-msg" style="display:none;"></div>
</form>

        </div>
      </div>
    </div>
  </section>

</main>

<script>
(function () {
  'use strict';

  /* Branded image fallback — never show a broken-image icon. */
  window.ismanImgFallback = function (img) {
    img.onerror = null;
    img.src = "data:image/svg+xml;utf8," + encodeURIComponent(
      '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="450" viewBox="0 0 600 450">' +
      '<rect width="600" height="450" fill="#d7e4e2"/>' +
      '<path d="M300 165l16 20 25-8 5 26 26 5-8 25 20 16-20 16 8 25-26 5-5 26-25-8-16 20-16-20-25 8-5-26-26-5 8-25-20-16 20-16-8-25 26-5 5-26 25 8z" fill="#0D9488" opacity="0.55"/>' +
      '<circle cx="300" cy="225" r="34" fill="#d7e4e2"/>' +
      '<text x="300" y="320" text-anchor="middle" font-family="Montserrat,sans-serif" font-size="18" font-weight="700" fill="#0a766b">ISMAN ENGINEERING</text></svg>'
    );
  };

  /* Hero background slideshow + slide counter (respects reduced-motion) */
  (function () {
    var slides = Array.prototype.slice.call(document.querySelectorAll('.hp-hero-slide'));
    var counter = document.getElementById('heroSlideNo');
    if (slides.length < 2) return;
    var idx = 0;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    function go(next) {
      slides[idx].classList.remove('is-active');
      idx = (next + slides.length) % slides.length;
      slides[idx].classList.add('is-active');
      if (counter) counter.textContent = pad(idx + 1);
    }
    if (counter) counter.textContent = pad(1);
    if (!reduce) setInterval(function () { go(idx + 1); }, 6000);
  })();

  /* Scroll reveal */
  var revealIo = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('is-visible'); revealIo.unobserve(e.target); }
    });
  }, { threshold: 0.12 });
  document.querySelectorAll('.reveal').forEach(function (el) { revealIo.observe(el); });

  /* Number counters */
  var easeOut = function (t) { return 1 - Math.pow(1 - t, 3); };
  var countIo = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      var el = entry.target;
      var target = parseInt(el.dataset.target, 10);
      if (isNaN(target)) { countIo.unobserve(el); return; }
      var start = null, dur = 1500;
      function step(ts) {
        if (!start) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        el.textContent = Math.floor(easeOut(p) * target).toLocaleString();
        if (p < 1) requestAnimationFrame(step);
        else el.textContent = target.toLocaleString();
      }
      requestAnimationFrame(step);
      countIo.unobserve(el);
    });
  }, { threshold: 0.5 });
  document.querySelectorAll('.stat-count').forEach(function (el) { countIo.observe(el); });

  /* Hero progress bars */
  var panel = document.getElementById('heroPanel');
  if (panel) {
    var barIo = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        e.target.querySelectorAll('.hp-bar-fill').forEach(function (fill) {
          fill.style.width = (parseInt(fill.dataset.pct, 10) || 0) + '%';
        });
        barIo.unobserve(e.target);
      });
    }, { threshold: 0.4 });
    barIo.observe(panel);
  }

  /* Project filter */
  var filters = document.querySelectorAll('.hp-filter');
  var cards   = document.querySelectorAll('.hp-proj-card');
  var empty   = document.getElementById('projEmpty');
  filters.forEach(function (btn) {
    btn.addEventListener('click', function () {
      filters.forEach(function (f) { f.classList.remove('is-active'); f.setAttribute('aria-selected', 'false'); });
      btn.classList.add('is-active'); btn.setAttribute('aria-selected', 'true');
      var f = btn.dataset.filter, shown = 0;
      cards.forEach(function (c) {
        var match = (f === 'all' || c.dataset.cat === f);
        c.classList.toggle('is-hidden', !match);
        if (match) shown++;
      });
      if (empty) empty.hidden = shown !== 0;
    });
  });

  /* Testimonials carousel */
  var tCards = Array.prototype.slice.call(document.querySelectorAll('.hp-testi-card'));
  var tDots  = Array.prototype.slice.call(document.querySelectorAll('.hp-testi-dot'));
  var tIndex = 0, tTimer = null;
  function tGo(n) {
    tCards[tIndex].classList.remove('is-active');
    if (tDots[tIndex]) tDots[tIndex].classList.remove('is-active');
    tIndex = (n + tCards.length) % tCards.length;
    tCards[tIndex].classList.add('is-active');
    if (tDots[tIndex]) tDots[tIndex].classList.add('is-active');
  }
  function tStart() { clearInterval(tTimer); tTimer = setInterval(function () { tGo(tIndex + 1); }, 6000); }
  if (tCards.length) {
    var prev = document.getElementById('testiPrev'), next = document.getElementById('testiNext');
    if (prev) prev.addEventListener('click', function () { tGo(tIndex - 1); tStart(); });
    if (next) next.addEventListener('click', function () { tGo(tIndex + 1); tStart(); });
    tDots.forEach(function (d, i) { d.addEventListener('click', function () { tGo(i); tStart(); }); });
    tStart();
  }

// Enquiry form submission - Updated version
const enquiryForm = document.getElementById('enquiryForm');
if (enquiryForm) {
    enquiryForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Validate form
        if (!enquiryForm.checkValidity()) {
            enquiryForm.reportValidity();
            return;
        }
        
        const submitBtn = enquiryForm.querySelector('button[type="submit"]');
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        submitBtn.disabled = true;
        
        // Get form data
        const formData = {
            name: document.getElementById('ef-name')?.value || '',
            phone: document.getElementById('ef-phone')?.value || '',
            email: document.getElementById('ef-email')?.value || '',
            service: document.getElementById('ef-service')?.value || '',
            message: document.getElementById('ef-message')?.value || ''
        };
        
        try {
            const response = await fetch('/Realestate/public/api/submit-enquiry.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            const msgDiv = document.getElementById('enquiryMsg');
            
            if (!msgDiv) {
                // Create message div if it doesn't exist
                const newMsgDiv = document.createElement('div');
                newMsgDiv.id = 'enquiryMsg';
                newMsgDiv.className = 'hp-form-msg';
                newMsgDiv.style.cssText = 'margin-top: 15px; padding: 12px; border-radius: 8px; font-size: 14px;';
                enquiryForm.appendChild(newMsgDiv);
            }
            
            const messageDiv = document.getElementById('enquiryMsg');
            messageDiv.style.display = 'block';
            
            if (data.success) {
                messageDiv.style.background = '#d4edda';
                messageDiv.style.color = '#155724';
                messageDiv.style.border = '1px solid #c3e6cb';
                messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                enquiryForm.reset();
                
                // Auto hide after 5 seconds
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 5000);
            } else {
                messageDiv.style.background = '#f8d7da';
                messageDiv.style.color = '#721c24';
                messageDiv.style.border = '1px solid #f5c6cb';
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
            }
        } catch (error) {
            console.error('Form submission error:', error);
            const messageDiv = document.getElementById('enquiryMsg');
            if (messageDiv) {
                messageDiv.style.display = 'block';
                messageDiv.style.background = '#f8d7da';
                messageDiv.style.color = '#721c24';
                messageDiv.style.border = '1px solid #f5c6cb';
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Unable to send message. Please try again or call us directly.';
            } else {
                alert('Unable to send message. Please call us directly at <?php echo $brand['phone']; ?>');
            }
        } finally {
            submitBtn.innerHTML = originalHtml;
            submitBtn.disabled = false;
        }
    });
}

})();
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/templates/public/layout.php';
?>