<?php
/** Campus photography and video section. Expects $campusMedia array. */
$campusMedia = $campusMedia ?? [];
if (!$campusMedia) {
    return;
}
?>
<section class="py-12 sm:py-16 bg-slate-50 border-y border-slate-100" id="campus-media">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="border-b border-slate-200 pb-5 mb-6 sm:mb-8">
      <div class="max-w-3xl">
        <span class="text-[#15A24A] text-[11px] uppercase tracking-widest font-extrabold">Campus Photography</span>
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-950 mt-2">Campus Photos &amp; Videos</h2>
        <p class="text-slate-500 text-sm sm:text-base font-medium leading-relaxed mt-2">
          Photos and videos from our classes, lab sessions, student projects, and campus activities.
        </p>
      </div>
    </div>

    <div class="dazu-campus-media-grid">
      <?php foreach ($campusMedia as $index => $item): ?>
        <?php
          $type = $item['media_type'] ?? 'image';
          $src = campus_media_url($item['file_path'] ?? '');
          $title = trim((string) ($item['title'] ?? ''));
          $description = trim((string) ($item['description'] ?? ''));
        ?>
        <article class="dazu-campus-media-card">
          <div class="dazu-campus-media-frame">
            <?php if ($type === 'video'): ?>
              <video src="<?php echo htmlspecialchars($src); ?>"
                     muted playsinline controls preload="metadata"></video>
            <?php else: ?>
              <img src="<?php echo htmlspecialchars($src); ?>"
                   alt="<?php echo htmlspecialchars($title ?: 'Dazu Training Institute campus photo'); ?>"
                   loading="lazy">
            <?php endif; ?>
          </div>
          <?php if ($title !== '' || $description !== ''): ?>
            <div class="dazu-campus-media-caption">
              <?php if ($title !== ''): ?>
                <h3><?php echo htmlspecialchars($title); ?></h3>
              <?php endif; ?>
              <?php if ($description !== ''): ?>
                <p><?php echo htmlspecialchars($description); ?></p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<style>
#campus-media .dazu-campus-media-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}
#campus-media .dazu-campus-media-card {
  overflow: hidden;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
#campus-media .dazu-campus-media-frame {
  aspect-ratio: 16 / 9;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}
#campus-media .dazu-campus-media-frame img,
#campus-media .dazu-campus-media-frame video {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
}
#campus-media .dazu-campus-media-caption {
  padding: 12px 14px;
}
#campus-media .dazu-campus-media-caption h3 {
  margin: 0;
  color: #020617;
  font-size: 13px;
  font-weight: 800;
  line-height: 1.35;
}
#campus-media .dazu-campus-media-caption p {
  margin: 5px 0 0;
  color: #64748b;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.5;
}
@media (min-width: 560px) {
  #campus-media .dazu-campus-media-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }
}
@media (min-width: 1024px) {
  #campus-media .dazu-campus-media-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
  }
}
@media (min-width: 1280px) {
  #campus-media .dazu-campus-media-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}
</style>
