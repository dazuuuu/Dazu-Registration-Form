<?php
// public/components/public/homepage_markup.php — mockup homepage sections
$heroImg = 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=800&q=80';
?>

<section class="hp-hero" id="home">
    <div class="dh-container hp-hero-inner">
        <div class="hp-hero-copy dh-reveal">
            <span class="hp-hero-eyebrow">Learn. Build. Succeed.</span>
            <h1>
                Upgrade Your Tech Skills.
                <span class="hp-accent">Build Your Future.</span>
            </h1>
            <p class="hp-hero-sub">
                Practical, hands-on training in high-demand tech skills to launch your career.
            </p>
            <div class="hp-hero-actions">
                <a href="#courses" class="hp-btn-explore">
                    Explore Courses <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
                <button type="button" class="hp-btn-watch" data-dh-apply-open>
                    <span class="hp-play"><i class="fas fa-paper-plane" aria-hidden="true"></i></span>
                    Apply Now
                </button>
            </div>
        </div>

        <div class="hp-hero-visual dh-reveal dh-delay-2">
            <div class="hp-hero-blob" aria-hidden="true"></div>
            <div class="hp-hero-blob-text" aria-hidden="true">Dazu<br>hub</div>
            <i class="fas fa-arrow-turn-down hp-hero-arrow hp-hero-arrow--1" aria-hidden="true"></i>
            <i class="fas fa-arrow-turn-up hp-hero-arrow hp-hero-arrow--2" aria-hidden="true"></i>
            <div class="hp-hero-photo">
                <img src="<?php echo htmlspecialchars($heroImg); ?>"
                     alt="Student learning tech skills at Dazu Hub"
                     loading="eager" width="600" height="700">
            </div>
        </div>
    </div>
</section>

<section class="hp-courses" id="courses">
    <div class="dh-container">
        <div class="hp-section-head dh-reveal">
            <h2>Popular Courses</h2>
        </div>

        <div class="hp-courses-grid">
            <?php foreach ($displayCourses as $i => $course):
                $imgSrc = !empty($course['cover_image']) ? $course['cover_image'] : ($course['card_image'] ?? '');
                $faIcon = $course['fa_icon'] ?? 'fa-book';
                $rating = $course['rating'] ?? '4.8';
            ?>
            <article class="hp-course-card dh-reveal dh-delay-<?php echo ($i % 4) + 1; ?>">
                <div class="hp-course-img-wrap">
                    <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                         alt="<?php echo htmlspecialchars($course['title']); ?>"
                         loading="lazy">
                    <span class="hp-course-icon"><i class="fas <?php echo htmlspecialchars($faIcon); ?>" aria-hidden="true"></i></span>
                </div>
                <div class="hp-course-body">
                    <div class="hp-course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                    <div class="hp-course-overview">
                        <?php echo htmlspecialchars(mb_strimwidth($course['overview'] ?? '', 0, 90, '…')); ?>
                    </div>
                    <div class="hp-course-meta">
                        <i class="fas fa-star hp-star" aria-hidden="true"></i>
                        <strong><?php echo htmlspecialchars($rating); ?></strong>
                        <span class="hp-dot">&middot;</span>
                        <span>Beginner to Advanced</span>
                    </div>
                    <button type="button" class="hp-course-apply-btn" data-dh-apply-open>
                        Apply <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="hp-features">
    <div class="dh-container">
        <div class="hp-features-grid">
            <div class="hp-feature dh-reveal">
                <div class="hp-feature-icon"><i class="fas fa-laptop-code" aria-hidden="true"></i></div>
                <h3>Hands-on Projects</h3>
                <p>Build real-world projects.</p>
            </div>
            <div class="hp-feature dh-reveal dh-delay-1">
                <div class="hp-feature-icon"><i class="fas fa-certificate" aria-hidden="true"></i></div>
                <h3>Industry Recognized</h3>
                <p>Get certified and boost your career.</p>
            </div>
            <div class="hp-feature dh-reveal dh-delay-2">
                <div class="hp-feature-icon"><i class="fas fa-chalkboard-user" aria-hidden="true"></i></div>
                <h3>Expert Mentors</h3>
                <p>Learn from industry professionals.</p>
            </div>
            <div class="hp-feature dh-reveal dh-delay-3">
                <div class="hp-feature-icon"><i class="fas fa-clock" aria-hidden="true"></i></div>
                <h3>Flexible Learning</h3>
                <p>Study at your own pace.</p>
            </div>
        </div>
    </div>
</section>

<section class="hp-apply" id="register">
    <div class="dh-container hp-apply-inner">
        <div class="hp-apply-info dh-reveal">
            <h2>Ready to start your tech journey?</h2>
            <p>Pay via M-Pesa Lipa na M-PESA, then complete your application. Our team verifies your payment and contacts you within 24 hours.</p>
            <ul class="hp-apply-list">
                <li><i class="fas fa-check-circle"></i> Flexible monthly payment plans</li>
                <li><i class="fas fa-check-circle"></i> Physical &amp; online study modes</li>
                <li><i class="fas fa-check-circle"></i> Certificate on completion</li>
                <li><i class="fas fa-check-circle"></i> Nairobi-based institute</li>
            </ul>
        </div>

        <div class="hp-apply-cta-col dh-reveal dh-delay-2">
            <?php include __DIR__ . '/payment_credentials_box.php'; ?>
            <button type="button" class="hf-submit hp-apply-cta-btn" data-dh-apply-open style="margin-top:16px;">
                <i class="fas fa-graduation-cap"></i> Open Application Form
            </button>
            <?php include __DIR__ . '/apply_share_actions.php'; ?>
        </div>
    </div>
</section>
