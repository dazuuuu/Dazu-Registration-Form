<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';

use Models\Connection;

Middleware::requireTutor();

// POST-only endpoint (forms on the student page submit here); a direct visit goes back to the student list.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('tutor/students/index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tutor_id = (int)$_SESSION['user_id']; // Actually needs tutor profile ID
    $student_id = (int)$_POST['student_id'];
    $timeline_days = (int)$_POST['timeline_days'];
    $start_date = $_POST['start_date'] ?: date('Y-m-d');
    
    $pdo = Connection::getInstance()->getConnection();
    
    // Get tutor ID
    $stmt = $pdo->prepare("SELECT id FROM tutors WHERE user_id = ?");
    $stmt->execute([$tutor_id]);
    $tutor = $stmt->fetch();
    
    if ($tutor) {
        $stmt = $pdo->prepare("UPDATE tutor_students SET timeline_days = ?, start_date = ? WHERE tutor_id = ? AND user_id = ?");
        $stmt->execute([$timeline_days, $start_date, $tutor['id'], $student_id]);
        
        SessionModel::setFlash('success', 'Timeline updated successfully.');
    }
    
    header('Location: view.php?id=' . (int) ($_POST['reg_id'] ?? 0));
    exit;
}
