<?php
// public/components/public/footer.php — Dazu Hub Tech Skills Institute (mockup)
$year = date('Y');
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
if (!function_exists('site_phone_display')) {
    require_once __DIR__ . '/../../../app/v1/helpers/contact.php';
}
?>
<footer class="dh-footer">
    <div class="dh-footer-top">
        <div class="dh-container dh-footer-grid">

            <div class="dh-footer-brand">
                <a href="<?php echo url('/'); ?>" class="dh-footer-logo-link">
                    <span class="dh-footer-logo-icon" aria-hidden="true">
                        <svg width="40" height="40" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="42" height="42" rx="10" fill="#00A859"/>
                            <text x="21" y="28" text-anchor="middle" font-family="Inter, sans-serif" font-size="16" font-weight="800" fill="#fff">&lt;/&gt;</text>
                        </svg>
                    </span>
                    <span class="dh-footer-logo-text">
                        <span class="dh-footer-logo-title">Dazu <strong>hub</strong></span>
                        <span class="dh-footer-logo-sub">Tech Skills Institute</span>
                    </span>
                </a>
                <p class="dh-footer-tagline">
                    Empowering the next generation of tech professionals through practical,
                    hands-on training in high-demand skills.
                </p>
                <div class="dh-footer-social">
                    <a href="#" class="dh-social-link" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="dh-social-link" aria-label="Twitter / X"><i class="fab fa-x-twitter"></i></a>
                    <a href="<?php echo htmlspecialchars(whatsapp_chat_url()); ?>" class="dh-social-link" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="#" class="dh-social-link" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="dh-footer-col">
                <h6 class="dh-footer-col-title">Quick Links</h6>
                <ul class="dh-footer-links">
                    <li><a href="<?php echo url('/'); ?>">Home</a></li>
                    <li><a href="<?php echo url('/#courses'); ?>">Courses</a></li>
                    <li><a href="<?php echo url('about/index.php'); ?>">About</a></li>
                    <li><a href="<?php echo url('contact/index.php'); ?>">Contact</a></li>
                </ul>
            </div>

            <div class="dh-footer-col">
                <h6 class="dh-footer-col-title">Top Courses</h6>
                <ul class="dh-footer-links">
                    <li><a href="<?php echo url('/#courses'); ?>">Python</a></li>
                    <li><a href="<?php echo url('/#courses'); ?>">Web Development</a></li>
                    <li><a href="<?php echo url('/#courses'); ?>">AI &amp; Machine Learning</a></li>
                    <li><a href="<?php echo url('/#courses'); ?>">Cybersecurity</a></li>
                </ul>
            </div>

            <div class="dh-footer-col">
                <h6 class="dh-footer-col-title">Contact Us</h6>
                <div class="dh-footer-contact-list">
                    <a href="mailto:info@dazuhub.com" class="dh-footer-contact-item">
                        <span class="dh-footer-contact-icon"><i class="fas fa-envelope"></i></span>
                        <span>info@dazuhub.com</span>
                    </a>
                    <a href="tel:<?php echo htmlspecialchars(site_phone_tel()); ?>" class="dh-footer-contact-item">
                        <span class="dh-footer-contact-icon"><i class="fas fa-phone"></i></span>
                        <span><?php echo htmlspecialchars(site_phone_display()); ?></span>
                    </a>
                    <div class="dh-footer-contact-item">
                        <span class="dh-footer-contact-icon"><i class="fas fa-location-dot"></i></span>
                        <span>Nairobi, Kenya</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dh-footer-bottom">
        <div class="dh-container">
            <small>&copy; <?php echo $year; ?> Dazu Hub Tech Skills Institute. All rights reserved.</small>
        </div>
    </div>
</footer>
