<?php // Shared announcement styles (feed, composer and the full-screen pop-up). Printed once per page. ?>
<?php if (empty($GLOBALS['__ann_css'])): $GLOBALS['__ann_css'] = true; ?>
<style>
.ann-embed{position:relative;width:100%;border-radius:12px;overflow:hidden;background:#0f172a}
.ann-embed iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.ann-img{display:block;width:100%;max-height:60vh;object-fit:contain;border-radius:12px;background:#f1f5f9}
.ann-audio{display:block;width:100%}
.ann-video{display:block;width:100%;max-height:60vh;border-radius:12px;background:#000}
.ann-media{display:grid;gap:10px;margin:12px 0}
.ann-meta{font-size:.8rem;color:#64748b}
.ann-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;margin-bottom:14px}
.ann-card.new{border-color:#0D9488;box-shadow:0 0 0 3px rgba(13,148,136,.12)}
.ann-card h3{font-size:1.1rem;font-weight:700;margin:0 0 4px}
.ann-summary{color:#334155;margin:0 0 8px}
.ann-msg{white-space:pre-line;color:#1f2937}
/* Full-screen pop-up: blurred page behind, card in the middle, close bar with an X on top */
.ann-pop{position:fixed;inset:0;z-index:2000;display:flex;align-items:center;justify-content:center;padding:16px;
  background:rgba(15,23,42,.45);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);animation:annIn .25s ease}
.ann-pop[hidden]{display:none}
.ann-pop-card{position:relative;width:min(680px,100%);max-height:calc(100vh - 32px);overflow:auto;background:#fff;border-radius:20px;
  box-shadow:0 30px 80px rgba(0,0,0,.35)}
.ann-pop-bar{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px 10px 18px;
  background:rgba(255,255,255,.72);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);border-bottom:1px solid rgba(226,232,240,.8)}
.ann-pop-tag{font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#0D9488}
.ann-pop-x{width:38px;height:38px;border-radius:50%;border:0;background:rgba(15,23,42,.08);color:#0f172a;font-size:18px;display:flex;align-items:center;justify-content:center}
.ann-pop-x:hover{background:rgba(15,23,42,.16)}
.ann-pop-body{padding:16px 20px 22px}
.ann-pop-body h2{font-size:1.45rem;font-weight:800;margin:0 0 6px;color:#0f172a}
.ann-pop-count{font-size:.75rem;color:#64748b}
@keyframes annIn{from{opacity:0}to{opacity:1}}
@media(max-width:576px){.ann-pop{padding:0;align-items:stretch}.ann-pop-card{border-radius:0;max-height:100vh;height:100%}}
</style>
<?php endif; ?>
