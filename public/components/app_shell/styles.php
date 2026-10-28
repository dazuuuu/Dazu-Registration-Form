<?php
// App shell look for the tutor and student portals: Dazu green + charcoal, Poppins.
// Desktop: fixed sidebar + slim top bar. Phones/tablets: compact app bar + floating bottom tab bar with a "More" sheet.
// Also restyles the portals' existing building blocks (.card-tutor, .card-student, .stat-card, tables, buttons, forms)
// so every page picks up the new look without being rewritten.
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
    --g:#44A024; --g-d:#36851b; --g-50:#eef8e9; --g-100:#dcf0d2;
    --char:#373435; --ink:#1d1b1c; --muted:#6b7067; --line:#e9ebe6; --bg:#f4f5f1; --card:#fff;
    --r:18px; --r-sm:12px; --side:264px;
    --shadow:0 1px 2px rgba(29,27,28,.04),0 6px 20px rgba(29,27,28,.05);
    --brand:var(--g); --brand-dark:var(--g-d); --brand-light:var(--g-50); --text:var(--ink); --border:var(--line);
}
html{-webkit-text-size-adjust:100%}
body{font-family:Poppins,system-ui,-apple-system,'Segoe UI',sans-serif;background:var(--bg);color:var(--ink);font-size:.93rem;min-height:100vh;-webkit-font-smoothing:antialiased}
a{color:var(--g-d)}
h1,h2,h3,h4,h5,h6{font-weight:600;letter-spacing:-.01em;color:var(--ink)}

/* ── Sidebar (desktop) ─────────────────────────────────────────────── */
.as-side{position:fixed;inset:0 auto 0 0;width:var(--side);background:#fff;border-right:1px solid var(--line);flex-direction:column;z-index:1030;overflow:hidden}
.as-side:before{content:"";position:absolute;right:-96px;top:-74px;width:190px;height:120px;background:var(--char);transform:rotate(28deg);box-shadow:-10px 12px 0 var(--g);opacity:.95}
.as-logo{position:relative;display:flex;align-items:center;padding:22px 22px 8px}
.as-logo img{height:40px;width:auto}
.as-role{position:relative;margin:6px 22px 14px;display:inline-flex;align-self:flex-start;align-items:center;gap:6px;font-size:.68rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--g-d);background:var(--g-50);border-radius:999px;padding:4px 10px}
.as-nav{flex:1;overflow-y:auto;padding:4px 14px 14px}
.as-nav a{display:flex;align-items:center;gap:12px;padding:10px 12px;margin-bottom:2px;border-radius:12px;color:#4a4e47;text-decoration:none;font-weight:500;font-size:.9rem;transition:background .15s,color .15s}
.as-nav a i{width:20px;text-align:center;font-size:1rem;color:#8a8f86;transition:color .15s}
.as-nav a:hover{background:#f5f7f3;color:var(--ink)}
.as-nav a.on{background:var(--g);color:#fff;box-shadow:0 6px 16px rgba(68,160,36,.28)}
.as-nav a.on i{color:#fff}
.as-nav .as-sep{font-size:.66rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#a3a89f;padding:14px 12px 6px}
.as-me{display:flex;align-items:center;gap:10px;margin:10px 14px 14px;padding:10px;border-radius:14px;background:#f6f7f4}
.as-me .nm{font-weight:600;font-size:.85rem;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.as-me .sb{font-size:.72rem;color:var(--muted)}
.as-me a{margin-left:auto;color:#b42318;width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:10px}
.as-me a:hover{background:#fde8e6}
.as-ava{width:38px;height:38px;border-radius:12px;flex:none;display:inline-flex;align-items:center;justify-content:center;background:var(--char);color:#fff;font-weight:600;font-size:.9rem;object-fit:cover;overflow:hidden}

/* ── Top bar ───────────────────────────────────────────────────────── */
.as-top{position:sticky;top:0;z-index:1020;display:flex;align-items:center;gap:10px;padding:10px 16px;background:rgba(244,245,241,.86);backdrop-filter:saturate(1.4) blur(12px);-webkit-backdrop-filter:saturate(1.4) blur(12px);border-bottom:1px solid transparent}
.as-top.scrolled{border-bottom-color:var(--line);background:rgba(255,255,255,.9)}
.as-top .as-mark{width:36px;height:36px;border-radius:11px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow);flex:none}
.as-top .as-mark img{width:26px;height:26px;object-fit:contain}
.as-title{min-width:0;flex:1}
.as-title .t{font-weight:600;font-size:1.02rem;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.as-title .s{font-size:.74rem;color:var(--muted)}
.as-actions{display:flex;align-items:center;gap:6px}
.as-iconbtn{width:40px;height:40px;border-radius:12px;border:0;background:#fff;color:#4a4e47;display:inline-flex;align-items:center;justify-content:center;box-shadow:var(--shadow);text-decoration:none}
.as-iconbtn:hover{color:var(--g-d)}
.as-actions .notif-bell{width:40px;height:40px;border-radius:12px;background:#fff;box-shadow:var(--shadow);color:#4a4e47}
.as-actions .notif-bell:hover{background:#fff;color:var(--g-d)}
.as-user{border:0;background:transparent;padding:0;display:flex;align-items:center;gap:8px}
.as-user .as-ava{width:40px;height:40px}
.dropdown-menu{border:1px solid var(--line);border-radius:14px;box-shadow:0 12px 32px rgba(29,27,28,.12);padding:6px}
.dropdown-item{border-radius:9px;padding:8px 12px;font-size:.88rem}
.dropdown-item:active{background:var(--g)}

/* ── Content ───────────────────────────────────────────────────────── */
.as-body{min-width:0;overflow-x:clip}
.as-main{max-width:1180px;margin:0 auto;padding:8px 16px 28px}
.as-main>*{max-width:100%}
@media(max-width:575.98px){.pwa-step .btn{white-space:normal}.pwa-step-body{min-width:0;flex-basis:calc(100% - 44px)}}
@media(min-width:992px){
    .as-body{margin-left:var(--side)}
    .as-top{padding:16px 32px}
    .as-main{padding:8px 32px 48px}
}
@media(max-width:991.98px){
    .as-main{padding-bottom:calc(104px + env(safe-area-inset-bottom,0px))}
}

/* ── Bottom tab bar (phones/tablets) ───────────────────────────────── */
.as-tabs{position:fixed;left:12px;right:12px;bottom:calc(10px + env(safe-area-inset-bottom,0px));z-index:1040;display:flex;background:var(--char);border-radius:22px;padding:7px;box-shadow:0 14px 34px rgba(29,27,28,.28)}
.as-tabs a,.as-tabs button{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;padding:7px 2px;border:0;background:transparent;border-radius:16px;color:#bdbfba;text-decoration:none;font-size:.66rem;font-weight:500;position:relative;transition:background .2s,color .2s}
.as-tabs i{font-size:1.12rem}
.as-tabs .on{background:var(--g);color:#fff}
.as-tabs .dot{position:absolute;top:4px;right:calc(50% - 16px);min-width:16px;height:16px;padding:0 4px;border-radius:8px;background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;line-height:16px}
@media(min-width:992px){.as-tabs{display:none}}

/* "More" bottom sheet */
.as-sheet.offcanvas-bottom{height:auto;max-height:82vh;border-radius:24px 24px 0 0;border:0;padding-bottom:env(safe-area-inset-bottom,0px)}
.as-sheet.offcanvas-bottom{display:flex;flex-direction:column}
/* The sheet body scrolls: without min-height:0 a long list grows past the screen and the last items cannot be reached. */
.as-sheet .offcanvas-body{flex:1 1 auto;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;padding-bottom:calc(20px + env(safe-area-inset-bottom,0px))}
@media(max-width:359.98px){.as-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.as-tabs a,.as-tabs button{font-size:.58rem}}
.as-sheet .grab{width:42px;height:5px;border-radius:3px;background:#d8dbd4;margin:10px auto 2px}
.as-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.as-grid a{display:flex;flex-direction:column;align-items:center;gap:8px;padding:14px 6px;border-radius:16px;background:#f6f7f4;color:var(--ink);text-decoration:none;font-size:.78rem;font-weight:500;text-align:center}
.as-grid a i{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;background:#fff;color:var(--g-d);font-size:1.05rem;box-shadow:var(--shadow)}
.as-grid a.on{background:var(--g-50);color:var(--g-d)}
.as-grid a.danger i{color:#b42318}

/* ── Restyle existing portal blocks ────────────────────────────────── */
.card-tutor,.card-student,.stat-card{background:var(--card);border:0;border-radius:var(--r);box-shadow:var(--shadow);overflow:hidden}
.card-tutor .head,.card-student .card-head{background:#fff;border-bottom:1px solid var(--line);padding:15px 20px;font-weight:600;font-size:.95rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.card-tutor .head i,.card-student .card-head i{color:var(--g)}
.stat-card{padding:18px}
.stat-card .label{font-size:.75rem;color:var(--muted);font-weight:500;text-transform:uppercase;letter-spacing:.06em}
.stat-card .value{color:var(--ink);font-weight:700}
.card{border:0;border-radius:var(--r);box-shadow:var(--shadow)}
.card.shadow-sm{box-shadow:var(--shadow)!important}
.table{--bs-table-hover-bg:#f7f9f5;margin-bottom:0}
.table>:not(caption)>*>*{padding:.8rem 1rem;border-bottom-color:var(--line)}
.table thead th,.table-light th{background:#fafbf8!important;color:var(--muted);font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--line)}
.list-group-item{border-color:var(--line)}
.btn{border-radius:11px;font-weight:500;padding:.5rem 1rem}
.btn-sm{border-radius:9px;padding:.32rem .7rem}
.btn-brand,.btn-success,.btn-primary{background:var(--g);border-color:var(--g);color:#fff}
.btn-brand:hover,.btn-success:hover,.btn-primary:hover,.btn-brand:focus,.btn-success:focus,.btn-primary:focus{background:var(--g-d);border-color:var(--g-d);color:#fff}
.btn-outline-success,.btn-outline-primary{color:var(--g-d);border-color:#bfe0b1}
.btn-outline-success:hover,.btn-outline-primary:hover{background:var(--g);border-color:var(--g);color:#fff}
.btn-dark{background:var(--char);border-color:var(--char)}
.btn-link{color:var(--g-d)}
.text-success,.text-brand{color:var(--g-d)!important}
.bg-success{background-color:var(--g)!important}
.text-primary{color:var(--char)!important}
.progress{background:#eceee9;border-radius:99px}
.progress-bar.bg-success,.progress-bar{background-color:var(--g)}
.form-control,.form-select{border-radius:11px;border-color:#dfe2db;padding:.55rem .8rem}
.form-control-sm,.form-select-sm{border-radius:9px;padding:.35rem .65rem}
.form-control:focus,.form-select:focus{border-color:var(--g);box-shadow:0 0 0 .2rem rgba(68,160,36,.15)}
.form-check-input:checked{background-color:var(--g);border-color:var(--g)}
.alert{border:0;border-radius:14px}
.badge{font-weight:600;border-radius:8px;padding:.38em .62em}
.accordion-button:not(.collapsed){background:var(--g-50);color:var(--g-d)}
.accordion-button:focus{box-shadow:none}
.modal-content{border:0;border-radius:20px}
.nav-tabs .nav-link.active,.nav-pills .nav-link.active{color:var(--g-d)}
.nav-pills .nav-link.active{background:var(--g);color:#fff}

/* ── Reusable page pieces ──────────────────────────────────────────── */
.as-hero{position:relative;overflow:hidden;border-radius:22px;background:var(--char);color:#fff;padding:22px;margin-bottom:18px}
.as-hero:after{content:"";position:absolute;right:-60px;top:-50px;width:260px;height:150px;background:var(--g);transform:rotate(-24deg);opacity:.9;border-radius:30px}
.as-hero:before{content:"";position:absolute;right:-30px;bottom:-80px;width:200px;height:200px;border-radius:50%;background:rgba(68,160,36,.18)}
.as-hero>*{position:relative;z-index:1}
.as-hero .hi{font-size:.8rem;opacity:.75}
.as-hero h1{color:#fff;font-size:1.45rem;margin:2px 0 6px}
.as-hero p{opacity:.8;margin:0;font-size:.88rem;max-width:520px}
.as-hero .btn-light{background:#fff;color:var(--ink);border:0}
.as-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:18px}
@media(min-width:768px){.as-stats{grid-template-columns:repeat(4,minmax(0,1fr))}}
.as-stat{background:#fff;border-radius:var(--r);box-shadow:var(--shadow);padding:16px;display:flex;flex-direction:column;gap:6px;text-decoration:none;color:inherit;min-width:0}
.as-stat .ic{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:var(--g-50);color:var(--g-d)}
.as-stat .ic.dark{background:#ecebeb;color:var(--char)}
.as-stat .ic.warn{background:#fff4e0;color:#b45309}
.as-stat .ic.red{background:#fde8e6;color:#b42318}
.as-stat .k{font-size:.72rem;color:var(--muted);font-weight:500;text-transform:uppercase;letter-spacing:.06em}
.as-stat .v{font-size:1.35rem;font-weight:700;line-height:1.1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.as-section-title{display:flex;align-items:center;justify-content:space-between;margin:22px 0 10px}
.as-section-title h2{font-size:1rem;margin:0}
.as-quick{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
@media(min-width:768px){.as-quick{grid-template-columns:repeat(4,minmax(0,1fr))}}
.as-quick a{display:flex;align-items:center;gap:10px;padding:14px;border-radius:16px;background:#fff;box-shadow:var(--shadow);color:var(--ink);text-decoration:none;font-weight:500;font-size:.86rem}
.as-quick a i{width:36px;height:36px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:var(--g-50);color:var(--g-d);flex:none}
.as-quick a:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(29,27,28,.08)}
.as-empty{text-align:center;padding:40px 16px;color:var(--muted)}
.as-empty i{font-size:2.2rem;opacity:.3;margin-bottom:10px;display:block}

/* PWA bits re-coloured to the brand */
.pwa-banner{border-color:var(--g-100)!important;border-left-color:var(--g)!important;border-radius:var(--r)!important;box-shadow:var(--shadow)!important}
.pwa-step-done .pwa-step-icon,.pwa-progress>div{background:var(--g)!important}
.pwa-step-done [data-step-text]{color:var(--g-d)!important}
.locked-banner{background:linear-gradient(135deg,#b42318,#7a1a12);color:#fff;border-radius:22px;padding:26px;margin-bottom:20px;text-align:center}
.locked-banner i{font-size:2.4rem;margin-bottom:10px}
.badge-plan{background:var(--g-50);color:var(--g-d)}
</style>
