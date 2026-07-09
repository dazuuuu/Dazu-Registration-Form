<?php
// public/components/public/footer.php — Dazu Hub Tech Skills Institute
$year = date('Y');
$base = '/Registration-System/public';
?>
<footer class="dh-footer">
    <div class="dh-footer-top">
        <div class="dh-container dh-footer-grid">

            <div class="dh-footer-brand">
                <a href="<?php echo $base; ?>/" class="dh-footer-logo-link">
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
                    <a href="#" class="dh-social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="dh-social-link" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="dh-footer-col">
                <h6 class="dh-footer-col-title">Quick Links</h6>
                <ul class="dh-footer-links">
                    <li><a href="<?php echo $base; ?>/">Home</a></li>
                    <li><a href="<?php echo $base; ?>/#courses">Courses</a></li>
                    <li><a href="<?php echo $base; ?>/about/">About</a></li>
                    <li><a href="<?php echo $base; ?>/contact/">Contact</a></li>
                </ul>
            </div>

            <div class="dh-footer-col">
                <h6 class="dh-footer-col-title">Top Courses</h6>
                <ul class="dh-footer-links">
                    <li><a href="<?php echo $base; ?>/#courses">Python</a></li>
                    <li><a href="<?php echo $base; ?>/#courses">Web Development</a></li>
                    <li><a href="<?php echo $base; ?>/#courses">AI &amp; Machine Learning</a></li>
                    <li><a href="<?php echo $base; ?>/#courses">Cybersecurity</a></li>
                </ul>
            </div>

            <div class="dh-footer-col">
                <h6 class="dh-footer-col-title">Contact Us</h6>
                <div class="dh-footer-contact-list">
                    <a href="mailto:info@dazuhub.com" class="dh-footer-contact-item">
                        <span class="dh-footer-contact-icon"><i class="fas fa-envelope"></i></span>
                        <span>info@dazuhub.com</span>
                    </a>
                    <a href="tel:+254750116600" class="dh-footer-contact-item">
                        <span class="dh-footer-contact-icon"><i class="fas fa-phone"></i></span>
                        <span>+254 750 116 600</span>
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

<style>
.dh-footer {
    background: #0b1120;
    color: rgba(255,255,255,0.65);
}
.dh-footer-top { padding: 72px 0 56px; }
.dh-footer-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr 1fr 1.3fr;
    gap: 48px;
    align-items: start;
}

.dh-footer-logo-link {
    display: inline-flex; align-items: center; gap: 12px;
    text-decoration: none; margin-bottom: 20px;
}
.dh-footer-logo-icon { display: flex; line-height: 0; }
.dh-footer-logo-text { display: flex; flex-direction: column; line-height: 1.15; }
.dh-footer-logo-title {
    font-family: var(--font-display);
    font-size: 1.2rem; font-weight: 800; color: #fff;
}
.dh-footer-logo-title strong { color: #00A859; font-weight: 800; }
.dh-footer-logo-sub {
    font-size: 0.68rem; color: rgba(255,255,255,0.45); margin-top: 3px;
}
.dh-footer-tagline {
    font-size: 0.9rem; line-height: 1.75;
    color: rgba(255,255,255,0.55);
    max-width: 300px; margin-bottom: 24px;
}

.dh-footer-social { display: flex; gap: 10px; }
.dh-social-link {
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.7);
    font-size: 0.85rem; text-decoration: none;
    transition: background .2s, color .2s, transform .15s;
}
.dh-social-link:hover {
    background: #00A859; color: #fff; transform: translateY(-2px);
}

.dh-footer-col-title {
    font-family: var(--font-display);
    font-size: 1rem; font-weight: 700;
    color: #fff; margin-bottom: 20px;
}
.dh-footer-links { list-style: none; display: flex; flex-direction: column; gap: 12px; }
.dh-footer-links a {
    font-size: 0.9rem; color: rgba(255,255,255,0.55);
    text-decoration: none; transition: color .2s;
}
.dh-footer-links a:hover { color: #00A859; }

.dh-footer-contact-list { display: flex; flex-direction: column; gap: 14px; }
.dh-footer-contact-item {
    display: flex; align-items: flex-start; gap: 12px;
    font-size: 0.9rem; color: rgba(255,255,255,0.55);
    text-decoration: none;
}
.dh-footer-contact-item:is(a):hover { color: #fff; }
.dh-footer-contact-icon {
    width: 22px; flex-shrink: 0;
    color: #00A859; font-size: 0.95rem;
    margin-top: 2px;
}

.dh-footer-bottom {
    border-top: 1px solid rgba(255,255,255,0.08);
    padding: 22px 0;
    text-align: center;
}
.dh-footer-bottom small {
    font-size: 0.82rem; color: rgba(255,255,255,0.35);
}

@media (max-width: 1024px) {
    .dh-footer-grid { grid-template-columns: 1fr 1fr; gap: 36px; }
    .dh-footer-brand { grid-column: 1 / -1; }
}
@media (max-width: 600px) {
    .dh-footer-grid { grid-template-columns: 1fr; gap: 28px; }
    .dh-footer-top { padding: 48px 0 36px; }
}
</style>
