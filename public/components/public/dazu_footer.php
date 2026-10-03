<?php
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/brand.php';
require_once __DIR__ . '/../../../app/v1/helpers/site_logo.php';
require_once __DIR__ . '/../../../app/v1/helpers/site_contact.php';
$year = date('Y');
$footerLogo = site_logo_url(brand_url('mark'));
$contact = site_contact_settings();
?>
<footer class="bg-slate-950 text-slate-400 border-t border-slate-900 pt-16 pb-12">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <?php
    $showCompany = !empty($contact['footer_show_company']); $showCourses = !empty($contact['footer_show_courses']); $showBottom = !empty($contact['footer_show_bottom']);
    $cols = 1 + ($showCompany ? 1 : 0) + ($showCourses ? 1 : 0);
    $gridMd = [1 => '', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][$cols];
    ?>
    <div class="grid grid-cols-2 <?php echo $gridMd; ?> gap-12 <?php echo $showBottom ? 'mb-16' : 'mb-2'; ?>">
      <div class="col-span-2 md:col-span-1">
        <a href="<?php echo url('/'); ?>" class="inline-block group mb-5">
          <img src="<?php echo htmlspecialchars(brand_url('logo')); ?>" alt="Dazu Training Institute" class="h-14 w-auto transition-transform duration-300 group-hover:scale-105">
        </a>
        <?php
        $fPhone = trim((string) ($contact['footer_phone'] ?? '')); $fEmail = trim((string) ($contact['footer_email'] ?? ''));
        $fAddress = trim((string) ($contact['footer_address'] ?? '')); $fHours = trim((string) ($contact['footer_hours'] ?? ''));
        ?>
        <?php if ($fPhone !== '' || $fEmail !== '' || $fAddress !== '' || $fHours !== ''): ?>
        <ul class="text-sm text-slate-300">
          <?php if ($fPhone !== ''): ?><li class="py-1.5">Phone: <a href="tel:<?php echo htmlspecialchars($contact['contact_phone_tel']); ?>" class="hover:text-green-500 transition-colors duration-200"><?php echo htmlspecialchars($fPhone); ?></a></li><?php endif; ?>
          <?php if ($fEmail !== ''): ?><li class="py-1.5">Email: <a href="mailto:<?php echo htmlspecialchars($fEmail); ?>" class="hover:text-green-500 transition-colors duration-200 break-all"><?php echo htmlspecialchars($fEmail); ?></a></li><?php endif; ?>
          <?php if ($fAddress !== ''): ?><li class="py-1.5"><?php echo htmlspecialchars($fAddress); ?></li><?php endif; ?>
          <?php if ($fHours !== ''): ?><li class="py-1.5"><?php echo htmlspecialchars($fHours); ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <a href="<?php echo url('verify/'); ?>" class="inline-flex items-center gap-2 mt-4 text-sm font-semibold text-green-500 hover:text-green-400 transition-colors duration-200" style="display:inline-flex;align-items:center;gap:8px;margin-top:16px;font-weight:600">
          <svg class="h-4 w-4" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.6-4A12 12 0 0112 2.9 12 12 0 013.4 6a12 12 0 00.6 9.6A12 12 0 0012 21a12 12 0 008-5.4A12 12 0 0020.6 6z"/></svg>
          Verify a Certificate
        </a>
        <?php
        $socials = [
          'social_facebook'  => ['Facebook',  '#1877F2', '<path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12z"/>'],
          'social_tiktok'    => ['TikTok',    '#000000', '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>'],
          'social_youtube'   => ['YouTube',   '#E53935', '<path d="M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2C.5 9.1.5 12 .5 12s0 2.9.5 4.8a3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1c.5-1.9.5-4.8.5-4.8s0-2.9-.5-4.8zM9.7 15.5v-7l6.1 3.5-6.1 3.5z"/>'],
          'social_instagram' => ['Instagram', '#262626', '<path d="M12 2.2c3.2 0 3.6 0 4.8.1 3.3.1 4.8 1.7 4.9 4.9.1 1.3.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 3.2-1.7 4.8-4.9 4.9-1.3.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-3.3-.1-4.8-1.7-4.9-4.9C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.8C2.4 3.9 4 2.4 7.2 2.3 8.4 2.2 8.8 2.2 12 2.2zM12 0C8.7 0 8.3 0 7.1.1 2.7.3.3 2.7.1 7.1 0 8.3 0 8.7 0 12s0 3.7.1 4.9c.2 4.4 2.6 6.8 7 7 1.2.1 1.6.1 4.9.1s3.7 0 4.9-.1c4.4-.2 6.8-2.6 7-7 .1-1.2.1-1.6.1-4.9s0-3.7-.1-4.9c-.2-4.4-2.6-6.8-7-7C15.7 0 15.3 0 12 0zm0 5.8a6.2 6.2 0 1 0 0 12.4 6.2 6.2 0 0 0 0-12.4zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.4-11.8a1.4 1.4 0 1 0 0 2.9 1.4 1.4 0 0 0 0-2.9z"/>'],
        ];
        ?>
        <?php $socials = array_filter($socials, fn($k) => trim((string) ($contact[$k] ?? '')) !== '', ARRAY_FILTER_USE_KEY); ?>
        <?php if ($socials): ?>
        <div class="flex gap-3 mt-6">
          <?php foreach ($socials as $key => [$label, $bg, $icon]): $href = trim((string) $contact[$key]); ?>
            <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" rel="noopener" aria-label="<?php echo $label; ?>" class="h-14 w-14 rounded-md flex items-center justify-center text-white transition-transform duration-200 hover:scale-105" style="background:<?php echo $bg; ?>;border:1px solid #334155">
              <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><?php echo $icon; ?></svg>
            </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($showCompany): ?>
      <div class="space-y-6">
        <h4 class="text-sm uppercase tracking-widest font-bold text-white">Company</h4>
        <ul class="space-y-3 text-sm">
          <li><a href="<?php echo url('about'); ?>" class="hover:text-green-500 transition-colors duration-200">About Us</a></li>
          <li><a href="<?php echo url('projects'); ?>" class="hover:text-green-500 transition-colors duration-200">Student Projects</a></li>
          <li><a href="<?php echo url('blog'); ?>" class="hover:text-green-500 transition-colors duration-200">Institute Blog</a></li>
          <li><a href="<?php echo url('contact'); ?>" class="hover:text-green-500 transition-colors duration-200">Contact &amp; Support</a></li>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ($showCourses): ?>
      <div class="space-y-6">
        <h4 class="text-sm uppercase tracking-widest font-bold text-white">Courses</h4>
        <ul class="space-y-3 text-sm">
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500 transition-colors duration-200">Software Development</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500 transition-colors duration-200">Artificial Intelligence</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500 transition-colors duration-200">Cybersecurity &amp; Networks</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500 transition-colors duration-200">Creative Graphics &amp; Design</a></li>
        </ul>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($showBottom): ?>
    <div class="border-t border-slate-900 pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
      <p><?php echo htmlspecialchars(trim((string) ($contact['footer_copyright'] ?? '')) !== '' ? $contact['footer_copyright'] : '© ' . $year . ' Dazu Training Institute. All rights reserved.'); ?></p>
      <div class="flex gap-6">
        <a href="<?php echo url('verify/'); ?>" class="hover:text-white transition-colors duration-200">Verify Certificate</a>
        <a href="<?php echo url('contact'); ?>" class="hover:text-white transition-colors duration-200">Support</a>
        <a href="<?php echo url('about'); ?>" class="hover:text-white transition-colors duration-200">Terms of Study</a>
      </div>
    <?php endif; ?>
    </div>
  </div>
</footer>
