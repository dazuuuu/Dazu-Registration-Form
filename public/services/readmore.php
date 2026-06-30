<?php
// public/services/readmore.php

$slug = $_GET['slug'] ?? null;
if (!$slug) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../../app/config/db_connect.php';

// Get service details
$stmt = $pdo->prepare("
    SELECT s.*, u.username as creator_name 
    FROM services s 
    LEFT JOIN users u ON s.created_by = u.id 
    WHERE s.slug = :slug AND s.status = 'published'
");
$stmt->execute([':slug' => $slug]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header('Location: index.php');
    exit();
}

// Update view count
$updateStmt = $pdo->prepare("UPDATE services SET view_count = view_count + 1 WHERE id = :id");
$updateStmt->execute([':id' => $service['id']]);

// Get sections
$stmt = $pdo->prepare("SELECT * FROM service_sections WHERE service_id = :service_id ORDER BY sort_order ASC");
$stmt->execute([':service_id' => $service['id']]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get gallery images
$stmt = $pdo->prepare("SELECT * FROM service_gallery WHERE service_id = :service_id ORDER BY sort_order ASC");
$stmt->execute([':service_id' => $service['id']]);
$gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get benefits
$stmt = $pdo->prepare("SELECT * FROM service_benefits WHERE service_id = :service_id ORDER BY sort_order ASC");
$stmt->execute([':service_id' => $service['id']]);
$benefits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get FAQs
$stmt = $pdo->prepare("SELECT * FROM service_faqs WHERE service_id = :service_id AND is_active = 1 ORDER BY sort_order ASC");
$stmt->execute([':service_id' => $service['id']]);
$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
function getYoutubeId($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return $matches[1] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($service['title']); ?> — Ismano Services</title>
  <meta name="description" content="<?php echo htmlspecialchars(substr($service['short_description'], 0, 160)); ?>">

  <link rel="stylesheet" href="../assets/css/theme.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
  body { padding-top: var(--navbar-height); background: var(--color-bg); }


  /* Content layout */
  .content-layout {
    max-width: 1100px;
    margin: 0 auto;
    padding: var(--space-12) 0;
  }

  /* Section styles */
  .section-block {
    margin-bottom: var(--space-14);
  }
  .section-title {
    font-family: var(--font-display);
    font-size: var(--text-2xl);
    font-weight: 700;
    margin-bottom: var(--space-6);
    color: var(--color-text-heading);
    position: relative;
    display: inline-block;
  }
  .section-title::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 50px;
    height: 2px;
    background: var(--color-accent);
  }
  .text-center .section-title::after {
    left: 50%;
    transform: translateX(-50%);
  }
  .section-content {
    font-size: var(--text-base);
    line-height: 1.8;
    color: var(--color-text-body);
  }
  .section-content p { margin-bottom: var(--space-4); }

  /* Image styles */
  .section-image {
    width: 100%;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    transition: transform var(--transition-base);
  }
  .section-image:hover { transform: scale(1.02); }

  /* Gallery grid */
  .gallery-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
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

  /* Benefits grid */
  .benefits-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-6);
  }
  .benefit-card {
    text-align: center;
    padding: var(--space-6);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    transition: all var(--transition-base);
  }
  .benefit-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: transparent;
  }
  .benefit-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--space-4);
    color: white;
    font-size: 1.5rem;
  }
  .benefit-title {
    font-size: var(--text-md);
    font-weight: 700;
    margin-bottom: var(--space-2);
  }
  .benefit-description {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    line-height: 1.6;
  }

  /* FAQ Accordion */
  .faq-accordion .accordion-item {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-3);
    background: var(--color-surface);
  }
  .faq-accordion .accordion-button {
    background: var(--color-surface);
    color: var(--color-text-heading);
    font-weight: 600;
    padding: var(--space-4) var(--space-5);
  }
  .faq-accordion .accordion-button:not(.collapsed) {
    background: var(--color-primary);
    color: white;
  }
  .faq-accordion .accordion-button:focus { box-shadow: none; }
  .faq-accordion .accordion-body {
    padding: var(--space-4) var(--space-5);
    color: var(--color-text-body);
    line-height: 1.7;
  }

  /* Video container */
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

  /* CTA Section */
  .cta-section {
    background: var(--color-primary);
    padding: var(--space-16) 0;
    text-align: center;
    color: white;
    margin-top: var(--space-8);
  }
  .cta-section h2 {
    font-size: var(--text-2xl);
    margin-bottom: var(--space-4);
  }
  .cta-section .btn-light {
    background: white;
    color: var(--color-primary);
    padding: 12px 32px;
    border-radius: var(--radius-full);
    font-weight: 600;
    margin: var(--space-2);
    text-decoration: none;
    display: inline-block;
    transition: all var(--transition-fast);
  }
  .cta-section .btn-light:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
  }
  .cta-section .btn-outline-light {
    border: 2px solid white;
    background: transparent;
    color: white;
    padding: 12px 32px;
    border-radius: var(--radius-full);
    font-weight: 600;
    margin: var(--space-2);
    text-decoration: none;
    display: inline-block;
    transition: all var(--transition-fast);
  }
  .cta-section .btn-outline-light:hover {
    background: white;
    color: var(--color-primary);
    transform: translateY(-3px);
  }

  @media (max-width: 768px) {
    .gallery-grid { grid-template-columns: repeat(2, 1fr); }
    .benefits-grid { grid-template-columns: 1fr; }
    .service-hero { padding: var(--space-10) 0; }
  }
  </style>
</head>
<body>

<?php include __DIR__ . '/../components/public/navbar.php'; ?>

<?php
  $hero_eyebrow = 'Service';
  $hero_title   = $service['title'];
  $hero_desc    = $service['short_description'];
  $hero_image   = !empty($service['cover_image']) ? $service['cover_image']
                : 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1600&q=80';
  include __DIR__ . '/../components/public/page_hero.php';
?>



<!-- Hero Section -->
<!--section class="service-hero">
  <div class="container service-hero-inner">
    <div class="breadcrumb-wrap">
      <a href="/Ismano/public/">Home</a>
      <i class="fas fa-chevron-right sep"></i>
      <a href="/Ismano/public/services/">Services</a>
      <i class="fas fa-chevron-right sep"></i>
      <span><?php echo htmlspecialchars($service['title']); ?></span>
    </div>
    <h1><?php echo htmlspecialchars($service['title']); ?></h1>
    <p><?php echo htmlspecialchars($service['short_description']); ?></p>
  </div>
</section-->

<div class="container">
  <div class="content-layout">

    <!-- Dynamic Sections -->
    <?php foreach ($sections as $section): ?>
      <div class="section-block">
        
        <?php if ($section['section_type'] === 'text_only'): ?>
          <div class="text-center">
            <?php if ($section['title']): ?>
              <h2 class="section-title"><?php echo htmlspecialchars($section['title']); ?></h2>
            <?php endif; ?>
            <div class="section-content">
              <?php 
// Allow basic HTML tags but remove dangerous ones
$allowed_tags = '<p><br><b><strong><i><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><div><span><blockquote><code><pre>';
echo strip_tags($section['content'], $allowed_tags); 
?>
            </div>
          </div>
          
        <?php elseif ($section['section_type'] === 'text_image_left'): ?>
          <div class="row align-items-center">
            <div class="col-lg-6 order-lg-2">
              <?php if ($section['title']): ?>
                <h2 class="section-title"><?php echo htmlspecialchars($section['title']); ?></h2>
              <?php endif; ?>
              <div class="section-content">
                <?php echo nl2br(htmlspecialchars($section['content'])); ?>
              </div>
            </div>
            <div class="col-lg-6 order-lg-1">
              <?php if ($section['media_url']): ?>
                <img src="<?php echo $section['media_url']; ?>" class="section-image" alt="<?php echo htmlspecialchars($section['title']); ?>">
              <?php endif; ?>
            </div>
          </div>
          
        <?php elseif ($section['section_type'] === 'text_image_right'): ?>
          <div class="row align-items-center">
            <div class="col-lg-6">
              <?php if ($section['title']): ?>
                <h2 class="section-title"><?php echo htmlspecialchars($section['title']); ?></h2>
              <?php endif; ?>
              <div class="section-content">
                <?php echo nl2br(htmlspecialchars($section['content'])); ?>
              </div>
            </div>
            <div class="col-lg-6">
              <?php if ($section['media_url']): ?>
                <img src="<?php echo $section['media_url']; ?>" class="section-image" alt="<?php echo htmlspecialchars($section['title']); ?>">
              <?php endif; ?>
            </div>
          </div>
          
        <?php elseif ($section['section_type'] === 'image_gallery' && !empty($gallery)): ?>
          <?php if ($section['title']): ?>
            <h2 class="section-title text-center"><?php echo htmlspecialchars($section['title']); ?></h2>
          <?php endif; ?>
          <div class="gallery-grid">
            <?php foreach ($gallery as $image): ?>
              <div class="gallery-item" onclick="openLightbox('<?php echo $image['image_path']; ?>')">
                <img src="<?php echo $image['image_path']; ?>" alt="<?php echo htmlspecialchars($image['image_title']); ?>">
                <div class="gallery-overlay">
                  <i class="fas fa-search-plus fa-2x"></i>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          
        <?php elseif ($section['section_type'] === 'video'): ?>
          <div class="row justify-content-center">
            <div class="col-lg-10">
              <?php if ($section['title']): ?>
                <h2 class="section-title text-center"><?php echo htmlspecialchars($section['title']); ?></h2>
              <?php endif; ?>
              <div class="video-container">
                <video controls>
                  <source src="<?php echo $section['media_url']; ?>" type="video/mp4">
                </video>
              </div>
            </div>
          </div>
          
        <?php elseif ($section['section_type'] === 'youtube'): ?>
          <div class="row justify-content-center">
            <div class="col-lg-10">
              <?php if ($section['title']): ?>
                <h2 class="section-title text-center"><?php echo htmlspecialchars($section['title']); ?></h2>
              <?php endif; ?>
              <div class="video-container">
                <iframe src="https://www.youtube.com/embed/<?php echo getYoutubeId($section['media_url']); ?>" allowfullscreen></iframe>
              </div>
            </div>
          </div>
          
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <!-- Benefits Section -->
    <?php if (!empty($benefits)): ?>
      <div class="section-block">
        <h2 class="section-title text-center">Why Choose Our Service?</h2>
        <div class="benefits-grid">
          <?php foreach ($benefits as $benefit): ?>
            <div class="benefit-card">
              <div class="benefit-icon">
                <i class="<?php echo $benefit['icon_class'] ?? 'fas fa-check-circle'; ?>"></i>
              </div>
              <h4 class="benefit-title"><?php echo htmlspecialchars($benefit['benefit_title']); ?></h4>
              <p class="benefit-description"><?php echo htmlspecialchars($benefit['benefit_description']); ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- FAQs Section -->
    <?php if (!empty($faqs)): ?>
      <div class="section-block">
        <h2 class="section-title text-center">Frequently Asked Questions</h2>
        <div class="faq-accordion accordion" id="faqAccordion">
          <?php foreach ($faqs as $index => $faq): ?>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button <?php echo $index !== 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?php echo $faq['id']; ?>">
                  <?php echo htmlspecialchars($faq['question']); ?>
                </button>
              </h2>
              <div id="faq<?php echo $faq['id']; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- CTA Section -->
<section class="cta-section">
  <div class="container">
    <h2>Ready to Get Started?</h2>
    <p class="lead mb-4">Let's discuss how we can help you achieve your goals</p>
    <a href="/Realestate/public/contact.php" class="btn-light">Contact Us</a>
    <a href="/Realestate/public/services/" class="btn-outline-light">View All Services</a>
  </div>
</section>

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

<?php include __DIR__ . '/../components/public/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openLightbox(imageSrc) {
  const modal = new bootstrap.Modal(document.getElementById('lightboxModal'));
  document.getElementById('lightboxImage').src = imageSrc;
  modal.show();
}
</script>

</body>
</html>