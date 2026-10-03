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
    $tutor_id = (int)$_SESSION['user_id'];
    $student_id = (int)$_POST['student_id'];
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    
    $pdo = Connection::getInstance()->getConnection();
    
    // Get tutor ID
    $stmt = $pdo->prepare("SELECT id FROM tutors WHERE user_id = ?");
    $stmt->execute([$tutor_id]);
    $tutor = $stmt->fetch();
    
    if ($tutor && $title && $message) {
        $stmt = $pdo->prepare("INSERT INTO tutor_announcements (tutor_id, student_id, title, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tutor['id'], $student_id, $title, $message]);
        
        \Models\SessionModel::setFlash('success', 'Announcement sent to student.');
    } else {
        \Models\SessionModel::setFlash('error', 'Failed to send announcement. Please check your inputs.');
    }
    
    header('Location: view.php?id=' . $student_id);
    exit;
}
