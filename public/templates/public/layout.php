<?php
// public/templates/public/layout.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
$webRoot = WEB_ROOT === '' ? '' : WEB_ROOT;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo htmlspecialchars($page_title ?? 'Dazu Hub Institute'); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($page_description ?? 'Tech training institute offering Python, PHP, Node.js, Frontend and AI courses in Nairobi, Kenya.'); ?>">
<meta name="theme-color" content="#2E7D32">
<link rel="manifest" href="<?php echo url('manifest.json'); ?>">
<link rel="apple-touch-icon" href="<?php echo asset('icons/icon-192.png'); ?>">

<!-- Anti-flash: apply saved theme before any CSS renders -->
<script>
(function(){
    try {
        var t = localStorage.getItem('dh-theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
    } catch(e){}
})();
</script>

<!-- Google Fonts: Space Grotesk (display) + Inter (body) + JetBrains Mono (code) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ============================================================
   DAZU HUB INSTITUTE — Global Design System
   Theme: Forest green #2E7D32 from logo; dual light/dark mode.
   Fonts: Space Grotesk (display) | Inter (body) | JetBrains Mono (code)
============================================================ */

/* ── CSS Custom Properties ──────────────────────────────── */
:root {
    /* Brand greens (logo-derived) */
    --dh-green:        #2E7D32;
    --dh-green-mid:    #388E3C;
    --dh-green-light:  #4CAF50;
    --dh-green-pale:   #E8F5E9;
    --dh-green-dark:   #1B5E20;

    /* Light mode surfaces */
    --bg:          #FFFFFF;
    --bg-alt:      #F6F8F5;
    --bg-card:     #FFFFFF;
    --bg-input:    #F6F8F5;

    /* Light mode text */
    --text:        #1A1A1A;
    --text-sub:    #4B5563;
    --text-muted:  #6B7280;
    --text-invert: #FFFFFF;

    /* Light mode borders */
    --border:      #E2E8E2;
    --border-card: #E8EEE8;

    /* Light mode nav */
    --nav-bg:      rgba(255,255,255,0.96);
    --nav-border:  rgba(0,0,0,0.07);
    --nav-shadow:  0 2px 20px rgba(0,0,0,0.07);

    /* Light mode footer */
    --footer-bg:   #1A2B1A;
    --footer-text: rgba(255,255,255,0.65);

    /* Interactive states */
    --focus-ring:  0 0 0 3px rgba(46,125,50,0.2);

    /* Shadows */
    --shadow-sm:   0 1px 4px rgba(0,0,0,0.06);
    --shadow-md:   0 4px 16px rgba(0,0,0,0.08);
    --shadow-lg:   0 8px 32px rgba(0,0,0,0.12);
    --shadow-xl:   0 16px 48px rgba(0,0,0,0.16);

    /* Typography */
    --font-display: 'Space Grotesk', sans-serif;
    --font-body:    'Inter', sans-serif;
    --font-mono:    'JetBrains Mono', monospace;

    /* Radius */
    --r-xs: 4px;
    --r-sm: 8px;
    --r-md: 12px;
    --r-lg: 18px;
    --r-xl: 24px;
    --r-full: 9999px;

    /* Motion */
    --trans: 0.25s ease;
    --trans-slow: 0.4s ease;
}

/* ── Dark Mode ───────────────────────────────────────────── */
[data-theme="dark"] {
    --bg:          #0D1117;
    --bg-alt:      #111820;
    --bg-card:     #161D27;
    --bg-input:    #1C2430;

    --text:        #E6EDF3;
    --text-sub:    #8D96A0;
    --text-muted:  #636D76;
    --text-invert: #0D1117;

    --border:      #21292F;
    --border-card: #21292F;

    --nav-bg:      rgba(13,17,23,0.96);
    --nav-border:  rgba(255,255,255,0.06);
    --nav-shadow:  0 2px 20px rgba(0,0,0,0.4);

    --footer-bg:   #080E08;
    --footer-text: rgba(255,255,255,0.5);

    --shadow-sm:   0 1px 4px rgba(0,0,0,0.3);
    --shadow-md:   0 4px 16px rgba(0,0,0,0.4);
    --shadow-lg:   0 8px 32px rgba(0,0,0,0.5);
    --shadow-xl:   0 16px 48px rgba(0,0,0,0.6);
}

/* ── Global Reset ────────────────────────────────────────── */
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

/* Smooth theme transitions on everything that changes visually */
html {
    scroll-behavior: smooth;
}

body,
header, footer, nav, aside, section, main, article, div,
a, p, h1, h2, h3, h4, h5, h6, span, li, ul, ol,
input, select, textarea, button, label, table, tr, td, th {
    transition:
        background-color var(--trans),
        border-color var(--trans),
        color var(--trans),
        box-shadow var(--trans);
}

/* Override transition for things that should NOT transition on theme switch
   (transforms, opacity, width/height — keep those snappy) */
* { transition-property: background-color, border-color, color, box-shadow, fill, stroke; }
a, button { transition: background-color var(--trans), border-color var(--trans), color var(--trans), box-shadow var(--trans), transform 0.15s ease, opacity 0.15s ease; }

body {
    font-family: var(--font-body);
    background: var(--bg);
    color: var(--text);
    line-height: 1.65;
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
    padding-top: 80px; /* nav height */
}

@media (max-width: 768px) {
    body { padding-top: 70px; }
}

/* ── Typography ──────────────────────────────────────────── */
h1, h2, h3, h4, h5, h6 {
    font-family: var(--font-display);
    color: var(--text);
    line-height: 1.15;
    letter-spacing: -0.02em;
}

a { color: var(--dh-green); text-decoration: none; }
a:hover { color: var(--dh-green-mid); }

p { color: var(--text-sub); }

code, pre, .mono {
    font-family: var(--font-mono);
}

/* ── Container ───────────────────────────────────────────── */
.dh-container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
}

@media (max-width: 768px) { .dh-container { padding: 0 16px; } }

/* ── Buttons ─────────────────────────────────────────────── */
.dh-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 24px;
    border-radius: var(--r-sm);
    font-family: var(--font-display);
    font-size: 0.9rem; font-weight: 700;
    border: none; cursor: pointer; white-space: nowrap;
    text-decoration: none;
}

.dh-btn-primary {
    background: var(--dh-green);
    color: #fff;
    box-shadow: 0 4px 14px rgba(46,125,50,0.3);
}
.dh-btn-primary:hover {
    background: var(--dh-green-dark);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(46,125,50,0.4);
}

.dh-btn-outline {
    background: transparent;
    color: var(--text);
    border: 1.5px solid var(--border);
}
.dh-btn-outline:hover {
    border-color: var(--dh-green);
    color: var(--dh-green);
    transform: translateY(-2px);
}

.dh-btn-ghost {
    background: transparent;
    color: var(--dh-green);
    border: 1.5px solid transparent;
}
.dh-btn-ghost:hover {
    background: var(--dh-green-pale);
    color: var(--dh-green-dark);
}

[data-theme="dark"] .dh-btn-ghost:hover {
    background: rgba(46,125,50,0.15);
    color: var(--dh-green-light);
}

/* ── Scroll Reveal ───────────────────────────────────────── */
.dh-reveal {
    opacity: 0;
    transform: translateY(28px);
    transition: opacity 0.65s ease, transform 0.65s ease;
}
.dh-reveal.visible { opacity: 1; transform: none; }
.dh-reveal-left  { opacity: 0; transform: translateX(-28px); transition: opacity 0.65s ease, transform 0.65s ease; }
.dh-reveal-left.visible { opacity: 1; transform: none; }
.dh-reveal-right { opacity: 0; transform: translateX(28px);  transition: opacity 0.65s ease, transform 0.65s ease; }
.dh-reveal-right.visible { opacity: 1; transform: none; }

.dh-delay-1 { transition-delay: 0.1s; }
.dh-delay-2 { transition-delay: 0.2s; }
.dh-delay-3 { transition-delay: 0.3s; }
.dh-delay-4 { transition-delay: 0.4s; }
.dh-delay-5 { transition-delay: 0.5s; }

/* ── Section eyebrow / pill ──────────────────────────────── */
.dh-eyebrow {
    display: inline-block;
    font-size: 0.72rem; font-weight: 700;
    letter-spacing: 0.14em; text-transform: uppercase;
    color: var(--dh-green);
    margin-bottom: 12px;
}

.dh-section-title {
    font-family: var(--font-display);
    font-size: clamp(1.9rem, 3.5vw, 2.8rem);
    font-weight: 800;
    color: var(--text);
    margin-bottom: 14px;
}

.dh-section-sub {
    color: var(--text-muted);
    font-size: 1rem;
    max-width: 560px;
}

/* ── Badge ───────────────────────────────────────────────── */
.dh-badge {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 5px 14px;
    border-radius: var(--r-full);
    font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
    background: var(--dh-green-pale);
    color: var(--dh-green-dark);
    border: 1px solid rgba(46,125,50,0.2);
}
[data-theme="dark"] .dh-badge {
    background: rgba(46,125,50,0.15);
    color: var(--dh-green-light);
    border-color: rgba(76,175,80,0.25);
}
.dh-badge::before {
    content: '';
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--dh-green);
    animation: dhPulse 2.2s ease-in-out infinite;
}

@keyframes dhPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.45; transform: scale(0.8); }
}

/* ── Cards (shared) ──────────────────────────────────────── */
.dh-card {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: var(--r-lg);
    transition: transform var(--trans), box-shadow var(--trans), border-color var(--trans);
}
.dh-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: rgba(46,125,50,0.3);
}

/* ── Page load animation ─────────────────────────────────── */
@keyframes dhFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: none; }
}
main { animation: dhFadeIn 0.45s ease both; }

/* ── Scrollbar styling ───────────────────────────────────── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--bg-alt); }
::-webkit-scrollbar-thumb { background: var(--dh-green); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--dh-green-dark); }

/* ── Focus ring ──────────────────────────────────────────── */
:focus-visible {
    outline: 2px solid var(--dh-green);
    outline-offset: 3px;
}

/* ── Utility ─────────────────────────────────────────────── */
.sr-only {
    position: absolute; width: 1px; height: 1px;
    padding: 0; margin: -1px; overflow: hidden;
    clip: rect(0,0,0,0); border: 0;
}
</style>

<?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
<?php include __DIR__ . '/../../components/public/navbar.php'; ?>

<main id="main-content">
    <?php echo $content ?? ''; ?>
</main>

<?php include __DIR__ . '/../../components/public/footer.php'; ?>

<!-- Scroll Reveal (shared, used by all pages) -->
<script>
(function(){
    var io = new IntersectionObserver(function(entries){
        entries.forEach(function(e){
            if(e.isIntersecting){
                e.target.classList.add('visible');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('.dh-reveal, .dh-reveal-left, .dh-reveal-right')
            .forEach(function(el){ io.observe(el); });
})();
</script>

<?php if (isset($extra_js)) echo $extra_js; ?>
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('<?php echo url('sw.js'); ?>').catch(function () {});
  });
}
</script>
</body>
</html>