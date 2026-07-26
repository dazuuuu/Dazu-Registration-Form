<?php
if (!function_exists('csrf_field')) {
    require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
}
?>
<section id="newsletter" class="py-20 bg-slate-50 border-y border-slate-100">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="max-w-2xl mx-auto text-center space-y-3">
      <span class="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full">Stay In The Loop</span>
      <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900">Get Course Updates &amp; Tech Tips</h2>
      <p class="text-slate-600 font-medium leading-relaxed">Subscribe for new course launches, campus events, and career tips from Dazu Hub Institute. No spam, unsubscribe anytime.</p>
    </div>

    <form id="dazu-newsletter-form" class="mt-8 max-w-lg mx-auto flex flex-col sm:flex-row gap-3" novalidate>
      <?php echo csrf_field(); ?>
      <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
      <input type="email" name="email" id="dazu-newsletter-email" required placeholder="you@example.com"
             class="flex-1 px-4 py-3.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
      <button type="submit" class="px-8 py-3.5 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl transition-all duration-200 whitespace-nowrap">
        Subscribe
      </button>
    </form>
    <p id="dazu-newsletter-feedback" class="hidden max-w-lg mx-auto mt-4 text-xs font-semibold text-center rounded-xl px-4 py-3"></p>
  </div>
</section>
<script>
(function(){
  var form = document.getElementById('dazu-newsletter-form');
  var feedback = document.getElementById('dazu-newsletter-feedback');
  if (!form || !feedback) return;

  function showFeedback(message, ok){
    feedback.textContent = message;
    feedback.classList.remove('hidden', 'bg-green-50', 'text-green-700', 'border', 'border-green-200', 'bg-red-50', 'text-red-700', 'border-red-200');
    feedback.classList.add(ok ? 'bg-green-50' : 'bg-red-50', ok ? 'text-green-700' : 'text-red-700', 'border', ok ? 'border-green-200' : 'border-red-200');
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    var button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Subscribing…';

    fetch('<?php echo url('api/newsletter/subscribe.php'); ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(new FormData(form))
    })
      .then(function(res){ return res.json(); })
      .then(function(data){
        showFeedback(data.message || (data.success ? 'Subscribed!' : 'Something went wrong.'), !!data.success);
        if (data.success) form.reset();
      })
      .catch(function(){
        showFeedback('Network error. Please try again.', false);
      })
      .finally(function(){
        button.disabled = false;
        button.textContent = 'Subscribe';
      });
  });
})();
</script>
