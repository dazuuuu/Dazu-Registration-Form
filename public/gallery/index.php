<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/controllers/GalleryController.php';

$galleryController = new GalleryController($pdo);
$page_title        = 'Gallery - Ismano';
$page_description  = 'Explore our creative portfolio of images and videos';

$category = isset($_GET['category']) && $_GET['category'] !== '' ? $_GET['category'] : null;
$type     = isset($_GET['type']) && in_array($_GET['type'], ['image', 'video'], true) ? $_GET['type'] : null;

$filters = ['status' => 'active', 'limit' => 30, 'offset' => 0];
if ($category) $filters['category']   = $category;
if ($type)     $filters['media_type'] = $type;

$items      = [];
$categories = [];
$loadError  = null;

try {
    if (method_exists($galleryController, 'getFiltered')) {
        $items = $galleryController->getFiltered($filters);
    } else {
        $items = $galleryController->getAll('active', 30, 0);
        if ($category) {
            $items = array_values(array_filter($items, fn($i) => ($i['category'] ?? null) === $category));
        }
        if ($type) {
            $items = array_values(array_filter($items, fn($i) => ($i['media_type'] ?? null) === $type));
        }
    }
    $categories = $galleryController->getCategories();
} catch (Throwable $e) {
    $loadError = $e->getMessage();
    error_log('[public gallery] ' . $e->getMessage());
}

/**
 * Render video HTML for card or modal context.
 */
function gallery_video_html(array $item, string $context): string {
    $height = $context === 'modal' ? 'height="500"' : '';

    if (!empty($item['video_embed_code'])) {
        return $item['video_embed_code'];
    }

    if (!empty($item['video_url'])) {
        $url = $item['video_url'];

        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            if (strpos($url, 'youtu.be') !== false) {
                $vid = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
            } else {
                parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $q);
                $vid = $q['v'] ?? '';
            }
            $vid = preg_replace('/[^A-Za-z0-9_\-]/', '', $vid);
            return '<iframe src="https://www.youtube.com/embed/' . $vid . '" width="100%" ' . $height . ' frameborder="0" allowfullscreen></iframe>';
        }

        if (strpos($url, 'vimeo.com') !== false) {
            $vid = preg_replace('/[^0-9]/', '', basename(parse_url($url, PHP_URL_PATH) ?? ''));
            return '<iframe src="https://player.vimeo.com/video/' . $vid . '" width="100%" ' . $height . ' frameborder="0" allowfullscreen></iframe>';
        }

        return '<video controls class="w-100"><source src="' . htmlspecialchars($url) . '" type="video/mp4"></video>';
    }

    if (!empty($item['file_path'])) {
        return '<video controls class="w-100"><source src="' . htmlspecialchars($item['file_path']) . '" type="video/mp4">Your browser does not support the video tag.</video>';
    }

    return '';
}

/**
 * Get a thumbnail src — falls back to a branded SVG placeholder.
 */
function gal_thumb(string $path, string $title = ''): string {
    if (trim($path) !== '') return htmlspecialchars($path);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400" viewBox="0 0 600 400">'
         . '<rect width="600" height="400" fill="#d0eeea"/>'
         . '<text x="300" y="210" text-anchor="middle" font-family="sans-serif" font-size="16" font-weight="700" fill="#0a766b">ISMAN ENGINEERING</text></svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

ob_start();
?>

<?php if (isset($_GET['debug'])): ?>
<div class="container mt-3">
    <div class="alert alert-info small">
        Items: <strong><?= count($items) ?></strong> |
        Categories: <strong><?= count($categories) ?></strong> |
        Filters: <code><?= htmlspecialchars(json_encode(array_diff_key($filters, ['status' => 1]))) ?></code>
        <?php if ($loadError): ?><br><span class="text-danger">Error: <?= htmlspecialchars($loadError) ?></span><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<noscript><style>.reveal{opacity:1!important;transform:none!important;}</style></noscript>

<!-- ============================================================
     GALLERY PAGE
     ============================================================ -->
<div class="gp-wrap">

    <!-- PAGE HEADER -->
    <header class="gp-header">
        <span class="gp-eyebrow">Our Portfolio</span>
        <h1 class="gp-title">Project Gallery</h1>
        <p class="gp-lead">A snapshot of our completed engineering projects across Kenya — from HVAC to commercial kitchens and beyond.</p>
    </header>

    <div class="container gp-body">

        <!-- FILTER BAR -->
        <?php if (!empty($categories) || !empty($items)): ?>
        <nav class="gp-filters reveal" aria-label="Filter gallery">
            <a href="?" class="gp-filter <?= !$category && !$type ? 'is-active' : '' ?>">All</a>
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= urlencode($cat) ?>"
                   class="gp-filter <?= $category === $cat ? 'is-active' : '' ?>">
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
            <a href="?type=image"  class="gp-filter <?= $type === 'image'  ? 'is-active' : '' ?>"><i class="fas fa-image"></i> Images</a>
            <a href="?type=video"  class="gp-filter <?= $type === 'video'  ? 'is-active' : '' ?>"><i class="fas fa-play-circle"></i> Videos</a>
        </nav>
        <?php endif; ?>

        <?php if ($loadError && !isset($_GET['debug'])): ?>
        <div class="gp-error reveal">
            <i class="fas fa-triangle-exclamation"></i>
            We couldn't load the gallery right now. Please try again shortly.
        </div>
        <?php endif; ?>

        <?php
        // Filter out items with no displayable media
        $displayItems = array_values(array_filter($items, function ($item) {
            return !empty($item['file_path']) || !empty($item['video_url']) || !empty($item['video_embed_code']);
        }));
        ?>

        <?php if (empty($displayItems)): ?>
        <div class="gp-empty reveal">
            <i class="fas fa-images"></i>
            <p>No gallery items available yet. Please check back soon.</p>
        </div>
        <?php else: ?>

        <!-- MASONRY GRID -->
        <div class="gp-grid" id="gpGrid">
            <?php foreach ($displayItems as $index => $item):
                $isVideo = ($item['media_type'] === 'video');
                $thumb   = $isVideo
                    ? ($item['thumbnail_path'] ?? $item['file_path'] ?? '')
                    : ($item['thumbnail_path'] ?? $item['file_path'] ?? '');
                $catLabel = htmlspecialchars($item['category'] ?? '');
                $modalId  = 'gm-' . (int)$item['id'];

                // Assign span classes for visual variety (masonry-like pattern)
                $spanClass = '';
                $mod = $index % 8;
                if ($mod === 0) $spanClass = 'gp-cell--wide';          // wide (2 cols)
                elseif ($mod === 4) $spanClass = 'gp-cell--tall';      // tall (2 rows)
            ?>
            <div class="gp-cell <?= $spanClass ?> reveal"
                 data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>"
                 role="button" tabindex="0"
                 aria-label="Open <?= htmlspecialchars($item['title']) ?>">

                <!-- MEDIA THUMBNAIL -->
                <div class="gp-cell-media">
                    <?php if ($isVideo): ?>
                        <?php if (!empty($thumb)): ?>
                            <img src="<?= gal_thumb($thumb, $item['title']) ?>"
                                 alt="<?= htmlspecialchars($item['title']) ?>"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='https://placehold.co/600x400?text=Video'">
                        <?php else: ?>
                            <div class="gp-cell-video-placeholder">
                                <i class="fas fa-play-circle"></i>
                            </div>
                        <?php endif; ?>
                        <span class="gp-cell-play"><i class="fas fa-play"></i></span>
                    <?php else: ?>
                        <img src="<?= gal_thumb($item['file_path'] ?? '', $item['title']) ?>"
                             alt="<?= htmlspecialchars($item['title']) ?>"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='https://placehold.co/600x400?text=Image+Unavailable'">
                        <span class="gp-cell-zoom"><i class="fas fa-search-plus"></i></span>
                    <?php endif; ?>

                    <!-- OVERLAY -->
                    <div class="gp-cell-overlay">
                        <?php if ($catLabel): ?>
                            <span class="gp-cell-tag"><?= $catLabel ?></span>
                        <?php endif; ?>
                        <h3 class="gp-cell-title"><?= htmlspecialchars($item['title']) ?></h3>
                        <?php if (!empty($item['description'])): ?>
                            <p class="gp-cell-desc"><?= htmlspecialchars(mb_strimwidth($item['description'], 0, 80, '…')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- MODAL -->
            <div class="modal fade gp-modal" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <button type="button" class="gp-modal-close" data-bs-dismiss="modal" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="gp-modal-body">
                            <div class="gp-modal-media">
                                <?php if ($isVideo): ?>
                                    <?= gallery_video_html($item, 'modal') ?>
                                <?php else: ?>
                                    <img src="<?= htmlspecialchars($item['file_path'] ?? '') ?>"
                                         class="img-fluid"
                                         alt="<?= htmlspecialchars($item['title']) ?>"
                                         onerror="this.onerror=null;this.src='https://placehold.co/1200x800?text=Image+Unavailable'">
                                <?php endif; ?>
                            </div>
                            <div class="gp-modal-info">
                                <?php if ($catLabel): ?>
                                    <span class="gp-modal-cat"><?= $catLabel ?></span>
                                <?php endif; ?>
                                <h2 class="gp-modal-title"><?= htmlspecialchars($item['title']) ?></h2>
                                <?php if (!empty($item['description'])): ?>
                                    <p class="gp-modal-desc"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
                                <?php endif; ?>
                                <?php if ($isVideo): ?>
                                    <span class="gp-modal-type"><i class="fas fa-video"></i> Video</span>
                                <?php else: ?>
                                    <span class="gp-modal-type"><i class="fas fa-image"></i> Image</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div><!-- /.gp-grid -->

        <?php endif; ?>
    </div><!-- /.container -->
</div><!-- /.gp-wrap -->

<!-- ============================================================
     STYLES
     ============================================================ -->
<style>
/* ---- Root tokens (safe overrides, uses theme vars where available) ---- */
:root {
    --gp-teal:     #0D9488;
    --gp-teal-lt:  #2DD4BF;
    --gp-dark:     #062B27;
    --gp-bg:       #E8F8F5;      /* mint background from screenshot */
    --gp-bg2:      #d3f0ea;
    --gp-white:    #ffffff;
    --gp-text:     #1a3532;
    --gp-muted:    #5a7a76;
    --gp-radius:   16px;
    --gp-gap:      14px;
}

/* ---- Page wrap ---- */
.gp-wrap { background: var(--gp-bg); min-height: 60vh; }

/* ---- Header ---- */
.gp-header {
    background: var(--gp-bg);
    text-align: center;
    padding: 80px 20px 48px;
    border-bottom: 1px solid rgba(13,148,136,.12);
}
.gp-eyebrow {
    display: inline-block;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: var(--gp-teal);
    background: rgba(13,148,136,.1);
    border: 1px solid rgba(13,148,136,.2);
    border-radius: 100px;
    padding: 6px 16px;
    margin-bottom: 18px;
}
.gp-title {
    font-family: var(--font-display, 'Montserrat', sans-serif);
    font-size: clamp(2.4rem, 5vw, 3.8rem);
    font-weight: 900;
    color: var(--gp-dark);
    letter-spacing: -.02em;
    text-transform: uppercase;
    margin: 0 0 16px;
}
.gp-lead {
    max-width: 560px;
    margin: 0 auto;
    color: var(--gp-muted);
    font-size: 1rem;
    line-height: 1.65;
}

/* ---- Filter bar ---- */
.gp-body { padding-block: 40px 80px; }
.gp-filters {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    margin-bottom: 40px;
}
.gp-filter {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 22px;
    border-radius: 100px;
    background: rgba(255,255,255,.7);
    border: 1.5px solid rgba(13,148,136,.18);
    color: var(--gp-muted);
    font-size: .82rem;
    font-weight: 600;
    text-decoration: none;
    transition: all .2s ease;
    backdrop-filter: blur(6px);
}
.gp-filter:hover {
    border-color: var(--gp-teal);
    color: var(--gp-teal);
    background: rgba(255,255,255,.95);
}
.gp-filter.is-active {
    background: var(--gp-teal);
    border-color: var(--gp-teal);
    color: #fff;
    box-shadow: 0 4px 16px rgba(13,148,136,.3);
}

/* ---- Empty / Error states ---- */
.gp-empty, .gp-error {
    text-align: center;
    padding: 80px 20px;
    color: var(--gp-muted);
}
.gp-empty i, .gp-error i {
    font-size: 2.8rem;
    color: var(--gp-teal);
    opacity: .45;
    display: block;
    margin-bottom: 14px;
}
.gp-error { color: #c0392b; }
.gp-error i { color: #c0392b; opacity: .6; }

/* ============================================================
   MASONRY GRID
   Auto-fill columns, each cell ~300px min.
   Wide cells span 2 cols, tall cells span 2 rows.
   ============================================================ */
.gp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    grid-auto-rows: 220px;
    gap: var(--gp-gap);
}

/* Default cell */
.gp-cell {
    border-radius: var(--gp-radius);
    overflow: hidden;
    cursor: pointer;
    position: relative;
    background: var(--gp-bg2);
    box-shadow: 0 2px 12px rgba(6,43,39,.08);
    transition: transform .3s ease, box-shadow .3s ease;
    outline: 2.5px solid transparent;
    outline-offset: 3px;
}
.gp-cell:hover  { transform: translateY(-4px); box-shadow: 0 14px 36px rgba(6,43,39,.18); }
.gp-cell:focus  { outline-color: var(--gp-teal); }

/* Span modifiers — create the "hero" wide/tall cells like screenshot */
.gp-cell--wide  { grid-column: span 2; }
.gp-cell--tall  { grid-row:    span 2; }

/* ---- Cell media layer ---- */
.gp-cell-media {
    position: absolute;
    inset: 0;
}
.gp-cell-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .55s ease;
    display: block;
}
.gp-cell:hover .gp-cell-media img { transform: scale(1.06); }

/* Video placeholder (no thumbnail) */
.gp-cell-video-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #0a3d38, #0D9488);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,.5);
    font-size: 3rem;
}

/* Play / zoom badges */
.gp-cell-play,
.gp-cell-zoom {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 2;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .9rem;
    color: #fff;
    background: rgba(13,148,136,.8);
    backdrop-filter: blur(4px);
    transition: background .2s ease, transform .2s ease;
    pointer-events: none;
}
.gp-cell:hover .gp-cell-play,
.gp-cell:hover .gp-cell-zoom {
    background: var(--gp-teal);
    transform: scale(1.1);
}

/* ---- Overlay (slides up on hover) ---- */
.gp-cell-overlay {
    position: absolute;
    inset: 0;
    z-index: 3;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 18px 16px 16px;
    background: linear-gradient(
        to top,
        rgba(6,27,25,.82) 0%,
        rgba(6,27,25,.4)  40%,
        transparent       70%
    );
    opacity: 0;
    transform: translateY(6px);
    transition: opacity .3s ease, transform .3s ease;
}
.gp-cell:hover .gp-cell-overlay {
    opacity: 1;
    transform: none;
}

/* Category tag in overlay */
.gp-cell-tag {
    display: inline-block;
    align-self: flex-start;
    font-size: .6rem;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--gp-teal-lt);
    background: rgba(13,148,136,.25);
    border: 1px solid rgba(45,212,191,.35);
    border-radius: 6px;
    padding: 3px 9px;
    margin-bottom: 6px;
}
.gp-cell-title {
    font-family: var(--font-display, 'Montserrat', sans-serif);
    font-size: .95rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 4px;
    line-height: 1.3;
}
.gp-cell-desc {
    font-size: .75rem;
    color: rgba(255,255,255,.75);
    margin: 0;
    line-height: 1.45;
}

/* ============================================================
   MODAL
   ============================================================ */
.gp-modal .modal-dialog {
    max-width: 900px;
}
.gp-modal .modal-content {
    border: none;
    border-radius: 20px;
    overflow: hidden;
    background: var(--gp-dark);
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
    background: var(--gp-dark);
    color: #fff;
}
.gp-modal-cat {
    display: inline-block;
    font-size: .62rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--gp-teal-lt);
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

/* ============================================================
   REVEAL ANIMATION
   ============================================================ */
.reveal {
    opacity: 0;
    transform: translateY(18px);
    transition: opacity .55s ease, transform .55s ease;
}
.reveal.is-visible {
    opacity: 1;
    transform: none;
}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 860px) {
    .gp-grid {
        grid-template-columns: repeat(2, 1fr);
        grid-auto-rows: 200px;
    }
}
@media (max-width: 600px) {
    .gp-grid {
        grid-template-columns: 1fr;
        grid-auto-rows: 220px;
    }
    .gp-cell--wide  { grid-column: span 1; }
    .gp-cell--tall  { grid-row:    span 1; }

    .gp-modal-body  { grid-template-columns: 1fr; }
    .gp-modal-info  { padding: 20px 18px 26px; }
    .gp-modal-media { min-height: 240px; }

    .gp-header      { padding: 60px 20px 36px; }
    .gp-title       { font-size: 2.2rem; }
}
</style>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script>
(function () {
    /* Scroll-reveal */
    var els = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('is-visible');
                    obs.unobserve(e.target);
                }
            });
        }, { threshold: 0.06, rootMargin: '0px 0px -30px 0px' });
        els.forEach(function (el) { io.observe(el); });
    } else {
        els.forEach(function (el) { el.classList.add('is-visible'); });
    }

    /* Safety net: reveal anything still hidden after page loads */
    window.addEventListener('load', function () {
        setTimeout(function () {
            els.forEach(function (el) {
                if (window.getComputedStyle(el).opacity === '0') {
                    el.classList.add('is-visible');
                }
            });
        }, 400);
    });

    /* Keyboard: open modal on Enter/Space for .gp-cell */
    document.querySelectorAll('.gp-cell').forEach(function (cell) {
        cell.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                cell.click();
            }
        });
    });

    /* Pause video on modal close */
    document.querySelectorAll('.gp-modal').forEach(function (modal) {
        modal.addEventListener('hidden.bs.modal', function () {
            modal.querySelectorAll('video, iframe').forEach(function (el) {
                if (el.tagName === 'VIDEO') {
                    el.pause();
                } else if (el.tagName === 'IFRAME') {
                    /* Reset iframe src to stop video */
                    var src = el.src;
                    el.src = '';
                    el.src = src;
                }
            });
        });
    });
})();
</script>

<?php
$content = ob_get_clean();
$use_home_navbar = false;
require_once __DIR__ . '/../templates/public/layout.php';
?>