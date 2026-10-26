<?php
// "Liquid glass" phone look for the tutor portal: white with soft Dazu-green light, frosted see-through cards,
// a frosted header and a floating frosted tab bar. Only below 992px and only on <body class="as-glass">;
// the desktop sidebar layout is unchanged. It restyles the shared blocks so every tutor page follows.
?>
<style>
@media (max-width: 991.98px) {
body.as-glass{
    --gl-card:rgba(255,255,255,.58); --gl-line:rgba(255,255,255,.8); --gl-edge:rgba(68,160,36,.14);
    --gl-text:#1f2421; --gl-muted:#5f6b61;
    --g:#44A024; --g-d:#2f7a14; --g-50:rgba(68,160,36,.12); --g-100:rgba(68,160,36,.24); --muted:var(--gl-muted); --ink:var(--gl-text);
    background:
        radial-gradient(70% 40% at 0% 0%, rgba(126,217,87,.45) 0%, transparent 70%),
        radial-gradient(60% 35% at 100% 18%, rgba(68,160,36,.28) 0%, transparent 70%),
        radial-gradient(80% 40% at 50% 100%, rgba(168,230,143,.35) 0%, transparent 70%),
        linear-gradient(180deg, #f7fbf4 0%, #ffffff 55%, #f1f8ec 100%) fixed;
    color:var(--gl-text);
}
html:has(body.as-glass){background:#f7fbf4}
body.as-glass a{color:var(--g-d)}
body.as-glass .text-muted,body.as-glass .form-text{color:var(--gl-muted)!important}

/* Frosted header card */
body.as-glass .as-top{margin:10px 12px 6px;padding:14px 14px 14px 18px;border-radius:24px;background:rgba(255,255,255,.55);border:1px solid var(--gl-line);
    -webkit-backdrop-filter:blur(22px) saturate(1.6);backdrop-filter:blur(22px) saturate(1.6);box-shadow:0 10px 30px rgba(47,122,20,.12),inset 0 1px 0 rgba(255,255,255,.9);top:8px}
body.as-glass .as-top.scrolled{background:rgba(255,255,255,.72)}
body.as-glass .as-top .as-mark{display:none!important}
body.as-glass .as-apptitle .t{font-size:1.55rem;font-weight:700;color:#1f2421;letter-spacing:-.02em}
body.as-glass .as-apptitle .s{font-size:.9rem;color:var(--gl-muted)}
body.as-glass .as-iconbtn,body.as-glass .as-actions .notif-bell{width:46px;height:46px;border-radius:15px;background:rgba(255,255,255,.6);color:#2f7a14;border:1px solid var(--gl-line);
    box-shadow:0 4px 12px rgba(47,122,20,.1),inset 0 1px 0 #fff}
body.as-glass .as-user .as-ava{width:52px;height:52px;border:2px solid #fff;box-shadow:0 4px 12px rgba(47,122,20,.18)}
body.as-glass .as-ava{background:rgba(68,160,36,.14);color:#2f7a14}

/* Floating frosted tab bar */
body.as-glass .as-tabs{left:10px;right:10px;background:rgba(255,255,255,.6);border:1px solid var(--gl-line);border-radius:24px;padding:6px;
    -webkit-backdrop-filter:blur(24px) saturate(1.7);backdrop-filter:blur(24px) saturate(1.7);box-shadow:0 14px 34px rgba(47,122,20,.18),inset 0 1px 0 #fff}
body.as-glass .as-tabs a,body.as-glass .as-tabs button{color:#5f6b61;font-size:.62rem}
body.as-glass .as-tabs .on{background:linear-gradient(135deg,#5cb83a,#44A024);color:#fff;box-shadow:0 6px 14px rgba(68,160,36,.35)}
body.as-glass .as-tabs .on i{color:#fff}

/* Sheets */
body.as-glass .as-sheet.offcanvas-bottom{background:rgba(255,255,255,.82);color:var(--gl-text);-webkit-backdrop-filter:blur(22px) saturate(1.5);backdrop-filter:blur(22px) saturate(1.5);border-top:1px solid var(--gl-line)}
body.as-glass .as-grid a{background:rgba(255,255,255,.65);color:var(--gl-text);border:1px solid var(--gl-edge)}
body.as-glass .as-grid a i{background:rgba(68,160,36,.12);color:#2f7a14;box-shadow:none}
body.as-glass .as-grid a.on{background:rgba(68,160,36,.14);color:#2f7a14}

/* Frosted cards everywhere */
body.as-glass .card-tutor,body.as-glass .card,body.as-glass .as-stat,body.as-glass .as-quick a,body.as-glass .ann-card,body.as-glass .pwa-banner,
body.as-glass .ts-card,body.as-glass .asg-card,body.as-glass .asg-panel,body.as-glass .asg-box,body.as-glass .asg-fb,body.as-glass .cl-card{
    background:var(--gl-card)!important;color:var(--gl-text);border:1px solid var(--gl-line)!important;
    box-shadow:0 8px 24px rgba(47,122,20,.08),inset 0 1px 0 rgba(255,255,255,.9)!important;-webkit-backdrop-filter:blur(18px) saturate(1.5);backdrop-filter:blur(18px) saturate(1.5)}
body.as-glass .card-tutor .head,body.as-glass .card-header,body.as-glass .card-footer{background:transparent!important;border-color:var(--gl-edge)}
body.as-glass .as-hero{background:linear-gradient(135deg,rgba(68,160,36,.92),rgba(47,122,20,.88));border:1px solid rgba(255,255,255,.4)}
body.as-glass .list-group-item,body.as-glass .accordion-item,body.as-glass .accordion-button{background:transparent;border-color:var(--gl-edge)}
body.as-glass .accordion-button:not(.collapsed){background:rgba(68,160,36,.1);color:#2f7a14}
body.as-glass .table{--bs-table-bg:transparent}
body.as-glass .table thead th,body.as-glass .table-light th{background:rgba(255,255,255,.5)!important}
body.as-glass .ts-course,body.as-glass .ts-meta div,body.as-glass .asg-section{background:rgba(255,255,255,.55)!important}
body.as-glass .form-control,body.as-glass .form-select,body.as-glass .ts-search input{background-color:rgba(255,255,255,.7);border-color:rgba(68,160,36,.18)}
body.as-glass .form-control:focus,body.as-glass .form-select:focus{background-color:#fff;border-color:#44A024;box-shadow:0 0 0 .2rem rgba(68,160,36,.15)}
body.as-glass .btn-brand,body.as-glass .btn-success,body.as-glass .btn-primary{background:linear-gradient(135deg,#5cb83a,#44A024);border:0;color:#fff;box-shadow:0 6px 14px rgba(68,160,36,.28)}
body.as-glass .btn-outline-secondary,body.as-glass .btn-outline-success,body.as-glass .btn-outline-primary{background:rgba(255,255,255,.6);border-color:rgba(68,160,36,.25);color:#2f7a14}
body.as-glass .progress,body.as-glass .td-mini{background:rgba(68,160,36,.12)}
body.as-glass .progress-bar,body.as-glass .td-mini>div{background:linear-gradient(90deg,#44A024,#7ed957)!important}
}
</style>
