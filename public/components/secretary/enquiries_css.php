<style>
/* The enquiries log (secretary portal). Dazu colours: #048C44, #037A3B, #E3F3EA. */
.se-stats { display:grid; grid-template-columns:repeat(4, 1fr); gap:20px; }
.se-stat { background:#fff; border-radius:22px; padding:20px 24px; color:inherit; display:block; border:1px solid transparent; }
.se-stat:hover { border-color:var(--g); } .se-stat small { display:block; color:var(--mut); font-size:.95rem; } .se-stat b { display:block; font-size:2.2rem; line-height:1.2; margin-top:6px; }
.se-box { background:#fff; border-radius:26px; padding:24px 28px; margin-top:22px; }
.se-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:14px; }
.se-head h2 { font-size:1.3rem; font-weight:800; margin:0; } .se-head p { margin:4px 0 0; color:var(--mut); font-size:.95rem; }
.se-tools { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.se-tools input[type=search], .se-tools select { min-height:46px; border:1px solid var(--line); border-radius:14px; padding:0 14px; background:#fff; font-size:.95rem; }
.se-tools input[type=search] { min-width:230px; }
.se-add { background:var(--g); color:#fff; border:0; border-radius:14px; min-height:48px; padding:0 22px; font-weight:800; display:inline-flex; align-items:center; gap:8px; cursor:pointer; } .se-add:hover { background:var(--gd); }
.se-table { width:100%; border-collapse:collapse; }
.se-table th { font-size:.74rem; text-transform:uppercase; letter-spacing:.08em; color:#6b7570; text-align:left; padding:12px 10px; border-bottom:1px solid var(--line); }
.se-table td { padding:16px 10px; border-bottom:1px solid var(--line); vertical-align:middle; } .se-table tbody tr { cursor:pointer; } .se-table tbody tr:hover { background:#fafcfa; } .se-table tbody tr:last-child td { border-bottom:0; }
.se-name { font-weight:800; color:var(--ink); display:block; } .se-first small { color:var(--mut); display:block; margin-top:2px; overflow-wrap:anywhere; }
.se-by { display:inline-flex; align-items:center; gap:10px; } .se-by i { width:34px; height:34px; border-radius:50%; background:#2b2f2d; color:#fff; font-style:normal; font-weight:800; font-size:.8rem; display:inline-flex; align-items:center; justify-content:center; }
.se-chip { display:inline-block; font-size:.8rem; font-weight:800; padding:5px 14px; border-radius:99px; white-space:nowrap; }
.se-chip.pending { background:#fff4d6; color:#8a6100; } .se-chip.called_back { background:var(--gl); color:var(--gd); } .se-chip.registered { background:#e5e9fb; color:#2f3f94; } .se-chip.not_interested { background:#eceeea; color:#555e59; }
.se-late { color:#b42318; font-size:.72rem; font-weight:800; }
.se-empty { text-align:center; padding:36px 10px; color:var(--mut); } .se-empty i { display:block; font-size:2rem; color:#b9c7be; margin-bottom:8px; } .se-empty b { display:block; color:#3b443f; }
.se-pager { display:flex; justify-content:space-between; align-items:center; gap:10px; padding-top:14px; color:var(--mut); } .se-pager a { color:var(--gd); font-weight:700; min-height:44px; display:inline-flex; align-items:center; }
.se-notes { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-top:22px; }
.se-notes > div { background:#fff; border-radius:24px; padding:22px 26px; } .se-notes h3 { font-size:1.05rem; font-weight:800; margin:0 0 8px; } .se-notes p { margin:0; color:var(--mut); }
/* the add sheet: a centred window on a computer, a full screen sheet on a phone */
.se-sheet { position:fixed; inset:0; background:rgba(15,30,22,.55); z-index:100; display:none; align-items:center; justify-content:center; padding:20px; }
.se-sheet.open { display:flex; }
.se-form { background:#fff; border-radius:24px; width:100%; max-width:720px; max-height:92vh; overflow-y:auto; padding:24px 26px; }
.se-form-h { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; } .se-form-h h2 { font-size:1.25rem; font-weight:800; margin:0; color:var(--gd); }
.se-x { border:0; background:var(--gl); color:var(--gd); width:44px; height:44px; border-radius:12px; font-size:1.1rem; }
.se-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; } .se-grid .full { grid-column:1 / -1; }
.se-form label { display:block; font-size:.8rem; font-weight:700; color:#374151; margin-bottom:5px; }
.se-form input, .se-form select, .se-form textarea { width:100%; min-height:46px; border:1px solid #cbd5d0; border-radius:12px; padding:8px 12px; font-size:1rem; background:#fff; }
.se-form input:focus, .se-form select:focus, .se-form textarea:focus { outline:0; border-color:var(--g); box-shadow:0 0 0 .2rem rgba(4,140,68,.18); }
.se-form-f { display:flex; gap:10px; margin-top:18px; flex-wrap:wrap; }
.se-save { background:var(--g); border:0; color:#fff; font-weight:800; border-radius:14px; min-height:52px; padding:0 28px; cursor:pointer; } .se-save:hover { background:var(--gd); }
.se-cancel { background:#fff; border:1px solid #cbd5d0; color:#374151; font-weight:700; border-radius:14px; min-height:52px; padding:0 22px; cursor:pointer; }
/* one enquiry */
.se-card { background:#fff; border-radius:24px; padding:22px 26px; margin-bottom:20px; } .se-card h2 { font-size:1.1rem; font-weight:800; margin:0 0 12px; color:var(--gd); }
.se-dl { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px 20px; } .se-dl small { display:block; color:var(--mut); font-size:.74rem; text-transform:uppercase; letter-spacing:.06em; } .se-dl div { overflow-wrap:anywhere; }
.se-actions { display:grid; grid-template-columns:1fr 1fr; gap:18px; } .se-actions form { background:#f7faf8; border:1px solid var(--line); border-radius:16px; padding:14px; }
.se-actions label { font-size:.8rem; font-weight:700; display:block; margin-bottom:6px; } .se-actions select, .se-actions input, .se-actions textarea { width:100%; min-height:46px; border:1px solid #cbd5d0; border-radius:12px; padding:8px 12px; background:#fff; margin-bottom:10px; }
.se-btn { background:var(--g); color:#fff; border:0; border-radius:12px; min-height:46px; padding:0 18px; font-weight:800; cursor:pointer; } .se-btn:hover { background:var(--gd); } .se-btn.ghost { background:#fff; color:var(--gd); border:1px solid var(--g); }
.se-note { padding:12px 0; border-bottom:1px solid var(--line); } .se-note:last-child { border:0; } .se-note b { font-weight:800; } .se-note small { color:var(--mut); margin-left:6px; } .se-note p { margin:4px 0 0; white-space:pre-wrap; overflow-wrap:anywhere; }
.se-hist { font-size:.88rem; color:#4a524e; } .se-hist div { padding:8px 0; border-bottom:1px dashed var(--line); } .se-hist div:last-child { border:0; }
.se-away { display:inline-block; background:#fff4d6; color:#8a6100; font-size:.72rem; font-weight:800; padding:2px 9px; border-radius:99px; margin-left:6px; }
.se-back { display:inline-flex; align-items:center; gap:6px; min-height:44px; color:var(--gd); font-weight:700; margin-bottom:8px; }
@media (max-width: 991.98px) {
  .se-stats { grid-template-columns:1fr 1fr; gap:12px; } .se-stat { padding:16px 18px; border-radius:18px; } .se-stat b { font-size:1.8rem; }
  .se-box { padding:18px; border-radius:20px; } .se-notes { grid-template-columns:1fr; gap:14px; }
  .se-tools { width:100%; } .se-tools input[type=search] { flex:1 1 100%; min-width:0; } .se-tools select { flex:1 1 auto; min-height:48px; } .se-add { flex:1 1 100%; justify-content:center; min-height:52px; }
  .se-table, .se-table tbody, .se-table tr, .se-table td { display:block; } .se-table thead { display:none; }
  .se-table tr { background:#fff; border:1px solid var(--line); border-radius:18px; padding:14px; margin-bottom:12px; }
  .se-table td { border:0; padding:7px 0; display:flex; justify-content:space-between; align-items:center; gap:12px; }
  .se-table td::before { content:attr(data-label); font-size:.72rem; font-weight:800; color:var(--mut); text-transform:uppercase; letter-spacing:.05em; flex:0 0 30%; }
  .se-table td.se-first { display:block; } .se-table td.se-first::before { display:none; }
  .se-sheet { padding:0; align-items:stretch; } .se-form { max-width:none; max-height:none; height:100%; border-radius:0; padding:calc(16px + env(safe-area-inset-top)) 18px calc(24px + env(safe-area-inset-bottom)); }
  .se-grid { grid-template-columns:1fr; } .se-form input, .se-form select, .se-form textarea { min-height:54px; font-size:1.05rem; } .se-form textarea { min-height:100px; }
  .se-form-f { flex-direction:column-reverse; } .se-save, .se-cancel { width:100%; min-height:56px; }
  .se-dl { grid-template-columns:1fr 1fr; } .se-actions { grid-template-columns:1fr; } .se-card { padding:18px; border-radius:20px; }
}
</style>
