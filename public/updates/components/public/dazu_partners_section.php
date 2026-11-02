<?php
// public/components/public/dazu_partners_section.php
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/PartnerModel.php';

use Models\Connection;
use Models\PartnerModel;

if (!isset($partners)) {
    try {
        $pdo = Connection::getInstance()->getConnection();
        $partnerModel = new PartnerModel($pdo);
        $partners = $partnerModel->getAll(true);
    } catch (\Throwable $e) {
        $partners = [];
    }
}

// Only include partners that have a logo uploaded
$validPartners = array_filter($partners, function($p) {
    return !empty($p['logo']);
});

if (empty($validPartners)) {
    return; // Don't render empty section
}

// Duplicate array to achieve seamless infinite continuous loop in CSS marquee
$marqueeList = array_merge($validPartners, $validPartners);
?>
<section class="py-16 bg-slate-50/70 border-y border-slate-100 overflow-hidden font-sans" id="partners-section">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 text-center mb-10">
    <span class="inline-block px-3 py-1 bg-green-50 text-[#15A24A] text-[11px] font-extrabold uppercase tracking-widest rounded-full border border-green-100 mb-3">
      Trusted By Industry Leaders
    </span>
    <h3 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">
      Our Technology &amp; Placement Partners
    </h3>
    <p class="text-slate-500 text-xs md:text-sm font-medium max-w-2xl mx-auto mt-2">
      Collaborating with leading technology pioneers and enterprise employers to empower your career journey.
    </p>
  </div>

  <!-- Continuous Infinite Marquee Carousel -->
  <div class="dazu-partners-carousel-wrap">
    <div class="dazu-partners-track">
      <?php foreach ($marqueeList as $p): ?>
        <?php
        $logoUrl = settings_asset_url($p['logo']);
        $hasLink = !empty($p['website_url']);
        ?>
        <div class="dazu-partner-slide">
          <?php if ($hasLink): ?>
            <a href="<?php echo htmlspecialchars($p['website_url']); ?>" target="_blank" rel="noopener noreferrer" class="dazu-partner-card" title="<?php echo htmlspecialchars($p['name']); ?>">
              <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="dazu-partner-img" loading="lazy" onerror="this.parentElement.style.display='none'">
            </a>
          <?php else: ?>
            <div class="dazu-partner-card" title="<?php echo htmlspecialchars($p['name']); ?>">
              <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="dazu-partner-img" loading="lazy" onerror="this.parentElement.style.display='none'">
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<style>
/* Partners Carousel Styles */
.dazu-partners-carousel-wrap {
  width: 100%;
  overflow: hidden;
  position: relative;
  mask-image: linear-gradient(to right, transparent 0%, black 10%, black 90%, transparent 100%);
  -webkit-mask-image: linear-gradient(to right, transparent 0%, black 10%, black 90%, transparent 100%);
  padding: 16px 0;
}

.dazu-partners-track {
  display: flex;
  align-items: center;
  gap: 56px;
  width: max-content;
  animation: dazu-partners-scroll 32s linear infinite;
}

.dazu-partners-track:hover {
  animation-play-state: paused;
}

@keyframes dazu-partners-scroll {
  0%   { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

.dazu-partner-slide {
  flex-shrink: 0;
}

.dazu-partner-card {
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.dazu-partner-card:hover {
  transform: scale(1.08);
}

.dazu-partner-img {
  height: 70px;
  max-width: 220px;
  width: auto;
  object-fit: contain;
  transition: transform 0.3s ease, opacity 0.3s ease;
  display: block;
  opacity: 1;
}

.dazu-partner-card:hover .dazu-partner-img {
  opacity: 0.85;
}
</style>
