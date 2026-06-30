<?php
// public/projects/readmore.php
$page_title = '';
$page_description = '';

ob_start();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../../app/config/db_connect.php';

// Get project details directly from database
$stmt = $pdo->prepare("
    SELECT p.*, c.category_name 
    FROM projects p 
    LEFT JOIN project_categories c ON p.category_id = c.id 
    WHERE p.id = :id AND p.status = 'published'
");
$stmt->execute([':id' => $id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: index.php');
    exit();
}

// Set page title and description
$page_title = htmlspecialchars($project['small_title']) . ' — Ismano Portfolio';
$page_description = htmlspecialchars(substr($project['description'], 0, 160));

// Update view count
$updateStmt = $pdo->prepare("UPDATE projects SET view_count = view_count + 1 WHERE id = :id");
$updateStmt->execute([':id' => $project['id']]);

// Get project gallery images
$stmt = $pdo->prepare("SELECT * FROM project_gallery WHERE project_id = :project_id ORDER BY sort_order ASC");
$stmt->execute([':project_id' => $project['id']]);
$gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get project videos
$stmt = $pdo->prepare("SELECT * FROM project_videos WHERE project_id = :project_id ORDER BY sort_order ASC");
$stmt->execute([':project_id' => $project['id']]);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get project tags
$stmt = $pdo->prepare("
    SELECT t.* FROM project_tags t
    INNER JOIN project_tag_relations ptr ON t.id = ptr.tag_id
    WHERE ptr.project_id = :project_id
");
$stmt->execute([':project_id' => $project['id']]);
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
function getYoutubeId($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return $matches[1] ?? '';
}

function getVimeoId($url) {
    preg_match('/vimeo\.com\/(?:channels\/\w+\/|groups\/[^\/]*\/|video\/|)(\d+)(?:|\/\?)/', $url, $matches);
    return $matches[1] ?? '';
}
?>

<style>
/* Page-specific styles */
.project-hero {
    background: var(--brand-primary);
    padding: var(--space-16) 0 var(--space-14);
    position: relative;
    overflow: hidden;
}
.project-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 60% 80% at 80% 50%, rgba(200,242,60,0.07) 0%, transparent 70%);
    pointer-events: none;
}
.project-hero h1 {
    font-family: var(--font-display);
    font-style: italic;
    font-size: clamp(2rem, 5vw, 3rem);
    font-weight: 700;
    color: #fff;
    margin-bottom: var(--space-3);
}
.project-hero .project-category {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 4px 12px;
    border-radius: var(--radius-full);
    font-size: var(--text-xs);
    font-weight: 500;
    color: var(--brand-accent);
    margin-bottom: var(--space-4);
}
.project-hero p {
    font-size: var(--text-sm);
    color: rgba(255,255,255,0.55);
    max-width: 580px;
    line-height: 1.8;
}

.content-layout {
    max-width: 1100px;
    margin: 0 auto;
    padding: var(--space-12) 0;
}

.project-info {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-8);
    margin-bottom: var(--space-10);
}
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-3) 0;
    border-bottom: 1px solid var(--color-border);
}
.info-row:last-child { border-bottom: none; }
.info-label { font-weight: 600; color: var(--color-text-heading); }
.info-value { color: var(--color-text-muted); }

.description-section { margin-bottom: var(--space-10); }
.description-section h2 {
    font-family: var(--font-display);
    font-size: var(--text-xl);
    font-weight: 700;
    margin-bottom: var(--space-5);
    color: var(--color-text-heading);
    position: relative;
    display: inline-block;
}
.description-section h2::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 50px;
    height: 2px;
    background: var(--brand-accent);
}
.description-content {
    font-size: var(--text-base);
    line-height: 1.8;
    color: var(--color-text-body);
}

.gallery-section { margin-bottom: var(--space-10); }
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-4);
}
.gallery-item {
    position: relative;
    aspect-ratio: 1;
    overflow: hidden;
    border-radius: var(--radius-lg);
    cursor: pointer;
}
.gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}
.gallery-item:hover img { transform: scale(1.1); }
.gallery-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
    color: white;
}
.gallery-item:hover .gallery-overlay { opacity: 1; }

.videos-section { margin-bottom: var(--space-10); }
.videos-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-5);
}
.video-container {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    overflow: hidden;
    border-radius: var(--radius-lg);
}
.video-container iframe,
.video-container video {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: none;
}

.tags-section {
    margin-bottom: var(--space-10);
    padding-top: var(--space-6);
    border-top: 1px solid var(--color-border);
}
.tag {
    display: inline-block;
    padding: 5px 12px;
    margin: 3px;
    background: var(--color-surface-alt);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-full);
    font-size: var(--text-xs);
    color: var(--color-text-body);
    text-decoration: none;
    transition: all var(--transition-fast);
}
.tag:hover {
    background: var(--brand-primary);
    border-color: var(--brand-primary);
    color: white;
}

.cta-section {
    background: var(--brand-primary);
    padding: var(--space-16) 0;
    text-align: center;
    color: white;
    margin-top: var(--space-8);
}
.cta-section h2 { font-size: var(--text-2xl); margin-bottom: var(--space-4); }
.cta-section .btn-light {
    background: white;
    color: var(--brand-primary);
    padding: 12px 32px;
    border-radius: var(--radius-full);
    font-weight: 600;
    margin: var(--space-2);
    text-decoration: none;
    display: inline-block;
}
.cta-section .btn-light:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.cta-section .btn-outline-light {
    border: 2px solid white;
    background: transparent;
    color: white;
    padding: 12px 32px;
    border-radius: var(--radius-full);
    font-weight: 600;
    margin: var(--space-2);
    text-decoration: none;
}
.cta-section .btn-outline-light:hover { background: white; color: var(--brand-primary); transform: translateY(-3px); }

@media (max-width: 768px) {
    .gallery-grid { grid-template-columns: repeat(2, 1fr); }
    .videos-grid { grid-template-columns: 1fr; }
}
</style>


<?php
  $hero_eyebrow = $project['category_name'] ?? 'Project';
  $hero_title   = $project['small_title'];
  $hero_sub     = $project['major_title'] ?? '';
  $hero_desc    = mb_strimwidth(strip_tags($project['description'] ?? ''), 0, 160, '…');
  $hero_image   = !empty($project['cover_image']) ? $project['cover_image']
                : 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=1600&q=80';
  include __DIR__ . '/../components/public/page_hero.php';
?>

<!-- Hero Section -->
<!--div class="project-hero">
    <div class="container">
        <div class="breadcrumb-wrap">
            <a href="/Realestate/public/">Home</a>
            <i class="fas fa-chevron-right sep"></i>
            <a href="/Realestate/public/projects/">Projects</a>
            <i class="fas fa-chevron-right sep"></i>
            <span><?php echo htmlspecialchars($project['small_title']); ?></span>
        </div>
        <div class="project-category"><?php echo htmlspecialchars($project['category_name'] ?? 'Uncategorized'); ?></div>
        <h1><?php echo htmlspecialchars($project['small_title']); ?></h1>
        <p><?php echo htmlspecialchars($project['major_title']); ?></p>
    </div>
</div-->

<div class="container">
    <div class="content-layout">
        
        <!-- Project Info -->
        <div class="project-info">
            <div class="info-row"><span class="info-label">Project Category</span><span class="info-value"><?php echo htmlspecialchars($project['category_name'] ?? 'Uncategorized'); ?></span></div>
            <div class="info-row"><span class="info-label">Completion Date</span><span class="info-value"><?php echo date('F d, Y', strtotime($project['created_at'])); ?></span></div>
            <div class="info-row"><span class="info-label">Views</span><span class="info-value"><?php echo number_format($project['view_count']); ?> views</span></div>
        </div>
        
        <!-- Description -->
        <div class="description-section">
            <h2>Project Overview</h2>
            <div class="description-content"><?php echo nl2br(htmlspecialchars($project['description'])); ?></div>
        </div>
        
        <!-- Gallery -->
        <?php if (!empty($gallery)): ?>
        <div class="gallery-section">
            <h2>Project Gallery</h2>
            <div class="gallery-grid">
                <?php foreach ($gallery as $image): ?>
                <div class="gallery-item" onclick="openLightbox('<?php echo $image['image_path']; ?>')">
                    <img src="<?php echo $image['image_path']; ?>" alt="<?php echo htmlspecialchars($image['image_title']); ?>">
                    <div class="gallery-overlay"><i class="fas fa-search-plus fa-2x"></i></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Videos -->
        <?php if (!empty($videos)): ?>
        <div class="videos-section">
            <h2>Project Videos</h2>
            <div class="videos-grid">
                <?php foreach ($videos as $video): ?>
                <div>
                    <?php if ($video['video_title']): ?>
                        <h4 class="mb-2"><?php echo htmlspecialchars($video['video_title']); ?></h4>
                    <?php endif; ?>
                    <div class="video-container">
                        <?php if ($video['video_type'] === 'youtube'): ?>
                            <iframe src="https://www.youtube.com/embed/<?php echo getYoutubeId($video['video_url']); ?>" allowfullscreen></iframe>
                        <?php elseif ($video['video_type'] === 'vimeo'): ?>
                            <iframe src="https://player.vimeo.com/video/<?php echo getVimeoId($video['video_url']); ?>" allowfullscreen></iframe>
                        <?php else: ?>
                            <video controls><source src="<?php echo $video['video_url']; ?>" type="video/mp4"></video>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tags -->
        <?php if (!empty($tags)): ?>
        <div class="tags-section">
            <h2>Tags</h2>
            <?php foreach ($tags as $tag): ?>
                <a href="/Realestate/public/projects/?tag=<?php echo $tag['tag_slug']; ?>" class="tag">
                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($tag['tag_name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- CTA Section -->
<div class="cta-section">
    <div class="container">
        <h2>Interested in Similar Projects?</h2>
        <p class="lead mb-4">Let's discuss how we can bring your vision to life</p>
        <a href="/Realestate/public/contact.php" class="btn-light">Contact Us</a>
        <a href="/Realestate/public/projects/" class="btn-outline-light">View All Projects</a>
    </div>
</div>

<!-- Lightbox Modal -->
<div class="modal fade" id="lightboxModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                <img src="" id="lightboxImage" class="img-fluid rounded" style="width: 100%;">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openLightbox(imageSrc) {
    const modal = new bootstrap.Modal(document.getElementById('lightboxModal'));
    document.getElementById('lightboxImage').src = imageSrc;
    modal.show();
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../templates/public/layout.php';
?>