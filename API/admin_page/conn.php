<?php
$host = 'localhost';
$db   = 'project_data';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage()
    ]);
    exit();
}

$api_base_url = 'http://localhost/project/';
$image_folder = 'img/';

$base_image_url = $api_base_url . $image_folder;
$default_image_url = $base_image_url . 'default.png';

function get_full_image_url($image_filename_from_db = null) {
    global $base_image_url, $default_image_url, $image_folder;

    // เช็คถ้าเป็น default_profile.png หรือค่าว่างให้ return null
    if (empty($image_filename_from_db) || 
        $image_filename_from_db === 'default_profile.png' || 
        $image_filename_from_db === 'default.png') {
        return null; // return null เพื่อให้ frontend ใช้ default avatar
    }

    $image_filename_from_db = trim($image_filename_from_db);

    // Remove duplicate protocol if exists (e.g. http://http://...)
    $image_filename_from_db = preg_replace('/^(https?:\/\/)+/i', 'http://', $image_filename_from_db);

    // ถ้าเป็น URL เต็ม (http/https) ให้ return เลย
    if (preg_match('/^https?:\/\//i', $image_filename_from_db)) {
        return $image_filename_from_db;
    }

    // ตัด prefix ออกถ้ามี
    if (str_starts_with($image_filename_from_db, $base_image_url)) {
        $image_filename_from_db = substr($image_filename_from_db, strlen($base_image_url));
    } else if (str_starts_with($image_filename_from_db, $image_folder)) {
        $image_filename_from_db = substr($image_filename_from_db, strlen($image_folder));
    }

    return $base_image_url . ltrim($image_filename_from_db, '/');
}

// Function สำหรับผู้ใช้ที่ต้องการ default image URL
function get_user_profile_url($image_filename_from_db = null) {
    global $base_image_url;
    
    // ถ้าเป็น default หรือไม่มีรูป ให้ return URL ของรูป default
    if (empty($image_filename_from_db) || 
        $image_filename_from_db === 'default_profile.png' || 
        $image_filename_from_db === 'default.png') {
        return $base_image_url . 'default_profile.png'; // return URL ของ default image
    }
    
    return get_full_image_url($image_filename_from_db);
}