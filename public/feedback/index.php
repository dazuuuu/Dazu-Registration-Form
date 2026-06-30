<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/controllers/TestimonialController.php';

$testimonialController = new TestimonialController($pdo);
$page_title = 'Leave Feedback - ISMAN Company';
$page_description = 'Share your experience with ISMAN Company';

// Auto-fill customer name from session if logged in
$customerName = '';
$customerEmail = '';
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $customerName = $user['username'];
            $customerEmail = $user['email'];
        }
    } catch (Exception $e) {
        // Ignore - use empty values
    }
}

// Handle form submission
$formSubmitted = false;
$formMessage = '';
$formSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'customer_name' => $_POST['customer_name'] ?? '',
        'customer_email' => $_POST['customer_email'] ?? '',
        'customer_phone' => $_POST['customer_phone'] ?? '',
        'rating' => (int)($_POST['rating'] ?? 5),
        'testimonial_text' => $_POST['testimonial_text'] ?? '',
        'service_tag' => $_POST['service_tag'] ?? '',
        'role' => $_POST['role'] ?? ''
    ];
    
    $result = $testimonialController->submit($data);
    $formSubmitted = true;
    $formSuccess = $result['success'];
    $formMessage = $result['message'];
    
    if ($formSuccess) {
        // Clear form data
        $customerName = '';
        $customerEmail = '';
    }
}

$services = [
    'Commercial Kitchen', 'Stainless Railing', 'Hospital Fit-out', 
    'Food Processing', 'HVAC Systems', 'Welding & Fabrication', 
    'Plumbing', 'Other'
];

ob_start();
?>

<style>
.feedback-section {
    padding: 60px 0;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: calc(100vh - 200px);
}
.feedback-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.1);
    overflow: hidden;
    max-width: 800px;
    margin: 0 auto;
}
.feedback-header {
    background: linear-gradient(135deg, #0D9488 0%, #0A766B 100%);
    color: white;
    padding: 40px;
    text-align: center;
}
.feedback-header h1 {
    color: white;
    margin-bottom: 10px;
}
.feedback-header p {
    color: rgba(255,255,255,0.9);
    margin-bottom: 0;
}
.feedback-body {
    padding: 40px;
}
.rating-stars {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 20px;
}
.rating-stars i {
    font-size: 32px;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #ddd;
}
.rating-stars i:hover,
.rating-stars i.active {
    color: #f39c12;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    font-weight: 600;
    margin-bottom: 8px;
    display: block;
    color: #333;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 16px;
    transition: border-color 0.2s ease;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #0D9488;
}
.btn-submit {
    width: 100%;
    padding: 14px;
    background: #0D9488;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-submit:hover {
    background: #0A766B;
    transform: translateY(-2px);
}
.alert-message {
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.info-note {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #0066c0;
}
.info-note i {
    margin-right: 8px;
}
</style>

<section class="feedback-section">
    <div class="container">
        <div class="feedback-card">
            <div class="feedback-header">
                <h1><i class="fas fa-star"></i> Share Your Experience</h1>
                <p>Your feedback helps us improve and serve you better</p>
            </div>
            <div class="feedback-body">
                <?php if ($formSubmitted): ?>
                    <div class="alert-message <?php echo $formSuccess ? 'alert-success' : 'alert-error'; ?>">
                        <i class="fas fa-<?php echo $formSuccess ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($formMessage); ?>
                    </div>
                <?php endif; ?>
                
                <div class="info-note">
                    <i class="fas fa-info-circle"></i>
                    Your feedback will be reviewed before being published on our website.
                </div>
                
                <form method="POST" id="feedbackForm">
                    <div class="rating-stars" id="ratingStars">
                        <i class="fas fa-star" data-rating="1"></i>
                        <i class="fas fa-star" data-rating="2"></i>
                        <i class="fas fa-star" data-rating="3"></i>
                        <i class="fas fa-star" data-rating="4"></i>
                        <i class="fas fa-star" data-rating="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingValue" value="5">
                    
                    <div class="form-group">
                        <label>Your Name *</label>
                        <input type="text" name="customer_name" required 
                               value="<?php echo htmlspecialchars($customerName); ?>"
                               placeholder="Enter your full name">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email (optional)</label>
                                <input type="email" name="customer_email" 
                                       value="<?php echo htmlspecialchars($customerEmail); ?>"
                                       placeholder="your@email.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone (optional)</label>
                                <input type="tel" name="customer_phone" 
                                       placeholder="07XX XXX XXX">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Service Used</label>
                                <select name="service_tag">
                                    <option value="">Select a service</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo htmlspecialchars($service); ?>">
                                            <?php echo htmlspecialchars($service); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Your Role / Title</label>
                                <input type="text" name="role" 
                                       placeholder="e.g., General Manager, ABC Company">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Your Feedback *</label>
                        <textarea name="testimonial_text" rows="5" required 
                                  placeholder="Tell us about your experience with ISMAN Company..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Feedback
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
// Rating stars functionality
const stars = document.querySelectorAll('#ratingStars i');
const ratingInput = document.getElementById('ratingValue');

function setRating(rating) {
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
    ratingInput.value = rating;
}

stars.forEach(star => {
    star.addEventListener('click', function() {
        const rating = parseInt(this.dataset.rating);
        setRating(rating);
    });
    
    star.addEventListener('mouseenter', function() {
        const rating = parseInt(this.dataset.rating);
        stars.forEach((s, i) => {
            if (i < rating) {
                s.style.color = '#f39c12';
            } else {
                s.style.color = '#ddd';
            }
        });
    });
});

document.getElementById('ratingStars')?.addEventListener('mouseleave', function() {
    const currentRating = parseInt(ratingInput.value);
    setRating(currentRating);
});

// Set default rating (5 stars)
setRating(5);
</script>

<?php
$content = ob_get_clean();
$use_home_navbar = false;
require_once __DIR__ . '/../templates/public/layout.php';
?>