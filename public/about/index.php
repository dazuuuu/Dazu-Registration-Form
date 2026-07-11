<?php
// public/about/index.php — Dazu Hub Institute
$page_title = 'About Us — Dazu Hub Institute';
$page_description = 'Dazu Hub Institute trains the next generation of developers in Nairobi with hands-on courses in Python, PHP, Full-Stack, and AI.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';

ob_start();
?>
<section class="dh-section" style="padding-top:56px;background:linear-gradient(135deg,#1B5E20,#2E7D32);color:#fff;">
    <div class="dh-container dh-reveal">
        <span class="dh-eyebrow" style="color:rgba(255,255,255,.8);">Our story</span>
        <h1 class="dh-h1" style="color:#fff;max-width:720px;">Building Kenya's next generation of tech talent</h1>
        <p class="dh-lead" style="color:rgba(255,255,255,.9);max-width:640px;">Dazu Hub Institute is a Nairobi-based training centre focused on practical software development — from your first line of code to job-ready projects.</p>
    </div>
</section>

<section class="dh-section dh-section--alt">
    <div class="dh-container">
        <div class="dh-stats-row dh-reveal">
            <div class="dh-stat"><strong>6+</strong><span>Career tracks</span></div>
            <div class="dh-stat"><strong>Flexible</strong><span>Payment plans</span></div>
            <div class="dh-stat"><strong>Online &</strong><span>Physical classes</span></div>
            <div class="dh-stat"><strong>Mentor-led</strong><span>Project learning</span></div>
        </div>
    </div>
</section>

<section class="dh-section">
    <div class="dh-container dh-grid-2">
        <div class="dh-reveal-left">
            <span class="dh-eyebrow">What we do</span>
            <h2 class="dh-h2">Learn by building real software</h2>
            <p>Our programs combine structured units, hands-on projects, and tutor feedback. Students progress through a clear curriculum — with tests and projects reviewed before advancing to the next module.</p>
            <ul class="dh-check-list">
                <li>Python, PHP, Node.js, Frontend & Full-Stack</li>
                <li>Monthly, 3-month, or full payment options</li>
                <li>Student portal for coursework and fee tracking</li>
                <li>Experienced tutors assigned per course</li>
            </ul>
        </div>
        <div class="dh-reveal-right dh-card" style="padding:32px;">
            <h3 class="dh-h3">Our values</h3>
            <p><strong>Practical first</strong> — every module ends in something you can show employers.</p>
            <p><strong>Accessible</strong> — flexible fees and online or physical study modes.</p>
            <p><strong>Accountable</strong> — tutors track your progress and give marks on every milestone.</p>
        </div>
    </div>
</section>

<section class="dh-section dh-section--alt" style="text-align:center;">
    <div class="dh-container dh-reveal">
        <h2 class="dh-h2">Ready to start?</h2>
        <p class="dh-lead">Browse our courses or apply today.</p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:20px;">
            <a href="<?php echo url('courses/index.php'); ?>" class="dh-btn dh-btn--primary">View courses</a>
            <a href="<?php echo url('auth/student_register.php'); ?>" class="dh-btn dh-btn--outline">Apply now</a>
        </div>
    </div>
</section>
<style>
.dh-stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:20px;text-align:center}
.dh-stat strong{display:block;font-size:1.5rem;color:var(--dh-green)}
.dh-check-list{list-style:none;padding:0}.dh-check-list li{padding:8px 0 8px 28px;position:relative}
.dh-check-list li::before{content:'✓';position:absolute;left:0;color:var(--dh-green);font-weight:700}
.dh-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start}
@media(max-width:768px){.dh-grid-2{grid-template-columns:1fr}}
</style>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/layout.php';
