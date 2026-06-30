<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/TestimonialController.php';

$testimonialController = new TestimonialController($pdo);
$page_title = 'Edit Testimonial';
$breadcrumbs = [
    ['label' => 'Testimonials', 'url' => 'index.php'],
    ['label' => 'Edit', 'active' => true]
];

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit();
}

$testimonial = $testimonialController->getById($id);
if (!$testimonial) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'customer_name' => $_POST['customer_name'],
        'customer_email' => $_POST['customer_email'],
        'customer_phone' => $_POST['customer_phone'],
        'customer_initial' => strtoupper(substr($_POST['customer_name'], 0, 1)),
        'rating' => (int)$_POST['rating'],
        'testimonial_text' => $_POST['testimonial_text'],
        'service_tag' => $_POST['service_tag'],
        'role' => $_POST['role'],
        'sort_order' => (int)$_POST['sort_order']
    ];
    
    $result = $testimonialController->update($id, $data);
    $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
    
    if ($result['success']) {
        header('Location: index.php');
        exit();
    }
}

$services = [
    'Commercial Kitchen', 'Stainless Railing', 'Hospital Fit-out', 
    'Food Processing', 'HVAC Systems', 'Welding & Fabrication', 
    'Plumbing', 'Other'
];

ob_start();
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Edit Testimonial</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" name="customer_name" class="form-control" 
                               value="<?php echo htmlspecialchars($testimonial['customer_name']); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Rating *</label>
                        <select name="rating" class="form-select">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo $testimonial['rating'] == $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="customer_email" class="form-control" 
                               value="<?php echo htmlspecialchars($testimonial['customer_email']); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="customer_phone" class="form-control" 
                               value="<?php echo htmlspecialchars($testimonial['customer_phone']); ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Service Tag</label>
                        <select name="service_tag" class="form-select">
                            <option value="">-- Select Service --</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo htmlspecialchars($service); ?>" 
                                    <?php echo $testimonial['service_tag'] === $service ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Customer Role/Title</label>
                        <input type="text" name="role" class="form-control" 
                               value="<?php echo htmlspecialchars($testimonial['role']); ?>">
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Testimonial Text *</label>
                <textarea name="testimonial_text" class="form-control" rows="6" required><?php echo htmlspecialchars($testimonial['testimonial_text']); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" 
                       value="<?php echo $testimonial['sort_order']; ?>">
                <small class="text-muted">Lower numbers appear first</small>
            </div>
            
            <button type="submit" class="btn btn-primary">Update Testimonial</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>