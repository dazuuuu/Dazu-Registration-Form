<?php
// "Page not found" for addresses that do not exist (sent by public/index.php; also usable as ErrorDocument 404).
require_once __DIR__ . '/../app/v1/init.php';
http_response_code(404);
header('X-Robots-Tag: noindex');
$page_title = 'Page not found — Dazu Training Institute';
ob_start();
?>
<div class="bg-slate-50 min-h-[60vh] py-20 flex items-center justify-center">
  <div class="max-w-md mx-auto px-4 text-center space-y-4">
    <div class="text-6xl font-extrabold text-[#44A024]">404</div>
    <h1 class="text-2xl font-extrabold text-slate-900">Page not found</h1>
    <p class="text-sm text-slate-500">The link may be old or mistyped. Try one of these instead.</p>
    <div class="flex flex-wrap gap-2 justify-center">
      <a href="<?php echo url('/'); ?>" class="px-5 py-3 text-xs font-extrabold uppercase tracking-widest text-white bg-[#44A024] rounded-xl">Home</a>
      <a href="<?php echo url('courses'); ?>" class="px-5 py-3 text-xs font-extrabold uppercase tracking-widest text-slate-700 bg-white border border-slate-200 rounded-xl">Courses</a>
      <a href="<?php echo url('login'); ?>" class="px-5 py-3 text-xs font-extrabold uppercase tracking-widest text-slate-700 bg-white border border-slate-200 rounded-xl">Sign in</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/public/dazu_layout.php';
