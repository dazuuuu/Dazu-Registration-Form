<?php
// public/components/public/footer.php — Dazu Hub Institute
$year = date('Y');
$logoPath = '/Registration-System/public/assets/images/logo/logo.png';
?>
<footer class="dh-footer">
    <div class="dh-footer-top">
        <div class="dh-container dh-footer-grid">

            <!-- Brand col -->
            <div class="dh-footer-brand">
                <a href="/Registration-System/public/" class="dh-footer-logo-link">
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
                    <li><a href="/Registration-System/public/#courses">Python &amp; Django</a></li>
                    <li><a href="/Registration-System/public/#courses">PHP Development</a></li>
                    <li><a href="/Registration-System/public/#courses">Node.js</a></li>
                    <li><a href="/Registration-System/public/#courses">Frontend Development</a></li>
                    <li><a href="/Registration-System/public/#courses">Full-Stack</a></li>
                    <li><a href="/Registration-System/public/#courses">AI Fundamentals</a></li>
                </ul>
            </div>

            <!-- Quick links col -->
            <div class="dh-footer-col">
                <h6 class="dh-footer-col-title">Institute</h6>
                <ul class="dh-footer-links">
                    <li><a href="/Registration-System/public/#how-it-works">How It Works</a></li>
                    <li><a href="/Registration-System/public/#register">Apply Now</a></li>
                    <li><a href="/Registration-System/public/auth/login.php">Student Login</a></li>
                    <li><a href="/Registration-System/public/auth/login.php">Tutor Login</a></li>
                </ul>
            </div>

            <!-- Contact col -->
            <div class="dh-footer-col">
                <h6 class="dh-footer-col-title">Contact</h6>
                <div class="dh-footer-contact-list">
                    <a href="mailto:info@dazuhub.ac.ke" class="dh-footer-contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>info@dazuhub.ac.ke</span>
                    </a>
                    <a href="tel:+254700000000" class="dh-footer-contact-item">
                        <i class="fas fa-phone"></i>
                        <span>+254 700 000 000</span>
                    </a>
                    <div class="dh-footer-contact-item">
                        <i class="fas fa-location-dot"></i>
                        <span>Nairobi, Kenya</span>
                    </div>
                </div>
                <a href="/Registration-System/public/#register" class="dh-btn dh-btn-primary" style="margin-top:18px;width:100%;justify-content:center;padding:11px 18px;">
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

<style>
/* ============================================================
   DAZU HUB — FOOTER
   Uses CSS variables from layout.php
============================================================ */
.dh-footer {
    background: var(--footer-bg);
    color: var(--footer-text);
    margin-top: 0;
}

.dh-footer-top { padding: 72px 0 56px; }

.dh-footer-grid {
    display: grid;
    grid-template-columns: 1.8fr 1fr 1fr 1.4fr;
    gap: 48px;
    align-items: start;
}

/* Brand */
.dh-footer-logo-link {
    display: inline-flex; align-items: center; gap: 10px;
    text-decoration: none; margin-bottom: 18px;
}
.dh-footer-logo { height: 40px; width: auto; object-fit: contain; }
.dh-footer-logo-fallback {
    display: flex; align-items: center; gap: 10px;
}
.dh-footer-logo-mark {
    font-family: var(--font-mono);
    font-size: 1.1rem; font-weight: 700;
    color: var(--dh-green-light);
    background: rgba(76,175,80,0.12);
    border-radius: 6px; padding: 6px 10px;
}
.dh-footer-logo-text {
    font-family: var(--font-display); font-size: 1.2rem; font-weight: 700;
    color: rgba(255,255,255,0.9);
}
.dh-footer-logo-text strong { color: var(--dh-green-light); }

.dh-footer-tagline {
    font-size: 0.875rem;
    line-height: 1.7;
    color: var(--footer-text);
    max-width: 260px;
    margin-bottom: 22px;
}

/* Social */
.dh-footer-social { display: flex; gap: 10px; }
.dh-social-link {
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.5);
    font-size: 0.85rem;
    text-decoration: none;
    transition: border-color var(--trans), color var(--trans), transform 0.15s ease;
}
.dh-social-link:hover {
    border-color: var(--dh-green-light);
    color: var(--dh-green-light);
    transform: translateY(-3px);
}

/* Column */
.dh-footer-col-title {
    font-family: var(--font-display);
    font-size: 0.75rem; font-weight: 700;
    letter-spacing: 0.12em; text-transform: uppercase;
    color: rgba(255,255,255,0.9);
    margin-bottom: 18px;
}

.dh-footer-links { list-style: none; display: flex; flex-direction: column; gap: 10px; }
.dh-footer-links a {
    font-size: 0.875rem;
    color: var(--footer-text);
    text-decoration: none;
    position: relative;
    display: inline-block;
    transition: color var(--trans), padding-left var(--trans);
}
.dh-footer-links a:hover {
    color: var(--dh-green-light);
    padding-left: 8px;
}
.dh-footer-links a::before {
    content: '';
    position: absolute; left: 0; top: 50%;
    width: 4px; height: 4px; border-radius: 50%;
    background: var(--dh-green-light);
    transform: translateY(-50%) scale(0);
    transition: transform var(--trans);
}
.dh-footer-links a:hover::before { transform: translateY(-50%) scale(1); }

/* Contact */
.dh-footer-contact-list { display: flex; flex-direction: column; gap: 12px; }
.dh-footer-contact-item {
    display: flex; align-items: center; gap: 10px;
    font-size: 0.875rem; color: var(--footer-text);
    text-decoration: none;
}
.dh-footer-contact-item:is(a):hover { color: var(--dh-green-light); }
.dh-footer-contact-item i {
    width: 18px; text-align: center;
    color: var(--dh-green-light); font-size: 0.9rem;
    flex-shrink: 0;
}

/* Bottom bar */
.dh-footer-bottom {
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 20px 0;
}
.dh-footer-bottom-inner {
    display: flex; align-items: center;
    justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.dh-footer-bottom small { font-size: 0.8rem; color: rgba(255,255,255,0.3); }
.dh-footer-bottom-links { display: flex; gap: 24px; }
.dh-footer-bottom-links a {
    font-size: 0.8rem; color: rgba(255,255,255,0.3);
    text-decoration: none;
    transition: color var(--trans);
}
.dh-footer-bottom-links a:hover { color: rgba(255,255,255,0.7); }

/* Responsive */
@media (max-width: 1024px) {
    .dh-footer-grid { grid-template-columns: 1fr 1fr; gap: 36px; }
    .dh-footer-brand { grid-column: 1 / -1; }
}
@media (max-width: 600px) {
    .dh-footer-grid { grid-template-columns: 1fr; gap: 28px; }
    .dh-footer-top { padding: 48px 0 36px; }
    .dh-footer-bottom-inner { flex-direction: column; align-items: flex-start; }
}
</style>