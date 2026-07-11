<?php
$page_title = 'Contact Dazu Hub — Dazu hub Institute';
$page_description = 'Contact Dazu hub Institute — tuition, study plans, campus labs in Thika Town.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/contact.php';
require_once __DIR__ . '/../../app/v1/helpers/site_contact.php';
require_once __DIR__ . '/../../app/v1/controllers/EnquiryController.php';

$c = site_contact_settings();
$sent = false;
$form_error = '';

if (empty($_SESSION['contact_captcha'])) {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['contact_captcha'] = ['a' => $a, 'b' => $b, 'answer' => $a + $b];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['website'])) {
        $sent = true;
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $form_error = 'Security check failed. Please refresh and try again.';
    } else {
        $captchaAns = (int) ($_POST['captcha_answer'] ?? -1);
        $expected = (int) ($_SESSION['contact_captcha']['answer'] ?? -99);
        if ($captchaAns !== $expected) {
            $form_error = 'Incorrect security answer. Please solve the math puzzle.';
        } else {
            $controller = new EnquiryController($pdo);
            $result = $controller->submit([
                'name' => trim($_POST['name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'service' => trim($_POST['subject'] ?? 'General Inquiry'),
                'message' => trim($_POST['message'] ?? ''),
            ]);
            if (!empty($result['success'])) {
                $sent = true;
                unset($_SESSION['contact_captcha']);
            } else {
                $form_error = $result['message'] ?? 'Could not send your inquiry.';
            }
        }
    }
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['contact_captcha'] = ['a' => $a, 'b' => $b, 'answer' => $a + $b];
}

$captcha = $_SESSION['contact_captcha'];
$mapEmbed = $c['contact_map_embed'];
$isIframe = str_contains($mapEmbed, 'google.com/maps') || str_contains($mapEmbed, 'maps.google');

function contact_old(string $k): string {
    return htmlspecialchars($_POST[$k] ?? '', ENT_QUOTES, 'UTF-8');
}

ob_start();
?>
<div class="bg-white min-h-screen py-16 text-slate-900">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="max-w-2xl mb-16">
      <span class="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full">Get in Touch</span>
      <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 mt-4">Contact Dazu Hub</h1>
      <p class="text-slate-600 font-medium leading-relaxed mt-3">Have questions about tuition fees, study plans, or physical computer labs? Talk directly with our registrar or visit us in Thika Town.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
      <div class="lg:col-span-5 space-y-8">
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-8 space-y-6">
          <h3 class="text-lg font-bold text-slate-900 uppercase tracking-wide">Institute Directory</h3>
          <ul class="space-y-6 text-sm">
            <li class="flex items-start gap-4">
              <div class="p-3 bg-white border border-slate-200 text-green-600 rounded-xl"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></div>
              <div><span class="block text-slate-400 uppercase tracking-wider text-[10px] font-bold">Call / WhatsApp</span><span class="text-base font-bold text-slate-900"><?php echo htmlspecialchars($c['contact_phone']); ?></span></div>
            </li>
            <li class="flex items-start gap-4">
              <div class="p-3 bg-white border border-slate-200 text-green-600 rounded-xl"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
              <div><span class="block text-slate-400 uppercase tracking-wider text-[10px] font-bold">Email Admissions</span><span class="text-base font-bold text-slate-900"><?php echo htmlspecialchars($c['contact_email']); ?></span></div>
            </li>
            <li class="flex items-start gap-4">
              <div class="p-3 bg-white border border-slate-200 text-green-600 rounded-xl"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
              <div><span class="block text-slate-400 uppercase tracking-wider text-[10px] font-bold">Campus Location</span><span class="text-base font-bold text-slate-900"><?php echo htmlspecialchars($c['contact_address']); ?></span></div>
            </li>
            <li class="flex items-start gap-4">
              <div class="p-3 bg-white border border-slate-200 text-green-600 rounded-xl"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
              <div><span class="block text-slate-400 uppercase tracking-wider text-[10px] font-bold">Office Hours</span><span class="text-base font-bold text-slate-900"><?php echo htmlspecialchars($c['contact_hours']); ?></span></div>
            </li>
          </ul>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Campus Directions</h3>
            <a href="<?php echo htmlspecialchars($c['contact_map_link']); ?>" target="_blank" rel="noopener" class="text-xs text-green-600 font-bold hover:underline flex items-center gap-1">Open in Google Maps ↗</a>
          </div>
          <?php if ($isIframe): ?>
            <div class="rounded-xl overflow-hidden border border-slate-200 aspect-[4/3] bg-white">
              <iframe src="<?php echo htmlspecialchars($mapEmbed); ?>" class="w-full h-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Campus map"></iframe>
            </div>
          <?php else: ?>
            <div class="bg-white border border-slate-200 rounded-xl p-8 text-center space-y-4 aspect-[4/3] flex flex-col justify-center items-center">
              <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
              <div>
                <p class="font-extrabold text-slate-900"><?php echo htmlspecialchars($c['contact_map_title']); ?></p>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest mt-1"><?php echo htmlspecialchars($c['contact_map_subtitle']); ?></p>
              </div>
              <p class="text-xs text-slate-500 font-medium max-w-xs leading-relaxed"><?php echo htmlspecialchars($c['contact_map_blurb']); ?></p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="lg:col-span-7">
        <div class="bg-white border border-slate-200 rounded-2xl p-8 md:p-10 space-y-8">
          <div>
            <h3 class="text-xl font-extrabold text-slate-900 uppercase tracking-wide">Inquiry Intake Form</h3>
            <p class="text-xs text-slate-500 font-semibold mt-1">Fill out the secure form below. Our admissions registrar will follow up within 24 business hours.</p>
          </div>

          <?php if ($sent): ?>
            <div class="p-8 border border-green-200 bg-green-50 rounded-2xl text-center space-y-3">
              <svg class="h-12 w-12 text-green-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <h4 class="text-lg font-bold text-slate-900">Message Delivered Successfully!</h4>
              <p class="text-xs text-slate-600">Thank you for contacting Dazu Hub Institute. We will call your phone number shortly.</p>
            </div>
          <?php else: ?>
            <?php if ($form_error): ?>
              <div class="p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($form_error); ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-6">
              <?php echo csrf_field(); ?>
              <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="block text-xs font-bold uppercase tracking-widest text-slate-500">Full Name *</label>
                  <input type="text" name="name" required placeholder="John Doe" value="<?php echo contact_old('name'); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
                </div>
                <div class="space-y-2">
                  <label class="block text-xs font-bold uppercase tracking-widest text-slate-500">Phone Number *</label>
                  <input type="tel" name="phone" required placeholder="0700 000 000" value="<?php echo contact_old('phone'); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
                </div>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="block text-xs font-bold uppercase tracking-widest text-slate-500">Email Address</label>
                  <input type="email" name="email" placeholder="john@example.com" value="<?php echo contact_old('email'); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
                </div>
                <div class="space-y-2">
                  <label class="block text-xs font-bold uppercase tracking-widest text-slate-500">Subject Topic</label>
                  <select name="subject" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
                    <?php foreach (['General Inquiry', 'Course Fees', 'Enrollment', 'Campus Visit', 'Partnership'] as $opt): ?>
                      <option <?php echo (($_POST['subject'] ?? 'General Inquiry') === $opt) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="space-y-2">
                <label class="block text-xs font-bold uppercase tracking-widest text-slate-500">Message Details *</label>
                <textarea name="message" required rows="5" placeholder="Specify your course interest or general question here..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600"><?php echo contact_old('message'); ?></textarea>
              </div>
              <div class="p-5 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-extrabold text-green-700 uppercase tracking-wider">Security Verification Challenge *</span>
                  <span class="text-[10px] text-slate-400 font-bold uppercase">Verify Human</span>
                </div>
                <div class="flex items-center gap-4">
                  <span class="text-sm font-bold text-slate-800">What is <?php echo (int) $captcha['a']; ?> + <?php echo (int) $captcha['b']; ?>?</span>
                  <input type="number" name="captcha_answer" required placeholder="Enter the math answer" class="flex-1 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
                </div>
              </div>
              <button type="submit" class="inline-flex items-center gap-2 px-8 py-3.5 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-extrabold uppercase tracking-widest rounded-xl">
                Send Inquiry
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
