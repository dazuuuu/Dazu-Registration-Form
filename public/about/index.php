<?php
// public/about/index.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$page_title = 'About Us - ISMAN Company';
$page_description = 'Learn about ISMAN Company - a leading engineering firm in Kenya specializing in industrial machinery, HVAC, fabrication, and automation solutions.';
$use_home_navbar = false;

// Statistics data
$stats = [
    ['number' => '15+', 'label' => 'Years of Excellence'],
    ['number' => '500+', 'label' => 'Projects Completed'],
    ['number' => '200+', 'label' => 'Satisfied Clients'],
    ['number' => '8', 'label' => 'Core Services']
];

// Team expertise areas
$expertise = [
    ['title' => 'Mechanical Technicians', 'icon' => 'fa-gear', 'count' => '12+'],
    ['title' => 'Electrical Engineers', 'icon' => 'fa-bolt', 'count' => '8+'],
    ['title' => 'Electronics Specialists', 'icon' => 'fa-microchip', 'count' => '6+'],
    ['title' => 'PLC / SCADA Experts', 'icon' => 'fa-display', 'count' => '5+'],
    ['title' => 'HVAC Technicians', 'icon' => 'fa-wind', 'count' => '10+'],
    ['title' => 'Fabrication Welders', 'icon' => 'fa-fire', 'count' => '15+']
];

// Services offered
$services = [
    ['title' => 'Industrial Machinery', 'description' => 'Installation and repair services for compressors, vacuum pumps, air conditioning systems, cold rooms, chillers and Building Management Systems (BMS).'],
    ['title' => 'Preventive Maintenance', 'description' => 'Scheduled preventive maintenance programs tailored to your equipment — keeping downtime to zero and your production running at peak efficiency.'],
    ['title' => 'Pneumatic & Hydraulic Systems', 'description' => 'Complete installation and maintenance of pneumatic systems and hydraulic systems for industrial and manufacturing environments.'],
    ['title' => 'Structural Installation', 'description' => 'Professional structural installation works, delivering robust, safe, and compliant industrial structures for your operations.'],
    ['title' => 'Food Grade Equipment', 'description' => 'Installation of food grade mills, conveyors, elevators, bins, storage silos and dehumidification equipment meeting international food safety standards.'],
    ['title' => 'Water Treatment Plants', 'description' => 'Installation and maintenance of water treatment plants including Reverse Osmosis (RO), Demineralization (DM) and dosing pump systems.'],
    ['title' => 'System Automation', 'description' => 'PLC, SCADA and datalogger installation with real-time alerts — bringing smart monitoring and full automation to your industrial operations.'],
    ['title' => 'HVAC Systems', 'description' => 'Design, installation and maintenance of heating, ventilation and air conditioning systems for commercial and industrial applications.']
];

// Values data
$values = [
    ['title' => 'Integrity', 'icon' => 'fa-hand-peace'],
    ['title' => 'Accountability', 'icon' => 'fa-clipboard-list'],
    ['title' => 'Professionalism', 'icon' => 'fa-briefcase'],
    ['title' => 'Excellence', 'icon' => 'fa-trophy']
];

// Why choose us points
$whyChooseUs = [
    ['icon' => 'fa-certificate', 'title' => 'Certified Engineering Excellence', 'description' => 'Industrial · HVAC · Fabrication · Automation'],
    ['icon' => 'fa-users', 'title' => 'Expert Manpower', 'description' => 'Our team consists of mechanical, electrical, and electronics technicians who are constantly under training to handle the latest industrial challenges.'],
    ['icon' => 'fa-chart-line', 'title' => 'Reliability & Durability', 'description' => 'We consider all technical requirements ensuring our repair and installation outcomes are as reliable and durable as possible — proven against future failures.'],
    ['icon' => 'fa-clock', 'title' => 'Minimal Downtime', 'description' => 'Fast response, accurate diagnostics and efficient execution mean your production line gets back up and running with the least possible disruption.'],
    ['icon' => 'fa-handshake', 'title' => 'Honesty is Our Policy', 'description' => 'We operate with full transparency — fair pricing, honest assessments and no shortcuts. Our company ethos is to be honorable and conscientious to all stakeholders.']
];

// Process steps
$processSteps = [
    ['step' => '01', 'title' => 'Consultation', 'description' => 'We begin by understanding your needs, challenges, and goals through detailed discussions and site assessment.'],
    ['step' => '02', 'title' => 'Design & Planning', 'description' => 'Our engineering team creates detailed designs, specifications, and project timelines tailored to your requirements.'],
    ['step' => '03', 'title' => 'Fabrication', 'description' => 'Using state-of-the-art equipment, we fabricate components to exact specifications with rigorous quality control.'],
    ['step' => '04', 'title' => 'Installation', 'description' => 'Our certified technicians handle professional installation with minimal disruption to your operations.'],
    ['step' => '05', 'title' => 'Maintenance', 'description' => 'Ongoing support and preventive maintenance ensure your equipment continues performing at peak efficiency.']
];

ob_start();
?>

<!-- Hero Section with Image -->
<section class="page-hero" style="position: relative; min-height: 500px; display: flex; align-items: center; background: linear-gradient(135deg, #06342F 0%, #0A5A52 100%); color: white; overflow: hidden;">
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.3;">
        <img src="https://images.unsplash.com/photo-1581092580497-e0d23cbdf1dc?w=1920&q=80" alt="Engineering" style="width: 100%; height: 100%; object-fit: cover;">
    </div>
    <div class="container" style="position: relative; z-index: 2;">
        <div class="row">
            <div class="col-lg-8">
                <span class="eyebrow" style="color: rgba(255,255,255,0.8);">Our Story</span>
                <h1 style="font-size: 3.5rem; font-weight: 800; margin-bottom: 1rem; color: white;">Built on Engineering <span style="color: #2DD4BF;">Excellence</span></h1>
                <p style="font-size: 1.2rem; margin-bottom: 2rem; opacity: 0.9;">
                    Founded with a vision to transform Kenya's engineering landscape, ISMAN has grown from a small welding workshop 
                    to a full-service engineering firm serving hospitals, hotels, and industrial clients nationwide.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="section" style="padding: 60px 0; background: var(--color-surface);">
    <div class="container">
        <div class="row g-4 text-center">
            <?php foreach ($stats as $stat): ?>
            <div class="col-md-3 col-6">
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--brand-primary, #0D9488);"><?php echo $stat['number']; ?></div>
                <div style="font-size: 0.9rem; color: var(--color-text-muted);"><?php echo $stat['label']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Team Section -->
<section class="section section-alt" style="padding: 80px 0; background: var(--color-surface-alt);">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <span class="eyebrow">Our Team</span>
                <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 1.5rem;">A Team of Highly Skilled Engineers & Technicians</h2>
                <p style="line-height: 1.8; margin-bottom: 1.5rem; color: var(--color-text-body);">
                    ISMAN COMPANY is a team of highly skilled engineers and technicians with hands-on experience in 
                    industrial equipment and production lines. We have built our reputation on delivering engineering 
                    excellence that East African industries rely on — from food processing plants and HVAC systems 
                    to full automation and water treatment solutions.
                </p>
                <p style="line-height: 1.8; margin-bottom: 1.5rem; color: var(--color-text-body);">
                    Our deep technical expertise, combined with a commitment to honesty and professionalism, has earned 
                    us the trust of over 200+ clients across manufacturing, hospitality, healthcare, and agriculture 
                    sectors. Every project we touch is treated with the same level of care and precision — whether it's 
                    a routine maintenance call or a full industrial installation.
                </p>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <?php foreach ($expertise as $exp): ?>
                    <div class="col-md-6">
                        <div style="background: var(--color-surface); border-radius: 12px; padding: 20px; text-align: center; border: 1px solid var(--color-border); transition: transform 0.3s ease;">
                            <div style="width: 50px; height: 50px; margin: 0 auto 15px; background: rgba(13,148,136,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas <?php echo $exp['icon']; ?>" style="font-size: 1.2rem; color: var(--brand-primary, #0D9488);"></i>
                            </div>
                            <h4 style="font-size: 0.9rem; margin-bottom: 5px;"><?php echo $exp['title']; ?></h4>
                            <div style="font-size: 0.8rem; color: var(--brand-primary, #0D9488); font-weight: 600;"><?php echo $exp['count']; ?> Experts</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="section" style="padding: 80px 0;">
    <div class="container">
        <div class="text-center" style="max-width: 700px; margin: 0 auto 60px;">
            <span class="eyebrow">What We Do</span>
            <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 1rem;">Our Services Include</h2>
            <p style="color: var(--color-text-muted);">End-to-end industrial engineering solutions — from installation to automation</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($services as $index => $service): ?>
            <div class="col-md-6 col-lg-3">
                <div style="background: var(--color-surface); border-radius: 12px; padding: 25px; border: 1px solid var(--color-border); height: 100%; transition: all 0.3s ease;">
                    <div style="width: 45px; height: 45px; margin-bottom: 15px; background: rgba(13,148,136,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-cog" style="font-size: 1.2rem; color: var(--brand-primary, #0D9488);"></i>
                    </div>
                    <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 10px;"><?php echo $service['title']; ?></h4>
                    <p style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.6; margin: 0;"><?php echo $service['description']; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Why Choose Us Section -->
<section class="section section-alt" style="padding: 80px 0; background: var(--color-surface-alt);">
    <div class="container">
        <div class="text-center" style="max-width: 700px; margin: 0 auto 60px;">
            <span class="eyebrow">Our Advantage</span>
            <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 1rem;">Why Choose Us</h2>
            <p style="color: var(--color-text-muted);">The ISMAN Advantage — Precision You Can Trust</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($whyChooseUs as $item): ?>
            <div class="col-md-6">
                <div style="display: flex; gap: 15px; padding: 20px; background: var(--color-surface); border-radius: 12px; border: 1px solid var(--color-border);">
                    <div style="flex-shrink: 0;">
                        <div style="width: 45px; height: 45px; background: rgba(13,148,136,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas <?php echo $item['icon']; ?>" style="font-size: 1.2rem; color: var(--brand-primary, #0D9488);"></i>
                        </div>
                    </div>
                    <div>
                        <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 8px;"><?php echo $item['title']; ?></h4>
                        <p style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.6; margin: 0;"><?php echo $item['description']; ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Mission & Values Section -->
<section class="section" style="padding: 80px 0;">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-6">
                <span class="eyebrow">What Drives Us</span>
                <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 1.5rem;">Our Mission & Goals</h2>
                <p style="line-height: 1.8; margin-bottom: 1.5rem; color: var(--color-text-body);">
                    To provide innovative engineering solutions that empower businesses and communities across East Africa — 
                    delivering quality, reliability, and value in every project. Our mission is to build our reputation 
                    for integrity, excellence, and leadership as one of the finest engineering and contracting organizations by:
                </p>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: var(--brand-primary, #0D9488);"></i>
                        <span>Continuously improving the standards and quality of our services.</span>
                    </li>
                    <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: var(--brand-primary, #0D9488);"></i>
                        <span>Constantly striving to exceed each client's expectations.</span>
                    </li>
                    <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: var(--brand-primary, #0D9488);"></i>
                        <span>Maintaining our dedication to the highest moral and professional principles.</span>
                    </li>
                    <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: var(--brand-primary, #0D9488);"></i>
                        <span>Providing a challenging and secure environment for our people.</span>
                    </li>
                </ul>
            </div>
            
            <div class="col-lg-6">
                <span class="eyebrow">Core Values</span>
                <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 1.5rem;">Company Ethos</h2>
                <p style="line-height: 1.8; margin-bottom: 1rem; color: var(--color-text-body);">
                    Our company ethos revolves around being regarded by all our stakeholders as being honorable and conscientious.
                </p>
                <p style="margin-bottom: 2rem; font-style: italic; color: var(--brand-primary, #0D9488); font-weight: 500;">
                    "We do what we say, and we say what we do — with full accountability to every client, partner, and team member."
                </p>
                
                <div class="row g-3">
                    <?php foreach ($values as $value): ?>
                    <div class="col-md-6">
                        <div style="text-align: center; padding: 15px; background: var(--color-surface-alt); border-radius: 10px;">
                            <div style="width: 50px; height: 50px; margin: 0 auto 10px; background: var(--brand-primary, #0D9488); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas <?php echo $value['icon']; ?>" style="font-size: 1.2rem; color: white;"></i>
                            </div>
                            <h4 style="font-size: 0.9rem; margin: 0;"><?php echo $value['title']; ?></h4>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Policy Section -->
<section class="section section-alt" style="padding: 80px 0; background: var(--color-surface-alt);">
    <div class="container">
        <div class="text-center" style="max-width: 800px; margin: 0 auto;">
            <span class="eyebrow">Our Commitment</span>
            <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 1rem;">Our Policy</h2>
            <p style="font-size: 1.3rem; margin-bottom: 1.5rem; color: var(--brand-primary, #0D9488); font-weight: 700;">
                "Honesty is the best Policy"
            </p>
            <p style="line-height: 1.8; margin-bottom: 2rem; color: var(--color-text-body);">
                At ISMAN, transparency in every engagement is non-negotiable. We believe that long-term relationships 
                are built on trust, clear communication, and consistent delivery — no shortcuts, no hidden costs.
            </p>
            <div class="row g-4">
                <div class="col-md-3 col-6">
                    <div style="text-align: center;">
                        <i class="fas fa-tag fa-2x" style="color: var(--brand-primary, #0D9488); margin-bottom: 10px;"></i>
                        <h5 style="font-size: 0.85rem;">Transparent pricing</h5>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div style="text-align: center;">
                        <i class="fas fa-comments fa-2x" style="color: var(--brand-primary, #0D9488); margin-bottom: 10px;"></i>
                        <h5 style="font-size: 0.85rem;">Clear communication</h5>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div style="text-align: center;">
                        <i class="fas fa-clock fa-2x" style="color: var(--brand-primary, #0D9488); margin-bottom: 10px;"></i>
                        <h5 style="font-size: 0.85rem;">On-time delivery</h5>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div style="text-align: center;">
                        <i class="fas fa-file-alt fa-2x" style="color: var(--brand-primary, #0D9488); margin-bottom: 10px;"></i>
                        <h5 style="font-size: 0.85rem;">Full documentation</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Process Section -->
<section class="section" style="padding: 80px 0;">
    <div class="container">
        <div class="text-center" style="max-width: 700px; margin: 0 auto 60px;">
            <span class="eyebrow">Our Process</span>
            <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 1rem;">The secret behind our success</h2>
            <p style="color: var(--color-text-muted);">
                Every project follows our proven ISMAN Formula: from initial consultation through design, 
                fabrication, installation, and ongoing maintenance.
            </p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($processSteps as $step): ?>
            <div class="col-md-6 col-lg-4">
                <div style="background: var(--color-surface); border-radius: 12px; padding: 25px; border: 1px solid var(--color-border); height: 100%;">
                    <div style="width: 50px; height: 50px; margin-bottom: 15px; background: var(--brand-primary, #0D9488); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;"><?php echo $step['step']; ?></div>
                    <h4 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 10px;"><?php echo $step['title']; ?></h4>
                    <p style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.6; margin: 0;"><?php echo $step['description']; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="section" style="padding: 80px 0; background: linear-gradient(135deg, #06342F 0%, #0A5A52 100%); color: white;">
    <div class="container">
        <div class="text-center" style="max-width: 700px; margin: 0 auto;">
            <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 1rem;">Ready to Work With Us?</h2>
            <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.9;">
                We provide solutions and take away your worries. Contact ISMAN today for a free consultation on your engineering needs.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="tel:+254724114555" class="btn" style="background: white; color: #06342F; padding: 12px 28px; border-radius: 50px; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-phone-alt me-2"></i> 072 411 4555
                </a>
                <a href="mailto:info@isman.co.ke" class="btn" style="background: transparent; border: 2px solid white; color: white; padding: 12px 28px; border-radius: 50px; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-envelope me-2"></i> info@isman.co.ke
                </a>
                <a href="/Ismano/public/contact/" class="btn" style="background: transparent; border: 2px solid white; color: white; padding: 12px 28px; border-radius: 50px; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-paper-plane me-2"></i> Send Message
                </a>
            </div>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../templates/public/layout.php';
?>