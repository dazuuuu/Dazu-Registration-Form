<?php
$page_title = 'About Dazu Training Institute | Technology & Vocational Training in Kenya';
$page_description = 'DAZU Training Institute offers practical software engineering, cybersecurity, data science, AI, Python, caregiver, beauty and business training in Nairobi, Thika and online.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/controllers/GalleryController.php';

$teamMembers = (new \Models\TeamMemberModel($pdo))->all(true);

try {
    $galleryItems = (new GalleryController($pdo))->getAll('active', 8, 0);
} catch (Throwable $e) {
    $galleryItems = [];
}

$whatsapp = 'https://wa.me/254722922454';

$serve = [
    ['Students and school leavers', 'looking to acquire market-relevant skills and prepare for future careers.'],
    ['College and university graduates', 'seeking practical competencies that complement their academic qualifications.'],
    ['Working professionals', 'interested in upgrading their skills and remaining competitive.'],
    ['Career changers', 'looking to transition into technology, healthcare, business or other professional fields.'],
    ['Entrepreneurs and business owners', 'seeking skills to establish, manage and grow successful businesses.'],
    ['Organisations and businesses', 'looking to strengthen their teams through professional and digital skills training.'],
];

$schools = [
    ['School of Software Engineering and Development', 'Build the technology that powers tomorrow.',
     ['DAZU Training Institute provides practical software engineering and software development training in Kenya for learners interested in building websites, applications, digital platforms and business systems.',
      'Our software development programmes introduce learners to the principles of programming, application development, databases, system architecture and modern software technologies.'],
     ['Software Engineering', 'Full-Stack Web Development', 'Front-End Development', 'Back-End Development', 'Python Programming', 'Database Management', 'Mobile Application Development', 'UI/UX Design', 'API Development and Integration', 'Cloud Computing and DevOps'],
     ['Learners develop practical programming and problem-solving skills through coding exercises, application development and project-based assignments.',
      'Our goal is to help students build the technical foundations required to pursue opportunities in software development, web development, application development, freelance programming and technology entrepreneurship.'],
     'Explore Software Engineering and Development Courses'],
    ['School of Cybersecurity and Networking', 'Learn to protect systems, networks and digital information.',
     ['As organisations increasingly depend on digital infrastructure, cybersecurity and network security have become essential areas of professional development.',
      'DAZU offers cybersecurity and networking training designed to introduce learners to the technologies, methods and practical skills used to protect computer systems, networks and information.'],
     ['Cybersecurity Fundamentals', 'Network Administration', 'Computer Networking', 'Ethical Hacking Fundamentals', 'Network Security', 'Linux Administration', 'Security Monitoring', 'Vulnerability Assessment', 'Digital Security Awareness', 'Incident Response Fundamentals'],
     ['Students gain exposure to networking tools, security environments, practical laboratory exercises and defensive security techniques.',
      'Our training helps learners develop foundational competencies relevant to potential pathways such as IT support, network administration, cybersecurity analysis and security operations.'],
     'Explore Cybersecurity and Networking Courses'],
    ['School of Data Science and Analytics', 'Transform information into meaningful insights.',
     ['Data is an important resource for modern businesses, governments and organisations. The ability to collect, organise, analyse and interpret data is increasingly valuable across different industries.',
      'DAZU Training Institute offers data science and data analytics training that introduces learners to the tools and techniques used to understand information and support evidence-based decision-making.'],
     ['Data Science', 'Data Analytics', 'Business Intelligence', 'Microsoft Excel for Data Analysis', 'Power BI and Dashboard Development', 'Statistical Analysis', 'Data Visualisation', 'Python for Data Science', 'Database Fundamentals', 'Introduction to Machine Learning'],
     ['Through practical assignments and data projects, learners develop skills in data cleaning, analysis, reporting, visualisation and interpretation.',
      'These competencies are applicable in business, finance, marketing, healthcare, education, research and other data-driven industries.',
      'Potential career pathways include data analyst, reporting assistant, business intelligence assistant and junior data science roles, depending on experience and further qualifications.'],
     'Explore Data Science and Data Analytics Courses'],
    ['School of Artificial Intelligence and Python Programming', 'Understand AI. Learn to code. Create intelligent solutions.',
     ['Artificial Intelligence and programming are transforming how people work, communicate, develop products and solve problems.',
      'At DAZU, we introduce learners to practical programming and AI technologies that can be applied in software development, business automation, data analysis and digital innovation.'],
     ['Python Programming', 'Artificial Intelligence Fundamentals', 'Generative AI', 'Machine Learning', 'AI Automation', 'AI Agents and Workflows', 'Prompt Engineering', 'AI for Business', 'Python for Data Analysis', 'Introduction to AI Application Development'],
     ['Python programming provides learners with a foundation in logical thinking, algorithms, programming structures, functions, object-oriented programming and automation.',
      'Our AI programmes introduce learners to practical applications of artificial intelligence and responsible ways of integrating AI into professional and business workflows.',
      'Students are encouraged to experiment, build projects and develop solutions using modern programming and AI tools.'],
     'Explore AI and Python Programming Courses'],
    ['School of Healthcare and Caregiver Training', 'Develop the knowledge and compassion required to support others.',
     ['DAZU Training Institute recognises the importance of skilled, responsible and compassionate care professionals in healthcare and community support environments.',
      'Our caregiver training programme introduces learners to essential caregiving principles, personal support, communication and basic care practices.'],
     ['Introduction to Caregiving', 'Personal Hygiene and Grooming', 'Basic Patient Care', 'Elderly Care', 'Child and Family Care Fundamentals', 'Nutrition and Feeding Support', 'Infection Prevention and Control', 'Patient Safety', 'Communication and Emotional Support', 'Basic First Aid Awareness'],
     ['The programme is designed to help learners understand the responsibilities involved in providing respectful, safe and compassionate assistance to individuals who require support.',
      'Potential opportunities may exist in home-based care, elderly care, community support and other appropriate care settings, subject to the relevant qualifications and regulatory requirements.'],
     'Explore Caregiver Training'],
    ['School of Beauty and Business', 'Turn creativity and professional beauty skills into business opportunities.',
     ['The beauty industry offers opportunities for individuals interested in personal care, beauty services, customer experience and entrepreneurship.',
      'DAZU Training Institute combines beauty-related skills with business knowledge to help learners understand both the practical and commercial aspects of the beauty industry.'],
     ['Introduction to Beauty Therapy', 'Skincare and Facial Care', 'Beauty Treatment Fundamentals', 'Personal Grooming', 'Professional Client Consultation', 'Hygiene and Sanitation', 'Customer Service', 'Salon Operations and Management', 'Beauty Business Marketing', 'Entrepreneurship and Business Development'],
     ['Learners develop an understanding of beauty service delivery, customer relationships, business operations and the fundamentals of establishing and managing a beauty enterprise.',
      'The programme is suitable for aspiring beauty professionals, entrepreneurs and individuals interested in starting or growing businesses within the beauty and personal care industry.'],
     'Explore Beauty and Business Courses'],
    ['School of Business and Entrepreneurship', 'Develop the skills to manage, market and grow a business.',
     ['Business success requires more than an idea. It requires planning, financial awareness, communication, marketing and the ability to manage resources effectively.',
      'Our business-related training equips learners with practical knowledge and skills that can support entrepreneurship, professional development and business operations.'],
     ['Entrepreneurship and Business Development', 'Business Management Fundamentals', 'Digital Marketing', 'Social Media Management', 'Virtual Assistance', 'Customer Service', 'Business Communication', 'Digital Business Tools', 'Business Productivity and Automation', 'Introduction to Financial Management'],
     ['We encourage learners to understand how business principles work in real situations, from developing a business idea to managing customers, promoting products and improving operations.',
      'Our objective is to support individuals who want to establish businesses, improve their professional capabilities or contribute effectively to existing organisations.'],
     'Explore Business and Professional Skills Courses'],
];

$approach = [
    ['Practical and Laboratory-Based Learning', 'Learners engage with relevant software, tools, equipment and practical environments related to their chosen programmes.'],
    ['Project-Based Learning', 'Students undertake projects that allow them to apply concepts, develop solutions and demonstrate their abilities.'],
    ['Instructor-Led Training', 'Our learning sessions are guided by trainers and professionals with relevant technical and practical experience.'],
    ['Mentorship and Guidance', 'Learners receive guidance as they develop their skills, complete assignments and explore professional development opportunities.'],
    ['Portfolio and Skills Development', 'Where applicable, students build projects and practical work that can help demonstrate their competencies to prospective employers and clients.'],
    ['Flexible Learning Options', 'We provide physical and online learning options to accommodate learners with different schedules and geographical needs.'],
];

$joiners = [
    'A school leaver looking for practical professional skills.',
    'A student interested in technology or vocational training.',
    'A graduate seeking additional practical competencies.',
    'A professional looking to upgrade or change careers.',
    'An aspiring software developer, cybersecurity professional or data analyst.',
    'An individual interested in caregiving or beauty services.',
    'An entrepreneur seeking business and digital marketing skills.',
    'A business looking to improve employee capabilities.',
];

$locations = [
    ['Nairobi Campus', 'Cianda House, 4th Floor, Room 406B, Koinange Street, Nairobi.', 'Our Nairobi location serves learners seeking accessible professional and technology training within the city.'],
    ['Thika Campus', 'Witeithie House, 5th Floor, Room 529, Thika Town.', 'Our Thika campus provides learners with access to physical training and practical learning opportunities.'],
    ['Online Learning', 'Learn from anywhere.', 'For learners outside Nairobi and Thika, we offer online learning options for eligible programmes, providing an opportunity to develop professional and digital skills remotely.'],
];

$values = [
    ['Practical Excellence', 'We emphasise the application of knowledge and the development of demonstrable skills.'],
    ['Innovation', 'We encourage creativity, experimentation and the development of solutions to real-world challenges.'],
    ['Accessibility', 'We strive to make relevant skills training accessible through different learning options.'],
    ['Integrity', 'We value honesty, responsibility and professionalism in our training and institutional relationships.'],
    ['Continuous Learning', 'We encourage learners and trainers to adapt, improve and remain curious.'],
    ['Empowerment', 'We believe education should create opportunities for personal, professional and entrepreneurial growth.'],
];

$projectsList = [
    'Websites, web applications and business management systems.',
    'Python applications and automation scripts.',
    'Data analysis reports and interactive dashboards.',
    'Cybersecurity and networking laboratory exercises.',
    'AI-powered applications and automation workflows.',
    'Graphic design and digital marketing projects.',
    'Business plans and entrepreneurship projects.',
];

$whyChoose = [
    ['Multiple Training Disciplines', 'Explore technology, healthcare, beauty, business and professional skills programmes within one institution.'],
    ['Practical Learning', 'Develop competencies through exercises, assignments and relevant projects.'],
    ['Flexible Study Options', 'Access available programmes through our Nairobi and Thika training locations or online.'],
    ['Career-Oriented Skills', 'Build competencies that can support employment applications, freelancing, entrepreneurship and professional growth.'],
    ['Mentorship and Trainer Support', 'Learn with guidance from trainers and professionals with relevant experience.'],
    ['Student Development', 'Build your knowledge, confidence and practical abilities through structured learning.'],
];

$faqs = [
    ['q' => 'What is DAZU Training Institute?', 'a' => 'DAZU Training Institute is a multidisciplinary training institution in Kenya offering technology, digital skills, healthcare, beauty and business-related training programmes.'],
    ['q' => 'Where is DAZU Training Institute located?', 'a' => 'We have training locations in Nairobi at Cianda House, 4th Floor, Room 406B, Koinange Street, and in Thika at Witeithie House, 5th Floor, Room 529.'],
    ['q' => 'Does DAZU offer Software Engineering courses in Kenya?', 'a' => 'Yes. We offer software engineering and development programmes covering programming, web development, databases, application development and related technologies.'],
    ['q' => 'Can I study Cybersecurity at DAZU?', 'a' => 'Yes. Our cybersecurity and networking programmes introduce learners to network security, cybersecurity fundamentals, ethical hacking concepts and practical security exercises.'],
    ['q' => 'Does DAZU offer Data Science and Data Analytics training?', 'a' => 'Yes. Our data programmes cover data analysis, data visualisation, Excel, Power BI, Python for data analysis and foundational data science concepts.'],
    ['q' => 'Can beginners learn Python Programming?', 'a' => 'Yes. Our Python programming training introduces learners to programming fundamentals, functions, object-oriented programming and automation.'],
    ['q' => 'Does DAZU offer caregiver training?', 'a' => 'Yes. Our caregiver programme introduces learners to personal care, hygiene, safety, communication, nutrition and compassionate care practices.'],
    ['q' => 'Does DAZU offer Beauty and Business training?', 'a' => 'Yes. Our Beauty and Business programme combines beauty service fundamentals, customer care, salon management, marketing and entrepreneurship.'],
    ['q' => 'Are classes conducted physically or online?', 'a' => 'We offer physical training options in Nairobi and Thika, as well as online learning opportunities for eligible programmes.'],
    ['q' => 'Do students receive certificates?', 'a' => 'Students who successfully complete their programme requirements receive the relevant DAZU Training Institute certificate. Any additional external recognition or accreditation depends on the specific programme and applicable arrangements.'],
    ['q' => 'Can I pay my tuition fees in instalments?', 'a' => 'Available payment plans vary by programme. Contact our admissions team for the current fees and payment arrangements.'],
    ['q' => 'How do I apply for admission?', 'a' => 'Visit our website at https://dazu.ac.ke/courses to explore available programmes, or apply directly through https://dazu.ac.ke/apply. You can also contact our admissions team by phone or WhatsApp.'],
];

// Google: these questions and answers as FAQ data.
$extra_head = ($extra_head ?? '') . '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org', '@type' => 'FAQPage',
    'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faqs),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';

$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$linkify = fn($t) => preg_replace('~https?://[^\s<]+[^\s<.,]~', '<a href="$0" class="ab-link">$0</a>', $h($t));

ob_start();
?>

<style>
/* About page: own styles (the site's built CSS only has the classes other pages use). */
.ab-wrap{max-width:1280px;margin:0 auto;padding:0 16px}@media(min-width:768px){.ab-wrap{padding:0 32px}}@media(min-width:1024px){.ab-wrap{padding:0 48px}}
.ab-sec{padding:72px 0}.ab-alt{background:#f8fafc;border-top:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9}
.ab-eye{color:#15A24A;font-size:12px;letter-spacing:.15em;text-transform:uppercase;font-weight:800}
.ab-h2{font-size:clamp(1.6rem,3vw,2.25rem);font-weight:800;color:#020617;letter-spacing:-.01em;line-height:1.2;margin:.6rem 0 1rem}
.ab-lead{color:#475569;line-height:1.75;font-weight:500;max-width:820px}.ab-lead p{margin:0 0 1rem}
.ab-g2,.ab-g3{display:grid;grid-template-columns:1fr;gap:20px}
@media(min-width:768px){.ab-g2{grid-template-columns:1fr 1fr}.ab-g3{grid-template-columns:1fr 1fr}}@media(min-width:1024px){.ab-g3{grid-template-columns:1fr 1fr 1fr}}
.ab-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px}
.ab-card h3{font-size:1rem;font-weight:800;color:#020617;margin:0 0 .5rem}.ab-card p{color:#475569;font-size:.92rem;line-height:1.65;margin:0}
.ab-num{display:inline-flex;width:36px;height:36px;border-radius:10px;background:#f0fdf4;color:#15A24A;font-weight:800;align-items:center;justify-content:center;margin-bottom:14px;font-size:.9rem}
.ab-btns{display:flex;flex-wrap:wrap;gap:12px;margin-top:8px}
.ab-btn{display:inline-block;padding:14px 22px;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;border-radius:12px;text-decoration:none;border:1px solid #e2e8f0;color:#0f172a;background:#fff}
.ab-btn:hover{background:#f8fafc}.ab-btn.pri{background:#15A24A;border-color:#15A24A;color:#fff}.ab-btn.pri:hover{background:#128a3e}
.ab-ticks{list-style:none;padding:0;margin:0;display:grid;gap:10px}.ab-ticks li{display:flex;gap:10px;color:#475569;line-height:1.6}.ab-ticks li:before{content:"✓";color:#15A24A;font-weight:800}
.ab-school{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:28px;margin-bottom:20px}
.ab-school .ab-tag{color:#15A24A;font-weight:700;margin:.2rem 0 1rem}
.ab-school p{color:#475569;line-height:1.7;margin:0 0 .8rem}
.ab-chips{display:flex;flex-wrap:wrap;gap:8px;margin:.4rem 0 1.2rem}.ab-chips span{font-size:.82rem;padding:6px 12px;border-radius:999px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-weight:600}
.ab-more{color:#15A24A;font-weight:800;text-decoration:none;font-size:.9rem}.ab-more:hover{text-decoration:underline}
.ab-link{color:#15A24A;text-decoration:underline}
.ab-fold{position:relative;overflow:hidden;transition:max-height .45s ease}
.ab-fold:not(.open){max-height:var(--peek,7.5rem)}
.ab-fold:not(.open):after{content:"";position:absolute;left:0;right:0;bottom:0;height:5rem;background:linear-gradient(rgba(255,255,255,0),#fff);pointer-events:none}
.ab-readmore{background:none;border:0;padding:6px 0;color:#15A24A;font-weight:800;font-size:.9rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.ab-readmore:hover{text-decoration:underline}
.ab-cta{background:linear-gradient(135deg,#020617,#0f172a 60%,#03341d);color:#fff;text-align:center}
.ab-cta .ab-h2{color:#fff}.ab-cta p{color:#cbd5e1;max-width:720px;margin:0 auto 1rem;line-height:1.7}
.ab-cta .ab-btns{justify-content:center}.ab-cta .ab-btn{background:transparent;color:#fff;border-color:rgba(255,255,255,.5)}.ab-cta .ab-btn.pri{background:#15A24A;border-color:#15A24A}
.ab-contact{margin-top:28px;font-size:.92rem;color:#e2e8f0;line-height:1.9}.ab-contact a{color:#86efac;text-decoration:none}
</style>

<!-- Hero -->
<section class="ab-sec" style="padding-top:64px">
  <div class="ab-wrap">
    <span class="inline-block text-[#15A24A] text-xs uppercase tracking-widest font-extrabold px-3 py-1.5 bg-green-50 border border-green-200 rounded-full">About DAZU Training Institute</span>
    <h1 class="ab-h2" style="font-size:clamp(2rem,4.5vw,3.25rem);margin-top:1rem">Building Skills. Empowering Careers. Driving Innovation.</h1>
    <div style="font-weight:700;color:#0f172a;margin-bottom:1.2rem">Practical Technology, Professional and Vocational Skills Training in Kenya</div>
    <div class="ab-lead">
      <p>DAZU Training Institute is a multidisciplinary technology, professional and vocational training institution in Kenya, serving students and professionals in Nairobi, Thika and across Africa through flexible online learning.</p>
      <div class="ab-fold" style="--peek:4.5rem">
      <p>We are dedicated to equipping individuals, graduates, entrepreneurs and organisations with practical, relevant and future-focused skills that respond to the changing demands of the workplace and the global economy.</p>
      <p>From Software Engineering, Cybersecurity, Data Science and Artificial Intelligence to Python Programming, Healthcare, Beauty and Business Training, our programmes are designed to help learners develop practical competencies, solve real-world problems and pursue meaningful professional opportunities.</p>
      <p>At DAZU, we believe education should go beyond theoretical knowledge. Our training approach combines instructor-led learning, practical exercises, technology laboratories, real-world projects and mentorship to help students build confidence and demonstrate what they can do.</p>
      <p>Whether you are starting your first career, upgrading your professional skills, changing careers, starting a business or exploring opportunities in technology, DAZU Training Institute provides a learning environment designed to support your growth.</p>
      </div>
      <button type="button" class="ab-readmore" aria-expanded="false">Read more <span>▾</span></button>
      <p style="font-weight:700;color:#0f172a;margin-top:1rem">Explore your potential. Develop practical skills. Build your future with DAZU.</p>
    </div>
    <div class="ab-btns">
      <a href="<?php echo url('courses'); ?>" class="ab-btn pri">Explore Our Courses</a>
      <a href="<?php echo url('apply'); ?>" class="ab-btn">Apply for Admission</a>
      <a href="<?php echo $whatsapp; ?>" target="_blank" rel="noopener" class="ab-btn">Talk to Our Admissions Team</a>
    </div>
  </div>
</section>

<!-- Who we are -->
<section class="ab-sec ab-alt">
  <div class="ab-wrap">
    <span class="ab-eye">Who We Are</span>
    <h2 class="ab-h2">A Training Institution Focused on Practical Skills and Professional Development</h2>
    <div class="ab-lead">
      <p>DAZU Training Institute was established with a commitment to making quality, practical and accessible skills training available to individuals seeking personal, professional and career development.</p>
      <p>We recognise that traditional academic knowledge alone may not always provide the practical competencies required in today's competitive job market. That is why our programmes emphasise applied learning, technical competence, problem-solving, creativity and professional development.</p>
      <p>Our institution brings together technology education, digital skills development, healthcare training, beauty education and business skills under one learning environment.</p>
    </div>
    <h3 style="font-weight:800;color:#020617;margin:1.5rem 0 1rem">We serve:</h3>
    <div class="ab-g3">
      <?php foreach ($serve as $s): ?><div class="ab-card"><h3><?php echo $h($s[0]); ?></h3><p><?php echo $h(ucfirst($s[1])); ?></p></div><?php endforeach; ?>
    </div>
    <p class="ab-lead" style="margin-top:1.5rem">Our focus is simple: equip learners with knowledge they can apply, practical experience they can demonstrate and skills they can continue developing throughout their careers.</p>
  </div>
</section>

<!-- Schools -->
<section class="ab-sec">
  <div class="ab-wrap">
    <span class="ab-eye">Our Schools</span>
    <h2 class="ab-h2">Our Schools and Areas of Specialisation</h2>
    <div style="margin-top:28px">
      <?php foreach ($schools as $n => $s): ?>
        <article class="ab-school" id="school-<?php echo $n + 1; ?>">
          <h3 style="font-size:1.3rem;font-weight:800;color:#020617;margin:0"><?php echo ($n + 1) . '. ' . $h($s[0]); ?></h3>
          <div class="ab-tag"><?php echo $h($s[1]); ?></div>
          <p><?php echo $h($s[2][0]); ?></p>
          <div class="ab-fold" style="--peek:3.2rem">
          <?php foreach (array_slice($s[2], 1) as $p): ?><p><?php echo $h($p); ?></p><?php endforeach; ?>
          <div style="font-weight:700;color:#0f172a;margin-top:.6rem"><?php echo $n === 5 ? 'Our Beauty and Business training covers areas such as:' : ($n === 6 ? 'Relevant training areas include:' : 'Training areas include:'); ?></div>
          <div class="ab-chips"><?php foreach ($s[3] as $a): ?><span><?php echo $h($a); ?></span><?php endforeach; ?></div>
          <?php foreach ($s[4] as $p): ?><p><?php echo $h($p); ?></p><?php endforeach; ?>
          <a href="<?php echo url('courses'); ?>" class="ab-more"><?php echo $h($s[5]); ?> →</a>
          </div>
          <button type="button" class="ab-readmore" aria-expanded="false">Read more <span>▾</span></button>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Training approach -->
<section class="ab-sec ab-alt">
  <div class="ab-wrap">
    <span class="ab-eye">Our Training Approach</span>
    <h2 class="ab-h2">Learning That Goes Beyond the Classroom</h2>
    <div class="ab-lead"><p>At DAZU Training Institute, practical application is central to our approach to education.</p>
      <p>We believe learners should have opportunities to practise what they study, work through challenges and demonstrate their understanding through practical assignments.</p>
      <p style="font-weight:700;color:#0f172a">Our learning approach includes:</p></div>
    <div class="ab-g3">
      <?php foreach ($approach as $i => $a): ?><div class="ab-card"><span class="ab-num"><?php echo str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT); ?></span><h3><?php echo $h($a[0]); ?></h3><p><?php echo $h($a[1]); ?></p></div><?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Who can join -->
<section class="ab-sec">
  <div class="ab-wrap ab-g2" style="align-items:start;gap:40px">
    <div>
      <span class="ab-eye">Admissions</span>
      <h2 class="ab-h2">Who Can Join DAZU Training Institute?</h2>
      <div class="ab-lead"><p>Our programmes are designed for different educational backgrounds, professional goals and levels of experience.</p>
        <p>Whether you are a beginner or looking to advance your existing skills, you can explore programmes that match your interests and objectives.</p>
        <p>Entry requirements vary depending on the course. Some beginner programmes require no previous technical experience, while advanced programmes may require foundational knowledge.</p></div>
    </div>
    <div class="ab-card"><h3 style="margin-bottom:1rem">You can join DAZU if you are:</h3>
      <ul class="ab-ticks"><?php foreach ($joiners as $j): ?><li><?php echo $h($j); ?></li><?php endforeach; ?></ul></div>
  </div>
</section>

<!-- Locations -->
<section class="ab-sec ab-alt">
  <div class="ab-wrap">
    <span class="ab-eye">Study With DAZU in Nairobi, Thika or Online</span>
    <h2 class="ab-h2">Accessible Skills Training in Kenya</h2>
    <p class="ab-lead">DAZU Training Institute serves learners through physical training locations and online learning opportunities.</p>
    <div class="ab-g3" style="margin-top:24px">
      <?php foreach ($locations as $l): ?><div class="ab-card"><h3><?php echo $h($l[0]); ?></h3><p style="font-weight:700;color:#0f172a;margin-bottom:.5rem"><?php echo $h($l[1]); ?></p><p><?php echo $h($l[2]); ?></p></div><?php endforeach; ?>
    </div>
    <p class="ab-lead" style="margin-top:1.5rem">Whether you prefer classroom learning or online study, you can explore available courses and choose a suitable learning arrangement.</p>
  </div>
</section>

<!-- Vision, mission, core values -->
<section class="ab-sec">
  <div class="ab-wrap">
    <span class="ab-eye">Who We Are</span>
    <h2 class="ab-h2">Our Vision, Mission and Core Values</h2>
    <div class="ab-g2" style="margin-top:24px">
      <div class="ab-card"><h3>Our Vision</h3><p>To become a leading African centre for technology education, innovation, professional development and practical skills training, empowering individuals to contribute meaningfully to a rapidly evolving world.</p></div>
      <div class="ab-card"><h3>Our Mission</h3><p>To equip individuals and organisations with relevant, practical and future-focused skills through accessible education, hands-on training, mentorship and continuous learning.</p></div>
    </div>
    <h3 style="font-weight:800;color:#020617;margin:2rem 0 1rem">Our Core Values</h3>
    <div class="ab-g3">
      <?php foreach ($values as $v): ?><div class="ab-card"><h3><?php echo $h($v[0]); ?></h3><p><?php echo $h($v[1]); ?></p></div><?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Gallery -->
<section class="bg-white py-20">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="max-w-2xl mb-12">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Life at Dazu Training Institute</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 mt-3 uppercase tracking-tight">Our Gallery</h2>
      <p class="text-slate-600 font-medium mt-4">A look inside our labs, classes, and student milestones across our Nairobi, Thika, and online learning community.</p>
    </div>

    <?php if (empty($galleryItems)): ?>
      <p class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-slate-600">Gallery photos and videos will be available soon.</p>
    <?php else: ?>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php foreach ($galleryItems as $g):
            $isVideo = ($g['media_type'] ?? 'image') === 'video';
            $thumb = $g['thumbnail_path'] ?: $g['file_path'];
            if ($thumb && !preg_match('#^https?://#i', $thumb)) $thumb = url(ltrim($thumb, '/'));
        ?>
          <button type="button" class="dazu-about-gallery-open group relative aspect-square overflow-hidden rounded-2xl border border-slate-200 bg-slate-50"
                  data-title="<?php echo htmlspecialchars($g['title'], ENT_QUOTES); ?>"
                  data-desc="<?php echo htmlspecialchars($g['description'] ?? '', ENT_QUOTES); ?>"
                  data-video="<?php echo $isVideo ? '1' : '0'; ?>"
                  data-src="<?php echo htmlspecialchars($isVideo ? ($g['video_url'] ?? $g['file_path'] ?? '') : ($g['file_path'] ?? ''), ENT_QUOTES); ?>">
            <?php if ($thumb): ?>
              <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($g['title']); ?>" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
            <?php else: ?>
              <div class="w-full h-full flex items-center justify-center text-slate-300"><i class="fas fa-image text-3xl"></i></div>
            <?php endif; ?>
            <span class="absolute inset-0 bg-slate-900/0 group-hover:bg-slate-900/50 transition-colors flex items-center justify-center">
              <i class="fas <?php echo $isVideo ? 'fa-play' : 'fa-search-plus'; ?> text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
            </span>
            <?php if ($isVideo): ?>
              <span class="absolute top-2 right-2 w-7 h-7 rounded-full bg-slate-900/70 text-white flex items-center justify-center text-[10px]"><i class="fas fa-play"></i></span>
            <?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Gallery lightbox -->
<div id="dazu-about-gallery-lightbox" class="fixed inset-0 bg-slate-950/90 hidden items-center justify-center z-50 p-4">
  <button type="button" id="dazu-about-gallery-close" class="absolute top-5 right-5 text-white/80 hover:text-white text-2xl" aria-label="Close">
    <i class="fas fa-times"></i>
  </button>
  <div class="max-w-3xl w-full">
    <div id="dazu-about-gallery-media" class="w-full"></div>
    <div class="mt-3 text-center">
      <h3 id="dazu-about-gallery-title" class="text-white font-bold"></h3>
      <p id="dazu-about-gallery-desc" class="text-slate-300 text-sm mt-1"></p>
    </div>
  </div>
</div>
<script>
(function(){
  var lb = document.getElementById('dazu-about-gallery-lightbox');
  var media = document.getElementById('dazu-about-gallery-media');
  var titleEl = document.getElementById('dazu-about-gallery-title');
  var descEl = document.getElementById('dazu-about-gallery-desc');

  function youtubeId(url) {
    var m = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
    return m ? m[1] : '';
  }

  document.querySelectorAll('.dazu-about-gallery-open').forEach(function(btn){
    btn.addEventListener('click', function(){
      var src = btn.getAttribute('data-src');
      var isVideo = btn.getAttribute('data-video') === '1';
      titleEl.textContent = btn.getAttribute('data-title') || '';
      descEl.textContent = btn.getAttribute('data-desc') || '';
      media.innerHTML = '';
      if (isVideo && /youtube\.com|youtu\.be/.test(src)) {
        media.innerHTML = '<div class="aspect-video rounded-xl overflow-hidden"><iframe class="w-full h-full" src="https://www.youtube.com/embed/' + youtubeId(src) + '" allowfullscreen title="Video"></iframe></div>';
      } else if (isVideo) {
        media.innerHTML = '<video controls class="w-full rounded-xl max-h-[70vh]"><source src="' + src + '" type="video/mp4"></video>';
      } else {
        media.innerHTML = '<img src="' + src + '" alt="" class="w-full rounded-xl max-h-[70vh] object-contain mx-auto">';
      }
      lb.classList.remove('hidden'); lb.classList.add('flex');
    });
  });
  function closeLb(){ lb.classList.add('hidden'); lb.classList.remove('flex'); media.innerHTML=''; }
  document.getElementById('dazu-about-gallery-close').addEventListener('click', closeLb);
  lb.addEventListener('click', function(e){ if (e.target === lb) closeLb(); });
})();
</script>

<!-- Team -->
<?php
// Social icon links for a team member — only fields that are filled in are shown.
$teamIcons = [
  'phone'     => ['Call', '<path d="M6.6 10.8a15.1 15.1 0 006.6 6.6l2.2-2.2a1 1 0 011-.25 11.4 11.4 0 003.6.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1z"/>'],
  'email'     => ['Email', '<path d="M20 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2zm0 4l-8 5-8-5V6l8 5 8-5z"/>'],
  'whatsapp'  => ['WhatsApp', '<path d="M17.5 14.4c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.47-.88-.79-1.47-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.27.49 1.7.63.72.23 1.37.2 1.88.12.58-.09 1.75-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.15l-.3-.18-3 .78.8-2.9-.2-.3A8.2 8.2 0 1112 20.2z"/>'],
  'tiktok'    => ['TikTok', '<path d="M16.6 5.8A4.3 4.3 0 0115.5 3h-3.1v12.4a2.6 2.6 0 11-2.6-2.6c.27 0 .53.04.78.12V9.7a5.8 5.8 0 105 5.7V9.1a7.4 7.4 0 004.3 1.4V7.4a4.3 4.3 0 01-3.3-1.6z"/>'],
  'linkedin'  => ['LinkedIn', '<path d="M4.98 3.5a2.5 2.5 0 11-.02 5 2.5 2.5 0 01.02-5zM3 9h4v12H3zm7 0h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1V21h-4v-5.6c0-1.33-.02-3.05-1.86-3.05-1.86 0-2.15 1.45-2.15 2.95V21h-4z"/>'],
  'facebook'  => ['Facebook', '<path d="M22 12a10 10 0 10-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0022 12z"/>'],
  'instagram' => ['Instagram', '<path d="M12 7.3a4.7 4.7 0 100 9.4 4.7 4.7 0 000-9.4zm0 7.7a3 3 0 110-6 3 3 0 010 6zm6-7.9a1.1 1.1 0 11-2.2 0 1.1 1.1 0 012.2 0zM21.9 8.1c-.1-1.6-.4-3-1.6-4.2S17.7 2.2 16 2.1C14.4 2 9.6 2 8 2.1c-1.6.1-3 .4-4.2 1.6S2.2 6.3 2.1 8c-.1 1.6-.1 6.4 0 8 .1 1.6.4 3 1.6 4.2s2.6 1.5 4.2 1.6c1.6.1 6.4.1 8 0 1.6-.1 3-.4 4.2-1.6s1.5-2.6 1.6-4.2c.1-1.6.1-6.4 0-8zm-2 9.7a3 3 0 01-1.7 1.7c-1.2.5-4 .4-5.2.4s-4.1.1-5.2-.4a3 3 0 01-1.7-1.7c-.5-1.2-.4-4-.4-5.3s-.1-4.1.4-5.2a3 3 0 011.7-1.7C7.9 5.1 10.7 5.2 12 5.2s4.1-.1 5.2.4a3 3 0 011.7 1.7c.5 1.2.4 4 .4 5.2s.1 4.1-.4 5.3z"/>'],
];
$teamHref = static function (string $key, string $v): string {
  $v = trim($v);
  if ($key === 'phone') return 'tel:' . preg_replace('/[^\d+]/', '', $v);
  if ($key === 'email') return 'mailto:' . $v;
  if ($key === 'whatsapp') {
    if (preg_match('#^https?://#i', $v)) return $v;
    $d = preg_replace('/\D/', '', $v);
    if (str_starts_with($d, '0')) $d = '254' . substr($d, 1); // Kenyan local number → international
    return 'https://wa.me/' . $d;
  }
  return preg_match('#^https?://#i', $v) ? $v : 'https://' . ltrim($v, '/');
};
?>
<style>
.dz-team{background:#fff;padding:80px 16px 90px}
.dz-team *{box-sizing:border-box}
.dz-team-wrap{max-width:1120px;margin:0 auto}
.dz-team-title{text-align:center;font-size:clamp(28px,3.6vw,40px);line-height:1.2;font-weight:700;color:#1e293b;letter-spacing:-.02em;margin:0 auto 64px;max-width:520px;text-transform:none}
.dz-team-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:40px 14px}
@media(min-width:640px){.dz-team-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(min-width:960px){.dz-team-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:56px 10px}}
.dz-team-photo{position:relative;aspect-ratio:1/0.88;border-radius:14px;overflow:hidden;display:flex;align-items:flex-end;justify-content:center}
/* soft pastel backdrops, in the same order as the design */
.dz-team-card:nth-child(8n+1) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#f1fbe2 100%)}
.dz-team-card:nth-child(8n+2) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#e3d3fb 100%)}
.dz-team-card:nth-child(8n+3) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#fbd3e6 100%)}
.dz-team-card:nth-child(8n+4) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#d3e7fb 100%)}
.dz-team-card:nth-child(8n+5) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#dbefc8 100%)}
.dz-team-card:nth-child(8n+6) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#c9ece6 100%)}
.dz-team-card:nth-child(8n+7) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#cfdde8 100%)}
.dz-team-card:nth-child(8n+8) .dz-team-photo{background:linear-gradient(180deg,#fff 0%,#f6e6d6 100%)}
.dz-team-photo img{width:100%;height:100%;object-fit:cover;object-position:top center;display:block}
.dz-team-initial{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:64px;font-weight:700;color:#334155}
.dz-team-name{font-size:19px;line-height:1.3;font-weight:600;color:#0f172a;margin:18px 0 6px;text-transform:none;letter-spacing:-.01em}
.dz-team-role{display:block;font-size:14px;color:#64748b}
.dz-team-empty{border:1px dashed #cbd5e1;border-radius:14px;padding:40px;text-align:center;color:#64748b;grid-column:1/-1}
</style>
<section class="dz-team" id="team">
  <svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs><linearGradient id="dz-ig-grad" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#FEDA75"/><stop offset=".3" stop-color="#FA7E1E"/><stop offset=".6" stop-color="#D62976"/><stop offset=".8" stop-color="#962FBF"/><stop offset="1" stop-color="#4F5BD5"/></linearGradient></defs></svg>
  <div class="dz-team-wrap">
    <h2 class="dz-team-title">A Team That Never Says Impossible</h2>
    <div class="dz-team-grid">
      <?php if (!$teamMembers): ?>
        <div class="dz-team-empty">Our team profiles will be available soon.</div>
      <?php endif; ?>
      <?php foreach ($teamMembers as $t): ?>
        <article class="dz-team-card">
          <div class="dz-team-photo">
            <?php if (!empty($t['photo_path'])): ?>
              <img src="<?php echo htmlspecialchars(url($t['photo_path']), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>" loading="lazy">
            <?php else: ?>
              <div class="dz-team-initial"><?php echo htmlspecialchars(strtoupper(substr($t['name'], 0, 1))); ?></div>
            <?php endif; ?>
          </div>
          <h3 class="dz-team-name"><?php $tn = trim((string) $t['name']); echo htmlspecialchars($tn === mb_strtoupper($tn) ? mb_convert_case(mb_strtolower($tn), MB_CASE_TITLE) : $tn); ?></h3>
          <span class="dz-team-role"><?php echo htmlspecialchars($t['role']); ?></span>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Student projects -->
<section class="ab-sec ab-alt">
  <div class="ab-wrap ab-g2" style="align-items:start;gap:40px">
    <div>
      <span class="ab-eye">Student Projects and Practical Learning</span>
      <h2 class="ab-h2">We Believe in Skills You Can Demonstrate.</h2>
      <div class="ab-lead"><p>At DAZU, we encourage learners to turn knowledge into practical work.</p>
        <p>Our project-based learning approach gives students opportunities to develop and demonstrate skills through relevant assignments and projects.</p>
        <p>We believe practical work helps learners develop problem-solving abilities, technical confidence and a stronger understanding of their chosen fields.</p></div>
      <a href="<?php echo url('projects'); ?>" class="ab-more">Explore our Student Projects →</a>
    </div>
    <div class="ab-card"><h3 style="margin-bottom:1rem">Depending on their programmes, learners may work on:</h3>
      <ul class="ab-ticks"><?php foreach ($projectsList as $p): ?><li><?php echo $h($p); ?></li><?php endforeach; ?></ul></div>
  </div>
</section>

<!-- Why choose -->
<section class="ab-sec">
  <div class="ab-wrap">
    <span class="ab-eye">Why DAZU</span>
    <h2 class="ab-h2">Why Choose DAZU Training Institute?</h2>
    <p class="ab-lead">Choosing a training institution is an important decision. At DAZU, we focus on providing a learning experience that connects education with practical application.</p>
    <div class="ab-g3" style="margin-top:24px">
      <?php foreach ($whyChoose as $i => $w): ?><div class="ab-card"><span class="ab-num"><?php echo str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT); ?></span><h3><?php echo $h($w[0]); ?></h3><p><?php echo $h($w[1]); ?></p></div><?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="bg-slate-50 border-t border-slate-100 py-20">
  <div class="max-w-[800px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="text-center mb-12">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Got Questions?</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 mt-3 uppercase tracking-tight">Frequently Asked Questions About DAZU Training Institute</h2>
      <p class="text-slate-600 font-medium mt-4">Answers to common questions about our programmes, locations, certificates, fees and admission.</p>
    </div>

    <div class="space-y-3" id="dazu-faq">
      <?php foreach ($faqs as $i => $faq): ?>
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <button type="button"
                  class="dazu-faq-toggle w-full flex items-center justify-between gap-4 px-5 py-4 text-left font-bold text-slate-900 hover:bg-slate-50 transition-colors"
                  aria-expanded="false"
                  data-faq="<?php echo $i; ?>">
            <span class="text-sm md:text-base"><?php echo htmlspecialchars($faq['q']); ?></span>
            <span class="dazu-faq-icon shrink-0 text-[#15A24A] text-xl font-light leading-none">+</span>
          </button>
          <div class="dazu-faq-panel hidden px-5 pb-5 text-slate-600 text-sm leading-relaxed border-t border-slate-100 pt-4">
            <?php echo $linkify($faq['a']); ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- Call to action -->
<section class="ab-sec ab-cta">
  <div class="ab-wrap">
    <span class="ab-eye" style="color:#86efac">Start Your Learning Journey With DAZU</span>
    <h2 class="ab-h2">Your Skills. Your Future. Your Opportunity.</h2>
    <p>The future belongs to people who continue learning, adapting and developing relevant skills.</p>
    <p>Whether you want to become a software engineer, cybersecurity professional, data analyst, Python programmer, caregiver, beauty professional or entrepreneur, DAZU Training Institute provides programmes designed to support your learning and professional development.</p>
    <p>Take the next step towards building your skills and exploring new opportunities.</p>
    <p style="color:#fff;font-weight:700">Join DAZU Training Institute and invest in your future.</p>
    <div class="ab-btns">
      <a href="<?php echo url('courses'); ?>" class="ab-btn pri">Explore Courses</a>
      <a href="<?php echo url('apply'); ?>" class="ab-btn">Apply for Admission</a>
    </div>
    <div class="ab-contact">
      Call / WhatsApp: <a href="tel:+254722922454">+254 722 922 454</a><br>
      <strong style="color:#fff">DAZU Training Institute</strong> · Nairobi | Thika | Online<br>
      Website: <a href="https://dazu.ac.ke">https://dazu.ac.ke</a> · Email: <a href="mailto:admin@dazu.ac.ke">admin@dazu.ac.ke</a>
    </div>
  </div>
</section>

<?php
$extra_js = <<<'HTML'
<script>
document.querySelectorAll('.ab-readmore').forEach(function (b) {
  var box = b.previousElementSibling;
  b.addEventListener('click', function () {
    var open = box.classList.toggle('open');
    if (open) { box.style.maxHeight = box.scrollHeight + 'px'; setTimeout(function () { if (box.classList.contains('open')) box.style.maxHeight = 'none'; }, 460); }
    else { box.style.maxHeight = box.scrollHeight + 'px'; requestAnimationFrame(function () { box.style.maxHeight = ''; }); box.closest('article,section').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    b.setAttribute('aria-expanded', open);
    b.innerHTML = open ? 'Read less <span>▴</span>' : 'Read more <span>▾</span>';
  });
});
(function(){
  document.querySelectorAll('.dazu-faq-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
      var panel = btn.nextElementSibling;
      var icon = btn.querySelector('.dazu-faq-icon');
      var open = !panel.classList.contains('hidden');
      document.querySelectorAll('.dazu-faq-panel').forEach(function(p){ p.classList.add('hidden'); });
      document.querySelectorAll('.dazu-faq-toggle').forEach(function(b){
        b.setAttribute('aria-expanded', 'false');
        var ic = b.querySelector('.dazu-faq-icon');
        if (ic) ic.textContent = '+';
      });
      if (!open) {
        panel.classList.remove('hidden');
        btn.setAttribute('aria-expanded', 'true');
        if (icon) icon.textContent = '−';
      }
    });
  });
})();
</script>
HTML;

$content = ob_get_clean();

if (empty($og_image) && !empty($teamMembers)) {
    foreach ($teamMembers as $t) {
        if (!empty($t['photo_path'])) {
            $og_image = url($t['photo_path']);
            break;
        }
    }
}
if (empty($og_image) && !empty($galleryItems)) {
    foreach ($galleryItems as $g) {
        if (!empty($g['file_path'])) {
            $og_image = $g['file_path'];
            break;
        }
    }
}

require __DIR__ . '/../templates/public/dazu_layout.php';
