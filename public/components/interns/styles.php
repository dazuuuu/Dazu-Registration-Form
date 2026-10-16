<?php // Intern Portal look (on top of components/app_shell/styles.php): Dazu green #44A024, charcoal #373435, Poppins, white cards. ?>
<style>
.ip .as-main{max-width:1320px}
.ip .as-nav .badge{background:#c9392c!important}
/* Phone tab bar as in the PWA design: white, green active item with a bar on top */
.ip .as-tabs{left:0;right:0;bottom:0;border-radius:0;background:#fff;border-top:1px solid var(--line);box-shadow:0 -4px 18px rgba(29,27,28,.06);padding:6px 6px calc(6px + env(safe-area-inset-bottom,0px))}
.ip .as-tabs a,.ip .as-tabs button{color:#6b7067}
.ip .as-tabs .on{background:transparent;color:var(--g)}
.ip .as-tabs .on:before{content:"";position:absolute;top:-6px;left:30%;right:30%;height:3px;border-radius:0 0 3px 3px;background:var(--g)}
.ip .as-nav a.on .badge{background:#fff!important;color:var(--g-d)!important}
.ip-card{background:#fff;border-radius:18px;box-shadow:var(--shadow);padding:18px 20px;margin-bottom:16px}
.ip-card h3{font-size:1.02rem;font-weight:600;margin:0}
.ip-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:12px}
.ip-head a,.ip-link{font-size:.8rem;font-weight:600;color:var(--g-d);text-decoration:none;background:var(--g-50);border-radius:999px;padding:4px 11px}
.ip-pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.7rem;font-weight:600;white-space:nowrap}
.ip-pill.g{background:#e7f4e1;color:#2f7a14}.ip-pill.o{background:#fff1dd;color:#b45309}.ip-pill.r{background:#fde8e6;color:#c0362c}
.ip-pill.b{background:#e6efff;color:#2b5fb5}.ip-pill.n{background:#eef0ee;color:#4a4e47}.ip-pill.d{background:#373435;color:#fff}
.ip-muted{color:var(--muted);font-size:.8rem}
/* Welcome banner (dashboard) */
.ip-hero{position:relative;overflow:hidden;border-radius:20px;background:var(--char);color:#fff;padding:24px 26px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between}
.ip-hero:after{content:"";position:absolute;right:-40px;top:-40px;width:180px;height:120px;background:var(--g);transform:rotate(28deg);opacity:.95}
.ip-hero>*{position:relative;z-index:1}
.ip-hero h1{color:#fff;font-size:1.45rem;margin:0 0 4px}
.ip-hero .sub{color:rgba(255,255,255,.75);font-size:.88rem}
.ip-hero .chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.ip-hero .chips span{font-size:.72rem;font-weight:600;border-radius:999px;padding:3px 10px;background:rgba(255,255,255,.12)}
.ip-hero .chips span.on{background:var(--g)}
.ip-clockbox{background:#fff;color:var(--ink);border-radius:16px;padding:14px 16px;min-width:260px}
.ip-clockbox .t{font-size:1.65rem;font-weight:700;line-height:1.1}
.ip-btn-out{background:#c9392c;border:0;color:#fff;border-radius:11px;font-weight:600;padding:.5rem 1rem}
.ip-btn-out:hover{background:#ad2e22;color:#fff}
.ip-btn-in{background:var(--g);border:0;color:#fff;border-radius:11px;font-weight:600;padding:.5rem 1rem}
.ip-btn-in:hover{background:var(--g-d);color:#fff}
/* Stat cards */
.ip-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:16px}
@media(min-width:768px){.ip-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(min-width:1200px){.ip-stats.six{grid-template-columns:repeat(6,minmax(0,1fr))}}
.ip-stat{background:#fff;border-radius:18px;box-shadow:var(--shadow);padding:16px 18px;min-width:0}
.ip-stat .k{font-size:.78rem;color:var(--muted)}
.ip-stat .v{font-size:1.65rem;font-weight:700;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ip-stat .s{font-size:.76rem;color:var(--muted)}
.ip-bar{height:6px;border-radius:99px;background:#eceee9;overflow:hidden;margin-top:8px}.ip-bar>div{height:100%;background:var(--g);border-radius:99px}
/* Rows */
.ip-row{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--line);border-radius:14px;margin-bottom:8px;text-decoration:none;color:inherit}
.ip-row:hover{border-color:var(--g-100);background:#fbfdf9}
.ip-row .grow{flex:1;min-width:0}.ip-row .nm{font-weight:600;font-size:.9rem}
.ip-alert{background:#fde8e6;color:#9f2a20;border-radius:12px;padding:10px 12px;font-size:.84rem}
.ip-ann{border-left:3px solid #d1d5db;padding:2px 0 2px 12px;margin-bottom:14px}
.ip-ann.urgent{border-color:#c9392c}.ip-ann.important{border-color:#e08a00}
/* Kanban */
.ip-board{display:grid;grid-template-columns:repeat(5,minmax(240px,1fr));gap:12px;overflow-x:auto;padding-bottom:6px}
.ip-col{background:#ecedea;border-radius:16px;padding:10px;min-height:120px}
.ip-col.rev{background:#f9e3e1}
.ip-col h4{font-size:.86rem;font-weight:600;margin:4px 4px 10px;display:flex;gap:6px;align-items:center}
.ip-col h4 .n{background:#fff;border-radius:999px;font-size:.7rem;padding:1px 8px}
.ip-task{display:block;background:#fff;border-radius:12px;padding:12px;margin-bottom:8px;text-decoration:none;color:inherit;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.ip-task:hover{box-shadow:0 6px 16px rgba(0,0,0,.08)}
.ip-task .ttl{font-weight:600;font-size:.88rem;margin:6px 0 4px}
.ip-task .who{font-size:.75rem;color:var(--muted);display:flex;align-items:center;gap:6px}
.ip-task .who b{width:20px;height:20px;border-radius:50%;background:var(--g);color:#fff;font-size:.55rem;display:inline-flex;align-items:center;justify-content:center}
.ip-quote{background:#fde8e6;color:#9f2a20;border-radius:10px;padding:8px 10px;font-size:.78rem;margin-top:6px}
.ip-viewtabs{display:flex;gap:6px;flex-wrap:wrap}
.ip-viewtabs a{padding:6px 16px;border-radius:999px;background:#fff;color:var(--ink);text-decoration:none;font-size:.86rem;font-weight:500;box-shadow:var(--shadow)}
.ip-viewtabs a.on{background:var(--g);color:#fff}
.ip-fab{position:fixed;right:16px;bottom:calc(92px + env(safe-area-inset-bottom,0px));z-index:1035;background:var(--g);color:#fff;border-radius:16px;padding:12px 18px;font-weight:600;text-decoration:none;box-shadow:0 10px 24px rgba(68,160,36,.4)}
.ip-fab:hover{color:#fff;background:var(--g-d)}
@media(min-width:992px){.ip-fab{display:none}}
/* Attendance */
.ip-ring{position:relative;width:170px;height:170px;margin:0 auto}
.ip-ring svg{transform:rotate(-90deg)}
.ip-ring .c{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.ip-ring .c b{font-size:1.9rem;line-height:1}
.ip-cal{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
.ip-cal .h{font-size:.72rem;color:var(--muted);text-align:center}
.ip-cal .d{height:34px;border-radius:8px;background:#eeefec;font-size:.7rem;display:flex;align-items:flex-start;justify-content:flex-start;padding:3px 5px;color:#fff}
.ip-cal .d.present{background:var(--g)}.ip-cal .d.late{background:#e08a00}.ip-cal .d.absent{background:#c9392c}.ip-cal .d.excused{background:#9aa5b1}
.ip-cal .d.off,.ip-cal .d.future{color:#9aa0a6}.ip-cal .d.blank{background:transparent}
.ip-legend span{font-size:.75rem;color:var(--muted);margin-left:10px}.ip-legend i{display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:4px}
.ip-stars{color:#f59e0b;letter-spacing:2px}.ip-stars .off{color:#d6d8d3}
.ip-tag{display:inline-block;background:var(--g-50);color:var(--g-d);border-radius:999px;padding:4px 12px;font-size:.78rem;font-weight:600;margin:0 6px 6px 0}
.ip-msg{max-width:80%;padding:9px 12px;border-radius:14px;font-size:.88rem;margin-bottom:8px;white-space:pre-line}
.ip-msg.me{background:var(--g);color:#fff;margin-left:auto;border-bottom-right-radius:4px}
.ip-msg.them{background:#f1f2ef;border-bottom-left-radius:4px}
.ip-msg small{display:block;opacity:.7;font-size:.68rem;margin-top:2px}
</style>
