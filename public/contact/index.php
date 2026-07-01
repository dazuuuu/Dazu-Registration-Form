<?php
// public/contact/index.php — Dazu Hub Institute
$page_title = 'Contact Us — Dazu Hub Institute';
$page_description = 'Get in touch with Dazu Hub Institute — call, email, WhatsApp, or send us a message about courses and enrollment.';

require_once __DIR__ . '/../../app/init.php';

$contact_phone      = '+254 700 000 000';
$contact_phone_e164 = '+254700000000';
$contact_email      = 'info@dazuhubinstitute.com';
$contact_whatsapp   = '254700000000';
$contact_address    = 'Nairobi, Kenya';
$contact_hours      = 'Mon – Fri, 9:00 AM – 5:00 PM';
$contact_socials    = [
    'instagram' => '#',
    'linkedin'  => '#',
    'twitter'   => '#',
    'facebook'  => '#',
];

$sent = false;
$form_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $form_error = 'Please fill in your name, email, and message.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_error = 'Please enter a valid email address.';
    } else {
        $sent = true;
    }
}

function cold(string $k): string {
    return htmlspecialchars($_POST[$k] ?? '', ENT_QUOTES, 'UTF-8');
}

$mapSrc = 'https://www.google.com/maps?q=' . urlencode($contact_address) . '&output=embed';

ob_start();
?>
<section class="dh-section" style="padding-top:56px;background:linear-gradient(135deg,#1B5E20,#2E7D32);color:#fff;">
    <div class="dh-container dh-reveal">
        <span class="dh-eyebrow" style="color:rgba(255,255,255,.8);">Contact</span>
        <h1 class="dh-h1" style="color:#fff;">Get in touch</h1>
        <p class="dh-lead" style="color:rgba(255,255,255,.9);max-width:560px;">Questions about courses, fees, or enrollment? We're here to help.</p>
    </div>
</section>

<section class="dh-section">
    <div class="dh-container">
        <div class="dh-contact-grid dh-reveal">
            <div>
                <h2 class="dh-h2">Reach us directly</h2>
                <p>Prefer a quick chat? Use any option below, or send a message and we'll reply within 24 hours.</p>
                <div class="dh-cinfo-list">
                    <a class="dh-cinfo" href="tel:<?php echo htmlspecialchars($contact_phone_e164); ?>">
                        <span class="dh-cinfo-icon"><i class="fas fa-phone"></i></span>
                        <span><small>Call us</small><strong><?php echo htmlspecialchars($contact_phone); ?></strong></span>
                    </a>
                    <a class="dh-cinfo" href="mailto:<?php echo htmlspecialchars($contact_email); ?>">
                        <span class="dh-cinfo-icon"><i class="fas fa-envelope"></i></span>
                        <span><small>Email</small><strong><?php echo htmlspecialchars($contact_email); ?></strong></span>
                    </a>
                    <a class="dh-cinfo" href="https://wa.me/<?php echo htmlspecialchars($contact_whatsapp); ?>" target="_blank" rel="noopener">
                        <span class="dh-cinfo-icon dh-cinfo-icon--wa"><i class="fab fa-whatsapp"></i></span>
                        <span><small>WhatsApp</small><strong>Chat with us</strong></span>
                    </a>
                    <div class="dh-cinfo">
                        <span class="dh-cinfo-icon"><i class="fas fa-location-dot"></i></span>
                        <span><small>Location</small><strong><?php echo htmlspecialchars($contact_address); ?></strong></span>
                    </div>
                    <div class="dh-cinfo">
                        <span class="dh-cinfo-icon"><i class="fas fa-clock"></i></span>
                        <span><small>Office hours</small><strong><?php echo htmlspecialchars($contact_hours); ?></strong></span>
                    </div>
                </div>
            </div>

            <div class="dh-card" style="padding:28px;">
                <h3 class="dh-h3">Send a message</h3>
                <?php if ($sent): ?>
                    <div class="dh-alert dh-alert--ok"><i class="fas fa-circle-check"></i> Thanks — we've received your message and will reply soon.</div>
                <?php elseif ($form_error): ?>
                    <div class="dh-alert dh-alert--err"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($form_error); ?></div>
                <?php endif; ?>
                <form method="POST" class="dh-form">
                    <div class="dh-form-row">
                        <div><label for="name">Your name *</label><input type="text" id="name" name="name" value="<?php echo cold('name'); ?>" required></div>
                        <div><label for="email">Email *</label><input type="email" id="email" name="email" value="<?php echo cold('email'); ?>" required></div>
                    </div>
                    <div><label for="subject">Subject</label><input type="text" id="subject" name="subject" value="<?php echo cold('subject'); ?>"></div>
                    <div><label for="message">Message *</label><textarea id="message" name="message" rows="5" required><?php echo cold('message'); ?></textarea></div>
                    <button type="submit" class="dh-btn dh-btn-primary">Send message <i class="fas fa-arrow-right"></i></button>
                </form>
            </div>
        </div>

        <div class="dh-map dh-reveal">
            <iframe src="<?php echo htmlspecialchars($mapSrc); ?>" loading="lazy" title="Dazu Hub Institute location"></iframe>
        </div>
    </div>
</section>
<style>
.dh-contact-grid{display:grid;grid-template-columns:1fr 1.1fr;gap:40px;align-items:start}
.dh-cinfo-list{display:flex;flex-direction:column;gap:12px;margin-top:24px}
.dh-cinfo{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--border);text-decoration:none;color:inherit}
.dh-cinfo:last-child{border-bottom:none}
.dh-cinfo small{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted)}
.dh-cinfo strong{color:var(--text)}
.dh-cinfo-icon{width:44px;height:44px;border-radius:10px;background:var(--dh-green-pale);color:var(--dh-green);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.dh-cinfo-icon--wa{background:rgba(37,211,102,.12);color:#1FAE54}
.dh-form{display:flex;flex-direction:column;gap:14px;margin-top:16px}
.dh-form label{display:block;font-size:.8rem;font-weight:600;margin-bottom:4px}
.dh-form input,.dh-form textarea{width:100%;padding:12px;border:1px solid var(--border);border-radius:8px;background:var(--bg-input);font-family:inherit}
.dh-form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.dh-alert{padding:12px 16px;border-radius:8px;font-size:.9rem;margin-bottom:12px}
.dh-alert--ok{background:rgba(46,125,50,.1);color:var(--dh-green-dark);border:1px solid rgba(46,125,50,.25)}
.dh-alert--err{background:#fff3f3;color:#c0392b;border:1px solid #f1c4c4}
.dh-map{margin-top:48px;border-radius:12px;overflow:hidden;border:1px solid var(--border)}
.dh-map iframe{width:100%;height:360px;border:0}
@media(max-width:768px){.dh-contact-grid,.dh-form-row{grid-template-columns:1fr}}
</style>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/layout.php';
