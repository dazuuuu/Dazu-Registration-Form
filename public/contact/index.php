<?php
// public/contact/index.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* ============================================================
   EDIT YOUR CONTACT DETAILS HERE — nothing else needs changing.
   ============================================================ */
$contact_phone    = '+254 700 000 000';        // shown to visitors
$contact_phone_e164 = '+254700000000';          // used for the tel: link (no spaces)
$contact_email    = 'hello@ismano.com';
$contact_whatsapp = '254700000000';              // digits only, no + or spaces (for wa.me)
$contact_address  = 'Nairobi, Kenya';
$contact_hours    = 'Mon – Fri, 9:00 AM – 5:00 PM';
$contact_socials  = [                            // set to '' to hide any of them
    'instagram' => '#',
    'linkedin'  => '#',
    'twitter'   => '#',
    'facebook'  => '#',
];
/* ============================================================ */

// --- Form handling (validation + UX). Wire your mailer/DB where marked. ---
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
        // ---------------------------------------------------------------
        // TODO: hook up delivery here. Two common options:
        //   1) Email:  mail($contact_email, $subject ?: 'New enquiry',
        //              $message, "From: $email");
        //   2) Database: INSERT INTO contact_messages (...) VALUES (...);
        // Until one is wired, the form just confirms receipt to the user.
        // ---------------------------------------------------------------
        $sent = true;
    }
}

// Safe redisplay helper (prevents reflected XSS in sticky values)
function cold(string $k): string { return htmlspecialchars($_POST[$k] ?? '', ENT_QUOTES, 'UTF-8'); }

$mapSrc = 'https://www.google.com/maps?q=' . urlencode($contact_address) . '&output=embed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us — Ismano</title>
    <meta name="description" content="Get in touch with Ismano — call, email, WhatsApp, or send us a message.">

    <link rel="stylesheet" href="../assets/css/theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
    body{ padding-top:var(--navbar-height); background:var(--color-surface); }

    .contact-section{ padding-block:var(--space-20) var(--space-24); }
    .contact-grid{ display:grid; grid-template-columns:1fr 1.05fr; gap:var(--space-16); align-items:start; }

    /* ── Left: details ─────────────────────────────────────── */
    .contact-lead h2{ font-size:var(--text-2xl); margin-bottom:var(--space-3); }
    .contact-lead p{ color:var(--color-text-muted); max-width:380px; margin-bottom:var(--space-8); }

    .cinfo-list{ display:flex; flex-direction:column; gap:var(--space-2); }
    .cinfo-item{
        display:flex; align-items:center; gap:var(--space-4);
        padding:var(--space-4) 0; border-bottom:1px solid var(--color-border);
        text-decoration:none;
    }
    .cinfo-item:last-child{ border-bottom:none; }
    .cinfo-icon{
        width:46px; height:46px; border-radius:var(--radius-md); flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        background:rgba(7,89,248,0.08); color:var(--color-primary); font-size:1rem;
        transition:background var(--transition-base), color var(--transition-base);
    }
    .cinfo-item:hover .cinfo-icon{ background:var(--color-primary); color:#fff; }
    .cinfo-icon.is-wa{ background:rgba(37,211,102,0.12); color:#1FAE54; }
    .cinfo-item:hover .cinfo-icon.is-wa{ background:#25D366; color:#fff; }
    .cinfo-label{ font-size:var(--text-xs); font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:var(--color-text-muted); }
    .cinfo-value{ font-size:var(--text-base); font-weight:500; color:var(--color-text-heading); }

    .contact-socials{ display:flex; gap:var(--space-3); margin-top:var(--space-8); }
    .csocial{
        width:42px; height:42px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        border:1px solid var(--color-border); color:var(--color-text-body);
        text-decoration:none; transition:all var(--transition-base);
    }
    .csocial:hover{ background:var(--color-primary); border-color:var(--color-primary); color:#fff; transform:translateY(-3px); }

    /* ── Right: form card ──────────────────────────────────── */
    .contact-card{
        background:var(--color-surface); border:1px solid var(--color-border);
        border-radius:var(--radius-lg); padding:var(--space-10);
        box-shadow:var(--shadow-md);
    }
    .contact-card h3{ font-size:var(--text-xl); margin-bottom:var(--space-2); }
    .contact-card .card-sub{ color:var(--color-text-muted); font-size:var(--text-sm); margin-bottom:var(--space-6); }

    .cf-field{ margin-bottom:var(--space-4); }
    .cf-field label{ display:block; font-size:0.8rem; font-weight:600; color:var(--color-text-heading); margin-bottom:6px; }
    .cf-input, .cf-textarea{
        width:100%; padding:12px 16px;
        border:1px solid var(--color-border); border-radius:var(--radius-md);
        font-family:var(--font-body); font-size:0.92rem; color:var(--color-text-body);
        background:var(--color-surface-alt);
        transition:border-color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
    }
    .cf-input:focus, .cf-textarea:focus{
        outline:none; border-color:var(--color-primary);
        background:var(--color-surface); box-shadow:0 0 0 3px rgba(7,89,248,0.12);
    }
    .cf-textarea{ resize:vertical; min-height:140px; line-height:1.7; }
    .cf-row{ display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4); }
    .cf-submit{
        width:100%; justify-content:center; margin-top:var(--space-2);
        padding:13px; font-size:0.95rem;
    }
    .cf-note{
        display:flex; align-items:center; gap:8px; margin-bottom:var(--space-5);
        padding:12px 16px; border-radius:var(--radius-md); font-size:0.88rem;
    }
    .cf-note.ok{ background:rgba(37,211,102,0.10); color:#147a3a; border:1px solid rgba(37,211,102,0.3); }
    .cf-note.err{ background:#fff3f3; color:#c0392b; border:1px solid #f1c4c4; }

    /* ── Map ───────────────────────────────────────────────── */
    .contact-map{ margin-top:var(--space-16); border-radius:var(--radius-lg); overflow:hidden; border:1px solid var(--color-border); }
    .contact-map iframe{ display:block; width:100%; height:380px; border:0; filter:grayscale(0.2) contrast(1.05); }

    @media (max-width:900px){
        .contact-grid{ grid-template-columns:1fr; gap:var(--space-10); }
    }
    @media (max-width:480px){
        .cf-row{ grid-template-columns:1fr; }
        .contact-card{ padding:var(--space-6); }
        .contact-map iframe{ height:280px; }
    }
    </style>
</head>
<body>

<?php include __DIR__ . '/../components/public/navbar.php'; ?>

<!-- Interactive page header (shared component) -->
<?php
    $hero_eyebrow = 'Contact';
    $hero_title   = 'Get in touch';
    $hero_sub     = "Let's talk about your project";
    $hero_desc    = 'Tell us what you have in mind. We usually reply within 24 hours — no pressure, just an honest conversation.';
    $hero_image   = 'https://images.unsplash.com/photo-1423666639041-f56000c27a9a?w=1600&q=80';
    include __DIR__ . '/../components/public/page_hero.php';
?>

<section class="section contact-section">
  <div class="container">
    <div class="contact-grid">

      <!-- Details -->
      <div class="contact-lead">
        <p class="eyebrow">Reach us directly</p>
        <h2>We'd love to hear from you</h2>
        <p>Prefer a quick chat? Use any of the options below, or send a message and we'll get right back to you.</p>

        <div class="cinfo-list">
          <a class="cinfo-item" href="tel:<?php echo htmlspecialchars($contact_phone_e164); ?>">
            <span class="cinfo-icon"><i class="fas fa-phone"></i></span>
            <span>
              <span class="cinfo-label">Call us</span><br>
              <span class="cinfo-value"><?php echo htmlspecialchars($contact_phone); ?></span>
            </span>
          </a>

          <a class="cinfo-item" href="mailto:<?php echo htmlspecialchars($contact_email); ?>">
            <span class="cinfo-icon"><i class="fas fa-envelope"></i></span>
            <span>
              <span class="cinfo-label">Email us</span><br>
              <span class="cinfo-value"><?php echo htmlspecialchars($contact_email); ?></span>
            </span>
          </a>

          <a class="cinfo-item" href="https://wa.me/<?php echo htmlspecialchars($contact_whatsapp); ?>" target="_blank" rel="noopener">
            <span class="cinfo-icon is-wa"><i class="fab fa-whatsapp"></i></span>
            <span>
              <span class="cinfo-label">WhatsApp</span><br>
              <span class="cinfo-value">Chat with us</span>
            </span>
          </a>

          <div class="cinfo-item">
            <span class="cinfo-icon"><i class="fas fa-location-dot"></i></span>
            <span>
              <span class="cinfo-label">Location</span><br>
              <span class="cinfo-value"><?php echo htmlspecialchars($contact_address); ?></span>
            </span>
          </div>

          <div class="cinfo-item">
            <span class="cinfo-icon"><i class="fas fa-clock"></i></span>
            <span>
              <span class="cinfo-label">Office hours</span><br>
              <span class="cinfo-value"><?php echo htmlspecialchars($contact_hours); ?></span>
            </span>
          </div>
        </div>

        <?php if (array_filter($contact_socials)): ?>
        <div class="contact-socials">
          <?php foreach ($contact_socials as $net => $url): if ($url === '') continue; ?>
            <a class="csocial" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener" aria-label="<?php echo htmlspecialchars(ucfirst($net)); ?>">
              <i class="fab fa-<?php echo htmlspecialchars($net); ?>"></i>
            </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Form -->
      <div class="contact-card">
        <h3>Send a message</h3>
        <p class="card-sub">Fill in the form and we'll be in touch shortly.</p>

        <?php if ($sent): ?>
          <div class="cf-note ok"><i class="fas fa-circle-check"></i> Thanks for reaching out — we've received your message and will reply soon.</div>
        <?php elseif ($form_error): ?>
          <div class="cf-note err"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($form_error); ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <div class="cf-row">
            <div class="cf-field">
              <label for="name">Your name *</label>
              <input class="cf-input" type="text" id="name" name="name" placeholder="your name" value="<?php echo cold('name'); ?>" required>
            </div>
            <div class="cf-field">
              <label for="email">Email *</label>
              <input class="cf-input" type="email" id="email" name="email" placeholder="you@example.com" value="<?php echo cold('email'); ?>" required>
            </div>
          </div>

          <div class="cf-field">
            <label for="subject">Subject</label>
            <input class="cf-input" type="text" id="subject" name="subject" placeholder="What's this about?" value="<?php echo cold('subject'); ?>">
          </div>

          <div class="cf-field">
            <label for="message">Message *</label>
            <textarea class="cf-textarea" id="message" name="message" placeholder="Tell us a little about your project…" required><?php echo cold('message'); ?></textarea>
          </div>

          <button type="submit" class="btn btn--primary cf-submit">
            Send message <i class="fas fa-arrow-right"></i>
          </button>
        </form>
      </div>

    </div><!-- /.contact-grid -->

    <!-- Map -->
    <div class="contact-map">
      <iframe src="<?php echo htmlspecialchars($mapSrc); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Our location"></iframe>
    </div>

  </div>
</section>

<?php include __DIR__ . '/../components/public/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>