<?php
// Public certificate verification.
//  - Scanning the QR code on a certificate opens verify/?c=<qr_code_hash>.
//  - Uploading a photo / scan / PDF of a certificate decodes its QR code in the browser (jsQR + pdf.js) and opens the same link.
//  - The certificate number printed on the certificate can also be typed in.
// Shows academic details only (never fees or contact details).
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/CertificateService.php';

$page_title = 'Verify a Certificate — Dazu Training Institute';
$page_description = 'Check that a Dazu Training Institute certificate is genuine by scanning its QR code, uploading it, or entering its certificate number.';

$db = \Models\Connection::getInstance()->getConnection();

$hash = trim((string) ($_GET['c'] ?? ''));
$certNo = strtoupper(trim((string) ($_GET['no'] ?? '')));
$searched = $hash !== '' || $certNo !== '';
$cert = null;

if ($hash !== '' && preg_match('/^[a-f0-9]{16,100}$/i', $hash)) {
    $where = 'c.qr_code_hash = ?'; $arg = strtolower($hash);
} elseif ($certNo !== '' && preg_match('/^[A-Z0-9\-]{4,50}$/', $certNo)) {
    $where = 'c.certificate_no = ?'; $arg = $certNo;
}
if (isset($where)) {
    $st = $db->prepare(
        "SELECT c.certificate_no, c.qr_code_hash, c.status, c.snapshot_name, c.snapshot_course, c.snapshot_duration_months,
                c.snapshot_dates, c.taught_skills, c.tutor_name, c.ceo_name, c.issued_at,
                r.registration_no, r.study_mode,
                CASE WHEN up.profile_photo_status <> 'rejected' THEN up.profile_photo_path END AS photo
         FROM certificates c
         JOIN registrations r ON r.id = c.registration_id
         LEFT JOIN user_profiles up ON up.user_id = r.user_id
         WHERE $where LIMIT 1"
    );
    $st->execute([$arg]);
    $cert = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Lookup by student registration number: photo, name, courses, certificates, student ID and letter of recommendation.
$regNo = strtoupper(trim((string) ($_GET['reg'] ?? '')));
$student = null;
if ($regNo !== '' && preg_match('#^[A-Z0-9/\-]{3,40}$#', $regNo)) {
    $st = $db->prepare("SELECT id, user_id, full_name, registration_no FROM registrations WHERE UPPER(registration_no) = ? AND status = 'approved' LIMIT 1");
    $st->execute([$regNo]);
    if ($sr = $st->fetch(PDO::FETCH_ASSOC)) {
        require_once __DIR__ . '/../../app/v1/services/RecommendationService.php';
        // Every approved enrolment of the same student (or just this one when there is no portal account).
        $st = $db->prepare(
            "SELECT r.id, r.registration_no, r.study_mode, r.created_at, c.title,
                    cert.certificate_no, cert.qr_code_hash, cert.issued_at,
                    k.status AS id_status, k.valid_until AS id_valid, k.qr_hash AS id_hash
             FROM registrations r JOIN courses c ON c.id = r.course_id
             LEFT JOIN certificates cert ON cert.registration_id = r.id AND cert.status <> 'withdrawn'
             LEFT JOIN student_id_cards k ON k.registration_id = r.id AND k.status IN ('generated','ready','collected')
             WHERE r.status = 'approved' AND " . ($sr['user_id'] ? 'r.user_id = ?' : 'r.id = ?') . " ORDER BY r.created_at"
        );
        $st->execute([$sr['user_id'] ?: $sr['id']]);
        $enrols = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($enrols as &$en) {
            $en['letter'] = lor_get_by_reg($db, (int) $en['id']);
            $en['sletter'] = null;   // the letter of recommendation (migration 095): final letters only, never a draft
            try {
                $sq = $db->prepare('SELECT generated_at FROM student_letters WHERE registration_id = ?');
                $sq->execute([(int) $en['id']]);
                $en['sletter'] = $sq->fetchColumn() ?: null;
            } catch (Throwable $ex) {
            }
        }
        unset($en);
        $student = $sr + ['enrols' => $enrols, 'photo' => cert_student_photo($db, $sr['user_id'] ? (int) $sr['user_id'] : null)];
    }
}

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
// Status is the admin's own tracking (being prepared, issued, picked up …) and does not change what is shown here;
// only a certificate the institute has withdrawn (revoked) is flagged.
$valid = $cert && $cert['status'] !== 'withdrawn';

$extra_head = <<<'CSS'
<style>
.vf{--g:#5c9e3a;--gd:#3f7a26;--d:#38373a;background:#f6f8f5;min-height:70vh;padding:56px 16px;color:#1f2937}
.vf *{box-sizing:border-box}
.vf-wrap{max-width:880px;margin:0 auto}
.vf-chip{display:inline-block;font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--gd);background:#eaf4e4;border:1px solid #cfe5c2;border-radius:999px;padding:4px 12px}
.vf h1{font-size:clamp(28px,5vw,42px);font-weight:800;letter-spacing:-.02em;margin:14px 0 8px;color:#0f172a}
.vf-lead{color:#475569;margin:0 0 28px;max-width:620px;line-height:1.6}
.vf-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:24px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.vf-grid{display:grid;grid-template-columns:1fr;gap:16px}
@media(min-width:760px){.vf-grid{grid-template-columns:1.2fr 1fr}}
.vf h1,.vf h2,.vf p,.vf dl,.vf dd{margin:0;padding:0}
.vf h1,.vf h2{text-transform:none}
.vf .vf-wrap h1{margin:14px 0 10px}
.vf .vf-wrap p.vf-lead{margin:0 0 28px}
.vf .vf-msg:empty{display:none}
.vf .vf-foot{margin-top:18px}
.vf svg{display:block;flex:none}
.vf-note{font-size:13px;color:#64748b;line-height:1.5}
.vf-drop{display:block;border:2px dashed #cbd5e1;border-radius:14px;padding:30px 16px;text-align:center;cursor:pointer;transition:.15s;background:#fafcf9}
.vf-drop:hover,.vf-drop.on,.vf-drop:focus{border-color:var(--g);background:#f1f8ec;outline:none}
.vf-drop-ico{display:flex;justify-content:center;color:var(--g);margin-bottom:10px}
.vf-drop-t{display:block;font-size:16px;font-weight:700;color:#0f172a;margin-bottom:4px}
.vf-drop-s{display:block;font-size:13px;color:#64748b}
.vf-cam{margin-bottom:16px}
.vf-cam-head{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.vf-cam-ico{width:52px;height:52px;border-radius:14px;background:#eaf4e4;color:var(--gd);display:flex;align-items:center;justify-content:center;flex:none}
.vf-cam-txt{flex:1;min-width:200px}
.vf-cam-view{position:relative;margin-top:18px;border-radius:14px;overflow:hidden;background:#0f172a;aspect-ratio:4/3;max-height:460px;width:100%}
.vf-cam-view[hidden]{display:none}
.vf-cam-view video{width:100%;height:100%;object-fit:cover;display:block}
.vf-cam-frame{position:absolute;left:50%;top:46%;width:min(58%,260px);aspect-ratio:1;transform:translate(-50%,-50%);pointer-events:none;box-shadow:0 0 0 9999px rgba(15,23,42,.45);border-radius:12px}
.vf-cam-frame i{position:absolute;width:28px;height:28px;border:4px solid #fff;border-radius:4px}
.vf-cam-frame i:nth-child(1){top:-2px;left:-2px;border-right:0;border-bottom:0}
.vf-cam-frame i:nth-child(2){top:-2px;right:-2px;border-left:0;border-bottom:0}
.vf-cam-frame i:nth-child(3){bottom:-2px;left:-2px;border-right:0;border-top:0}
.vf-cam-frame i:nth-child(4){bottom:-2px;right:-2px;border-left:0;border-top:0}
.vf-cam-frame b{position:absolute;left:8%;right:8%;height:2px;background:#86efac;box-shadow:0 0 12px #4ade80;animation:vfscan 2s ease-in-out infinite}
@keyframes vfscan{0%,100%{top:10%}50%{top:88%}}
.vf-cam-bar{position:absolute;left:0;right:0;bottom:0;padding:12px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;background:linear-gradient(transparent,rgba(15,23,42,.7))}
.vf-cam-sel{border:0;border-radius:10px;padding:10px 12px;font-size:14px;max-width:220px}
.vf-btn-ghost{background:rgba(255,255,255,.18);backdrop-filter:blur(6px)}
.vf-btn-ghost:hover{background:rgba(255,255,255,.3)}
.vf-h{font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#334155;margin:0 0 12px}
.vf-row{display:flex;gap:8px}
.vf-in{flex:1;min-width:0;border:1px solid #cbd5e1;border-radius:10px;padding:11px 14px;font-size:15px}
.vf-in:focus{outline:none;border-color:var(--g);box-shadow:0 0 0 3px #e2f0d9}
.vf-btn{display:inline-flex;align-items:center;justify-content:center;font-size:15px;line-height:1.2;text-transform:none;letter-spacing:0;background:var(--g);color:#fff;border:0;border-radius:10px;padding:11px 18px;font-weight:700;cursor:pointer;white-space:nowrap}
.vf-btn:hover{background:var(--gd)}
.vf-msg{margin-top:12px;font-size:14px;min-height:20px}
.vf-res{margin-top:24px;border-radius:18px;overflow:hidden;border:1px solid #e2e8f0;background:#fff}
.vf-band{display:flex;align-items:center;gap:14px;padding:18px 24px;color:#fff}
.vf-band.ok{background:var(--g)} .vf-band.bad{background:#b91c1c} .vf-band.warn{background:#b45309}
.vf-band svg{flex:none}
.vf-band b{font-size:18px;display:block}
.vf-band small{opacity:.9}
.vf-body{padding:24px;display:flex;gap:24px;flex-wrap:wrap}
.vf-photo{width:110px;height:130px;border-radius:12px;object-fit:cover;border:1px solid #e2e8f0}
.vf-name{font-family:Georgia,'Times New Roman',serif;font-style:italic;font-weight:700;font-size:30px;color:var(--gd);margin:0 0 4px}
.vf-dl{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px 24px;margin:16px 0 0;flex:1;min-width:240px}
.vf-dl div{min-width:0}
.vf-dl dt{font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#64748b}
.vf-dl dd{margin:3px 0 0;font-weight:600;color:#0f172a;overflow-wrap:anywhere}
.vf-text{padding:0 24px 24px;color:#334155;line-height:1.6}
.vf-foot{font-size:12px;color:#64748b;margin-top:18px;text-align:center}
</style>
CSS;

ob_start();
?>
<div class="vf"><div class="vf-wrap">
  <span class="vf-chip">Certificate Verification</span>
  <h1>Verify a Dazu certificate</h1>
  <p class="vf-lead">Scan the QR code on the certificate with your phone camera, upload a photo or PDF of the certificate, or type the certificate number printed under the QR code.</p>

  <?php if ($regNo !== ''): ?>
    <div class="vf-res" id="result">
      <?php if (!$student): ?>
        <div class="vf-band bad">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
          <div><b>No student with registration number <?php echo $e($regNo); ?></b><small>Check the number on the letter or certificate and try again.</small></div>
        </div>
      <?php else: ?>
        <div class="vf-band ok">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="10"/><path d="M7 12.5l3 3 7-7"/></svg>
          <div><b>Registered Dazu student</b><small>Registration No. <?php echo $e($student['registration_no']); ?></small></div>
        </div>
        <div class="vf-body">
          <?php if ($student['photo']): ?><img class="vf-photo" src="<?php echo $e(settings_asset_url($student['photo'])); ?>" alt="Photo of <?php echo $e($student['full_name']); ?>"><?php endif; ?>
          <div style="flex:1;min-width:240px">
            <p class="vf-name"><?php echo $e($student['full_name']); ?></p>
            <?php foreach ($student['enrols'] as $en): ?>
              <dl class="vf-dl" style="border-top:1px solid #eef2f7;padding-top:14px">
                <div><dt>Course</dt><dd><?php echo $e($en['title']); ?><?php echo $en['study_mode'] ? ' · ' . $e(ucfirst($en['study_mode'])) : ''; ?></dd></div>
                <div><dt>Registration No</dt><dd><?php echo $e($en['registration_no']); ?></dd></div>
                <div><dt>Certificate</dt><dd><?php if ($en['certificate_no']): ?><a href="?c=<?php echo $e($en['qr_code_hash']); ?>#result"><?php echo $e($en['certificate_no']); ?></a><?php echo $en['issued_at'] ? ' · ' . $e(date('j M Y', strtotime($en['issued_at']))) : ''; ?><?php else: ?>Not yet awarded<?php endif; ?></dd></div>
                <div><dt>Student ID</dt><dd><?php if ($en['id_status']): ?><a href="<?php echo $e(url('verify/id.php?k=' . $en['id_hash'])); ?>">Issued</a> · valid until <?php echo (int) $en['id_valid']; ?><?php else: ?>Not issued<?php endif; ?></dd></div>
                <div><dt>Letter of recommendation</dt><dd><?php if ($en['sletter']): ?>Issued <?php echo $e(date('j M Y', strtotime($en['sletter']))); ?><?php elseif ($en['letter']): ?><a href="<?php echo $e(url('letters/recommendation.php?k=' . $en['letter']['verify_hash'])); ?>" target="_blank">View letter</a> · <?php echo $e(date('j M Y', strtotime($en['letter']['created_at']))); ?><?php else: ?>Not given<?php endif; ?></dd></div>
              </dl>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <p class="vf-foot">Check that the name and photo above match the person and their documents.</p>
    <h2 class="vf-h" style="margin-top:36px">Verify something else</h2>
  <?php elseif ($searched): ?>
    <div class="vf-res" id="result">
      <?php if (!$cert): ?>
        <div class="vf-band bad">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
          <div><b>No matching certificate</b><small>This code was not issued by Dazu Training Institute. The certificate may be altered or fake.</small></div>
        </div>
      <?php elseif (!$valid): ?>
        <div class="vf-band bad">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
          <div><b>Certificate withdrawn</b>
            <small>Certificate <?php echo $e($cert['certificate_no']); ?> has been withdrawn by Dazu Training Institute and is no longer valid.</small></div>
        </div>
      <?php else: ?>
        <div class="vf-band ok">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="10"/><path d="M7 12.5l3 3 7-7"/></svg>
          <div><b>Valid certificate</b><small>Issued by Dazu Training Institute<?php echo $cert['issued_at'] ? ' on ' . $e(date('j F Y', strtotime($cert['issued_at']))) : ''; ?></small></div>
        </div>
        <div class="vf-body">
          <?php if ($cert['photo']): ?><img class="vf-photo" src="<?php echo $e(settings_asset_url($cert['photo'])); ?>" alt="Photo of <?php echo $e($cert['snapshot_name']); ?>"><?php endif; ?>
          <div style="flex:1;min-width:240px">
            <p class="vf-name"><?php echo $e($cert['snapshot_name']); ?></p>
            <div style="color:#475569">has successfully completed the <strong><?php echo $e($cert['snapshot_course']); ?></strong></div>
            <dl class="vf-dl">
              <div><dt>Certificate No</dt><dd><?php echo $e($cert['certificate_no']); ?></dd></div>
              <?php if ($cert['registration_no']): ?><div><dt>Registration No</dt><dd><?php echo $e($cert['registration_no']); ?></dd></div><?php endif; ?>
              <div><dt>Programme</dt><dd><?php echo $e(cert_duration_label((int) $cert['snapshot_duration_months'])); ?> programme<?php echo $cert['study_mode'] ? ' · ' . $e(ucfirst($cert['study_mode'])) : ''; ?></dd></div>
              <?php if ($cert['snapshot_dates']): ?><div><dt>Period of study</dt><dd><?php echo $e($cert['snapshot_dates']); ?></dd></div><?php endif; ?>
              <?php if ($cert['tutor_name']): ?><div><dt>Course tutor</dt><dd><?php echo $e($cert['tutor_name']); ?></dd></div><?php endif; ?>
              <div><dt>Director</dt><dd><?php echo $e($cert['ceo_name'] ?: CERT_DIRECTOR_NAME); ?></dd></div>
            </dl>
          </div>
        </div>
        <?php if (trim((string) $cert['taught_skills']) !== ''): ?>
          <div class="vf-text"><div class="vf-h" style="margin-bottom:6px">What was completed</div><?php echo nl2br($e(trim($cert['taught_skills']))); ?></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <p class="vf-foot">Check that the name and course above match the paper certificate exactly.</p>
    <h2 class="vf-h" style="margin-top:36px">Verify another certificate</h2>
  <?php endif; ?>

  <div class="vf-card vf-cam">
    <div class="vf-cam-head">
      <div class="vf-cam-ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2"/><rect x="7" y="7" width="4" height="4"/><rect x="13" y="7" width="4" height="4"/><rect x="7" y="13" width="4" height="4"/><path d="M13 13h4v4h-4z"/></svg></div>
      <div class="vf-cam-txt">
        <h2 class="vf-h" style="margin:0 0 4px">Scan with your camera</h2>
        <p class="vf-note">Point your camera at the QR code on the certificate. It verifies automatically.</p>
      </div>
      <button type="button" class="vf-btn" id="camStart">Open camera</button>
    </div>
    <div class="vf-cam-view" id="camView" hidden>
      <video id="camVideo" playsinline muted></video>
      <div class="vf-cam-frame"><i></i><i></i><i></i><i></i><b></b></div>
      <div class="vf-cam-bar">
        <select id="camSelect" class="vf-cam-sel" aria-label="Camera" hidden></select>
        <button type="button" class="vf-btn vf-btn-ghost" id="camStop">Close camera</button>
      </div>
    </div>
    <div class="vf-msg" id="camMsg"></div>
  </div>

  <div class="vf-grid">
    <div class="vf-card">
      <h2 class="vf-h">Upload the certificate</h2>
      <div class="vf-drop" id="drop" role="button" tabindex="0" aria-label="Choose a photo or PDF of the certificate">
        <input type="file" id="file" accept="image/*,application/pdf" style="display:none">
        <div class="vf-drop-ico"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4m0 0l-4 4m4-4l4 4"/><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg></div>
        <div class="vf-drop-t">Drop a photo or PDF here</div>
        <div class="vf-drop-s">or tap to choose a file (JPG, PNG or PDF)</div>
      </div>
      <div class="vf-msg" id="msg"></div>
    </div>
    <div class="vf-card">
      <h2 class="vf-h">Enter certificate number</h2>
      <form method="GET" action="" class="vf-row">
        <input class="vf-in" name="no" placeholder="e.g. DDS-2026-0042" value="<?php echo $e($certNo); ?>" required>
        <button class="vf-btn">Verify</button>
      </form>
      <p class="vf-note" style="margin-top:12px">The number is printed under the QR code and at the bottom of the certificate.</p>
      <h2 class="vf-h" style="margin-top:22px">Or a student registration number</h2>
      <form method="GET" action="" class="vf-row">
        <input class="vf-in" name="reg" placeholder="e.g. DCH061/26" value="<?php echo $e($regNo); ?>" required>
        <button class="vf-btn">Verify</button>
      </form>
      <p class="vf-note" style="margin-top:12px">Shown on letters of recommendation. Lists the student's photo, courses, certificates, student ID and letter of recommendation.</p>
    </div>
  </div>
</div></div>
<?php
$content = ob_get_clean();
$verifyBase = json_encode(url('verify/'));
$extra_js = <<<JS
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
  const base = {$verifyBase};
  const file = document.getElementById('file'), drop = document.getElementById('drop'), msg = document.getElementById('msg');
  const say = (t, c) => { msg.style.color = c || '#475569'; msg.textContent = t; };
  if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

  // Try the whole image at a few sizes, then the left part (where the QR sits on Dazu certificates).
  function scan(source, w, h) {
    const tries = [[0, 0, w, h, 1600], [0, 0, w, h, 1000], [0, 0, w, h, 2400], [0, h * 0.35, w * 0.35, h * 0.5, 900], [0, 0, w * 0.5, h, 1200]];
    const c = document.createElement('canvas'), x = c.getContext('2d', {willReadFrequently: true});
    for (const [sx, sy, sw, sh, max] of tries) {
      const k = Math.min(1, max / Math.max(sw, sh));
      c.width = Math.round(sw * k); c.height = Math.round(sh * k);
      x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
      x.drawImage(source, sx, sy, sw, sh, 0, 0, c.width, c.height);
      const r = jsQR(x.getImageData(0, 0, c.width, c.height).data, c.width, c.height, {inversionAttempts: 'attemptBoth'});
      if (r && r.data) return r.data;
    }
    return null;
  }

  async function fromPdf(f) {
    const pdf = await pdfjsLib.getDocument({data: await f.arrayBuffer()}).promise;
    const page = await pdf.getPage(1);
    const vp = page.getViewport({scale: 3});
    const c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
    await page.render({canvasContext: c.getContext('2d'), viewport: vp}).promise;
    return scan(c, c.width, c.height);
  }

  function fromImage(f) {
    return new Promise((ok, bad) => {
      const img = new Image();
      img.onload = () => { ok(scan(img, img.naturalWidth, img.naturalHeight)); URL.revokeObjectURL(img.src); };
      img.onerror = () => bad(new Error('Could not open that image.'));
      img.src = URL.createObjectURL(f);
    });
  }

  function go(text, out) {
    out = out || say;
    let code = null;
    try { code = new URL(text, location.href).searchParams.get('c'); } catch (e) {}
    if (!code && /^[a-f0-9]{16,100}$/i.test(text.trim())) code = text.trim();
    if (!code) { out('That QR code is not a Dazu certificate code.', '#b91c1c'); return false; }
    out('QR code found. Checking…', '#3f7a26');
    location.href = base + '?c=' + encodeURIComponent(code) + '#result';
    return true;
  }

  async function handle(f) {
    if (!f) return;
    if (!window.jsQR) { say('The QR reader could not load. Check your internet connection, or type the certificate number instead.', '#b91c1c'); return; }
    say('Reading ' + f.name + '…');
    try {
      const isPdf = f.type === 'application/pdf' || /\.pdf$/i.test(f.name);
      if (isPdf && !window.pdfjsLib) throw new Error('The PDF reader could not load. Upload a photo or screenshot instead.');
      const text = isPdf ? await fromPdf(f) : await fromImage(f);
      if (text) go(text);
      else say('No QR code could be read. Try a sharper, well-lit photo with the QR code in view, or type the certificate number.', '#b91c1c');
    } catch (e) { say(e.message || 'Could not read that file.', '#b91c1c'); }
  }

  // ── Upload box: it's a div (the theme restyles <label>), so open the file picker ourselves
  drop.addEventListener('click', () => file.click());
  drop.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); file.click(); } });

  // ── Live camera scanning
  const camStart = document.getElementById('camStart'), camStop = document.getElementById('camStop');
  const camView = document.getElementById('camView'), video = document.getElementById('camVideo');
  const camSel = document.getElementById('camSelect'), camMsg = document.getElementById('camMsg');
  const camSay = (t, c) => { camMsg.style.color = c || '#475569'; camMsg.textContent = t; };
  let stream = null, timer = null, detector = null, busy = false;
  if ('BarcodeDetector' in window) {
    try { detector = new BarcodeDetector({formats: ['qr_code']}); } catch (e) { detector = null; }
  }
  const grab = document.createElement('canvas'), gctx = grab.getContext('2d', {willReadFrequently: true});

  function stopCam() {
    clearInterval(timer); timer = null;
    if (stream) stream.getTracks().forEach(t => t.stop());
    stream = null; video.srcObject = null;
    camView.hidden = true; camStart.hidden = false;
  }

  async function startCam(deviceId) {
    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      camSay('The camera only works when the site is opened over https://. Upload a photo or type the certificate number instead.', '#b91c1c');
      return;
    }
    if (!detector && !window.jsQR) { camSay('The QR reader could not load. Check your internet connection.', '#b91c1c'); return; }
    if (stream) stream.getTracks().forEach(t => t.stop());
    camSay('Starting camera…');
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: deviceId ? {deviceId: {exact: deviceId}} : {facingMode: {ideal: 'environment'}, width: {ideal: 1280}, height: {ideal: 720}}
      });
    } catch (e) {
      stream = null;
      camSay(e.name === 'NotAllowedError' ? 'Camera permission was blocked. Allow camera access in your browser settings and try again.'
           : e.name === 'NotFoundError' ? 'No camera was found on this device.'
           : 'Could not open the camera (' + (e.message || e.name) + ').', '#b91c1c');
      return;
    }
    video.srcObject = stream;
    camView.hidden = false; camStart.hidden = true;
    await video.play().catch(() => {});
    camSay('Hold the QR code inside the frame…', '#3f7a26');
    camView.scrollIntoView({behavior: 'smooth', block: 'center'});

    // List cameras (labels are only available after permission is granted)
    try {
      const cams = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput');
      const current = stream.getVideoTracks()[0].getSettings().deviceId;
      camSel.innerHTML = cams.map((d, i) => '<option value="' + d.deviceId + '"' + (d.deviceId === current ? ' selected' : '') + '>' + (d.label || 'Camera ' + (i + 1)) + '</option>').join('');
      camSel.hidden = cams.length < 2;
    } catch (e) {}

    clearInterval(timer);
    timer = setInterval(scanFrame, 200);
  }

  async function scanFrame() {
    if (busy || !stream || video.readyState < 2) return;
    busy = true;
    try {
      let text = null;
      if (detector) {
        const codes = await detector.detect(video);
        if (codes.length) text = codes[0].rawValue;
      } else {
        const w = video.videoWidth, h = video.videoHeight, k = Math.min(1, 800 / Math.max(w, h));
        grab.width = Math.round(w * k); grab.height = Math.round(h * k);
        gctx.drawImage(video, 0, 0, grab.width, grab.height);
        const r = jsQR(gctx.getImageData(0, 0, grab.width, grab.height).data, grab.width, grab.height, {inversionAttempts: 'dontInvert'});
        if (r && r.data) text = r.data;
      }
      if (text) {
        if (navigator.vibrate) navigator.vibrate(80);
        stopCam();
        camSay('QR code found. Checking…', '#3f7a26');
        if (!go(text, camSay)) setTimeout(() => startCam(), 1800);
      }
    } catch (e) {
      // BarcodeDetector can fail on some devices; fall back to jsQR
      if (detector && window.jsQR) detector = null;
    }
    busy = false;
  }

  camStart.addEventListener('click', () => startCam());
  camStop.addEventListener('click', () => { stopCam(); camSay(''); });
  camSel.addEventListener('change', () => startCam(camSel.value));
  window.addEventListener('pagehide', stopCam);
  document.addEventListener('visibilitychange', () => { if (document.hidden && stream) { stopCam(); camSay('Camera closed. Tap “Open camera” to scan again.'); } });

  file.addEventListener('change', () => handle(file.files[0]));
  ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('on'); }));
  ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.remove('on'); }));
  drop.addEventListener('drop', e => handle(e.dataTransfer.files[0]));
})();
</script>
JS;
require __DIR__ . '/../templates/public/dazu_layout.php';
