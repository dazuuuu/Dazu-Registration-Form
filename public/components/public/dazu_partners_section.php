<?php
/** Homepage partners / collaborators logos. Requires $partners array from partners_load_for_homepage(). */
if (empty($partners)) {
    return;
}
?>
<section class="py-16 bg-white border-t border-slate-100" id="partners">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="text-center mb-10">
      <span class="text-[#15A24A] text-xs font-extrabold uppercase tracking-widest px-3 py-1 bg-green-50 border border-green-100 rounded-full inline-block">Our Partners</span>
      <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-950 mt-4">Trusted by industry leaders</h2>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6 md:gap-8 items-center">
      <?php foreach ($partners as $partner): ?>
        <div class="flex flex-col items-center justify-center text-center p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:border-green-200 transition-colors">
          <img src="<?php echo htmlspecialchars(settings_asset_url($partner['image_path'])); ?>"
               alt="<?php echo htmlspecialchars($partner['title'] ?? 'Partner'); ?>"
               class="max-h-14 w-full object-contain grayscale hover:grayscale-0 transition-all duration-300"
               loading="lazy">
          <?php if (!empty($partner['title'])): ?>
            <p class="mt-3 text-[11px] font-bold uppercase tracking-wider text-slate-500"><?php echo htmlspecialchars($partner['title']); ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
