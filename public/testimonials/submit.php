<?php
$page_title = 'Share Your Story — Dazu hub Institute';
$page_description = 'Submit your Dazu Hub testimonial — text or video — for the homepage.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/helpers/testimonials.php';
require_once __DIR__ . '/../../app/v1/controllers/TestimonialController.php';

$open = testimonials_students_open();
$controller = new TestimonialController($pdo);
$error = '';
$success = '';

$customerName = '';
$customerEmail = '';
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $customerName = $user['username'] ?? '';
        $customerEmail = $user['email'] ?? '';
    }
}

$courses = [
    'Software Development', 'Web Development', 'Cybersecurity & Networks',
    'Data Analytics', 'AI & Data Science', 'Graphic Design', 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $open) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $type = ($_POST['testimonial_type'] ?? 'text') === 'video' ? 'video' : 'text';
        $result = $controller->submit([
            'customer_name' => $_POST['customer_name'] ?? '',
            'customer_email' => $_POST['customer_email'] ?? '',
            'customer_phone' => $_POST['customer_phone'] ?? '',
            'rating' => (int) ($_POST['rating'] ?? 5),
            'testimonial_type' => $type,
            'testimonial_text' => $_POST['testimonial_text'] ?? '',
            'video_url' => $_POST['video_url'] ?? '',
            'video_provider' => $_POST['video_provider'] ?? 'youtube',
            'service_tag' => $_POST['service_tag'] ?? '',
            'role' => $_POST['role'] ?? '',
        ]);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

ob_start();
?>
<div class="bg-slate-50 min-h-screen py-16">
  <div class="max-w-2xl mx-auto px-4">
    <div class="bg-white border border-slate-200 rounded-2xl p-8 md:p-10 space-y-8 shadow-sm">
      <div class="space-y-2 text-center">
        <span class="text-green-600 text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 bg-green-50 border border-green-200 rounded-full">Alumni Voices</span>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight pt-2">Share Your Testimonial</h1>
        <p class="text-xs text-slate-500 font-medium">Tell future students about your Dazu Hub experience. Approved stories appear on our homepage.</p>
      </div>

      <?php if (!$open): ?>
        <div class="p-6 border border-amber-200 bg-amber-50 text-amber-900 text-sm font-medium rounded-xl text-center">
          Student testimonial submissions are currently <strong>closed</strong>. Check back later or contact the institute office.
        </div>
        <div class="text-center">
          <a href="<?php echo url('/'); ?>" class="text-green-600 font-bold text-xs hover:underline">← Back to homepage</a>
        </div>
      <?php else: ?>

        <?php if ($error): ?>
          <div class="p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="p-4 border border-green-200 bg-green-50 text-green-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-6" id="testimonial-form">
          <?php echo csrf_field(); ?>

          <div class="grid grid-cols-2 gap-3 p-1 bg-slate-100 rounded-xl">
            <label class="cursor-pointer">
              <input type="radio" name="testimonial_type" value="text" class="peer sr-only" checked>
              <span class="block text-center py-2.5 text-xs font-bold rounded-lg peer-checked:bg-white peer-checked:text-[#15A24A] text-slate-600">Text Story</span>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="testimonial_type" value="video" class="peer sr-only">
              <span class="block text-center py-2.5 text-xs font-bold rounded-lg peer-checked:bg-white peer-checked:text-[#15A24A] text-slate-600">Video Link</span>
            </label>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-2">
              <label class="block text-xs font-bold uppercase tracking-widest text-slate-500">Full Name *</label>
              <input type="text" name="customer_name" required value="<?php echo htmlspecialchars($_POST['customer_name'] ?? $customerName); ?>"
                     class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
            </div>
            <div class="space-y-2">
              <label class="block text-xs font-bold uppercase tracking-widest text-slate-500">Email</label>
              <input type="email" name="customer_email" value="<?php echo htmlspecialchars($_POST['customer_email'] ?? $customerEmail); ?>"
                     class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-2">
              <label class="block text-xs font-bold uppercase tracking-widest text-slate-500">Course / Track</label>
              <select name="service_tag" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
                <?php foreach ($courses as $c): ?>
                  <option <?php echo (($_POST['service_tag'] ?? '') === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="space-y-2">
              <label class="block text-xs font-bold uppercase tracking-widest text-slate-500">Current Role / Title</label>
              <input type="text" name="role" placeholder="e.g. Data Analyst at Fintech Africa"
                     value="<?php echo htmlspecialchars($_POST['role'] ?? ''); ?>"
                     class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
            </div>
          </div>

          <div class="space-y-2">
            <label class="block text-xs font-bold uppercase tracking-widest text-slate-500">Rating</label>
            <select name="rating" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <option value="<?php echo $i; ?>" <?php echo ((int)($_POST['rating'] ?? 5) === $i) ? 'selected' : ''; ?>><?php echo $i; ?> stars</option>
              <?php endfor; ?>
            </select>
          </div>

          <div id="field-text" class="space-y-2">
            <label class="block text-xs font-bold uppercase tracking-widest text-slate-500" id="label-story">Your Story *</label>
            <textarea name="testimonial_text" rows="5" placeholder="Share how Dazu Hub helped your career..."
                      class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600"><?php echo htmlspecialchars($_POST['testimonial_text'] ?? ''); ?></textarea>
          </div>

          <div id="field-video" class="space-y-2 hidden">
            <label class="block text-xs font-bold uppercase tracking-widest text-slate-500">YouTube / Video URL *</label>
            <input type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=..."
                   value="<?php echo htmlspecialchars($_POST['video_url'] ?? ''); ?>"
                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
            <input type="hidden" name="video_provider" value="youtube">
            <p class="text-[10px] text-slate-400">Paste a YouTube link. You can add an optional caption in the text field when switching to video mode.</p>
          </div>

          <button type="submit" class="w-full py-3.5 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl">Submit Testimonial</button>
        </form>

        <div class="text-center">
          <a href="<?php echo url('/'); ?>" class="text-slate-400 text-xs hover:text-slate-600">← Back to homepage</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
(function(){
  var form = document.getElementById('testimonial-form');
  if (!form) return;
  var textField = document.getElementById('field-text');
  var videoField = document.getElementById('field-video');
  var textArea = form.querySelector('[name="testimonial_text"]');
  var videoUrl = form.querySelector('[name="video_url"]');
  function sync(){
    var isVideo = form.querySelector('[name="testimonial_type"]:checked')?.value === 'video';
    videoField.classList.toggle('hidden', !isVideo);
    if (textArea) textArea.required = !isVideo;
    if (videoUrl) videoUrl.required = isVideo;
    var label = document.getElementById('label-story');
    if (label) label.textContent = isVideo ? 'Optional Caption' : 'Your Story *';
  }
  form.querySelectorAll('[name="testimonial_type"]').forEach(function(r){ r.addEventListener('change', sync); });
  sync();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
