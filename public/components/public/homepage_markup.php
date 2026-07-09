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
                <a href="#register" class="hp-btn-watch">
                    <span class="hp-play"><i class="fas fa-play" aria-hidden="true"></i></span>
                    Watch Overview
                </a>
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
                if ($usingStaticCourses) {
                    $detailLink = url('/#register');
                } else {
                    $detailLink = course_view_url($course);
                }
            ?>
            <a href="<?php echo htmlspecialchars($detailLink); ?>" class="hp-course-card dh-reveal dh-delay-<?php echo ($i % 4) + 1; ?>" style="text-decoration:none;color:inherit;">
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
                </div>
            </a>
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
            <p>Fill in the application form and our team will verify your payment and get you enrolled.</p>
            <ul class="hp-apply-list">
                <li><i class="fas fa-check-circle"></i> Flexible monthly payment plans</li>
                <li><i class="fas fa-check-circle"></i> Physical &amp; online study modes</li>
                <li><i class="fas fa-check-circle"></i> Certificate on completion</li>
                <li><i class="fas fa-check-circle"></i> Nairobi-based institute</li>
            </ul>
        </div>

        <div class="hp-apply-form-col dh-reveal dh-delay-2">
            <div class="hp-form-card">
                <div class="hp-form-head">
                    <h2><i class="fas fa-graduation-cap" style="margin-right:6px;"></i> Apply Now</h2>
                    <p>Limited slots per course. We follow up within 24 hours.</p>
                </div>
                <div class="hp-form-body">
                    <?php if ($reg_success): ?>
                    <div class="hf-alert hf-alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div><strong>Application received.</strong> Our team will verify your payment and contact you within 24 hours.</div>
                    </div>
                    <?php else: ?>
                    <?php if ($reg_error): ?>
                    <div class="hf-alert hf-alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo htmlspecialchars($reg_error); ?></div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="hpForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="dh_register" value="1">

                        <div class="hf-row">
                            <div class="hf-field">
                                <label for="hp_name">Full Name *</label>
                                <input type="text" id="hp_name" name="full_name" required placeholder="Jane Wanjiku"
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                            </div>
                            <div class="hf-field">
                                <label for="hp_phone">Phone *</label>
                                <input type="tel" id="hp_phone" name="phone" required placeholder="07XX XXX XXX"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="hf-field">
                            <label for="hp_email">Email *</label>
                            <input type="email" id="hp_email" name="email" required placeholder="jane@email.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="hf-field">
                            <label for="hp_course">Course *</label>
                            <select id="hp_course" name="course_id" required>
                                <option value="">— Select a course —</option>
                                <?php foreach ($displayCourses as $c): ?>
                                <option value="<?php echo (int)($c['id'] ?? 0); ?>"
                                        data-fee="<?php echo (float)$c['course_fee']; ?>"
                                        data-months="<?php echo (int)$c['duration_months']; ?>"
                                        <?php echo (isset($_POST['course_id']) && (int)$_POST['course_id']===(int)($c['id']??0)) ? 'selected':''; ?>>
                                    <?php echo htmlspecialchars($c['title']); ?> — KSh <?php echo number_format($c['course_fee']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="hf-field">
                            <label>Study Mode *</label>
                            <div class="hf-mode">
                                <div class="hf-mode-opt">
                                    <input type="radio" id="hp_online" name="study_mode" value="online" required
                                           <?php echo (!isset($_POST['study_mode'])||$_POST['study_mode']==='online')?'checked':''; ?>>
                                    <label for="hp_online">💻 <strong>Online</strong></label>
                                </div>
                                <div class="hf-mode-opt">
                                    <input type="radio" id="hp_physical" name="study_mode" value="physical"
                                           <?php echo (isset($_POST['study_mode'])&&$_POST['study_mode']==='physical')?'checked':''; ?>>
                                    <label for="hp_physical">🏫 <strong>Physical</strong></label>
                                </div>
                            </div>
                        </div>

                        <div class="hf-field">
                            <label>Payment Plan *</label>
                            <div class="hf-plan">
                                <div class="hf-plan-opt">
                                    <input type="radio" id="hp_mo" name="payment_plan" value="monthly" checked>
                                    <label for="hp_mo"><strong>Monthly</strong><span id="hp_hint_mo">—</span></label>
                                </div>
                                <div class="hf-plan-opt">
                                    <input type="radio" id="hp_3mo" name="payment_plan" value="3months">
                                    <label for="hp_3mo"><strong>3-Month</strong><span id="hp_hint_3mo">—</span></label>
                                </div>
                                <div class="hf-plan-opt">
                                    <input type="radio" id="hp_full" name="payment_plan" value="full">
                                    <label for="hp_full"><strong>Full</strong><span id="hp_hint_full">—</span></label>
                                </div>
                            </div>
                        </div>

                        <div class="hf-fee" id="hpFeeBox">
                            <div class="hf-fee-row"><span>Reg. fee</span><strong id="hp_rfee">KSh 1,000</strong></div>
                            <div class="hf-fee-row"><span id="hp_ilabel">Select a course</span><strong id="hp_iamt">—</strong></div>
                            <hr class="hf-fee-hr">
                            <div class="hf-fee-total">
                                <span>Pay now</span>
                                <span class="amt" id="hp_total">KSh 1,000</span>
                            </div>
                        </div>

                        <div class="hf-field">
                            <label for="hp_mpesa">M-Pesa Name / Code *</label>
                            <input type="text" id="hp_mpesa" name="payment_reference" required
                                   placeholder="Jane Wanjiku or QGH7XXXX"
                                   value="<?php echo htmlspecialchars($_POST['payment_reference'] ?? ''); ?>">
                        </div>

                        <div class="hf-field">
                            <label>ID / Result Slip (PDF) *</label>
                            <label class="hf-upload" for="hp_id_doc">
                                <i class="fas fa-file-pdf"></i>
                                <span id="hpUploadText">Upload PDF</span>
                            </label>
                            <input type="file" id="hp_id_doc" name="id_document" accept="application/pdf" required>
                        </div>

                        <button type="submit" class="hf-submit" id="hpSubmitBtn">
                            <i class="fas fa-paper-plane"></i> Submit Application
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
