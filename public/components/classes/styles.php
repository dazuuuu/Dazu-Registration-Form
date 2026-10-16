<?php // Class Calendar look (tutor + student + admin): Dazu green, charcoal, white rounded cards. ?>
<?php if (empty($GLOBALS['__cal_css'])): $GLOBALS['__cal_css'] = true; ?>
<style>
.cl-card{background:#fff;border-radius:18px;box-shadow:0 1px 2px rgba(29,27,28,.04),0 6px 20px rgba(29,27,28,.05);padding:18px 20px;margin-bottom:16px}
.cl-card h3{font-size:1rem;font-weight:600;margin:0}
.cl-head{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;margin-bottom:12px}
.cl-tabs{display:inline-flex;background:#f1f2ef;border-radius:999px;padding:3px}
.cl-tabs a{padding:5px 14px;border-radius:999px;color:#373435;text-decoration:none;font-size:.84rem;font-weight:500}
.cl-tabs a.on{background:#44A024;color:#fff}
.cl-nav{display:flex;align-items:center;gap:6px}
.cl-nav a{width:32px;height:32px;border-radius:50%;background:#f1f2ef;color:#373435;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}
.cl-month{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px}
.cl-month .h{font-size:.72rem;color:#6b7067;text-align:center;font-weight:600;padding-bottom:2px}
.cl-day{min-height:104px;border:1px solid #eceee9;border-radius:12px;padding:5px;display:flex;flex-direction:column;gap:3px;background:#fff;text-decoration:none;color:inherit;position:relative}
.cl-day.out{background:#fafbf8;color:#a3a89f}
.cl-day.today{border-color:#44A024;box-shadow:inset 0 0 0 1px #44A024}
.cl-day .n{font-size:.75rem;font-weight:600}
.cl-day.today .n{background:#44A024;color:#fff;border-radius:999px;padding:0 7px;align-self:flex-start}
.cl-day .add{position:absolute;top:5px;right:6px;font-size:.7rem;color:#a3a89f;text-decoration:none;display:none}
.cl-day:hover .add{display:block}
.cl-ev{display:block;font-size:.72rem;line-height:1.25;padding:3px 6px;border-radius:7px;background:#f3f8ef;color:#373435;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cl-ev:hover{background:#e3f2da}
.cl-ev .dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:4px;vertical-align:1px}
.cl-ev.cancelled{background:#fde8e6;color:#9f2a20;text-decoration:line-through}
.cl-ev.moved{background:#fff1dd;color:#92400e}
.dot.physical{background:#44A024}.dot.online{background:#2b78d0}.dot.hybrid{background:#8b5cf6}
.cl-legend span{font-size:.75rem;color:#6b7067;margin-left:10px;white-space:nowrap}
.cl-pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.7rem;font-weight:600;white-space:nowrap}
.cl-pill.physical{background:#e7f4e1;color:#2f7a14}.cl-pill.online{background:#e6efff;color:#2b5fb5}.cl-pill.hybrid{background:#efe9ff;color:#6d28d9}
.cl-pill.cancelled{background:#fde8e6;color:#c0362c}.cl-pill.moved{background:#fff1dd;color:#b45309}.cl-pill.present{background:#e7f4e1;color:#2f7a14}
.cl-pill.late{background:#fff1dd;color:#b45309}.cl-pill.absent{background:#fde8e6;color:#c0362c}.cl-pill.excused{background:#eef0ee;color:#4a4e47}
.cl-row{display:flex;gap:12px;align-items:center;padding:11px 0;border-bottom:1px solid #eceee9;text-decoration:none;color:inherit}
.cl-row:last-child{border-bottom:0}
.cl-date{width:48px;flex:none;text-align:center;border-radius:12px;background:#f3f8ef;padding:5px 0;line-height:1.1}
.cl-date b{display:block;font-size:1.1rem;color:#373435}.cl-date small{font-size:.65rem;color:#44A024;font-weight:700;text-transform:uppercase}
.cl-muted{color:#6b7067;font-size:.8rem}
.cl-week{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}
.cl-week .col{background:#fafbf8;border-radius:12px;padding:8px;min-height:220px}
.cl-week .col.today{background:#eef7e8}
.cl-moved{background:#fff8ec;border-radius:12px;padding:10px 12px;font-size:.85rem;color:#92400e}
.cl-cancel{background:#fde8e6;border-radius:12px;padding:10px 12px;font-size:.85rem;color:#9f2a20}
@media(max-width:767.98px){.cl-day{min-height:62px}.cl-ev{font-size:0;padding:0;width:8px;height:8px;border-radius:50%;display:inline-block}
  .cl-ev .dot{margin:0;width:8px;height:8px}.cl-week{grid-template-columns:1fr}.cl-week .col{min-height:0}}
</style>
<?php endif; ?>
