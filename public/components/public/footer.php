<?php
// public/components/public/footer.php — Dazu Hub Institute
$year = date('Y');
require_once __DIR__ . '/../../../app/v1/helpers/site_logo.php';
require_once __DIR__ . '/../../../app/v1/helpers/site_contact.php';
$logoPath = site_logo_url(url('assets/images/logo/logo.png'));
$contact = site_contact_settings();
?>
<footer class="dh-footer">
    <div class="dh-footer-top">
        <div class="dh-container dh-footer-grid">

            <!-- Brand col -->
            <div class="dh-footer-brand">
                <a href="<?php echo url('/'); ?>" class="dh-footer-logo-link">
                    <img src="<?php echo $logoPath; ?>" alt="Dazu Hub Institute"
                         height="40" class="dh-footer-logo"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <span class="dh-footer-logo-fallback" style="display:none;">
                        <span class="dh-footer-logo-mark">&lt;/&gt;</span>
                        <span class="dh-footer-logo-text">Dazu <strong>Hub</strong></span>
                    </span>
                </a>
                <p class="dh-footer-tagline">
                    Hands-on tech training for the next generation of developers.
                    Based in Nairobi, Kenya.
                </p>
                <div class="dh-footer-social">
                    <a href="<?php echo htmlspecialchars(whatsapp_chat_url()); ?>" class="dh-social-link" target="_blank" rel="noopener" aria-label="WhatsApp" title="Chat on WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="#" class="dh-social-link" aria-label="Twitter / X">
                        <i class="fab fa-x-twitter"></i>
                    </a>
                    <a href="#" class="dh-social-link" aria-label="LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="#" class="dh-social-link" aria-label="GitHub">
                        <i class="fab fa-github"></i>
                    </a>
                    <a href="#" class="dh-social-link" aria-label="YouTube">
                        <i class="fab fa-youtube"></i>
                    </a>
                </div>
            </div>

            <!-- Courses col -->
            <div class="dh-footer-col">
                <h6 class="dh-footer-col-title">Courses</h6>
                <ul class="dh-footer-links">
                    <li><a href="<?php echo url('#courses'); ?>">Python &amp; Django</a></li>
                    <li><a href="<?php echo url('#courses'); ?>">PHP Development</a></li>
                    <li><a href="<?php echo url('#courses'); ?>">Node.js</a></li>
                    <li><a href="<?php echo url('#courses'); ?>">Frontend Development</a></li>
                    <li><a href="<?php echo url('#courses'); ?>">Full-Stack</a></li>
                    <li><a href="<?php echo url('#courses'); ?>">AI Fundamentals</a></li>
                </ul>
            </div>

            <!-- Quick links col -->
            <div class="dh-footer-col">
                <h6 class="dh-footer-col-title">Institute</h6>
                <ul class="dh-footer-links">
                    <li><a href="<?php echo url('about/index.php'); ?>">About Us</a></li>
                    <li><a href="<?php echo url('courses/index.php'); ?>">All Courses</a></li>
                    <li><a href="<?php echo url('contact/index.php'); ?>">Contact</a></li>
                    <li><a href="<?php echo url('auth/student_register.php'); ?>">Apply Now</a></li>
                    <li><a href="<?php echo url('auth/login.php'); ?>">Student Login</a></li>
                    <li><a href="<?php echo url('auth/login.php'); ?>">Tutor Login</a></li>
                </ul>
            </div>

            <!-- Contact col -->
            <div class="dh-footer-col">
                <h6 class="dh-footer-col-title">Contact</h6>
                <div class="dh-footer-contact-list">
                    <a href="mailto:<?php echo htmlspecialchars($contact['contact_email']); ?>" class="dh-footer-contact-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($contact['contact_email']); ?></span>
                    </a>
                    <a href="tel:<?php echo htmlspecialchars(site_phone_tel()); ?>" class="dh-footer-contact-item">
                        <i class="fas fa-phone"></i>
                        <span><?php echo htmlspecialchars(site_phone_display()); ?></span>
                    </a>
                    <a href="<?php echo htmlspecialchars(whatsapp_chat_url()); ?>" class="dh-footer-contact-item" target="_blank" rel="noopener">
                        <i class="fab fa-whatsapp"></i>
                        <span>WhatsApp <?php echo htmlspecialchars(site_whatsapp_display()); ?></span>
                    </a>
                    <div class="dh-footer-contact-item">
                        <i class="fas fa-location-dot"></i>
                        <span><?php echo htmlspecialchars($contact['contact_address']); ?></span>
                    </div>
                </div>
                <a href="<?php echo url('courses/index.php'); ?>" class="dh-btn dh-btn-primary dh-footer-cta">
                    <i class="fas fa-paper-plane"></i> Apply Now
                </a>
            </div>
        </div>
    </div>

    <div class="dh-footer-bottom">
        <div class="dh-container dh-footer-bottom-inner">
            <small>&copy; <?php echo $year; ?> Dazu Hub Institute. All rights reserved.</small>
            <div class="dh-footer-bottom-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms &amp; Conditions</a>
            </div>
        </div>
    </div>
</footer>
