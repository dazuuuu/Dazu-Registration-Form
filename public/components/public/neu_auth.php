<?php
// Partial — included by other pages, never opened directly.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit('Not found'); }
// public/components/public/neu_auth.php — the soft "neumorphism" look shared by Sign in, Forgot password and Reset (OTP) pages.
// Gives .neu-* styles and makes every .neu-eye button show / hide the password field next to it.
?>
<style>
.neu-page{--bg:#e7efe9;--hi:#ffffff;--sh:#bccbc0;--g:#15A24A;--gd:#0f8a3d;min-height:calc(100vh - 120px);display:flex;align-items:center;justify-content:center;background:var(--bg);padding:40px 16px}
.neu-card{width:100%;max-width:360px;background:var(--bg);border-radius:32px;padding:34px 28px 28px;box-shadow:14px 14px 30px var(--sh),-14px -14px 30px var(--hi);text-align:center}
.neu-logo{width:88px;height:88px;border-radius:50%;margin:0 auto 14px;background:var(--bg);display:flex;align-items:center;justify-content:center;box-shadow:6px 6px 14px var(--sh),-6px -6px 14px var(--hi)}
.neu-logo img{width:58px;height:58px;object-fit:contain;mix-blend-mode:multiply}
.neu-title{font-size:21px;font-weight:800;color:#0f172a;margin:0;text-transform:none;letter-spacing:-.01em}
.neu-sub{font-size:12px;letter-spacing:.14em;color:#475569;margin:4px 0 26px}
.neu-msg{font-size:12px;font-weight:600;border-radius:14px;padding:10px 12px;margin-bottom:16px;text-align:left}
.neu-err{background:#fde8e8;color:#b91c1c}.neu-ok{background:#dcf5e3;color:#15803d}.neu-info{background:#eef2ef;color:#334155}
.neu-msg a{color:var(--g);text-decoration:underline}
.neu-field{position:relative;display:flex;align-items:center;margin-bottom:18px;border-radius:16px;background:var(--bg);box-shadow:inset 5px 5px 10px var(--sh),inset -5px -5px 10px var(--hi)}
.neu-field>svg{width:17px;height:17px;flex:none;margin-left:16px;color:#475569}
.neu-field input{flex:1;min-width:0;border:0;background:transparent;padding:14px 12px;font-size:14px;color:#0f172a;outline:none}
.neu-field input::placeholder{color:#94a3b8}
.neu-field:focus-within{box-shadow:inset 5px 5px 10px var(--sh),inset -5px -5px 10px var(--hi),0 0 0 2px rgba(21,162,74,.35)}
.neu-otp input{text-align:center;letter-spacing:.5em;font-weight:800;font-family:ui-monospace,monospace;font-size:18px}
.neu-eye{border:0;background:none;color:#64748b;padding:8px 14px 8px 4px;cursor:pointer;display:flex}
.neu-eye:hover{color:var(--g)}.neu-eye svg{width:18px;height:18px}
.neu-btn{width:100%;border:0;border-radius:16px;padding:14px;font-size:15px;font-weight:800;color:#fff;cursor:pointer;
  background:linear-gradient(135deg,#22c55e,var(--g) 60%,var(--gd));box-shadow:6px 6px 14px var(--sh),-6px -6px 14px var(--hi);transition:transform .12s,box-shadow .12s}
.neu-btn:hover{transform:translateY(-1px)}.neu-btn:active{transform:none;box-shadow:inset 3px 3px 8px rgba(0,0,0,.18)}
.neu-btn2{width:100%;border:0;border-radius:16px;padding:12px;font-size:13px;font-weight:700;color:#334155;cursor:pointer;background:var(--bg);box-shadow:5px 5px 12px var(--sh),-5px -5px 12px var(--hi);margin-top:14px}
.neu-btn2:hover{color:var(--g)}
.neu-links{margin-top:18px;font-size:12px;color:#64748b;font-weight:600;line-height:1.9}
.neu-links a{color:#64748b;text-decoration:none}.neu-links a:hover{color:var(--g)}.neu-links a.pri{color:var(--g)}
.neu-note{font-size:12px;color:#64748b;margin:-14px 0 22px;line-height:1.6}
</style>
<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest('.neu-eye'); if (!b) return;
  var i = b.parentNode.querySelector('input'), show = i.type === 'password';
  i.type = show ? 'text' : 'password';
  b.setAttribute('aria-pressed', show); b.setAttribute('aria-label', show ? 'Hide password' : 'Show password'); b.title = b.getAttribute('aria-label');
  b.querySelector('[data-slash]').style.display = show ? '' : 'none'; i.focus();
});
</script>
