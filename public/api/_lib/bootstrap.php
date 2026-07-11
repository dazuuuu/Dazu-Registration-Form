<?php
/**
 * Shared bootstrap for JSON API endpoints consumed by the React frontend.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../app/v1/config/paths.php';
require_once __DIR__ . '/respond.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin !== '' && (str_contains($origin, $host) || str_contains($origin, 'localhost') || str_contains($origin, '127.0.0.1'))) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Accept');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';

use Models\Connection;

function api_pdo(): PDO
{
    return Connection::getInstance()->getConnection();
}

function api_require_auth(): array
{
    if (empty($_SESSION['user_id'])) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    return [
        'id' => (int) $_SESSION['user_id'],
        'email' => $_SESSION['email'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'role_id' => (int) ($_SESSION['role_id'] ?? 0),
    ];
}

function api_require_role(string ...$roles): array
{
    $user = api_require_auth();
    if (!in_array($user['role'], $roles, true)) {
        json_response(['success' => false, 'message' => 'Forbidden'], 403);
    }
    return $user;
}

function api_shape_course(array $c): array
{
    $cover = $c['cover_image'] ?? '';
    if ($cover !== '' && !preg_match('#^https?://#i', $cover)) {
        $cover = url(ltrim($cover, '/'));
    }
    return [
        'id' => (int) ($c['id'] ?? 0),
        'title' => $c['title'] ?? '',
        'abbreviation' => $c['abbreviation'] ?? '',
        'overview' => $c['overview'] ?? '',
        'description' => $c['description'] ?? '',
        'duration_months' => (int) ($c['duration_months'] ?? 0),
        'course_fee' => (float) ($c['course_fee'] ?? 0),
        'cover_image' => $cover,
        'is_active' => !empty($c['is_active']),
        'rating' => $c['rating'] ?? '4.8',
    ];
}

function api_shape_testimonial(array $t): array
{
    $avatar = $t['avatar_url'] ?? '';
    if ($avatar !== '' && !preg_match('#^https?://#i', $avatar)) {
        $avatar = url(ltrim($avatar, '/'));
    }
    $thumb = $t['thumbnail_url'] ?? '';
    if ($thumb !== '' && !preg_match('#^https?://#i', $thumb)) {
        $thumb = url(ltrim($thumb, '/'));
    }
    $video = $t['video_url'] ?? '';
    if ($video !== '' && !preg_match('#^https?://#i', $video) && str_starts_with($video, '/')) {
        $video = url(ltrim($video, '/'));
    }
    return [
        'id' => (int) ($t['id'] ?? 0),
        'customer_name' => $t['customer_name'] ?? '',
        'role' => $t['role'] ?? '',
        'service_tag' => $t['service_tag'] ?? '',
        'rating' => (int) ($t['rating'] ?? 5),
        'testimonial_text' => $t['testimonial_text'] ?? '',
        'testimonial_type' => $t['testimonial_type'] ?? 'text',
        'video_url' => $video,
        'video_provider' => $t['video_provider'] ?? 'none',
        'avatar_url' => $avatar,
        'thumbnail_url' => $thumb,
        'is_featured' => !empty($t['is_featured']),
    ];
}
